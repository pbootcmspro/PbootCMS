<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2026年8月7日
 *  内容访问量增量聚合与批量回写（fix #27）
 */
namespace app\common;

use core\basic\Model;
use core\database\Mysqli;
use core\database\Pdo;
use core\database\Sqlite;

class VisitsCounter
{

    /** @var int 分片数量，降低单文件锁竞争范围 */
    const SHARD_COUNT = 256;

    /** @var int 单条内容本地增量达到该值时触发回写 */
    const BATCH_THRESHOLD = 20;

    /** @var int 距上次回写超过该秒数且有待刷增量时触发回写 */
    const FLUSH_INTERVAL = 5;

    /**
     * @var int 写库中的增量滞留超过该秒数视为进程中断遗留，可由其他进程重新提交。
     *      取值需明显大于单次 UPDATE 的最坏耗时，否则数据库严重阻塞时会重复计数。
     */
    const SENDING_TIMEOUT = 60;

    /** @var int 全量补偿扫描的最小间隔秒数，避免每个请求都遍历所有分片 */
    const RECOVERY_INTERVAL = 60;

    /** @var int 单次补偿最多处理的分片数，避免某个请求背负全部回写 */
    const RECOVERY_SHARD_LIMIT = 32;

    /** @var int 无增量条目闲置超过该秒数后从分片中回收，避免分片文件无限增长 */
    const PRUNE_IDLE = 3600;

    /** @var int 销账失败重试次数；销账丢失会导致增量被超时回收后重复入库 */
    const SETTLE_RETRY = 3;

    /** @var int 分片原子替换失败时的重试次数（Windows 上读者占句柄时 rename 易失败） */
    const RENAME_RETRY = 3;

    /** @var callable|null 测试注入：function (int $id, int $pending): bool */
    private static $flushHandler = null;

    /** @var int 测试覆盖阈值 */
    private static $batchThreshold = self::BATCH_THRESHOLD;

    /** @var int 测试覆盖间隔 */
    private static $flushInterval = self::FLUSH_INTERVAL;

    /** @var int 测试覆盖提交超时 */
    private static $sendingTimeout = self::SENDING_TIMEOUT;

    /** @var int 测试覆盖补偿扫描间隔 */
    private static $recoveryInterval = self::RECOVERY_INTERVAL;

    /** @var bool */
    private static $shutdownRegistered = false;

    /** @var bool 是否注册 shutdown 刷盘（探测进程可关闭） */
    private static $shutdownEnabled = true;

    /** @var bool 是否在首次计数时补偿刷盘（探测进程可关闭） */
    private static $recoveryEnabled = true;

    /** @var string|null 测试覆盖 RUN_PATH */
    private static $storageRunPath = null;

    /** @var bool 进程内是否已尝试补偿刷盘 */
    private static $recoveryAttempted = false;

    /** @var array<int,int>|null 观察窗口：统计单次调用内已成功入库的增量 */
    private static $committedWatch = null;

    /** @var array<int,array> 展示路径的分片读缓存，写入时失效 */
    private static $shardReadCache = array();

    /** @var array<int,bool> 本次请求写过的分片，关停刷盘只处理这些分片 */
    private static $touchedShards = array();

    /** @var bool 测试：模拟分片落盘失败 */
    private static $saveFailure = false;

    /** @var int 写库中记录键的进程内自增序号 */
    private static $sendingSeq = 0;

    /**
     * 记录一次访问（热路径：仅写本地增量，仅在到期时才批量回写）
     *
     * @param int $id 内容 ID
     * @return bool
     */
    public static function incr($id)
    {
        $id = (int) $id;
        if ($id <= 0) {
            return false;
        }

        self::maybeRecoverPending();
        self::registerShutdownFlush();

        $marked = self::lockedShardMutate(self::shardIndex($id), function (array &$data, &$dirty) use ($id) {
            $now = time();
            $entry = self::entry($data, $id);
            self::reclaimStaleSending($entry, $now);
            if ($entry['last_flush'] <= 0) {
                $entry['last_flush'] = $now; // 新建条目从首次访问起计时，使间隔触发生效
            }

            $entry['delta'] ++;
            $marked = array(
                'key' => '',
                'pending' => 0
            );
            // 热路径只按阈值触发；间隔触发交给关停刷盘与补偿扫描，
            // 否则低频内容每次访问都会在请求内同步写库，失去批量聚合意义
            if (self::isDue($entry, $now, false, false)) {
                $marked['pending'] = $entry['delta'];
                $marked['key'] = self::markSending($entry, $marked['pending'], $now);
            }

            $data[$id] = $entry;
            $dirty = true;
            return $marked;
        });

        if ($marked === false) {
            self::logFailure('incr failed to lock/save shard for content ' . $id);
            return false;
        }
        if ($marked['pending'] > 0) {
            if (! self::commitFlush($id, $marked['key'], $marked['pending'])) {
                self::logFailure('incr commitFlush failed for content ' . $id);
            }
        }
        return true;
    }

    /**
     * 记录一次访问并返回展示用访问量。
     * $dbVisits 为计数前读取的数据库快照，若本次调用触发了回写，
     * 快照不含刚写入的增量，需由已入库增量补回，避免展示值低于实际。
     *
     * @param int $id
     * @param int $dbVisits 计数前的数据库快照值
     * @return int
     */
    public static function incrAndGetVisits($id, $dbVisits)
    {
        $id = (int) $id;
        if ($id <= 0) {
            return (int) $dbVisits;
        }

        $prevWatch = self::$committedWatch;
        self::$committedWatch = array(
            $id => 0
        );
        try {
            self::incr($id);
            $committed = (int) self::$committedWatch[$id];
        } finally {
            self::$committedWatch = $prevWatch;
        }

        return (int) $dbVisits + $committed + self::getPending($id);
    }

    /**
     * 强制回写指定内容的待刷增量
     *
     * @param int $id
     * @return bool
     */
    public static function flush($id)
    {
        $id = (int) $id;
        if ($id <= 0) {
            return false;
        }

        $marked = self::lockedShardMutate(self::shardIndex($id), function (array &$data, &$dirty) use ($id) {
            $now = time();
            $entry = self::entry($data, $id);
            if (self::reclaimStaleSending($entry, $now)) {
                $dirty = true;
            }

            $marked = array(
                'key' => '',
                'pending' => $entry['delta']
            );
            if ($marked['pending'] > 0) {
                $marked['key'] = self::markSending($entry, $marked['pending'], $now);
                $dirty = true;
            }
            if ($dirty) {
                $data[$id] = $entry;
            }
            return $marked;
        });

        if ($marked === false) {
            return false;
        }
        if ($marked['pending'] <= 0) {
            return true;
        }

        return self::commitFlush($id, $marked['key'], $marked['pending']);
    }

    /**
     * 强制回写所有分片中的待刷增量，忽略阈值与间隔
     *
     * @return bool
     */
    public static function flushAll()
    {
        return self::flushShards(self::listExistingShards(), true);
    }

    /**
     * 仅回写已到期（达阈值或超过间隔）的增量，保持批量聚合效果
     *
     * @param int[]|null $shards 为空时处理磁盘上所有分片
     * @return bool
     */
    public static function flushDue($shards = null)
    {
        if (! is_array($shards)) {
            $shards = self::listExistingShards();
        }
        if (! $shards) {
            return true;
        }
        return self::flushShards($shards, false);
    }

    /**
     * 读取尚未确认入库的本地增量（含正在写库的部分）
     *
     * @param int $id
     * @return int
     */
    public static function getPending($id)
    {
        $id = (int) $id;
        if ($id <= 0) {
            return 0;
        }

        $entry = self::entry(self::loadShardForRead(self::shardIndex($id)), $id);
        return $entry['delta'] + self::sendingTotal($entry);
    }

    /**
     * 展示用访问量：数据库值 + 尚未确认入库的增量
     *
     * @param int $id
     * @param int $dbVisits
     * @return int
     */
    public static function getDisplayVisits($id, $dbVisits)
    {
        return (int) $dbVisits + self::getPending($id);
    }

    /** 测试：注入回写处理器 */
    public static function setFlushHandler($handler)
    {
        self::$flushHandler = $handler;
    }

    /** 测试：重置注入与阈值 */
    public static function resetTestState()
    {
        self::$flushHandler = null;
        self::$batchThreshold = self::BATCH_THRESHOLD;
        self::$flushInterval = self::FLUSH_INTERVAL;
        self::$sendingTimeout = self::SENDING_TIMEOUT;
        self::$recoveryInterval = self::RECOVERY_INTERVAL;
        self::$shutdownRegistered = false;
        self::$shutdownEnabled = true;
        self::$recoveryEnabled = true;
        self::$storageRunPath = null;
        self::$recoveryAttempted = false;
        self::$committedWatch = null;
        self::$shardReadCache = array();
        self::$touchedShards = array();
        self::$saveFailure = false;
        self::$sendingSeq = 0;
    }

    /** 测试/探测：关闭进程结束时的到期刷盘 */
    public static function disableShutdownFlush()
    {
        self::$shutdownEnabled = false;
    }

    /** 测试：恢复进程结束时的到期刷盘 */
    public static function enableShutdownFlush()
    {
        self::$shutdownEnabled = true;
    }

    /** 测试/探测：关闭首次计数时的补偿刷盘 */
    public static function disableRecoveryFlush()
    {
        self::$recoveryEnabled = false;
    }

    /** 测试：恢复首次计数时的补偿刷盘 */
    public static function enableRecoveryFlush()
    {
        self::$recoveryEnabled = true;
    }

    /** 测试：指定增量文件目录所在 run 路径 */
    public static function setStorageRunPath($runPath)
    {
        self::$storageRunPath = $runPath ? rtrim(str_replace('\\', '/', $runPath), '/') : null;
        self::$shardReadCache = array();
        self::$touchedShards = array();
    }

    /** 测试：模拟分片落盘失败 */
    public static function simulateSaveFailure($enabled)
    {
        self::$saveFailure = (bool) $enabled;
    }

    /** 测试：降低批量阈值以触发刷盘 */
    public static function setBatchThreshold($threshold)
    {
        self::$batchThreshold = max(1, (int) $threshold);
    }

    /** 测试：降低刷盘间隔 */
    public static function setFlushInterval($seconds)
    {
        self::$flushInterval = max(1, (int) $seconds);
    }

    /** 测试：调整写库中增量的滞留回收时间 */
    public static function setSendingTimeout($seconds)
    {
        self::$sendingTimeout = max(0, (int) $seconds);
    }

    /** 测试：调整全量补偿扫描间隔 */
    public static function setRecoveryInterval($seconds)
    {
        self::$recoveryInterval = max(0, (int) $seconds);
    }

    /** 测试：清空本地增量目录 */
    public static function clearStorage($runPath = null)
    {
        $dir = self::getDataDir($runPath);
        if (is_dir($dir)) {
            path_delete($dir);
        }
        self::$shardReadCache = array();
        self::$touchedShards = array();
    }

    /**
     * 分片级批量回写：锁内把到期增量转入 sending 并落盘，锁外再写库。
     * 落盘失败时跳过本批入库，增量仍留在磁盘等下次重试，避免重复计数。
     */
    private static function flushShards(array $shards, $force)
    {
        $ok = true;

        foreach ($shards as $shard) {
            $shard = (int) $shard;
            $batch = self::prepareShardBatch($shard, $force);
            if ($batch === false) {
                $ok = false; // 增量仍在磁盘上，下次刷盘重试
                continue;
            }
            if (! $batch) {
                continue;
            }

            // 每条写后立刻 commit：同事务后一条失败会 rollback 前一条，
            // 若仍按「曾成功」销账会导致增量永久丢失（database is locked 等高并发场景）
            foreach ($batch as $index => $item) {
                $committed = self::flushToDatabase($item['id'], $item['pending']);
                if ($committed && ! self::commitDatabase()) {
                    $committed = false; // 未确认落盘，退回本地待刷
                }
                if (! $committed) {
                    $ok = false;
                }
                $batch[$index]['committed'] = $committed;
            }

            // 同分片一次加锁完成整批销账，避免逐条读改写放大锁竞争
            if (! self::settleShardBatch($shard, $batch)) {
                $ok = false;
            }

            // 入库成功且 sending 已销掉才记入观察窗口；销账失败时 sending 仍在，由 getPending 补展示
            foreach ($batch as $item) {
                if (! empty($item['committed']) && self::sendingKeyMissing($item['id'], $item['key'])) {
                    self::recordCommitted($item['id'], $item['pending']);
                }
            }
        }

        return $ok;
    }

    /**
     * 锁内把分片中到期的增量转入写库中状态并落盘，返回待提交批次。
     * 落盘失败时不返回批次，避免磁盘仍持有增量却已入库造成重复计数。
     *
     * @return array|false false=加锁或落盘失败
     */
    private static function prepareShardBatch($shard, $force)
    {
        $lockFp = self::acquireShardLock($shard);
        if ($lockFp === false) {
            return false;
        }

        try {
            $data = self::loadShard($shard, true);
            if (! $data) {
                // 空分片文件直接回收，避免每次扫描重复读取（损坏文件已在 loadShard 隔离）
                if (file_exists(self::shardFile($shard))) {
                    self::persistShard($shard, array());
                }
                return array();
            }

            $batch = array();
            $dirty = false;
            $now = time();
            foreach ($data as $contentId => $item) {
                $entry = self::normalizeEntry($item);
                if (self::reclaimStaleSending($entry, $now)) {
                    $dirty = true;
                }

                if ($entry['delta'] > 0 && self::isDue($entry, $now, $force)) {
                    $pending = $entry['delta'];
                    $batch[] = array(
                        'id' => (int) $contentId,
                        'key' => self::markSending($entry, $pending, $now),
                        'pending' => $pending
                    );
                    $dirty = true;
                }

                if (self::isPrunable($entry, $now)) {
                    unset($data[$contentId]);
                    $dirty = true;
                    continue;
                }
                $data[$contentId] = $entry;
            }

            if ($dirty && ! self::persistShard($shard, $data)) {
                return false;
            }
            return $batch;
        } finally {
            self::releaseShardLock($lockFp);
        }
    }

    /**
     * 提交单条增量到数据库。
     * 调用前增量已在磁盘上从 delta 转入 sending，进程中断时不会丢失，
     * 由其他进程在 SENDING_TIMEOUT 后重新提交。
     */
    private static function commitFlush($id, $key, $pending)
    {
        $pending = (int) $pending;
        if ($pending <= 0) {
            return true;
        }

        $committed = self::flushToDatabase($id, $pending);
        if ($committed && ! self::commitDatabase()) {
            $committed = false; // 未确认落盘，增量退回本地待刷
        }
        $settled = self::settleShardBatch(self::shardIndex($id), array(
            array(
                'id' => (int) $id,
                'key' => $key,
                'pending' => $pending,
                'committed' => $committed
            )
        ));
        // 入库成功且 sending 已销掉才记入观察窗口，避免与 getPending 重复加算
        if ($committed && self::sendingKeyMissing($id, $key)) {
            self::recordCommitted($id, $pending);
        }
        return $committed && $settled;
    }

    /** 指定写库中记录是否已不在磁盘（已销账或已被超时回收） */
    private static function sendingKeyMissing($id, $key)
    {
        // 销账判定属正确性路径，直读磁盘，不依赖展示缓存被顺带失效
        $entry = self::entry(self::loadShard(self::shardIndex($id)), $id);
        return ! isset($entry['sending'][$key]);
    }

    /**
     * 批量结算同一分片的写库中记录：已入库的销账，失败的退回本地待刷。
     * 记录已被其他进程按超时回收时按 key 判定跳过，避免退回时重复计数。
     * 销账若最终失败，增量会在 SENDING_TIMEOUT 后被回收重放，因此需要重试。
     */
    private static function settleShardBatch($shard, array $records)
    {
        if (! $records) {
            return true;
        }

        for ($attempt = 0; $attempt < self::SETTLE_RETRY; $attempt ++) {
            $result = self::lockedShardMutate($shard, function (array &$data, &$dirty) use ($records) {
                foreach ($records as $record) {
                    $id = (int) $record['id'];
                    $entry = self::entry($data, $id);
                    if (! isset($entry['sending'][$record['key']])) {
                        continue; // 已被超时回收，销账与退回都不再需要
                    }
                    unset($entry['sending'][$record['key']]);
                    if (empty($record['committed'])) {
                        $entry['delta'] += (int) $record['pending'];
                    }
                    $data[$id] = $entry;
                    $dirty = true;
                }
                return true;
            });
            if ($result !== false) {
                return true;
            }
            if ($attempt < self::SETTLE_RETRY - 1) {
                usleep(20000);
            }
        }
        self::logFailure('settleShardBatch failed for shard ' . $shard);
        return false;
    }

    private static function recordCommitted($id, $pending)
    {
        $id = (int) $id;
        if (self::$committedWatch === null || ! array_key_exists($id, self::$committedWatch)) {
            return;
        }
        self::$committedWatch[$id] += (int) $pending;
    }

    private static function flushToDatabase($id, $pending)
    {
        if (self::$flushHandler) {
            try {
                return (bool) call_user_func(self::$flushHandler, $id, $pending);
            } catch (\Throwable $e) {
                return false;
            }
        }

        return self::withFailSoftDb(function () use ($id, $pending) {
            try {
                $model = new Model();
                $model->table('ay_content')->where('id=' . (int) $id)->update(array(
                    'visits' => '+=' . (int) $pending
                ));
                return ! self::dbHadFailSoftError();
            } catch (\Throwable $e) {
                return false;
            }
        });
    }

    /**
     * 确认写入落盘。SQLite / pdo_sqlite 每次写入自动开启隐式事务，
     * 仅在连接析构时提交；销账前不提交，后续 SQL 出错回滚会永久丢失增量。
     */
    private static function commitDatabase()
    {
        if (self::$flushHandler) {
            return true;
        }

        return self::withFailSoftDb(function () {
            try {
                $model = new Model();
                $model->commit();
                return ! self::dbHadFailSoftError();
            } catch (\Throwable $e) {
                return false;
            }
        });
    }

    /**
     * 刷盘专用软失败通道：SQL 错误时由驱动回滚并返回 false，不 error()+exit。
     */
    private static function withFailSoftDb(callable $fn)
    {
        Sqlite::setFailSoft(true);
        Pdo::setFailSoft(true);
        Mysqli::setFailSoft(true);
        try {
            return $fn();
        } finally {
            Sqlite::setFailSoft(false);
            Pdo::setFailSoft(false);
            Mysqli::setFailSoft(false);
        }
    }

    /** 当前刷盘调用是否在驱动层发生过 SQL 软失败 */
    private static function dbHadFailSoftError()
    {
        return Sqlite::hadFailSoftError() || Pdo::hadFailSoftError() || Mysqli::hadFailSoftError();
    }

    /**
     * 是否到达回写时机
     *
     * @param bool $allowInterval 是否允许间隔触发；热路径传 false，仅按阈值批量
     */
    private static function isDue(array $entry, $now, $force, $allowInterval = true)
    {
        if ($force || $entry['delta'] >= self::$batchThreshold) {
            return true;
        }
        if (! $allowInterval) {
            return false;
        }
        if ($entry['last_flush'] <= 0) {
            return true; // 旧版本遗留条目无计时基准，尽快归库
        }
        return ($now - $entry['last_flush']) >= self::$flushInterval;
    }

    /** 长期无增量的条目可回收，避免分片文件随内容量无限增长 */
    private static function isPrunable(array $entry, $now)
    {
        return $entry['delta'] === 0 && ! $entry['sending']
            && ($now - $entry['last_flush']) >= self::PRUNE_IDLE;
    }

    /**
     * 将本地增量转入“写库中”，进程中断时留痕以便重放。
     * 每次提交独立成一条带时间戳的记录，后续提交不会刷新既有记录的时间，
     * 中断遗留的记录仍能按自身时间被回收。
     *
     * @return string 本笔提交的记录键，用于后续销账
     */
    private static function markSending(array &$entry, $amount, $now)
    {
        $amount = (int) $amount;
        $entry['delta'] = max(0, $entry['delta'] - $amount);
        $key = self::nextSendingKey();
        $entry['sending'][$key] = array(
            'amount' => $amount,
            'at' => $now
        );
        $entry['last_flush'] = $now;
        return $key;
    }

    /** 记录键需在多进程间唯一，PID 复用时靠序号与随机数区分 */
    private static function nextSendingKey()
    {
        self::$sendingSeq ++;
        return 's' . \process_instance_id() . '-' . self::$sendingSeq . '-' . mt_rand(100000, 999999);
    }

    /**
     * 回收进程中断遗留的“写库中”增量，重新计入待刷
     *
     * @return bool 是否发生回收
     */
    private static function reclaimStaleSending(array &$entry, $now)
    {
        $reclaimed = false;
        foreach ($entry['sending'] as $key => $record) {
            if (($now - (int) $record['at']) < self::$sendingTimeout) {
                continue;
            }
            $entry['delta'] += (int) $record['amount'];
            unset($entry['sending'][$key]);
            $reclaimed = true;
        }
        return $reclaimed;
    }

    /** 写库中记录的合计增量 */
    private static function sendingTotal(array $entry)
    {
        $total = 0;
        foreach ($entry['sending'] as $record) {
            $total += (int) $record['amount'];
        }
        return $total;
    }

    /**
     * 单分片加锁读改写。闭包通过 $dirty 声明是否需要落盘，避免无变更时空写。
     *
     * @param int $shard 分片编号
     * @return mixed false=加锁或落盘失败；其余为闭包返回值
     */
    private static function lockedShardMutate($shard, callable $mutator)
    {
        $shard = (int) $shard;
        $lockFp = self::acquireShardLock($shard);
        if ($lockFp === false) {
            return false;
        }

        try {
            $data = self::loadShard($shard, true);
            $dirty = false;
            $result = $mutator($data, $dirty);
            if ($dirty) {
                if (! self::saveShard($shard, $data)) {
                    return false;
                }
                self::$touchedShards[$shard] = true;
            }
            return $result;
        } finally {
            self::releaseShardLock($lockFp);
        }
    }

    /**
     * 补偿刷盘：回收进程中断、数据库暂时失败与低流量内容遗留的增量。
     * 全量扫描按 RECOVERY_INTERVAL 限流，并用非阻塞锁保证同一时刻只有一个进程执行。
     * 每次最多处理 RECOVERY_SHARD_LIMIT 个分片，用游标轮转，避免单请求背负全部回写。
     */
    private static function maybeRecoverPending()
    {
        if (! self::$recoveryEnabled || self::$recoveryAttempted) {
            return;
        }
        self::$recoveryAttempted = true;

        // 限流判断先行，避免每个请求都为被限流的扫描白跑一次 glob
        $marker = self::getDataDir() . '/recover.lock';
        clearstatcache(true, $marker);
        if (file_exists($marker) && (time() - (int) @filemtime($marker)) < self::$recoveryInterval) {
            return;
        }

        $shards = self::listExistingShards();
        if (! $shards) {
            return;
        }

        check_dir(self::getDataDir(), true);
        $fp = @fopen($marker, 'c');
        if ($fp === false) {
            return;
        }
        if (! flock($fp, LOCK_EX | LOCK_NB)) {
            fclose($fp);
            return;
        }
        try {
            @touch($marker);
            clearstatcache(true, $marker);
            $batch = self::nextRecoveryShardBatch($shards);
            if ($batch && ! self::flushDue($batch)) {
                self::logFailure('recovery flushDue failed for shards: ' . implode(',', $batch));
            }
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    /**
     * 按游标取出本轮要补偿的分片，并推进游标以便下次从后续分片继续。
     *
     * @param int[] $shards
     * @return int[]
     */
    private static function nextRecoveryShardBatch(array $shards)
    {
        $count = count($shards);
        if ($count <= 0) {
            return array();
        }
        if ($count <= self::RECOVERY_SHARD_LIMIT) {
            return $shards;
        }

        $cursorFile = self::getDataDir() . '/recover.cursor';
        $cursor = 0;
        if (is_file($cursorFile)) {
            $raw = @file_get_contents($cursorFile);
            if ($raw !== false && is_numeric(trim($raw))) {
                $cursor = (int) trim($raw);
            }
        }
        if ($cursor < 0) {
            $cursor = 0;
        }
        $cursor = $cursor % $count;

        $batch = array();
        for ($i = 0; $i < self::RECOVERY_SHARD_LIMIT; $i ++) {
            $batch[] = $shards[($cursor + $i) % $count];
        }
        $next = ($cursor + self::RECOVERY_SHARD_LIMIT) % $count;
        @file_put_contents($cursorFile, (string) $next, LOCK_EX);
        return $batch;
    }

    private static function registerShutdownFlush()
    {
        if (! self::$shutdownEnabled || self::$shutdownRegistered) {
            return;
        }
        self::$shutdownRegistered = true;
        register_shutdown_function(array(__CLASS__, 'shutdownFlush'));
    }

    /**
     * 进程结束前只回写本次请求写过的分片中已到期的增量，
     * 未到期的增量留在磁盘继续聚合，由后续请求或补偿刷盘归库。
     */
    public static function shutdownFlush()
    {
        if (! self::$touchedShards) {
            return;
        }
        // 此时页面已输出，驱动层错误页与 headers-sent 警告一律丢弃，避免污染页面尾部；
        // error() 内的 exit 会跳过 finally，但关停时仍会调用该回调，返回空串即被吞掉
        ob_start(function () {
            return '';
        });
        try {
            self::flushDue(array_keys(self::$touchedShards));
        } finally {
            ob_end_clean();
        }
    }

    private static function getDataDir($runPath = null)
    {
        if ($runPath === null && self::$storageRunPath !== null) {
            $runPath = self::$storageRunPath;
        }
        $base = $runPath ?: (defined('RUN_PATH') ? RUN_PATH : ROOT_PATH . '/runtime');
        return rtrim(str_replace('\\', '/', $base), '/') . '/data/visits';
    }

    private static function shardIndex($id)
    {
        return ((int) $id) % self::SHARD_COUNT;
    }

    private static function shardFile($shard)
    {
        return self::getDataDir() . '/shard_' . (int) $shard . '.json';
    }

    private static function shardLockFile($shard)
    {
        return self::getDataDir() . '/shard_' . (int) $shard . '.lock';
    }

    /** @return int[] 仅返回磁盘上已存在的分片编号，避免冷启动空扫 256 片 */
    private static function listExistingShards()
    {
        $dir = self::getDataDir();
        if (! is_dir($dir)) {
            return array();
        }
        $files = glob($dir . '/shard_*.json');
        if (! is_array($files) || ! $files) {
            return array();
        }
        $shards = array();
        foreach ($files as $file) {
            if (preg_match('/^shard_(\d+)\.json$/', basename($file), $matches)) {
                $shards[] = (int) $matches[1];
            }
        }
        sort($shards, SORT_NUMERIC);
        return $shards;
    }

    /** @return resource|false */
    private static function acquireShardLock($shard)
    {
        check_dir(self::getDataDir(), true);
        $fp = @fopen(self::shardLockFile($shard), 'c');
        if ($fp === false || ! flock($fp, LOCK_EX)) {
            if ($fp) {
                fclose($fp);
            }
            return false;
        }
        return $fp;
    }

    /** @param resource $fp */
    private static function releaseShardLock($fp)
    {
        flock($fp, LOCK_UN);
        fclose($fp);
    }

    private static function entry(array $data, $id)
    {
        $id = (int) $id;
        return self::normalizeEntry(isset($data[$id]) ? $data[$id] : null);
    }

    private static function normalizeEntry($item)
    {
        if (! is_array($item)) {
            $item = array();
        }
        return array(
            'delta' => isset($item['delta']) && is_numeric($item['delta']) ? max(0, (int) $item['delta']) : 0,
            'sending' => self::normalizeSending($item),
            'last_flush' => isset($item['last_flush']) && is_numeric($item['last_flush']) ? (int) $item['last_flush'] : 0
        );
    }

    /**
     * 归一化写库中记录，兼容旧格式（sending 为标量 + sending_at）
     *
     * @return array<string,array> key => array('amount' => int, 'at' => int)
     */
    private static function normalizeSending(array $item)
    {
        $legacyAt = isset($item['sending_at']) && is_numeric($item['sending_at']) ? (int) $item['sending_at'] : 0;
        $sending = isset($item['sending']) ? $item['sending'] : null;

        if (is_numeric($sending)) {
            $amount = max(0, (int) $sending);
            return $amount > 0 ? array(
                'legacy' => array(
                    'amount' => $amount,
                    'at' => $legacyAt
                )
            ) : array();
        }
        if (! is_array($sending)) {
            return array();
        }

        $records = array();
        foreach ($sending as $key => $record) {
            if (! is_array($record) || ! isset($record['amount']) || ! is_numeric($record['amount'])) {
                continue;
            }
            $amount = max(0, (int) $record['amount']);
            if ($amount <= 0) {
                continue;
            }
            $records[(string) $key] = array(
                'amount' => $amount,
                'at' => isset($record['at']) && is_numeric($record['at']) ? (int) $record['at'] : $legacyAt
            );
        }
        return $records;
    }

    /** 展示路径复用同一请求内的分片内容，避免列表逐条读盘；加锁读写始终走 loadShard */
    private static function loadShardForRead($shard)
    {
        if (! isset(self::$shardReadCache[$shard])) {
            self::$shardReadCache[$shard] = self::loadShard($shard);
        }
        return self::$shardReadCache[$shard];
    }

    /**
     * @param bool $allowQuarantine 仅持锁路径可隔离损坏文件；展示读路径禁止 rename，
     *                              避免无锁读者把刚写好的正常分片误隔离
     */
    private static function loadShard($shard, $allowQuarantine = false)
    {
        $file = self::shardFile($shard);
        if (! file_exists($file)) {
            return array();
        }
        $raw = @file_get_contents($file);
        if ($raw === false || $raw === '') {
            return array();
        }
        $data = json_decode($raw, true);
        if (! is_array($data)) {
            if ($allowQuarantine) {
                // 待刷增量是用户数据，损坏时隔离留证，不可静默丢弃
                @rename($file, $file . '.corrupt.' . time() . '.' . mt_rand(1000, 9999));
                self::$shardReadCache = array();
                self::logFailure('loadShard corrupt json, quarantined shard ' . $shard);
            }
            return array();
        }
        $normalized = array();
        foreach ($data as $contentId => $item) {
            if (! is_numeric($contentId) || ! is_array($item)) {
                continue;
            }
            $normalized[(int) $contentId] = self::normalizeEntry($item);
        }
        return $normalized;
    }

    private static function persistShard($shard, array $data)
    {
        if (self::$saveFailure) {
            return false;
        }
        if (! $data) {
            return self::deleteShard($shard);
        }
        return self::saveShard($shard, $data);
    }

    private static function deleteShard($shard)
    {
        $file = self::shardFile($shard);
        if (file_exists($file) && ! @unlink($file)) {
            return false;
        }
        self::$shardReadCache = array();
        return true;
    }

    private static function saveShard($shard, array $data)
    {
        if (self::$saveFailure) {
            return false;
        }
        check_dir(self::getDataDir(), true);
        $file = self::shardFile($shard);
        $payload = json_encode($data, JSON_UNESCAPED_UNICODE);
        if ($payload === false) {
            return false;
        }
        $tmp = $file . '.' . \process_instance_id() . '.' . mt_rand() . '.tmp';
        if (@file_put_contents($tmp, $payload, LOCK_EX) === false) {
            return false;
        }
        // Windows 上若有进程正读分片文件，rename 可能短暂失败，短重试避免丢增量
        for ($attempt = 0; $attempt < self::RENAME_RETRY; $attempt ++) {
            if (@rename($tmp, $file)) {
                self::$shardReadCache = array();
                return true;
            }
            if ($attempt < self::RENAME_RETRY - 1) {
                usleep(20000);
            }
        }
        @unlink($tmp);
        self::logFailure('saveShard rename failed for shard ' . $shard);
        return false;
    }

    private static function logFailure($message)
    {
        @error_log('[VisitsCounter] ' . $message);
    }
}

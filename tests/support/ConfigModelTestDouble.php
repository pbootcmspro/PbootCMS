<?php

declare(strict_types=1);

use app\admin\model\system\ConfigModel;

/**
 * ConfigModel 内存桩：覆盖 DB 四方法，真实调用 appendIframeWhitelistHosts()
 */
final class ConfigModelTestDouble extends ConfigModel
{
    /** @var array<string, string|null> */
    public $store = array();

    /** @var bool */
    public $configRowExists = true;

    /** @var bool */
    public $writeFails = false;

    /** @var bool */
    public $modCalled = false;

    /** @var bool */
    public $addCalled = false;

    /** @var array|null */
    public $lastAddData = null;

    /**
     * @param array<string, string|null> $store
     */
    public function __construct(array $store = array(), $configRowExists = true)
    {
        $this->store = $store;
        $this->configRowExists = (bool) $configRowExists;
        // 跳过 parent::__construct()，避免触发 Config / DB
    }

    public function getValue($name)
    {
        return array_key_exists($name, $this->store) ? $this->store[$name] : null;
    }

    public function checkConfig($where)
    {
        return $this->configRowExists ? (object) array('id' => 1) : null;
    }

    public function modValue($name, $value)
    {
        $this->modCalled = true;
        if ($this->writeFails) {
            return false;
        }
        $this->store[$name] = $value;
        return true;
    }

    public function addConfig(array $data)
    {
        $this->addCalled = true;
        $this->lastAddData = $data;
        if ($this->writeFails) {
            return false;
        }
        if (isset($data['name'])) {
            $this->store[$data['name']] = isset($data['value']) ? $data['value'] : '';
        }
        return 1;
    }
}

/**
 * 模拟 layui 2.5.7 upload.js 的 fileLength/allDone 与 mylayui 自管批次收尾逻辑。
 * 运行: node tests/multi-upload-batch.test.js
 */
'use strict';

function layuiFileLength(ctx, e) {
  var t = 0;
  var i = e || ctx.files || ctx.chooseFiles || {};
  Object.keys(i).forEach(function () { t++; });
  return t;
}

function simulateLayuiAllDone(ctx, successCount, abortCount) {
  var total = layuiFileLength(ctx, null);
  return ctx.multiple && successCount + abortCount === total;
}

// --- 自管批次（extract from mylayui.js pattern）---
function createBatchManager() {
  var files = '';
  var html = '';
  var finalized = false;
  var inst = {
    _batchFinalized: false,
    _batchPending: 0,
    _batchSuccess: 0,
    item: { data: function () { return 'pics'; } },
    _uploadFiles: {}
  };

  function finalize() {
    if (inst._batchFinalized) return { action: 'skip' };
    inst._batchFinalized = true;
    var result = { action: 'finalize', files: files, html: html, success: inst._batchSuccess };
    files = '';
    html = '';
    return result;
  }

  function tick() {
    if (inst._batchPending <= 0) return finalize();
    return { action: 'pending' };
  }

  return {
    choose: function (count) {
      inst._batchFinalized = false;
      inst._batchPending = count;
      inst._batchSuccess = 0;
    },
    done: function (path) {
      inst._batchSuccess++;
      files = files ? files + ',' + path : path;
      html += '<dl>' + path + '</dl>';
      inst._batchPending--;
      return tick();
    },
    allDone: function () {
      return tick();
    },
    getInst: function () { return inst; }
  };
}

var passed = 0;
var failed = 0;

function assert(name, cond) {
  if (cond) {
    passed++;
    console.log('  OK  ' + name);
  } else {
    failed++;
    console.error('  FAIL ' + name);
  }
}

console.log('=== layui fileLength 回归（第二批 allDone 不触发）===');
var ctx = { multiple: true, files: undefined, chooseFiles: { '1000-0': {} } };
assert('第一批 fileLength=1', layuiFileLength(ctx, null) === 1);
assert('第一批 allDone 可触发', simulateLayuiAllDone(ctx, 1, 0) === true);

ctx.files = ctx.files || {};
ctx.files['1000-0'] = ctx.chooseFiles['1000-0'];
Object.keys(ctx.files).forEach(function (k) { delete ctx.files[k]; });

ctx.chooseFiles = { '2000-0': {} };
assert('第二批 fileLength=0（layui bug）', layuiFileLength(ctx, null) === 0);
assert('第二批 allDone 不触发', simulateLayuiAllDone(ctx, 1, 0) === false);

console.log('\n=== 自管批次收尾 ===');
var mgr = createBatchManager();

mgr.choose(1);
var r1 = mgr.done('/a.jpg');
assert('第一批 done 触发 finalize', r1.action === 'finalize');
assert('第一批写入路径', r1.files === '/a.jpg');

mgr.choose(1);
var r2 = mgr.done('/b.jpg');
assert('第二批 done 触发 finalize', r2.action === 'finalize');
assert('第二批写入路径', r2.files === '/b.jpg');

console.log('\n=== layui.each 无 scope 参数（_batchPending 必须用 inst 计数）===');
(function () {
  var inst = { _batchPending: 0 };
  var chosen = { '1000-0': {}, '1000-1': {}, '1000-2': {} };
  // 模拟错误写法：第三个 this 被 layui.each 忽略
  function layuiEach(obj, fn) {
    for (var i in obj) { fn.call(undefined, i, obj[i]); }
  }
  layuiEach(chosen, function () {
    if (this && this._batchPending !== undefined) this._batchPending++;
  }, inst);
  assert('错误写法 pending 仍为 0', inst._batchPending === 0);
  inst._batchPending = 0;
  layuiEach(chosen, function () { inst._batchPending++; });
  assert('inst 闭包计数 pending=3', inst._batchPending === 3);
})();

console.log('\n=== pending=0 误计数：首张即 finalize（用户所见仅 1 张预览）===');
(function () {
  var inst = { _batchPending: 0, _batchFinalized: false, _batchSuccess: 0 };
  var files = '';
  var html = '';
  function finalize() {
    if (inst._batchFinalized) return { action: 'skip' };
    inst._batchFinalized = true;
    return { action: 'finalize', html: html, files: files };
  }
  function done(path) {
    files = path;
    html = '<dl>' + path + '</dl>';
    inst._batchPending--;
    if (inst._batchPending <= 0) return finalize();
    return { action: 'pending' };
  }
  var r = done('/only.jpg');
  assert('pending 误为 0 时首张 finalize', r.action === 'finalize');
  assert('html 仅 1 项', r.html === '<dl>/only.jpg</dl>');
})();

console.log('\n=== fileLength=1 时 allDone 不提前 finalize（pending 仍>0）===');
(function () {
  var inst = { _batchPending: 3, _batchFinalized: false };
  var files = '';
  function finalize() {
    if (inst._batchFinalized) return { action: 'skip' };
    inst._batchFinalized = true;
    return { action: 'finalize', files: files };
  }
  function tick() {
    if (inst._batchPending <= 0) return finalize();
    return { action: 'pending' };
  }
  files = '/a.jpg';
  inst._batchPending = 2;
  var r = tick();
  assert('allDone 兜底时 pending=2 不 finalize', r.action === 'pending');
})();

console.log('\n=== 幂等：末张 done 与 layui allDone 不重复 finalize ===');
mgr = createBatchManager();
mgr.choose(2);
mgr.done('/c.jpg');
var r4 = mgr.done('/d.jpg');
assert('末张 done 触发 finalize', r4.action === 'finalize');
assert('随后 allDone 幂等跳过', mgr.allDone().action === 'skip');

console.log('\n=== 一次多选 3 + 再一次 2 ===');
mgr = createBatchManager();
mgr.choose(3);
mgr.done('/1.jpg');
mgr.done('/2.jpg');
var batch1 = mgr.done('/3.jpg');
assert('第一批 3 张 finalize', batch1.action === 'finalize' && batch1.success === 3);
mgr.choose(2);
mgr.done('/4.jpg');
var batch2 = mgr.done('/5.jpg');
assert('第二批 2 张 finalize', batch2.action === 'finalize' && batch2.success === 2);

console.log('\n=== 批内部分失败 ===');
mgr = createBatchManager();
mgr.choose(2);
mgr.done('/ok.jpg');
var inst2 = mgr.getInst();
inst2._batchPending--;
var partial = inst2._batchPending <= 0 ? (function () {
  if (inst2._batchFinalized) return { action: 'skip' };
  inst2._batchFinalized = true;
  return { action: 'finalize', success: 1 };
})() : null;
assert('1 成功 1 失败仍 finalize', partial && partial.action === 'finalize');

console.log('\n--- 结果: ' + passed + ' passed, ' + failed + ' failed ---');
process.exit(failed > 0 ? 1 : 0);

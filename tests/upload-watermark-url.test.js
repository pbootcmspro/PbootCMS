/**
 * 模拟 layui 2.13.9 upload 多元素实例拆分，验证 mylayui 水印 URL 切换。
 * 运行: node tests/upload-watermark-url.test.js
 */
'use strict';

var fs = require('fs');
var path = require('path');

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

function hasClass(el, cls) {
  return (' ' + (el.className || '') + ' ').indexOf(' ' + cls + ' ') >= 0;
}

/** 2.13.9：elem 匹配多个时为每个元素克隆 config 建子实例，父实例不发请求 */
function renderMulti(options, elems) {
  var parent = { config: Object.assign({}, options) };
  var children = elems.map(function (el) {
    return { config: Object.assign({}, options, { elem: el, item: null }) };
  });
  return { parent: parent, children: children };
}

function ajaxUrl(inst) {
  return inst.config.url;
}

function clickThenBefore(inst, beforeFn) {
  inst.config.item = inst.config.elem;
  beforeFn.call(inst.config, {});
  return ajaxUrl(inst);
}

var uploadurl = '/admin/Index/upload';

function beforeOldBugParentOnly(parentInst) {
  return function () {
    parentInst.config.url = hasClass(this.item, 'watermark')
      ? uploadurl + '/watermark/1'
      : uploadurl;
  };
}

function beforeFixedThisUrl() {
  this.url = hasClass(this.item, 'watermark')
    ? uploadurl + '/watermark/1'
    : uploadurl;
}

console.log('=== 源码：before 写 this.url 而非 *Inst.config.url ===');
(function () {
  var src = fs.readFileSync(
    path.join(__dirname, '../apps/admin/view/default/js/mylayui.js'),
    'utf8'
  );
  assert('单图 before 使用 this.url', /before:\s*function\s*\([^)]*\)\s*\{[\s\S]*?this\.url\s*=\s*\$\(this\.item\)\.hasClass\('watermark'\)/.test(src));
  assert('多图 before 使用 this.url', (src.match(/this\.url\s*=\s*\$\(this\.item\)\.hasClass\('watermark'\)/g) || []).length >= 2);
  assert('不再写 uploadInst.config.url', !/uploadInst\.config\.url\s*=/.test(src));
  assert('不再写 uploadsInst.config.url', !/uploadsInst\.config\.url\s*=/.test(src));
})();

console.log('\n=== 回归：旧写法在多元素下子实例 url 不变 ===');
(function () {
  var elems = [
    { className: 'layui-btn upload watermark', des: 'extpic' },
    { className: 'layui-btn upload watermark', des: 'ico' }
  ];
  var tree = renderMulti({ url: uploadurl, before: null }, elems);
  var before = beforeOldBugParentOnly(tree.parent);

  var url0 = clickThenBefore(tree.children[0], before);
  var url1 = clickThenBefore(tree.children[1], before);

  assert('旧写法父 config 被改写', tree.parent.config.url === uploadurl + '/watermark/1');
  assert('旧写法子0 ajax 仍为基础 url', url0 === uploadurl);
  assert('旧写法子1 ajax 仍为基础 url', url1 === uploadurl);
})();

console.log('\n=== 修复：多元素 watermark 按钮 ajax 带 /watermark/1 ===');
(function () {
  var elems = [
    { className: 'layui-btn upload watermark', des: 'extpic' },
    { className: 'layui-btn upload watermark', des: 'ico' }
  ];
  var tree = renderMulti({ url: uploadurl }, elems);

  var url0 = clickThenBefore(tree.children[0], beforeFixedThisUrl);
  assert('扩展单图 url 含水印', url0 === uploadurl + '/watermark/1');
  assert('子0 config 与父分离', tree.children[0].config.url !== tree.parent.config.url);

  var url1 = clickThenBefore(tree.children[1], beforeFixedThisUrl);
  assert('缩略图 url 含水印', url1 === uploadurl + '/watermark/1');
})();

console.log('\n=== 修复：多元素 .uploads 同理 ===');
(function () {
  var elems = [
    { className: 'layui-btn uploads watermark', des: 'gallery' },
    { className: 'layui-btn uploads watermark', des: 'pics' }
  ];
  var tree = renderMulti({ url: uploadurl }, elems);
  assert('多图扩展字段含水印', clickThenBefore(tree.children[0], beforeFixedThisUrl) === uploadurl + '/watermark/1');
  assert('轮播多图含水印', clickThenBefore(tree.children[1], beforeFixedThisUrl) === uploadurl + '/watermark/1');
})();

console.log('\n=== 无回归：单元素路径（父即子）===');
(function () {
  var el = { className: 'layui-btn upload watermark', des: 'ico' };
  var inst = { config: Object.assign({ url: uploadurl }, { elem: el, item: null }) };
  var url = clickThenBefore(inst, beforeFixedThisUrl);
  assert('单按钮 watermark 仍生效', url === uploadurl + '/watermark/1');

  var el2 = { className: 'layui-btn upload', des: 'logo' };
  var inst2 = { config: Object.assign({ url: uploadurl }, { elem: el2, item: null }) };
  assert('无 watermark 类不加参数', clickThenBefore(inst2, beforeFixedThisUrl) === uploadurl);
})();

console.log('\n=== 无回归：同组混用 watermark / 非 watermark 互不污染 ===');
(function () {
  var elems = [
    { className: 'layui-btn upload watermark', des: 'ico' },
    { className: 'layui-btn upload', des: 'logo' }
  ];
  var tree = renderMulti({ url: uploadurl }, elems);
  var uWm = clickThenBefore(tree.children[0], beforeFixedThisUrl);
  var uPlain = clickThenBefore(tree.children[1], beforeFixedThisUrl);
  assert('watermark 子实例带参数', uWm === uploadurl + '/watermark/1');
  assert('普通子实例不带参数', uPlain === uploadurl);
  assert('后点普通不影响已设的 watermark 子实例', tree.children[0].config.url === uploadurl + '/watermark/1');
})();

console.log('\n=== 无回归：先普通后 watermark 切换正确 ===');
(function () {
  var elems = [
    { className: 'layui-btn upload', des: 'a' },
    { className: 'layui-btn upload watermark', des: 'b' }
  ];
  var tree = renderMulti({ url: uploadurl }, elems);
  clickThenBefore(tree.children[0], beforeFixedThisUrl);
  clickThenBefore(tree.children[1], beforeFixedThisUrl);
  assert('先点普通后点水印，水印子实例正确', tree.children[1].config.url === uploadurl + '/watermark/1');
  assert('普通子实例保持基础 url', tree.children[0].config.url === uploadurl);
})();

console.log('\n--- 结果: ' + passed + ' passed, ' + failed + ' failed ---');
process.exit(failed > 0 ? 1 : 0);

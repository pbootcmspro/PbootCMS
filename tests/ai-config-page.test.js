/**
 * 配置页 AI 页签脚本：测试连接弹窗纯文本展示、服务商 placeholder 联动与默认地址提示（#330）。
 * 直接执行 config.html 中的真实脚本，DOM / layer / layui / fetch 用桩替代。
 * 运行: node tests/ai-config-page.test.js
 */
'use strict';

var fs = require('fs');
var path = require('path');
var vm = require('vm');

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

var html = fs.readFileSync(path.join(__dirname, '..', 'apps/admin/view/default/system/config.html'), 'utf8');
var start = html.lastIndexOf('<script>');
var end = html.indexOf('</script>', start);
var script = html.slice(start + '<script>'.length, end);

var providers = {
  deepseek: { name: 'DeepSeek', base: 'https://api.deepseek.com/chat/completions', model: 'deepseek-v4-flash', required: false },
  qwen: { name: '通义千问', base: 'https://dashscope.aliyuncs.com/compatible-mode/v1/chat/completions', model: 'qwen-plus-latest', required: false },
  claude: { name: 'Claude', base: 'https://api.anthropic.com/v1/messages', model: 'claude-sonnet-4-20250514', required: false },
  openai: { name: 'OpenAI 兼容（其他服务）', base: '', model: '', required: true }
};
script = script.replace('{$ai_provider_json}', JSON.stringify(providers)).replace('{url./admin/Ai/ping}', '/admin/Ai/ping');
// 同页有 {foreach $groups(key,value)}，模板引擎会全局替换 [key]；脚本不得写成 providers[key]
assert('AI script avoids foreach [key] collision', script.indexOf('[key]') < 0);
script = script.replace(/\[key\]/g, '2');

function makeEnv(fetchImpl, opts) {
  opts = opts || {};
  var listeners = {};
  var timers = [];
  function el(attrs) {
    var e = Object.assign({ value: '', placeholder: '', style: {}, textContent: '', disabled: false }, attrs);
    e.addEventListener = function (type, fn) { (listeners[(e.id || e.name) + ':' + type] = listeners[(e.id || e.name) + ':' + type] || []).push(fn); };
    return e;
  }
  var radios = ['deepseek', 'qwen', 'claude', 'openai'].map(function (v) {
    return el({ name: 'ai_provider', type: 'radio', value: v, checked: v === 'deepseek' });
  });
  var fields = {
    formcheck: el({ name: 'formcheck', value: 'token' }),
    ai_api_base: el({ id: 'ai_api_base', name: 'ai_api_base', value: '' }),
    ai_model: el({ id: 'ai_model', name: 'ai_model', value: '' }),
    ai_api_key: el({ name: 'ai_api_key', value: 'save****1234' })
  };
  var form = {
    querySelector: function (sel) {
      if (sel === 'input[name=formcheck]') return fields.formcheck;
      if (/ai_provider"\]:checked$/.test(sel)) return radios.filter(function (r) { return r.checked; })[0] || null;
      var m = /\[name="([^"]+)"\]$/.exec(sel);
      if (m) return m[1] === 'ai_provider' ? radios[0] : fields[m[1]] || null;
      return null;
    }
  };
  var btn = el({ id: 'aiPingBtn' });
  var hint = el({ id: 'aiBaseHint', style: { display: 'none' } });
  [btn, fields.ai_api_base, fields.ai_model].forEach(function (e) { e.closest = function () { return form; }; });
  var byId = { aiPingBtn: btn, ai_api_base: fields.ai_api_base, ai_model: fields.ai_model, aiBaseHint: hint };
  var alerts = [];
  var radioHandler = null;
  var layuiApi = {
    form: { on: function (evt, fn) { if (evt === 'radio(ai_provider)') radioHandler = fn; } },
    use: function (m, fn) { fn(); }
  };
  var sandbox = {
    document: { getElementById: function (id) { return byId[id] || null; } },
    layer: { alert: function (c) { alerts.push(c); }, msg: function () {}, closeAll: function () {} },
    window: {},
    setTimeout: function (fn) { timers.push(fn); return timers.length; },
    fetch: fetchImpl,
    String: String,
    JSON: JSON,
    Math: Math,
    Uint8Array: Uint8Array,
    confirm: function () { return true; }
  };
  if (!opts.delayLayui) {
    sandbox.layui = layuiApi;
    sandbox.window.layui = layuiApi;
  }
  vm.runInNewContext(script, sandbox);
  return {
    btn: btn, fields: fields, hint: hint, alerts: alerts, radios: radios, timers: timers,
    provideLayui: function () {
      sandbox.layui = layuiApi;
      sandbox.window.layui = layuiApi;
      while (timers.length) {
        timers.shift()();
      }
    },
    pick: function (v) { radios.forEach(function (r) { r.checked = r.value === v; }); radioHandler && radioHandler({ value: v }); },
    type: function (v) { fields.ai_api_base.value = v; (listeners['ai_api_base:input'] || []).forEach(function (fn) { fn(); }); }
  };
}

function respond(body) {
  return function () { return Promise.resolve({ json: function () { return Promise.resolve(body); } }); };
}

function flush() {
  return new Promise(function (r) { setTimeout(r, 0); });
}

(async function () {
  console.log('=== ping popup renders remote text as plain text ===');
  var env = makeEnv(respond({
    code: 1, msg: 'ok',
    data: { reply: '<script>alert(1)</script>\n&lt;b&gt; "q" \'s\'', provider_name: 'OpenAI 兼容（其他服务）', model: 'gpt-4o', endpoint: 'https://gw.example/v1/chat/completions', requested: true }
  }));
  env.btn.onclick();
  await flush();
  var out = env.alerts[0] || '';
  assert('success alert shown', out.indexOf('连接成功') === 0);
  assert('script tag neutralised', out.indexOf('<script') === -1 && out.indexOf('&lt;script&gt;') !== -1);
  assert('entity escaped once', out.indexOf('&amp;lt;b&amp;gt;') !== -1);
  assert('quotes escaped', out.indexOf('&quot;q&quot;') !== -1 && out.indexOf('&#39;s&#39;') !== -1);
  assert('newlines kept as <br>', out.indexOf('<br>') !== -1 && out.indexOf('\n') === -1);
  assert('summary lists provider, request model and address', out.indexOf('服务商：OpenAI 兼容（其他服务）') !== -1 && out.indexOf('请求模型：gpt-4o') !== -1 && out.indexOf('地址：https://gw.example/v1/chat/completions') !== -1);

  console.log('=== failure without request is labelled ===');
  env = makeEnv(respond({ code: 0, msg: '配置无效：<img src=x onerror=alert(1)>（未发出请求）', data: { provider_name: 'DeepSeek', model: 'm', endpoint: '地址无效', requested: false } }));
  env.btn.onclick();
  await flush();
  out = env.alerts[0] || '';
  assert('failure alert shown', out.indexOf('连接失败') === 0);
  assert('error html neutralised', out.indexOf('<img') === -1);
  assert('draft model labelled as pending', out.indexOf('待测试模型：m') !== -1);
  assert('not-sent marker shown', out.indexOf('未发出请求') !== -1);

  console.log('=== framework error shape and network failure ===');
  env = makeEnv(respond({ code: 0, data: '表单提交校验失败,请刷新后重试！' }));
  env.btn.onclick();
  await flush();
  assert('string data shown as the reason', (env.alerts[0] || '').indexOf('表单提交校验失败') !== -1);
  env = makeEnv(function () { return Promise.reject(new Error('<b>offline</b>')); });
  env.btn.onclick();
  await flush();
  assert('network error escaped', (env.alerts[0] || '').indexOf('请求异常：&lt;b&gt;offline') === 0);

  console.log('=== provider switch updates placeholders without touching values ===');
  env = makeEnv(respond({ code: 1, data: {} }));
  assert('initial base placeholder uses deepseek default', env.fields.ai_api_base.placeholder.indexOf('https://api.deepseek.com/chat/completions') !== -1);
  assert('initial model placeholder uses deepseek default', env.fields.ai_model.placeholder.indexOf('deepseek-v4-flash') !== -1);
  env.fields.ai_model.value = 'my-model';
  env.pick('openai');
  assert('openai base marked required', env.fields.ai_api_base.placeholder.indexOf('必填') === 0);
  assert('openai model marked required', env.fields.ai_model.placeholder.indexOf('必填') === 0);
  assert('typed model kept', env.fields.ai_model.value === 'my-model');
  env.pick('qwen');
  assert('qwen placeholder', env.fields.ai_model.placeholder.indexOf('qwen-plus-latest') !== -1);

  console.log('=== placeholders work before foot.html loads layui ===');
  env = makeEnv(respond({ code: 1, data: {} }), { delayLayui: true });
  assert('refresh runs without layui', env.fields.ai_api_base.placeholder.indexOf('https://api.deepseek.com/chat/completions') !== -1);
  env.pick('openai');
  assert('radio not bound before layui', env.fields.ai_api_base.placeholder.indexOf('必填') !== 0);
  env.provideLayui();
  env.pick('openai');
  assert('radio binds after layui arrives', env.fields.ai_api_base.placeholder.indexOf('必填') === 0);

  console.log('=== another provider default URL is flagged and cleared ===');
  env.pick('deepseek');
  env.type('https://dashscope.aliyuncs.com/compatible-mode/v1/chat/completions/');
  assert('hint shown for qwen default under deepseek', env.hint.style.display === '' && env.hint.textContent.indexOf('通义千问') !== -1);
  assert('typed URL kept', env.fields.ai_api_base.value === 'https://dashscope.aliyuncs.com/compatible-mode/v1/chat/completions/');
  env.pick('qwen');
  assert('hint hidden once provider matches', env.hint.style.display === 'none');
  env.pick('deepseek');
  env.type('https://gw.example/v1');
  assert('hint hidden after editing URL', env.hint.style.display === 'none');
  env.type('');
  assert('hint hidden for empty URL', env.hint.style.display === 'none');

  console.log('\n' + passed + ' passed, ' + failed + ' failed');
  process.exit(failed ? 1 : 0);
})();

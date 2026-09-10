/* ============================================================
   Slexion 全站中英文切换脚本（Liquid Glass 主题配套）
   用法：
     1. 在每个页面 </body> 前引入：<script src="/i18n.js"></script>
        （需在页面自身脚本之前引入，且页面脚本用 window.I18N）
     2. 静态文本：给元素加 data-en="英文"（中文默认即页面原文，
        首次加载自动捕获为 data-zh；也可手动指定 data-zh）
        - 含子元素的富文本用 data-en-html="<b>...</b>"
        - 输入框占位符：data-en-ph="英文占位"
        - readonly 输入框的值：data-en-val="英文值"
        - <title> 加 data-en 可切换页面标题
     3. 动态文本（JS 生成）：window.I18N.t(中文, 英文)
     4. 语言变化时会派发 i18nchange 事件（detail.lang），
        页面脚本可监听并重绘动态内容
     5. 首次访问自动检测系统语言，英文系统默认英文
   ============================================================ */
(function () {
    var STORAGE_KEY = 'slexion_lang';

    // 检测系统/浏览器语言
    function detectSystemLang() {
        try {
            var navLang = (navigator.language || navigator.userLanguage || 'zh').toLowerCase();
            if (navLang.indexOf('en') === 0) return 'en';
            return 'zh';
        } catch (e) {
            return 'zh';
        }
    }

    // 默认语言：可用 __i18nDefault 覆盖（如 home_en 默认英文）
    var lang = (typeof window.__i18nDefault !== 'undefined' && window.__i18nDefault === 'en')
        ? 'en' : 'zh';
    try {
        var saved = localStorage.getItem(STORAGE_KEY);
        if (saved === 'zh' || saved === 'en') {
            lang = saved;
        } else {
            // 首次访问，使用系统语言
            lang = detectSystemLang();
        }
    } catch (e) {
        // 隐私模式等无法访问 localStorage 时，使用系统语言
        lang = detectSystemLang();
    }

    // ---------- 首次加载：捕获原始中文 ----------
    function capture() {
        document.querySelectorAll('[data-en]').forEach(function (el) {
            if (!el.hasAttribute('data-zh')) {
                el.setAttribute('data-zh', el.hasAttribute('data-en-html') ? el.innerHTML : el.textContent);
            }
        });
        document.querySelectorAll('[data-en-ph]').forEach(function (el) {
            if (!el.hasAttribute('data-zh-ph')) {
                el.setAttribute('data-zh-ph', el.getAttribute('placeholder') || '');
            }
        });
        document.querySelectorAll('[data-en-val]').forEach(function (el) {
            if (!el.hasAttribute('data-zh-val')) {
                el.setAttribute('data-zh-val', el.value || '');
            }
        });
    }

    var zhTitle = document.title;
    var titleEn = null;
    (function () {
        var t = document.querySelector('title[data-en]');
        if (t) titleEn = t.getAttribute('data-en');
    })();

    // ---------- 应用语言 ----------
    function applyLang() {
        var en = (lang === 'en');

        document.querySelectorAll('[data-en]').forEach(function (el) {
            var zh = el.getAttribute('data-zh');
            var target = en ? el.getAttribute('data-en') : (zh != null ? zh : el.getAttribute('data-en'));
            if (el.hasAttribute('data-en-html')) {
                el.innerHTML = target;
            } else if (el.textContent !== target) {
                el.textContent = target;
            }
        });

        document.querySelectorAll('[data-en-ph]').forEach(function (el) {
            var zh = el.getAttribute('data-zh-ph');
            el.setAttribute('placeholder', en ? el.getAttribute('data-en-ph') : (zh != null ? zh : el.getAttribute('data-en-ph')));
        });

        document.querySelectorAll('[data-en-val]').forEach(function (el) {
            var zh = el.getAttribute('data-zh-val');
            el.value = en ? el.getAttribute('data-en-val') : (zh != null ? zh : el.getAttribute('data-en-val'));
        });

        if (en && titleEn != null) document.title = titleEn;
        else document.title = zhTitle;

        var toggle = document.getElementById('i18n-toggle');
        if (toggle) {
            toggle.querySelectorAll('[data-l]').forEach(function (s) {
                s.classList.toggle('active', s.getAttribute('data-l') === lang);
            });
        }
    }

    // ---------- 拖动功能 ----------
    function makeDraggable(el, storageKey) {
        var dragging = false, moved = false;
        var startX, startY, origX, origY;
        try {
            var saved = localStorage.getItem(storageKey);
            if (saved) {
                var pos = JSON.parse(saved);
                el.style.left = pos.x + 'px';
                el.style.top = pos.y + 'px';
                el.style.right = 'auto';
            }
        } catch(e) {}
        function start(e) {
            var touch = e.touches ? e.touches[0] : e;
            dragging = true; moved = false;
            startX = touch.clientX; startY = touch.clientY;
            var rect = el.getBoundingClientRect();
            origX = rect.left; origY = rect.top;
            el.style.transition = 'none';
            e.preventDefault();
        }
        function move(e) {
            if (!dragging) return;
            var touch = e.touches ? e.touches[0] : e;
            var dx = touch.clientX - startX, dy = touch.clientY - startY;
            if (Math.abs(dx) > 3 || Math.abs(dy) > 3) moved = true;
            var nx = Math.max(0, Math.min(window.innerWidth - el.offsetWidth, origX + dx));
            var ny = Math.max(0, Math.min(window.innerHeight - el.offsetHeight, origY + dy));
            el.style.left = nx + 'px';
            el.style.top = ny + 'px';
            el.style.right = 'auto';
            e.preventDefault();
        }
        function end() {
            if (!dragging) return;
            dragging = false;
            el.style.transition = '';
            if (moved) {
                var rect = el.getBoundingClientRect();
                try { localStorage.setItem(storageKey, JSON.stringify({x: rect.left, y: rect.top})); } catch(e) {}
                setTimeout(function(){ moved = false; }, 150);
            }
        }
        el.addEventListener('mousedown', start);
        document.addEventListener('mousemove', move);
        document.addEventListener('mouseup', end);
        el.addEventListener('touchstart', start, {passive:false});
        document.addEventListener('touchmove', move, {passive:false});
        document.addEventListener('touchend', end);
        el.addEventListener('click', function(e) {
            if (moved) { e.stopPropagation(); e.preventDefault(); }
        }, true);
    }
    // ---------- 切换按钮 ----------
    function injectToggle() {
        if (document.getElementById('i18n-toggle')) return;
        var hasNav = !!document.querySelector('.cyber-nav, nav');
        var d = document.createElement('div');
        d.id = 'i18n-toggle';
        d.setAttribute('title', lang === 'zh' ? 'Switch to English' : '切换到中文');
        d.innerHTML = '<span data-l="zh">中</span><i>|</i><span data-l="en">EN</span>';
        d.style.cssText =
            'position:fixed;' +
            'top:' + (hasNav ? '70px' : '16px') + ';' +
            'right:16px;' +
            'z-index:10001;' +
            'display:flex;align-items:center;gap:4px;' +
            'padding:6px 12px;border-radius:999px;' +
            'background:rgba(255,255,255,0.08);' +
            '-webkit-backdrop-filter:blur(14px) saturate(160%) brightness(1.1);' +
            'backdrop-filter:blur(14px) saturate(160%) brightness(1.1);' +
            'border:1px solid rgba(255,255,255,0.2);' +
            'box-shadow:inset 0 1px 0 rgba(255,255,255,0.25),0 6px 18px rgba(0,0,0,0.35);' +
            'cursor:pointer;user-select:none;' +
            'font-family:inherit;font-size:13px;letter-spacing:1px;';
        d.addEventListener('click', function () {
            setLang(lang === 'zh' ? 'en' : 'zh');
        });
        document.body.appendChild(d);
        makeDraggable(d, 'slexion_i18n_pos');

        var st = document.createElement('style');
        st.textContent =
            '#i18n-toggle span{color:rgba(255,255,255,0.55);padding:2px 6px;border-radius:8px;transition:0.2s;}' +
            '#i18n-toggle span.active{color:#0ff;background:rgba(0,255,255,0.18);text-shadow:0 0 8px rgba(0,255,255,0.8);}' +
            '#i18n-toggle i{color:rgba(255,255,255,0.25);font-style:normal;font-size:11px;}';
        document.head.appendChild(st);
    }

    // ---------- 设置语言 ----------
    function setLang(l) {
        lang = (l === 'en') ? 'en' : 'zh';
        try { localStorage.setItem(STORAGE_KEY, lang); } catch (e) {}
        applyLang();
        var t = document.getElementById('i18n-toggle');
        if (t) t.setAttribute('title', lang === 'zh' ? 'Switch to English' : '切换到中文');
        try {
            window.dispatchEvent(new CustomEvent('i18nchange', { detail: { lang: lang } }));
        } catch (e) {}
    }

    // ---------- 对外 API ----------
    window.I18N = {
        get lang() { return lang; },
        t: function (zh, en) { return (lang === 'en' ? en : zh); },
        setLang: setLang
    };

    // ---------- 初始化 ----------
    capture();
    injectToggle();
    applyLang();
})();

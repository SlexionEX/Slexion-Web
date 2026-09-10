/* ============================================================
 *  Slexion 全站悬浮 Liquid Glass 导航栏
 *  用法：在每个页面 </body> 前、i18n.js 之前引入
 *    <script src="/navbar.js"></script>
 *    <script src="/i18n.js"></script>
 * ============================================================ */
(function () {
    'use strict';
    /* ---------- 自动注入全站动画 ---------- */
    (function loadAnimations() {
        if (window.__animationsLoaded) return;
        window.__animationsLoaded = true;
        var s = document.createElement('script');
        s.src = '/animations.js';
        s.defer = true;
        document.head.appendChild(s);
    })();


    /* ---------- 导航项（中英） ---------- */
    var NAV_ITEMS = [
        { href: '/home',          zh: '主页',       en: 'Home' },
        { href: '/vlog',          zh: 'VLOG',       en: 'VLOG' },
        { href: '/feedback',      zh: '反馈',       en: 'Feedback' },
        { href: '/browser',       zh: '关于浏览器', en: 'About Browser' },
        { href: '/games',         zh: '小游戏',     en: 'Games' },
        { href: '/clock',         zh: '时钟',       en: 'Clock' },
        { href: '/accountcenter', zh: '用户中心',   en: 'User Center' },
        { href: '/eula/eula.html', zh: 'EULA',     en: 'EULA' }
    ];

    /* ---------- 注入 CSS ---------- */
    var css = [
        '.glass-nav{',
        '  position:fixed;top:14px;left:50%;transform:translateX(-50%);',
        '  display:flex;align-items:center;gap:4px;',
        '  padding:7px 10px;',
        '  background:rgba(255,255,255,0.08);',
        '  -webkit-backdrop-filter:blur(22px) saturate(180%) brightness(1.12);',
        '  backdrop-filter:blur(22px) saturate(180%) brightness(1.12);',
        '  border:1px solid rgba(255,255,255,0.18);',
        '  border-radius:999px;',
        '  box-shadow:0 8px 32px rgba(0,0,0,0.45),inset 0 1px 0 rgba(255,255,255,0.28),inset 0 -1px 0 rgba(0,0,0,0.10);',
        '  z-index:10000;',
        '  max-width:calc(100vw - 180px);',
        '  overflow-x:auto;overflow-y:hidden;',
        '  scrollbar-width:none;',
        '  -webkit-overflow-scrolling:touch;',
        '}',
        '.glass-nav::-webkit-scrollbar{display:none;}',
        '.glass-nav a{',
        '  display:inline-flex;align-items:center;justify-content:center;',
        '  padding:8px 18px;',
        '  border-radius:999px;',
        '  color:rgba(255,255,255,0.82);',
        '  text-decoration:none;',
        '  font-size:15px;',
        '  font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;',
        '  white-space:nowrap;',
        '  transition:background .25s ease,color .25s ease,box-shadow .25s ease,transform .28s cubic-bezier(.34,1.56,.64,1);',
        '}',
        '.glass-nav a:hover{',
        '  background:rgba(255,255,255,0.14);',
        '  color:#fff;',
        '  transform:scale(1.06);',
        '}',
        '.glass-nav a:active{transform:scale(0.96);}',
        '.glass-nav a.active{',
        '  background:linear-gradient(135deg,#00e5ff,#ff00ff);',
        '  color:#000;',
        '  font-weight:600;',
        '  box-shadow:0 4px 16px rgba(0,229,255,0.35);',
        '}',
        'body.glass-nav-loaded{padding-top:76px;}',
        '@media (max-width:900px){',
        '  .glass-nav{gap:2px;padding:6px 8px;}',
        '  .glass-nav a{padding:7px 14px;font-size:13px;}',
        '}',
        '@media (max-width:640px){',
        '  .glass-nav{top:10px;gap:1px;padding:5px 6px;max-width:calc(100vw - 140px);}',
        '  .glass-nav a{padding:6px 10px;font-size:12px;}',
        '  body.glass-nav-loaded{padding-top:68px;}',
        '}',
        '/* ===== 全站全局圆角 ===== */',
        'button, input[type="button"], input[type="submit"], input[type="reset"], .btn { border-radius: 10px !important; }',
        'input, select, textarea { border-radius: 8px; }',
        '.card, .panel, .box, .modal, .dialog, .popup { border-radius: 16px; }',
        'img { border-radius: 8px; }',
        '/* ===== 全站底部版权 ===== */',
        '.site-footer{',
        '  text-align:center;padding:20px 16px 24px;',
        '  color:rgba(255,255,255,0.45);font-size:13px;',
        '  font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;',
        '  position:relative;z-index:1;',
        '  margin-top:20px;',
        '}',
        '.site-footer a{color:rgba(0,229,255,0.7);text-decoration:none;}',
        '.site-footer a:hover{color:#00e5ff;}',
        '.site-footer .footer-sep{margin:0 8px;opacity:.4;}',
        '/* ===== flex 布局页面页脚修复（防止被挤到一侧） ===== */',
        'body.footer-flex-fix{flex-wrap:wrap;}',
        'body.footer-flex-fix .site-footer{flex-basis:100%;flex-shrink:0;}',
        '/* ===== 全站控件 hover 弹性放大 ===== */',
        'button:hover,.btn:hover,.card:hover,.panel:hover,.box:hover,.item:hover,.tab:hover,.chip:hover,.icon-btn:hover,[role=button]:hover,.game-card:hover,.vlog-item:hover,.stat-card:hover,.center-card:hover,.auth-tab:hover,.acct-tab:hover,.acct-dd-item:hover,.btn-primary:hover,.btn-ghost:hover,.submit-btn:hover{',
        '  transform:scale(1.04);',
        '  transition:transform .22s cubic-bezier(.34,1.56,.64,1),background .2s ease,box-shadow .2s ease;',
        '}',
        'button:active,.btn:active,.card:active,.panel:active,.item:active,.tab:active,.chip:active,.icon-btn:active,[role=button]:active,.btn-primary:active,.btn-ghost:active,.submit-btn:active{transform:scale(0.97);}',
        '/* 输入框/下拉/文本域 hover 只发光不放大（避免输入体验问题） */',
        'input:hover,select:hover,textarea:hover{',
        '  box-shadow:0 0 12px rgba(0,229,255,.45) !important;',
        '  outline:1px solid rgba(0,229,255,.5);outline-offset:-1px;',
        '  transition:box-shadow .15s ease,outline-color .15s ease;',
        '}',
        'label:hover{cursor:pointer;}',
    ].join('\n');

    /* ---------- 通用拖动函数 ---------- */
    function makeDraggable(el, storageKey, onMove) {
        var dragging = false, moved = false;
        var startX, startY, origX, origY;
        try {
            var saved = localStorage.getItem(storageKey);
            if (saved) {
                var pos = JSON.parse(saved);
                el.style.left = pos.x + 'px';
                el.style.top = pos.y + 'px';
                el.style.transform = 'none';
                el.style.right = 'auto';
                if (onMove) onMove(pos.x, pos.y);
            }
        } catch(e) {}
        function start(e) {
            var touch = e.touches ? e.touches[0] : e;
            dragging = true; moved = false;
            startX = touch.clientX; startY = touch.clientY;
            var rect = el.getBoundingClientRect();
            origX = rect.left; origY = rect.top;
            el.style.transition = 'none';
            el.style.cursor = 'grabbing';
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
            el.style.transform = 'none';
            el.style.right = 'auto';
            if (onMove) onMove(nx, ny);
            e.preventDefault();
        }
        function end() {
            if (!dragging) return;
            dragging = false;
            el.style.transition = '';
            el.style.cursor = '';
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
    var styleEl = document.createElement('style');
    styleEl.textContent = css;
    document.head.appendChild(styleEl);

    /* ---------- 构建导航 HTML ---------- */
    var nav = document.createElement('nav');
    nav.className = 'glass-nav';
    nav.setAttribute('aria-label', 'Site navigation');

    var currentPath = window.location.pathname.replace(/\/+$/, '') || '/';
    if (currentPath === '/' || currentPath === '/index.html' || currentPath === '/home/home.html') {
        currentPath = '/home';
    }
    var pathMap = {
        '/vlog/vlog.php': '/vlog',
        '/feedback/feedback.html': '/feedback',
        '/slexionbrowser/AboutSlexionBrowser.html': '/browser',
        '/littlegames/lg.html': '/games',
        '/macosclock/AboutmacOSClock.html': '/clock'
    };
    if (pathMap[currentPath]) currentPath = pathMap[currentPath];

    NAV_ITEMS.forEach(function (item) {
        var a = document.createElement('a');
        a.href = item.href;
        a.textContent = item.zh;
        a.setAttribute('data-en', item.en);
        a.setAttribute('data-zh', item.zh);
        var norm = item.href.replace(/\/+$/, '');
        if (currentPath === norm || currentPath.endsWith(norm)) {
            a.className = 'active';
        }
        nav.appendChild(a);
    });

    /* ---------- 加载 busuanzi 统计 ---------- */
    (function loadBusuanzi() {
        if (window.__busuanzi_loaded) return;
        window.__busuanzi_loaded = true;
        var s = document.createElement('script');
        s.async = true;
        s.src = '//busuanzi.ibruce.info/busuanzi/2.3/busuanzi.pure.mini.js';
        document.head.appendChild(s);
    })();

    /* ---------- 语言同步：主动监听 i18nchange + 轮询兜底 ---------- */
    var lastLang = null;
    function syncNavLang(lang) {
        if (lang === lastLang) return;
        lastLang = lang;
        var en = (lang === 'en');
        nav.querySelectorAll('a').forEach(function (a, i) {
            if (NAV_ITEMS[i]) {
                a.textContent = en ? NAV_ITEMS[i].en : NAV_ITEMS[i].zh;
            }
        });
    }
    // 轮询检查语言状态（兜底，防止事件丢失）
    function pollLang() {
        if (window.I18N && window.I18N.lang) {
            syncNavLang(window.I18N.lang);
        }
    }
    pollLang();
    setInterval(pollLang, 500);
    window.addEventListener('i18nchange', function (e) {
        syncNavLang(e.detail.lang);
    });

    /* ---------- 插入到 body 最前面 ---------- */
    function inject() {
        if (document.body) {
            document.body.insertBefore(nav, document.body.firstChild);
            document.body.classList.add('glass-nav-loaded');
            makeDraggable(nav, 'slexion_nav_pos');
            // 注入底部版权
            injectFooter();
        } else {
            document.addEventListener('DOMContentLoaded', inject, { once: true });
        }
    }

    /* ---------- 注入底部版权 ---------- */
    function injectFooter() {
        // 避免重复注入
        if (document.querySelector('.site-footer')) return;
        var footer = document.createElement('footer');
        footer.className = 'site-footer';
        footer.innerHTML = 'Copyright © Slexion 2026' +
            '<span class="footer-sep">|</span>' +
            '<a href="/eula/eula.html" data-en="User Agreement">用户协议</a>' +
            '<span class="footer-sep">|</span>' +
            '<a href="/feedback/feedback.html" data-en="Feedback">意见反馈</a>';
        // 插入到 body 最后
        document.body.appendChild(footer);

        // flex 布局页面：让页脚独占一行（防止被挤到一侧）
        try {
            var bdStyle = window.getComputedStyle(document.body);
            if (bdStyle.display === 'flex' || bdStyle.display === 'inline-flex') {
                document.body.classList.add('footer-flex-fix');
            }
        } catch (e) { /* 静默失败 */ }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', inject, { once: true });
    } else {
        inject();
    }
})();

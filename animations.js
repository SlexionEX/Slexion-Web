/* ============================================================
 *  Slexion 全站动画系统
 *  用法：由 navbar.js 自动注入，无需手动引入
 *  功能：
 *    1. 滚动进入动画（Intersection Observer）
 *    2. 页面加载进度条 + 淡入
 *    3. 按钮点击波纹效果
 *    4. 平滑滚动
 *    5. 图片加载淡入
 *    6. 数字计数动画
 *  动画类（加在任意元素上即可）：
 *    data-anim="fade-up"      从下滑入+淡入
 *    data-anim="fade-down"    从上滑入+淡入
 *    data-anim="fade-left"    从右滑入+淡入
 *    data-anim="fade-right"   从左滑入+淡入
 *    data-anim="zoom-in"      缩放进入+淡入
 *    data-anim="blur-in"      模糊清晰+淡入
 *    data-anim="flip-in"      翻转进入
 *    data-anim="slide-up"     大幅上滑
 *    data-anim-delay="300"    延迟毫秒数
 *    data-anim-duration="800" 持续毫秒数
 * ============================================================ */
(function () {
    'use strict';

    /* ---------- 注入 CSS ---------- */
    var css = [
        /* ===== 页面加载进度条 ===== */
        '#page-loader{',
        '  position:fixed;top:0;left:0;width:100%;height:3px;',
        '  background:linear-gradient(90deg,#00e5ff,#ff00ff);',
        '  z-index:99999;transform:scaleX(0);transform-origin:left;',
        '  transition:transform .3s ease;box-shadow:0 0 10px rgba(0,229,255,.8);',
        '}',
        '#page-loader.done{opacity:0;transition:opacity .4s ease,transform .3s ease;}',

        /* ===== 滚动进入动画基础 ===== */
        '[data-anim]{',
        '  opacity:0;',
        '  will-change:transform,opacity,filter;',
        '}',
        '[data-anim].anim-in{',
        '  opacity:1;',
        '  transition:opacity var(--anim-duration, 0.8s) cubic-bezier(.22,1,.36,1),transform var(--anim-duration, 0.8s) cubic-bezier(.22,1,.36,1),filter var(--anim-duration, 0.8s) ease;',
        '}',

        /* 各方向动画 */
        '[data-anim="fade-up"]{transform:translateY(40px);}',
        '[data-anim="fade-up"].anim-in{transform:translateY(0);}',
        '[data-anim="fade-down"]{transform:translateY(-40px);}',
        '[data-anim="fade-down"].anim-in{transform:translateY(0);}',
        '[data-anim="fade-left"]{transform:translateX(40px);}',
        '[data-anim="fade-left"].anim-in{transform:translateX(0);}',
        '[data-anim="fade-right"]{transform:translateX(-40px);}',
        '[data-anim="fade-right"].anim-in{transform:translateX(0);}',
        '[data-anim="zoom-in"]{transform:scale(.85);}',
        '[data-anim="zoom-in"].anim-in{transform:scale(1);}',
        '[data-anim="blur-in"]{filter:blur(12px);transform:translateY(20px);}',
        '[data-anim="blur-in"].anim-in{filter:blur(0);transform:translateY(0);}',
        '[data-anim="flip-in"]{transform:perspective(800px) rotateX(-30deg);transform-origin:top;}',
        '[data-anim="flip-in"].anim-in{transform:perspective(800px) rotateX(0);}',
        '[data-anim="slide-up"]{transform:translateY(80px);}',
        '[data-anim="slide-up"].anim-in{transform:translateY(0);}',

        /* ===== 按钮波纹 ===== */
        '.ripple-container{position:relative;overflow:hidden;}',
        '.ripple{',
        '  position:absolute;border-radius:50%;',
        '  background:rgba(255,255,255,.4);',
        '  transform:scale(0);animation:ripple-anim .6s ease-out;',
        '  pointer-events:none;',
        '}',
        '@keyframes ripple-anim{',
        '  to{transform:scale(4);opacity:0;}',
        '}',

        /* ===== 图片加载淡入 ===== */
        'img.img-fade{opacity:0;transition:opacity .6s ease;}',
        'img.img-fade.loaded{opacity:1;}',

        /* ===== 卡片 hover 提升（增强现有效果） ===== */
        '.card:hover,.panel:hover,.box:hover,.game-card:hover,.vlog-item:hover,.stat-card:hover{',
        '  transform:translateY(-4px) scale(1.02);',
        '  box-shadow:0 16px 48px rgba(0,0,0,.5);',
        '}',

        /* ===== 减少动画偏好（系统 + 用户开关） ===== */
        '@media (prefers-reduced-motion:reduce){',
        '  [data-anim]{opacity:1;transform:none;filter:none;transition:none;}',
        '  .ripple{display:none;}',
        '}',
        'body.reduce-motion [data-anim]{opacity:1;transform:none;filter:none;transition:none;}',
        'body.reduce-motion .ripple{display:none;}',
        'body.reduce-motion #page-loader{display:none;}',
        'body.reduce-motion .card:hover,body.reduce-motion .panel:hover,body.reduce-motion .box:hover,body.reduce-motion .game-card:hover,body.reduce-motion .vlog-item:hover,body.reduce-motion .stat-card:hover{transform:none;}',
    ].join('\n');

    var styleEl = document.createElement('style');
    styleEl.textContent = css;
    document.head.appendChild(styleEl);

    /* ---------- 全局减少动画开关检测 ---------- */
    function isReduceMotion() {
        return document.body.classList.contains('reduce-motion') ||
               localStorage.getItem('slexion_reduce_motion') === '1' ||
               (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
    }

    // 页面加载时同步 localStorage 状态到 body class
    if (localStorage.getItem('slexion_reduce_motion') === '1') {
        document.body.classList.add('reduce-motion');
    }

    // 监听用户中心的开关变化
    window.addEventListener('reducemotionchange', function (e) {
        if (e.detail && e.detail.enabled) {
            document.body.classList.add('reduce-motion');
        } else {
            document.body.classList.remove('reduce-motion');
        }
    });

    /* ---------- 全局动画速度设置 ---------- */
    (function initAnimSpeed() {
        var saved = localStorage.getItem('slexion_anim_speed');
        if (saved) {
            document.documentElement.style.setProperty('--anim-duration', parseFloat(saved) + 's');
        }
        // 监听速度变化
        window.addEventListener('animspeedchange', function (e) {
            if (e.detail && e.detail.speed) {
                document.documentElement.style.setProperty('--anim-duration', parseFloat(e.detail.speed) + 's');
            }
        });
    })();

    /* ---------- 页面加载进度条 ---------- */
    var loader = document.createElement('div');
    loader.id = 'page-loader';
    document.body.appendChild(loader);

    // 减少动画模式：直接隐藏进度条
    if (isReduceMotion()) {
        loader.style.display = 'none';
    }

    var progress = 0;
    var loaderTimer = setInterval(function () {
        if (isReduceMotion()) { clearInterval(loaderTimer); return; }
        progress += Math.random() * 15;
        if (progress > 90) progress = 90;
        loader.style.transform = 'scaleX(' + (progress / 100) + ')';
    }, 100);

    window.addEventListener('load', function () {
        clearInterval(loaderTimer);
        loader.style.transform = 'scaleX(1)';
        setTimeout(function () {
            loader.classList.add('done');
        }, 200);
    });

    /* ---------- 滚动进入动画（Intersection Observer） ---------- */
    function initScrollAnimations() {
        var elements = document.querySelectorAll('[data-anim]');
        if (!elements.length) return;

        // 减少动画模式：直接显示所有元素
        if (isReduceMotion()) {
            elements.forEach(function (el) { el.classList.add('anim-in'); });
            return;
        }

        if (!('IntersectionObserver' in window)) {
            // 不支持的浏览器直接显示
            elements.forEach(function (el) { el.classList.add('anim-in'); });
            return;
        }

        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    var el = entry.target;
                    var delay = parseInt(el.getAttribute('data-anim-delay')) || 0;
                    var duration = parseInt(el.getAttribute('data-anim-duration')) || 800;
                    setTimeout(function () {
                        el.style.transitionDuration = duration + 'ms';
                        el.classList.add('anim-in');
                    }, delay);
                    observer.unobserve(el);
                }
            });
        }, {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        });

        elements.forEach(function (el) { observer.observe(el); });
    }

    /* ---------- 按钮波纹效果 ---------- */
    function initRipples() {
        document.addEventListener('click', function (e) {
            // 减少动画模式：跳过波纹
            if (isReduceMotion()) return;
            var btn = e.target.closest('button, .btn, .chip, .tab, [role="button"], .glass-nav a, .acct-btn, #i18n-toggle, .acct-dd-item');
            if (!btn) return;

            // 检查是否刚拖动过（防止拖动后触发波纹）
            if (btn._justDragged) {
                btn._justDragged = false;
                return;
            }

            if (!btn.classList.contains('ripple-container')) {
                btn.classList.add('ripple-container');
            }

            var rect = btn.getBoundingClientRect();
            var size = Math.max(rect.width, rect.height);
            var x = e.clientX - rect.left - size / 2;
            var y = e.clientY - rect.top - size / 2;

            var ripple = document.createElement('span');
            ripple.className = 'ripple';
            ripple.style.width = ripple.style.height = size + 'px';
            ripple.style.left = x + 'px';
            ripple.style.top = y + 'px';
            btn.appendChild(ripple);

            setTimeout(function () { ripple.remove(); }, 600);
        });
    }

    /* ---------- 平滑滚动 ---------- */
    function initSmoothScroll() {
        document.addEventListener('click', function (e) {
            var link = e.target.closest('a[href^="#"]');
            if (!link) return;
            var targetId = link.getAttribute('href');
            if (targetId === '#' || targetId.length < 2) return;
            var target = document.querySelector(targetId);
            if (!target) return;
            e.preventDefault();
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    }

    /* ---------- 图片加载淡入 ---------- */
    function initImageFade() {
        var images = document.querySelectorAll('img:not([data-no-fade])');
        images.forEach(function (img) {
            if (img.complete && img.naturalWidth > 0) {
                img.classList.add('img-fade', 'loaded');
            } else {
                img.classList.add('img-fade');
                img.addEventListener('load', function () {
                    img.classList.add('loaded');
                });
                img.addEventListener('error', function () {
                    img.classList.add('loaded');
                });
            }
        });
    }

    /* ---------- 数字计数动画 ---------- */
    function initCounters() {
        var counters = document.querySelectorAll('[data-count]');
        if (!counters.length) return;

        // 减少动画模式：直接显示最终数字
        if (isReduceMotion()) {
            counters.forEach(function (el) {
                var target = parseInt(el.getAttribute('data-count'));
                el.textContent = target.toLocaleString();
            });
            return;
        }

        if (!('IntersectionObserver' in window)) return;

        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    var el = entry.target;
                    var target = parseInt(el.getAttribute('data-count'));
                    var duration = parseInt(el.getAttribute('data-count-duration')) || 2000;
                    var start = 0;
                    var startTime = null;

                    function animate(timestamp) {
                        if (!startTime) startTime = timestamp;
                        var progress = Math.min((timestamp - startTime) / duration, 1);
                        var eased = 1 - Math.pow(1 - progress, 3);
                        el.textContent = Math.floor(eased * target).toLocaleString();
                        if (progress < 1) requestAnimationFrame(animate);
                    }
                    requestAnimationFrame(animate);
                    observer.unobserve(el);
                }
            });
        }, { threshold: 0.5 });

        counters.forEach(function (el) { observer.observe(el); });
    }

    /* ---------- 自动给常见元素添加动画 ---------- */
    function autoAnimateCommon() {
        // 减少动画模式：跳过自动动画
        if (isReduceMotion()) {
            return;
        }
        // 给卡片、标题、段落自动添加 fade-up 动画（带交错延迟）
        var selectors = '.card, .panel, .box, .game-card, .vlog-item, .stat-card, .center-card, h1, h2, h3';
        var elements = document.querySelectorAll(selectors);
        var index = 0;
        elements.forEach(function (el) {
            // 跳过已经有 data-anim 的元素
            if (el.hasAttribute('data-anim')) return;
            // 跳过 navbar 内的元素
            if (el.closest('.glass-nav')) return;
            // 跳过太小的元素
            if (el.offsetHeight < 30 && el.tagName !== 'H1' && el.tagName !== 'H2' && el.tagName !== 'H3') return;

            el.setAttribute('data-anim', 'fade-up');
            el.setAttribute('data-anim-delay', Math.min(index * 80, 400));
            index++;
        });
    }

    /* ---------- 初始化 ---------- */
    function init() {
        autoAnimateCommon();
        initScrollAnimations();
        initRipples();
        initSmoothScroll();
        initImageFade();
        initCounters();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // 页面切换时重新初始化（SPA 场景）
    window.addEventListener('DOMContentLoaded', function () {
        // 延迟一下确保 DOM 完整
        setTimeout(init, 100);
    });

    // 暴露 API
    window.SlexionAnim = {
        refresh: init,
        animateIn: function (el, type) {
            el.setAttribute('data-anim', type || 'fade-up');
            initScrollAnimations();
        }
    };
})();

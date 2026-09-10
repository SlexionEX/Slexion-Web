/* ============================================================
 *  Slexion Account - 全站登录系统前端
 *  用法：在每个页面 </body> 前、i18n.js 之后引入
 *    <script src="/account.js"></script>
 * ============================================================ */
(function () {
    'use strict';

    var API = {
        session:  '/account/session.php',
        login:    '/account/login.php',
        register: '/account/register.php',
        logout:   '/account/logout.php'
    };

    var state = {
        loggedin: false,
        username: '',
        email: '',
        avatar: '',
        csrf: ''
    };

    /* ---------- 注入 CSS ---------- */
    var css = [
        '.acct-btn{',
        '  position:fixed;top:14px;left:16px;z-index:10001;',
        '  display:flex;align-items:center;gap:6px;',
        '  padding:8px 18px;border-radius:999px;cursor:pointer;',
        '  background:rgba(255,255,255,0.08);',
        '  -webkit-backdrop-filter:blur(22px) saturate(180%) brightness(1.12);',
        '  backdrop-filter:blur(22px) saturate(180%) brightness(1.12);',
        '  border:1px solid rgba(255,255,255,0.18);',
        '  box-shadow:0 8px 32px rgba(0,0,0,0.45),inset 0 1px 0 rgba(255,255,255,0.28);',
        '  color:rgba(255,255,255,0.9);font-size:14px;',
        '  font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;',
        '  transition:all .25s ease;user-select:none;',
        '}',
        '.acct-btn:hover{background:rgba(255,255,255,0.16);color:#fff;}',
        '.acct-btn .acct-avatar{',
        '  width:22px;height:22px;border-radius:50%;',
        '  background:linear-gradient(135deg,#00e5ff,#ff00ff);',
        '  display:flex;align-items:center;justify-content:center;',
        '  font-size:12px;font-weight:bold;color:#000;',
        '}',
        '.acct-dropdown{',
        '  position:fixed;top:56px;left:16px;z-index:10001;',
        '  min-width:180px;border-radius:16px;overflow:hidden;',
        '  background:rgba(10,10,20,0.92);',
        '  -webkit-backdrop-filter:blur(22px);backdrop-filter:blur(22px);',
        '  border:1px solid rgba(255,255,255,0.15);',
        '  box-shadow:0 12px 40px rgba(0,0,0,0.6);',
        '  display:none;',
        '}',
        '.acct-dropdown.show{display:block;}',
        '.acct-dropdown .acct-dd-header{',
        '  padding:14px 16px;border-bottom:1px solid rgba(255,255,255,0.1);',
        '}',
        '.acct-dropdown .acct-dd-name{color:#0ff;font-weight:bold;font-size:15px;}',
        '.acct-dropdown .acct-dd-email{color:#888;font-size:12px;margin-top:2px;word-break:break-all;}',
        '.acct-dropdown .acct-dd-item{',
        '  display:block;padding:12px 16px;color:rgba(255,255,255,0.8);',
        '  text-decoration:none;cursor:pointer;font-size:14px;transition:background .2s;',
        '}',
        '.acct-dropdown .acct-dd-item:hover{background:rgba(0,229,255,0.12);color:#0ff;}',
        /* 模态框 */
        '.acct-modal-overlay{',
        '  position:fixed;inset:0;z-index:20000;',
        '  background:rgba(0,0,0,0.6);-webkit-backdrop-filter:blur(6px);backdrop-filter:blur(6px);',
        '  display:flex;align-items:center;justify-content:center;',
        '  opacity:0;visibility:hidden;transition:all .3s ease;',
        '}',
        '.acct-modal-overlay.show{opacity:1;visibility:visible;}',
        '.acct-modal{',
        '  width:380px;max-width:92vw;max-height:90vh;overflow-y:auto;',
        '  background:rgba(15,15,30,0.92);',
        '  -webkit-backdrop-filter:blur(28px) saturate(180%);backdrop-filter:blur(28px) saturate(180%);',
        '  border:1px solid rgba(255,255,255,0.16);',
        '  border-radius:24px;',
        '  box-shadow:0 20px 60px rgba(0,0,0,0.6),inset 0 1px 0 rgba(255,255,255,0.2);',
        '  padding:28px;position:relative;',
        '  transform:translateY(20px) scale(.96);transition:transform .3s ease;',
        '}',
        '.acct-modal-overlay.show .acct-modal{transform:translateY(0) scale(1);}',
        '.acct-modal-close{',
        '  position:absolute;top:14px;right:16px;cursor:pointer;',
        '  color:rgba(255,255,255,0.5);font-size:22px;line-height:1;transition:color .2s;',
        '  background:none;border:none;',
        '}',
        '.acct-modal-close:hover{color:#fff;}',
        '.acct-modal h2{',
        '  text-align:center;margin:0 0 6px;font-size:22px;',
        '  background:linear-gradient(90deg,#00e5ff,#ff00ff);',
        '  -webkit-background-clip:text;-webkit-text-fill-color:transparent;',
        '  background-clip:text;',
        '}',
        '.acct-modal .acct-sub{text-align:center;color:#888;font-size:13px;margin-bottom:20px;}',
        '.acct-tabs{display:flex;gap:8px;margin-bottom:18px;}',
        '.acct-tab{',
        '  flex:1;padding:10px;text-align:center;cursor:pointer;',
        '  border-radius:12px;font-size:14px;color:rgba(255,255,255,0.6);',
        '  background:rgba(255,255,255,0.05);border:1px solid transparent;transition:all .2s;',
        '}',
        '.acct-tab.active{',
        '  background:rgba(0,229,255,0.12);border-color:rgba(0,229,255,0.4);color:#0ff;',
        '}',
        '.acct-form{display:none;}',
        '.acct-form.active{display:block;}',
        '.acct-input{',
        '  width:100%;box-sizing:border-box;margin:8px 0;padding:12px 14px;',
        '  background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.15);',
        '  border-radius:12px;color:#fff;font-size:14px;outline:none;transition:border .2s,box-shadow .2s;',
        '}',
        '.acct-input:focus{border-color:#00e5ff;box-shadow:0 0 0 3px rgba(0,229,255,0.15);}',
        '.acct-input::placeholder{color:#666;}',
        '.acct-submit{',
        '  width:100%;margin-top:14px;padding:13px;border:none;border-radius:12px;cursor:pointer;',
        '  background:linear-gradient(90deg,#00e5ff,#ff00ff);color:#000;',
        '  font-size:15px;font-weight:bold;transition:opacity .2s,transform .1s;',
        '}',
        '.acct-submit:hover{opacity:.9;}',
        '.acct-submit:active{transform:scale(.98);}',
        '.acct-submit:disabled{opacity:.5;cursor:not-allowed;}',
        '.acct-error{',
        '  color:#ff4466;background:rgba(255,68,102,0.1);border:1px solid rgba(255,68,102,0.3);',
        '  padding:10px 14px;border-radius:10px;font-size:13px;margin:10px 0;display:none;',
        '}',
        '.acct-error.show{display:block;}',
        '.acct-success{',
        '  color:#00ff88;background:rgba(0,255,136,0.1);border:1px solid rgba(0,255,136,0.3);',
        '  padding:10px 14px;border-radius:10px;font-size:13px;margin:10px 0;display:none;',
        '}',
        '.acct-success.show{display:block;}',
        '/* 同意协议复选框 */',
        '.acct-agree{',
        '  display:flex;align-items:center;gap:8px;',
        '  margin:12px 0 4px;font-size:13px;color:rgba(255,255,255,0.7);',
        '  cursor:pointer;user-select:none;',
        '}',
        '.acct-agree input[type="checkbox"]{',
        '  width:16px;height:16px;cursor:pointer;accent-color:#00e5ff;',
        '  border-radius:4px;',
        '}',
        '.acct-agree a{color:#00e5ff;text-decoration:none;}',
        '.acct-agree a:hover{text-decoration:underline;}',
        '@media (max-width:640px){',
        '  .acct-btn{left:12px;padding:6px 12px;font-size:12px;top:10px;}',
        '  .acct-dropdown{left:12px;top:50px;}',
        '}'
    ].join('\n');

    var styleEl = document.createElement('style');
    styleEl.textContent = css;
    document.head.appendChild(styleEl);

    /* ---------- 工具函数 ---------- */
    function el(tag, attrs, children) {
        var e = document.createElement(tag);
        if (attrs) for (var k in attrs) {
            if (k === 'class') e.className = attrs[k];
            else if (k === 'html') e.innerHTML = attrs[k];
            else if (k.startsWith('on')) e.addEventListener(k.slice(2), attrs[k]);
            else e.setAttribute(k, attrs[k]);
        }
        if (children) {
            if (typeof children === 'string') e.textContent = children;
            else children.forEach(function (c) { if (c) e.appendChild(c); });
        }
        return e;
    }

    function t(zh, en) {
        return window.I18N ? I18N.t(zh, en) : zh;
    }

    function ajax(url, data, callback) {
        var xhr = new XMLHttpRequest();
        xhr.open('POST', url, true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.onload = function () {
            try {
                callback(JSON.parse(xhr.responseText));
            } catch (e) {
                callback({ success: false, error: '服务器响应异常' });
            }
        };
        xhr.onerror = function () {
            callback({ success: false, error: '网络错误，请检查连接' });
        };
        var form = new FormData();
        for (var k in data) form.append(k, data[k]);
        xhr.send(form);
    }

    /* ---------- 拖动功能 ---------- */
    function makeDraggable(el, storageKey, onMove) {
        var dragging = false, moved = false;
        var startX, startY, origX, origY;
        try {
            var saved = localStorage.getItem(storageKey);
            if (saved) {
                var pos = JSON.parse(saved);
                el.style.left = pos.x + 'px';
                el.style.top = pos.y + 'px';
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
            if (onMove) onMove(nx, ny);
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
    /* ---------- 构建 UI ---------- */
    var acctBtn, acctDropdown, modalOverlay;
    // 弹窗元素引用（用于语言切换时更新）
    var modalEls = {};

    function buildAccountButton() {
        acctBtn = el('div', { class: 'acct-btn', id: 'acct-btn' });
        acctDropdown = el('div', { class: 'acct-dropdown', id: 'acct-dropdown' });
        document.body.appendChild(acctBtn);
        document.body.appendChild(acctDropdown);
        makeDraggable(acctBtn, 'slexion_login_pos', function(x, y) { acctDropdown.style.left = x + 'px'; acctDropdown.style.top = (y + acctBtn.offsetHeight + 6) + 'px'; });
        updateButtonUI();
    }

    function updateButtonUI() {
        acctBtn.innerHTML = '';
        acctDropdown.innerHTML = '';

        if (state.loggedin) {
            var initial = state.username ? state.username.charAt(0).toUpperCase() : '?';
            var avatarBox = el('div', { class: 'acct-avatar' });
            if (state.avatar) {
                var img = el('img', { src: '/usertx/' + state.avatar, alt: '', style: 'width:100%;height:100%;border-radius:50%;object-fit:cover;' });
                avatarBox.appendChild(img);
            } else {
                avatarBox.textContent = initial;
            }
            acctBtn.appendChild(avatarBox);
            acctBtn.appendChild(el('span', {}, state.username));
            acctBtn.appendChild(el('span', { style: 'font-size:10px;opacity:.6;' }, '▾'));

            var header = el('div', { class: 'acct-dd-header' }, [
                el('div', { class: 'acct-dd-name' }, state.username),
                el('div', { class: 'acct-dd-email' }, state.email || '')
            ]);
            var centerItem = el('div', { class: 'acct-dd-item', 'data-en': 'User Center' }, t('用户中心', 'User Center'));
            centerItem.addEventListener('click', function (e) {
                e.stopPropagation();
                acctDropdown.classList.remove('show');
                window.location.href = '/accountcenter';
            });
            var logoutItem = el('div', { class: 'acct-dd-item', 'data-en': '退出登录' }, t('退出登录', 'Log out'));
            logoutItem.addEventListener('click', doLogout);

            acctDropdown.appendChild(header);
            acctDropdown.appendChild(centerItem);
            acctDropdown.appendChild(logoutItem);

            acctBtn.onclick = function (e) {
                e.stopPropagation();
                acctDropdown.classList.toggle('show');
            };
        } else {
            acctBtn.appendChild(el('span', { 'data-en': '登录' }, t('登录', 'Sign in')));
            acctBtn.onclick = function (e) {
                e.stopPropagation();
                openModal('login');
            };
        }
    }

    function buildModal() {
        modalOverlay = el('div', { class: 'acct-modal-overlay', id: 'acct-modal-overlay' });

        var modal = el('div', { class: 'acct-modal' });
        var closeBtn = el('button', { class: 'acct-modal-close', html: '&times;' });
        closeBtn.addEventListener('click', closeModal);

        var title = el('h2', { id: 'acct-modal-title' }, 'Slexion Account');
        modalEls.title = title;
        var sub = el('div', { class: 'acct-sub' }, t('登录或注册以继续', 'Sign in or register to continue'));
        modalEls.sub = sub;

        // Tab 切换
        var tabLogin = el('div', { class: 'acct-tab active' }, t('登录', 'Sign in'));
        var tabRegister = el('div', { class: 'acct-tab' }, t('注册', 'Register'));
        modalEls.tabLogin = tabLogin;
        modalEls.tabRegister = tabRegister;
        var tabs = el('div', { class: 'acct-tabs' }, [tabLogin, tabRegister]);

        // 错误/成功提示
        var errorBox = el('div', { class: 'acct-error', id: 'acct-error' });
        var successBox = el('div', { class: 'acct-success', id: 'acct-success' });

        // 登录表单
        var loginForm = el('div', { class: 'acct-form active', id: 'acct-form-login' });
        var loginUser = el('input', { class: 'acct-input', type: 'text', name: 'username', placeholder: t('用户名或邮箱', 'Username or email'), required: '' });
        var loginPass = el('input', { class: 'acct-input', type: 'password', name: 'password', placeholder: t('密码', 'Password'), required: '' });
        var loginAgree = el('label', { class: 'acct-agree' }, [
            el('input', { type: 'checkbox', name: 'agree', id: 'login-agree' }),
            el('span', { html: t('我已阅读并同意', 'I have read and agree to the ') + '<a href="/eula/eula.html" target="_blank">' + t('《用户协议》', 'User Agreement') + '</a>' })
        ]);
        var loginBtn = el('button', { class: 'acct-submit', type: 'button' }, t('登录', 'Sign in'));
        modalEls.loginUser = loginUser;
        modalEls.loginPass = loginPass;
        modalEls.loginAgree = loginAgree;
        modalEls.loginBtn = loginBtn;
        loginBtn.addEventListener('click', doLogin);
        loginForm.appendChild(loginUser);
        loginForm.appendChild(loginPass);
        loginForm.appendChild(loginAgree);
        loginForm.appendChild(loginBtn);

        // 注册表单（邮箱可选）
        var registerForm = el('div', { class: 'acct-form', id: 'acct-form-register' });
        var regUser = el('input', { class: 'acct-input', type: 'text', name: 'username', placeholder: t('用户名（3-20位）', 'Username (3-20 chars)'), required: '' });
        var regEmail = el('input', { class: 'acct-input', type: 'email', name: 'email', placeholder: t('邮箱（可选）', 'Email (optional)') });
        var regPass = el('input', { class: 'acct-input', type: 'password', name: 'password', placeholder: t('密码（至少6位）', 'Password (min 6 chars)'), required: '' });
        var regPass2 = el('input', { class: 'acct-input', type: 'password', name: 'confirm_password', placeholder: t('确认密码', 'Confirm password'), required: '' });
        var regAgree = el('label', { class: 'acct-agree' }, [
            el('input', { type: 'checkbox', name: 'agree', id: 'register-agree' }),
            el('span', { html: t('我已阅读并同意', 'I have read and agree to the ') + '<a href="/eula/eula.html" target="_blank">' + t('《用户协议》', 'User Agreement') + '</a>' })
        ]);
        var regBtn = el('button', { class: 'acct-submit', type: 'button' }, t('注册并登录', 'Register & Sign in'));
        modalEls.regUser = regUser;
        modalEls.regEmail = regEmail;
        modalEls.regPass = regPass;
        modalEls.regPass2 = regPass2;
        modalEls.regAgree = regAgree;
        modalEls.regBtn = regBtn;
        regBtn.addEventListener('click', doRegister);
        registerForm.appendChild(regUser);
        registerForm.appendChild(regEmail);
        registerForm.appendChild(regPass);
        registerForm.appendChild(regPass2);
        registerForm.appendChild(regAgree);
        registerForm.appendChild(regBtn);

        modal.appendChild(closeBtn);
        modal.appendChild(title);
        modal.appendChild(sub);
        modal.appendChild(tabs);
        modal.appendChild(errorBox);
        modal.appendChild(successBox);
        modal.appendChild(loginForm);
        modal.appendChild(registerForm);
        modalOverlay.appendChild(modal);
        document.body.appendChild(modalOverlay);

        // Tab 切换
        tabLogin.addEventListener('click', function () {
            tabLogin.classList.add('active');
            tabRegister.classList.remove('active');
            loginForm.classList.add('active');
            registerForm.classList.remove('active');
            hideMessages();
        });
        tabRegister.addEventListener('click', function () {
            tabRegister.classList.add('active');
            tabLogin.classList.remove('active');
            registerForm.classList.add('active');
            loginForm.classList.remove('active');
            hideMessages();
        });

        // 点击遮罩关闭
        modalOverlay.addEventListener('click', function (e) {
            if (e.target === modalOverlay) closeModal();
        });
        // ESC 关闭
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeModal();
        });
    }

    function openModal(tab) {
        modalOverlay.classList.add('show');
        if (tab === 'register') {
            document.querySelector('.acct-tab:nth-child(2)').click();
        } else {
            document.querySelector('.acct-tab:nth-child(1)').click();
        }
        hideMessages();
    }

    function closeModal() {
        modalOverlay.classList.remove('show');
        // 清空所有表单
        var inputs = modalOverlay.querySelectorAll('input');
        inputs.forEach(function (inp) {
            inp.value = '';
            if (inp.type === 'checkbox') inp.checked = false;
        });
        hideMessages();
    }

    /* ---------- 弹窗语言切换 ---------- */
    function updateModalLang() {
        if (!modalEls.title) return;
        modalEls.title.textContent = 'Slexion Account';
        modalEls.sub.textContent = t('登录或注册以继续', 'Sign in or register to continue');
        modalEls.tabLogin.textContent = t('登录', 'Sign in');
        modalEls.tabRegister.textContent = t('注册', 'Register');
        modalEls.loginUser.setAttribute('placeholder', t('用户名或邮箱', 'Username or email'));
        modalEls.loginPass.setAttribute('placeholder', t('密码', 'Password'));
        modalEls.loginBtn.textContent = t('登录', 'Sign in');
        modalEls.regUser.setAttribute('placeholder', t('用户名（3-20位）', 'Username (3-20 chars)'));
        modalEls.regEmail.setAttribute('placeholder', t('邮箱（可选）', 'Email (optional)'));
        modalEls.regPass.setAttribute('placeholder', t('密码（至少6位）', 'Password (min 6 chars)'));
        modalEls.regPass2.setAttribute('placeholder', t('确认密码', 'Confirm password'));
        modalEls.regBtn.textContent = t('注册并登录', 'Register & Sign in');
        // 更新同意协议文字
        var agreeText = t('我已阅读并同意', 'I have read and agree to the ') + '<a href="/eula/eula.html" target="_blank">' + t('《用户协议》', 'User Agreement') + '</a>';
        if (modalEls.loginAgree) {
            modalEls.loginAgree.querySelector('span').innerHTML = agreeText;
        }
        if (modalEls.regAgree) {
            modalEls.regAgree.querySelector('span').innerHTML = agreeText;
        }
    }

    function showError(msg) {
        var box = document.getElementById('acct-error');
        box.textContent = msg;
        box.classList.add('show');
        document.getElementById('acct-success').classList.remove('show');
    }

    function showSuccess(msg) {
        var box = document.getElementById('acct-success');
        box.textContent = msg;
        box.classList.add('show');
        document.getElementById('acct-error').classList.remove('show');
    }

    function hideMessages() {
        document.getElementById('acct-error').classList.remove('show');
        document.getElementById('acct-success').classList.remove('show');
    }

    /* ---------- 登录/注册/登出 ---------- */
    function doLogin() {
        var form = document.getElementById('acct-form-login');
        var username = form.querySelector('[name=username]').value.trim();
        var password = form.querySelector('[name=password]').value;
        var agree = form.querySelector('#login-agree');
        var btn = form.querySelector('.acct-submit');

        if (!username || !password) {
            showError(t('请输入账号和密码', 'Please enter account and password'));
            return;
        }
        if (!agree.checked) {
            showError(t('请先阅读并同意《用户协议》', 'Please read and agree to the User Agreement first'));
            return;
        }

        btn.disabled = true;
        btn.textContent = t('登录中...', 'Signing in...');
        hideMessages();

        ajax(API.login, {
            username: username,
            password: password,
            csrf_token: state.csrf
        }, function (res) {
            btn.disabled = false;
            btn.textContent = t('登录', 'Sign in');
            if (res.success) {
                state.loggedin = true;
                state.username = res.username;
                state.email = res.email;
                state.avatar = res.avatar || '';
                showSuccess(t('登录成功', 'Login successful'));
                updateButtonUI();
                setTimeout(closeModal, 800);
            } else {
                showError(res.error || t('登录失败', 'Login failed'));
            }
        });
    }

    function doRegister() {
        var form = document.getElementById('acct-form-register');
        var username = form.querySelector('[name=username]').value.trim();
        var email = form.querySelector('[name=email]').value.trim();
        var password = form.querySelector('[name=password]').value;
        var confirm = form.querySelector('[name=confirm_password]').value;
        var agree = form.querySelector('#register-agree');
        var btn = form.querySelector('.acct-submit');

        if (!username || !password || !confirm) {
            showError(t('请填写所有必填字段', 'Please fill in all required fields'));
            return;
        }
        if (password !== confirm) {
            showError(t('两次输入的密码不一致', 'Passwords do not match'));
            return;
        }
        if (!agree.checked) {
            showError(t('请先阅读并同意《用户协议》', 'Please read and agree to the User Agreement first'));
            return;
        }

        btn.disabled = true;
        btn.textContent = t('注册中...', 'Registering...');
        hideMessages();

        ajax(API.register, {
            username: username,
            email: email,
            password: password,
            confirm_password: confirm,
            csrf_token: state.csrf
        }, function (res) {
            btn.disabled = false;
            btn.textContent = t('注册并登录', 'Register & Sign in');
            if (res.success) {
                state.loggedin = true;
                state.username = res.username;
                state.email = res.email;
                state.avatar = res.avatar || '';
                showSuccess(t('注册成功，已自动登录', 'Registered and signed in'));
                updateButtonUI();
                setTimeout(closeModal, 1000);
            } else {
                showError(res.error || t('注册失败', 'Registration failed'));
            }
        });
    }

    function doLogout() {
        acctDropdown.classList.remove('show');
        ajax(API.logout, {}, function () {
            state.loggedin = false;
            state.username = '';
            state.email = '';
            state.avatar = '';
            updateButtonUI();
            // 刷新 session 获取新 csrf
            fetchSession();
        });
    }

    /* ---------- 获取登录状态 ---------- */
    function fetchSession() {
        var xhr = new XMLHttpRequest();
        xhr.open('GET', API.session + '?t=' + Date.now(), true);
        xhr.onload = function () {
            try {
                var res = JSON.parse(xhr.responseText);
                state.loggedin = !!res.loggedin;
                state.username = res.username || '';
                state.email = res.email || '';
                state.avatar = res.avatar || '';
                state.csrf = res.csrf_token || '';
                updateButtonUI();
            } catch (e) { /* ignore */ }
        };
        xhr.send();
    }

    /* ---------- 初始化 ---------- */
    function init() {
        buildAccountButton();
        buildModal();
        fetchSession();

        // 检测 URL 参数自动打开登录弹窗
        if (window.location.search.indexOf('login=1') !== -1) {
            setTimeout(function () { openModal('login'); }, 500);
        }

        // 点击外部关闭下拉菜单
        document.addEventListener('click', function () {
            if (acctDropdown) acctDropdown.classList.remove('show');
        });

        // 语言切换时刷新按钮文字和弹窗文字
        if (window.addEventListener) {
            window.addEventListener('i18nchange', function () {
                updateButtonUI();
                updateModalLang();
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // 暴露全局登录函数，供其他页面调用
    window.openLoginModal = function () { openModal('login'); };
    window.openRegisterModal = function () { openModal('register'); };
})();

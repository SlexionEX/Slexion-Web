/* ============================================================
 *  Slexion Account - 全站登录/注册弹窗组件
 *  用法：在每个页面 navbar.js 之后、i18n.js 之前引入
 *    <script src="/navbar.js"></script>
 *    <script src="/account/auth.js"></script>
 *    <script src="/i18n.js"></script>
 * ============================================================ */
(function () {
    'use strict';

    var API_BASE = '/account';
    var csrfToken = '';
    var currentUser = null;
    var isLoggedIn = false;

    /* ---------- 工具函数 ---------- */
    function $(sel, ctx) { return (ctx || document).querySelector(sel); }
    function $$(sel, ctx) { return Array.from((ctx || document).querySelectorAll(sel)); }
    function el(tag, attrs, children) {
        var e = document.createElement(tag);
        if (attrs) for (var k in attrs) {
            if (k === 'class') e.className = attrs[k];
            else if (k === 'style') e.style.cssText = attrs[k];
            else if (k.startsWith('on')) e.addEventListener(k.slice(2), attrs[k]);
            else e.setAttribute(k, attrs[k]);
        }
        if (children) {
            if (typeof children === 'string') e.textContent = children;
            else children.forEach(function (c) { if (c) e.appendChild(c); });
        }
        return e;
    }

    /* ---------- 注入 CSS ---------- */
    function injectCSS() {
        var css = [
            '.auth-btn{',
            '  margin-left:auto;',
            '  padding:7px 18px;',
            '  border-radius:999px;',
            '  background:linear-gradient(135deg,#00e5ff,#ff00ff);',
            '  color:#000;',
            '  font-weight:600;',
            '  font-size:14px;',
            '  border:none;',
            '  cursor:pointer;',
            '  white-space:nowrap;',
            '  transition:transform .2s,box-shadow .2s;',
            '  box-shadow:0 2px 10px rgba(0,229,255,.3);',
            '}',
            '.auth-btn:hover{transform:translateY(-1px);box-shadow:0 4px 16px rgba(255,0,255,.45);}',
            '.auth-user{',
            '  margin-left:auto;',
            '  display:flex;align-items:center;gap:8px;',
            '  padding:6px 8px 6px 16px;',
            '  border-radius:999px;',
            '  background:rgba(255,255,255,.08);',
            '  border:1px solid rgba(255,255,255,.15);',
            '}',
            '.auth-user .uname{color:#fff;font-size:14px;font-weight:500;max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}',
            '.auth-user .logout{',
            '  padding:5px 14px;border-radius:999px;border:none;cursor:pointer;',
            '  background:rgba(255,80,80,.15);color:#ff6b6b;font-size:12px;font-weight:600;',
            '  transition:background .2s;',
            '}',
            '.auth-user .logout:hover{background:rgba(255,80,80,.3);}',
            /* Modal */
            '.auth-overlay{',
            '  position:fixed;inset:0;z-index:99990;',
            '  background:rgba(0,0,0,.65);',
            '  backdrop-filter:blur(8px);',
            '  -webkit-backdrop-filter:blur(8px);',
            '  display:flex;align-items:center;justify-content:center;',
            '  opacity:0;visibility:hidden;transition:opacity .25s,visibility .25s;',
            '}',
            '.auth-overlay.show{opacity:1;visibility:visible;}',
            '.auth-modal{',
            '  width:380px;max-width:92vw;',
            '  background:rgba(15,15,30,.85);',
            '  backdrop-filter:blur(24px) saturate(180%);',
            '  -webkit-backdrop-filter:blur(24px) saturate(180%);',
            '  border:1px solid rgba(255,255,255,.15);',
            '  border-radius:24px;',
            '  padding:32px 28px;',
            '  box-shadow:0 24px 64px rgba(0,0,0,.6),inset 0 1px 0 rgba(255,255,255,.15);',
            '  transform:translateY(20px) scale(.96);',
            '  transition:transform .25s;',
            '  position:relative;',
            '}',
            '.auth-overlay.show .auth-modal{transform:translateY(0) scale(1);}',
            '.auth-close{',
            '  position:absolute;top:14px;right:14px;',
            '  width:32px;height:32px;border-radius:50%;',
            '  background:rgba(255,255,255,.08);border:none;cursor:pointer;',
            '  color:#aaa;font-size:18px;line-height:1;',
            '  display:flex;align-items:center;justify-content:center;',
            '  transition:background .2s,color .2s;',
            '}',
            '.auth-close:hover{background:rgba(255,80,80,.2);color:#ff6b6b;}',
            '.auth-tabs{display:flex;gap:8px;margin-bottom:24px;}',
            '.auth-tab{',
            '  flex:1;padding:10px;border-radius:12px;border:none;cursor:pointer;',
            '  background:rgba(255,255,255,.06);color:#888;font-size:14px;font-weight:600;',
            '  transition:all .2s;',
            '}',
            '.auth-tab.active{background:linear-gradient(135deg,#00e5ff,#ff00ff);color:#000;}',
            '.auth-form{display:none;}',
            '.auth-form.active{display:block;}',
            '.auth-field{margin-bottom:16px;}',
            '.auth-field label{display:block;color:#aaa;font-size:12px;margin-bottom:6px;font-weight:500;}',
            '.auth-field input{',
            '  width:100%;box-sizing:border-box;',
            '  padding:12px 16px;border-radius:12px;',
            '  background:rgba(0,0,0,.4);',
            '  border:1px solid rgba(0,229,255,.3);',
            '  color:#fff;font-size:14px;',
            '  transition:border-color .2s,box-shadow .2s;',
            '}',
            '.auth-field input:focus{outline:none;border-color:#ff00ff;box-shadow:0 0 0 3px rgba(255,0,255,.15);}',
            '.auth-field input::placeholder{color:#555;}',
            '.auth-terms{',
            '  display:flex;align-items:center;gap:8px;',
            '  font-size:12px;color:#888;margin:4px 0 12px;',
            '  user-select:none;cursor:pointer;line-height:1.4;',
            '}',
            '.auth-terms input{width:15px;height:15px;accent-color:#00e5ff;cursor:pointer;flex-shrink:0;}',
            '.auth-terms a{color:#00e5ff;text-decoration:none;white-space:nowrap;}',
            '.auth-terms a:hover{text-decoration:underline;}',
            '.auth-submit{',
            '  width:100%;padding:13px;border-radius:12px;border:none;cursor:pointer;',
            '  background:linear-gradient(135deg,#00e5ff,#ff00ff);',
            '  color:#000;font-size:15px;font-weight:700;',
            '  margin-top:8px;transition:transform .15s,box-shadow .2s;',
            '  box-shadow:0 4px 16px rgba(0,229,255,.3);',
            '}',
            '.auth-submit:hover{transform:translateY(-1px);box-shadow:0 6px 20px rgba(255,0,255,.4);}',
            '.auth-submit:active{transform:translateY(0);}',
            '.auth-submit:disabled{opacity:.5;cursor:not-allowed;transform:none;}',
            '.auth-msg{',
            '  padding:10px 14px;border-radius:10px;font-size:13px;margin-top:12px;',
            '  display:none;',
            '}',
            '.auth-msg.error{display:block;background:rgba(255,80,80,.12);color:#ff6b6b;border:1px solid rgba(255,80,80,.25);}',
            '.auth-msg.success{display:block;background:rgba(0,229,255,.1);color:#00e5ff;border:1px solid rgba(0,229,255,.25);}',
            '.auth-msg.warn{display:block;background:rgba(255,180,0,.1);color:#ffb400;border:1px solid rgba(255,180,0,.25);}',
            '@media(max-width:640px){',
            '  .auth-modal{padding:24px 20px;}',
            '  .auth-user .uname{max-width:80px;}',
            '}'
        ].join('\n');
        var style = document.createElement('style');
        style.textContent = css;
        document.head.appendChild(style);
    }

    /* ---------- 构建 Modal ---------- */
    var overlay, loginForm, registerForm, msgEl;

    function buildModal() {
        overlay = el('div', { class: 'auth-overlay', onclick: function (e) { if (e.target === overlay) closeModal(); } });

        var modal = el('div', { class: 'auth-modal' });
        modal.appendChild(el('button', { class: 'auth-close', onclick: closeModal, 'aria-label': '关闭' }, '×'));

        // Tabs
        var tabs = el('div', { class: 'auth-tabs' });
        var tabLogin = el('button', { class: 'auth-tab active', type: 'button', onclick: function () { switchTab('login'); } }, '登录');
        var tabRegister = el('button', { class: 'auth-tab', type: 'button', onclick: function () { switchTab('register'); } }, '注册');
        tabs.appendChild(tabLogin);
        tabs.appendChild(tabRegister);
        modal.appendChild(tabs);

        // 登录表单
        loginForm = el('form', { class: 'auth-form active', onsubmit: handleLogin });
        loginForm.appendChild(buildField('username', '用户名', 'text', '请输入用户名', true));
        loginForm.appendChild(buildField('password', '密码', 'password', '请输入密码', true));
        loginForm.appendChild(buildTerms('login-terms'));
        loginForm.appendChild(el('button', { class: 'auth-submit', type: 'submit' }, '登 录'));
        modal.appendChild(loginForm);

        // 注册表单
        registerForm = el('form', { class: 'auth-form', onsubmit: handleRegister });
        registerForm.appendChild(buildField('reg-username', '用户名', 'text', '3-20位，字母/数字/中文', true));
        registerForm.appendChild(buildField('reg-email', '邮箱（选填）', 'email', '用于找回密码', false));
        registerForm.appendChild(buildField('reg-password', '密码', 'password', '至少6位', true));
        registerForm.appendChild(buildField('reg-confirm', '确认密码', 'password', '再次输入密码', true));
        registerForm.appendChild(buildTerms('reg-terms'));
        registerForm.appendChild(el('button', { class: 'auth-submit', type: 'submit' }, '注册并登录'));
        modal.appendChild(registerForm);

        // 消息区
        msgEl = el('div', { class: 'auth-msg' });
        modal.appendChild(msgEl);

        overlay.appendChild(modal);
        document.body.appendChild(overlay);
    }

    function buildField(id, label, type, placeholder, required) {
        var wrap = el('div', { class: 'auth-field' });
        wrap.appendChild(el('label', { for: id }, label));
        var input = el('input', { id: id, type: type, placeholder: placeholder, autocomplete: type === 'password' ? 'current-password' : 'username' });
        if (required) input.required = true;
        wrap.appendChild(input);
        return wrap;
    }

    // 同意协议勾选框（默认勾选，可取消；提交时校验）
    function buildTerms(id) {
        var wrap = el('label', { class: 'auth-terms' });
        var box = el('input', { type: 'checkbox', id: id, checked: true });
        wrap.appendChild(box);
        wrap.appendChild(document.createTextNode('我已阅读并同意'));
        var link = el('a', { href: '/eula', target: '_blank', rel: 'noopener' }, '《用户协议》');
        wrap.appendChild(link);
        return wrap;
    }

    function switchTab(tab) {
        var loginTab = loginForm.parentElement.querySelector('.auth-tab:first-child');
        var regTab = loginForm.parentElement.querySelector('.auth-tab:last-child');
        if (tab === 'login') {
            loginTab.classList.add('active');
            regTab.classList.remove('active');
            loginForm.classList.add('active');
            registerForm.classList.remove('active');
        } else {
            regTab.classList.add('active');
            loginTab.classList.remove('active');
            registerForm.classList.add('active');
            loginForm.classList.remove('active');
        }
        hideMsg();
    }

    function showMsg(text, type) {
        msgEl.textContent = text;
        msgEl.className = 'auth-msg ' + (type || 'error');
    }
    function hideMsg() { msgEl.className = 'auth-msg'; msgEl.textContent = ''; }

    function openModal(tab) {
        if (!overlay) buildModal();
        overlay.classList.add('show');
        document.body.style.overflow = 'hidden';
        if (tab) switchTab(tab);
        hideMsg();
        setTimeout(function () {
            var firstInput = overlay.querySelector('input');
            if (firstInput) firstInput.focus();
        }, 100);
    }

    function closeModal() {
        if (overlay) {
            overlay.classList.remove('show');
            document.body.style.overflow = '';
        }
    }

    /* ---------- API 调用 ---------- */
    function apiPost(url, data) {
        var fd = new FormData();
        fd.append('csrf_token', csrfToken);
        for (var k in data) {
            if (data.hasOwnProperty(k)) fd.append(k, data[k]);
        }
        return fetch(API_BASE + url, {
            method: 'POST',
            credentials: 'same-origin',
            body: fd
        }).then(function (r) { return r.json().then(function (d) { return { status: r.status, data: d }; }); });
    }

    function handleLogin(e) {
        e.preventDefault();
        var btn = loginForm.querySelector('.auth-submit');
        btn.disabled = true;
        btn.textContent = '登录中...';

        var username = $('#username').value.trim();
        var password = $('#password').value;

        var terms = $('#login-terms');
        if (terms && !terms.checked) {
            showMsg('请先勾选同意《用户协议》', 'warn');
            btn.disabled = false;
            btn.textContent = '登 录';
            return;
        }

        apiPost('/account/login.php', { username: username, password: password })
            .then(function (res) {
                if (res.data.success) {
                    showMsg('登录成功，正在跳转...', 'success');
                    isLoggedIn = true;
                    currentUser = { username: res.data.username, email: res.data.email || '', avatar: res.data.avatar || '' };
                    updateAuthUI();
                    setTimeout(closeModal, 600);
                } else {
                    showMsg(res.data.error || res.data.msg || '登录失败', res.data.locked ? 'warn' : 'error');
                    // 刷新 CSRF token
                    refreshSession();
                }
            })
            .catch(function () { showMsg('网络错误，请稍后重试', 'error'); })
            .finally(function () {
                btn.disabled = false;
                btn.textContent = '登 录';
            });
    }

    function handleRegister(e) {
        e.preventDefault();
        var btn = registerForm.querySelector('.auth-submit');
        btn.disabled = true;
        btn.textContent = '注册中...';

        var username = $('#reg-username').value.trim();
        var email = $('#reg-email').value.trim();
        var password = $('#reg-password').value;
        var confirm = $('#reg-confirm').value;

        if (password !== confirm) {
            showMsg('两次输入的密码不一致', 'warn');
            btn.disabled = false;
            btn.textContent = '注册并登录';
            return;
        }

        var terms = $('#reg-terms');
        if (terms && !terms.checked) {
            showMsg('请先勾选同意《用户协议》', 'warn');
            btn.disabled = false;
            btn.textContent = '注册并登录';
            return;
        }

        apiPost('/account/register.php', { username: username, email: email, password: password, confirm_password: confirm })
            .then(function (res) {
                if (res.data.success) {
                    showMsg('注册成功，已自动登录', 'success');
                    isLoggedIn = true;
                    currentUser = { username: res.data.username, email: res.data.email || '', avatar: res.data.avatar || '' };
                    updateAuthUI();
                    setTimeout(closeModal, 800);
                } else {
                    showMsg(res.data.error || res.data.msg || '注册失败', 'error');
                    refreshSession();
                }
            })
            .catch(function () { showMsg('网络错误，请稍后重试', 'error'); })
            .finally(function () {
                btn.disabled = false;
                btn.textContent = '注册并登录';
            });
    }

    function handleLogout() {
        fetch(API_BASE + '/logout.php?format=json', { credentials: 'same-origin' })
            .then(function () {
                isLoggedIn = false;
                currentUser = null;
                updateAuthUI();
                refreshSession();
            });
    }

    /* ---------- 导航栏登录按钮 ---------- */
    var authContainer;

    function updateAuthUI() {
        if (!authContainer) return;
        authContainer.innerHTML = '';

        if (isLoggedIn && currentUser) {
            var userBox = el('div', { class: 'auth-user' });
            userBox.appendChild(el('span', { class: 'uname', title: currentUser.username }, '👤 ' + currentUser.username));
            userBox.appendChild(el('button', { class: 'logout', type: 'button', onclick: handleLogout }, '登出'));
            authContainer.appendChild(userBox);
        } else {
            var loginBtn = el('button', { class: 'auth-btn', type: 'button', onclick: function () { openModal('login'); } });
            loginBtn.setAttribute('data-en', 'Login');
            loginBtn.textContent = '登录';
            authContainer.appendChild(loginBtn);
        }

        // 通知 i18n 重新翻译
        if (window.I18N) {
            try { window.dispatchEvent(new Event('i18nchange')); } catch (e) {}
        }
    }

    function injectAuthButton() {
        // 登录按钮已由 account.js 提供（左上角），此处不再注入任何按钮
        authContainer = null;
    }

    /* ---------- 初始化 ---------- */
    function refreshSession() {
        return fetch(API_BASE + '/session.php', { credentials: 'same-origin', cache: 'no-store' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                isLoggedIn = !!d.loggedin;
                currentUser = d.loggedin ? { username: d.username || '', email: d.email || '', avatar: d.avatar || '' } : null;
                csrfToken = d.csrf_token || '';
                updateAuthUI();
                return d;
            })
            .catch(function () {
                // 静默失败
            });
    }

    function init() {
        injectCSS();
        buildModal();
        injectAuthButton();
        refreshSession();

        // ESC 关闭弹窗
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && overlay && overlay.classList.contains('show')) closeModal();
        });

        // 暴露全局方法（供其他脚本调用）
        window.SlexionAuth = {
            open: openModal,
            close: closeModal,
            logout: handleLogout,
            isLoggedIn: function () { return isLoggedIn; },
            getUser: function () { return currentUser; },
            refresh: refreshSession
        };
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();

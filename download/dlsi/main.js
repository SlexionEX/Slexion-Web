/* ============================================================
   Slexion 资源库 — 一键复制链接
   配合 dlsi.html / style.css 使用
   ============================================================ */
(function () {
    // 兼容旧浏览器的剪贴板回退
    function copyText(text) {
        if (navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(text);
        }
        return new Promise(function (resolve, reject) {
            var ta = document.createElement('textarea');
            ta.value = text;
            ta.style.position = 'fixed';
            ta.style.opacity = '0';
            document.body.appendChild(ta);
            ta.focus();
            ta.select();
            try {
                document.execCommand('copy');
                resolve();
            } catch (e) {
                reject(e);
            } finally {
                document.body.removeChild(ta);
            }
        });
    }

    document.querySelectorAll('.copy-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var input = btn.parentElement.querySelector('.link-input');
            var text = input ? input.value : '';
            copyText(text).then(function () {
                var old = btn.textContent;
                btn.textContent = '已复制 ✓';
                btn.classList.add('copied');
                setTimeout(function () {
                    btn.textContent = old;
                    btn.classList.remove('copied');
                }, 1500);
            }).catch(function () {
                btn.textContent = '复制失败';
                setTimeout(function () {
                    btn.textContent = '一键复制链接';
                }, 1500);
            });
        });
    });
})();

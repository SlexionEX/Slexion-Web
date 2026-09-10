/**
 * 通用弹窗拖动工具 v2
 * 给所有 .draggable 弹窗的 .drag-handle 元素添加拖动功能
 * 修复：使用 fixed 定位，松开后保持位置不消失
 */
(function () {
    'use strict';

    function initDraggable(overlay) {
        if (!overlay) return;
        if (overlay.dataset.draggableInit === '1') return;
        overlay.dataset.draggableInit = '1';

        var handle = overlay.querySelector('.drag-handle') || overlay.querySelector('h1') || overlay.querySelector('h2');
        if (!handle) return;

        var box = overlay.firstElementChild || overlay;
        var isDragging = false;
        var startX = 0, startY = 0;
        var startLeft = 0, startTop = 0;

        handle.style.cursor = 'move';
        handle.style.userSelect = 'none';
        handle.style.webkitUserSelect = 'none';
        handle.style.touchAction = 'none';

        function getBoxRect() {
            return box.getBoundingClientRect();
        }

        function onStart(e) {
            // 不阻止点击按钮等交互
            if (e.target.tagName === 'BUTTON' || e.target.tagName === 'INPUT' || e.target.tagName === 'A') return;

            isDragging = true;
            var point = e.touches ? e.touches[0] : e;
            startX = point.clientX;
            startY = point.clientY;

            // 获取当前位置，切换到 fixed 定位
            var rect = getBoxRect();
            box.style.position = 'fixed';
            box.style.margin = '0';
            box.style.transform = 'none';
            box.style.left = rect.left + 'px';
            box.style.top = rect.top + 'px';
            box.style.right = 'auto';
            box.style.bottom = 'auto';

            startLeft = rect.left;
            startTop = rect.top;

            if (e.cancelable) e.preventDefault();
            e.stopPropagation();
        }

        function onMove(e) {
            if (!isDragging) return;
            var point = e.touches ? e.touches[0] : e;
            var dx = point.clientX - startX;
            var dy = point.clientY - startY;
            var newLeft = startLeft + dx;
            var newTop = startTop + dy;

            // 限制在视口内（至少保留标题栏可见）
            var maxLeft = window.innerWidth - 80;
            var maxTop = window.innerHeight - 40;
            newLeft = Math.max(-box.offsetWidth + 80, Math.min(newLeft, maxLeft));
            newTop = Math.max(0, Math.min(newTop, maxTop));

            box.style.left = newLeft + 'px';
            box.style.top = newTop + 'px';

            if (e.cancelable) e.preventDefault();
        }

        function onEnd(e) {
            if (isDragging) {
                isDragging = false;
                if (e && e.cancelable) e.preventDefault();
            }
        }

        handle.addEventListener('mousedown', onStart);
        document.addEventListener('mousemove', onMove);
        document.addEventListener('mouseup', onEnd);
        handle.addEventListener('touchstart', onStart, { passive: false });
        document.addEventListener('touchmove', onMove, { passive: false });
        document.addEventListener('touchend', onEnd);
        document.addEventListener('touchcancel', onEnd);
    }

    function initAll() {
        document.querySelectorAll('.draggable').forEach(initDraggable);
    }

    window.initDraggable = initDraggable;
    window.initAllDraggable = initAll;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAll);
    } else {
        initAll();
    }

    // 监听新弹窗出现
    var observer = new MutationObserver(function (mutations) {
        mutations.forEach(function (m) {
            m.addedNodes.forEach(function (node) {
                if (node.classList && node.classList.contains('draggable')) {
                    setTimeout(function () { initDraggable(node); }, 50);
                }
            });
        });
    });
    observer.observe(document.body, { childList: true, subtree: true });
})();

/**
 * 2048 无限模式游戏逻辑
 * 支持自定义棋盘大小（行x列，3-20）
 * 支持键盘和触屏操作
 * 方块移动/合并动画
 * 登录后保存最高分到服务器
 */
(function () {
    'use strict';

    var ROWS = 4;
    var COLS = 4;
    var board = [];
    var score = 0;
    var best = 0;
    var gameOver = false;
    var isLoggedIn = false;
    var tileIdCounter = 0;
    var tiles = {}; // id -> {value, row, col, element}

    // DOM 元素
    var boardEl, tilesEl, gridBgEl, scoreEl, bestEl, gameOverEl, finalScoreEl;
    var rowsInput, colsInput, loginTip;

    // 初始化
    function init() {
        boardEl = document.getElementById('board');
        tilesEl = document.getElementById('tiles');
        gridBgEl = document.getElementById('grid-bg');
        scoreEl = document.getElementById('score');
        bestEl = document.getElementById('best');
        gameOverEl = document.getElementById('game-over');
        finalScoreEl = document.getElementById('final-score');
        rowsInput = document.getElementById('rows-input');
        colsInput = document.getElementById('cols-input');
        loginTip = document.getElementById('login-tip');

        // 绑定事件
        document.getElementById('new-game').addEventListener('click', newGame);
        document.getElementById('retry-btn').addEventListener('click', newGame);

        // 输入框回车开始新游戏
        rowsInput.addEventListener('keypress', function (e) {
            if (e.key === 'Enter') newGame();
        });
        colsInput.addEventListener('keypress', function (e) {
            if (e.key === 'Enter') newGame();
        });

        // 键盘事件
        document.addEventListener('keydown', handleKeyDown);

        // 触屏事件
        var touchStartX = 0, touchStartY = 0, touchMoved = false;
        boardEl.addEventListener('touchstart', function (e) {
            touchStartX = e.touches[0].clientX;
            touchStartY = e.touches[0].clientY;
            touchMoved = false;
        }, { passive: true });

        boardEl.addEventListener('touchmove', function (e) {
            e.preventDefault();
            touchMoved = true;
        }, { passive: false });

        boardEl.addEventListener('touchend', function (e) {
            if (!touchMoved) return;
            var dx = e.changedTouches[0].clientX - touchStartX;
            var dy = e.changedTouches[0].clientY - touchStartY;
            var absDx = Math.abs(dx);
            var absDy = Math.abs(dy);

            if (Math.max(absDx, absDy) < 30) return;

            if (absDx > absDy) {
                if (dx > 0) move('right');
                else move('left');
            } else {
                if (dy > 0) move('down');
                else move('up');
            }
        }, { passive: true });

        // 检测登录状态
        checkLoginStatus();

        // 尝试恢复保存的游戏状态，没有则开始新游戏
        if (!loadGameState()) {
            newGame();
        }
    }

    // 保存游戏状态到 localStorage
    function saveGameState() {
        if (gameOver) return; // 游戏结束不保存
        var state = {
            board: board,
            score: score,
            rows: ROWS,
            cols: COLS,
            timestamp: Date.now()
        };
        try {
            localStorage.setItem('2048_game_state', JSON.stringify(state));
        } catch (e) {}
    }

    // 从 localStorage 恢复游戏状态
    function loadGameState() {
        try {
            var saved = localStorage.getItem('2048_game_state');
            if (!saved) return false;

            var state = JSON.parse(saved);
            if (!state.board || !state.rows || !state.cols) return false;

            // 恢复行列数
            ROWS = state.rows;
            COLS = state.cols;
            rowsInput.value = ROWS;
            colsInput.value = COLS;

            // 恢复棋盘和分数
            board = state.board;
            score = state.score || 0;
            gameOver = false;
            gameOverEl.style.display = 'none';

            // 读取最高分
            if (isLoggedIn) {
                loadBestFromServer();
            } else {
                best = 0;
                bestEl.textContent = best;
            }

            // 生成网格背景
            generateGridBg();

            // 重建方块
            tiles = {};
            tilesEl.innerHTML = '';
            tileIdCounter = 0;
            for (var i = 0; i < ROWS; i++) {
                for (var j = 0; j < COLS; j++) {
                    if (board[i][j] !== 0) {
                        var id = ++tileIdCounter;
                        var element = createTileElement(id, board[i][j], i, j);
                        tiles[id] = { value: board[i][j], row: i, col: j, element: element };
                    }
                }
            }

            updateScore();
            render();
            return true;
        } catch (e) {
            return false;
        }
    }

    // 清除保存的游戏状态
    function clearGameState() {
        try {
            localStorage.removeItem('2048_game_state');
        } catch (e) {}
    }

    // 检测登录状态
    function checkLoginStatus() {
        fetch('/account/session.php')
            .then(function (r) { return r.json(); })
            .then(function (data) {
                isLoggedIn = data.loggedin === true;
                if (isLoggedIn) {
                    loginTip.style.display = 'none';
                    // 登录后从服务器加载最高分
                    loadBestFromServer();
                } else {
                    loginTip.style.display = 'block';
                }
            })
            .catch(function () {
                isLoggedIn = false;
                loginTip.style.display = 'block';
            });
    }

    // 从服务器加载最高分
    function loadBestFromServer() {
        var key = '2048_best_' + ROWS + 'x' + COLS;
        fetch('/account/get_score.php?game=2048&key=' + encodeURIComponent(key))
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success && data.score !== undefined) {
                    best = parseInt(data.score, 10) || 0;
                    bestEl.textContent = best;
                }
            })
            .catch(function () {});
    }

    // 保存最高分到服务器
    function saveBestToServer() {
        if (!isLoggedIn) return;
        var key = '2048_best_' + ROWS + 'x' + COLS;
        var formData = new FormData();
        formData.append('game', '2048');
        formData.append('key', key);
        formData.append('score', best.toString());
        fetch('/account/save_score.php', {
            method: 'POST',
            body: formData
        }).catch(function () {});
    }

    // 生成网格背景
    function generateGridBg() {
        gridBgEl.innerHTML = '';
        gridBgEl.style.gridTemplateColumns = 'repeat(' + COLS + ', 1fr)';
        // 不设置 grid-template-rows，让行高由 aspect-ratio 自动决定

        var gap = Math.max(3, Math.min(6, Math.floor(100 / Math.max(ROWS, COLS))));
        gridBgEl.style.gap = gap + 'px';

        for (var i = 0; i < ROWS * COLS; i++) {
            var cell = document.createElement('div');
            cell.className = 'grid-cell';
            gridBgEl.appendChild(cell);
        }
    }

    // 新游戏
    function newGame() {
        // 清除保存的游戏状态
        clearGameState();

        // 读取输入的行列数
        var inputRows = parseInt(rowsInput.value, 10);
        var inputCols = parseInt(colsInput.value, 10);

        // 验证范围
        if (isNaN(inputRows) || inputRows < 3) inputRows = 3;
        if (isNaN(inputCols) || inputCols < 3) inputCols = 3;
        if (inputRows > 20) inputRows = 20;
        if (inputCols > 20) inputCols = 20;

        rowsInput.value = inputRows;
        colsInput.value = inputCols;

        ROWS = inputRows;
        COLS = inputCols;

        // 初始化棋盘
        board = [];
        for (var i = 0; i < ROWS; i++) {
            board[i] = [];
            for (var j = 0; j < COLS; j++) {
                board[i][j] = 0;
            }
        }

        // 清除旧方块
        tiles = {};
        tilesEl.innerHTML = '';
        tileIdCounter = 0;

        score = 0;
        gameOver = false;
        gameOverEl.style.display = 'none';

        // 读取最高分
        if (isLoggedIn) {
            // 登录后从服务器加载
            loadBestFromServer();
        } else {
            // 未登录不保存最高分，每次清零
            best = 0;
            bestEl.textContent = best;
        }

        generateGridBg();
        addRandomTile();
        addRandomTile();
        updateScore();
        render();
    }

    // 创建方块DOM元素
    function createTileElement(id, value, row, col) {
        var tile = document.createElement('div');
        var tileClass = 'tile';
        if (value <= 2048) {
            tileClass += ' tile-' + value;
        } else {
            tileClass += ' tile-super';
        }
        tile.className = tileClass + ' new';
        tile.textContent = value;
        tile.dataset.id = id;

        // 计算位置
        var pos = getTilePosition(row, col);
        tile.style.left = pos.left + 'px';
        tile.style.top = pos.top + 'px';
        tile.style.width = pos.size + 'px';
        tile.style.height = pos.size + 'px';
        tile.style.fontSize = getFontSize(value, pos.size) + 'px';

        tilesEl.appendChild(tile);

        // 移除new类
        setTimeout(function () {
            tile.classList.remove('new');
        }, 200);

        return tile;
    }

    // 获取方块位置和大小
    function getTilePosition(row, col) {
        var boardWidth = tilesEl.offsetWidth;
        var boardHeight = tilesEl.offsetHeight;
        var gap = Math.max(3, Math.min(6, Math.floor(100 / Math.max(ROWS, COLS))));
        var cellWidth = (boardWidth - gap * (COLS - 1)) / COLS;
        var cellHeight = (boardHeight - gap * (ROWS - 1)) / ROWS;
        var size = Math.min(cellWidth, cellHeight);

        return {
            left: col * (cellWidth + gap),
            top: row * (cellHeight + gap),
            size: size,
            cellWidth: cellWidth,
            cellHeight: cellHeight,
            gap: gap
        };
    }

    // 计算字体大小
    function getFontSize(value, cellSize) {
        var digits = value.toString().length;
        var baseSize = cellSize * 0.38;

        if (digits <= 2) return baseSize;
        if (digits === 3) return baseSize * 0.8;
        if (digits === 4) return baseSize * 0.65;
        if (digits === 5) return baseSize * 0.55;
        return baseSize * 0.45;
    }

    // 添加随机方块
    function addRandomTile() {
        var empty = [];
        for (var i = 0; i < ROWS; i++) {
            for (var j = 0; j < COLS; j++) {
                if (board[i][j] === 0) {
                    empty.push({ row: i, col: j });
                }
            }
        }
        if (empty.length === 0) return false;

        var pos = empty[Math.floor(Math.random() * empty.length)];
        var value = Math.random() < 0.9 ? 2 : 4;
        board[pos.row][pos.col] = value;

        var id = ++tileIdCounter;
        var element = createTileElement(id, value, pos.row, pos.col);
        tiles[id] = { value: value, row: pos.row, col: pos.col, element: element };

        return true;
    }

    // 处理键盘
    function handleKeyDown(e) {
        if (gameOver) return;

        var key = e.key;
        var direction = null;

        if (key === 'ArrowUp' || key === 'w' || key === 'W') direction = 'up';
        else if (key === 'ArrowDown' || key === 's' || key === 'S') direction = 'down';
        else if (key === 'ArrowLeft' || key === 'a' || key === 'A') direction = 'left';
        else if (key === 'ArrowRight' || key === 'd' || key === 'D') direction = 'right';

        if (direction) {
            e.preventDefault();
            move(direction);
        }
    }

    // 移动
    function move(direction) {
        if (gameOver) return;

        var oldBoard = JSON.stringify(board);
        var mergedTiles = []; // 记录合并的方块id，用于动画

        // 清除合并标记
        var merged = [];
        for (var i = 0; i < ROWS; i++) {
            merged[i] = [];
            for (var j = 0; j < COLS; j++) {
                merged[i][j] = false;
            }
        }

        if (direction === 'left') {
            for (var row = 0; row < ROWS; row++) {
                var line = [];
                for (var col = 0; col < COLS; col++) {
                    if (board[row][col] !== 0) line.push({ value: board[row][col], row: row, col: col });
                }
                var result = slideAndMerge(line, row, 'left');
                for (var col = 0; col < COLS; col++) {
                    board[row][col] = result[col] ? result[col].value : 0;
                }
            }
        } else if (direction === 'right') {
            for (var row = 0; row < ROWS; row++) {
                var line = [];
                for (var col = COLS - 1; col >= 0; col--) {
                    if (board[row][col] !== 0) line.push({ value: board[row][col], row: row, col: col });
                }
                var result = slideAndMerge(line, row, 'right');
                for (var col = COLS - 1; col >= 0; col--) {
                    board[row][col] = result[COLS - 1 - col] ? result[COLS - 1 - col].value : 0;
                }
            }
        } else if (direction === 'up') {
            for (var col = 0; col < COLS; col++) {
                var line = [];
                for (var row = 0; row < ROWS; row++) {
                    if (board[row][col] !== 0) line.push({ value: board[row][col], row: row, col: col });
                }
                var result = slideAndMerge(line, col, 'up');
                for (var row = 0; row < ROWS; row++) {
                    board[row][col] = result[row] ? result[row].value : 0;
                }
            }
        } else if (direction === 'down') {
            for (var col = 0; col < COLS; col++) {
                var line = [];
                for (var row = ROWS - 1; row >= 0; row--) {
                    if (board[row][col] !== 0) line.push({ value: board[row][col], row: row, col: col });
                }
                var result = slideAndMerge(line, col, 'down');
                for (var row = ROWS - 1; row >= 0; row--) {
                    board[row][col] = result[ROWS - 1 - row] ? result[ROWS - 1 - row].value : 0;
                }
            }
        }

        // 检查是否有变化
        if (oldBoard !== JSON.stringify(board)) {
            // 先更新所有方块位置（动画）
            updateTilePositions();

            // 延迟添加新方块，等移动动画完成
            setTimeout(function () {
                addRandomTile();
                updateScore();
                render();
                // 保存游戏状态
                saveGameState();

                if (!canMove()) {
                    gameOver = true;
                    showGameOver();
                    // 游戏结束，清除保存的状态
                    clearGameState();
                }
            }, 120);
        }
    }

    // 滑动并合并
    function slideAndMerge(line, fixedIndex, direction) {
        var result = [];
        var i = 0;

        while (i < line.length) {
            if (i + 1 < line.length && line[i].value === line[i + 1].value) {
                // 合并
                var newValue = line[i].value * 2;
                score += newValue;
                result.push({ value: newValue, merged: true, from: [line[i], line[i + 1]] });
                i += 2;
            } else {
                result.push({ value: line[i].value, merged: false, from: [line[i]] });
                i++;
            }
        }

        return result;
    }

    // 更新所有方块位置（带动画）
    function updateTilePositions() {
        // 简单实现：重新渲染，CSS transition会处理动画
        // 由于我们用了新的board，需要重新映射方块
        // 这里简化处理：清除所有方块，重新创建
        // 更好的实现应该保留DOM元素并移动位置，但为了简单，重新创建

        // 记录当前所有方块的值和位置
        var newTiles = {};
        tilesEl.innerHTML = '';
        tileIdCounter = 0;

        for (var i = 0; i < ROWS; i++) {
            for (var j = 0; j < COLS; j++) {
                if (board[i][j] !== 0) {
                    var id = ++tileIdCounter;
                    var element = createTileElement(id, board[i][j], i, j);
                    newTiles[id] = { value: board[i][j], row: i, col: j, element: element };
                }
            }
        }

        tiles = newTiles;
    }

    // 检查是否还能移动
    function canMove() {
        // 检查是否有空格
        for (var i = 0; i < ROWS; i++) {
            for (var j = 0; j < COLS; j++) {
                if (board[i][j] === 0) return true;
            }
        }

        // 检查是否有相邻相同数字
        for (var i = 0; i < ROWS; i++) {
            for (var j = 0; j < COLS; j++) {
                var val = board[i][j];
                if (i < ROWS - 1 && board[i + 1][j] === val) return true;
                if (j < COLS - 1 && board[i][j + 1] === val) return true;
            }
        }

        return false;
    }

    // 更新分数
    function updateScore() {
        scoreEl.textContent = score;
        if (score > best) {
            best = score;
            bestEl.textContent = best;
            // 只有登录后才保存最高分到服务器
            if (isLoggedIn) {
                saveBestToServer();
            }
            // 未登录不保存，刷新页面后清零
        }
    }

    // 渲染
    function render() {
        // 方块已经在updateTilePositions和addRandomTile中创建
        // 这里只需要确保位置正确
        var pos = getTilePosition(0, 0);
        for (var id in tiles) {
            if (tiles.hasOwnProperty(id)) {
                var tile = tiles[id];
                var p = getTilePosition(tile.row, tile.col);
                tile.element.style.left = p.left + 'px';
                tile.element.style.top = p.top + 'px';
                tile.element.style.width = p.size + 'px';
                tile.element.style.height = p.size + 'px';
                tile.element.style.fontSize = getFontSize(tile.value, p.size) + 'px';
            }
        }
    }

    // 显示游戏结束
    function showGameOver() {
        finalScoreEl.textContent = score;
        gameOverEl.style.display = 'flex';
    }

    // 窗口大小改变时重新渲染
    window.addEventListener('resize', function () {
        if (!gameOver) render();
    });

    // 启动
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();

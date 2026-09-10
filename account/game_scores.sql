-- 创建游戏分数表
CREATE TABLE IF NOT EXISTS game_scores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    game VARCHAR(50) NOT NULL,
    score_key VARCHAR(100) NOT NULL,
    score BIGINT NOT NULL DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_game_key (user_id, game, score_key),
    INDEX idx_user_game (user_id, game)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

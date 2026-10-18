CREATE TABLE IF NOT EXISTS user_presence (
    session_key CHAR(64) NOT NULL PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    last_seen_at DATETIME NOT NULL,
    KEY idx_presence_user_seen (user_id,last_seen_at),
    KEY idx_presence_cleanup (last_seen_at),
    CONSTRAINT fk_presence_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

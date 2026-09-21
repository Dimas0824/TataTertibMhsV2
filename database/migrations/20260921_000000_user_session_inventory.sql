CREATE TABLE USER_SESSION (
    id_session BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    actor_type VARCHAR(20) NOT NULL,
    actor_id VARCHAR(32) NOT NULL,
    sid CHAR(16) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_session),
    UNIQUE KEY uq_user_session_sid (sid),
    INDEX idx_user_session_actor (actor_type, actor_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

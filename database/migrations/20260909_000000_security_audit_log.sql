CREATE TABLE SECURITY_AUDIT_LOG (
    id_audit BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event VARCHAR(40) NOT NULL,
    actor_type VARCHAR(20) NULL,
    actor_id VARCHAR(32) NULL,
    sid CHAR(16) NULL,
    ip VARCHAR(45) NULL,
    detail VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_audit),
    INDEX idx_event_time (event, created_at),
    INDEX idx_actor (actor_type, actor_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

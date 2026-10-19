-- 사내 전산 관리 시스템 스키마 (MySQL 8.0 / MariaDB 10.6+)
-- 시간 값은 애플리케이션에서 'Y-m-d H:i:s' 문자열로 넣는다 (DB 종류와 무관하게 같은 코드 사용).

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS users (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    username        VARCHAR(40)  NOT NULL UNIQUE,
    password_hash   VARCHAR(255) NOT NULL,
    name            VARCHAR(40)  NOT NULL,
    dept            VARCHAR(40)  NOT NULL DEFAULT '',
    role            VARCHAR(10)  NOT NULL DEFAULT 'staff',   -- admin(전산 담당) / staff(일반 직원)
    failed_count    INT          NOT NULL DEFAULT 0,
    locked_until    DATETIME     NULL,
    created_at      DATETIME     NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS assets (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    asset_no        VARCHAR(20)  NOT NULL UNIQUE,            -- 자산번호 예: PC-2024-013
    category        VARCHAR(20)  NOT NULL,                   -- PC / 노트북 / 서버 / 네트워크 / 프린터 / 기타
    model           VARCHAR(80)  NOT NULL DEFAULT '',
    user_name       VARCHAR(40)  NOT NULL DEFAULT '',
    dept            VARCHAR(40)  NOT NULL DEFAULT '',
    location        VARCHAR(60)  NOT NULL DEFAULT '',
    ip              VARCHAR(45)  NOT NULL DEFAULT '',
    os              VARCHAR(40)  NOT NULL DEFAULT '',
    purchased_on    DATE         NULL,
    warranty_until  DATE         NULL,
    status          VARCHAR(10)  NOT NULL DEFAULT '사용',    -- 사용 / 예비 / 수리 / 폐기
    memo            VARCHAR(255) NOT NULL DEFAULT '',
    updated_at      DATETIME     NOT NULL,
    INDEX idx_assets_dept (dept),
    INDEX idx_assets_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tickets (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    title           VARCHAR(100) NOT NULL,
    category        VARCHAR(20)  NOT NULL,                   -- PC / 네트워크 / 프린터 / ERP·프로그램 / 계정 / 기타
    priority        VARCHAR(10)  NOT NULL DEFAULT '보통',    -- 긴급 / 높음 / 보통 / 낮음
    status          VARCHAR(10)  NOT NULL DEFAULT '접수',    -- 접수 / 처리중 / 완료
    requester_id    INT          NOT NULL,
    asset_id        INT          NULL,
    content         TEXT         NOT NULL,
    assignee_id     INT          NULL,
    resolution      TEXT         NULL,
    created_at      DATETIME     NOT NULL,
    started_at      DATETIME     NULL,
    resolved_at     DATETIME     NULL,
    INDEX idx_tickets_status (status),
    INDEX idx_tickets_created (created_at),
    FOREIGN KEY (requester_id) REFERENCES users(id),
    FOREIGN KEY (assignee_id)  REFERENCES users(id),
    FOREIGN KEY (asset_id)     REFERENCES assets(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS backups (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    target          VARCHAR(60)  NOT NULL,                   -- 백업 대상 예: ERP DB, 파일서버
    result          VARCHAR(10)  NOT NULL,                   -- 성공 / 실패
    size_mb         DECIMAL(12,1) NOT NULL DEFAULT 0,
    file_name       VARCHAR(255) NOT NULL DEFAULT '',
    detail          VARCHAR(255) NOT NULL DEFAULT '',
    source          VARCHAR(10)  NOT NULL DEFAULT '수동',    -- 수동 / 자동(cron)
    checked_by      INT          NULL,
    checked_at      DATETIME     NOT NULL,
    INDEX idx_backups_target (target, checked_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS audit_logs (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT          NULL,
    username        VARCHAR(40)  NOT NULL DEFAULT '',
    action          VARCHAR(30)  NOT NULL,                   -- login / login_fail / create / update / delete / export ...
    target          VARCHAR(40)  NOT NULL DEFAULT '',
    detail          VARCHAR(255) NOT NULL DEFAULT '',
    ip              VARCHAR(45)  NOT NULL DEFAULT '',
    created_at      DATETIME     NOT NULL,
    INDEX idx_audit_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

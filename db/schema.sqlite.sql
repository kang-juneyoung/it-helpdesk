-- 데모·테스트용 SQLite 스키마. 컬럼과 의미는 schema.mysql.sql과 같다.
PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS users (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    username        TEXT    NOT NULL UNIQUE,
    password_hash   TEXT    NOT NULL,
    name            TEXT    NOT NULL,
    dept            TEXT    NOT NULL DEFAULT '',
    role            TEXT    NOT NULL DEFAULT 'staff',
    failed_count    INTEGER NOT NULL DEFAULT 0,
    locked_until    TEXT    NULL,
    created_at      TEXT    NOT NULL
);

CREATE TABLE IF NOT EXISTS assets (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    asset_no        TEXT    NOT NULL UNIQUE,
    category        TEXT    NOT NULL,
    model           TEXT    NOT NULL DEFAULT '',
    user_name       TEXT    NOT NULL DEFAULT '',
    dept            TEXT    NOT NULL DEFAULT '',
    location        TEXT    NOT NULL DEFAULT '',
    ip              TEXT    NOT NULL DEFAULT '',
    os              TEXT    NOT NULL DEFAULT '',
    purchased_on    TEXT    NULL,
    warranty_until  TEXT    NULL,
    status          TEXT    NOT NULL DEFAULT '사용',
    memo            TEXT    NOT NULL DEFAULT '',
    updated_at      TEXT    NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_assets_dept ON assets(dept);
CREATE INDEX IF NOT EXISTS idx_assets_status ON assets(status);

CREATE TABLE IF NOT EXISTS tickets (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    title           TEXT    NOT NULL,
    category        TEXT    NOT NULL,
    priority        TEXT    NOT NULL DEFAULT '보통',
    status          TEXT    NOT NULL DEFAULT '접수',
    requester_id    INTEGER NOT NULL REFERENCES users(id),
    asset_id        INTEGER NULL REFERENCES assets(id) ON DELETE SET NULL,
    content         TEXT    NOT NULL,
    assignee_id     INTEGER NULL REFERENCES users(id),
    resolution      TEXT    NULL,
    created_at      TEXT    NOT NULL,
    started_at      TEXT    NULL,
    resolved_at     TEXT    NULL
);
CREATE INDEX IF NOT EXISTS idx_tickets_status ON tickets(status);
CREATE INDEX IF NOT EXISTS idx_tickets_created ON tickets(created_at);

CREATE TABLE IF NOT EXISTS backups (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    target          TEXT    NOT NULL,
    result          TEXT    NOT NULL,
    size_mb         REAL    NOT NULL DEFAULT 0,
    file_name       TEXT    NOT NULL DEFAULT '',
    detail          TEXT    NOT NULL DEFAULT '',
    source          TEXT    NOT NULL DEFAULT '수동',
    checked_by      INTEGER NULL,
    checked_at      TEXT    NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_backups_target ON backups(target, checked_at);

CREATE TABLE IF NOT EXISTS audit_logs (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id         INTEGER NULL,
    username        TEXT    NOT NULL DEFAULT '',
    action          TEXT    NOT NULL,
    target          TEXT    NOT NULL DEFAULT '',
    detail          TEXT    NOT NULL DEFAULT '',
    ip              TEXT    NOT NULL DEFAULT '',
    created_at      TEXT    NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_audit_created ON audit_logs(created_at);

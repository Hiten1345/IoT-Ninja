<?php
// db.php — SQLite connection singleton + schema initialization

function getDB() {
    static $db = null;
    if ($db !== null) return $db;

    if (!defined('DATA_DIR')) die('FATAL: DATA_DIR not defined before getDB().');

    try {
        $db = new PDO('sqlite:' . DATA_DIR . 'iot_platform.db');
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        // Performance tuning
        $db->exec('PRAGMA journal_mode=WAL');       // concurrent reads while writing
        $db->exec('PRAGMA synchronous=NORMAL');     // fast writes, safe enough
        $db->exec('PRAGMA foreign_keys=ON');
        $db->exec('PRAGMA cache_size=-8000');       // 8 MB page cache

        // ── Users ────────────────────────────────────────────────────────────
        $db->exec("CREATE TABLE IF NOT EXISTS users (
            UID                  TEXT PRIMARY KEY,
            FirstName            TEXT NOT NULL DEFAULT '',
            LastName             TEXT NOT NULL DEFAULT '',
            BornYear             TEXT NOT NULL DEFAULT '',
            Email                TEXT UNIQUE,
            Password             TEXT NOT NULL DEFAULT '',
            Status               TEXT NOT NULL DEFAULT 'active',
            ResetToken           TEXT NOT NULL DEFAULT '',
            TokenExpiry          TEXT NOT NULL DEFAULT '',
            ForcePasswordChange  INTEGER NOT NULL DEFAULT 0
        )");

        // ── Projects ─────────────────────────────────────────────────────────
        $db->exec("CREATE TABLE IF NOT EXISTS projects (
            ProjectID  TEXT PRIMARY KEY,
            UID        TEXT NOT NULL,
            Name       TEXT NOT NULL,
            BoardType  TEXT NOT NULL DEFAULT 'ESP8266',
            APIKey     TEXT UNIQUE NOT NULL,
            CreatedAt  DATETIME DEFAULT (datetime('now','localtime'))
        )");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_projects_uid    ON projects(UID)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_projects_apikey ON projects(APIKey)");

        // ── IoT latest values (one row per project+field) ────────────────────
        $db->exec("CREATE TABLE IF NOT EXISTS iot_latest (
            project_id TEXT NOT NULL,
            field      TEXT NOT NULL COLLATE NOCASE,
            value      TEXT NOT NULL DEFAULT '',
            updated_at DATETIME DEFAULT (datetime('now','localtime')),
            PRIMARY KEY (project_id, field)
        )");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_latest_project ON iot_latest(project_id)");

        // ── IoT history (append-only, one row per write) ─────────────────────
        $db->exec("CREATE TABLE IF NOT EXISTS iot_history (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            project_id  TEXT NOT NULL,
            field       TEXT NOT NULL,
            value       TEXT NOT NULL,
            recorded_at DATETIME DEFAULT (datetime('now','localtime'))
        )");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_history_lookup
                   ON iot_history(project_id, field, recorded_at)");

    } catch (PDOException $e) {
        error_log('SQLite init failed: ' . $e->getMessage());
        die('Database initialisation failed. Check error logs.');
    }

    return $db;
}

<?php
/*
 * Idempotent schema migrations for the CMS tables.
 *
 * setup.php only ever runs once (it refuses to run if the tables already
 * exist), so anything added to the schema after the initial install lives
 * here instead. ensureContentSchema() is called at the top of every admin
 * API request — each statement is a no-op once it has been applied, so it
 * is safe (and cheap) to run every time.
 *
 * partials/content.php deliberately does NOT call this: public pages must
 * not carry migration cost. Deploying a schema change therefore means
 * running these migrations on the server (see the one-off script pattern in
 * the deploy notes) before the content.php that depends on them goes live.
 */

function ensureContentSchema(PDO $pdo): void {
    // ── Stories: [REDACTED] glitch toggle ────────────────────────────────
    if (!columnExists($pdo, 'cms_stories', 'has_redacted')) {
        $pdo->exec("ALTER TABLE cms_stories ADD COLUMN has_redacted TINYINT(1) NOT NULL DEFAULT 0 AFTER desc_es");
    }

    // ── Series ───────────────────────────────────────────────────────────
    // A series is a reading order *within* one project (a project can hold
    // several series; a series never spans projects and never contains a
    // project). series_part is the admin-assigned part number; stories left
    // without one fall in after the numbered ones, oldest upload first.
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS cms_series (
            id INT AUTO_INCREMENT PRIMARY KEY,
            slug VARCHAR(150) NOT NULL UNIQUE,
            project_id INT NOT NULL,
            title_en VARCHAR(200) NOT NULL,
            title_es VARCHAR(200) DEFAULT NULL,
            desc_en TEXT DEFAULT NULL,
            desc_es TEXT DEFAULT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (project_id) REFERENCES cms_projects(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    if (!columnExists($pdo, 'cms_stories', 'series_id')) {
        $pdo->exec("ALTER TABLE cms_stories ADD COLUMN series_id INT DEFAULT NULL AFTER project_id");
        $pdo->exec("ALTER TABLE cms_stories ADD COLUMN series_part INT DEFAULT NULL AFTER series_id");
        $pdo->exec(
            "ALTER TABLE cms_stories ADD CONSTRAINT fk_story_series
             FOREIGN KEY (series_id) REFERENCES cms_series(id) ON DELETE SET NULL"
        );
    }
}

// SHOW COLUMNS won't take a bound parameter on MariaDB with native prepares,
// so the names are inlined — both always come from this file, never a request.
function columnExists(PDO $pdo, string $table, string $column): bool {
    foreach ([$table, $column] as $name) {
        if (!preg_match('/^[a-z_][a-z0-9_]*$/i', $name)) {
            throw new InvalidArgumentException('unsafe identifier');
        }
    }
    return (bool)$pdo->query("SHOW COLUMNS FROM `$table` LIKE '$column'")->fetch();
}

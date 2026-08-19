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
}

function columnExists(PDO $pdo, string $table, string $column): bool {
    $stmt = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE :c");
    $stmt->execute([':c' => $column]);
    return (bool)$stmt->fetch();
}

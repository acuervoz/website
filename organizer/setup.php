<?php
require_once __DIR__ . '/config.php';

$errors = [];
$created = [];

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die('<pre style="color:red">DB connection failed: ' . htmlspecialchars($e->getMessage()) . '</pre>');
}

// Check existing tables
$existing = [];
$rows = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
foreach ($rows as $t) $existing[$t] = true;

$tables = ['projects', 'tasks', 'task_logs', 'device_tokens'];
$alreadyExist = array_filter($tables, fn($t) => isset($existing[$t]));

if ($alreadyExist) {
    die('<pre style="color:orange">⚠ Setup refused: the following tables already exist: '
        . implode(', ', $alreadyExist)
        . "\n\nDelete this file from the server. Tables were NOT modified.</pre>");
}

$sqls = [
    'projects' => "CREATE TABLE projects (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        colour VARCHAR(7) DEFAULT '#FF6777',
        archived TINYINT(1) DEFAULT 0,
        sort_order INT DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'tasks' => "CREATE TABLE tasks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        project_id INT NOT NULL,
        parent_task_id INT DEFAULT NULL,
        title TEXT NOT NULL,
        priority ENUM('ASAP','Soon','Backlog') DEFAULT 'Soon',
        status ENUM('active','completed','archived') DEFAULT 'active',
        sort_order INT DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
        FOREIGN KEY (parent_task_id) REFERENCES tasks(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'task_logs' => "CREATE TABLE task_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        task_id INT NOT NULL,
        action ENUM('completed','updated','reopened') DEFAULT 'completed',
        note TEXT DEFAULT NULL,
        logged_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'device_tokens' => "CREATE TABLE device_tokens (
        id INT AUTO_INCREMENT PRIMARY KEY,
        token_hash VARCHAR(64) NOT NULL UNIQUE,
        device_label VARCHAR(100) DEFAULT 'Unnamed Device',
        last_seen DATETIME DEFAULT CURRENT_TIMESTAMP,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
];

foreach ($sqls as $name => $sql) {
    try {
        $pdo->exec($sql);
        $created[] = $name;
    } catch (PDOException $e) {
        $errors[] = "$name: " . $e->getMessage();
    }
}

?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Organizer Setup</title>
<style>
  body { background:#0a0a0a; color:#f0f0f0; font-family:monospace; padding:40px; }
  pre  { background:#111; border:1px solid #2a2a2a; padding:20px; }
  .ok  { color:#4caf84; }
  .err { color:#FF6777; }
  .warn { color:#f0b429; font-size:1.2em; font-weight:bold; }
</style>
</head>
<body>
<pre>
┌─ ORGANIZER SETUP ───────────────────────────────┐
│                                                  │
<?php if ($errors): ?>
│  <span class="err">✗ Some tables failed to create:</span>                  │
<?php foreach ($errors as $e): ?>
│    <?= htmlspecialchars($e) . "\n" ?>
<?php endforeach; ?>
<?php endif; ?>
<?php if ($created): ?>
│  <span class="ok">✓ Tables created:</span>                             │
<?php foreach ($created as $t): ?>
│    · <?= htmlspecialchars($t) ?>
<?php endforeach; ?>
│                                                  │
│  Setup complete. You may now visit index.php     │
│                                                  │
└──────────────────────────────────────────────────┘

</pre>
<p class="warn">⚠ DELETE setup.php from your server NOW.<br>
It must not remain accessible after first run.</p>
<?php else: ?>
│  Nothing was created.                            │
└──────────────────────────────────────────────────┘
</pre>
<?php endif; ?>
</body>
</html>

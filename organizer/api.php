<?php
require_once __DIR__ . '/config.php';

session_start();

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

// ── Helpers ───────────────────────────────────────────────────────────────────

function jsonOut(array $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

function jsonError(string $msg, int $code = 400): void {
    jsonOut(['error' => $msg], $code);
}

function getDb(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER, DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
    return $pdo;
}

function getTokenFromCookie(): ?string {
    return $_COOKIE[COOKIE_NAME] ?? null;
}

function getDeviceRow(): ?array {
    $raw = getTokenFromCookie();
    if (!$raw) return null;
    $hash = hash('sha256', $raw);
    $pdo  = getDb();
    $stmt = $pdo->prepare("SELECT * FROM device_tokens WHERE token_hash = :h LIMIT 1");
    $stmt->execute([':h' => $hash]);
    return $stmt->fetch() ?: null;
}

function requireAuth(): array {
    $device = getDeviceRow();
    if (!$device) {
        jsonError('Unauthorized', 401);
    }
    // Rolling 30-day renewal
    $expires = time() + (30 * 24 * 60 * 60);
    setcookie(COOKIE_NAME, getTokenFromCookie(), [
        'expires'  => $expires,
        'path'     => '/',
        'domain'   => COOKIE_DOMAIN,
        'secure'   => true,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    // Update last_seen
    $pdo  = getDb();
    $stmt = $pdo->prepare("UPDATE device_tokens SET last_seen = NOW() WHERE id = :id");
    $stmt->execute([':id' => $device['id']]);
    return $device;
}

function requireCsrf(): void {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!$token || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        jsonError('Invalid CSRF token', 403);
    }
}

function post(string $key, $default = null) {
    $data = json_decode(file_get_contents('php://input'), true);
    if (is_array($data) && array_key_exists($key, $data)) return $data[$key];
    return $_POST[$key] ?? $default;
}

function postAll(): array {
    $data = json_decode(file_get_contents('php://input'), true);
    return is_array($data) ? $data : $_POST;
}

// ── Schema migrations (idempotent, run every request) ────────────────────────

function ensureMigrations(PDO $pdo): void {
    // Add description column if not present
    $col = $pdo->query("SHOW COLUMNS FROM tasks LIKE 'description'")->fetch();
    if (!$col) {
        $pdo->exec("ALTER TABLE tasks ADD COLUMN description TEXT DEFAULT NULL");
    }
    // Extend priority ENUM to include 'No Priority' if not already present
    $pri = $pdo->query("SHOW COLUMNS FROM tasks LIKE 'priority'")->fetch();
    if ($pri && strpos($pri['Type'], 'No Priority') === false) {
        $pdo->exec("ALTER TABLE tasks MODIFY COLUMN priority ENUM('ASAP','Soon','Backlog','No Priority') DEFAULT 'Soon'");
    }
}

// ── Routing ───────────────────────────────────────────────────────────────────

$action = $_GET['action'] ?? $_POST['action'] ?? postAll()['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

try {
    ensureMigrations(getDb());

    switch ($action) {

        // ── AUTH ──────────────────────────────────────────────────────────────

        case 'login':
            if ($method !== 'POST') jsonError('Method not allowed', 405);
            $body = postAll();
            $password = $body['password'] ?? '';
            if (!password_verify($password, APP_PASSWORD)) {
                jsonError('Invalid credentials', 401);
            }
            $token = bin2hex(random_bytes(32));
            $hash  = hash('sha256', $token);
            $pdo   = getDb();
            $stmt  = $pdo->prepare(
                "INSERT INTO device_tokens (token_hash, device_label, last_seen, created_at)
                 VALUES (:h, 'Unnamed Device', NOW(), NOW())"
            );
            $stmt->execute([':h' => $hash]);
            $expires = time() + (30 * 24 * 60 * 60);
            setcookie(COOKIE_NAME, $token, [
                'expires'  => $expires,
                'path'     => '/',
                'domain'   => COOKIE_DOMAIN,
                'secure'   => true,
                'httponly' => true,
                'samesite' => 'Strict',
            ]);
            jsonOut(['ok' => true]);

        case 'logout':
            if ($method !== 'POST') jsonError('Method not allowed', 405);
            $raw = getTokenFromCookie();
            if ($raw) {
                $hash = hash('sha256', $raw);
                $pdo  = getDb();
                $stmt = $pdo->prepare("DELETE FROM device_tokens WHERE token_hash = :h");
                $stmt->execute([':h' => $hash]);
            }
            setcookie(COOKIE_NAME, '', [
                'expires'  => time() - 3600,
                'path'     => '/',
                'domain'   => COOKIE_DOMAIN,
                'secure'   => true,
                'httponly' => true,
                'samesite' => 'Strict',
            ]);
            jsonOut(['ok' => true]);

        case 'check_auth':
            $device = getDeviceRow();
            jsonOut(['authed' => (bool)$device]);

        // ── PROJECTS ──────────────────────────────────────────────────────────

        case 'get_projects':
            requireAuth();
            $pdo  = getDb();
            $stmt = $pdo->query(
                "SELECT p.*,
                    COUNT(CASE WHEN t.status='active' THEN 1 END) AS task_count,
                    COUNT(CASE WHEN t.status='active' AND t.priority='ASAP' THEN 1 END) AS asap_count
                 FROM projects p
                 LEFT JOIN tasks t ON t.project_id = p.id AND t.parent_task_id IS NULL
                 WHERE p.archived = 0
                 GROUP BY p.id
                 ORDER BY p.sort_order ASC, p.created_at ASC"
            );
            jsonOut($stmt->fetchAll());

        case 'get_archived_projects':
            requireAuth();
            $pdo  = getDb();
            $stmt = $pdo->query(
                "SELECT p.*,
                    COUNT(CASE WHEN t.status='active' THEN 1 END) AS task_count
                 FROM projects p
                 LEFT JOIN tasks t ON t.project_id = p.id
                 WHERE p.archived = 1
                 GROUP BY p.id
                 ORDER BY p.created_at DESC"
            );
            jsonOut($stmt->fetchAll());

        case 'create_project':
            requireAuth();
            requireCsrf();
            $body   = postAll();
            $name   = trim($body['name'] ?? '');
            $colour = $body['colour'] ?? '#FF6777';
            if (!$name) jsonError('Name required');
            if (!preg_match('/^#[0-9a-fA-F]{6}$/', $colour)) $colour = '#FF6777';
            $pdo  = getDb();
            $stmt = $pdo->prepare(
                "INSERT INTO projects (name, colour, sort_order)
                 VALUES (:n, :c, (SELECT COALESCE(MAX(sort_order),0)+1 FROM projects p2))"
            );
            $stmt->execute([':n' => $name, ':c' => $colour]);
            jsonOut(['ok' => true, 'id' => (int)$pdo->lastInsertId()]);

        case 'update_project':
            requireAuth();
            requireCsrf();
            $body   = postAll();
            $id     = (int)($body['id'] ?? 0);
            $name   = trim($body['name'] ?? '');
            $colour = $body['colour'] ?? '#FF6777';
            if (!$id || !$name) jsonError('id and name required');
            if (!preg_match('/^#[0-9a-fA-F]{6}$/', $colour)) $colour = '#FF6777';
            $pdo  = getDb();
            $stmt = $pdo->prepare("UPDATE projects SET name=:n, colour=:c WHERE id=:id");
            $stmt->execute([':n' => $name, ':c' => $colour, ':id' => $id]);
            jsonOut(['ok' => true]);

        case 'archive_project':
            requireAuth();
            requireCsrf();
            $body = postAll();
            $id   = (int)($body['id'] ?? 0);
            if (!$id) jsonError('id required');
            $pdo  = getDb();
            $stmt = $pdo->prepare("UPDATE projects SET archived = NOT archived WHERE id = :id");
            $stmt->execute([':id' => $id]);
            jsonOut(['ok' => true]);

        case 'delete_project':
            requireAuth();
            requireCsrf();
            $body = postAll();
            $id   = (int)($body['id'] ?? 0);
            if (!$id) jsonError('id required');
            $pdo  = getDb();
            $stmt = $pdo->prepare("DELETE FROM projects WHERE id = :id");
            $stmt->execute([':id' => $id]);
            jsonOut(['ok' => true]);

        case 'reorder_projects':
            requireAuth();
            requireCsrf();
            $body  = postAll();
            $order = $body['order'] ?? [];
            if (!is_array($order)) jsonError('order must be array');
            $pdo  = getDb();
            $stmt = $pdo->prepare("UPDATE projects SET sort_order = :o WHERE id = :id");
            foreach ($order as $i => $id) {
                $stmt->execute([':o' => (int)$i, ':id' => (int)$id]);
            }
            jsonOut(['ok' => true]);

        // ── TASKS ─────────────────────────────────────────────────────────────

        case 'get_tasks':
            requireAuth();
            $projectId = (int)($_GET['project_id'] ?? 0);
            if (!$projectId) jsonError('project_id required');
            $pdo  = getDb();
            // Fetch parent tasks
            $stmt = $pdo->prepare(
                "SELECT * FROM tasks
                 WHERE project_id = :pid AND parent_task_id IS NULL AND status = 'active'
                 ORDER BY sort_order ASC, created_at ASC"
            );
            $stmt->execute([':pid' => $projectId]);
            $parents = $stmt->fetchAll();
            // Fetch subtasks
            $stmt2 = $pdo->prepare(
                "SELECT * FROM tasks
                 WHERE project_id = :pid AND parent_task_id IS NOT NULL AND status = 'active'
                 ORDER BY sort_order ASC, created_at ASC"
            );
            $stmt2->execute([':pid' => $projectId]);
            $subs = $stmt2->fetchAll();
            // Nest subtasks
            $subMap = [];
            foreach ($subs as $s) {
                $subMap[$s['parent_task_id']][] = $s;
            }
            foreach ($parents as &$p) {
                $p['subtasks'] = $subMap[$p['id']] ?? [];
            }
            jsonOut($parents);

        case 'get_priority_tasks':
            requireAuth();
            $pdo  = getDb();
            $stmt = $pdo->query(
                "SELECT t.*, p.name AS project_name, p.colour AS project_colour
                 FROM tasks t
                 JOIN projects p ON p.id = t.project_id
                 WHERE t.status = 'active' AND p.archived = 0
                   AND (t.parent_task_id IS NOT NULL OR t.priority != 'No Priority')
                 ORDER BY t.priority ASC, t.sort_order ASC, t.created_at ASC"
            );
            $rows   = $stmt->fetchAll();
            $result = ['ASAP' => [], 'Soon' => [], 'Backlog' => []];
            $taskMap = [];
            foreach ($rows as $row) {
                $taskMap[$row['id']] = $row;
                $taskMap[$row['id']]['subtasks'] = [];
            }
            foreach ($taskMap as $id => $task) {
                if ($task['parent_task_id']) {
                    if (isset($taskMap[$task['parent_task_id']])) {
                        $taskMap[$task['parent_task_id']]['subtasks'][] = &$taskMap[$id];
                    }
                }
            }
            foreach ($taskMap as $task) {
                if ($task['parent_task_id']) continue; // skip subtasks at top level
                $pri = $task['priority'];
                if (isset($result[$pri])) $result[$pri][] = $task;
            }
            jsonOut($result);

        case 'create_task':
            requireAuth();
            requireCsrf();
            $body         = postAll();
            $projectId    = (int)($body['project_id'] ?? 0);
            $parentTaskId = isset($body['parent_task_id']) ? (int)$body['parent_task_id'] : null;
            $title        = trim($body['title'] ?? '');
            $priority     = $body['priority'] ?? 'Soon';
            $description  = isset($body['description']) ? trim($body['description']) ?: null : null;
            if (!$projectId || !$title) jsonError('project_id and title required');
            if (!in_array($priority, ['ASAP','Soon','Backlog','No Priority'])) $priority = 'Soon';
            $pdo  = getDb();
            $stmt = $pdo->prepare(
                "INSERT INTO tasks (project_id, parent_task_id, title, priority, description, sort_order)
                 VALUES (:pid, :ptid, :title, :pri, :desc,
                    (SELECT COALESCE(MAX(t2.sort_order),0)+1 FROM tasks t2
                     WHERE t2.project_id=:pid2 AND t2.parent_task_id <=> :ptid2))"
            );
            $stmt->execute([
                ':pid'   => $projectId,
                ':ptid'  => $parentTaskId,
                ':title' => $title,
                ':pri'   => $priority,
                ':desc'  => $description,
                ':pid2'  => $projectId,
                ':ptid2' => $parentTaskId,
            ]);
            jsonOut(['ok' => true, 'id' => (int)$pdo->lastInsertId()]);

        case 'update_task':
            requireAuth();
            requireCsrf();
            $body        = postAll();
            $id          = (int)($body['id'] ?? 0);
            $title       = trim($body['title'] ?? '');
            $priority    = $body['priority'] ?? 'Soon';
            $projectId   = isset($body['project_id']) ? (int)$body['project_id'] : null;
            $description = array_key_exists('description', $body) ? (trim($body['description']) ?: null) : false;
            if (!$id) jsonError('id required');
            if (!in_array($priority, ['ASAP','Soon','Backlog','No Priority'])) $priority = 'Soon';
            $pdo = getDb();
            if ($projectId) {
                $descSql = $description !== false ? ', description=:desc' : '';
                $stmt = $pdo->prepare(
                    "UPDATE tasks SET title=:t, priority=:p, project_id=:pid{$descSql} WHERE id=:id"
                );
                $params = [':t'=>$title,':p'=>$priority,':pid'=>$projectId,':id'=>$id];
                if ($description !== false) $params[':desc'] = $description;
                $stmt->execute($params);
            } else {
                $descSql = $description !== false ? ', description=:desc' : '';
                $stmt = $pdo->prepare("UPDATE tasks SET title=:t, priority=:p{$descSql} WHERE id=:id");
                $params = [':t'=>$title,':p'=>$priority,':id'=>$id];
                if ($description !== false) $params[':desc'] = $description;
                $stmt->execute($params);
            }
            // Log update
            $log = $pdo->prepare(
                "INSERT INTO task_logs (task_id, action) VALUES (:tid, 'updated')"
            );
            $log->execute([':tid' => $id]);
            jsonOut(['ok' => true]);

        case 'complete_task':
            requireAuth();
            requireCsrf();
            $body = postAll();
            $id   = (int)($body['id'] ?? 0);
            $note = trim($body['note'] ?? '') ?: null;
            if (!$id) jsonError('id required');
            $pdo  = getDb();
            $stmt = $pdo->prepare("UPDATE tasks SET status='completed' WHERE id=:id");
            $stmt->execute([':id' => $id]);
            $log  = $pdo->prepare(
                "INSERT INTO task_logs (task_id, action, note) VALUES (:tid, 'completed', :note)"
            );
            $log->execute([':tid' => $id, ':note' => $note]);
            jsonOut(['ok' => true]);

        case 'reopen_task':
            requireAuth();
            requireCsrf();
            $body = postAll();
            $id   = (int)($body['id'] ?? 0);
            if (!$id) jsonError('id required');
            $pdo  = getDb();
            $stmt = $pdo->prepare("UPDATE tasks SET status='active' WHERE id=:id");
            $stmt->execute([':id' => $id]);
            $log  = $pdo->prepare(
                "INSERT INTO task_logs (task_id, action) VALUES (:tid, 'reopened')"
            );
            $log->execute([':tid' => $id]);
            jsonOut(['ok' => true]);

        case 'delete_task':
            requireAuth();
            requireCsrf();
            $body = postAll();
            $id   = (int)($body['id'] ?? 0);
            if (!$id) jsonError('id required');
            $pdo  = getDb();
            $stmt = $pdo->prepare("DELETE FROM tasks WHERE id=:id");
            $stmt->execute([':id' => $id]);
            jsonOut(['ok' => true]);

        case 'reorder_tasks':
            requireAuth();
            requireCsrf();
            $body  = postAll();
            $order = $body['order'] ?? [];
            if (!is_array($order)) jsonError('order must be array');
            $pdo  = getDb();
            $stmt = $pdo->prepare("UPDATE tasks SET sort_order=:o WHERE id=:id");
            foreach ($order as $i => $id) {
                $stmt->execute([':o' => (int)$i, ':id' => (int)$id]);
            }
            jsonOut(['ok' => true]);

        // ── TASK LOGS ─────────────────────────────────────────────────────────

        case 'get_task_log':
            requireAuth();
            $taskId = (int)($_GET['task_id'] ?? 0);
            if (!$taskId) jsonError('task_id required');
            $pdo  = getDb();
            $stmt = $pdo->prepare(
                "SELECT * FROM task_logs WHERE task_id=:tid ORDER BY logged_at DESC"
            );
            $stmt->execute([':tid' => $taskId]);
            jsonOut($stmt->fetchAll());

        case 'get_project_tasks_all':
            requireAuth();
            $projectId = (int)($_GET['project_id'] ?? 0);
            if (!$projectId) jsonError('project_id required');
            $pdo  = getDb();
            $stmt = $pdo->prepare(
                "SELECT t.*,
                    (SELECT MAX(tl.logged_at) FROM task_logs tl
                     WHERE tl.task_id = t.id AND tl.action = 'completed') AS completed_at,
                    (SELECT tl.note FROM task_logs tl
                     WHERE tl.task_id = t.id AND tl.action = 'completed'
                     ORDER BY tl.logged_at DESC LIMIT 1) AS completion_note
                 FROM tasks t
                 WHERE t.project_id = :pid AND t.parent_task_id IS NULL
                 ORDER BY t.sort_order ASC, t.created_at ASC"
            );
            $stmt->execute([':pid' => $projectId]);
            $parents = $stmt->fetchAll();
            $stmt2 = $pdo->prepare(
                "SELECT t.*,
                    (SELECT MAX(tl.logged_at) FROM task_logs tl
                     WHERE tl.task_id = t.id AND tl.action = 'completed') AS completed_at
                 FROM tasks t
                 WHERE t.project_id = :pid AND t.parent_task_id IS NOT NULL
                 ORDER BY t.sort_order ASC, t.created_at ASC"
            );
            $stmt2->execute([':pid' => $projectId]);
            $subs = $stmt2->fetchAll();
            $subMap = [];
            foreach ($subs as $s) { $subMap[$s['parent_task_id']][] = $s; }
            foreach ($parents as &$p) { $p['subtasks'] = $subMap[$p['id']] ?? []; }
            jsonOut($parents);

        case 'get_project_completed':
            requireAuth();
            $pid   = (int)($_GET['project_id'] ?? 0);
            $limit = max(1, min((int)($_GET['limit'] ?? 3), 1000));
            if (!$pid) jsonError('project_id required');
            $pdo  = getDb();
            $stmt = $pdo->prepare(
                "SELECT t.*,
                    (SELECT MAX(tl.logged_at) FROM task_logs tl
                     WHERE tl.task_id = t.id AND tl.action = 'completed') AS completed_at,
                    (SELECT tl.note FROM task_logs tl
                     WHERE tl.task_id = t.id AND tl.action = 'completed'
                     ORDER BY tl.logged_at DESC LIMIT 1) AS completion_note
                 FROM tasks t
                 WHERE t.project_id = :pid AND t.status = 'completed'
                 ORDER BY completed_at DESC, t.id DESC
                 LIMIT :lim"
            );
            $stmt->bindValue(':pid', $pid, PDO::PARAM_INT);
            $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
            $stmt->execute();
            jsonOut($stmt->fetchAll());

        case 'get_completed_tasks':
            requireAuth();
            $pdo  = getDb();
            $stmt = $pdo->query(
                "SELECT t.*, p.name AS project_name, p.colour AS project_colour,
                    (SELECT MAX(tl.logged_at) FROM task_logs tl
                     WHERE tl.task_id = t.id AND tl.action = 'completed') AS completed_at,
                    (SELECT tl.note FROM task_logs tl
                     WHERE tl.task_id = t.id AND tl.action = 'completed'
                     ORDER BY tl.logged_at DESC LIMIT 1) AS completion_note
                 FROM tasks t
                 JOIN projects p ON p.id = t.project_id
                 WHERE t.status = 'completed'
                 ORDER BY completed_at DESC, t.id DESC"
            );
            jsonOut($stmt->fetchAll());

        case 'get_recent_activity':
            requireAuth();
            $pdo  = getDb();
            $stmt = $pdo->query(
                "SELECT tl.*, t.title AS task_title, p.name AS project_name, p.colour AS project_colour
                 FROM task_logs tl
                 JOIN tasks t ON t.id = tl.task_id
                 JOIN projects p ON p.id = t.project_id
                 ORDER BY tl.logged_at DESC
                 LIMIT 20"
            );
            jsonOut($stmt->fetchAll());

        // ── DEVICES ───────────────────────────────────────────────────────────

        case 'get_devices':
            $device = requireAuth();
            $pdo    = getDb();
            $stmt   = $pdo->query(
                "SELECT id, device_label, last_seen, created_at FROM device_tokens ORDER BY created_at ASC"
            );
            $rows = $stmt->fetchAll();
            foreach ($rows as &$r) {
                $r['is_current'] = ($r['id'] === $device['id']);
            }
            jsonOut($rows);

        case 'label_device':
            requireAuth();
            requireCsrf();
            $body  = postAll();
            $id    = (int)($body['id'] ?? 0);
            $label = trim($body['label'] ?? '');
            if (!$id || !$label) jsonError('id and label required');
            $pdo  = getDb();
            $stmt = $pdo->prepare("UPDATE device_tokens SET device_label=:l WHERE id=:id");
            $stmt->execute([':l' => substr($label, 0, 100), ':id' => $id]);
            jsonOut(['ok' => true]);

        case 'revoke_device':
            $current = requireAuth();
            requireCsrf();
            $body = postAll();
            $id   = (int)($body['id'] ?? 0);
            if (!$id) jsonError('id required');
            if ($id === (int)$current['id']) jsonError('Cannot revoke current device', 403);
            $pdo  = getDb();
            $stmt = $pdo->prepare("DELETE FROM device_tokens WHERE id=:id");
            $stmt->execute([':id' => $id]);
            jsonOut(['ok' => true]);

        default:
            jsonError('Unknown action', 404);
    }
} catch (PDOException $e) {
    jsonError('Database error', 500);
} catch (Exception $e) {
    jsonError('Server error', 500);
}

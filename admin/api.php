<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/crypto.php';
require_once __DIR__ . '/schema.php';

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

function getSessionRow(): ?array {
    $raw = getTokenFromCookie();
    if (!$raw) return null;
    $hash = hash('sha256', $raw);
    $pdo  = getDb();
    $stmt = $pdo->prepare("SELECT * FROM admin_sessions WHERE token_hash = :h LIMIT 1");
    $stmt->execute([':h' => $hash]);
    return $stmt->fetch() ?: null;
}

function requireAuth(): array {
    $session = getSessionRow();
    if (!$session) jsonError('Unauthorized', 401);
    $expires = time() + (30 * 24 * 60 * 60);
    setcookie(COOKIE_NAME, getTokenFromCookie(), [
        'expires'  => $expires,
        'path'     => '/',
        'domain'   => COOKIE_DOMAIN,
        'secure'   => true,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    $pdo = getDb();
    $pdo->prepare("UPDATE admin_sessions SET last_seen = NOW() WHERE id = :id")
        ->execute([':id' => $session['id']]);
    return $session;
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

function slugify(string $text): string {
    $slug = strtolower(trim($text));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    $slug = trim($slug, '-');
    return $slug !== '' ? $slug : 'untitled';
}

// $reserved: slugs that are free in the table but can't be used anyway —
// a story may not be called "series", since that path segment is where a
// project keeps its series pages (projects/<project>/series/<series>/).
function uniqueSlug(PDO $pdo, string $table, string $base, array $reserved = []): string {
    $slug = $base;
    $i = 2;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM $table WHERE slug = :s");
    while (true) {
        $stmt->execute([':s' => $slug]);
        if ((int)$stmt->fetchColumn() === 0 && !in_array($slug, $reserved, true)) return $slug;
        $slug = $base . '-' . $i;
        $i++;
    }
}

function projectsRoot(): string {
    return dirname(__DIR__) . '/projects';
}

function writeStoryIndexPhp(string $storySlug): string {
    return "<?php\n\$storySlug = '{$storySlug}';\nrequire __DIR__ . '/../../../partials/content.php';\n\$story = \$STORIES[\$storySlug];\ninclude __DIR__ . '/../../../partials/story-shell.php';\n";
}

function writeSeriesIndexPhp(string $seriesSlug): string {
    return "<?php\n\$seriesSlug = '{$seriesSlug}';\nrequire __DIR__ . '/../../../../partials/content.php';\n\$series = \$SERIES[\$seriesSlug];\ninclude __DIR__ . '/../../../../partials/series-shell.php';\n";
}

function seriesDir(string $projectSlug, string $seriesSlug): string {
    return projectsRoot() . '/' . $projectSlug . '/series/' . $seriesSlug;
}

function writeProjectIndexPhp(string $projectSlug): string {
    return "<?php\n\$projectSlug = '{$projectSlug}';\nrequire __DIR__ . '/../../partials/content.php';\n\$project = \$PROJECTS[\$projectSlug];\ninclude __DIR__ . '/../../partials/project-shell.php';\n";
}

// A project that can actually hold stories/series — custom-SPA projects
// (postcords-archive, the-post-within) render their own page and never read
// the story list, so nothing may be filed under them.
function fetchAddableProject(PDO $pdo, int $projectId): array {
    $stmt = $pdo->prepare("SELECT * FROM cms_projects WHERE id = :id");
    $stmt->execute([':id' => $projectId]);
    $project = $stmt->fetch();
    if (!$project) jsonError('Project not found', 404);
    if ((int)$project['is_custom_spa'] === 1) {
        jsonError('This project has its own custom page and does not read from the story list — pick a different project.', 400);
    }
    return $project;
}

// Reading order of a series, matching partials/content.php: parts the admin
// numbered come first in that order, unnumbered ones follow by upload date.
function seriesStoriesInOrder(PDO $pdo, int $seriesId): array {
    $stmt = $pdo->prepare(
        "SELECT id, slug, title_en, title_es, series_part, created_at
         FROM cms_stories WHERE series_id = :sid
         ORDER BY (series_part IS NULL) ASC, series_part ASC, created_at ASC"
    );
    $stmt->execute([':sid' => $seriesId]);
    return $stmt->fetchAll();
}

// A story may only join a series that lives in the same project — series
// never span projects. Returns null for "no series".
function seriesIdForProject(PDO $pdo, $rawSeriesId, int $projectId): ?int {
    $seriesId = (int)$rawSeriesId;
    if (!$seriesId) return null;
    $stmt = $pdo->prepare("SELECT project_id FROM cms_series WHERE id = :id");
    $stmt->execute([':id' => $seriesId]);
    $row = $stmt->fetch();
    if (!$row) jsonError('Series not found', 404);
    if ((int)$row['project_id'] !== $projectId) {
        jsonError('That series belongs to a different project.', 400);
    }
    return $seriesId;
}

// An explicit part number, or null to let the story fall in chronologically.
function normalisePart($rawPart, ?int $seriesId): ?int {
    if ($seriesId === null) return null;
    if ($rawPart === null || $rawPart === '' || (int)$rawPart < 1) return null;
    return (int)$rawPart;
}

function deleteDirRecursive(string $dir): void {
    if (!is_dir($dir)) return;
    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $path = $dir . '/' . $item;
        if (is_dir($path)) deleteDirRecursive($path);
        else unlink($path);
    }
    rmdir($dir);
}

// ── Routing ───────────────────────────────────────────────────────────────────

$action = $_GET['action'] ?? $_POST['action'] ?? postAll()['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

try {
    $pdo = getDb();
    ensureContentSchema($pdo);

    switch ($action) {

        // ── AUTH (key-file challenge/response, no password) ─────────────────

        case 'get_challenge':
            $nonce = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', time() + NONCE_TTL_SECONDS);
            $pdo->prepare("INSERT INTO login_nonces (nonce, expires_at) VALUES (:n, :e)")
                ->execute([':n' => $nonce, ':e' => $expires]);
            jsonOut(['nonce' => $nonce]);

        case 'login':
            if ($method !== 'POST') jsonError('Method not allowed', 405);
            $body = postAll();
            $nonce = $body['nonce'] ?? '';
            $sigB64 = $body['signature'] ?? '';
            if (!$nonce || !$sigB64) jsonError('nonce and signature required');

            $stmt = $pdo->prepare("SELECT * FROM login_nonces WHERE nonce = :n LIMIT 1");
            $stmt->execute([':n' => $nonce]);
            $nonceRow = $stmt->fetch();
            if (!$nonceRow || (int)$nonceRow['used'] === 1 || strtotime($nonceRow['expires_at']) < time()) {
                jsonError('Challenge expired or already used — reload and try again', 401);
            }

            $signature = base64_decode($sigB64, true);
            if ($signature === false || strlen($signature) !== 64) {
                jsonError('Malformed signature', 400);
            }
            $derSig = rawEcdsaSignatureToDer($signature);

            $keys = $pdo->query("SELECT public_key_pem FROM admin_keys")->fetchAll(PDO::FETCH_COLUMN);
            $verified = false;
            foreach ($keys as $pem) {
                $pub = openssl_pkey_get_public($pem);
                if (!$pub) continue;
                if (openssl_verify($nonce, $derSig, $pub, OPENSSL_ALGO_SHA256) === 1) {
                    $verified = true;
                    break;
                }
            }
            if (!$verified) jsonError('Invalid signature', 401);

            $pdo->prepare("UPDATE login_nonces SET used = 1 WHERE id = :id")
                ->execute([':id' => $nonceRow['id']]);

            $token = bin2hex(random_bytes(32));
            $hash  = hash('sha256', $token);
            $pdo->prepare(
                "INSERT INTO admin_sessions (token_hash, device_label, last_seen, created_at)
                 VALUES (:h, 'Unnamed Device', NOW(), NOW())"
            )->execute([':h' => $hash]);

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
                $pdo->prepare("DELETE FROM admin_sessions WHERE token_hash = :h")
                    ->execute([':h' => hash('sha256', $raw)]);
            }
            setcookie(COOKIE_NAME, '', [
                'expires' => time() - 3600, 'path' => '/', 'domain' => COOKIE_DOMAIN,
                'secure' => true, 'httponly' => true, 'samesite' => 'Strict',
            ]);
            jsonOut(['ok' => true]);

        case 'check_auth':
            $session = getSessionRow();
            if ($session && empty($_SESSION['csrf_token'])) {
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            }
            jsonOut(['authed' => (bool)$session, 'csrf_token' => $session ? $_SESSION['csrf_token'] : null]);

        // ── PROJECTS ──────────────────────────────────────────────────────────

        case 'list_projects':
            requireAuth();
            $rows = $pdo->query(
                "SELECT p.*, COUNT(s.id) AS story_count
                 FROM cms_projects p
                 LEFT JOIN cms_stories s ON s.project_id = p.id
                 GROUP BY p.id
                 ORDER BY p.sort_order ASC"
            )->fetchAll();
            jsonOut($rows);

        case 'create_project':
            requireAuth();
            requireCsrf();
            $body = postAll();
            $titleEn = trim($body['title_en'] ?? '');
            if (!$titleEn) jsonError('title_en required');
            $slug = uniqueSlug($pdo, 'cms_projects', slugify($body['slug'] ?? $titleEn));

            $maxSort = (int)$pdo->query("SELECT COALESCE(MAX(sort_order),-1) FROM cms_projects")->fetchColumn();

            $pdo->prepare(
                "INSERT INTO cms_projects
                    (slug, title_en, title_es, type_en, type_es, desc_en, desc_es,
                     noun_singular_en, noun_plural_en, noun_singular_es, noun_plural_es,
                     is_custom_spa, sort_order)
                 VALUES (:slug, :title_en, :title_es, :type_en, :type_es, :desc_en, :desc_es,
                     :sing_en, :plur_en, :sing_es, :plur_es, 0, :sort)"
            )->execute([
                ':slug'     => $slug,
                ':title_en' => $titleEn,
                ':title_es' => $body['title_es'] ?? null ?: null,
                ':type_en'  => $body['type_en'] ?? null ?: null,
                ':type_es'  => $body['type_es'] ?? null ?: null,
                ':desc_en'  => $body['desc_en'] ?? null ?: null,
                ':desc_es'  => $body['desc_es'] ?? null ?: null,
                ':sing_en'  => trim($body['noun_singular_en'] ?? '') ?: 'story',
                ':plur_en'  => trim($body['noun_plural_en'] ?? '') ?: 'stories',
                ':sing_es'  => trim($body['noun_singular_es'] ?? '') ?: null,
                ':plur_es'  => trim($body['noun_plural_es'] ?? '') ?: null,
                ':sort'     => $maxSort + 1,
            ]);

            $dir = projectsRoot() . '/' . $slug;
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            file_put_contents($dir . '/index.php', writeProjectIndexPhp($slug));

            jsonOut(['ok' => true, 'slug' => $slug]);

        // ── SERIES ────────────────────────────────────────────────────────────

        case 'list_series':
            requireAuth();
            $rows = $pdo->query(
                "SELECT se.*, p.slug AS project_slug, p.title_en AS project_title_en,
                        (SELECT COUNT(*) FROM cms_stories s WHERE s.series_id = se.id) AS story_count
                 FROM cms_series se
                 JOIN cms_projects p ON p.id = se.project_id
                 ORDER BY se.sort_order ASC, se.id ASC"
            )->fetchAll();
            jsonOut($rows);

        case 'get_series':
            requireAuth();
            $id = (int)($_GET['id'] ?? 0);
            if (!$id) jsonError('id required');
            $stmt = $pdo->prepare(
                "SELECT se.*, p.slug AS project_slug FROM cms_series se
                 JOIN cms_projects p ON p.id = se.project_id WHERE se.id = :id"
            );
            $stmt->execute([':id' => $id]);
            $series = $stmt->fetch();
            if (!$series) jsonError('Not found', 404);
            $series['stories'] = seriesStoriesInOrder($pdo, $id);
            jsonOut($series);

        case 'create_series':
            requireAuth();
            requireCsrf();
            $body = postAll();
            $projectId = (int)($body['project_id'] ?? 0);
            $titleEn   = trim($body['title_en'] ?? '');
            if (!$projectId || !$titleEn) jsonError('project_id and title_en are required');

            $project = fetchAddableProject($pdo, $projectId);
            $slug = uniqueSlug($pdo, 'cms_series', slugify($body['slug'] ?? $titleEn));
            $maxSort = (int)$pdo->query("SELECT COALESCE(MAX(sort_order),-1) FROM cms_series")->fetchColumn();

            $pdo->prepare(
                "INSERT INTO cms_series (slug, project_id, title_en, title_es, desc_en, desc_es, sort_order)
                 VALUES (:slug, :pid, :title_en, :title_es, :desc_en, :desc_es, :sort)"
            )->execute([
                ':slug'     => $slug,
                ':pid'      => $projectId,
                ':title_en' => $titleEn,
                ':title_es' => trim($body['title_es'] ?? '') ?: null,
                ':desc_en'  => trim($body['desc_en'] ?? '') ?: null,
                ':desc_es'  => trim($body['desc_es'] ?? '') ?: null,
                ':sort'     => $maxSort + 1,
            ]);
            $seriesId = (int)$pdo->lastInsertId();

            $dir = seriesDir($project['slug'], $slug);
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            file_put_contents($dir . '/index.php', writeSeriesIndexPhp($slug));

            jsonOut(['ok' => true, 'id' => $seriesId, 'slug' => $slug]);

        case 'update_series':
            requireAuth();
            requireCsrf();
            $body = postAll();
            $id = (int)($body['id'] ?? 0);
            if (!$id) jsonError('id required');

            $stmt = $pdo->prepare(
                "SELECT se.*, p.slug AS project_slug FROM cms_series se
                 JOIN cms_projects p ON p.id = se.project_id WHERE se.id = :id"
            );
            $stmt->execute([':id' => $id]);
            $series = $stmt->fetch();
            if (!$series) jsonError('Not found', 404);

            // A series never spans projects, so moving one is only allowed
            // while it is still empty — otherwise its parts would sit under a
            // project that no longer lists them.
            $newProjectId   = (int)$series['project_id'];
            $newProjectSlug = $series['project_slug'];
            if (array_key_exists('project_id', $body) && (int)$body['project_id'] !== $newProjectId) {
                $cnt = $pdo->prepare("SELECT COUNT(*) FROM cms_stories WHERE series_id = :id");
                $cnt->execute([':id' => $id]);
                if ((int)$cnt->fetchColumn() > 0) {
                    jsonError('Remove its stories before moving this series to another project.', 400);
                }
                $newProject = fetchAddableProject($pdo, (int)$body['project_id']);
                $oldDir = seriesDir($series['project_slug'], $series['slug']);
                $newDir = seriesDir($newProject['slug'], $series['slug']);
                if (is_dir($newDir)) jsonError('A series with this slug already exists in the target project', 409);
                if (!is_dir(dirname($newDir))) mkdir(dirname($newDir), 0755, true);
                if (is_dir($oldDir)) {
                    rename($oldDir, $newDir);
                } else {
                    mkdir($newDir, 0755, true);
                    file_put_contents($newDir . '/index.php', writeSeriesIndexPhp($series['slug']));
                }
                $newProjectId   = (int)$newProject['id'];
                $newProjectSlug = $newProject['slug'];
            }

            $titleEn = trim($body['title_en'] ?? $series['title_en']);
            if ($titleEn === '') jsonError('title_en required');

            $pdo->prepare(
                "UPDATE cms_series SET
                    project_id = :pid, title_en = :title_en, title_es = :title_es,
                    desc_en = :desc_en, desc_es = :desc_es
                 WHERE id = :id"
            )->execute([
                ':pid'      => $newProjectId,
                ':title_en' => $titleEn,
                ':title_es' => array_key_exists('title_es', $body) ? (trim($body['title_es']) ?: null) : $series['title_es'],
                ':desc_en'  => array_key_exists('desc_en', $body) ? (trim($body['desc_en']) ?: null) : $series['desc_en'],
                ':desc_es'  => array_key_exists('desc_es', $body) ? (trim($body['desc_es']) ?: null) : $series['desc_es'],
                ':id'       => $id,
            ]);

            // The editor sends the member list back in its new reading order:
            // every id in it gets its 1-based part number, and any story that
            // was in the series but is missing from the list drops out of it.
            if (array_key_exists('story_ids', $body) && is_array($body['story_ids'])) {
                $ids = array_values(array_filter(array_unique(array_map('intval', $body['story_ids']))));
                $setPart = $pdo->prepare(
                    "UPDATE cms_stories SET series_id = :sid, series_part = :part
                     WHERE id = :id AND project_id = :pid"
                );
                foreach ($ids as $i => $storyId) {
                    $setPart->execute([':sid' => $id, ':part' => $i + 1, ':id' => $storyId, ':pid' => $newProjectId]);
                }
                $clear = "UPDATE cms_stories SET series_id = NULL, series_part = NULL WHERE series_id = :sid";
                if ($ids) $clear .= " AND id NOT IN (" . implode(',', $ids) . ")";
                $pdo->prepare($clear)->execute([':sid' => $id]);
            }

            jsonOut(['ok' => true, 'project_slug' => $newProjectSlug]);

        case 'delete_series':
            requireAuth();
            requireCsrf();
            $body = postAll();
            $id = (int)($body['id'] ?? 0);
            if (!$id) jsonError('id required');

            $stmt = $pdo->prepare(
                "SELECT se.*, p.slug AS project_slug FROM cms_series se
                 JOIN cms_projects p ON p.id = se.project_id WHERE se.id = :id"
            );
            $stmt->execute([':id' => $id]);
            $series = $stmt->fetch();
            if (!$series) jsonError('Not found', 404);

            $dir = seriesDir($series['project_slug'], $series['slug']);
            deleteDirRecursive($dir);
            // Leave no empty projects/<project>/series/ container behind.
            $parent = dirname($dir);
            if (is_dir($parent) && count(scandir($parent)) === 2) rmdir($parent);
            // Member stories survive — the FK just nulls their series_id —
            // but their now-meaningless part numbers go with the series.
            $pdo->prepare("UPDATE cms_stories SET series_part = NULL WHERE series_id = :id")->execute([':id' => $id]);
            $pdo->prepare("DELETE FROM cms_series WHERE id = :id")->execute([':id' => $id]);

            jsonOut(['ok' => true]);

        // ── STORIES ───────────────────────────────────────────────────────────

        case 'list_stories':
            requireAuth();
            $rows = $pdo->query(
                "SELECT s.*, p.slug AS project_slug, p.title_en AS project_title_en,
                        se.title_en AS series_title_en
                 FROM cms_stories s
                 JOIN cms_projects p ON p.id = s.project_id
                 LEFT JOIN cms_series se ON se.id = s.series_id
                 ORDER BY s.created_at DESC"
            )->fetchAll();
            jsonOut($rows);

        case 'get_story':
            requireAuth();
            $id = (int)($_GET['id'] ?? 0);
            if (!$id) jsonError('id required');
            $stmt = $pdo->prepare(
                "SELECT s.*, p.slug AS project_slug
                 FROM cms_stories s JOIN cms_projects p ON p.id = s.project_id
                 WHERE s.id = :id"
            );
            $stmt->execute([':id' => $id]);
            $story = $stmt->fetch();
            if (!$story) jsonError('Not found', 404);

            $dir = projectsRoot() . '/' . $story['project_slug'] . '/' . $story['slug'];
            $story['body_en'] = is_file("$dir/{$story['slug']}.md") ? file_get_contents("$dir/{$story['slug']}.md") : '';
            $story['body_es'] = is_file("$dir/{$story['slug']}-es.md") ? file_get_contents("$dir/{$story['slug']}-es.md") : '';
            jsonOut($story);

        case 'create_story':
            requireAuth();
            requireCsrf();
            $body = postAll();
            $projectId = (int)($body['project_id'] ?? 0);
            $titleEn   = trim($body['title_en'] ?? '');
            $bodyEn    = $body['body_en'] ?? '';
            if (!$projectId || !$titleEn || trim($bodyEn) === '') {
                jsonError('project_id, title_en and body_en are required');
            }

            $project = fetchAddableProject($pdo, $projectId);
            $seriesId   = seriesIdForProject($pdo, $body['series_id'] ?? null, $projectId);
            $seriesPart = normalisePart($body['series_part'] ?? null, $seriesId);

            $slug = uniqueSlug($pdo, 'cms_stories', slugify($body['slug'] ?? $titleEn), ['series']);
            $titleEs = trim($body['title_es'] ?? '') ?: null;
            $bodyEs  = trim($body['body_es'] ?? '');

            $dir = projectsRoot() . '/' . $project['slug'] . '/' . $slug;
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            file_put_contents("$dir/$slug.md", $bodyEn);
            if ($bodyEs !== '') file_put_contents("$dir/$slug-es.md", $bodyEs);
            file_put_contents("$dir/index.php", writeStoryIndexPhp($slug));

            $isFav = !empty($body['is_favourite']);
            $favOrder = null;
            if ($isFav) {
                $favOrder = 1 + (int)$pdo->query("SELECT COALESCE(MAX(favourite_sort_order),-1) FROM cms_stories")->fetchColumn();
            }

            $pdo->prepare(
                "INSERT INTO cms_stories
                    (slug, project_id, series_id, series_part, title_en, title_es, type_en, type_es,
                     desc_en, desc_es, has_redacted, is_favourite, favourite_sort_order, created_at)
                 VALUES (:slug, :pid, :series_id, :series_part, :title_en, :title_es, :type_en, :type_es,
                     :desc_en, :desc_es, :redacted, :fav, :favOrder, NOW())"
            )->execute([
                ':slug'        => $slug,
                ':pid'         => $projectId,
                ':series_id'   => $seriesId,
                ':series_part' => $seriesPart,
                ':title_en' => $titleEn,
                ':title_es' => $titleEs,
                ':type_en'  => trim($body['type_en'] ?? '') ?: null,
                ':type_es'  => trim($body['type_es'] ?? '') ?: null,
                ':desc_en'  => trim($body['desc_en'] ?? '') ?: null,
                ':desc_es'  => trim($body['desc_es'] ?? '') ?: null,
                ':redacted' => !empty($body['has_redacted']) ? 1 : 0,
                ':fav'      => $isFav ? 1 : 0,
                ':favOrder' => $favOrder,
            ]);

            jsonOut(['ok' => true, 'slug' => $slug, 'project_slug' => $project['slug']]);

        case 'update_story':
            requireAuth();
            requireCsrf();
            $body = postAll();
            $id = (int)($body['id'] ?? 0);
            if (!$id) jsonError('id required');

            $stmt = $pdo->prepare(
                "SELECT s.*, p.slug AS project_slug FROM cms_stories s
                 JOIN cms_projects p ON p.id = s.project_id WHERE s.id = :id"
            );
            $stmt->execute([':id' => $id]);
            $story = $stmt->fetch();
            if (!$story) jsonError('Not found', 404);

            $titleEn = trim($body['title_en'] ?? $story['title_en']);
            $titleEs = array_key_exists('title_es', $body) ? (trim($body['title_es']) ?: null) : $story['title_es'];
            $bodyEn  = $body['body_en'] ?? null;
            $bodyEs  = array_key_exists('body_es', $body) ? trim($body['body_es']) : null;

            // Moving to a different project? Validate + relocate the story's
            // folder on disk before touching its files, so the .md writes
            // below land in the right place.
            $newProjectId = (int)$story['project_id'];
            $newProjectSlug = $story['project_slug'];
            if (array_key_exists('project_id', $body) && (int)$body['project_id'] !== (int)$story['project_id']) {
                $newProject = fetchAddableProject($pdo, (int)$body['project_id']);
                $oldDir = projectsRoot() . '/' . $story['project_slug'] . '/' . $story['slug'];
                $newParentDir = projectsRoot() . '/' . $newProject['slug'];
                if (!is_dir($newParentDir)) mkdir($newParentDir, 0755, true);
                $newDir = $newParentDir . '/' . $story['slug'];
                if (is_dir($newDir)) jsonError('A story with this slug already exists in the target project', 409);
                rename($oldDir, $newDir);
                $newProjectId = (int)$newProject['id'];
                $newProjectSlug = $newProject['slug'];
            }

            // Series membership follows the project: leaving the project also
            // means leaving any series that belonged to the old one.
            $seriesId = array_key_exists('series_id', $body)
                ? seriesIdForProject($pdo, $body['series_id'], $newProjectId)
                : ($newProjectId === (int)$story['project_id'] ? $story['series_id'] : null);
            $seriesId = $seriesId === null ? null : (int)$seriesId;
            $seriesPart = array_key_exists('series_part', $body)
                ? normalisePart($body['series_part'], $seriesId)
                : normalisePart($story['series_part'], $seriesId);

            $dir = projectsRoot() . '/' . $newProjectSlug . '/' . $story['slug'];
            if ($bodyEn !== null) file_put_contents("$dir/{$story['slug']}.md", $bodyEn);
            if ($bodyEs !== null) {
                $esPath = "$dir/{$story['slug']}-es.md";
                if ($bodyEs === '') { if (is_file($esPath)) unlink($esPath); }
                else file_put_contents($esPath, $bodyEs);
            }

            $isFav = array_key_exists('is_favourite', $body) ? !empty($body['is_favourite']) : (bool)$story['is_favourite'];
            $favOrder = $story['favourite_sort_order'];
            if ($isFav && $favOrder === null) {
                $favOrder = 1 + (int)$pdo->query("SELECT COALESCE(MAX(favourite_sort_order),-1) FROM cms_stories")->fetchColumn();
            } elseif (!$isFav) {
                $favOrder = null;
            }

            $pdo->prepare(
                "UPDATE cms_stories SET
                    project_id = :project_id,
                    series_id = :series_id, series_part = :series_part,
                    title_en = :title_en, title_es = :title_es,
                    type_en = :type_en, type_es = :type_es,
                    desc_en = :desc_en, desc_es = :desc_es,
                    has_redacted = :redacted,
                    is_favourite = :fav, favourite_sort_order = :favOrder
                 WHERE id = :id"
            )->execute([
                ':project_id' => $newProjectId,
                ':series_id'   => $seriesId,
                ':series_part' => $seriesPart,
                ':title_en' => $titleEn,
                ':title_es' => $titleEs,
                ':type_en'  => array_key_exists('type_en', $body) ? (trim($body['type_en']) ?: null) : $story['type_en'],
                ':type_es'  => array_key_exists('type_es', $body) ? (trim($body['type_es']) ?: null) : $story['type_es'],
                ':desc_en'  => array_key_exists('desc_en', $body) ? (trim($body['desc_en']) ?: null) : $story['desc_en'],
                ':desc_es'  => array_key_exists('desc_es', $body) ? (trim($body['desc_es']) ?: null) : $story['desc_es'],
                ':redacted' => (array_key_exists('has_redacted', $body) ? !empty($body['has_redacted']) : (bool)$story['has_redacted']) ? 1 : 0,
                ':fav'      => $isFav ? 1 : 0,
                ':favOrder' => $favOrder,
                ':id'       => $id,
            ]);

            jsonOut(['ok' => true]);

        case 'delete_story':
            requireAuth();
            requireCsrf();
            $body = postAll();
            $id = (int)($body['id'] ?? 0);
            if (!$id) jsonError('id required');

            $stmt = $pdo->prepare(
                "SELECT s.*, p.slug AS project_slug FROM cms_stories s
                 JOIN cms_projects p ON p.id = s.project_id WHERE s.id = :id"
            );
            $stmt->execute([':id' => $id]);
            $story = $stmt->fetch();
            if (!$story) jsonError('Not found', 404);

            deleteDirRecursive(projectsRoot() . '/' . $story['project_slug'] . '/' . $story['slug']);
            $pdo->prepare("DELETE FROM cms_stories WHERE id = :id")->execute([':id' => $id]);

            jsonOut(['ok' => true]);

        default:
            jsonError('Unknown action', 404);
    }
} catch (PDOException $e) {
    jsonError('Database error', 500);
} catch (Exception $e) {
    jsonError('Server error', 500);
}

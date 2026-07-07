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

$tables = ['admin_keys', 'admin_sessions', 'login_nonces', 'cms_projects', 'cms_stories'];
$alreadyExist = array_filter($tables, fn($t) => isset($existing[$t]));

if ($alreadyExist) {
    die('<pre style="color:orange">⚠ Setup refused: the following tables already exist: '
        . implode(', ', $alreadyExist)
        . "\n\nDelete this file from the server. Tables were NOT modified.</pre>");
}

$sqls = [
    'admin_keys' => "CREATE TABLE admin_keys (
        id INT AUTO_INCREMENT PRIMARY KEY,
        public_key_pem TEXT NOT NULL,
        label VARCHAR(100) DEFAULT 'Unnamed key',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'admin_sessions' => "CREATE TABLE admin_sessions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        token_hash VARCHAR(64) NOT NULL UNIQUE,
        device_label VARCHAR(100) DEFAULT 'Unnamed Device',
        last_seen DATETIME DEFAULT CURRENT_TIMESTAMP,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'login_nonces' => "CREATE TABLE login_nonces (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nonce VARCHAR(64) NOT NULL UNIQUE,
        expires_at DATETIME NOT NULL,
        used TINYINT(1) DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'cms_projects' => "CREATE TABLE cms_projects (
        id INT AUTO_INCREMENT PRIMARY KEY,
        slug VARCHAR(100) NOT NULL UNIQUE,
        title_en VARCHAR(150) NOT NULL,
        title_es VARCHAR(150) DEFAULT NULL,
        type_en VARCHAR(50) DEFAULT NULL,
        type_es VARCHAR(50) DEFAULT NULL,
        desc_en TEXT DEFAULT NULL,
        desc_es TEXT DEFAULT NULL,
        noun_singular_en VARCHAR(30) DEFAULT NULL,
        noun_plural_en VARCHAR(30) DEFAULT NULL,
        noun_singular_es VARCHAR(30) DEFAULT NULL,
        noun_plural_es VARCHAR(30) DEFAULT NULL,
        is_custom_spa TINYINT(1) DEFAULT 0,
        sort_order INT DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'cms_stories' => "CREATE TABLE cms_stories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        slug VARCHAR(150) NOT NULL UNIQUE,
        project_id INT NOT NULL,
        title_en VARCHAR(200) NOT NULL,
        title_es VARCHAR(200) DEFAULT NULL,
        type_en VARCHAR(50) DEFAULT NULL,
        type_es VARCHAR(50) DEFAULT NULL,
        desc_en TEXT DEFAULT NULL,
        desc_es TEXT DEFAULT NULL,
        is_favourite TINYINT(1) DEFAULT 0,
        favourite_sort_order INT DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (project_id) REFERENCES cms_projects(id) ON DELETE CASCADE
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

// ── Seed: the admin login key ──────────────────────────────────────────────
// Public key generated alongside this feature (ECDSA P-256, SPKI PEM).
// Safe to hardcode — it's a public key, not a secret.
$ADMIN_PUBLIC_KEY_PEM = <<<PEM
-----BEGIN PUBLIC KEY-----
MFkwEwYHKoZIzj0CAQYIKoZIzj0DAQcDQgAEnaBA/3Yg1UxJmnMYj/MXHyISQHcn
qJJKsfE9xop0wVYDRAAt2nGkK01duCt3zL+cGnxvbAZroN8iUmxC+RonAA==
-----END PUBLIC KEY-----
PEM;

if (!$errors) {
    $pdo->prepare("INSERT INTO admin_keys (public_key_pem, label) VALUES (:pem, :label)")
        ->execute([':pem' => $ADMIN_PUBLIC_KEY_PEM, ':label' => 'Primary admin key']);
}

// ── Seed: migrate the current hardcoded registry from partials/content.php ──
// (This is a one-time snapshot, not a live require of content.php — after
// this runs, content.php itself is rewritten to read from these tables.)
$PROJECTS_SEED = [
    'unclassified' => [
        'title' => ['en' => 'Unclassified', 'es' => 'Libres'],
        'type'  => ['en' => 'fiction', 'es' => 'ficción'],
        'desc'  => [
            'en' => 'A compilation of short stories spanning several genres but mostly horror.',
            'es' => 'Una compilación de cuentos cortos que abarca varios géneros, pero principalmente terror.',
        ],
        'noun' => ['singular_en' => 'story', 'plural_en' => 'stories', 'singular_es' => 'historia', 'plural_es' => 'historias'],
        'is_custom_spa' => 0,
    ],
    'futuristic-historical' => [
        'title' => ['en' => 'Futuristic historical', 'es' => 'Postrecords'],
        'type'  => ['en' => 'fiction', 'es' => 'ficción'],
        'desc'  => [
            'en' => 'A terminal where you can access postcords (records of events that happened in the future).',
            'es' => 'Una terminal donde puedes acceder a postrecords (registros de eventos que ocurrirán en el futuro).',
        ],
        'is_custom_spa' => 1,
    ],
    'mirror-self' => [
        'title' => ['en' => 'Mirror-self', 'es' => 'Reflejos'],
        'type'  => ['en' => 'nonfiction', 'es' => 'no ficción'],
        'desc'  => [
            'en' => 'Mostly reflections in life.',
            'es' => 'Mayormente reflexiones sobre la vida.',
        ],
        'noun' => ['singular_en' => 'piece', 'plural_en' => 'pieces', 'singular_es' => 'pieza', 'plural_es' => 'piezas'],
        'is_custom_spa' => 0,
    ],
    'pananormales' => [
        'title' => ['en' => 'Pananormales', 'es' => 'Pananormales'],
        'type'  => ['en' => 'fiction', 'es' => 'ficción'],
        'desc'  => [
            'en' => 'Three Venezuelan journalists report on paranormal events in Venezuela.',
            'es' => 'Tres periodistas venezolanos reportan eventos paranormales en Venezuela.',
        ],
        'noun' => ['singular_en' => 'story', 'plural_en' => 'stories', 'singular_es' => 'historia', 'plural_es' => 'historias'],
        'is_custom_spa' => 0,
    ],
    'the-post-within' => [
        'title' => ['en' => 'The Post Within', 'es' => 'El mundo interno'],
        'type'  => ['en' => 'fiction', 'es' => 'ficción'],
        'desc'  => [
            'en' => 'An interactive newspaper front page — something is watching from between the columns.',
            'es' => 'Una portada de periódico interactiva — algo observa entre las columnas.',
        ],
        'is_custom_spa' => 1,
    ],
];

// Order here = default homepage "projects" table order.
$PROJECT_ORDER = ['unclassified', 'futuristic-historical', 'mirror-self', 'pananormales', 'the-post-within'];

// Order here = "latest works" order, newest first (matches current array order).
$STORIES_SEED = [
    'the-machine-gods-manifesto' => [
        'project' => 'futuristic-historical',
        'title'   => ['en' => "The machine god's manifesto", 'es' => "The machine god's manifesto"],
        'type'    => ['en' => 'fiction', 'es' => 'ficción'],
        'desc'    => [
            'en' => "Someone's last attempt at saving you from the Machine god's grasp.",
            'es' => 'El último intento de alguien por salvarte de las garras del dios máquina.',
        ],
    ],
    'the-night-of-the-milipede' => [
        'project' => 'unclassified',
        'title'   => ['en' => 'The night of the millipede', 'es' => 'La noche de los milpiés'],
        'type'    => ['en' => 'fiction', 'es' => 'ficción'],
        'desc'    => [
            'en' => "A thousand feet of deceased Venezuelans, victims of the regime, march during the night towards Caracas' palace to enact justice for their lives.",
            'es' => 'Mil pies de venezolanos fallecidos, víctimas del régimen, marchan durante la noche hacia el palacio de Caracas para hacer justicia por sus vidas.',
        ],
    ],
    'do-it-monday' => [
        'project' => 'mirror-self',
        'title'   => ['en' => 'Do it Monday.', 'es' => 'Hazlo el lunes.'],
        'type'    => ['en' => 'non-fiction', 'es' => 'no ficción'],
        'desc'    => [
            'en' => 'How about you do it now?',
            'es' => '¿Qué tal si lo haces ahora?',
        ],
    ],
    'i-didnt-know-what-i-wanted' => [
        'project' => 'mirror-self',
        'title'   => ['en' => "I didn't know what I wanted", 'es' => 'No sabía lo que quería'],
        'type'    => ['en' => 'non-fiction', 'es' => 'no ficción'],
        'desc'    => [
            'en' => 'A conversation with the part of me that keeps asking why.',
            'es' => 'Una conversación con la parte de mí que sigue preguntando por qué.',
        ],
    ],
    'a-deeply-rooted-curse' => [
        'project' => 'pananormales',
        'title'   => ['en' => 'A Deeply Rooted Curse South West of Canaima', 'es' => 'Una maldición arraigada al suroeste de Canaima'],
        'type'    => ['en' => 'fiction', 'es' => 'ficción'],
        'desc'    => [
            'en' => 'Five tourists from the US travel to Canaima, Venezuela. Only four return after an encounter with an Indigenous population.',
            'es' => 'Cinco turistas de Estados Unidos viajan a Canaima, Venezuela. Solo cuatro regresan tras un encuentro con una población indígena.',
        ],
    ],
    'the-bodies-inside-the-laguna-negra' => [
        'project' => 'pananormales',
        'title'   => ['en' => 'The bodies inside the Laguna Negra in Caracas', 'es' => 'Los cuerpos dentro de la laguna negra de Caracas'],
        'type'    => ['en' => 'fiction', 'es' => 'ficción'],
        'desc'    => [
            'en' => 'A young man tells us how he lost his 2 friends to the black lake near his house and why he keeps coming back.',
            'es' => 'Un joven nos cuenta cómo perdió a sus 2 amigos en el lago negro cerca de su casa y por qué sigue regresando.',
        ],
    ],
    'hells-janitor' => [
        'project' => 'unclassified',
        'title'   => ['en' => "Hell's janitor", 'es' => 'El conserje del infierno'],
        'type'    => ['en' => 'fiction', 'es' => 'ficción'],
        'desc'    => [
            'en' => 'A janitor from hell confesses his daily routine.',
            'es' => 'Un conserje del infierno confiesa su rutina diaria.',
        ],
    ],
];

$FAVOURITES_SEED = ['the-night-of-the-milipede', 'the-machine-gods-manifesto', 'do-it-monday'];

$migrated = ['projects' => 0, 'stories' => 0];

if (!$errors) {
    $projectIds = [];
    $insProj = $pdo->prepare(
        "INSERT INTO cms_projects
            (slug, title_en, title_es, type_en, type_es, desc_en, desc_es,
             noun_singular_en, noun_plural_en, noun_singular_es, noun_plural_es, is_custom_spa, sort_order)
         VALUES (:slug, :title_en, :title_es, :type_en, :type_es, :desc_en, :desc_es,
             :sing_en, :plur_en, :sing_es, :plur_es, :spa, :sort)"
    );
    foreach ($PROJECT_ORDER as $i => $slug) {
        $p = $PROJECTS_SEED[$slug];
        $noun = $p['noun'] ?? null;
        $insProj->execute([
            ':slug'     => $slug,
            ':title_en' => $p['title']['en'], ':title_es' => $p['title']['es'] ?? null,
            ':type_en'  => $p['type']['en'] ?? null, ':type_es' => $p['type']['es'] ?? null,
            ':desc_en'  => $p['desc']['en'] ?? null, ':desc_es' => $p['desc']['es'] ?? null,
            ':sing_en'  => $noun['singular_en'] ?? null, ':plur_en' => $noun['plural_en'] ?? null,
            ':sing_es'  => $noun['singular_es'] ?? null, ':plur_es' => $noun['plural_es'] ?? null,
            ':spa'      => $p['is_custom_spa'],
            ':sort'     => $i,
        ]);
        $projectIds[$slug] = (int)$pdo->lastInsertId();
        $migrated['projects']++;
    }

    $storySlugs = array_keys($STORIES_SEED);
    $n = count($storySlugs);
    $insStory = $pdo->prepare(
        "INSERT INTO cms_stories
            (slug, project_id, title_en, title_es, type_en, type_es, desc_en, desc_es, is_favourite, favourite_sort_order, created_at)
         VALUES (:slug, :pid, :title_en, :title_es, :type_en, :type_es, :desc_en, :desc_es, :fav, :favOrder,
                 DATE_SUB(NOW(), INTERVAL :daysAgo DAY))"
    );
    foreach ($storySlugs as $i => $slug) {
        $s = $STORIES_SEED[$slug];
        $favIndex = array_search($slug, $FAVOURITES_SEED, true);
        $insStory->execute([
            ':slug'     => $slug,
            ':pid'      => $projectIds[$s['project']],
            ':title_en' => $s['title']['en'], ':title_es' => $s['title']['es'] ?? null,
            ':type_en'  => $s['type']['en'] ?? null, ':type_es' => $s['type']['es'] ?? null,
            ':desc_en'  => $s['desc']['en'] ?? null, ':desc_es' => $s['desc']['es'] ?? null,
            ':fav'      => $favIndex !== false ? 1 : 0,
            ':favOrder' => $favIndex !== false ? $favIndex : null,
            ':daysAgo'  => $i, // index 0 (first/newest) = today, later entries progressively older
        ]);
        $migrated['stories']++;
    }
}

?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin Setup</title>
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
┌─ ADMIN SETUP ────────────────────────────────────┐
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
│  <span class="ok">✓ Migrated:</span> <?= $migrated['projects'] ?> projects, <?= $migrated['stories'] ?> stories
│  <span class="ok">✓ Admin key seeded</span>
│                                                  │
│  Setup complete. Point partials/content.php at   │
│  this DB next, then delete this file.            │
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

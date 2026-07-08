<?php
/*
 * Central content registry + bilingual support.
 *
 * $PROJECTS / $STORIES / $FAVOURITES are read from the admin CMS's database
 * (see admin/) rather than hardcoded here — this file just reshapes the flat
 * DB rows back into the ['en' => ..., 'es' => ...] nested arrays the rest of
 * the site expects, so index.php / project-shell.php / story-shell.php never
 * had to change. Adding/editing stories now happens through the admin/
 * portal instead of hand-editing this file.
 *
 * A story's 'langs' is derived from whether its *_es columns are non-NULL —
 * an untranslated story just doesn't show up on Spanish listings. The story
 * body text itself still lives on disk as <slug>.md / <slug>-es.md, fetched
 * and rendered client-side by story-shell.php exactly as before.
 *
 * Since content now comes from a DB that the admin/ portal writes to at any
 * time, every page that requires this file must never be served from
 * Siteground's Dynamic Cache — otherwise edits appear not to take effect
 * until the cache happens to expire. Siteground (for non-WordPress sites)
 * honors a plain Cache-Control header for this: see
 * https://www.siteground.com/kb/siteground-dynamic-caching-configuration/
 */

header('Cache-Control: no-cache');

$lang = preg_match('#^/es(/|$)#', $_SERVER['REQUEST_URI']) ? 'es' : 'en';

// Localized field lookup with English fallback, e.g. t($story['title'], $lang).
function t($field, $lang) {
  return $field[$lang] ?? $field['en'];
}

function project_href($projectSlug, $lang) {
  return ($lang === 'es' ? '/es' : '') . '/projects/' . $projectSlug . '/';
}

function story_href($storySlug, $lang) {
  global $STORIES;
  return ($lang === 'es' ? '/es' : '') . '/projects/' . $STORIES[$storySlug]['project'] . '/' . $storySlug . '/';
}

// Same page, other language — used by the nav's language switcher.
function lang_switch_href($lang) {
  $uri = $_SERVER['REQUEST_URI'];
  if ($lang === 'es') {
    $stripped = preg_replace('#^/es(?=/|$)#', '', $uri, 1);
    return $stripped === '' ? '/' : $stripped;
  }
  return '/es' . $uri;
}

// Stories available in a given language, in registry order.
function stories_for_lang($lang) {
  global $STORIES;
  $out = array();
  foreach ($STORIES as $slug => $story) {
    if (in_array($lang, $story['langs'])) {
      $out[$slug] = $story;
    }
  }
  return $out;
}

// A language's own name, written in $displayLang — e.g. lang_name('es', 'en') => 'Spanish'.
function lang_name($code, $displayLang) {
  $names = array(
    'en' => array('en' => 'English', 'es' => 'inglés'),
    'es' => array('en' => 'Spanish', 'es' => 'español'),
  );
  return $names[$code][$displayLang];
}

// "Not available in this language" notice — used wherever a piece of content
// (a story, a project) hasn't been translated into the current $lang yet.
// $availableLang is the language the content DOES exist in (its href points
// there). The notice itself is written in $lang, since that's what the
// visitor is currently reading the rest of the page in.
function lang_notice_html($lang, $availableLang, $availableHref) {
  $missingName   = lang_name($lang, $lang);
  $availableName = lang_name($availableLang, $lang);
  if ($lang === 'es') {
    $text = 'Página no disponible en ' . $missingName . '. '
      . '<a href="' . htmlspecialchars($availableHref) . '">Haz clic aquí</a> '
      . 'para leer la versión en ' . $availableName . '.';
  } else {
    $text = 'Page not available in ' . $missingName . ' language. '
      . '<a href="' . htmlspecialchars($availableHref) . '">Click here</a> '
      . 'to read the ' . $availableName . ' version.';
  }
  return '<p class="lang-notice">' . $text . '</p>';
}

function contentDb(): PDO {
  static $pdo = null;
  if ($pdo) return $pdo;
  require_once __DIR__ . '/../admin/config.php';
  $pdo = new PDO(
    'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
    DB_USER, DB_PASS,
    [
      PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
  );
  return $pdo;
}

// Optional field: only include the key at all if the English value is set,
// matching how the original hardcoded arrays simply omitted e.g. 'count' for
// projects that don't use it.
function bilingualField(array $row, string $enCol, string $esCol): ?array {
  if ($row[$enCol] === null) return null;
  $out = ['en' => $row[$enCol]];
  if ($row[$esCol] !== null) $out['es'] = $row[$esCol];
  return $out;
}

$pdo = contentDb();

// Stories first — project "count" labels need each project's live story
// count and don't just repeat stale stored text (a project's noun changes
// between singular/plural as stories are added/removed, e.g. "1 piece" ->
// "2 pieces", not just a number swapped into fixed text).
$projectSlugById = array();
$stmt = $pdo->query("SELECT id, slug FROM cms_projects");
foreach ($stmt->fetchAll() as $row) $projectSlugById[$row['id']] = $row['slug'];

// Order here is also the default "latest works" order (newest first).
$STORIES = array();
$favouritesOrdered = array();
$stmt = $pdo->query("SELECT * FROM cms_stories ORDER BY created_at DESC");
foreach ($stmt->fetchAll() as $row) {
  $langs = array('en');
  if ($row['title_es'] !== null) $langs[] = 'es';
  $STORIES[$row['slug']] = array(
    'project' => $projectSlugById[$row['project_id']],
    'title'   => bilingualField($row, 'title_en', 'title_es'),
    'type'    => bilingualField($row, 'type_en', 'type_es'),
    'desc'    => bilingualField($row, 'desc_en', 'desc_es'),
    'langs'   => $langs,
  );
  if ($row['is_favourite']) {
    $favouritesOrdered[(int)$row['favourite_sort_order']] = $row['slug'];
  }
}
ksort($favouritesOrdered);
$FAVOURITES = array_values($favouritesOrdered);

$storyCountByProject = array_count_values(array_column($STORIES, 'project'));

$PROJECTS = array();
$stmt = $pdo->query("SELECT * FROM cms_projects ORDER BY sort_order ASC");
foreach ($stmt->fetchAll() as $row) {
  $entry = array(
    'title' => bilingualField($row, 'title_en', 'title_es'),
    'type'  => bilingualField($row, 'type_en', 'type_es'),
    'desc'  => bilingualField($row, 'desc_en', 'desc_es'),
  );
  if ($row['noun_plural_en'] !== null) {
    $n = $storyCountByProject[$row['slug']] ?? 0;
    $nounEn = ($n === 1 && $row['noun_singular_en'] !== null) ? $row['noun_singular_en'] : $row['noun_plural_en'];
    $count = array('en' => "$n $nounEn");
    if ($row['noun_plural_es'] !== null) {
      $nounEs = ($n === 1 && $row['noun_singular_es'] !== null) ? $row['noun_singular_es'] : $row['noun_plural_es'];
      $count['es'] = "$n $nounEs";
    }
    $entry['count'] = $count;
  }
  $PROJECTS[$row['slug']] = $entry;
}

// Site-wide UI strings.
$UI = array(
  'en' => array(
    'nav_home' => 'home', 'nav_projects' => 'projects', 'nav_random' => 'random', 'lang_switch' => 'ES',
    'sec_intro' => '// intro', 'sec_favourites' => '// my favourites',
    'sec_projects' => '// projects', 'sec_latest' => '// latest works',
    'sec_stories' => '// stories', 'sec_random' => '// random',
    'col_title' => 'title', 'col_type' => 'type', 'col_project' => 'project', 'col_desc' => 'description',
    'back_projects' => 'projects',
    'tagline_home' => 'storyteller &nbsp;&middot;&nbsp; writer &nbsp;&middot;&nbsp; web-raised',
    'tagline_projects' => 'acuervoz.com &nbsp;&middot;&nbsp; writer. builder.',
    'intro_p1' => "Welcome, wherever you're coming from. This is my archive of stories both fiction and non-fiction, projects and other small creativity outputs that my <s>computer</s> brain needs to release.",
    'intro_p2' => 'Doing it for the love of the art, hope you enjoy your stay!',
    'footer_main' => 'made by hand &nbsp;&middot;&nbsp; no trackers &nbsp;&middot;&nbsp; no cookies &nbsp;&middot;&nbsp; acuervoz.com',
    'footer_home' => 'made by hand &nbsp;&middot;&nbsp; no trackers &nbsp;&middot;&nbsp; no cookies &nbsp;&middot;&nbsp; no control',
    'footer_browser' => 'best viewed in any browser',
    'loading' => 'loading',
    'noscript_required' => 'JavaScript is required to render this page.',
    'read_raw' => 'Read the raw markdown file instead.',
    'could_not_load' => 'Could not load content.',
    'read_raw_file' => 'Read the raw file.',
    'random_picking' => 'picking something for you',
    'random_taking' => 'taking you somewhere...',
    'random_noscript' => 'javascript is off — here are all the pieces:',
    'random_full_list' => 'go to the full list &rarr;',
  ),
  'es' => array(
    'nav_home' => 'inicio', 'nav_projects' => 'proyectos', 'nav_random' => 'aleatorio', 'lang_switch' => 'EN',
    'sec_intro' => '// introducción', 'sec_favourites' => '// mis favoritos',
    'sec_projects' => '// proyectos', 'sec_latest' => '// últimos trabajos',
    'sec_stories' => '// historias', 'sec_random' => '// aleatorio',
    'col_title' => 'título', 'col_type' => 'tipo', 'col_project' => 'proyecto', 'col_desc' => 'descripción',
    'back_projects' => 'proyectos',
    'tagline_home' => 'narrador &nbsp;&middot;&nbsp; escritor &nbsp;&middot;&nbsp; criado en la web',
    'tagline_projects' => 'acuervoz.com &nbsp;&middot;&nbsp; escritor. constructor.',
    'intro_p1' => 'Bienvenido, vengas de donde vengas. Este es mi archivo de historias, tanto ficción como no ficción, proyectos y otras pequeñas salidas creativas que mi <s>computadora</s> cerebro necesita liberar.',
    'intro_p2' => '¡Lo hago por amor al arte, espero que disfrutes tu estadía!',
    'footer_main' => 'hecho a mano &nbsp;&middot;&nbsp; sin rastreadores &nbsp;&middot;&nbsp; sin cookies &nbsp;&middot;&nbsp; acuervoz.com',
    'footer_home' => 'hecho a mano &nbsp;&middot;&nbsp; sin rastreadores &nbsp;&middot;&nbsp; sin cookies &nbsp;&middot;&nbsp; sin control',
    'footer_browser' => 'se ve mejor en cualquier navegador',
    'loading' => 'cargando',
    'noscript_required' => 'Se requiere JavaScript para mostrar esta página.',
    'read_raw' => 'Lee el archivo markdown sin procesar.',
    'could_not_load' => 'No se pudo cargar el contenido.',
    'read_raw_file' => 'Lee el archivo sin procesar.',
    'random_picking' => 'eligiendo algo para ti',
    'random_taking' => 'llevándote a algún lugar...',
    'random_noscript' => 'JavaScript está desactivado — aquí están todas las piezas:',
    'random_full_list' => 'ir a la lista completa &rarr;',
  ),
);

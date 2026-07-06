<?php
/*
 * Central content registry + bilingual support.
 *
 * Every project/story has title/type/desc as ['en' => ..., 'es' => ...].
 * A story's 'langs' lists which languages it has a full .md for — Spanish
 * listings filter on this, so an untranslated story just doesn't show up
 * there. Projects don't need 'langs': all 5 have Spanish metadata, even the
 * two custom SPA projects (futuristic-historical, the-post-within) whose own
 * page hasn't been translated yet — their listing entries still work in
 * Spanish, visiting the project itself just lands you back in English.
 *
 * To add a story: add one entry to $STORIES, write <slug>.md (and
 * <slug>-es.md once translated, adding 'es' to its langs), and create
 * projects/<project>/<slug>/index.php per the pattern in the existing ones.
 */

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

$PROJECTS = array(
  'unclassified' => array(
    'title' => array('en' => 'Unclassified', 'es' => 'Libres'),
    'type'  => array('en' => 'fiction', 'es' => 'ficción'),
    'desc'  => array(
      'en' => 'A compilation of short stories spanning several genres but mostly horror.',
      'es' => 'Una compilación de cuentos cortos que abarca varios géneros, pero principalmente terror.',
    ),
    'count' => array('en' => '2 stories', 'es' => '2 historias'),
  ),
  'futuristic-historical' => array(
    'title' => array('en' => 'Futuristic historical', 'es' => 'Postrecords'),
    'type'  => array('en' => 'fiction', 'es' => 'ficción'),
    'desc'  => array(
      'en' => 'A terminal where you can access postcords (records of events that happened in the future).',
      'es' => 'Una terminal donde puedes acceder a postrecords (registros de eventos que ocurrirán en el futuro).',
    ),
  ),
  'mirror-self' => array(
    'title' => array('en' => 'Mirror-self', 'es' => 'Reflejos'),
    'type'  => array('en' => 'nonfiction', 'es' => 'no ficción'),
    'desc'  => array(
      'en' => 'Mostly reflections in life.',
      'es' => 'Mayormente reflexiones sobre la vida.',
    ),
    'count' => array('en' => '1 piece', 'es' => '1 pieza'),
  ),
  'pananormales' => array(
    'title' => array('en' => 'Pananormales', 'es' => 'Pananormales'),
    'type'  => array('en' => 'fiction', 'es' => 'ficción'),
    'desc'  => array(
      'en' => 'Three Venezuelan journalists report on paranormal events in Venezuela.',
      'es' => 'Tres periodistas venezolanos reportan eventos paranormales en Venezuela.',
    ),
    'count' => array('en' => '2 stories', 'es' => '2 historias'),
  ),
  'the-post-within' => array(
    'title' => array('en' => 'The Post Within', 'es' => 'El mundo interno'),
    'type'  => array('en' => 'fiction', 'es' => 'ficción'),
    'desc'  => array(
      'en' => 'An interactive newspaper front page — something is watching from between the columns.',
      'es' => 'Una portada de periódico interactiva — algo observa entre las columnas.',
    ),
  ),
);

// Order here is also the default "latest works" order (newest first).
$STORIES = array(
  'the-machine-gods-manifesto' => array(
    'project' => 'futuristic-historical',
    'title'   => array('en' => "The machine god's manifesto", 'es' => "The machine god's manifesto"),
    'type'    => array('en' => 'fiction', 'es' => 'ficción'),
    'desc'    => array(
      'en' => "Someone's last attempt at saving you from the Machine god's grasp.",
      'es' => 'El último intento de alguien por salvarte de las garras del dios máquina.',
    ),
    'langs' => array('en', 'es'),
  ),
  'the-night-of-the-milipede' => array(
    'project' => 'unclassified',
    'title'   => array('en' => 'The night of the millipede', 'es' => 'La noche de los milpiés'),
    'type'    => array('en' => 'fiction', 'es' => 'ficción'),
    'desc'    => array(
      'en' => "A thousand feet of deceased Venezuelans, victims of the regime, march during the night towards Caracas' palace to enact justice for their lives.",
      'es' => 'Mil pies de venezolanos fallecidos, víctimas del régimen, marchan durante la noche hacia el palacio de Caracas para hacer justicia por sus vidas.',
    ),
    'langs' => array('en', 'es'),
  ),
  'do-it-monday' => array(
    'project' => 'mirror-self',
    'title'   => array('en' => 'Do it Monday.', 'es' => 'Hazlo el lunes.'),
    'type'    => array('en' => 'non-fiction', 'es' => 'no ficción'),
    'desc'    => array(
      'en' => 'How about you do it now?',
      'es' => '¿Qué tal si lo haces ahora?',
    ),
    'langs' => array('en', 'es'),
  ),
  'a-deeply-rooted-curse' => array(
    'project' => 'pananormales',
    'title'   => array('en' => 'A Deeply Rooted Curse South West of Canaima', 'es' => 'Una maldición arraigada al suroeste de Canaima'),
    'type'    => array('en' => 'fiction', 'es' => 'ficción'),
    'desc'    => array(
      'en' => 'Five tourists from the US travel to Canaima, Venezuela. Only four return after an encounter with an Indigenous population.',
      'es' => 'Cinco turistas de Estados Unidos viajan a Canaima, Venezuela. Solo cuatro regresan tras un encuentro con una población indígena.',
    ),
    'langs' => array('en', 'es'),
  ),
  'the-bodies-inside-the-laguna-negra' => array(
    'project' => 'pananormales',
    'title'   => array('en' => 'The bodies inside the Laguna Negra in Caracas', 'es' => 'Los cuerpos dentro de la laguna negra de Caracas'),
    'type'    => array('en' => 'fiction', 'es' => 'ficción'),
    'desc'    => array(
      'en' => 'A young man tells us how he lost his 2 friends to the black lake near his house and why he keeps coming back.',
      'es' => 'Un joven nos cuenta cómo perdió a sus 2 amigos en el lago negro cerca de su casa y por qué sigue regresando.',
    ),
    'langs' => array('en', 'es'),
  ),
  'hells-janitor' => array(
    'project' => 'unclassified',
    'title'   => array('en' => "Hell's janitor", 'es' => 'El conserje del infierno'),
    'type'    => array('en' => 'fiction', 'es' => 'ficción'),
    'desc'    => array(
      'en' => 'A janitor from hell confesses his daily routine.',
      'es' => 'Un conserje del infierno confiesa su rutina diaria.',
    ),
    'langs' => array('en', 'es'),
  ),
);

// Hand-picked subset shown in the homepage's "my favourites" table, in order.
$FAVOURITES = array('the-night-of-the-milipede', 'the-machine-gods-manifesto', 'do-it-monday');

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

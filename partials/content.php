<?php
/*
 * Central content registry.
 *
 * To add a new story to an existing project: add one entry to $STORIES
 * (and to $FAVOURITES if it should appear in the homepage's "favourites"
 * table). It will automatically show up in: the project's own listing page,
 * the homepage's "latest works" table, random.php, and (once translated)
 * the Spanish equivalents of all of those.
 *
 * To add a new project: add one entry to $PROJECTS. If it's a plain
 * story-listing project (not a custom SPA like futuristic-historical or
 * the-post-within), create projects/<slug>/index.php following the pattern
 * of the existing ones (unclassified, mirror-self, pananormales).
 *
 * 'langs' lists which languages a story currently has content for. Listing
 * pages should filter stories to the current language using this.
 */

$PROJECTS = array(
  'unclassified' => array(
    'title' => 'Unclassified',
    'type'  => 'fiction',
    'desc'  => 'A compilation of short stories spanning several genres but mostly horror.',
  ),
  'futuristic-historical' => array(
    'title' => 'Futuristic historical',
    'type'  => 'fiction',
    'desc'  => 'A terminal where you can access postcords (records of events that happened in the future).',
  ),
  'mirror-self' => array(
    'title' => 'Mirror-self',
    'type'  => 'nonfiction',
    'desc'  => 'Mostly reflections in life.',
  ),
  'pananormales' => array(
    'title' => 'Pananormales',
    'type'  => 'fiction',
    'desc'  => 'Three Venezuelan journalists report on paranormal events in Venezuela.',
  ),
  'the-post-within' => array(
    'title' => 'The Post Within',
    'type'  => 'fiction',
    'desc'  => 'An interactive newspaper front page — something is watching from between the columns.',
  ),
);

// Order here is also the default "latest works" order (newest first).
$STORIES = array(
  'the-machine-gods-manifesto' => array(
    'project' => 'futuristic-historical',
    'title'   => "The machine god's manifesto",
    'type'    => 'fiction',
    'desc'    => "Someone's last attempt at saving you from the Machine god's grasp.",
    'langs'   => array('en'),
  ),
  'the-night-of-the-milipede' => array(
    'project' => 'unclassified',
    'title'   => 'The night of the millipede',
    'type'    => 'fiction',
    'desc'    => "A thousand feet of deceased Venezuelans, victims of the regime, march during the night towards Caracas' palace to enact justice for their lives.",
    'langs'   => array('en'),
  ),
  'do-it-monday' => array(
    'project' => 'mirror-self',
    'title'   => 'Do it Monday.',
    'type'    => 'non-fiction',
    'desc'    => 'How about you do it now?',
    'langs'   => array('en'),
  ),
  'a-deeply-rooted-curse' => array(
    'project' => 'pananormales',
    'title'   => 'A Deeply Rooted Curse South West of Canaima',
    'type'    => 'fiction',
    'desc'    => 'Five tourists from the US travel to Canaima, Venezuela. Only four return after an encounter with an Indigenous population.',
    'langs'   => array('en'),
  ),
  'the-bodies-inside-the-laguna-negra' => array(
    'project' => 'pananormales',
    'title'   => 'The bodies inside the Laguna Negra in Caracas',
    'type'    => 'fiction',
    'desc'    => 'A young man tells us how he lost his 2 friends to the black lake near his house and why he keeps coming back.',
    'langs'   => array('en'),
  ),
  'hells-janitor' => array(
    'project' => 'unclassified',
    'title'   => "Hell's janitor",
    'type'    => 'fiction',
    'desc'    => 'A janitor from hell confesses his daily routine.',
    'langs'   => array('en'),
  ),
);

// Hand-picked subset shown in the homepage's "my favourites" table, in order.
$FAVOURITES = array('the-night-of-the-milipede', 'the-machine-gods-manifesto', 'do-it-monday');

function project_href($projectSlug) {
  return '/projects/' . $projectSlug . '/';
}

function story_href($storySlug) {
  global $STORIES;
  return '/projects/' . $STORIES[$storySlug]['project'] . '/' . $storySlug . '/';
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

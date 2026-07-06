<?php
$projectSlug = 'mirror-self';
require __DIR__ . '/../../partials/content.php';
$project      = $PROJECTS[$projectSlug];
$pageTitle    = $project['title'] . ' — A Cuervoz';
$projectTitle = $project['title'];
$projectType  = $project['type'];
$countLabel   = '1 piece';
$introText    = $project['desc'];
$stories = array();
foreach ($STORIES as $slug => $story) {
  if ($story['project'] === $projectSlug) {
    $stories[] = array('href' => $slug, 'title' => $story['title'], 'type' => $story['type'], 'desc' => $story['desc']);
  }
}
include __DIR__ . '/../../partials/project-shell.php';

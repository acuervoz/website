<?php
$storySlug = 'do-it-monday';
require __DIR__ . '/../../../partials/content.php';
$story       = $STORIES[$storySlug];
$pageTitle   = $story['title'] . ' — A Cuervoz';
$storyTitle  = $story['title'];
$storyType   = $story['type'];
$projectName = $PROJECTS[$story['project']]['title'];
$mdFile      = $storySlug . '.md';
include __DIR__ . '/../../../partials/story-shell.php';

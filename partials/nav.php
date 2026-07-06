<?php
/*
 * Shared site nav. Set $activeNav to 'home' | 'projects' | 'random' before
 * including this partial to highlight the matching link; leave it unset
 * (or null) for no highlight, as on story/project pages. Expects $lang and
 * $UI to already be set (both come from partials/content.php).
 */
if (!isset($activeNav)) { $activeNav = null; }
function nav_class($name, $activeNav) { return $name === $activeNav ? ' class="active"' : ''; }
$navHome     = $lang === 'es' ? '/es/' : '/';
$navProjects = $lang === 'es' ? '/es/projects' : '/projects';
$navRandom   = $lang === 'es' ? '/es/random' : '/random';
?>  <nav>
    <a href="<?php echo $navHome; ?>"<?php echo nav_class('home', $activeNav); ?>><?php echo $UI[$lang]['nav_home']; ?></a>
    <span class="nav-sep">/</span>
    <a href="<?php echo $navProjects; ?>"<?php echo nav_class('projects', $activeNav); ?>><?php echo $UI[$lang]['nav_projects']; ?></a>
    <span class="nav-sep">/</span>
    <a href="<?php echo $navRandom; ?>"<?php echo nav_class('random', $activeNav); ?>><?php echo $UI[$lang]['nav_random']; ?></a>
    <span class="nav-sep">/</span>
    <a href="<?php echo lang_switch_href($lang); ?>"><?php echo $UI[$lang]['lang_switch']; ?></a>
  </nav>

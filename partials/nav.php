<?php
/*
 * Shared site nav. Set $activeNav to 'home' | 'projects' | 'random' before
 * including this partial to highlight the matching link; leave it unset
 * (or null) for no highlight, as on story/project pages.
 */
if (!isset($activeNav)) { $activeNav = null; }
function nav_class($name, $activeNav) { return $name === $activeNav ? ' class="active"' : ''; }
?>  <nav>
    <a href="/"<?php echo nav_class('home', $activeNav); ?>>home</a>
    <span class="nav-sep">/</span>
    <a href="/projects"<?php echo nav_class('projects', $activeNav); ?>>projects</a>
    <span class="nav-sep">/</span>
    <a href="/random"<?php echo nav_class('random', $activeNav); ?>>random</a>
  </nav>

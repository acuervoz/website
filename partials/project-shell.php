<?php
/*
 * Shared shell for a simple project listing page (a project made of plain
 * story-reader pages, not a custom SPA like postcords-archive or
 * the-post-within).
 * The including file must set $projectSlug and $project (= $PROJECTS[$projectSlug])
 * before requiring this partial. The story list is derived from $STORIES,
 * filtered to this project and to stories available in the current $lang.
 */
$translated    = array_key_exists($lang, $project['title']);
$availableLang = $translated ? $lang : 'en';
$projectTitle  = t($project['title'], $lang);
$projectType   = t($project['type'], $lang);
$countLabel    = t($project['count'], $lang);
$introText     = t($project['desc'], $lang);
$pageTitle     = $projectTitle . ' — A Cuervoz';

$stories = array();
foreach (stories_for_lang($lang) as $slug => $s) {
  if ($s['project'] === $projectSlug) {
    $stories[] = array(
      'href'  => $slug,
      'title' => t($s['title'], $lang),
      'type'  => t($s['type'], $lang),
      'desc'  => t($s['desc'], $lang),
    );
  }
}
?><!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?php echo $pageTitle; ?></title>
  <link rel="stylesheet" href="/style.css" />
</head>
<body>

<?php include __DIR__ . '/nav.php'; ?>

  <div class="divider">- - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -</div>

  <a class="back-link" href="../">&larr; <?php echo $UI[$lang]['back_projects']; ?></a>

  <div class="page-header">
    <h1 class="page-title"><?php echo $projectTitle; ?></h1>
    <div class="page-meta">
      <span><?php echo $projectType; ?></span>
      <span><?php echo $countLabel; ?></span>
    </div>
  </div>

  <p class="intro"><?php echo $introText; ?></p>

<?php if ($translated): ?>
  <div class="sec-hdr"><?php echo $UI[$lang]['sec_stories']; ?></div>

  <table class="proj-table">
    <thead>
      <tr>
        <th style="width:36%"><?php echo $UI[$lang]['col_title']; ?></th>
        <th style="width:14%"><?php echo $UI[$lang]['col_type']; ?></th>
        <th><?php echo $UI[$lang]['col_desc']; ?></th>
      </tr>
    </thead>
    <tbody>
<?php foreach ($stories as $s): ?>
      <tr>
        <td class="t-cell"><a href="<?php echo $s['href']; ?>"><?php echo $s['title']; ?></a></td>
        <td class="d-cell"><?php echo $s['type']; ?></td>
        <td class="d-cell"><?php echo $s['desc']; ?></td>
      </tr>
<?php endforeach; ?>
    </tbody>
  </table>
<?php else: ?>
  <?php echo lang_notice_html($lang, $availableLang, project_href($projectSlug, $availableLang)); ?>
<?php endif; ?>

  <div class="divider" style="margin-top:2.5rem;">- - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -</div>

<?php include __DIR__ . '/footer.php'; ?>

</body>
</html>

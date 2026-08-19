<?php
/*
 * Shared shell for a series' own page — the landing page listing every part
 * of one series in reading order, with the series' description.
 * The including file must set $seriesSlug and $series (= $SERIES[$seriesSlug])
 * before requiring this partial. Everything else is derived here from
 * $series, $STORIES, $PROJECTS and the current $lang.
 *
 * Lives on disk at projects/<project>/series/<series>/index.php, written by
 * the admin CMS when a series is created (see admin/api.php).
 */
$translated    = array_key_exists($lang, $series['title']);
$availableLang = $translated ? $lang : 'en';
$projectSlug   = $series['project'];
$seriesTitle   = t($series['title'], $lang);
$projectName   = t($PROJECTS[$projectSlug]['title'], $lang);
$introText     = $series['desc'] ? t($series['desc'], $lang) : '';
$pageTitle     = $seriesTitle . ' — A Cuervoz';

$parts = array();
foreach (series_stories($seriesSlug, $lang) as $slug) {
  $s = $STORIES[$slug];
  $parts[] = array(
    'href'  => '../../' . $slug . '/',
    'title' => t($s['title'], $lang),
    'type'  => t($s['type'], $lang),
    'desc'  => t($s['desc'], $lang),
  );
}
$countLabel = count($parts) . ' ' . ($lang === 'es'
  ? (count($parts) === 1 ? 'parte' : 'partes')
  : (count($parts) === 1 ? 'part' : 'parts'));
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

  <a class="back-link" href="../../">&larr; <?php echo strtolower($projectName); ?></a>

  <div class="page-header">
    <h1 class="page-title"><?php echo $seriesTitle; ?></h1>
    <div class="page-meta">
      <span><?php echo $UI[$lang]['series_label']; ?></span>
      <span><?php echo $projectName; ?></span>
      <span><?php echo $countLabel; ?></span>
    </div>
  </div>

<?php if ($translated): ?>
<?php if ($introText !== ''): ?>
  <p class="intro"><?php echo $introText; ?></p>
<?php endif; ?>

  <div class="sec-hdr"><?php echo $UI[$lang]['sec_stories']; ?></div>

  <table class="proj-table">
    <thead>
      <tr>
        <th style="width:8%"><?php echo $UI[$lang]['col_part']; ?></th>
        <th style="width:32%"><?php echo $UI[$lang]['col_title']; ?></th>
        <th style="width:14%"><?php echo $UI[$lang]['col_type']; ?></th>
        <th><?php echo $UI[$lang]['col_desc']; ?></th>
      </tr>
    </thead>
    <tbody>
<?php foreach ($parts as $i => $s): ?>
      <tr>
        <td class="part-cell"><?php echo str_pad($i + 1, 2, '0', STR_PAD_LEFT); ?></td>
        <td class="t-cell"><a href="<?php echo $s['href']; ?>"><?php echo $s['title']; ?></a></td>
        <td class="d-cell"><?php echo $s['type']; ?></td>
        <td class="d-cell"><?php echo $s['desc']; ?></td>
      </tr>
<?php endforeach; ?>
    </tbody>
  </table>
<?php else: ?>
  <?php echo lang_notice_html($lang, $availableLang, series_href($seriesSlug, $availableLang)); ?>
<?php endif; ?>

  <div class="divider" style="margin-top:2.5rem;">- - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -</div>

<?php include __DIR__ . '/footer.php'; ?>

</body>
</html>

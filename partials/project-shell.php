<?php
/*
 * Shared shell for a simple project listing page (a project made of plain
 * story-reader pages, not a custom SPA like futuristic-historical or
 * the-post-within).
 * The including file must set these before requiring this partial:
 *   $pageTitle     <title> text, e.g. "Unclassified — A Cuervoz"
 *   $projectTitle  heading text, e.g. "Unclassified"
 *   $projectType   e.g. "fiction" / "nonfiction"
 *   $countLabel    e.g. "2 stories" / "1 piece"
 *   $introText     one paragraph describing the project
 *   $stories       array of ['href' => ..., 'title' => ..., 'type' => ..., 'desc' => ...]
 */
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?php echo $pageTitle; ?></title>
  <link rel="stylesheet" href="/style.css" />
</head>
<body>

<?php include __DIR__ . '/nav.php'; ?>

  <div class="divider">- - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -</div>

  <a class="back-link" href="/projects">&larr; projects</a>

  <div class="page-header">
    <h1 class="page-title"><?php echo $projectTitle; ?></h1>
    <div class="page-meta">
      <span><?php echo $projectType; ?></span>
      <span><?php echo $countLabel; ?></span>
    </div>
  </div>

  <p class="intro"><?php echo $introText; ?></p>

  <div class="sec-hdr">// stories</div>

  <table class="proj-table">
    <thead>
      <tr>
        <th style="width:36%">title</th>
        <th style="width:14%">type</th>
        <th>description</th>
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

  <div class="divider" style="margin-top:2.5rem;">- - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -</div>

<?php include __DIR__ . '/footer.php'; ?>

</body>
</html>

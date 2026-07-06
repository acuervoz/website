<?php
require __DIR__ . '/partials/content.php';
$activeNav = 'home';
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="description" content="A Cuervoz — writer, builder, collector of ideas." />
  <title>A Cuervoz</title>
  <link rel="stylesheet" href="style.css" />
</head>
<body>

  <!-- ── Logo ── -->
  <div class="logo" role="img" aria-label="A Cuervoz"></div>
  <div class="tagline">storyteller &nbsp;&middot;&nbsp; writer &nbsp;&middot;&nbsp; web-raised <span class="blink">_</span></div>

  <div class="divider">- - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -</div>

<?php include __DIR__ . '/partials/nav.php'; ?>

  <!-- ── Intro ── -->
  <div class="sec-hdr">// intro</div>
  <p class="intro">
    Welcome, wherever you're coming from. This is my archive of stories both fiction and
    non-fiction, projects and other small creativity outputs that my <s>computer</s> brain needs
    to release.
  </p>
  <br>
  <p class="intro">Doing it for the love of the art, hope you enjoy your stay!</p>

  <!-- ── My Favourites ── -->
  <div class="sec-hdr">// my favourites</div>

  <table class="proj-table">
    <thead>
      <tr>
        <th style="width:28%">title</th>
        <th style="width:12%">type</th>
        <th style="width:16%">project</th>
        <th>description</th>
      </tr>
    </thead>
    <tbody>
<?php foreach ($FAVOURITES as $slug): $s = $STORIES[$slug]; $proj = $PROJECTS[$s['project']]; ?>
      <tr>
        <td class="t-cell"><a href="<?php echo story_href($slug); ?>"><?php echo $s['title']; ?></a></td>
        <td class="d-cell"><?php echo $s['type']; ?></td>
        <td><a href="<?php echo project_href($s['project']); ?>" class="project-badge"><?php echo $proj['title']; ?></a></td>
        <td class="d-cell"><?php echo $s['desc']; ?></td>
      </tr>
<?php endforeach; ?>
    </tbody>
  </table>

  <!-- ── Projects ── -->
  <div class="sec-hdr">// projects</div>

  <table class="proj-table">
    <thead>
      <tr>
        <th style="width:28%">title</th>
        <th style="width:14%">type</th>
        <th>description</th>
      </tr>
    </thead>
    <tbody>
<?php foreach ($PROJECTS as $slug => $p): ?>
      <tr>
        <td class="t-cell"><a href="<?php echo project_href($slug); ?>"><?php echo $p['title']; ?></a></td>
        <td class="d-cell"><?php echo $p['type']; ?></td>
        <td class="d-cell"><?php echo $p['desc']; ?></td>
      </tr>
<?php endforeach; ?>
    </tbody>
  </table>

  <!-- ── Latest Works ── -->
  <div class="sec-hdr">// latest works</div>

  <table class="proj-table">
    <thead>
      <tr>
        <th style="width:28%">title</th>
        <th style="width:12%">type</th>
        <th style="width:16%">project</th>
        <th>description</th>
      </tr>
    </thead>
    <tbody>
<?php foreach ($STORIES as $slug => $s): $proj = $PROJECTS[$s['project']]; ?>
      <tr>
        <td class="t-cell"><a href="<?php echo story_href($slug); ?>"><?php echo $s['title']; ?></a></td>
        <td class="d-cell"><?php echo $s['type']; ?></td>
        <td><a href="<?php echo project_href($s['project']); ?>" class="project-badge"><?php echo $proj['title']; ?></a></td>
        <td class="d-cell"><?php echo $s['desc']; ?></td>
      </tr>
<?php endforeach; ?>
    </tbody>
  </table>

  <div class="divider" style="margin-top:2.5rem;">- - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -</div>

  <footer>
    made by hand &nbsp;&middot;&nbsp; no trackers &nbsp;&middot;&nbsp; no cookies &nbsp;&middot;&nbsp; no control<br>
  </footer>

</body>
</html>

<?php
require __DIR__ . '/../partials/content.php';
$activeNav = 'projects';
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="description" content="Projects — A Cuervoz" />
  <title>Projects — A Cuervoz</title>
  <link rel="canonical" href="/projects" />
  <link rel="stylesheet" href="/style.css" />
</head>
<body>

  <!-- ── Logo ── -->
  <div class="logo" role="img" aria-label="A Cuervoz"></div>
  <div class="tagline">acuervoz.com &nbsp;&middot;&nbsp; writer. builder. <span class="blink">_</span></div>

  <div class="divider">- - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -</div>

<?php include __DIR__ . '/../partials/nav.php'; ?>

  <!-- ── All projects ── -->
  <div class="sec-hdr">// projects</div>

  <table class="proj-table">
    <thead>
      <tr>
        <th style="width:32%">title</th>
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

  <div class="divider" style="margin-top:2.5rem;">- - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -</div>

  <footer>
    made by hand &nbsp;&middot;&nbsp; no trackers &nbsp;&middot;&nbsp; no cookies &nbsp;&middot;&nbsp; acuervoz.com<br>
    best viewed in any browser
  </footer>

</body>
</html>

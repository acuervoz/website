<?php
require __DIR__ . '/../partials/content.php';
$activeNav = 'projects';
$pageTitle = ($lang === 'es' ? 'Proyectos' : 'Projects') . ' — A Cuervoz';
?><!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="description" content="<?php echo $pageTitle; ?>" />
  <title><?php echo $pageTitle; ?></title>
  <link rel="canonical" href="<?php echo $lang === 'es' ? '/es/projects' : '/projects'; ?>" />
  <link rel="stylesheet" href="/style.css" />
</head>
<body>

  <!-- ── Logo ── -->
  <div class="logo" role="img" aria-label="A Cuervoz"></div>
  <div class="tagline"><?php echo $UI[$lang]['tagline_projects']; ?> <span class="blink">_</span></div>

  <div class="divider">- - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -</div>

<?php include __DIR__ . '/../partials/nav.php'; ?>

  <!-- ── All projects ── -->
  <div class="sec-hdr"><?php echo $UI[$lang]['sec_projects']; ?></div>

  <table class="proj-table">
    <thead>
      <tr>
        <th style="width:32%"><?php echo $UI[$lang]['col_title']; ?></th>
        <th style="width:14%"><?php echo $UI[$lang]['col_type']; ?></th>
        <th><?php echo $UI[$lang]['col_desc']; ?></th>
      </tr>
    </thead>
    <tbody>
<?php foreach ($PROJECTS as $slug => $p): ?>
      <tr>
        <td class="t-cell"><a href="<?php echo project_href($slug, $lang); ?>"><?php echo t($p['title'], $lang); ?></a></td>
        <td class="d-cell"><?php echo t($p['type'], $lang); ?></td>
        <td class="d-cell"><?php echo t($p['desc'], $lang); ?></td>
      </tr>
<?php endforeach; ?>
    </tbody>
  </table>

  <div class="divider" style="margin-top:2.5rem;">- - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -</div>

  <footer>
    <?php echo $UI[$lang]['footer_main']; ?><br>
    <?php echo $UI[$lang]['footer_browser']; ?>
  </footer>

</body>
</html>

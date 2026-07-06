<?php
require __DIR__ . '/partials/content.php';
$activeNav = 'home';
$metaDesc = $lang === 'es'
  ? 'A Cuervoz — narrador, constructor, coleccionista de ideas.'
  : 'A Cuervoz — writer, builder, collector of ideas.';
?><!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="description" content="<?php echo $metaDesc; ?>" />
  <title>A Cuervoz</title>
  <link rel="stylesheet" href="/style.css" />
</head>
<body>

  <!-- ── Logo ── -->
  <div class="logo" role="img" aria-label="A Cuervoz"></div>
  <div class="tagline"><?php echo $UI[$lang]['tagline_home']; ?> <span class="blink">_</span></div>

  <div class="divider">- - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -</div>

<?php include __DIR__ . '/partials/nav.php'; ?>

  <!-- ── Intro ── -->
  <div class="sec-hdr"><?php echo $UI[$lang]['sec_intro']; ?></div>
  <p class="intro">
    <?php echo $UI[$lang]['intro_p1']; ?>
  </p>
  <br>
  <p class="intro"><?php echo $UI[$lang]['intro_p2']; ?></p>

  <!-- ── My Favourites ── -->
  <div class="sec-hdr"><?php echo $UI[$lang]['sec_favourites']; ?></div>

  <table class="proj-table">
    <thead>
      <tr>
        <th style="width:28%"><?php echo $UI[$lang]['col_title']; ?></th>
        <th style="width:12%"><?php echo $UI[$lang]['col_type']; ?></th>
        <th style="width:16%"><?php echo $UI[$lang]['col_project']; ?></th>
        <th><?php echo $UI[$lang]['col_desc']; ?></th>
      </tr>
    </thead>
    <tbody>
<?php foreach ($FAVOURITES as $slug): $s = $STORIES[$slug]; if (!in_array($lang, $s['langs'])) continue; $proj = $PROJECTS[$s['project']]; ?>
      <tr>
        <td class="t-cell"><a href="<?php echo story_href($slug, $lang); ?>"><?php echo t($s['title'], $lang); ?></a></td>
        <td class="d-cell"><?php echo t($s['type'], $lang); ?></td>
        <td><a href="<?php echo project_href($s['project'], $lang); ?>" class="project-badge"><?php echo t($proj['title'], $lang); ?></a></td>
        <td class="d-cell"><?php echo t($s['desc'], $lang); ?></td>
      </tr>
<?php endforeach; ?>
    </tbody>
  </table>

  <!-- ── Projects ── -->
  <div class="sec-hdr"><?php echo $UI[$lang]['sec_projects']; ?></div>

  <table class="proj-table">
    <thead>
      <tr>
        <th style="width:28%"><?php echo $UI[$lang]['col_title']; ?></th>
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

  <!-- ── Latest Works ── -->
  <div class="sec-hdr"><?php echo $UI[$lang]['sec_latest']; ?></div>

  <table class="proj-table">
    <thead>
      <tr>
        <th style="width:28%"><?php echo $UI[$lang]['col_title']; ?></th>
        <th style="width:12%"><?php echo $UI[$lang]['col_type']; ?></th>
        <th style="width:16%"><?php echo $UI[$lang]['col_project']; ?></th>
        <th><?php echo $UI[$lang]['col_desc']; ?></th>
      </tr>
    </thead>
    <tbody>
<?php foreach (stories_for_lang($lang) as $slug => $s): $proj = $PROJECTS[$s['project']]; ?>
      <tr>
        <td class="t-cell"><a href="<?php echo story_href($slug, $lang); ?>"><?php echo t($s['title'], $lang); ?></a></td>
        <td class="d-cell"><?php echo t($s['type'], $lang); ?></td>
        <td><a href="<?php echo project_href($s['project'], $lang); ?>" class="project-badge"><?php echo t($proj['title'], $lang); ?></a></td>
        <td class="d-cell"><?php echo t($s['desc'], $lang); ?></td>
      </tr>
<?php endforeach; ?>
    </tbody>
  </table>

  <div class="divider" style="margin-top:2.5rem;">- - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -</div>

  <footer>
    <?php echo $UI[$lang]['footer_home']; ?><br>
  </footer>

</body>
</html>

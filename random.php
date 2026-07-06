<?php
require __DIR__ . '/partials/content.php';
$activeNav = 'random';

// Every story available in the current language is eligible for random —
// add a story (and its translation) to partials/content.php and it shows
// up here automatically.
$pieces = array();
foreach (stories_for_lang($lang) as $slug => $story) {
  $pieces[] = story_href($slug, $lang);
}
$pageTitle = ($lang === 'es' ? 'Aleatorio' : 'Random') . ' — A Cuervoz';
$homeHref  = $lang === 'es' ? '/es/' : '/';
?><!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?php echo $pageTitle; ?></title>
  <link rel="stylesheet" href="/style.css" />
</head>
<body>

<?php include __DIR__ . '/partials/nav.php'; ?>

  <div class="divider">- - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -</div>

  <div class="random-wrap">
    <div class="sec-hdr"><?php echo $UI[$lang]['sec_random']; ?></div>
    <p id="status"><?php echo $UI[$lang]['random_picking']; ?><span class="blink">_</span></p>
    <p id="fallback" style="display:none;">
      <?php echo $UI[$lang]['random_noscript']; ?>
      <a href="<?php echo $homeHref; ?>"><?php echo $UI[$lang]['random_full_list']; ?></a>
    </p>
  </div>

  <script>
    var pieces = <?php echo json_encode(array_values($pieces)); ?>;

    if (pieces.length > 0) {
      var pick = pieces[Math.floor(Math.random() * pieces.length)];
      document.getElementById("status").textContent = <?php echo json_encode($UI[$lang]['random_taking']); ?>;
      setTimeout(function() { window.location.href = pick; }, 700);
    } else {
      document.getElementById("status").style.display = "none";
      document.getElementById("fallback").style.display = "block";
    }
  </script>

  <noscript>
    <style>#fallback { display: block !important; } #status { display: none; }</style>
  </noscript>

<?php include __DIR__ . '/partials/footer.php'; ?>

</body>
</html>

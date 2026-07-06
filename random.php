<?php
require __DIR__ . '/partials/content.php';
$activeNav = 'random';

// Every story in the registry is eligible for random — add a story to
// partials/content.php and it shows up here automatically.
$pieces = array();
foreach ($STORIES as $slug => $story) {
  $pieces[] = story_href($slug);
}
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Random — A Cuervoz</title>
  <link rel="stylesheet" href="style.css" />
</head>
<body>

<?php include __DIR__ . '/partials/nav.php'; ?>

  <div class="divider">- - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -</div>

  <div class="random-wrap">
    <div class="sec-hdr">// random</div>
    <p id="status">picking something for you<span class="blink">_</span></p>
    <p id="fallback" style="display:none;">
      javascript is off — here are all the pieces:
      <a href="/">go to the full list &rarr;</a>
    </p>
  </div>

  <script>
    var pieces = <?php echo json_encode(array_values($pieces)); ?>;

    if (pieces.length > 0) {
      var pick = pieces[Math.floor(Math.random() * pieces.length)];
      document.getElementById("status").textContent = "taking you somewhere...";
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

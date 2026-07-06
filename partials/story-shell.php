<?php
/*
 * Shared shell for a single-story reader page.
 * The including file must set these before requiring this partial:
 *   $pageTitle    <title> text, e.g. "Hell's janitor — A Cuervoz"
 *   $storyTitle   heading text, e.g. "Hell's janitor"
 *   $storyType    e.g. "fiction" / "non-fiction"
 *   $projectName  parent project display name, e.g. "Unclassified"
 *   $mdFile       markdown filename sitting next to this file, e.g. "hells-janitor.md"
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

  <a class="back-link" href="../">&larr; <?php echo strtolower($projectName); ?></a>

  <div class="page-header">
    <h1 class="page-title"><?php echo $storyTitle; ?></h1>
    <div class="page-meta">
      <span><?php echo $storyType; ?></span>
      <span><?php echo $projectName; ?></span>
    </div>
  </div>

  <div class="page-body" id="md-content">
    <p class="loading-msg">loading<span class="blink">_</span></p>
    <noscript>
      <p>JavaScript is required to render this page. <a href="<?php echo $mdFile; ?>">Read the raw markdown file instead.</a></p>
    </noscript>
  </div>

  <div class="divider" style="margin-top:2.5rem;">- - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -</div>

<?php include __DIR__ . '/footer.php'; ?>

  <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
  <script>
    fetch('<?php echo $mdFile; ?>')
      .then(function(r) { return r.text(); })
      .then(function(text) {
        document.getElementById('md-content').innerHTML = marked.parse(text);
      })
      .catch(function() {
        document.getElementById('md-content').innerHTML =
          '<p>Could not load content. <a href="<?php echo $mdFile; ?>">Read the raw file.</a></p>';
      });
  </script>

</body>
</html>

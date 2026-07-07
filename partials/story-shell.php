<?php
/*
 * Shared shell for a single-story reader page.
 * The including file must set $storySlug and $story (= $STORIES[$storySlug])
 * before requiring this partial. Everything else — title, type, project
 * name, which .md file to fetch — is derived here from $story, $PROJECTS,
 * and the current $lang (all from partials/content.php).
 */
$storyLang   = in_array($lang, $story['langs']) ? $lang : 'en'; // fall back if untranslated
$storyTitle  = t($story['title'], $lang);
$storyType   = t($story['type'], $lang);
$projectName = t($PROJECTS[$story['project']]['title'], $lang);
$pageTitle   = $storyTitle . ' — A Cuervoz';
$mdFile      = $storySlug . ($storyLang === 'es' ? '-es.md' : '.md');
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

  <a class="back-link" href="../">&larr; <?php echo strtolower($projectName); ?></a>

  <div class="page-header">
    <h1 class="page-title"><?php echo $storyTitle; ?></h1>
    <div class="page-meta">
      <span><?php echo $storyType; ?></span>
      <span><?php echo $projectName; ?></span>
    </div>
  </div>

  <div class="page-body" id="md-content">
    <p class="loading-msg"><?php echo $UI[$lang]['loading']; ?><span class="blink">_</span></p>
    <noscript>
      <p><?php echo $UI[$lang]['noscript_required']; ?> <a href="<?php echo $mdFile; ?>"><?php echo $UI[$lang]['read_raw']; ?></a></p>
    </noscript>
  </div>

  <div class="divider" style="margin-top:2.5rem;">- - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -</div>

<?php include __DIR__ . '/footer.php'; ?>

  <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
  <script>
    fetch('<?php echo $mdFile; ?>')
      .then(function(r) { return r.text(); })
      .then(function(text) {
        var el = document.getElementById('md-content');
        el.innerHTML = marked.parse(text);
        // Alignment convention: a paragraph starting with "R: " or "C: " is
        // right- or center-aligned; the marker itself is stripped.
        el.querySelectorAll('p').forEach(function(p) {
          if (/^R:\s*/.test(p.innerHTML)) {
            p.innerHTML = p.innerHTML.replace(/^R:\s*/, '');
            p.classList.add('dlg-right');
          } else if (/^C:\s*/.test(p.innerHTML)) {
            p.innerHTML = p.innerHTML.replace(/^C:\s*/, '');
            p.classList.add('dlg-center');
          }
        });
      })
      .catch(function() {
        document.getElementById('md-content').innerHTML =
          '<p><?php echo addslashes($UI[$lang]['could_not_load']); ?> <a href="<?php echo $mdFile; ?>"><?php echo addslashes($UI[$lang]['read_raw_file']); ?></a></p>';
      });
  </script>

</body>
</html>

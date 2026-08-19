<?php
/*
 * Shared shell for a single-story reader page.
 * The including file must set $storySlug and $story (= $STORIES[$storySlug])
 * before requiring this partial. Everything else — title, type, project
 * name, which .md file to fetch — is derived here from $story, $PROJECTS,
 * and the current $lang (all from partials/content.php).
 */
$translated    = in_array($lang, $story['langs']);
$availableLang = $translated ? $lang : $story['langs'][0]; // e.g. falls back to 'en'
$storyTitle    = t($story['title'], $lang);
$storyType     = t($story['type'], $lang);
$projectName   = t($PROJECTS[$story['project']]['title'], $lang);
$pageTitle     = $storyTitle . ' — A Cuervoz';
$mdFile        = $storySlug . ($availableLang === 'es' ? '-es.md' : '.md');
$redact        = !empty($story['redact']);
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
<?php if ($translated): ?>
    <p class="loading-msg"><?php echo $UI[$lang]['loading']; ?><span class="blink">_</span></p>
    <noscript>
      <p><?php echo $UI[$lang]['noscript_required']; ?> <a href="<?php echo $mdFile; ?>"><?php echo $UI[$lang]['read_raw']; ?></a></p>
    </noscript>
<?php else: ?>
    <?php echo lang_notice_html($lang, $availableLang, story_href($storySlug, $availableLang)); ?>
<?php endif; ?>
  </div>

  <div class="divider" style="margin-top:2.5rem;">- - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -</div>

<?php include __DIR__ . '/footer.php'; ?>

<?php if ($translated): ?>
  <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
  <script>
<?php if ($redact): ?>
    // "Redact" is a per-story toggle in the admin CMS: with it on, every
    // literal [REDACTED] in the markdown is swapped for a styled span (see
    // .redacted in style.css). Done over text nodes rather than innerHTML so
    // a marker that lands inside a link or an <em> can't break its markup.
    function glitchRedactions(root) {
      var walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, null, false);
      var nodes = [];
      while (walker.nextNode()) nodes.push(walker.currentNode);
      nodes.forEach(function(node) {
        var parts = node.nodeValue.split(/(\[redacted\])/gi);
        if (parts.length < 2) return;
        var frag = document.createDocumentFragment();
        parts.forEach(function(part) {
          if (part === '') return;
          if (/^\[redacted\]$/i.test(part)) {
            var span = document.createElement('span');
            span.className = 'redacted';
            span.setAttribute('aria-label', 'redacted');
            span.textContent = part;
            frag.appendChild(span);
          } else {
            frag.appendChild(document.createTextNode(part));
          }
        });
        node.parentNode.replaceChild(frag, node);
      });
    }
<?php endif; ?>
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
<?php if ($redact): ?>
        glitchRedactions(el);
<?php endif; ?>
      })
      .catch(function() {
        document.getElementById('md-content').innerHTML =
          '<p><?php echo addslashes($UI[$lang]['could_not_load']); ?> <a href="<?php echo $mdFile; ?>"><?php echo addslashes($UI[$lang]['read_raw_file']); ?></a></p>';
      });
  </script>
<?php endif; ?>

</body>
</html>

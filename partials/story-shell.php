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
// Cache-bust by the file's own modification time: editing a story in the admin
// rewrites the .md, which changes this, which makes it a URL no cache has seen.
// The .htaccess no-cache header covers new requests; this covers whatever a
// cache is already holding.
$mdPath        = dirname(__DIR__) . '/projects/' . $story['project'] . '/' . $storySlug . '/' . $mdFile;
$mdSrc         = $mdFile . (is_file($mdPath) ? '?v=' . filemtime($mdPath) : '');
$redact        = !empty($story['redact']);

// Series context: the link back to the series' own page, and the previous /
// next part buttons. Position is taken from the parts readable in the
// current language, so an untranslated part never becomes a dead end — and
// a story that isn't itself readable here has no position at all.
$seriesSlug  = $story['series'] ?? null;
$seriesTitle = $seriesSlug !== null ? t($SERIES[$seriesSlug]['title'], $lang) : null;
$prevPart    = null;
$nextPart    = null;
$partLabel   = null;
if ($seriesSlug !== null && $translated) {
  $parts = series_stories($seriesSlug, $lang);
  $i = array_search($storySlug, $parts, true);
  if ($i !== false) {
    $partLabel = sprintf($UI[$lang]['part_n_of'], $i + 1, count($parts));
    if ($i > 0)                  $prevPart = $parts[$i - 1];
    if ($i < count($parts) - 1)  $nextPart = $parts[$i + 1];
  }
}
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
<?php if ($seriesSlug !== null): ?>
    <div class="series-line">
      <a class="series-link" href="<?php echo series_href($seriesSlug, $lang); ?>"><?php echo $UI[$lang]['series_label']; ?>: <?php echo $seriesTitle; ?></a>
<?php if ($partLabel !== null): ?>
      <span class="series-part"><?php echo $partLabel; ?></span>
<?php endif; ?>
    </div>
<?php endif; ?>
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

<?php if ($prevPart !== null || $nextPart !== null): ?>
  <div class="series-nav">
<?php if ($prevPart !== null): ?>
    <a class="series-nav-btn" href="<?php echo story_href($prevPart, $lang); ?>">&larr; <?php echo $UI[$lang]['prev_part']; ?></a>
<?php endif; ?>
<?php if ($nextPart !== null): ?>
    <a class="series-nav-btn next" href="<?php echo story_href($nextPart, $lang); ?>"><?php echo $UI[$lang]['next_part']; ?> &rarr;</a>
<?php endif; ?>
  </div>
<?php endif; ?>

  <div class="divider" style="margin-top:2.5rem;">- - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -</div>

<?php include __DIR__ . '/footer.php'; ?>

<?php if ($translated): ?>
  <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
  <script>
    marked.setOptions({ breaks: true });

  // ── Line alignment ───────────────────────────────────
  // A line starting "R: " or "C: " is right- or centre-aligned and the marker
  // is stripped. Alignment is per LINE, not per paragraph: with breaks:true a
  // paragraph holds several <br>-separated lines, and each can align on its
  // own. Paragraphs with no marker are left exactly as marked.js built them.
  function applyAlignment(root) {
    root.querySelectorAll('p').forEach(function(p) {
      if (!/(^|<br\s*\/?>)\s*[RC]:/i.test(p.innerHTML)) return;
      var frag = document.createDocumentFragment();
      p.innerHTML.split(/<br\s*\/?>/i).forEach(function(line) {
        var div = document.createElement('div');
        var m = /^\s*([RC]):\s*/i.exec(line);
        if (m) {
          div.className = m[1].toUpperCase() === 'R' ? 'dlg-right' : 'dlg-center';
          line = line.slice(m[0].length);
        }
        div.innerHTML = line;
        frag.appendChild(div);
      });
      p.innerHTML = '';
      p.appendChild(frag);
    });
  }

  // ── Inline markup the editor can insert ──────────────
  // [[text]] renders as a fake button (see .fake-btn). Done over text nodes
  // rather than the markdown source so a stray [[ inside a code block or a
  // link can't rewrite someone else's markup.
  function decorateButtons(root) {
    var walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, null, false);
    var nodes = [];
    while (walker.nextNode()) nodes.push(walker.currentNode);
    nodes.forEach(function(node) {
      if (node.parentNode && node.parentNode.nodeName === 'CODE') return;
      var parts = node.nodeValue.split(/(\[\[[^\]]+\]\])/g);
      if (parts.length < 2) return;
      var frag = document.createDocumentFragment();
      parts.forEach(function(part) {
        if (part === '') return;
        var m = /^\[\[([^\]]+)\]\]$/.exec(part);
        if (m) {
          var b = document.createElement('span');
          b.className = 'fake-btn';
          b.textContent = m[1];
          frag.appendChild(b);
        } else {
          frag.appendChild(document.createTextNode(part));
        }
      });
      node.parentNode.replaceChild(frag, node);
    });
  }
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
    fetch('<?php echo $mdSrc; ?>')
      .then(function(r) { return r.text(); })
      .then(function(text) {
        var el = document.getElementById('md-content');
        el.innerHTML = marked.parse(text);
        decorateButtons(el);
        applyAlignment(el);
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

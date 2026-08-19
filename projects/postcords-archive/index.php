<?php
/*
 * Postcords archive — the terminal.
 *
 * This project renders its own page rather than the shared project-shell:
 * the terminal IS the project page. It's a normal CMS project in every other
 * way, so the story list below is built from the same registry the rest of
 * the site reads, in whatever order the admin's PROJECTS tab put them in —
 * adding a story to this project makes it appear in the terminal, no edit
 * here needed. (It was a hardcoded array back when this file was static
 * index.html, which is why nothing filed here used to show up.)
 */
$projectSlug = 'postcords-archive';
require __DIR__ . '/../../partials/content.php';

$terminalStories = array();
// 'oldest' so a newly added postcord joins the end of the archive rather
// than jumping to the top of the listing.
foreach (project_stories($projectSlug, 'en', 'oldest') as $slug => $s) {
  $entry = array(
    'slug'   => $slug,
    'series' => $s['series'],          // null, or the slug of the series it belongs to
    'en' => array(
      'title' => $s['title']['en'],
      'desc'  => $s['desc']['en'] ?? '',
      'file'  => $slug . '/' . $slug . '.md',
    ),
  );
  // A story with no Spanish block shows the terminal's own "not available in
  // this language" notice instead of fetching a file that isn't there.
  if (in_array('es', $s['langs'])) {
    $entry['es'] = array(
      'title' => $s['title']['es'] ?? $s['title']['en'],
      'desc'  => $s['desc']['es'] ?? '',
      'file'  => $slug . '/' . $slug . '-es.md',
    );
  }
  $terminalStories[] = $entry;
}

// Series in this project, each carrying its parts in reading order. Unlike a
// story, a series is only a label, so an untranslated one falls back to its
// English text rather than showing a "not available" notice — there's nothing
// to fail to fetch. Parts are listed in every language; a part that isn't
// translated shows that notice itself when opened, as it always has.
$terminalSeries = array();
foreach (series_for_project($projectSlug, 'en') as $sslug => $ser) {
  $terminalSeries[] = array(
    'slug'  => $sslug,
    'en'    => array(
      'title' => $ser['title']['en'],
      'desc'  => $ser['desc']['en'] ?? '',
    ),
    'es'    => array(
      'title' => $ser['title']['es'] ?? $ser['title']['en'],
      'desc'  => $ser['desc']['es'] ?? ($ser['desc']['en'] ?? ''),
    ),
    'parts' => array_values(series_stories($sslug, 'en')),
  );
}

$jsonFlags   = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
$storiesJson = json_encode($terminalStories, $jsonFlags);
$seriesJson  = json_encode($terminalSeries, $jsonFlags);
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>POSTCORDS ARCHIVE</title>
  <style>
    @import url('https://unpkg.com/@fontsource/ibm-plex-mono/index.css');

    :root {
      --bg:          #0d0d0d;
      --bg2:         #111110;
      --text:        #ede8df;
      --bright:      #ede8df;
      --dim:         #9a9488;
      --ghost:       #4a4a40;
      --faint:       #333328;
      --border:      #1a1a16;
      --sel-bg:      rgba(255, 106, 119, 0.12);
      --sel-border:  rgba(255, 106, 119, 0.25);
      --active-bg:   #ff6a77;
      --active-fg:   #0d0d0d;
      --accent:      #ff6a77;
      --font: 'IBM Plex Mono', 'Courier New', monospace;
    }

    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    html, body {
      height: 100%;
      overflow: hidden;
      background: #000;
      font-family: var(--font);
    }

    body {
      display: flex;
      align-items: center;
      justify-content: center;
    }

    /* CRT scanlines */
    body::before {
      content: '';
      position: fixed;
      inset: 0;
      background: repeating-linear-gradient(
        0deg,
        transparent,
        transparent 3px,
        rgba(0,0,0,0.1) 3px,
        rgba(0,0,0,0.1) 4px
      );
      pointer-events: none;
      z-index: 9999;
    }

    /* Vignette */
    body::after {
      content: '';
      position: fixed;
      inset: 0;
      background: radial-gradient(ellipse at center, transparent 55%, rgba(0,0,0,0.65) 100%);
      pointer-events: none;
      z-index: 9998;
    }

    /* ── Terminal window ── */
    .terminal {
      width:   min(880px, 96vw);
      height:  min(760px, 92vh);
      background: var(--bg);
      border: 1px solid var(--border);
      display: flex;
      flex-direction: column;
      box-shadow:
        0 0 0 1px rgba(255,106,119,0.03),
        0 0 50px rgba(255,106,119,0.05),
        inset 0 0 80px rgba(0,0,0,0.25);
    }

    /* ── Header bar ── */
    .term-bar {
      background: var(--bg2);
      border-bottom: 1px solid var(--border);
      padding: 0.38rem 1rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-shrink: 0;
      user-select: none;
    }
    .bar-title {
      font-size: 10px;
      letter-spacing: 0.14em;
      color: var(--accent);
    }
    .bar-right {
      font-size: 9px;
      letter-spacing: 0.1em;
      color: var(--ghost);
    }
    .bar-right .online { color: var(--dim); }
    .bar-right a.lang-link {
      color: var(--ghost);
      text-decoration: none;
      cursor: pointer;
    }
    .bar-right a.lang-link:hover { color: var(--accent); }

    /* ── Shared view layout ── */
    .term-view {
      flex: 1;
      display: flex;
      flex-direction: column;
      overflow: hidden;
      padding: 0.85rem 1.1rem 0.7rem;
      font-size: 12px;
      color: var(--text);
      line-height: 1.6;
    }

    .term-rule {
      border: none;
      border-top: 1px solid var(--ghost);
      margin: 0.45rem 0;
      flex-shrink: 0;
      opacity: 0.55;
    }

    /* ── LIST VIEW ── */
    #view-list { display: flex; }
    #view-story { display: none; }

    /* Intro block. It's long enough to crowd the listing on a short window,
       so it's allowed to shrink and scroll on its own rather than pushing the
       archive off the bottom — the shell is the retro scrollbar's anchor. */
    .prompt-shell {
      position: relative;
      flex: 0 1 auto;
      min-height: 0;
      max-height: 44%;
      display: flex;
    }
    .term-prompt {
      font-size: 11px;
      color: var(--dim);
      line-height: 1.65;
      overflow-y: auto;
      padding-right: 14px;
      flex: 1;
      max-width: 82ch;
      scrollbar-width: none;
    }
    .term-prompt::-webkit-scrollbar { display: none; }
    .prompt-row {
      display: flex;
      align-items: baseline;
    }
    /* A blank line is pure spacing — no ">" to squash out of shape. */
    .prompt-row.is-spacer { height: 0.7em; }
    /* The call to action stays put while the letter above it scrolls. */
    .prompt-fixed {
      flex: 0 0 auto;   /* overrides .term-prompt's flex:1 — it must not grow */
      overflow: visible;
      margin-bottom: 0.55rem;
    }
    .term-prompt .cursor-prefix {
      color: var(--text);
      margin-right: 0.4em;
      flex-shrink: 0;
    }

    /* Two-column area */
    .term-cols {
      display: flex;
      gap: 0;
      flex: 1;
      overflow: hidden;
      margin: 0.3rem 0;
    }

    /* The scroll shell is the positioning context for the retro scrollbar
       track (below) — it does NOT scroll itself. Only .list-col scrolls.
       This split matters: an absolutely-positioned child whose containing
       block is the scrolling element scrolls away with the content instead
       of staying pinned like a real scrollbar, so the track has to live in
       a non-scrolling ancestor instead. */
    .list-col-shell {
      flex: 1;
      position: relative;
      overflow: hidden;
      display: flex;
    }
    .list-col {
      flex: 1;
      overflow-y: auto;
      padding-right: 14px;
    }

    /* Story entry row */
    .term-entry {
      display: flex;
      align-items: baseline;
      gap: 0.55rem;
      padding: 0.32rem 0.45rem;
      cursor: pointer;
      user-select: none;
      border: 1px solid transparent;
      transition: background 60ms;
    }

    .e-arrow {
      width: 1.1em;
      font-size: 10px;
      color: var(--text);
      flex-shrink: 0;
      visibility: hidden;
    }
    .e-num {
      font-size: 10px;
      color: var(--ghost);
      flex-shrink: 0;
      min-width: 4.5ch;
    }
    .e-title {
      font-size: 12px;
      letter-spacing: 0.04em;
      text-transform: uppercase;
      color: var(--dim);
      flex: 1;
    }
    /* Focused (hover / keyboard) */
    .term-entry.focused {
      background: var(--sel-bg);
      border-color: var(--sel-border);
    }
    .term-entry.focused .e-arrow  { visibility: visible; }
    .term-entry.focused .e-title  { color: var(--text); }
    .term-entry.focused .e-num    { color: var(--dim); }

    /* Active flash */
    .term-entry.flash {
      background: var(--active-bg);
    }
    .term-entry.flash .e-arrow,
    .term-entry.flash .e-title,
    .term-entry.flash .e-num { color: var(--active-fg); }

    /* Home / utility rows (home, language toggle) */
    .term-entry.is-home .e-title,
    .term-entry.is-lang .e-title {
      font-size: 10px;
      letter-spacing: 0.1em;
      color: var(--ghost);
    }
    .term-entry.is-home.focused .e-title,
    .term-entry.is-lang.focused .e-title { color: var(--dim); }

    /* Series rows read as directories: accented, with a part-count tag */
    .term-entry.is-series .e-title { color: var(--dim); }
    .term-entry.is-series.focused .e-title { color: var(--text); }
    .e-tag {
      font-size: 9px;
      letter-spacing: 0.1em;
      color: var(--accent);
      border: 1px solid var(--sel-border);
      padding: 0 4px;
      flex-shrink: 0;
    }
    /* The SERIES menu row sits with home/lang but keeps the accent tint. */
    .term-entry.is-series-menu .e-title {
      font-size: 10px;
      letter-spacing: 0.1em;
      color: var(--accent);
      opacity: 0.75;
    }
    .term-entry.is-series-menu.focused .e-title { opacity: 1; }

    /* "back" row — same muted treatment as home/lang */
    .term-entry.is-back .e-title {
      font-size: 10px;
      letter-spacing: 0.1em;
      color: var(--ghost);
    }
    .term-entry.is-back.focused .e-title { color: var(--dim); }

    /* Previous / next part bar inside a story */
    .story-nav {
      flex-shrink: 0;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 0.75rem;
      margin-top: 0.5rem;
    }
    .story-nav-btn {
      background: none;
      border: 1px solid var(--border);
      color: var(--dim);
      font-family: var(--font);
      font-size: 10px;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      padding: 0.3rem 0.7rem;
      cursor: pointer;
      transition: border-color 80ms, color 80ms;
    }
    .story-nav-btn:hover {
      border-color: var(--accent);
      color: var(--accent);
    }
    .story-nav-mid {
      font-size: 9px;
      letter-spacing: 0.1em;
      color: var(--ghost);
      user-select: none;
    }

    /* Description panel (right) */
    .desc-col-shell {
      width: 270px;
      flex-shrink: 0;
      border-left: 1px solid var(--ghost);
      position: relative;
      overflow: hidden;
      display: flex;
    }
    .desc-col {
      flex: 1;
      padding-left: 1rem;
      padding-right: 14px;
      overflow-y: auto;
      display: flex;
      flex-direction: column;
    }
    .desc-label {
      font-size: 8.5px;
      letter-spacing: 0.14em;
      text-transform: uppercase;
      color: var(--ghost);
      margin-bottom: 0.5rem;
    }
    .desc-text {
      font-size: 11px;
      color: var(--ghost);
      line-height: 1.75;
      transition: color 120ms;
    }
    .desc-col.active .desc-text { color: var(--dim); }
    /* Series provenance under the blurb — same accent as the row tag. */
    .desc-series {
      font-size: 10px;
      color: var(--accent);
      letter-spacing: 0.06em;
      line-height: 1.6;
      margin-top: 0.5rem;
    }
    .desc-series:empty { display: none; }

    /* Hint bar */
    .term-hints {
      flex-shrink: 0;
      border-top: 1px solid var(--ghost);
      padding-top: 0.38rem;
      margin-top: 0.2rem;
      font-size: 9px;
      color: var(--ghost);
      letter-spacing: 0.08em;
      display: flex;
      gap: 2rem;
      user-select: none;
      opacity: 0.7;
    }

    /* ── STORY VIEW ── */
    .story-meta {
      flex-shrink: 0;
      margin-bottom: 0.45rem;
    }
    .story-back {
      font-size: 10px;
      color: var(--ghost);
      cursor: pointer;
      letter-spacing: 0.1em;
      display: inline-block;
      margin-bottom: 0.35rem;
    }
    .story-back:hover { color: var(--dim); }

    .story-title {
      font-size: 13px;
      color: var(--bright);
      letter-spacing: 0.07em;
      text-transform: uppercase;
      line-height: 1.3;
    }
    .story-info {
      font-size: 9px;
      color: var(--ghost);
      letter-spacing: 0.1em;
      margin-top: 0.25rem;
    }

    .story-body-shell {
      flex: 1;
      position: relative;
      overflow: hidden;
      display: flex;
    }
    .story-body {
      flex: 1;
      overflow-y: auto;
      padding: 0.35rem 14px 0 0;
      font-size: 12px;
      color: var(--dim);
      line-height: 1.85;
    }

    /* Markdown within terminal */
    .story-body p           { margin-bottom: 1rem; }
    .story-body h2          {
      font-size: 9.5px;
      letter-spacing: 0.14em;
      text-transform: uppercase;
      color: var(--text);
      font-weight: normal;
      margin: 1.5rem 0 0.5rem;
      padding-bottom: 0.25rem;
      border-bottom: 1px solid var(--ghost);
    }
    .story-body blockquote  {
      border-left: 1px solid var(--ghost);
      padding-left: 0.75rem;
      margin: 0.75rem 0;
      font-size: 11px;
    }
    .story-body blockquote p { color: var(--ghost); margin-bottom: 0.25rem; }
    .story-body strong      { color: var(--text); font-weight: normal; }
    .story-body em          { font-style: italic; }
    .story-body a           { color: var(--dim); }
    .story-body hr          { border: none; border-top: 1px solid var(--ghost); margin: 1rem 0; opacity: 0.4; }
    .story-body ul,
    .story-body ol          { padding-left: 1.5rem; margin-bottom: 1rem; }
    .story-body li          { margin-bottom: 0.2rem; }
    .story-body .dlg-right  { text-align: right; }
    .story-body .dlg-center { text-align: center; }
    /* [[text]] from the editor — looks like a button, deliberately isn't one */
    .story-body .fake-btn {
      display: inline-block;
      font-family: var(--font);
      font-size: 11px;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      color: var(--accent);
      border: 1px solid var(--accent);
      background: rgba(255, 106, 119, 0.12);
      padding: 0.15rem 0.6rem;
      margin: 0.1rem 0.1rem;
      line-height: 1.6;
      user-select: none;
    }

    .story-hint {
      flex-shrink: 0;
      border-top: 1px solid var(--ghost);
      padding-top: 0.38rem;
      margin-top: 0.2rem;
      font-size: 9px;
      color: var(--ghost);
      letter-spacing: 0.08em;
      user-select: none;
      opacity: 0.7;
    }

    .sys-msg {
      font-size: 11px;
      color: var(--ghost);
      letter-spacing: 0.06em;
    }
    .sys-msg a {
      color: var(--accent);
      text-decoration: underline;
    }
    .sys-msg a:hover { color: var(--bright); }

    /* Scrollbar — chunky retro terminal style: hard pixel edges, bordered
       track, solid blocky thumb, no arrow buttons, no rounding.
       Native ::-webkit-scrollbar styling only exists in Chromium/WebKit —
       Firefox has no way to render a bordered/hard-edged thumb, only a
       plain OS-drawn bar. So the native bar is hidden everywhere and a
       custom track+thumb (built in JS, see .retro-scrollbar-* below) is
       used instead, which renders identically in every browser. */
    .list-col, .desc-col, .story-body {
      scrollbar-width: none;
    }
    .list-col::-webkit-scrollbar,
    .desc-col::-webkit-scrollbar,
    .story-body::-webkit-scrollbar {
      display: none;
    }

    .retro-scrollbar-track {
      position: absolute;
      top: 0;
      right: 0;
      bottom: 0;
      width: 12px;
      background: var(--bg2);
      border-left: 1px solid var(--border);
      box-shadow: inset 0 0 0 1px var(--border);
      display: none;
    }
    .retro-scrollbar-track.active { display: block; }
    .retro-scrollbar-thumb {
      position: absolute;
      left: 0;
      right: 0;
      background: var(--ghost);
      border: 2px solid var(--bg2);
      box-shadow: inset 0 0 0 1px var(--faint);
      cursor: pointer;
    }
    .retro-scrollbar-thumb:hover,
    .retro-scrollbar-thumb.dragging { background: var(--accent); }

    @keyframes blink { 0%,100% { opacity:1; } 50% { opacity:0; } }
    .blink { animation: blink 1.1s step-end infinite; }

    @media (max-width: 600px) {
      .desc-col-shell { display: none; }
    }
  </style>
</head>
<body>

<div class="terminal">

  <!-- Header bar -->
  <div class="term-bar">
    <span class="bar-title" id="bar-title">POSTCORDS ARCHIVE</span>
    <span class="bar-right"><a class="lang-link" id="nav-lang"></a>&nbsp;&nbsp;<span class="online">ONLINE</span>&nbsp;&nbsp;<span class="blink">█</span></span>
  </div>

  <!-- ── LIST VIEW ── -->
  <div class="term-view" id="view-list">

    <div class="prompt-shell">
      <div class="term-prompt" id="term-prompt"></div>
    </div>
    <div class="term-prompt prompt-fixed">
      <div class="prompt-row"><span class="cursor-prefix">&gt;</span><span id="prompt-select"></span></div>
    </div>

    <hr class="term-rule">

    <div class="term-cols">
      <div class="list-col-shell">
        <div class="list-col" id="term-list"></div>
      </div>
      <div class="desc-col-shell">
        <div class="desc-col" id="desc-col">
          <div class="desc-label" id="desc-label">// record info</div>
          <div class="desc-text" id="desc-text">—</div>
          <div class="desc-series" id="desc-series"></div>
        </div>
      </div>
    </div>

    <hr class="term-rule">
    <div class="term-hints">
      <span id="hint-nav">↑ ↓ &nbsp;NAVIGATE</span>
      <span id="hint-open">ENTER &nbsp;OPEN</span>
      <span id="hint-back">ESC &nbsp;BACK</span>
    </div>

  </div>

  <!-- ── STORY VIEW ── -->
  <div class="term-view" id="view-story">

    <div class="story-meta">
      <div class="story-back" id="story-back">← <span id="story-back-label">BACK TO ARCHIVE</span> &nbsp;[ESC]</div>
      <div class="story-title" id="story-title"></div>
      <div class="story-info" id="story-info"></div>
    </div>

    <hr class="term-rule">

    <div class="story-body-shell">
      <div class="story-body" id="story-body">
        <div id="story-body-content">
          <p class="sys-msg" id="story-loading">&gt; RETRIEVING RECORD…</p>
        </div>
      </div>
    </div>

    <div class="story-nav" id="story-nav">
      <button type="button" class="story-nav-btn" id="story-prev"></button>
      <span class="story-nav-mid" id="story-part-label"></span>
      <button type="button" class="story-nav-btn" id="story-next"></button>
    </div>

    <hr class="term-rule">
    <div class="story-hint"><span id="story-hint-label">ESC — BACK TO ARCHIVE</span></div>

  </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
<script>
  // ── Language state ───────────────────────────────────
  // /es and /es/<path> serve this exact same file (see .htaccess), so the
  // requested language is read straight from the URL, same convention as
  // the rest of the site.
  var path = window.location.pathname;
  var isEs = /^\/es(\/|$)/.test(path);
  var otherLangHref = isEs
    ? (path.replace(/^\/es(?=\/|$)/, '') || '/')
    : ('/es' + path);

  var TXT = {
    en: {
      docTitle:    'POSTCORDS ARCHIVE',
      barTitle:    'POSTCORDS ARCHIVE',
      promptLines: [
        "POSTCORDS ARCHIVE — EVENTS THAT HAPPENED IN THE FUTURE.",
        "",
        "Hi, I’m 4RCH1V3R. I’ve made this archive from a collection of files that tend to appear in a computer I found dumped near where I live. It holds records that seem like they will have happened in the future, hence the name. I’m not entirely sure where they’re coming from yet since this operating system looks like it was custom-made. It’s taking me a while to fully understand it.",
        "",
        "Postcords tend to appear every now and then with interesting stories. You can read more about what they are or how I come about them in article 1, otherwise feel free to read any of the accounts I’ve found so far.",
        "",
        "Enjoy!",
        "4RCH1V3R"
      ],
      promptSelect: "SELECT A POSTCORD TO RETRIEVE.",
      descLabel:   '// record info',
      hintNav:     '↑ ↓  NAVIGATE',
      hintOpen:    'ENTER  OPEN',
      hintBack:    'ESC  BACK',
      storyBack:   'BACK TO ARCHIVE',
      storyHint:   'ESC — BACK TO ARCHIVE',
      storyHintSeries: 'ESC — BACK TO ARCHIVE     ← → — PREVIOUS / NEXT PART',
      seriesTag:   'SERIES',
      partsWord:   'PARTS',
      ptWord:      'PT',
      partWord:    'PART',
      partOf:      function(n, total) { return 'PART ' + n + ' OF ' + total; },
      retrieving:  '> RETRIEVING RECORD…',
      notFound:    '> ERROR: RECORD NOT FOUND.',
      noTranslation: function(href) {
        return '<p class="sys-msg">&gt; RECORD NOT AVAILABLE IN ENGLISH LANGUAGE. ' +
          '<a href="' + href + '">CLICK HERE</a> TO READ THE SPANISH VERSION.</p>';
      },
      projectInfo: 'POSTCORDS ARCHIVE'
    },
    es: {
      docTitle:    'POSTCORDS ARCHIVE',
      barTitle:    'POSTCORDS ARCHIVE',
      promptLines: [
        "ARCHIVO DE POSTCORDS — SUCESOS QUE OCURRIERON EN EL FUTURO.",
        "",
        "Hola, soy 4RCH1V3R. He creado este archivo a partir de una colección de ficheros que suelen aparecer en una computadora que encontré tirada cerca de donde vivo. Contiene registros que parecen haber ocurrido en el futuro, de ahí el nombre. Todavía no sé con certeza de dónde vienen, ya que este sistema operativo parece hecho a medida. Me está costando entenderlo del todo.",
        "",
        "Los postcords aparecen de vez en cuando con historias interesantes. Puedes leer más sobre qué son o cómo doy con ellos en el artículo 1; si no, siéntete libre de leer cualquiera de los relatos que he encontrado hasta ahora.",
        "",
        "¡Que lo disfrutes!",
        "4RCH1V3R"
      ],
      promptSelect: "SELECCIONA UN POSTCORD PARA RECUPERARLO.",
      descLabel:   '// info del registro',
      hintNav:     '↑ ↓  NAVEGAR',
      hintOpen:    'ENTER  ABRIR',
      hintBack:    'ESC  VOLVER',
      storyBack:   'VOLVER AL ARCHIVO',
      storyHintSeries: 'ESC — VOLVER AL ARCHIVO     ← → — PARTE ANTERIOR / SIGUIENTE',
      seriesTag:   'SERIE',
      partsWord:   'PARTES',
      ptWord:      'PT',
      partWord:    'PARTE',
      partOf:      function(n, total) { return 'PARTE ' + n + ' DE ' + total; },
      storyHint:   'ESC — VOLVER AL ARCHIVO',
      retrieving:  '> RECUPERANDO REGISTRO…',
      notFound:    '> ERROR: REGISTRO NO ENCONTRADO.',
      noTranslation: function(href) {
        return '<p class="sys-msg">&gt; REGISTRO NO DISPONIBLE EN ESPAÑOL. ' +
          '<a href="' + href + '">HAZ CLIC AQUÍ</a> PARA LEER LA VERSIÓN EN INGLÉS.</p>';
      },
      projectInfo: 'POSTCORDS ARCHIVE'
    }
  };
  var L = TXT[isEs ? 'es' : 'en'];

  document.title = L.docTitle;
  document.documentElement.lang = isEs ? 'es' : 'en';
  document.getElementById('bar-title').textContent      = L.barTitle;
  // Intro block: every line gets the "> " prompt prefix; an empty string is a
  // blank prompt line, which is how the paragraph breaks are written.
  var promptEl = document.getElementById('term-prompt');
  L.promptLines.forEach(function(line) {
    var row = document.createElement('div');
    if (line === '') {
      row.className = 'prompt-row is-spacer';
    } else {
      row.className = 'prompt-row';
      var pre = document.createElement('span');
      pre.className = 'cursor-prefix';
      pre.textContent = '>';
      var txt = document.createElement('span');
      txt.textContent = line;
      row.appendChild(pre);
      row.appendChild(txt);
    }
    promptEl.appendChild(row);
  });
  document.getElementById('prompt-select').textContent = L.promptSelect;
  document.getElementById('desc-label').textContent     = L.descLabel;
  document.getElementById('hint-nav').innerHTML          = L.hintNav;
  document.getElementById('hint-open').innerHTML         = L.hintOpen;
  document.getElementById('hint-back').innerHTML         = L.hintBack;
  document.getElementById('story-back-label').textContent = L.storyBack;
  document.getElementById('story-hint-label').textContent = L.storyHint;
  document.getElementById('story-loading').innerHTML      = '&gt; ' + L.retrieving.replace(/^>\s*/, '');

  var navLangLink = document.getElementById('nav-lang');
  navLangLink.href = otherLangHref;
  navLangLink.textContent = isEs ? 'EN' : 'ES';

  // ── Custom retro scrollbar ─────────────────────────────
  // Native ::-webkit-scrollbar styling doesn't exist in Firefox, so the
  // blocky retro look is drawn by hand here instead — a track+thumb pair
  // kept in sync with real scroll position/content size, with drag support.
  //
  // The track is appended to `shell` (a non-scrolling positioned wrapper
  // around `scrollEl`), NOT to `scrollEl` itself. An absolutely-positioned
  // element whose containing block is the scrolling element scrolls away
  // with the content instead of staying pinned like a real scrollbar — so
  // the positioning context has to be a separate ancestor that never
  // scrolls, while all measurements (scrollTop/scrollHeight/clientHeight)
  // still read from scrollEl.
  function mountRetroScrollbar(scrollEl, shell) {
    var track = document.createElement('div');
    track.className = 'retro-scrollbar-track';
    var thumb = document.createElement('div');
    thumb.className = 'retro-scrollbar-thumb';
    track.appendChild(thumb);
    shell.appendChild(track);

    function update() {
      var overflow = scrollEl.scrollHeight - scrollEl.clientHeight;
      if (overflow <= 1) {
        track.classList.remove('active');
        return;
      }
      track.classList.add('active');
      var trackHeight = scrollEl.clientHeight;
      var thumbHeight = Math.max(20, (scrollEl.clientHeight / scrollEl.scrollHeight) * trackHeight);
      var maxThumbTop = trackHeight - thumbHeight;
      var thumbTop = (scrollEl.scrollTop / overflow) * maxThumbTop;
      thumb.style.height = thumbHeight + 'px';
      thumb.style.top    = thumbTop + 'px';
    }

    var dragging = false, dragStartY = 0, dragStartScrollTop = 0;
    thumb.addEventListener('mousedown', function(e) {
      dragging = true;
      dragStartY = e.clientY;
      dragStartScrollTop = scrollEl.scrollTop;
      thumb.classList.add('dragging');
      e.preventDefault();
    });
    document.addEventListener('mousemove', function(e) {
      if (!dragging) return;
      var overflow = scrollEl.scrollHeight - scrollEl.clientHeight;
      var trackHeight = scrollEl.clientHeight;
      var thumbHeight = Math.max(20, (scrollEl.clientHeight / scrollEl.scrollHeight) * trackHeight);
      var maxThumbTop = trackHeight - thumbHeight;
      if (maxThumbTop <= 0) return;
      var deltaY = e.clientY - dragStartY;
      scrollEl.scrollTop = dragStartScrollTop + (deltaY / maxThumbTop) * overflow;
    });
    document.addEventListener('mouseup', function() {
      if (!dragging) return;
      dragging = false;
      thumb.classList.remove('dragging');
    });

    scrollEl.addEventListener('scroll', update);
    window.addEventListener('resize', update);
    new MutationObserver(update).observe(scrollEl, { childList: true, subtree: true, characterData: true });
    update();
  }

  mountRetroScrollbar(document.getElementById('term-list'), document.querySelector('.list-col-shell'));
  mountRetroScrollbar(document.getElementById('desc-col'),  document.querySelector('.desc-col-shell'));
  mountRetroScrollbar(document.getElementById('story-body'), document.querySelector('.story-body-shell'));
  mountRetroScrollbar(document.getElementById('term-prompt'), document.querySelector('.prompt-shell'));

  // Honour the line breaks the author actually typed: plain markdown folds a
  // single newline into the previous line, which isn't what the editor's own
  // preview shows.
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

  // ── Story registry ────────────────────────────────────
  // Emitted by the PHP at the top of this file, straight from the CMS, in
  // the project's admin-set order. A story whose 'es' block is omitted has
  // no Spanish translation yet — opening it while isEs is true shows a
  // "not available" notice instead of a broken fetch.
  var STORIES = <?php echo $storiesJson; ?>;

  // Series are directories in this archive: opening one swaps the list for
  // its parts. Each carries 'parts' as an ordered array of story slugs.
  var SERIES = <?php echo $seriesJson; ?>;

  var BY_SLUG = {};
  STORIES.forEach(function(s) { BY_SLUG[s.slug] = s; });
  var SERIES_BY_SLUG = {};
  SERIES.forEach(function(se) { se.isSeries = true; SERIES_BY_SLUG[se.slug] = se; });

  var HOME = {
    isHome: true,
    en: { title: 'home', desc: 'Return to the main site.' },
    es: { title: 'inicio', desc: 'Volver al sitio principal.' }
  };
  var LANG_TOGGLE = {
    isLang: true,
    en: { title: 'read in spanish', desc: 'Switch this terminal to Spanish.' },
    es: { title: 'leer en inglés',  desc: 'Cambiar esta terminal a inglés.' }
  };
  var SERIES_MENU = {
    isSeriesMenu: true,
    en: { title: 'series', desc: 'Records that run in sequence.' },
    es: { title: 'series', desc: 'Registros que van en secuencia.' }
  };
  var BACK_UP = {
    isBack: true,
    en: { title: 'back', desc: 'Go back.' },
    es: { title: 'volver', desc: 'Volver atrás.' }
  };

  // Root lists the stories only; series live behind the SERIES menu item,
  // which sits between the language toggle and home and is dropped entirely
  // when this project has no series.
  var ROOT = STORIES.concat(
    SERIES.length ? [LANG_TOGGLE, SERIES_MENU, HOME] : [LANG_TOGGLE, HOME]
  );
  var ALL  = ROOT;

  function loc(item) { return item[isEs ? 'es' : 'en']; }

  var curIdx  = 0;
  var inStory = false;
  var rows    = [];
  var curPrev = null;  // neighbouring parts of the story on screen, or null
  var curNext = null;

  var listEl  = document.getElementById('term-list');
  var descCol = document.getElementById('desc-col');
  var descTxt = document.getElementById('desc-text');
  var descSeries = document.getElementById('desc-series');

  // ── Build rows ────────────────────────────────────────
  // Called again whenever the listing changes (drilling into a series and
  // back out), so it clears whatever was there first.
  function buildRows(items) {
    ALL = items;
    listEl.innerHTML = '';
    rows = [];
    var n = 0;

    items.forEach(function(item, i) {
      var el = document.createElement('div');
      var utility = item.isHome || item.isLang || item.isBack || item.isSeriesMenu;
      el.className = 'term-entry'
        + (item.isHome   ? ' is-home'   : '')
        + (item.isLang   ? ' is-lang'   : '')
        + (item.isBack   ? ' is-back'   : '')
        + (item.isSeries ? ' is-series' : '')
        + (item.isSeriesMenu ? ' is-series-menu' : '');

      var num = utility ? '[--]' : '[' + String(++n).padStart(2, '0') + ']';
      var title = loc(item).title.toUpperCase();

      // A series row shows its length; a story that belongs to one is
      // flagged with its part number. Both ride the same accent tag.
      var tag = '';
      if (item.isSeries) {
        tag = '<span class="e-tag">' + L.seriesTag + ' - ' + item.parts.length + ' ' + L.partsWord + '</span>';
      } else if (item.series && SERIES_BY_SLUG[item.series]) {
        var partNo = SERIES_BY_SLUG[item.series].parts.indexOf(item.slug) + 1;
        if (partNo > 0) {
          tag = '<span class="e-tag">' + L.seriesTag + ' - ' + L.ptWord + ' ' + partNo + '</span>';
        }
      }

      el.innerHTML =
        '<span class="e-arrow">▶</span>' +
        '<span class="e-num">'   + num + '</span>' +
        '<span class="e-title">' + title + '</span>' + tag;

      el.addEventListener('mouseenter', function() { setFocus(i); });
      el.addEventListener('click',      function() { activate(i); });

      listEl.appendChild(el);
      rows.push(el);
    });
  }

  // Where BACK goes from the list currently on screen.
  var backTo = null;

  function openSeriesMenu() {
    backTo = showRoot;
    buildRows(SERIES.concat([BACK_UP]));
    setFocus(0);
    listEl.scrollTop = 0;
  }

  function openSeries(se) {
    backTo = openSeriesMenu;
    var parts = se.parts.map(function(slug) { return BY_SLUG[slug]; })
                        .filter(Boolean);
    buildRows(parts.concat([BACK_UP]));
    setFocus(0);
    listEl.scrollTop = 0;
  }

  function showRoot() {
    backTo = null;
    buildRows(ROOT);
    setFocus(0);
    listEl.scrollTop = 0;
  }

  // ── Focus ─────────────────────────────────────────────
  function setFocus(idx) {
    curIdx = idx;
    rows.forEach(function(el, i) {
      el.classList.toggle('focused', i === idx);
    });
    var item = ALL[idx];
    descTxt.textContent = loc(item).desc || '—';

    // Series provenance, called out under the blurb in the accent colour.
    var se = item.series ? SERIES_BY_SLUG[item.series] : null;
    if (se) {
      var i = se.parts.indexOf(item.slug);
      descSeries.textContent = loc(se).title.toUpperCase() + ' — ' + L.partOf(i + 1, se.parts.length);
    } else if (item.isSeries) {
      descSeries.textContent = L.seriesTag + ' — ' + item.parts.length + ' ' + L.partsWord;
    } else {
      descSeries.textContent = '';
    }
    descCol.classList.add('active');
  }

  // ── Activate ──────────────────────────────────────────
  function activate(idx) {
    rows[idx].classList.add('flash');
    setTimeout(function() {
      rows[idx].classList.remove('flash');
      var item = ALL[idx];
      if (item.isHome) {
        window.location.href = isEs ? '/es/' : '/';
      } else if (item.isLang) {
        window.location.href = otherLangHref;
      } else if (item.isSeriesMenu) {
        openSeriesMenu();
      } else if (item.isSeries) {
        openSeries(item);
      } else if (item.isBack) {
        (backTo || showRoot)();
      } else {
        openStory(item);
      }
    }, 140);
  }

  // ── Story view ────────────────────────────────────────
  function openStory(item) {
    inStory = true;
    document.getElementById('view-list').style.display  = 'none';
    document.getElementById('view-story').style.display = 'flex';

    document.getElementById('story-title').textContent = loc(item).title.toUpperCase();

    // Series context — taken from the story itself, not from how it was
    // reached, so a part opened straight off the root list still gets its
    // neighbours.
    var se  = item.series ? SERIES_BY_SLUG[item.series] : null;
    var idx = se ? se.parts.indexOf(item.slug) : -1;
    curPrev = (se && idx > 0) ? BY_SLUG[se.parts[idx - 1]] : null;
    curNext = (se && idx > -1 && idx < se.parts.length - 1) ? BY_SLUG[se.parts[idx + 1]] : null;

    var navEl   = document.getElementById('story-nav');
    var prevBtn = document.getElementById('story-prev');
    var nextBtn = document.getElementById('story-next');

    if (se && idx > -1) {
      document.getElementById('story-info').textContent =
        loc(se).title.toUpperCase() + ' · ' + L.partOf(idx + 1, se.parts.length);
      document.getElementById('story-part-label').textContent = L.partOf(idx + 1, se.parts.length);
      // A missing neighbour hides its button rather than showing a dead one.
      prevBtn.textContent   = curPrev ? '← ' + L.partWord + ' ' + String(idx).padStart(2, '0') : '';
      nextBtn.textContent   = curNext ? L.partWord + ' ' + String(idx + 2).padStart(2, '0') + ' →' : '';
      prevBtn.style.visibility = curPrev ? 'visible' : 'hidden';
      nextBtn.style.visibility = curNext ? 'visible' : 'hidden';
      navEl.style.display = 'flex';
      document.getElementById('story-hint-label').textContent = L.storyHintSeries;
    } else {
      document.getElementById('story-info').textContent = L.projectInfo;
      navEl.style.display = 'none';
      document.getElementById('story-hint-label').textContent = L.storyHint;
    }

    // story-body scrolls; story-body-content is the node whose innerHTML
    // gets replaced below — keeping them separate means the retro
    // scrollbar (mounted directly on story-body) never gets wiped out.
    var body        = document.getElementById('story-body');
    var bodyContent = document.getElementById('story-body-content');
    body.scrollTop = 0;

    // No translation for this postcord in the current language — show a
    // notice with a link to read it in the language it does exist in,
    // rather than silently falling back or fetching a missing file.
    if (isEs && !item.es) {
      bodyContent.innerHTML = TXT.es.noTranslation(otherLangHref);
      return;
    }
    if (!isEs && !item.en) {
      bodyContent.innerHTML = TXT.en.noTranslation(otherLangHref);
      return;
    }

    bodyContent.innerHTML = '<p class="sys-msg">&gt; ' + L.retrieving.replace(/^>\s*/, '') + '</p>';

    fetch(loc(item).file)
      .then(function(r) { return r.text(); })
      .then(function(t) {
        bodyContent.innerHTML = marked.parse(t);
        applyAlignment(bodyContent);
        decorateButtons(bodyContent);
      })
      .catch(function()  {
        bodyContent.innerHTML = '<p class="sys-msg">&gt; ' + L.notFound.replace(/^>\s*/, '') + '</p>';
      });
  }

  document.getElementById('story-prev').addEventListener('click', function() {
    if (curPrev) openStory(curPrev);
  });
  document.getElementById('story-next').addEventListener('click', function() {
    if (curNext) openStory(curNext);
  });

  function closeStory() {
    inStory = false;
    document.getElementById('view-story').style.display = 'none';
    document.getElementById('view-list').style.display  = 'flex';
    document.getElementById('story-body-content').innerHTML = '';
  }

  document.getElementById('story-back').addEventListener('click', closeStory);

  // ── Keyboard ──────────────────────────────────────────
  document.addEventListener('keydown', function(e) {
    if (inStory) {
      if (e.key === 'Escape') closeStory();
      else if (e.key === 'ArrowLeft'  && curPrev) { e.preventDefault(); openStory(curPrev); }
      else if (e.key === 'ArrowRight' && curNext) { e.preventDefault(); openStory(curNext); }
      return;
    }
    if (e.key === 'ArrowDown') {
      e.preventDefault();
      setFocus(Math.min(curIdx + 1, ALL.length - 1));
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      setFocus(Math.max(curIdx - 1, 0));
    } else if (e.key === 'Enter') {
      activate(curIdx);
    } else if (e.key === 'Escape') {
      // Nested one level in? Back out rather than leaving the archive.
      if (backTo) (backTo)();
      else window.location.href = isEs ? '/es/' : '/';
    }
  });

  showRoot();
</script>

</body>
</html>

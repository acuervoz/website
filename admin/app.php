<?php
require_once __DIR__ . '/config.php';

function currentSession(): ?array {
    $raw = $_COOKIE[COOKIE_NAME] ?? null;
    if (!$raw) return null;
    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
        $stmt = $pdo->prepare("SELECT * FROM admin_sessions WHERE token_hash = :h LIMIT 1");
        $stmt->execute([':h' => hash('sha256', $raw)]);
        $row = $stmt->fetch();
        if ($row) {
            $pdo->prepare("UPDATE admin_sessions SET last_seen = NOW() WHERE id = :id")->execute([':id' => $row['id']]);
            $expires = time() + (30 * 24 * 60 * 60);
            setcookie(COOKIE_NAME, $raw, [
                'expires' => $expires, 'path' => '/', 'domain' => COOKIE_DOMAIN,
                'secure' => true, 'httponly' => true, 'samesite' => 'Strict',
            ]);
        }
        return $row ?: null;
    } catch (Exception $e) {
        return null;
    }
}

$session = currentSession();
if (!$session) {
    header('Location: index.php');
    exit;
}

session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin — Content</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/easymde/dist/easymde.min.css">
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/easymde/dist/easymde.min.js"></script>
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
  --bg: #0a0a0a; --bg-surface: #111111; --bg-raised: #1a1a1a; --border: #2a2a2a;
  --accent: #FF6777; --accent-dim: #cc4455; --text: #f0f0f0; --text-muted: #666666;
  --text-dim: #444444; --success: #4caf84;
}

html, body { background: var(--bg); color: var(--text); font-family: 'JetBrains Mono', monospace; font-size: 13px; }

#nav {
  position: sticky; top: 0; z-index: 100; background: var(--bg); border-bottom: 1px solid var(--border);
  height: 44px; display: flex; align-items: center; padding: 0 20px; justify-content: space-between;
}
.nav-brand { color: var(--accent); font-weight: 700; letter-spacing: 0.15em; font-size: 12px; text-transform: uppercase; }
.nav-right { display: flex; gap: 8px; }
.nav-tabs { display: flex; gap: 2px; }
.nav-tab {
  background: none; border: 1px solid transparent; color: var(--text-muted);
  font-family: 'JetBrains Mono', monospace; font-size: 11px; letter-spacing: 0.12em;
  padding: 5px 14px; cursor: pointer;
}
.nav-tab:hover { color: var(--text); }
.nav-tab.active { color: var(--accent); border-color: var(--border); background: var(--bg-surface); }

.pane { display: block; padding: 24px 20px; max-width: 900px; margin: 0 auto; }

.btn {
  background: none; border: 1px solid var(--border); color: var(--text-muted);
  font-family: 'JetBrains Mono', monospace; font-size: 11px; letter-spacing: 0.08em; text-transform: uppercase;
  padding: 5px 12px; cursor: pointer; transition: border-color 120ms ease, color 120ms ease;
}
.btn:hover { border-color: var(--accent); color: var(--accent); }
.btn-accent { border-color: var(--accent); color: var(--accent); }
.btn-accent:hover { background: var(--accent); color: var(--bg); }
.btn-sm { padding: 3px 8px; font-size: 10px; }

.list-toolbar { display: flex; gap: 16px; margin-top: 16px; align-items: flex-end; }
.list-toolbar .modal-select { width: auto; min-width: 200px; }
.stories-table { width: 100%; border-collapse: collapse; font-size: 12px; margin-top: 16px; }
.stories-table th { text-align: left; color: var(--text-muted); font-size: 10px; letter-spacing: 0.1em; text-transform: uppercase; padding: 6px 8px; border-bottom: 1px solid var(--border); font-weight: 400; }
.stories-table td { padding: 8px; border-bottom: 1px solid var(--bg-raised); vertical-align: middle; }
.stories-table .title-cell { color: var(--text); }
.stories-table .muted { color: var(--text-dim); font-size: 11px; }
.fav-star { color: var(--accent); }
.redact-flag { color: var(--text-muted); letter-spacing: -1px; }
.modal-checkbox-row code { font-family: inherit; color: var(--text); font-size: 11px; }
.lang-badge { display:inline-block; padding:1px 5px; border:1px solid var(--border); font-size:9px; letter-spacing:0.05em; margin-right:3px; color:var(--text-muted); }
.row-actions { display: flex; gap: 4px; }

.empty-state { color: var(--text-dim); font-size: 12px; padding: 20px 8px; }

.modal-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,0.75); z-index: 200; display: flex; align-items: flex-start; justify-content: center; padding: 30px 20px; overflow-y: auto; }
.modal { background: var(--bg-surface); border: 1px solid var(--border); min-width: 360px; max-width: 640px; width: 100%; }
.modal-header { padding: 10px 16px; border-bottom: 1px solid var(--border); font-size: 11px; letter-spacing: 0.1em; text-transform: uppercase; color: var(--accent); font-weight: 700; display: flex; justify-content: space-between; }
.modal-body { padding: 20px 16px; }
.modal-footer { padding: 12px 16px; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: 8px; }
.modal-field { margin-bottom: 16px; }
.modal-row { display: flex; gap: 12px; }
.modal-row .modal-field { flex: 1; }
.modal-label { font-size: 11px; letter-spacing: 0.1em; text-transform: uppercase; color: var(--text-muted); margin-bottom: 6px; display: block; }
.modal-input, .modal-select, .modal-textarea {
  width: 100%; background: var(--bg-raised); border: 1px solid var(--border); color: var(--text);
  font-family: 'JetBrains Mono', monospace; font-size: 12px; padding: 7px 8px; outline: none;
  transition: border-color 120ms ease;
}
.modal-input:focus, .modal-select:focus, .modal-textarea:focus { border-color: var(--accent); }
.modal-textarea { resize: vertical; max-width: 100%; }
.modal-checkbox-row { display: flex; align-items: center; gap: 8px; font-size: 12px; color: var(--text-muted); }
.new-project-box { border: 1px solid var(--border); padding: 12px; background: var(--bg-raised); margin-top: 8px; }
.field-hint { color: var(--text-dim); font-size: 10px; margin-top: 5px; }
.part-tag { color: var(--text-dim); font-size: 10px; }

/* Series editor: the ordered list of parts */
.parts-list { border: 1px solid var(--border); background: var(--bg-raised); }
.part-row { display: flex; align-items: center; gap: 10px; padding: 6px 8px; border-bottom: 1px solid var(--bg-surface); }
.part-row:last-child { border-bottom: none; }
.part-num { color: var(--accent); font-size: 10px; letter-spacing: 0.1em; }
.part-title { flex: 1; font-size: 12px; }
.part-btns { display: flex; gap: 4px; }
.part-btns .btn[disabled] { opacity: 0.3; cursor: default; }
.part-btns .btn[disabled]:hover { border-color: var(--border); color: var(--text-muted); }
.add-part-row { display: flex; gap: 6px; align-items: center; margin-top: 8px; }
.add-part-row .modal-select { flex: 1; }
.editor-tabs { display: flex; gap: 2px; margin-bottom: 8px; }
.editor-tab { background: none; border: 1px solid var(--border); color: var(--text-muted); font-family: 'JetBrains Mono', monospace; font-size: 11px; text-transform: uppercase; letter-spacing: 0.06em; padding: 5px 12px; cursor: pointer; }
.editor-tab.active { color: var(--accent); border-color: var(--accent); }
.editor-pane { display: none; }
.editor-pane.active { display: block; }
.import-row { display: flex; justify-content: flex-end; margin-bottom: 6px; }

/* EasyMDE dark-theme override */
.EasyMDEContainer .CodeMirror { background: var(--bg-raised); color: var(--text); border: 1px solid var(--border); font-family: 'JetBrains Mono', monospace; font-size: 12.5px; }
.editor-toolbar { background: var(--bg-raised); border: 1px solid var(--border); border-bottom: none; opacity: 1; }
.editor-toolbar a { color: var(--text-muted) !important; }
.editor-toolbar a.active, .editor-toolbar a:hover { background: var(--bg-surface); border-color: var(--border); color: var(--accent) !important; }
.editor-toolbar i.separator { border-left: 1px solid var(--border); border-right: none; }
.CodeMirror-cursor { border-left-color: var(--text) !important; }
.editor-preview, .editor-preview-side { background: var(--bg-surface); color: var(--text); }

[x-cloak] { display: none !important; }
</style>
</head>
<body x-data="app()" x-init="init()">

<nav id="nav">
  <span class="nav-brand">░░ CONTENT ADMIN</span>
  <div class="nav-tabs">
    <button class="nav-tab" :class="{active: tab==='stories'}" @click="tab='stories'">STORIES</button>
    <button class="nav-tab" :class="{active: tab==='series'}" @click="tab='series'">SERIES</button>
    <button class="nav-tab" :class="{active: tab==='projects'}" @click="tab='projects'">PROJECTS</button>
  </div>
  <div class="nav-right">
    <button class="btn btn-accent" x-show="tab==='stories'" @click="openNewStory()">+ NEW STORY</button>
    <button class="btn btn-accent" x-show="tab==='series'" @click="openNewSeries()">+ NEW SERIES</button>
    <button class="btn" @click="logout()">LOGOUT</button>
  </div>
</nav>

<main class="pane" x-show="tab==='stories'">
  <template x-if="stories.length === 0">
    <div class="empty-state">· no stories yet — click + NEW STORY to add one</div>
  </template>

  <div class="list-toolbar" x-show="stories.length > 0">
    <div class="modal-field" style="margin-bottom:0">
      <label class="modal-label">Filter by project</label>
      <select class="modal-select" x-model="filterProjectId">
        <option value="">— all projects —</option>
        <template x-for="p in projects" :key="p.id">
          <option :value="p.id" x-text="p.title_en"></option>
        </template>
      </select>
    </div>
    <div class="modal-field" style="margin-bottom:0">
      <label class="modal-label">Sort by</label>
      <select class="modal-select" x-model="sortBy">
        <option value="date_desc">date (newest first)</option>
        <option value="date_asc">date (oldest first)</option>
        <option value="title_asc">title (A–Z)</option>
        <option value="title_desc">title (Z–A)</option>
        <option value="project_asc">project (A–Z)</option>
        <option value="project_desc">project (Z–A)</option>
      </select>
    </div>
  </div>

  <table class="stories-table" x-show="stories.length > 0">
    <thead>
      <tr><th>Title</th><th>Project</th><th>Series</th><th>Langs</th><th></th><th>Date</th><th></th></tr>
    </thead>
    <tbody>
      <template x-if="filteredStories().length === 0">
        <tr><td colspan="7" class="empty-state">· no stories match this filter</td></tr>
      </template>
      <template x-for="s in filteredStories()" :key="s.id">
        <tr>
          <td class="title-cell" x-text="s.title_en"></td>
          <td class="muted" x-text="s.project_title_en"></td>
          <td class="muted">
            <template x-if="s.series_title_en">
              <span>
                <span x-text="s.series_title_en"></span>
                <span class="part-tag" x-show="s.series_part" x-text="'· pt ' + s.series_part"></span>
              </span>
            </template>
            <template x-if="!s.series_title_en"><span class="muted">—</span></template>
          </td>
          <td>
            <span class="lang-badge">EN</span>
            <template x-if="s.title_es"><span class="lang-badge">ES</span></template>
          </td>
          <td>
            <span class="fav-star" x-show="Number(s.is_favourite)">★</span>
            <span class="redact-flag" x-show="Number(s.has_redacted)" title="[REDACTED] text is glitched">▓</span>
          </td>
          <td class="muted" x-text="s.created_at ? s.created_at.substring(0,10) : ''"></td>
          <td class="row-actions">
            <button class="btn btn-sm" @click="openEditStory(s)">EDIT</button>
            <button class="btn btn-sm" style="color:var(--accent);border-color:var(--accent)" @click="deleteStory(s.id)">DELETE</button>
          </td>
        </tr>
      </template>
    </tbody>
  </table>
</main>

<main class="pane" x-show="tab==='series'" x-cloak>
  <template x-if="series.length === 0">
    <div class="empty-state">· no series yet — click + NEW SERIES to group some stories into one</div>
  </template>

  <table class="stories-table" x-show="series.length > 0">
    <thead>
      <tr><th>Series</th><th>Project</th><th>Parts</th><th>Langs</th><th></th></tr>
    </thead>
    <tbody>
      <template x-for="se in series" :key="se.id">
        <tr>
          <td class="title-cell" x-text="se.title_en"></td>
          <td class="muted" x-text="se.project_title_en"></td>
          <td class="muted" x-text="se.story_count"></td>
          <td>
            <span class="lang-badge">EN</span>
            <template x-if="se.title_es"><span class="lang-badge">ES</span></template>
          </td>
          <td class="row-actions">
            <button class="btn btn-sm" @click="openEditSeries(se)">EDIT</button>
            <button class="btn btn-sm" style="color:var(--accent);border-color:var(--accent)" @click="deleteSeries(se.id)">DELETE</button>
          </td>
        </tr>
      </template>
    </tbody>
  </table>
</main>

<main class="pane" x-show="tab==='projects'" x-cloak>
  <table class="stories-table">
    <thead>
      <tr><th>Project</th><th>Type</th><th>Stories</th><th>Series</th><th>Langs</th><th></th></tr>
    </thead>
    <tbody>
      <template x-for="p in projects" :key="p.id">
        <tr>
          <td class="title-cell" x-text="p.title_en"></td>
          <td class="muted" x-text="p.type_en || '—'"></td>
          <td class="muted" x-text="p.story_count"></td>
          <td class="muted" x-text="p.series_count"></td>
          <td>
            <span class="lang-badge">EN</span>
            <template x-if="p.title_es"><span class="lang-badge">ES</span></template>
          </td>
          <td class="row-actions">
            <button class="btn btn-sm" @click="openEditProject(p)">EDIT</button>
          </td>
        </tr>
      </template>
    </tbody>
  </table>
</main>

<!-- Edit project modal -->
<div class="modal-backdrop" x-show="modal==='project'" x-cloak @click.self="closeModal()">
  <div class="modal">
    <div class="modal-header">
      <span>─ EDIT PROJECT ───────────────</span>
      <button class="btn btn-sm" @click="closeModal()">✕</button>
    </div>
    <div class="modal-body">

      <div class="modal-row">
        <div class="modal-field"><label class="modal-label">Title (EN)</label><input class="modal-input" x-model="projectForm.title_en"></div>
        <div class="modal-field"><label class="modal-label">Title (ES) — optional</label><input class="modal-input" x-model="projectForm.title_es"></div>
      </div>
      <div class="modal-row">
        <div class="modal-field"><label class="modal-label">Type (EN)</label>
          <select class="modal-select" x-model="projectForm.type_en">
            <option value="">— none —</option>
            <option value="fiction">fiction</option>
            <option value="non-fiction">non-fiction</option>
          </select>
        </div>
        <div class="modal-field"><label class="modal-label">Type (ES)</label>
          <select class="modal-select" x-model="projectForm.type_es">
            <option value="">— ninguno —</option>
            <option value="ficción">ficción</option>
            <option value="no ficción">no ficción</option>
          </select>
        </div>
      </div>
      <div class="modal-field">
        <label class="modal-label">Description (EN) — shown on the project page</label>
        <textarea class="modal-textarea" x-model="projectForm.desc_en" style="min-height:70px"></textarea>
      </div>
      <div class="modal-field">
        <label class="modal-label">Description (ES)</label>
        <textarea class="modal-textarea" x-model="projectForm.desc_es" style="min-height:70px"></textarea>
      </div>
      <div class="modal-row">
        <div class="modal-field"><label class="modal-label">Noun singular (EN)</label><input class="modal-input" x-model="projectForm.noun_singular_en" placeholder="story"></div>
        <div class="modal-field"><label class="modal-label">Noun plural (EN)</label><input class="modal-input" x-model="projectForm.noun_plural_en" placeholder="stories"></div>
      </div>
      <div class="modal-row">
        <div class="modal-field"><label class="modal-label">Noun singular (ES)</label><input class="modal-input" x-model="projectForm.noun_singular_es" placeholder="historia"></div>
        <div class="modal-field"><label class="modal-label">Noun plural (ES)</label><input class="modal-input" x-model="projectForm.noun_plural_es" placeholder="historias"></div>
      </div>

      <div class="modal-field">
        <label class="modal-label">Stories — in the order they appear on the page</label>
        <div class="parts-list">
          <template x-if="projectForm.stories.length === 0">
            <div class="empty-state" style="padding:8px">· nothing filed under this project yet</div>
          </template>
          <template x-for="(s, i) in projectForm.stories" :key="s.id">
            <div class="part-row">
              <span class="part-num" x-text="String(i + 1).padStart(2, '0')"></span>
              <span class="part-title" x-text="s.title_en"></span>
              <span class="part-btns">
                <button class="btn btn-sm" @click="moveProjectStory(i, -1)" :disabled="i === 0">↑</button>
                <button class="btn btn-sm" @click="moveProjectStory(i, 1)" :disabled="i === projectForm.stories.length - 1">↓</button>
              </span>
            </div>
          </template>
        </div>
        <div class="add-part-row">
          <select class="modal-select" x-model="addProjectStoryId">
            <option value="">— move a story here from another project —</option>
            <template x-for="s in storiesElsewhere()" :key="s.id">
              <option :value="s.id" x-text="s.title_en + '  (' + s.project_title_en + ')'"></option>
            </template>
          </select>
          <button class="btn btn-sm" @click="addProjectStory()">ADD</button>
        </div>
        <div class="field-hint">
          · every story lives in exactly one project, so adding one here moves it —
          its folder moves on disk and it leaves any series it was part of
        </div>
      </div>

      <div class="modal-field" x-show="projectForm.series.length">
        <label class="modal-label">Series in this project</label>
        <div class="parts-list">
          <template x-for="se in projectForm.series" :key="se.id">
            <div class="part-row">
              <span class="part-title" x-text="se.title_en"></span>
              <span class="part-btns"><button class="btn btn-sm" @click="closeModal(); tab='series'">OPEN SERIES TAB</button></span>
            </div>
          </template>
        </div>
        <div class="field-hint">· the order of a series' own parts is edited in the SERIES tab</div>
      </div>

    </div>
    <div class="modal-footer">
      <button class="btn" @click="closeModal()">CANCEL</button>
      <button class="btn btn-accent" @click="saveProject()">SAVE</button>
    </div>
  </div>
</div>

<!-- Add/Edit series modal -->
<div class="modal-backdrop" x-show="modal==='series'" x-cloak @click.self="closeModal()">
  <div class="modal">
    <div class="modal-header">
      <span x-text="seriesForm.id ? '─ EDIT SERIES ────────────────' : '─ NEW SERIES ─────────────────'"></span>
      <button class="btn btn-sm" @click="closeModal()">✕</button>
    </div>
    <div class="modal-body">

      <div class="modal-field">
        <label class="modal-label">Project:</label>
        <select class="modal-select" x-model="seriesForm.project_id" @change="seriesForm.stories = []">
          <option value="" disabled>choose a project...</option>
          <template x-for="p in addableProjects()" :key="p.id">
            <option :value="p.id" x-text="p.title_en"></option>
          </template>
        </select>
        <div class="field-hint" x-show="seriesForm.id && seriesForm.stories.length">
          · a series can't be moved to another project while it still has parts
        </div>
      </div>

      <div class="modal-row">
        <div class="modal-field"><label class="modal-label">Title (EN)</label><input class="modal-input" x-model="seriesForm.title_en"></div>
        <div class="modal-field"><label class="modal-label">Title (ES) — optional</label><input class="modal-input" x-model="seriesForm.title_es"></div>
      </div>
      <div class="modal-field">
        <label class="modal-label">Description (EN) — shown on the series page</label>
        <textarea class="modal-textarea" x-model="seriesForm.desc_en" style="min-height:70px"></textarea>
      </div>
      <div class="modal-field">
        <label class="modal-label">Description (ES)</label>
        <textarea class="modal-textarea" x-model="seriesForm.desc_es" style="min-height:70px"></textarea>
      </div>

      <div class="modal-field">
        <label class="modal-label">Parts — in reading order</label>
        <div class="parts-list">
          <template x-if="seriesForm.stories.length === 0">
            <div class="empty-state" style="padding:8px">· nothing in this series yet</div>
          </template>
          <template x-for="(s, i) in seriesForm.stories" :key="s.id">
            <div class="part-row">
              <span class="part-num" x-text="String(i + 1).padStart(2, '0')"></span>
              <span class="part-title" x-text="s.title_en"></span>
              <span class="part-btns">
                <button class="btn btn-sm" @click="movePart(i, -1)" :disabled="i === 0">↑</button>
                <button class="btn btn-sm" @click="movePart(i, 1)" :disabled="i === seriesForm.stories.length - 1">↓</button>
                <button class="btn btn-sm" @click="seriesForm.stories.splice(i, 1)" title="remove from series">✕</button>
              </span>
            </div>
          </template>
        </div>
        <div class="add-part-row" x-show="seriesForm.project_id">
          <select class="modal-select" x-model="addPartId">
            <option value="">— add a story to this series —</option>
            <template x-for="s in addableToSeries()" :key="s.id">
              <option :value="s.id" x-text="s.title_en"></option>
            </template>
          </select>
          <button class="btn btn-sm" @click="addPart()">ADD</button>
        </div>
      </div>

    </div>
    <div class="modal-footer">
      <button class="btn" @click="closeModal()">CANCEL</button>
      <button class="btn btn-accent" @click="saveSeries()">SAVE</button>
    </div>
  </div>
</div>

<!-- Add/Edit story modal -->
<div class="modal-backdrop" x-show="modal==='story'" x-cloak @click.self="closeModal()">
  <div class="modal">
    <div class="modal-header">
      <span x-text="editingId ? '─ EDIT STORY ─────────────────' : '─ NEW STORY ──────────────────'"></span>
      <button class="btn btn-sm" @click="closeModal()">✕</button>
    </div>
    <div class="modal-body">

      <div class="modal-field">
        <label class="modal-label">Project:</label>
        <template x-if="!showNewProjectInline">
          <div>
            <select class="modal-select" x-model="form.project_id" style="margin-bottom:6px"
                    @change="form.series_id = ''; form.series_part = ''">
              <option value="" disabled>choose a project...</option>
              <template x-for="p in addableProjects()" :key="p.id">
                <option :value="p.id" x-text="p.title_en"></option>
              </template>
            </select>
            <button class="btn btn-sm" @click="showNewProjectInline = true">+ NEW PROJECT</button>
          </div>
        </template>
        <template x-if="showNewProjectInline">
          <div class="new-project-box">
            <div class="modal-row">
              <div class="modal-field"><label class="modal-label">Title (EN)</label><input class="modal-input" x-model="newProject.title_en"></div>
              <div class="modal-field"><label class="modal-label">Title (ES)</label><input class="modal-input" x-model="newProject.title_es"></div>
            </div>
            <div class="modal-row">
              <div class="modal-field"><label class="modal-label">Type (EN)</label>
                <select class="modal-select" x-model="newProject.type_en">
                  <option value="">— none —</option>
                  <option value="fiction">fiction</option>
                  <option value="non-fiction">non-fiction</option>
                </select>
              </div>
              <div class="modal-field"><label class="modal-label">Type (ES)</label>
                <select class="modal-select" x-model="newProject.type_es">
                  <option value="">— ninguno —</option>
                  <option value="ficción">ficción</option>
                  <option value="no ficción">no ficción</option>
                </select>
              </div>
            </div>
            <div class="modal-field"><label class="modal-label">Description (EN)</label><textarea class="modal-textarea" x-model="newProject.desc_en" style="min-height:50px"></textarea></div>
            <div class="modal-field"><label class="modal-label">Description (ES)</label><textarea class="modal-textarea" x-model="newProject.desc_es" style="min-height:50px"></textarea></div>
            <div class="modal-row">
              <div class="modal-field"><label class="modal-label">Noun singular (EN)</label><input class="modal-input" x-model="newProject.noun_singular_en" placeholder="story"></div>
              <div class="modal-field"><label class="modal-label">Noun plural (EN)</label><input class="modal-input" x-model="newProject.noun_plural_en" placeholder="stories"></div>
            </div>
            <div class="modal-row">
              <div class="modal-field"><label class="modal-label">Noun singular (ES)</label><input class="modal-input" x-model="newProject.noun_singular_es" placeholder="historia"></div>
              <div class="modal-field"><label class="modal-label">Noun plural (ES)</label><input class="modal-input" x-model="newProject.noun_plural_es" placeholder="historias"></div>
            </div>
            <div style="display:flex;gap:6px">
              <button class="btn btn-sm" @click="showNewProjectInline=false">CANCEL</button>
              <button class="btn btn-accent btn-sm" @click="createProjectInline()">CREATE + SELECT</button>
            </div>
          </div>
        </template>
      </div>

      <div class="modal-field" x-show="form.project_id">
        <label class="modal-label">Series:</label>
        <template x-if="!showNewSeriesInline">
          <div>
            <div class="modal-row">
              <div class="modal-field" style="margin-bottom:6px">
                <select class="modal-select" x-model="form.series_id">
                  <option value="">— none —</option>
                  <template x-for="se in seriesForProject()" :key="se.id">
                    <option :value="se.id" x-text="se.title_en"></option>
                  </template>
                </select>
              </div>
              <div class="modal-field" style="margin-bottom:6px">
                <input class="modal-input" type="number" min="1" placeholder="part # — blank = by upload date"
                       x-model="form.series_part" :disabled="!form.series_id">
              </div>
            </div>
            <button class="btn btn-sm" @click="showNewSeriesInline = true">+ NEW SERIES</button>
          </div>
        </template>
        <template x-if="showNewSeriesInline">
          <div class="new-project-box">
            <div class="modal-row">
              <div class="modal-field"><label class="modal-label">Title (EN)</label><input class="modal-input" x-model="newSeries.title_en"></div>
              <div class="modal-field"><label class="modal-label">Title (ES)</label><input class="modal-input" x-model="newSeries.title_es"></div>
            </div>
            <div class="modal-field"><label class="modal-label">Description (EN)</label><textarea class="modal-textarea" x-model="newSeries.desc_en" style="min-height:50px"></textarea></div>
            <div class="modal-field"><label class="modal-label">Description (ES)</label><textarea class="modal-textarea" x-model="newSeries.desc_es" style="min-height:50px"></textarea></div>
            <div style="display:flex;gap:6px">
              <button class="btn btn-sm" @click="showNewSeriesInline=false">CANCEL</button>
              <button class="btn btn-accent btn-sm" @click="createSeriesInline()">CREATE + SELECT</button>
            </div>
          </div>
        </template>
      </div>

      <div class="modal-row">
        <div class="modal-field"><label class="modal-label">Title (EN)</label><input class="modal-input" x-model="form.title_en"></div>
        <div class="modal-field"><label class="modal-label">Title (ES) — optional</label><input class="modal-input" x-model="form.title_es"></div>
      </div>
      <div class="modal-row">
        <div class="modal-field"><label class="modal-label">Type (EN)</label>
          <select class="modal-select" x-model="form.type_en">
            <option value="">— none —</option>
            <option value="fiction">fiction</option>
            <option value="non-fiction">non-fiction</option>
          </select>
        </div>
        <div class="modal-field"><label class="modal-label">Type (ES)</label>
          <select class="modal-select" x-model="form.type_es">
            <option value="">— ninguno —</option>
            <option value="ficción">ficción</option>
            <option value="no ficción">no ficción</option>
          </select>
        </div>
      </div>
      <div class="modal-row">
        <div class="modal-field"><label class="modal-label">Blurb (EN)</label><textarea class="modal-textarea" x-model="form.desc_en" style="min-height:50px"></textarea></div>
        <div class="modal-field"><label class="modal-label">Blurb (ES)</label><textarea class="modal-textarea" x-model="form.desc_es" style="min-height:50px"></textarea></div>
      </div>
      <div class="modal-field modal-checkbox-row">
        <input type="checkbox" id="fav-check" x-model="form.is_favourite">
        <label for="fav-check">Show in homepage "Favourites"</label>
      </div>
      <div class="modal-field modal-checkbox-row">
        <input type="checkbox" id="redact-check" x-model="form.has_redacted">
        <label for="redact-check">Redact — render every <code>[REDACTED]</code> in the text glitched</label>
      </div>

      <div class="modal-field">
        <div class="editor-tabs">
          <button type="button" class="editor-tab" :class="{active: activeTab==='en'}" @click="activeTab='en'">English</button>
          <button type="button" class="editor-tab" :class="{active: activeTab==='es'}" @click="activeTab='es'">Spanish (optional)</button>
        </div>

        <div class="editor-pane" :class="{active: activeTab==='en'}">
          <div class="import-row"><button class="btn btn-sm" @click="importMd('en')">IMPORT .MD</button></div>
          <textarea id="mde-en"></textarea>
        </div>
        <div class="editor-pane" :class="{active: activeTab==='es'}">
          <div class="import-row"><button class="btn btn-sm" @click="importMd('es')">IMPORT .MD</button></div>
          <textarea id="mde-es"></textarea>
        </div>
      </div>

    </div>
    <div class="modal-footer">
      <button class="btn" @click="closeModal()">CANCEL</button>
      <button class="btn btn-accent" @click="saveStory()">SAVE</button>
    </div>
  </div>
</div>

<script>
function wrapLine(editor, marker) {
  const cm = editor.codemirror;
  const cursor = cm.getCursor();
  const lineText = cm.getLine(cursor.line);
  const stripped = lineText.replace(/^(R:|C:)\s*/, '');
  const newText = marker ? marker + ' ' + stripped : stripped;
  cm.replaceRange(newText, { line: cursor.line, ch: 0 }, { line: cursor.line, ch: lineText.length });
  cm.focus();
}

// Wrap the selection (or drop an empty pair at the cursor) in an inline
// marker — used for [[fake buttons]].
function wrapSelection(editor, before, after) {
  const cm = editor.codemirror;
  const sel = cm.getSelection();
  if (sel) {
    cm.replaceSelection(before + sel + after);
  } else {
    const cur = cm.getCursor();
    cm.replaceRange(before + after, cur);
    cm.setCursor({ line: cur.line, ch: cur.ch + before.length });
  }
  cm.focus();
}

function makeEditor(elId, initialValue) {
  const el = document.getElementById(elId);
  el.value = initialValue || '';
  return new EasyMDE({
    element: el,
    spellChecker: false,
    status: false,
    toolbar: [
      'bold', 'italic', 'heading-1', 'heading-2', '|',
      'unordered-list', 'ordered-list', 'quote', '|',
      { name: 'align-center', action: (ed) => wrapLine(ed, 'C:'), className: 'fa fa-align-center', title: 'Center this line' },
      { name: 'align-right',  action: (ed) => wrapLine(ed, 'R:'), className: 'fa fa-align-right',  title: 'Right-align this line' },
      { name: 'align-left',   action: (ed) => wrapLine(ed, null), className: 'fa fa-align-left',   title: 'Back to left align' },
      '|',
      { name: 'fake-button', action: (ed) => wrapSelection(ed, '[[', ']]'), className: 'fa fa-square', title: 'Render the selected text as a red button' },
      '|', 'link', 'preview', 'guide',
    ],
    // Match how the site renders: a single newline is a line break, not a
    // continuation of the previous line.
    renderingConfig: { singleLineBreaks: true },
  });
}

function app() {
  return {
    csrfToken: '',
    tab: 'stories',
    projects: [],
    stories: [],
    series: [],
    modal: null,
    editingId: null,
    activeTab: 'en',
    showNewProjectInline: false,
    showNewSeriesInline: false,
    filterProjectId: '',
    sortBy: 'date_desc',
    addPartId: '',
    addProjectStoryId: '',
    form: { project_id: '', series_id: '', series_part: '', title_en: '', title_es: '', type_en: '', type_es: '', desc_en: '', desc_es: '', has_redacted: false, is_favourite: false, body_en: '', body_es: '' },
    newProject: { title_en: '', title_es: '', type_en: '', type_es: '', desc_en: '', desc_es: '', noun_singular_en: '', noun_plural_en: '', noun_singular_es: '', noun_plural_es: '' },
    newSeries: { title_en: '', title_es: '', desc_en: '', desc_es: '' },
    seriesForm: { id: null, project_id: '', title_en: '', title_es: '', desc_en: '', desc_es: '', stories: [] },
    projectForm: { id: null, title_en: '', title_es: '', type_en: '', type_es: '', desc_en: '', desc_es: '',
                   noun_singular_en: '', noun_plural_en: '', noun_singular_es: '', noun_plural_es: '',
                   stories: [], series: [] },
    mdeEn: null,
    mdeEs: null,

    async init() {
      this.csrfToken = <?php echo json_encode($csrfToken); ?>;
      await this.loadAll();
    },

    async loadAll() {
      this.projects = await fetch('api.php?action=list_projects').then(r => r.json());
      this.stories  = await fetch('api.php?action=list_stories').then(r => r.json());
      this.series   = await fetch('api.php?action=list_series').then(r => r.json());
    },

    addableProjects() {
      return this.projects;
    },

    // Series never span projects, so only the current project's ones are offered.
    seriesForProject() {
      return this.series.filter(se => String(se.project_id) === String(this.form.project_id));
    },

    // Stories of the series' project that aren't spoken for: unassigned, or
    // already part of this series. Stealing one out of another series has to
    // be done from that series' own editor, so it's never a silent side effect.
    addableToSeries() {
      const taken = this.seriesForm.stories.map(s => String(s.id));
      return this.stories.filter(s =>
        String(s.project_id) === String(this.seriesForm.project_id) &&
        (!s.series_id || String(s.series_id) === String(this.seriesForm.id)) &&
        !taken.includes(String(s.id))
      );
    },

    filteredStories() {
      let list = this.stories;
      if (this.filterProjectId) {
        list = list.filter(s => String(s.project_id) === String(this.filterProjectId));
      }
      list = list.slice();
      list.sort((a, b) => {
        switch (this.sortBy) {
          case 'title_asc':   return a.title_en.localeCompare(b.title_en);
          case 'title_desc':  return b.title_en.localeCompare(a.title_en);
          case 'date_asc':    return new Date(a.created_at) - new Date(b.created_at);
          case 'date_desc':   return new Date(b.created_at) - new Date(a.created_at);
          case 'project_asc': return a.project_title_en.localeCompare(b.project_title_en);
          case 'project_desc':return b.project_title_en.localeCompare(a.project_title_en);
          default: return 0;
        }
      });
      return list;
    },

    jsonHeaders() {
      return { 'Content-Type': 'application/json', 'X-CSRF-Token': this.csrfToken };
    },

    openNewStory() {
      this.editingId = null;
      this.activeTab = 'en';
      this.showNewProjectInline = false;
      this.showNewSeriesInline = false;
      this.form = { project_id: '', series_id: '', series_part: '', title_en: '', title_es: '', type_en: '', type_es: '', desc_en: '', desc_es: '', has_redacted: false, is_favourite: false, body_en: '', body_es: '' };
      this.modal = 'story';
      this.$nextTick(() => this.initEditors());
    },

    async openEditStory(story) {
      const full = await fetch('api.php?action=get_story&id=' + story.id).then(r => r.json());
      this.editingId = story.id;
      this.activeTab = 'en';
      this.showNewProjectInline = false;
      this.showNewSeriesInline = false;
      this.form = {
        project_id: story.project_id,
        series_id: full.series_id || '',
        series_part: full.series_part || '',
        title_en: full.title_en, title_es: full.title_es || '',
        type_en: full.type_en || '', type_es: full.type_es || '',
        desc_en: full.desc_en || '', desc_es: full.desc_es || '',
        has_redacted: !!Number(full.has_redacted),
        is_favourite: !!Number(full.is_favourite),
        body_en: full.body_en || '', body_es: full.body_es || '',
      };
      this.modal = 'story';
      this.$nextTick(() => this.initEditors());
    },

    initEditors() {
      this.mdeEn = makeEditor('mde-en', this.form.body_en);
      this.mdeEs = makeEditor('mde-es', this.form.body_es);
    },

    destroyEditors() {
      if (this.mdeEn) { this.mdeEn.toTextArea(); this.mdeEn = null; }
      if (this.mdeEs) { this.mdeEs.toTextArea(); this.mdeEs = null; }
    },

    closeModal() {
      this.destroyEditors();
      this.modal = null;
    },

    importMd(which) {
      const input = document.createElement('input');
      input.type = 'file';
      input.accept = '.md,.txt';
      input.onchange = async () => {
        const file = input.files[0];
        if (!file) return;
        const text = await file.text();
        const editor = which === 'en' ? this.mdeEn : this.mdeEs;
        editor.value(text);
      };
      input.click();
    },

    // ── Projects ──────────────────────────────────────────────────────────

    async openEditProject(p) {
      const full = await fetch('api.php?action=get_project&id=' + p.id).then(r => r.json());
      this.projectForm = {
        id: full.id,
        title_en: full.title_en, title_es: full.title_es || '',
        type_en: full.type_en || '', type_es: full.type_es || '',
        desc_en: full.desc_en || '', desc_es: full.desc_es || '',
        noun_singular_en: full.noun_singular_en || '', noun_plural_en: full.noun_plural_en || '',
        noun_singular_es: full.noun_singular_es || '', noun_plural_es: full.noun_plural_es || '',
        stories: full.stories.map(s => ({ id: s.id, title_en: s.title_en })),
        series: full.series || [],
      };
      this.addProjectStoryId = '';
      this.modal = 'project';
    },

    storiesElsewhere() {
      const here = this.projectForm.stories.map(s => String(s.id));
      return this.stories.filter(s => !here.includes(String(s.id)));
    },

    moveProjectStory(i, delta) {
      const list = this.projectForm.stories;
      const j = i + delta;
      if (j < 0 || j >= list.length) return;
      [list[i], list[j]] = [list[j], list[i]];
    },

    addProjectStory() {
      if (!this.addProjectStoryId) return;
      const story = this.stories.find(s => String(s.id) === String(this.addProjectStoryId));
      if (story) {
        if (!confirm('Move "' + story.title_en + '" out of ' + story.project_title_en + ' and into this project?')) return;
        this.projectForm.stories.push({ id: story.id, title_en: story.title_en });
      }
      this.addProjectStoryId = '';
    },

    async saveProject() {
      if (!this.projectForm.title_en.trim()) { alert('title (EN) is required'); return; }
      const res = await fetch('api.php?action=update_project', {
        method: 'POST', headers: this.jsonHeaders(),
        body: JSON.stringify({
          ...this.projectForm,
          story_ids: this.projectForm.stories.map(s => s.id),
          csrf_token: this.csrfToken,
        }),
      });
      const result = await res.json();
      if (!res.ok) { alert(result.error || 'save failed'); return; }
      this.closeModal();
      await this.loadAll();
    },

    // ── Series ────────────────────────────────────────────────────────────

    openNewSeries() {
      this.seriesForm = { id: null, project_id: '', title_en: '', title_es: '', desc_en: '', desc_es: '', stories: [] };
      this.addPartId = '';
      this.modal = 'series';
    },

    async openEditSeries(se) {
      const full = await fetch('api.php?action=get_series&id=' + se.id).then(r => r.json());
      this.seriesForm = {
        id: full.id,
        project_id: full.project_id,
        title_en: full.title_en, title_es: full.title_es || '',
        desc_en: full.desc_en || '', desc_es: full.desc_es || '',
        stories: full.stories.map(s => ({ id: s.id, title_en: s.title_en })),
      };
      this.addPartId = '';
      this.modal = 'series';
    },

    movePart(i, delta) {
      const list = this.seriesForm.stories;
      const j = i + delta;
      if (j < 0 || j >= list.length) return;
      [list[i], list[j]] = [list[j], list[i]];
    },

    addPart() {
      if (!this.addPartId) return;
      const story = this.stories.find(s => String(s.id) === String(this.addPartId));
      if (story) this.seriesForm.stories.push({ id: story.id, title_en: story.title_en });
      this.addPartId = '';
    },

    async saveSeries() {
      if (!this.seriesForm.project_id) { alert('choose a project first'); return; }
      if (!this.seriesForm.title_en.trim()) { alert('title (EN) is required'); return; }

      // A new series is created first, then handed its parts through the same
      // ordering call the editor uses for an existing one.
      let id = this.seriesForm.id;
      if (!id) {
        const res = await fetch('api.php?action=create_series', {
          method: 'POST', headers: this.jsonHeaders(),
          body: JSON.stringify({ ...this.seriesForm, csrf_token: this.csrfToken }),
        });
        const result = await res.json();
        if (!res.ok) { alert(result.error || 'failed to create series'); return; }
        id = result.id;
      }

      const res = await fetch('api.php?action=update_series', {
        method: 'POST', headers: this.jsonHeaders(),
        body: JSON.stringify({
          ...this.seriesForm, id,
          story_ids: this.seriesForm.stories.map(s => s.id),
          csrf_token: this.csrfToken,
        }),
      });
      const result = await res.json();
      if (!res.ok) { alert(result.error || 'save failed'); return; }
      this.closeModal();
      await this.loadAll();
    },

    async deleteSeries(id) {
      if (!confirm('Delete this series? Its stories are kept — they just stop being part of it.')) return;
      const res = await fetch('api.php?action=delete_series', {
        method: 'POST', headers: this.jsonHeaders(), body: JSON.stringify({ id, csrf_token: this.csrfToken }),
      });
      if (res.ok) await this.loadAll();
      else { const r = await res.json(); alert(r.error || 'delete failed'); }
    },

    async createSeriesInline() {
      if (!this.newSeries.title_en.trim()) { alert('the series needs an English title'); return; }
      const res = await fetch('api.php?action=create_series', {
        method: 'POST', headers: this.jsonHeaders(),
        body: JSON.stringify({ ...this.newSeries, project_id: this.form.project_id, csrf_token: this.csrfToken }),
      });
      const result = await res.json();
      if (!res.ok) { alert(result.error || 'failed to create series'); return; }
      await this.loadAll();
      this.form.series_id = result.id;
      this.newSeries = { title_en: '', title_es: '', desc_en: '', desc_es: '' };
      this.showNewSeriesInline = false;
    },

    async createProjectInline() {
      const res = await fetch('api.php?action=create_project', {
        method: 'POST', headers: this.jsonHeaders(),
        body: JSON.stringify({ ...this.newProject, csrf_token: this.csrfToken }),
      });
      const result = await res.json();
      if (!res.ok) { alert(result.error || 'failed to create project'); return; }
      await this.loadAll();
      const created = this.projects.find(p => p.slug === result.slug);
      if (created) this.form.project_id = created.id;
      this.showNewProjectInline = false;
    },

    async saveStory() {
      if (!this.form.project_id) { alert('choose a project first'); return; }
      if (!this.form.title_en.trim()) { alert('title (EN) is required'); return; }
      const bodyEn = this.mdeEn.value();
      if (!bodyEn.trim()) { alert('the English story body is empty'); return; }

      const payload = { ...this.form, body_en: bodyEn, body_es: this.mdeEs.value(), csrf_token: this.csrfToken };
      const action = this.editingId ? 'update_story' : 'create_story';
      if (this.editingId) payload.id = this.editingId;

      const res = await fetch('api.php?action=' + action, { method: 'POST', headers: this.jsonHeaders(), body: JSON.stringify(payload) });
      const result = await res.json();
      if (!res.ok) { alert(result.error || 'save failed'); return; }
      this.closeModal();
      await this.loadAll();
    },

    async deleteStory(id) {
      if (!confirm('Delete this story permanently? This removes its files from disk too.')) return;
      const res = await fetch('api.php?action=delete_story', {
        method: 'POST', headers: this.jsonHeaders(), body: JSON.stringify({ id, csrf_token: this.csrfToken }),
      });
      if (res.ok) await this.loadAll();
      else { const r = await res.json(); alert(r.error || 'delete failed'); }
    },

    async logout() {
      await fetch('api.php?action=logout', { method: 'POST', headers: this.jsonHeaders(), body: JSON.stringify({ csrf_token: this.csrfToken }) });
      window.location.href = 'index.php';
    },
  };
}
</script>
</body>
</html>

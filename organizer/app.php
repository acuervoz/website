<?php
require_once __DIR__ . '/config.php';

session_start();

function getDeviceFromCookie(): ?array {
    $raw = $_COOKIE[COOKIE_NAME] ?? null;
    if (!$raw) return null;
    $hash = hash('sha256', $raw);
    try {
        $pdo  = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
        $stmt = $pdo->prepare("SELECT * FROM device_tokens WHERE token_hash=:h LIMIT 1");
        $stmt->execute([':h' => $hash]);
        $row = $stmt->fetch();
        if ($row) {
            $pdo->prepare("UPDATE device_tokens SET last_seen=NOW() WHERE id=:id")
                ->execute([':id' => $row['id']]);
            $expires = time() + (30 * 24 * 60 * 60);
            setcookie(COOKIE_NAME, $raw, [
                'expires'  => $expires,
                'path'     => '/',
                'domain'   => COOKIE_DOMAIN,
                'secure'   => true,
                'httponly' => true,
                'samesite' => 'Strict',
            ]);
        }
        return $row ?: null;
    } catch (Exception $e) {
        return null;
    }
}

$device = getDeviceFromCookie();
if (!$device) {
    header('Location: index.php');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Organizer</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
  --bg:         #0a0a0a;
  --bg-surface: #111111;
  --bg-raised:  #1a1a1a;
  --border:     #2a2a2a;
  --accent:     #FF6777;
  --accent-dim: #cc4455;
  --text:       #f0f0f0;
  --text-muted: #666666;
  --text-dim:   #444444;
  --success:    #4caf84;
  --warning:    #f0b429;
}

html, body {
  height: 100%;
  background: var(--bg);
  color: var(--text);
  font-family: 'JetBrains Mono', monospace;
  font-size: 13px;
}

/* ── Retro terminal scrollbars ──────────────────────────────────────────── */
::-webkit-scrollbar { width: 10px; height: 10px; }
::-webkit-scrollbar-track {
  background: var(--bg);
  border-left: 1px solid var(--border);
}
::-webkit-scrollbar-thumb {
  background: repeating-linear-gradient(
    to bottom,
    var(--text-dim)  0px,
    var(--text-dim)  2px,
    transparent      2px,
    transparent      5px
  );
  border-left: 1px solid var(--border);
  border-right: 1px solid var(--border);
}
::-webkit-scrollbar-thumb:hover {
  background: repeating-linear-gradient(
    to bottom,
    var(--text-muted) 0px,
    var(--text-muted) 2px,
    transparent       2px,
    transparent       5px
  );
}
::-webkit-scrollbar-corner { background: var(--bg); }
::-webkit-scrollbar-button { display: none; }

/* ── Nav ────────────────────────────────────────────────────────────────── */
#nav {
  position: fixed;
  top: 0; left: 0; right: 0;
  z-index: 100;
  background: var(--bg);
  border-bottom: 1px solid var(--border);
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 20px;
  height: 44px;
}
.nav-inner {
  display: flex;
  align-items: center;
  justify-content: space-between;
  width: 100%;
  max-width: 60%;
  margin: 0 auto;
}
@media (max-width: 1300px) { .nav-inner { max-width: 85%; } }
@media (max-width: 900px)  { .nav-inner { max-width: 100%; } }

.nav-brand {
  color: var(--accent);
  font-weight: 700;
  letter-spacing: 0.15em;
  font-size: 12px;
  text-transform: uppercase;
  margin-right: 24px;
  flex-shrink: 0;
}
.nav-links {
  display: flex;
  align-items: center;
  gap: 2px;
  flex: 1;
  flex-wrap: wrap;
}
.nav-btn {
  background: none;
  border: none;
  color: var(--text-muted);
  font-family: 'JetBrains Mono', monospace;
  font-size: 11px;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  cursor: pointer;
  padding: 6px 10px;
  position: relative;
  transition: color 120ms ease;
  white-space: nowrap;
}
.nav-btn:hover { color: var(--text); }
.nav-btn.active { color: var(--accent); }
.nav-btn.active::after {
  content: '';
  position: absolute;
  bottom: 0; left: 10px; right: 10px;
  height: 1px;
  background: var(--accent);
}
.nav-right {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-shrink: 0;
}
.nav-icon-btn {
  background: none;
  border: 1px solid var(--border);
  color: var(--text-muted);
  font-family: 'JetBrains Mono', monospace;
  font-size: 11px;
  padding: 4px 10px;
  cursor: pointer;
  letter-spacing: 0.08em;
  transition: border-color 120ms ease, color 120ms ease;
}
.nav-icon-btn:hover { border-color: var(--accent); color: var(--accent); }

/* ── Layout ─────────────────────────────────────────────────────────────── */
#main {
  margin-top: 44px;
  padding: 24px 20px;
  min-height: calc(100vh - 44px);
}
.content-wrap {
  max-width: 60%;
  margin: 0 auto;
  width: 100%;
}
@media (max-width: 1300px) { .content-wrap { max-width: 85%; } }
@media (max-width: 900px)  { .content-wrap { max-width: 100%; } }
@media (max-width: 600px)  {
  #main { padding: 16px 10px; }
  .nav-brand { display: none; }
}

/* ── Section headers (priority view) ────────────────────────────────────── */
.section-header {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 6px;
  cursor: pointer;
  user-select: none;
  color: var(--text-muted);
  font-size: 11px;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  border-bottom: 1px solid var(--border);
  margin-bottom: 2px;
  transition: background 120ms ease, color 120ms ease;
  border-radius: 0;
}
.section-header.drag-target {
  color: var(--accent);
  background: rgba(255,103,119,0.06);
  border-bottom-color: var(--accent);
}
.section-header .pri-icon { font-size: 13px; }
.pri-asap  { color: var(--accent); }
.pri-soon  { color: var(--text); }
.pri-back  { color: var(--text-muted); }
.section-header .count   { color: var(--text-dim); font-size: 11px; }
.section-header .toggle  { margin-left: auto; color: var(--text-dim); }

/* ── Task rows ──────────────────────────────────────────────────────────── */
.task-row {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 6px 8px;
  border-left: 2px solid transparent;
  border-top: 2px solid transparent;
  transition: background 120ms ease, border-color 120ms ease;
  position: relative;
  cursor: default;
}
.task-row[draggable="true"] { cursor: grab; }
.task-row[draggable="true"]:active { cursor: grabbing; }
.task-row:hover {
  background: var(--bg-raised);
  border-left-color: var(--accent);
}
.task-row.is-dragging { opacity: 0.3; }
.task-row.drag-insert-before { border-top-color: var(--accent) !important; }
.task-row.subtask { padding-left: 24px; }
.subtask-prefix { color: var(--text-dim); flex-shrink: 0; font-size: 11px; }
.drag-handle {
  color: var(--text-dim);
  cursor: grab;
  flex-shrink: 0;
  font-size: 11px;
  opacity: 0;
  transition: opacity 120ms ease;
  user-select: none;
}
.task-row:hover .drag-handle { opacity: 1; }
.project-tag {
  display: inline-block;
  padding: 1px 6px;
  font-size: 10px;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  border: 1px solid;
  flex-shrink: 0;
  white-space: nowrap;
}
.task-title {
  flex: 1;
  color: var(--text);
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.task-actions {
  display: flex;
  align-items: center;
  gap: 4px;
  flex-shrink: 0;
  opacity: 0;
  transition: opacity 120ms ease;
}
.task-row:hover .task-actions { opacity: 1; }
.task-btn {
  background: none;
  border: 1px solid var(--border);
  color: var(--text-muted);
  font-family: 'JetBrains Mono', monospace;
  font-size: 11px;
  padding: 2px 6px;
  cursor: pointer;
  transition: border-color 120ms ease, color 120ms ease;
}
.task-btn:hover { border-color: var(--accent); color: var(--accent); }
.task-btn.success:hover { border-color: var(--success); color: var(--success); }

/* ── Inline edit ─────────────────────────────────────────────────────────── */
.inline-edit-form {
  display: flex;
  align-items: center;
  gap: 6px;
  flex: 1;
  min-width: 0;
}
.inline-edit-form input,
.inline-edit-form select {
  background: var(--bg-raised);
  border: 1px solid var(--accent);
  color: var(--text);
  font-family: 'JetBrains Mono', monospace;
  font-size: 12px;
  padding: 3px 6px;
  outline: none;
}
.inline-edit-form input  { flex: 1; min-width: 0; }
.inline-edit-form select { font-size: 11px; }

/* ── Dropdown menu ──────────────────────────────────────────────────────── */
.dropdown-wrap { position: relative; }
.dropdown-menu {
  position: absolute;
  right: 0; top: 100%;
  z-index: 50;
  background: var(--bg-surface);
  border: 1px solid var(--border);
  min-width: 160px;
  padding: 4px 0;
}
.dropdown-item {
  display: block;
  width: 100%;
  background: none;
  border: none;
  color: var(--text);
  font-family: 'JetBrains Mono', monospace;
  font-size: 11px;
  padding: 6px 12px;
  text-align: left;
  cursor: pointer;
  letter-spacing: 0.06em;
  transition: background 120ms ease, color 120ms ease;
}
.dropdown-item:hover { background: var(--bg-raised); color: var(--accent); }

/* ── Priority view ──────────────────────────────────────────────────────── */
.priority-section { margin-bottom: 24px; }

/* ── Dashboard ──────────────────────────────────────────────────────────── */
.dashboard-scroll {
  display: flex;
  gap: 16px;
  overflow-x: auto;
  padding-bottom: 16px;
  align-items: flex-start;
}
.project-card {
  border: 1px solid var(--border);
  min-width: 260px;
  max-width: 280px;
  flex-shrink: 0;
  background: var(--bg-surface);
  display: flex;
  flex-direction: column;
  transition: border-color 120ms ease, opacity 120ms ease;
}
.project-card[draggable="true"] { cursor: grab; }
.project-card[draggable="true"]:active { cursor: grabbing; }
.project-card.is-dragging { opacity: 0.3; }
.project-card.drag-target { border-color: var(--accent); }
.project-card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 8px 12px;
  border-bottom: 1px solid var(--border);
}
.project-card-title {
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  flex: 1;
}
.project-card-body {
  padding: 8px 0;
  flex: 1;
  overflow-y: auto;
  max-height: 420px;
}
.card-section-header {
  padding: 4px 12px;
  font-size: 10px;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  color: var(--text-muted);
  display: flex;
  align-items: center;
  gap: 6px;
}
.card-task-row {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 4px 12px;
  border-left: 2px solid transparent;
  transition: background 120ms ease, border-color 120ms ease;
}
.card-task-row:hover { background: var(--bg-raised); border-left-color: var(--accent); }
.card-task-row .task-title { font-size: 12px; }
.card-task-row .task-actions { opacity: 0; }
.card-task-row:hover .task-actions { opacity: 1; }
.card-task-row.subtask { padding-left: 24px; }
.project-card-footer { border-top: 1px solid var(--border); padding: 8px 12px; }
.add-task-form { display: flex; gap: 6px; flex-direction: column; }
.add-task-form input,
.add-task-form select {
  background: var(--bg-raised);
  border: 1px solid var(--border);
  color: var(--text);
  font-family: 'JetBrains Mono', monospace;
  font-size: 12px;
  padding: 5px 8px;
  outline: none;
  width: 100%;
  transition: border-color 120ms ease;
}
.add-task-form input:focus,
.add-task-form select:focus { border-color: var(--accent); }
.add-task-row { display: flex; gap: 6px; }
.add-task-row select { width: auto; flex-shrink: 0; }

.btn {
  background: none;
  border: 1px solid var(--border);
  color: var(--text-muted);
  font-family: 'JetBrains Mono', monospace;
  font-size: 11px;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  padding: 5px 12px;
  cursor: pointer;
  transition: border-color 120ms ease, color 120ms ease;
}
.btn:hover { border-color: var(--accent); color: var(--accent); }
.btn-accent { border-color: var(--accent); color: var(--accent); }
.btn-accent:hover { background: var(--accent); color: var(--bg); }
.btn-sm { padding: 3px 8px; font-size: 10px; }
.btn-text {
  background: none;
  border: none;
  color: var(--text-muted);
  font-family: 'JetBrains Mono', monospace;
  font-size: 11px;
  cursor: pointer;
  padding: 4px 0;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  transition: color 120ms ease;
}
.btn-text:hover { color: var(--accent); }
.new-project-card {
  border: 1px dashed var(--border);
  min-width: 180px;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
  cursor: pointer;
  transition: border-color 120ms ease;
  background: transparent;
  flex-shrink: 0;
}
.new-project-card:hover { border-color: var(--accent); }
.new-project-label {
  color: var(--text-muted);
  font-size: 11px;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  transition: color 120ms ease;
}
.new-project-card:hover .new-project-label { color: var(--accent); }

/* ── Archive view ───────────────────────────────────────────────────────── */
.archive-header {
  font-size: 11px;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  color: var(--text-muted);
  margin-bottom: 16px;
  padding-bottom: 8px;
  border-bottom: 1px solid var(--border);
}

/* ── Completed view ─────────────────────────────────────────────────────── */
.completed-header {
  font-size: 11px;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  color: var(--text-muted);
  margin-bottom: 16px;
  padding-bottom: 8px;
  border-bottom: 1px solid var(--border);
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.completed-row {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 8px 8px;
  border-left: 2px solid transparent;
  border-top: 2px solid transparent;
  transition: background 120ms ease, border-color 120ms ease;
}
.completed-row[draggable="true"] { cursor: grab; }
.completed-row[draggable="true"]:active { cursor: grabbing; }
.completed-row:hover { background: var(--bg-raised); border-left-color: var(--text-dim); }
.completed-row.is-dragging { opacity: 0.3; }
.completed-row.drag-insert-before { border-top-color: var(--accent) !important; }
.completed-meta {
  font-size: 10px;
  color: var(--text-dim);
  white-space: nowrap;
  padding-top: 2px;
  flex-shrink: 0;
  width: 110px;
}
.completed-body { flex: 1; min-width: 0; }
.completed-title {
  color: var(--text-muted);
  font-size: 12px;
  text-decoration: line-through;
  text-decoration-color: var(--text-dim);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.completed-note {
  color: var(--text-dim);
  font-size: 11px;
  margin-top: 3px;
}
.completed-note::before { content: '└─ '; }
.completed-actions { flex-shrink: 0; opacity: 0; transition: opacity 120ms ease; }
.completed-row:hover .completed-actions { opacity: 1; }

/* ── Modals ─────────────────────────────────────────────────────────────── */
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.75);
  z-index: 200;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
}
.modal {
  background: var(--bg-surface);
  border: 1px solid var(--border);
  min-width: 360px;
  max-width: 540px;
  width: 100%;
  max-height: 80vh;
  display: flex;
  flex-direction: column;
}
@media (max-width: 600px) { .modal { min-width: 0; } }
.modal-header {
  padding: 10px 16px;
  border-bottom: 1px solid var(--border);
  font-size: 11px;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  color: var(--accent);
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.modal-body { padding: 20px 16px; overflow-y: auto; flex: 1; }
.modal-footer {
  padding: 12px 16px;
  border-top: 1px solid var(--border);
  display: flex;
  justify-content: flex-end;
  gap: 8px;
}
.modal-task-title {
  color: var(--text);
  font-size: 13px;
  margin-bottom: 16px;
  padding: 8px;
  background: var(--bg-raised);
  border-left: 2px solid var(--accent);
}
.modal-label {
  font-size: 11px;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  color: var(--text-muted);
  margin-bottom: 6px;
  display: block;
}
.modal-textarea {
  width: 100%;
  background: var(--bg-raised);
  border: 1px solid var(--border);
  color: var(--text);
  font-family: 'JetBrains Mono', monospace;
  font-size: 12px;
  padding: 8px;
  resize: vertical;
  min-height: 80px;
  outline: none;
  transition: border-color 120ms ease;
}
.modal-textarea:focus { border-color: var(--accent); }
.modal-input {
  width: 100%;
  background: var(--bg-raised);
  border: 1px solid var(--border);
  color: var(--text);
  font-family: 'JetBrains Mono', monospace;
  font-size: 12px;
  padding: 7px 8px;
  outline: none;
  transition: border-color 120ms ease;
}
.modal-input:focus { border-color: var(--accent); }

/* ── Devices panel ──────────────────────────────────────────────────────── */
.devices-table { width: 100%; border-collapse: collapse; font-size: 12px; }
.devices-table th {
  text-align: left;
  color: var(--text-muted);
  font-size: 10px;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  padding: 4px 8px;
  border-bottom: 1px solid var(--border);
  font-weight: 400;
}
.devices-table td { padding: 8px 8px; border-bottom: 1px solid var(--bg-raised); vertical-align: middle; }
.current-dot {
  display: inline-block;
  width: 6px; height: 6px;
  border-radius: 50%;
  background: var(--success);
  margin-right: 4px;
  vertical-align: middle;
}

/* ── History log ────────────────────────────────────────────────────────── */
.history-entry { padding: 8px 0; border-bottom: 1px solid var(--bg-raised); }
.history-meta {
  font-size: 11px;
  color: var(--text-muted);
  margin-bottom: 4px;
  display: flex;
  align-items: center;
  gap: 8px;
}
.history-action-completed { color: var(--success); }
.history-action-reopened  { color: var(--warning); }
.history-action-updated   { color: var(--text-muted); }
.history-note { color: var(--text-dim); font-size: 11px; padding-left: 16px; }
.history-note::before { content: '└─ '; color: var(--text-dim); }

/* ── Colour picker ──────────────────────────────────────────────────────── */
.colour-swatches { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 8px; }
.colour-swatch {
  width: 24px; height: 24px;
  border: 2px solid transparent;
  cursor: pointer;
  transition: border-color 120ms ease;
}
.colour-swatch.selected, .colour-swatch:hover { border-color: var(--text); }
.colour-custom { display: flex; align-items: center; gap: 8px; margin-top: 8px; }
.colour-custom input[type=color] {
  width: 32px; height: 24px;
  border: 1px solid var(--border);
  background: none;
  cursor: pointer;
  padding: 0;
}

/* ── Toast notifications ────────────────────────────────────────────────── */
#toast-area {
  position: fixed;
  bottom: 20px; right: 20px;
  z-index: 300;
  display: flex;
  flex-direction: column;
  gap: 8px;
  pointer-events: none;
}
.toast {
  background: var(--bg-surface);
  border: 1px solid var(--border);
  padding: 10px 16px;
  font-size: 12px;
  max-width: 300px;
  animation: toastIn 200ms ease;
}
.toast.success { border-left: 3px solid var(--success); }
.toast.error   { border-left: 3px solid var(--accent); }
.toast.info    { border-left: 3px solid var(--text-muted); }
@keyframes toastIn {
  from { opacity: 0; transform: translateY(8px); }
  to   { opacity: 1; transform: translateY(0); }
}

/* ── Loading bar ────────────────────────────────────────────────────────── */
.loading-bar {
  position: fixed;
  top: 44px; left: 0; right: 0;
  height: 2px;
  background: var(--accent);
  z-index: 150;
  animation: loadPulse 1s ease infinite;
}
@keyframes loadPulse {
  0%,100% { opacity: 0.4; }
  50%      { opacity: 1; }
}

/* ── Misc ───────────────────────────────────────────────────────────────── */
.empty-state { color: var(--text-dim); font-size: 12px; padding: 12px 8px; letter-spacing: 0.06em; }
*:focus-visible { outline: 1px solid var(--accent); outline-offset: 1px; }
button:focus    { outline: none; }
[x-cloak]       { display: none !important; }
</style>
</head>
<body x-data="app()" x-init="init()">

<div class="loading-bar" x-show="loading" x-cloak></div>

<!-- ── Nav ──────────────────────────────────────────────────────────────── -->
<nav id="nav">
  <div class="nav-inner">
    <div style="display:flex;align-items:center;flex:1;min-width:0">
      <span class="nav-brand">░░ ORGANIZER</span>
      <div class="nav-links">
        <button class="nav-btn" :class="{active:view==='priority'}"   @click="switchView('priority')">PRIORITY</button>
        <button class="nav-btn" :class="{active:view==='dashboard'}"  @click="switchView('dashboard')">DASHBOARD</button>
        <button class="nav-btn" :class="{active:view==='archive'}"    @click="switchView('archive')">ARCHIVE</button>
        <button class="nav-btn" :class="{active:view==='completed'}"  @click="switchView('completed')">COMPLETED</button>
      </div>
    </div>
    <div class="nav-right">
      <button class="nav-icon-btn" @click="openDevices()">⚙ DEVICES</button>
    </div>
  </div>
</nav>

<!-- ── Main ─────────────────────────────────────────────────────────────── -->
<main id="main">
<div class="content-wrap">

  <!-- ── PRIORITY VIEW ──────────────────────────────────────────────────── -->
  <div x-show="view==='priority'" x-cloak>
    <template x-for="pri in ['ASAP','Soon','Backlog']" :key="pri">
      <div class="priority-section"
           @dragover.prevent="onSectionDragOver($event, pri)"
           @dragleave="onSectionDragLeave($event, pri)"
           @drop.prevent="onSectionDrop($event, pri)">

        <!-- Section header — drop zone to change priority -->
        <div class="section-header"
             :class="{'drag-target': dragOverPri===pri && drag.priority!==pri}"
             @click="toggleSection(pri)">
          <span class="pri-icon" :class="{'pri-asap':pri==='ASAP','pri-soon':pri==='Soon','pri-back':pri==='Backlog'}"
            x-text="pri==='ASAP'?'!!':pri==='Soon'?'◈':'·'"></span>
          <span x-text="pri"></span>
          <span class="count" x-text="'('+countPriTasks(pri)+')'"></span>
          <span class="toggle" x-text="collapsedSections[pri] ? '▸' : '▾'"></span>
        </div>

        <div x-show="!collapsedSections[pri]">
          <template x-if="countPriTasks(pri)===0">
            <div class="empty-state">· drop tasks here or add via dashboard</div>
          </template>
          <template x-for="task in priorityTasks[pri]" :key="task.id">
            <div>
              <!-- Main task row -->
              <div class="task-row"
                   draggable="true"
                   :data-id="task.id"
                   :class="{'is-dragging': drag.id===task.id, 'drag-insert-before': dragOverId===task.id && drag.priority===pri}"
                   @dragstart="onTaskDragStart($event, task)"
                   @dragend="onDragEnd()"
                   @dragover.prevent.stop="onTaskDragOver($event, task)"
                   @drop.prevent.stop="onTaskDrop($event, task)">
                <span class="drag-handle">⠿</span>
                <span class="project-tag"
                  :style="'color:'+task.project_colour+';border-color:'+task.project_colour+'40'"
                  x-text="task.project_name"></span>
                <template x-if="editingTask && editingTask.id===task.id">
                  <div class="inline-edit-form" @click.stop>
                    <input type="text" x-model="editingTask.title"
                      @keydown.enter="saveEdit()" @keydown.escape="editingTask=null"
                      @blur="saveEditBlur()" x-ref="editInput" x-init="$nextTick(()=>$el.focus())">
                    <select x-model="editingTask.priority">
                      <option value="ASAP">!! ASAP</option>
                      <option value="Soon">◈ Soon</option>
                      <option value="Backlog">· Backlog</option>
                    </select>
                    <select x-model="editingTask.project_id">
                      <template x-for="p in projects" :key="p.id">
                        <option :value="p.id" x-text="p.name"></option>
                      </template>
                    </select>
                  </div>
                </template>
                <template x-if="!editingTask || editingTask.id!==task.id">
                  <span class="task-title" x-text="task.title"></span>
                </template>
                <div class="task-actions">
                  <button class="task-btn success" @click.stop="openCompleteModal(task)" title="Complete">✓</button>
                  <button class="task-btn" @click.stop="startEdit(task)" title="Edit">✎</button>
                  <div class="dropdown-wrap" @click.outside="closeDropdown(task.id)">
                    <button class="task-btn" @click.stop="toggleDropdown(task.id)">⋮</button>
                    <div class="dropdown-menu" x-show="openDropdownId===task.id" x-cloak @click.stop>
                      <button class="dropdown-item" @click="openHistory(task.id); closeDropdown(task.id)">View History</button>
                      <template x-for="p in projects.filter(p=>p.id!=task.project_id)" :key="p.id">
                        <button class="dropdown-item"
                          @click="moveTask(task.id, p.id); closeDropdown(task.id)"
                          x-text="'→ '+p.name"></button>
                      </template>
                      <button class="dropdown-item" @click="deleteTask(task.id); closeDropdown(task.id)">Delete</button>
                    </div>
                  </div>
                </div>
              </div>
              <!-- Subtasks -->
              <template x-for="sub in task.subtasks||[]" :key="sub.id">
                <div class="task-row subtask"
                     draggable="true"
                     :class="{'is-dragging': drag.id===sub.id}"
                     @dragstart="onTaskDragStart($event, sub)"
                     @dragend="onDragEnd()">
                  <span class="subtask-prefix">└─</span>
                  <template x-if="editingTask && editingTask.id===sub.id">
                    <div class="inline-edit-form" @click.stop>
                      <input type="text" x-model="editingTask.title"
                        @keydown.enter="saveEdit()" @keydown.escape="editingTask=null"
                        @blur="saveEditBlur()" x-init="$nextTick(()=>$el.focus())">
                      <select x-model="editingTask.priority">
                        <option value="ASAP">!! ASAP</option>
                        <option value="Soon">◈ Soon</option>
                        <option value="Backlog">· Backlog</option>
                      </select>
                    </div>
                  </template>
                  <template x-if="!editingTask || editingTask.id!==sub.id">
                    <span class="task-title" x-text="sub.title"></span>
                  </template>
                  <div class="task-actions">
                    <button class="task-btn success" @click.stop="openCompleteModal(sub)">✓</button>
                    <button class="task-btn" @click.stop="startEdit(sub)">✎</button>
                    <div class="dropdown-wrap" @click.outside="closeDropdown(sub.id)">
                      <button class="task-btn" @click.stop="toggleDropdown(sub.id)">⋮</button>
                      <div class="dropdown-menu" x-show="openDropdownId===sub.id" x-cloak @click.stop>
                        <button class="dropdown-item" @click="openHistory(sub.id); closeDropdown(sub.id)">View History</button>
                        <button class="dropdown-item" @click="deleteTask(sub.id); closeDropdown(sub.id)">Delete</button>
                      </div>
                    </div>
                  </div>
                </div>
              </template>
            </div>
          </template>
        </div>
      </div>
    </template>
  </div>

  <!-- ── DASHBOARD VIEW ─────────────────────────────────────────────────── -->
  <div x-show="view==='dashboard'" x-cloak>
    <div class="dashboard-scroll">
      <template x-for="project in projects" :key="project.id">
        <div class="project-card"
             draggable="true"
             :class="{'is-dragging': drag.id===project.id && drag.type==='project', 'drag-target': dragOverId==='proj_'+project.id}"
             @dragstart.self="onProjDragStart($event, project)"
             @dragend="onDragEnd()"
             @dragover.prevent="onProjDragOver($event, project)"
             @drop.prevent="onProjDrop($event, project)">
          <div class="project-card-header" style="border-left:3px solid" :style="'border-left-color:'+project.colour">
            <span class="project-card-title" x-text="project.name"></span>
            <div class="dropdown-wrap" @click.outside="closeDropdown('proj_'+project.id)" @click.stop>
              <button class="task-btn" @click.stop="toggleDropdown('proj_'+project.id)">⋮</button>
              <div class="dropdown-menu" x-show="openDropdownId==='proj_'+project.id" x-cloak @click.stop>
                <button class="dropdown-item" @click="openRenameProject(project); closeDropdown('proj_'+project.id)">Rename</button>
                <button class="dropdown-item" @click="archiveProject(project.id); closeDropdown('proj_'+project.id)">Archive</button>
                <button class="dropdown-item" @click="deleteProject(project.id); closeDropdown('proj_'+project.id)">Delete</button>
              </div>
            </div>
          </div>
          <div class="project-card-body">
            <template x-for="pri in ['ASAP','Soon','Backlog']" :key="pri">
              <template x-if="getProjectTasks(project.id, pri).length > 0">
                <div>
                  <div class="card-section-header">
                    <span :class="{'pri-asap':pri==='ASAP','pri-soon':pri==='Soon','pri-back':pri==='Backlog'}"
                      x-text="pri==='ASAP'?'!!':pri==='Soon'?'◈':'·'"></span>
                    <span x-text="getProjectTasks(project.id,pri).length+' '+pri"></span>
                  </div>
                  <template x-for="task in getProjectTasks(project.id, pri)" :key="task.id">
                    <div>
                      <div class="card-task-row">
                        <span class="task-title" x-text="task.title"></span>
                        <div class="task-actions">
                          <button class="task-btn success btn-sm" @click="openCompleteModal(task)">✓</button>
                          <button class="task-btn btn-sm" @click="startEdit(task)">✎</button>
                        </div>
                      </div>
                      <template x-for="sub in task.subtasks||[]" :key="sub.id">
                        <div class="card-task-row subtask">
                          <span class="subtask-prefix">└─</span>
                          <span class="task-title" x-text="sub.title"></span>
                          <div class="task-actions">
                            <button class="task-btn success btn-sm" @click="openCompleteModal(sub)">✓</button>
                          </div>
                        </div>
                      </template>
                    </div>
                  </template>
                </div>
              </template>
            </template>
            <template x-if="getProjectAllTasks(project.id).length===0">
              <div class="empty-state" style="padding:8px 12px">· no active tasks</div>
            </template>
          </div>
          <div class="project-card-footer">
            <template x-if="addingTaskTo!==project.id">
              <button class="btn-text" @click.stop="startAddTask(project.id)">+ ADD TASK</button>
            </template>
            <template x-if="addingTaskTo===project.id">
              <div class="add-task-form" @click.stop>
                <input type="text" x-model="newTaskTitle" placeholder="task title..."
                  @keydown.enter="submitAddTask(project.id)"
                  @keydown.escape="addingTaskTo=null; newTaskTitle=''"
                  x-init="$nextTick(()=>$el.focus())">
                <div class="add-task-row">
                  <select x-model="newTaskPriority">
                    <option value="ASAP">!! ASAP</option>
                    <option value="Soon">◈ Soon</option>
                    <option value="Backlog">· Backlog</option>
                  </select>
                  <button class="btn btn-accent btn-sm" @click="submitAddTask(project.id)">ADD</button>
                  <button class="btn btn-sm" @click="addingTaskTo=null; newTaskTitle=''">✕</button>
                </div>
              </div>
            </template>
          </div>
        </div>
      </template>
      <button class="new-project-card" @click="openNewProjectModal()">
        <span class="new-project-label">+ NEW PROJECT</span>
      </button>
    </div>
  </div>

  <!-- ── ARCHIVE VIEW ────────────────────────────────────────────────────── -->
  <div x-show="view==='archive'" x-cloak>
    <p class="archive-header">── ARCHIVED PROJECTS ──</p>
    <div class="dashboard-scroll">
      <template x-if="archivedProjects.length===0">
        <div class="empty-state">· no archived projects</div>
      </template>
      <template x-for="project in archivedProjects" :key="project.id">
        <div class="project-card" style="opacity:0.7">
          <div class="project-card-header" style="border-left:3px solid" :style="'border-left-color:'+project.colour">
            <span class="project-card-title" x-text="project.name"></span>
            <div style="display:flex;gap:4px">
              <button class="btn btn-sm" @click="archiveProject(project.id)">UNARCHIVE</button>
              <button class="btn btn-sm" style="color:var(--accent);border-color:var(--accent)"
                @click="deleteProject(project.id)">DELETE</button>
            </div>
          </div>
          <div class="project-card-body">
            <div class="empty-state" style="padding:8px 12px">
              <span x-text="project.task_count+' task(s) archived'"></span>
            </div>
          </div>
        </div>
      </template>
    </div>
  </div>

  <!-- ── COMPLETED VIEW ─────────────────────────────────────────────────── -->
  <div x-show="view==='completed'" x-cloak>
    <div class="completed-header">
      <span>── COMPLETED TASKS ── <span style="color:var(--text-dim)" x-text="completedTasks.length ? '('+completedTasks.length+')' : ''"></span></span>
      <span style="color:var(--text-dim);font-size:10px">drag to reorder</span>
    </div>
    <template x-if="completedTasks.length===0">
      <div class="empty-state">· no completed tasks yet</div>
    </template>
    <template x-for="task in completedTasks" :key="task.id">
      <div class="completed-row"
           draggable="true"
           :class="{'is-dragging': drag.id===task.id && drag.type==='completed', 'drag-insert-before': dragOverId==='comp_'+task.id}"
           @dragstart="onCompletedDragStart($event, task)"
           @dragend="onDragEnd()"
           @dragover.prevent="onCompletedDragOver($event, task)"
           @drop.prevent="onCompletedDrop($event, task)">
        <div class="completed-meta" x-text="formatShortDate(task.completed_at)"></div>
        <div class="completed-body">
          <div class="completed-title" x-text="task.title"></div>
          <div style="display:flex;align-items:center;gap:6px;margin-top:3px">
            <span class="project-tag"
              :style="'color:'+task.project_colour+';border-color:'+task.project_colour+'40;font-size:9px'"
              x-text="task.project_name"></span>
            <span style="color:var(--text-dim);font-size:10px"
              x-text="task.priority==='ASAP'?'!! '+task.priority : task.priority==='Soon'?'◈ '+task.priority:'· '+task.priority"></span>
          </div>
          <template x-if="task.completion_note">
            <div class="completed-note" x-text="task.completion_note"></div>
          </template>
        </div>
        <div class="completed-actions">
          <button class="task-btn btn-sm" @click="reopenTask(task.id)" title="Reopen">↺</button>
          <button class="task-btn btn-sm" @click="deleteTask(task.id)" title="Delete">✕</button>
        </div>
      </div>
    </template>
  </div>

</div><!-- .content-wrap -->
</main>

<!-- ── MODALS ────────────────────────────────────────────────────────────── -->

<!-- Complete task -->
<div class="modal-backdrop" x-show="modal==='complete'" x-cloak @click.self="modal=null">
  <div class="modal">
    <div class="modal-header">
      <span>─ COMPLETE TASK ─────────────────</span>
      <button class="task-btn" @click="modal=null">✕</button>
    </div>
    <div class="modal-body">
      <div class="modal-task-title" x-text="modalTask ? '&quot;'+modalTask.title+'&quot;' : ''"></div>
      <label class="modal-label">Add a note (optional):</label>
      <textarea class="modal-textarea" x-model="completeNote"
        @keydown.ctrl.enter="confirmComplete()"
        placeholder="optional completion note..."></textarea>
    </div>
    <div class="modal-footer">
      <button class="btn" @click="modal=null">CANCEL</button>
      <button class="btn btn-accent" @click="confirmComplete()">✓ MARK COMPLETE</button>
    </div>
  </div>
</div>

<!-- Task history -->
<div class="modal-backdrop" x-show="modal==='history'" x-cloak @click.self="modal=null">
  <div class="modal">
    <div class="modal-header">
      <span>─ TASK HISTORY ──────────────────</span>
      <button class="task-btn" @click="modal=null">✕</button>
    </div>
    <div class="modal-body">
      <div class="modal-task-title" x-text="historyTask ? historyTask.title : ''"></div>
      <template x-if="historyLog.length===0">
        <div class="empty-state">· no history yet</div>
      </template>
      <template x-for="entry in historyLog" :key="entry.id">
        <div class="history-entry">
          <div class="history-meta">
            <span x-text="formatDate(entry.logged_at)"></span>
            <span :class="'history-action-'+entry.action"
              x-text="entry.action==='completed'?'✓ completed':entry.action==='reopened'?'↺ reopened':'✎ updated'"></span>
          </div>
          <template x-if="entry.note">
            <div class="history-note" x-text="entry.note"></div>
          </template>
        </div>
      </template>
    </div>
    <div class="modal-footer">
      <button class="btn" @click="modal=null">✕ CLOSE</button>
    </div>
  </div>
</div>

<!-- New project -->
<div class="modal-backdrop" x-show="modal==='new-project'" x-cloak @click.self="modal=null">
  <div class="modal">
    <div class="modal-header">
      <span>─ NEW PROJECT ───────────────────</span>
      <button class="task-btn" @click="modal=null">✕</button>
    </div>
    <div class="modal-body">
      <label class="modal-label">Project name:</label>
      <input class="modal-input" type="text" x-model="newProjectName"
        @keydown.enter="submitNewProject()" placeholder="project name..."
        style="margin-bottom:16px" x-init="$nextTick(()=>$el.focus())">
      <label class="modal-label">Colour:</label>
      <div class="colour-swatches">
        <template x-for="c in colourSwatches" :key="c">
          <div class="colour-swatch" :class="{selected:newProjectColour===c}"
            :style="'background:'+c" @click="newProjectColour=c"></div>
        </template>
      </div>
      <div class="colour-custom">
        <span style="color:var(--text-muted);font-size:11px">custom:</span>
        <input type="color" x-model="newProjectColour">
        <span style="color:var(--text-muted);font-size:11px" x-text="newProjectColour"></span>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn" @click="modal=null">CANCEL</button>
      <button class="btn btn-accent" @click="submitNewProject()">CREATE PROJECT</button>
    </div>
  </div>
</div>

<!-- Rename project -->
<div class="modal-backdrop" x-show="modal==='rename-project'" x-cloak @click.self="modal=null">
  <div class="modal">
    <div class="modal-header">
      <span>─ RENAME PROJECT ────────────────</span>
      <button class="task-btn" @click="modal=null">✕</button>
    </div>
    <div class="modal-body">
      <label class="modal-label">Name:</label>
      <input class="modal-input" type="text" x-model="editProjectName"
        @keydown.enter="submitRenameProject()" style="margin-bottom:16px"
        x-init="$nextTick(()=>$el.focus())">
      <label class="modal-label">Colour:</label>
      <div class="colour-swatches">
        <template x-for="c in colourSwatches" :key="c">
          <div class="colour-swatch" :class="{selected:editProjectColour===c}"
            :style="'background:'+c" @click="editProjectColour=c"></div>
        </template>
      </div>
      <div class="colour-custom">
        <span style="color:var(--text-muted);font-size:11px">custom:</span>
        <input type="color" x-model="editProjectColour">
        <span style="color:var(--text-muted);font-size:11px" x-text="editProjectColour"></span>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn" @click="modal=null">CANCEL</button>
      <button class="btn btn-accent" @click="submitRenameProject()">SAVE</button>
    </div>
  </div>
</div>

<!-- Devices -->
<div class="modal-backdrop" x-show="modal==='devices'" x-cloak @click.self="modal=null">
  <div class="modal" style="max-width:620px">
    <div class="modal-header">
      <span>─ REGISTERED DEVICES ────────────</span>
      <button class="task-btn" @click="modal=null">✕</button>
    </div>
    <div class="modal-body">
      <table class="devices-table">
        <thead>
          <tr>
            <th>ID</th><th>LABEL</th><th>LAST SEEN</th><th>REGISTERED</th><th></th>
          </tr>
        </thead>
        <tbody>
          <template x-for="dev in devices" :key="dev.id">
            <tr>
              <td style="color:var(--text-muted)" x-text="String(dev.id).padStart(2,'0')"></td>
              <td>
                <template x-if="dev.is_current">
                  <span><span class="current-dot"></span><span x-text="dev.device_label"></span>
                  <span style="color:var(--text-dim);font-size:10px"> (this device)</span></span>
                </template>
                <template x-if="!dev.is_current">
                  <span x-text="dev.device_label"></span>
                </template>
              </td>
              <td style="color:var(--text-muted);font-size:11px" x-text="relativeTime(dev.last_seen)"></td>
              <td style="color:var(--text-dim);font-size:11px" x-text="dev.created_at.substring(0,10)"></td>
              <td>
                <div style="display:flex;gap:4px">
                  <button class="btn btn-sm" @click="promptLabelDevice(dev)">LABEL</button>
                  <template x-if="!dev.is_current">
                    <button class="btn btn-sm" style="color:var(--accent);border-color:var(--accent)"
                      @click="revokeDevice(dev.id)">REVOKE</button>
                  </template>
                </div>
              </td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>
    <div class="modal-footer">
      <button class="btn" @click="modal=null">✕ CLOSE</button>
      <button class="btn" style="color:var(--accent);border-color:var(--accent)" @click="logout()">LOGOUT</button>
    </div>
  </div>
</div>

<!-- Label device -->
<div class="modal-backdrop" x-show="modal==='label-device'" x-cloak @click.self="modal=null">
  <div class="modal" style="min-width:0;max-width:360px">
    <div class="modal-header">
      <span>─ LABEL DEVICE ──────────────────</span>
      <button class="task-btn" @click="modal=null">✕</button>
    </div>
    <div class="modal-body">
      <label class="modal-label">Device label:</label>
      <input class="modal-input" type="text" x-model="labelDeviceValue"
        @keydown.enter="submitLabelDevice()" placeholder="e.g. MacBook Pro"
        x-init="$nextTick(()=>$el.focus())">
    </div>
    <div class="modal-footer">
      <button class="btn" @click="modal=null">CANCEL</button>
      <button class="btn btn-accent" @click="submitLabelDevice()">SAVE LABEL</button>
    </div>
  </div>
</div>

<!-- Toast area -->
<div id="toast-area">
  <template x-for="t in toasts" :key="t.id">
    <div class="toast" :class="t.type" x-text="t.message"></div>
  </template>
</div>

<script>
const CSRF = <?= json_encode($csrfToken) ?>;

function app() {
  return {
    view: 'priority',
    projects: [],
    archivedProjects: [],
    projectTasksCache: {},
    priorityTasks: { ASAP: [], Soon: [], Backlog: [] },
    completedTasks: [],
    loading: false,
    modal: null,
    modalTask: null,
    completeNote: '',
    historyTask: null,
    historyLog: [],
    editingTask: null,
    editSaving: false,
    openDropdownId: null,
    addingTaskTo: null,
    newTaskTitle: '',
    newTaskPriority: 'Soon',
    newProjectName: '',
    newProjectColour: '#FF6777',
    editProjectId: null,
    editProjectName: '',
    editProjectColour: '#FF6777',
    devices: [],
    labelDeviceId: null,
    labelDeviceValue: '',
    collapsedSections: { ASAP: false, Soon: false, Backlog: false },
    toasts: [],
    colourSwatches: [
      '#FF6777','#cc4455','#f0b429','#4caf84','#5b8def',
      '#a78bfa','#f97316','#22d3ee','#e879f9','#f0f0f0'
    ],

    // ── Drag state ────────────────────────────────────────────────────────
    drag: { id: null, type: null, priority: null, projectId: null },
    dragOverId: null,
    dragOverPri: null,

    // ── Init ──────────────────────────────────────────────────────────────
    async init() {
      await Promise.all([this.fetchProjects(), this.fetchPriorityTasks()]);
    },

    // ── View switching ────────────────────────────────────────────────────
    async switchView(v) {
      this.view = v;
      this.modal = null;
      this.editingTask = null;
      if (v === 'priority')   await Promise.all([this.fetchProjects(), this.fetchPriorityTasks()]);
      if (v === 'dashboard')  { await this.fetchProjects(); await this.fetchAllProjectTasks(); }
      if (v === 'archive')    await this.fetchArchivedProjects();
      if (v === 'completed')  await this.fetchCompletedTasks();
    },

    // ── Data fetching ─────────────────────────────────────────────────────
    async fetchProjects() {
      const d = await this.api('get_projects');
      if (d && !d.error) this.projects = d;
    },
    async fetchArchivedProjects() {
      const d = await this.api('get_archived_projects');
      if (d && !d.error) this.archivedProjects = d;
    },
    async fetchPriorityTasks() {
      const d = await this.api('get_priority_tasks');
      if (d && !d.error) this.priorityTasks = d;
    },
    async fetchCompletedTasks() {
      const d = await this.api('get_completed_tasks');
      if (d && !d.error) this.completedTasks = d;
    },
    async fetchAllProjectTasks() {
      const cache = {};
      await Promise.all(this.projects.map(async p => {
        const d = await this.api('get_tasks', { project_id: p.id });
        if (d && !d.error) cache[p.id] = d;
      }));
      this.projectTasksCache = cache;
    },
    async fetchProjectTasks(pid) {
      const d = await this.api('get_tasks', { project_id: pid });
      if (d && !d.error) this.projectTasksCache = { ...this.projectTasksCache, [pid]: d };
    },

    // ── Task helpers ──────────────────────────────────────────────────────
    getProjectTasks(pid, pri) {
      return (this.projectTasksCache[pid] || []).filter(t => t.priority === pri);
    },
    getProjectAllTasks(pid) {
      return this.projectTasksCache[pid] || [];
    },
    countPriTasks(pri) {
      return (this.priorityTasks[pri] || []).length;
    },
    toggleSection(pri) {
      this.collapsedSections[pri] = !this.collapsedSections[pri];
    },
    findTask(id) {
      for (const pri of ['ASAP','Soon','Backlog']) {
        const t = (this.priorityTasks[pri] || []).find(t => t.id == id);
        if (t) return t;
        for (const task of (this.priorityTasks[pri] || [])) {
          const s = (task.subtasks || []).find(s => s.id == id);
          if (s) return s;
        }
      }
      return null;
    },

    // ── Complete task ─────────────────────────────────────────────────────
    openCompleteModal(task) {
      this.modalTask    = task;
      this.completeNote = '';
      this.modal        = 'complete';
    },
    async confirmComplete() {
      if (!this.modalTask) return;
      const ok = await this.apiPost('complete_task', { id: this.modalTask.id, note: this.completeNote });
      if (ok) {
        this.modal = null;
        this.showNotification('✓ task marked complete', 'success');
        await this.refresh();
      }
    },

    // ── Reopen task ───────────────────────────────────────────────────────
    async reopenTask(id) {
      const ok = await this.apiPost('reopen_task', { id });
      if (ok) {
        this.showNotification('↺ task reopened', 'info');
        await this.fetchCompletedTasks();
        await this.fetchPriorityTasks();
      }
    },

    // ── Inline edit ───────────────────────────────────────────────────────
    startEdit(task) {
      this.editingTask = { ...task };
      this.editSaving  = false;
    },
    async saveEdit() {
      if (!this.editingTask || this.editSaving) return;
      this.editSaving = true;
      const t = { ...this.editingTask };
      this.editingTask = null;
      await this.apiPost('update_task', { id: t.id, title: t.title, priority: t.priority, project_id: t.project_id });
      this.editSaving = false;
      await this.refresh();
    },
    saveEditBlur() {
      // Small delay to allow select @change to fire first
      setTimeout(() => { if (this.editingTask) this.saveEdit(); }, 150);
    },

    // ── Delete task ───────────────────────────────────────────────────────
    async deleteTask(id) {
      if (!confirm('Delete this task?')) return;
      await this.apiPost('delete_task', { id });
      this.showNotification('task deleted', 'info');
      await this.refresh();
      if (this.view === 'completed') await this.fetchCompletedTasks();
    },

    // ── Move task (between projects) ──────────────────────────────────────
    async moveTask(id, projectId) {
      const task = this.findTask(id);
      if (!task) return;
      await this.apiPost('update_task', { id, title: task.title, priority: task.priority, project_id: projectId });
      this.showNotification('task moved', 'info');
      await this.refresh();
    },

    // ── Add task (dashboard) ──────────────────────────────────────────────
    startAddTask(pid) {
      this.addingTaskTo = pid;
      this.newTaskTitle = '';
      this.newTaskPriority = 'Soon';
    },
    async submitAddTask(pid) {
      if (!this.newTaskTitle.trim()) return;
      await this.apiPost('create_task', { project_id: pid, title: this.newTaskTitle.trim(), priority: this.newTaskPriority });
      this.addingTaskTo = null;
      this.newTaskTitle = '';
      this.showNotification('task added', 'success');
      await this.fetchProjectTasks(pid);
      await this.fetchProjects();
    },

    // ── Projects ──────────────────────────────────────────────────────────
    openNewProjectModal() {
      this.newProjectName   = '';
      this.newProjectColour = '#FF6777';
      this.modal = 'new-project';
    },
    async submitNewProject() {
      if (!this.newProjectName.trim()) return;
      const res = await this.apiPost('create_project', { name: this.newProjectName.trim(), colour: this.newProjectColour });
      if (res) {
        this.modal = null;
        this.showNotification('project created', 'success');
        await this.fetchProjects();
        if (this.view === 'dashboard') await this.fetchAllProjectTasks();
      }
    },
    openRenameProject(project) {
      this.editProjectId     = project.id;
      this.editProjectName   = project.name;
      this.editProjectColour = project.colour;
      this.modal = 'rename-project';
    },
    async submitRenameProject() {
      if (!this.editProjectName.trim()) return;
      await this.apiPost('update_project', { id: this.editProjectId, name: this.editProjectName.trim(), colour: this.editProjectColour });
      this.modal = null;
      this.showNotification('project updated', 'success');
      await this.fetchProjects();
      if (this.view === 'dashboard') await this.fetchAllProjectTasks();
    },
    async archiveProject(id) {
      await this.apiPost('archive_project', { id });
      this.showNotification('project toggled', 'info');
      await this.fetchProjects();
      await this.fetchArchivedProjects();
    },
    async deleteProject(id) {
      if (!confirm('Delete this project and all its tasks?')) return;
      await this.apiPost('delete_project', { id });
      this.showNotification('project deleted', 'info');
      await this.fetchProjects();
      await this.fetchArchivedProjects();
      if (this.view === 'dashboard') await this.fetchAllProjectTasks();
    },

    // ── History ───────────────────────────────────────────────────────────
    async openHistory(taskId) {
      this.historyTask = this.findTask(taskId) || { title: 'Task #' + taskId };
      this.historyLog  = [];
      this.modal = 'history';
      const d = await this.api('get_task_log', { task_id: taskId });
      if (d && !d.error) this.historyLog = d;
    },

    // ── Devices ───────────────────────────────────────────────────────────
    async openDevices() {
      this.modal = 'devices';
      const d = await this.api('get_devices');
      if (d && !d.error) this.devices = d;
    },
    promptLabelDevice(dev) {
      this.labelDeviceId    = dev.id;
      this.labelDeviceValue = dev.device_label;
      this.modal = 'label-device';
    },
    async submitLabelDevice() {
      if (!this.labelDeviceValue.trim()) return;
      await this.apiPost('label_device', { id: this.labelDeviceId, label: this.labelDeviceValue.trim() });
      await this.openDevices();
    },
    async revokeDevice(id) {
      if (!confirm('Revoke this device?')) return;
      await this.apiPost('revoke_device', { id });
      this.showNotification('device revoked', 'info');
      await this.openDevices();
    },
    async logout() {
      await this.apiPost('logout', {});
      window.location.href = 'index.php';
    },

    // ── Dropdowns ─────────────────────────────────────────────────────────
    toggleDropdown(id) { this.openDropdownId = this.openDropdownId === id ? null : id; },
    closeDropdown(id)  { if (this.openDropdownId === id) this.openDropdownId = null; },

    // ── Refresh ───────────────────────────────────────────────────────────
    async refresh() {
      if (this.view === 'priority')  await Promise.all([this.fetchProjects(), this.fetchPriorityTasks()]);
      if (this.view === 'dashboard') { await this.fetchProjects(); await this.fetchAllProjectTasks(); }
      if (this.view === 'completed') await this.fetchCompletedTasks();
    },

    // ── Drag and drop — TASKS (priority view) ─────────────────────────────
    onTaskDragStart(e, task) {
      e.dataTransfer.effectAllowed = 'move';
      e.dataTransfer.setData('text/plain', String(task.id));
      this.drag = { id: task.id, type: 'task', priority: task.priority, projectId: task.project_id };
    },

    onSectionDragOver(e, pri) {
      if (this.drag.type !== 'task') return;
      // Only activate section highlight if not over a specific task row
      this.dragOverPri = pri;
    },
    onSectionDragLeave(e, pri) {
      if (this.dragOverPri === pri) this.dragOverPri = null;
    },
    onSectionDrop(e, pri) {
      if (this.drag.type !== 'task') return;
      // Only handle if not dropped on a task (tasks stop propagation)
      if (this.drag.priority !== pri) {
        this._changeTaskPriority(this.drag.id, pri);
      }
      this.dragOverPri = null;
      this.dragOverId  = null;
    },

    onTaskDragOver(e, task) {
      if (this.drag.type !== 'task' || this.drag.id === task.id) return;
      if (this.drag.priority === task.priority) {
        this.dragOverId  = task.id;
        this.dragOverPri = null;
      } else {
        this.dragOverPri = task.priority;
        this.dragOverId  = null;
      }
    },
    onTaskDrop(e, task) {
      if (this.drag.type !== 'task' || this.drag.id === task.id) return;
      if (this.drag.priority === task.priority) {
        this._reorderTasksInSection(this.drag.id, task.id, task.priority);
      } else {
        this._changeTaskPriority(this.drag.id, task.priority);
      }
      this.dragOverId  = null;
      this.dragOverPri = null;
    },

    _reorderTasksInSection(dragId, targetId, pri) {
      const arr = [...(this.priorityTasks[pri] || [])];
      const fi  = arr.findIndex(t => t.id == dragId);
      const ti  = arr.findIndex(t => t.id == targetId);
      if (fi === -1 || ti === -1) return;
      const [item] = arr.splice(fi, 1);
      arr.splice(ti, 0, item);
      this.priorityTasks = { ...this.priorityTasks, [pri]: arr };
      this.apiPost('reorder_tasks', { order: arr.map(t => t.id) });
    },

    async _changeTaskPriority(taskId, newPri) {
      const task = this.findTask(taskId);
      if (!task) return;
      const oldPri = task.priority;
      // Optimistic: move in local state
      this.priorityTasks[oldPri] = (this.priorityTasks[oldPri] || []).filter(t => t.id != taskId);
      const updated = { ...task, priority: newPri };
      this.priorityTasks[newPri] = [...(this.priorityTasks[newPri] || []), updated];
      await this.apiPost('update_task', { id: taskId, title: task.title, priority: newPri, project_id: task.project_id });
      this.showNotification('priority → ' + newPri, 'success');
      await this.fetchPriorityTasks();
    },

    // ── Drag and drop — PROJECTS (dashboard) ──────────────────────────────
    onProjDragStart(e, project) {
      e.dataTransfer.effectAllowed = 'move';
      e.dataTransfer.setData('text/plain', 'proj_' + project.id);
      this.drag = { id: project.id, type: 'project', priority: null, projectId: null };
    },
    onProjDragOver(e, project) {
      if (this.drag.type !== 'project' || this.drag.id === project.id) return;
      this.dragOverId = 'proj_' + project.id;
    },
    onProjDrop(e, project) {
      if (this.drag.type !== 'project' || this.drag.id === project.id) { this.dragOverId = null; return; }
      const arr = [...this.projects];
      const fi  = arr.findIndex(p => p.id == this.drag.id);
      const ti  = arr.findIndex(p => p.id == project.id);
      if (fi !== -1 && ti !== -1) {
        const [item] = arr.splice(fi, 1);
        arr.splice(ti, 0, item);
        this.projects = arr;
        this.apiPost('reorder_projects', { order: arr.map(p => p.id) });
      }
      this.dragOverId = null;
      this.onDragEnd();
    },

    // ── Drag and drop — COMPLETED TASKS ───────────────────────────────────
    onCompletedDragStart(e, task) {
      e.dataTransfer.effectAllowed = 'move';
      this.drag = { id: task.id, type: 'completed', priority: null, projectId: null };
    },
    onCompletedDragOver(e, task) {
      if (this.drag.type !== 'completed' || this.drag.id === task.id) return;
      this.dragOverId = 'comp_' + task.id;
    },
    onCompletedDrop(e, task) {
      if (this.drag.type !== 'completed' || this.drag.id === task.id) { this.dragOverId = null; return; }
      const arr = [...this.completedTasks];
      const fi  = arr.findIndex(t => t.id == this.drag.id);
      const ti  = arr.findIndex(t => t.id == task.id);
      if (fi !== -1 && ti !== -1) {
        const [item] = arr.splice(fi, 1);
        arr.splice(ti, 0, item);
        this.completedTasks = arr;
        this.apiPost('reorder_tasks', { order: arr.map(t => t.id) });
      }
      this.dragOverId = null;
      this.onDragEnd();
    },

    onDragEnd() {
      this.drag       = { id: null, type: null, priority: null, projectId: null };
      this.dragOverId = null;
      this.dragOverPri = null;
    },

    // ── API wrappers ──────────────────────────────────────────────────────
    async api(action, params = {}) {
      this.loading = true;
      try {
        const qs = new URLSearchParams({ action, ...params }).toString();
        const r  = await fetch('api.php?' + qs, { headers: { 'X-CSRF-Token': CSRF } });
        return await r.json();
      } catch (e) {
        this.showNotification('network error', 'error');
        return null;
      } finally {
        this.loading = false;
      }
    },
    async apiPost(action, body = {}) {
      this.loading = true;
      try {
        const r = await fetch('api.php', {
          method:  'POST',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
          body:    JSON.stringify({ action, csrf_token: CSRF, ...body }),
        });
        const data = await r.json();
        if (data.error) { this.showNotification(data.error, 'error'); return null; }
        return data;
      } catch (e) {
        this.showNotification('network error', 'error');
        return null;
      } finally {
        this.loading = false;
      }
    },

    // ── Notifications ─────────────────────────────────────────────────────
    showNotification(message, type = 'info') {
      const id = Date.now() + Math.random();
      this.toasts.push({ id, message, type });
      setTimeout(() => { this.toasts = this.toasts.filter(t => t.id !== id); }, 2500);
    },

    // ── Date helpers ──────────────────────────────────────────────────────
    formatDate(str) {
      if (!str) return '';
      const d = new Date(str.replace(' ', 'T'));
      return d.toLocaleString('en-GB', { year:'numeric', month:'2-digit', day:'2-digit', hour:'2-digit', minute:'2-digit' });
    },
    formatShortDate(str) {
      if (!str) return '—';
      const d    = new Date(str.replace(' ', 'T'));
      const now  = new Date();
      const diff = (now - d) / 1000;
      if (diff < 86400) return 'today ' + d.toLocaleTimeString('en-GB', { hour:'2-digit', minute:'2-digit' });
      if (diff < 172800) return 'yesterday';
      return d.toLocaleDateString('en-GB', { day:'2-digit', month:'short', year:'2-digit' });
    },
    relativeTime(str) {
      if (!str) return '';
      const d    = new Date(str.replace(' ', 'T'));
      const diff = (Date.now() - d.getTime()) / 1000;
      if (diff < 60)    return 'just now';
      if (diff < 3600)  return Math.floor(diff/60) + 'm ago';
      if (diff < 86400) return Math.floor(diff/3600) + 'h ago';
      return Math.floor(diff/86400) + ' days ago';
    },
  };
}
</script>
</body>
</html>

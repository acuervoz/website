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

html, body { height: 100%; background: var(--bg); color: var(--text); font-family: 'JetBrains Mono', monospace; font-size: 13px; }

/* ── Retro terminal scrollbars ──────────────────────────────────────────── */
::-webkit-scrollbar { width: 10px; height: 10px; }
::-webkit-scrollbar-track { background: var(--bg); border-left: 1px solid var(--border); }
::-webkit-scrollbar-thumb {
  background: repeating-linear-gradient(to bottom, var(--text-dim) 0px, var(--text-dim) 2px, transparent 2px, transparent 5px);
  border-left: 1px solid var(--border);
  border-right: 1px solid var(--border);
}
::-webkit-scrollbar-thumb:hover {
  background: repeating-linear-gradient(to bottom, var(--text-muted) 0px, var(--text-muted) 2px, transparent 2px, transparent 5px);
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
  height: 44px;
  display: flex;
  align-items: center;
  padding: 0 20px;
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
.nav-brand { color: var(--accent); font-weight: 700; letter-spacing: 0.15em; font-size: 12px; text-transform: uppercase; margin-right: 20px; flex-shrink: 0; }
.nav-links { display: flex; align-items: center; gap: 2px; flex: 1; flex-wrap: wrap; }
.nav-btn {
  background: none; border: none;
  color: var(--text-muted);
  font-family: 'JetBrains Mono', monospace;
  font-size: 11px; letter-spacing: 0.1em; text-transform: uppercase;
  cursor: pointer; padding: 6px 10px; position: relative;
  transition: color 120ms ease; white-space: nowrap;
}
.nav-btn:hover { color: var(--text); }
.nav-btn.active { color: var(--accent); }
.nav-btn.active::after {
  content: ''; position: absolute; bottom: 0; left: 10px; right: 10px;
  height: 1px; background: var(--accent);
}
.nav-right { display: flex; align-items: center; gap: 6px; flex-shrink: 0; }
.nav-icon-btn {
  background: none; border: 1px solid var(--border);
  color: var(--text-muted); font-family: 'JetBrains Mono', monospace;
  font-size: 11px; padding: 4px 10px; cursor: pointer;
  letter-spacing: 0.08em; transition: border-color 120ms ease, color 120ms ease;
}
.nav-icon-btn:hover { border-color: var(--accent); color: var(--accent); }
.nav-add-btn {
  background: var(--accent); border: none;
  color: var(--bg); font-family: 'JetBrains Mono', monospace;
  font-size: 11px; padding: 4px 12px; cursor: pointer;
  letter-spacing: 0.08em; text-transform: uppercase; font-weight: 700;
  transition: background 120ms ease;
}
.nav-add-btn:hover { background: var(--accent-dim); }
.hamburger-btn {
  display: none; background: none; border: 1px solid var(--border);
  color: var(--text-muted); font-family: 'JetBrains Mono', monospace;
  font-size: 14px; padding: 3px 8px; cursor: pointer; line-height: 1;
  transition: border-color 120ms ease, color 120ms ease;
}
.hamburger-btn:hover { border-color: var(--accent); color: var(--accent); }
@media (max-width: 640px) { .nav-links { display: none; } .hamburger-btn { display: block; } }

/* ── Mobile menu ─────────────────────────────────────────────────────────── */
.mobile-menu {
  position: fixed; top: 44px; left: 0; right: 0; z-index: 99;
  background: var(--bg-surface); border-bottom: 1px solid var(--border);
  padding: 4px 0;
}
.mobile-menu-item {
  display: block; width: 100%; background: none; border: none;
  color: var(--text-muted); font-family: 'JetBrains Mono', monospace;
  font-size: 12px; letter-spacing: 0.1em; text-transform: uppercase;
  padding: 12px 20px; text-align: left; cursor: pointer;
  transition: background 120ms ease, color 120ms ease;
}
.mobile-menu-item:hover { background: var(--bg-raised); color: var(--text); }
.mobile-menu-item.active { color: var(--accent); }

/* ── Layout ─────────────────────────────────────────────────────────────── */
#main { margin-top: 44px; padding: 24px 20px; min-height: calc(100vh - 44px); }
.content-wrap { max-width: 60%; margin: 0 auto; width: 100%; }
@media (max-width: 1300px) { .content-wrap { max-width: 85%; } }
@media (max-width: 900px)  { .content-wrap { max-width: 100%; } }
@media (max-width: 600px)  { #main { padding: 16px 10px; } .nav-brand { display: none; } }

/* ── Section headers ────────────────────────────────────────────────────── */
.section-header {
  display: flex; align-items: center; gap: 8px; padding: 8px 6px;
  cursor: pointer; user-select: none; color: var(--text-muted);
  font-size: 11px; letter-spacing: 0.12em; text-transform: uppercase;
  border-bottom: 1px solid var(--border); margin-bottom: 2px;
  transition: background 120ms ease, color 120ms ease, border-color 120ms ease;
}
.section-header.drag-target { color: var(--accent); background: rgba(255,103,119,0.06); border-bottom-color: var(--accent); }
.pri-asap  { color: var(--accent); }
.pri-soon  { color: var(--text); }
.pri-back  { color: var(--text-muted); }
.section-header .count  { color: var(--text-dim); font-size: 11px; }
.section-header .toggle { margin-left: auto; color: var(--text-dim); }

/* ── Task rows ──────────────────────────────────────────────────────────── */
.task-row {
  overflow-x: auto;
  border-left: 2px solid transparent; border-top: 2px solid transparent;
  transition: background 120ms ease, border-color 120ms ease;
}
.task-row::-webkit-scrollbar { height: 3px; }
.task-row[draggable="true"] { cursor: grab; }
.task-row[draggable="true"]:active { cursor: grabbing; }
.task-row:hover { background: var(--bg-raised); border-left-color: var(--accent); }
.task-row.is-dragging { opacity: 0.3; }
.task-row.drag-insert-before { border-top-color: var(--accent) !important; }
.task-row-inner { display: flex; align-items: center; gap: 8px; padding: 6px 8px; width: max-content; min-width: 100%; }
.task-row.subtask .task-row-inner { padding-left: 24px; }
.task-row .task-title { overflow: visible; text-overflow: clip; }
.subtask-prefix { color: var(--text-dim); flex-shrink: 0; font-size: 11px; }
.drag-handle { color: var(--text-dim); cursor: grab; flex-shrink: 0; font-size: 11px; opacity: 0; transition: opacity 120ms ease; user-select: none; }
.task-row:hover .drag-handle { opacity: 1; }
.project-tag { display: inline-block; padding: 1px 6px; font-size: 10px; letter-spacing: 0.06em; text-transform: uppercase; border: 1px solid; flex-shrink: 0; white-space: nowrap; }
.task-title { flex: 1; color: var(--text); min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.task-info  { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 1px; }
.task-info .task-title { flex: none; }
.task-desc  { font-size: 10px; color: var(--text-dim); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.task-num   { font-size: 10px; color: var(--text-dim); flex-shrink: 0; min-width: 20px; }
.task-actions { display: flex; align-items: center; gap: 4px; flex-shrink: 0; }
.task-btn { background: none; border: 1px solid var(--border); color: var(--text-muted); font-family: 'JetBrains Mono', monospace; font-size: 11px; padding: 2px 6px; cursor: pointer; transition: border-color 120ms ease, color 120ms ease; }
.task-btn:hover { border-color: var(--accent); color: var(--accent); }
.task-btn.success:hover { border-color: var(--success); color: var(--success); }


/* ── Priority view: cards ────────────────────────────────────────────────── */
.pri-task-card {
  border: 1px solid var(--border); border-left: 2px solid transparent;
  background: var(--bg-surface); padding: 8px;
  display: flex; flex-direction: column; gap: 8px;
  cursor: grab; user-select: none;
  transition: border-color 120ms ease, background 120ms ease;
}
.pri-task-card:active { cursor: grabbing; }
.pri-task-card:hover { background: var(--bg-raised); border-color: var(--accent); }
.pri-task-card.is-dragging { opacity: 0.3; }
.pri-task-card.drag-insert-left { border-left-color: var(--accent) !important; }
.pri-card-header { display: flex; align-items: center; justify-content: space-between; gap: 6px; }
.pri-task-card .task-title { white-space: normal; overflow: visible; text-overflow: clip; flex: none; font-size: 13px; line-height: 1.4; }
.pri-card-desc { font-size: 10px; color: var(--text-dim); }
.pri-subtask-row { display: flex; align-items: center; gap: 6px; padding-top: 4px; border-top: 1px solid var(--bg-raised); }
.pri-subtask-pfx { color: var(--text-dim); font-size: 11px; flex-shrink: 0; }
.pri-subtask-row .task-title { flex: 1; min-width: 0; white-space: normal; font-size: 11px; }

/* ── Inline edit ─────────────────────────────────────────────────────────── */
.inline-edit-form { display: flex; align-items: center; gap: 6px; flex: 1; min-width: 0; }
.inline-edit-form input,
.inline-edit-form select {
  background: var(--bg-raised); border: 1px solid var(--accent);
  color: var(--text); font-family: 'JetBrains Mono', monospace; font-size: 12px; padding: 3px 6px; outline: none;
}
.inline-edit-form input  { flex: 1; min-width: 0; }
.inline-edit-form select { font-size: 11px; }

/* ── Dropdown ────────────────────────────────────────────────────────────── */
.dropdown-wrap { position: relative; }
.dropdown-menu { position: absolute; right: 0; top: 100%; z-index: 50; background: var(--bg-surface); border: 1px solid var(--border); min-width: 160px; padding: 4px 0; }
.dropdown-item { display: block; width: 100%; background: none; border: none; color: var(--text); font-family: 'JetBrains Mono', monospace; font-size: 11px; padding: 6px 12px; text-align: left; cursor: pointer; letter-spacing: 0.06em; transition: background 120ms ease, color 120ms ease; }
.dropdown-item:hover { background: var(--bg-raised); color: var(--accent); }

/* ── Priority view sections ──────────────────────────────────────────────── */
.priority-section { margin-bottom: 24px; }

/* ── Dashboard & Completed board shared card styles ────────────────────── */
.board-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
  gap: 16px;
  align-items: start;
}
.project-card {
  border: 1px solid var(--border);
  background: var(--bg-surface); display: flex; flex-direction: column;
  transition: border-color 120ms ease, opacity 120ms ease;
}
.project-card[draggable="true"] { cursor: grab; }
.project-card[draggable="true"]:active { cursor: grabbing; }
.project-card.is-dragging { opacity: 0.3; }
.project-card.drag-target { border-color: var(--accent); }
.project-card-header { display: flex; align-items: center; justify-content: space-between; padding: 8px 12px; border-bottom: 1px solid var(--border); }
.project-card-title { font-size: 11px; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; flex: 1; }
.project-card-body { padding: 8px 0; flex: 1; overflow-y: auto; max-height: 420px; }
.card-section-header { padding: 4px 12px; font-size: 10px; letter-spacing: 0.12em; text-transform: uppercase; color: var(--text-muted); display: flex; align-items: center; gap: 6px; }
.card-task-row { display: flex; align-items: flex-start; gap: 6px; padding: 4px 12px; border-left: 2px solid transparent; border-top: 2px solid transparent; transition: background 120ms ease, border-color 120ms ease; }
.card-task-row:hover { background: var(--bg-raised); border-left-color: var(--accent); }
.card-task-row[draggable="true"] { cursor: grab; }
.card-task-row.is-dragging { opacity: 0.3; }
.card-task-row.drag-insert-before { border-top-color: var(--accent) !important; }
.card-task-row .task-title { font-size: 12px; }
.card-task-row.subtask { padding-left: 24px; }
.card-task-row .task-title { white-space: normal; overflow: visible; text-overflow: unset; }
.task-num { padding-top: 1px; }
.task-title-btn { cursor: pointer; }
.task-title-btn:hover { color: var(--accent); }
.card-drag-handle { color: var(--text-dim); cursor: grab; font-size: 11px; opacity: 0; flex-shrink: 0; user-select: none; transition: opacity 120ms ease; }
.project-card-header:hover .card-drag-handle { opacity: 1; }
.project-card-footer { border-top: 1px solid var(--border); padding: 8px 12px; }

/* ── Completed tasks in dashboard card ──────────────────────────────────── */
.card-completed-divider { margin: 4px 12px; border: none; border-top: 1px dashed var(--border); }
.card-completed-row {
  display: flex; align-items: center; gap: 6px; padding: 3px 12px;
  border-left: 2px solid transparent; border-top: 2px solid transparent;
  transition: background 120ms ease;
}
.card-completed-row[draggable="true"] { cursor: grab; }
.card-completed-row:hover { background: var(--bg-raised); }
.card-completed-row.drag-insert-before { border-top-color: var(--text-dim) !important; }
.card-completed-date { font-size: 10px; color: var(--text-dim); flex-shrink: 0; white-space: nowrap; }
.card-completed-title { font-size: 11px; color: var(--text-dim); text-decoration: line-through; text-decoration-color: var(--text-dim); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; flex: 1; }

/* ── Completed board cards ──────────────────────────────────────────────── */
.comp-card-row {
  display: flex; align-items: flex-start; gap: 8px; padding: 6px 12px;
  border-left: 2px solid transparent; border-top: 2px solid transparent;
  transition: background 120ms ease, border-color 120ms ease;
}
.comp-card-row[draggable="true"] { cursor: grab; }
.comp-card-row:hover { background: var(--bg-raised); border-left-color: var(--text-dim); }
.comp-card-row.is-dragging { opacity: 0.3; }
.comp-card-row.drag-insert-before { border-top-color: var(--accent) !important; }
.comp-date  { font-size: 10px; color: var(--text-dim); flex-shrink: 0; padding-top: 1px; min-width: 68px; }
.comp-body  { flex: 1; min-width: 0; }
.comp-title { font-size: 12px; color: var(--text-muted); text-decoration: line-through; text-decoration-color: var(--text-dim); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.comp-note  { font-size: 10px; color: var(--text-dim); margin-top: 2px; }
.comp-note::before { content: '└─ '; }
.comp-pri   { font-size: 10px; color: var(--text-dim); }
.comp-card-actions { flex-shrink: 0; }

/* ── Add task / common form elements ────────────────────────────────────── */
.add-task-form { display: flex; gap: 6px; flex-direction: column; }
.add-task-form input, .add-task-form select {
  background: var(--bg-raised); border: 1px solid var(--border); color: var(--text);
  font-family: 'JetBrains Mono', monospace; font-size: 12px; padding: 5px 8px; outline: none; width: 100%;
  transition: border-color 120ms ease;
}
.add-task-form input:focus, .add-task-form select:focus { border-color: var(--accent); }
.add-task-row { display: flex; gap: 6px; }
.add-task-row select { width: auto; flex-shrink: 0; }
.btn { background: none; border: 1px solid var(--border); color: var(--text-muted); font-family: 'JetBrains Mono', monospace; font-size: 11px; letter-spacing: 0.08em; text-transform: uppercase; padding: 5px 12px; cursor: pointer; transition: border-color 120ms ease, color 120ms ease; }
.btn:hover { border-color: var(--accent); color: var(--accent); }
.btn-accent { border-color: var(--accent); color: var(--accent); }
.btn-accent:hover { background: var(--accent); color: var(--bg); }
.btn-sm { padding: 3px 8px; font-size: 10px; }
.btn-text { background: none; border: none; color: var(--text-muted); font-family: 'JetBrains Mono', monospace; font-size: 11px; cursor: pointer; padding: 4px 0; letter-spacing: 0.08em; text-transform: uppercase; transition: color 120ms ease; }
.btn-text:hover { color: var(--accent); }
.new-project-card { border: 1px dashed var(--border); min-height: 80px; display: flex; align-items: center; justify-content: center; padding: 20px; cursor: pointer; transition: border-color 120ms ease; background: transparent; flex-shrink: 0; }
.new-project-card:hover { border-color: var(--accent); }
.new-project-label { color: var(--text-muted); font-size: 11px; letter-spacing: 0.1em; text-transform: uppercase; transition: color 120ms ease; }
.new-project-card:hover .new-project-label { color: var(--accent); }

/* ── Archive / Completed view headers ───────────────────────────────────── */
.view-header { font-size: 11px; letter-spacing: 0.12em; text-transform: uppercase; color: var(--text-muted); margin-bottom: 16px; padding-bottom: 8px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; }

/* ── Modals ─────────────────────────────────────────────────────────────── */
.modal-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,0.75); z-index: 200; display: flex; align-items: center; justify-content: center; padding: 20px; }
.modal { background: var(--bg-surface); border: 1px solid var(--border); min-width: 360px; max-width: 540px; width: 100%; max-height: 80vh; display: flex; flex-direction: column; }
@media (max-width: 600px) { .modal { min-width: 0; } }
.modal-header { padding: 10px 16px; border-bottom: 1px solid var(--border); font-size: 11px; letter-spacing: 0.1em; text-transform: uppercase; color: var(--accent); font-weight: 700; display: flex; align-items: center; justify-content: space-between; }
.modal-body { padding: 20px 16px; overflow-y: auto; flex: 1; }
.modal-footer { padding: 12px 16px; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: 8px; }
.modal-task-title { color: var(--text); font-size: 13px; margin-bottom: 16px; padding: 8px; background: var(--bg-raised); border-left: 2px solid var(--accent); }
.modal-label { font-size: 11px; letter-spacing: 0.1em; text-transform: uppercase; color: var(--text-muted); margin-bottom: 6px; display: block; }
.modal-textarea { width: 100%; background: var(--bg-raised); border: 1px solid var(--border); color: var(--text); font-family: 'JetBrains Mono', monospace; font-size: 12px; padding: 8px; resize: vertical; min-height: 80px; outline: none; transition: border-color 120ms ease; }
.modal-textarea:focus { border-color: var(--accent); }
.modal-input { width: 100%; background: var(--bg-raised); border: 1px solid var(--border); color: var(--text); font-family: 'JetBrains Mono', monospace; font-size: 12px; padding: 7px 8px; outline: none; transition: border-color 120ms ease; }
.modal-input:focus { border-color: var(--accent); }
.modal-select { width: 100%; background: var(--bg-raised); border: 1px solid var(--border); color: var(--text); font-family: 'JetBrains Mono', monospace; font-size: 12px; padding: 7px 8px; outline: none; transition: border-color 120ms ease; }
.modal-select:focus { border-color: var(--accent); }
.modal-field { margin-bottom: 16px; }
.modal-subtask-list { margin-bottom: 6px; }
.modal-subtask-item { display: flex; align-items: center; gap: 6px; margin-bottom: 4px; }
.modal-subtask-prefix { color: var(--text-dim); font-size: 11px; flex-shrink: 0; }

/* ── Project view modal ─────────────────────────────────────────────────── */
.pv-task-row { display: flex; align-items: flex-start; gap: 8px; padding: 6px 16px; border-left: 2px solid transparent; border-top: 2px solid transparent; transition: background 120ms ease, border-color 120ms ease; cursor: grab; }
.pv-task-row:active { cursor: grabbing; }
.pv-task-row:hover { background: var(--bg-raised); border-left-color: var(--accent); }
.pv-task-row.subtask { padding-left: 36px; cursor: default; }
.pv-task-row.is-dragging { opacity: 0.3; }
.pv-task-row.drag-insert-before { border-top-color: var(--accent) !important; }
.pv-drag-handle { color: var(--text-dim); font-size: 11px; opacity: 0; flex-shrink: 0; user-select: none; transition: opacity 120ms ease; padding-top: 2px; }
.pv-task-row:not(.subtask):hover .pv-drag-handle { opacity: 1; }
.pv-task-num { font-size: 10px; color: var(--text-dim); flex-shrink: 0; min-width: 22px; padding-top: 2px; }
.pv-task-info { flex: 1; min-width: 0; }
.pv-task-title { color: var(--text); font-size: 12px; word-break: break-word; cursor: pointer; }
.pv-task-title:hover { color: var(--accent); }
.pv-task-meta { font-size: 10px; color: var(--text-dim); margin-top: 2px; }
.pv-task-actions { flex-shrink: 0; display: flex; gap: 4px; }
.pv-done .pv-task-title { text-decoration: line-through; text-decoration-color: var(--text-dim); color: var(--text-dim); }
.pv-done .pv-task-meta { opacity: 0.6; }
.project-card-title-btn { cursor: pointer; transition: color 120ms ease; }
.project-card-title-btn:hover { color: var(--accent); }

/* ── Devices panel ──────────────────────────────────────────────────────── */
.devices-table { width: 100%; border-collapse: collapse; font-size: 12px; }
.devices-table th { text-align: left; color: var(--text-muted); font-size: 10px; letter-spacing: 0.1em; text-transform: uppercase; padding: 4px 8px; border-bottom: 1px solid var(--border); font-weight: 400; }
.devices-table td { padding: 8px 8px; border-bottom: 1px solid var(--bg-raised); vertical-align: middle; }
.current-dot { display: inline-block; width: 6px; height: 6px; border-radius: 50%; background: var(--success); margin-right: 4px; vertical-align: middle; }

/* ── History ─────────────────────────────────────────────────────────────── */
.history-entry { padding: 8px 0; border-bottom: 1px solid var(--bg-raised); }
.history-meta { font-size: 11px; color: var(--text-muted); margin-bottom: 4px; display: flex; align-items: center; gap: 8px; }
.history-action-completed { color: var(--success); }
.history-action-reopened  { color: var(--warning); }
.history-action-updated   { color: var(--text-muted); }
.history-note { color: var(--text-dim); font-size: 11px; padding-left: 16px; }
.history-note::before { content: '└─ '; color: var(--text-dim); }

/* ── Colour picker ──────────────────────────────────────────────────────── */
.colour-swatches { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 8px; }
.colour-swatch { width: 24px; height: 24px; border: 2px solid transparent; cursor: pointer; transition: border-color 120ms ease; }
.colour-swatch.selected, .colour-swatch:hover { border-color: var(--text); }
.colour-custom { display: flex; align-items: center; gap: 8px; margin-top: 8px; }
.colour-custom input[type=color] { width: 32px; height: 24px; border: 1px solid var(--border); background: none; cursor: pointer; padding: 0; }

/* ── Toast ───────────────────────────────────────────────────────────────── */
#toast-area { position: fixed; bottom: 20px; right: 20px; z-index: 300; display: flex; flex-direction: column; gap: 8px; pointer-events: none; }
.toast { background: var(--bg-surface); border: 1px solid var(--border); padding: 10px 16px; font-size: 12px; max-width: 300px; animation: toastIn 200ms ease; }
.toast.success { border-left: 3px solid var(--success); }
.toast.error   { border-left: 3px solid var(--accent); }
.toast.info    { border-left: 3px solid var(--text-muted); }
@keyframes toastIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }

/* ── Loading bar ─────────────────────────────────────────────────────────── */
.loading-bar { position: fixed; top: 44px; left: 0; right: 0; height: 2px; background: var(--accent); z-index: 150; animation: loadPulse 1s ease infinite; }
@keyframes loadPulse { 0%,100% { opacity: 0.4; } 50% { opacity: 1; } }

/* ── Misc ────────────────────────────────────────────────────────────────── */
.empty-state { color: var(--text-dim); font-size: 12px; padding: 12px 8px; letter-spacing: 0.06em; }
*:focus-visible { outline: 1px solid var(--accent); outline-offset: 1px; }
button:focus { outline: none; }
[x-cloak] { display: none !important; }
@media (max-width: 640px) {
  .card-task-row .task-info { overflow-x: auto; overflow-y: hidden; }
  .card-task-row .task-info::-webkit-scrollbar { height: 3px; }
  .card-task-row .task-title { white-space: nowrap; }
}
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
        <button class="nav-btn" :class="{active:view==='priority'}"  @click="switchView('priority')">PRIORITY</button>
        <button class="nav-btn" :class="{active:view==='dashboard'}" @click="switchView('dashboard')">DASHBOARD</button>
        <button class="nav-btn" :class="{active:view==='archive'}"   @click="switchView('archive')">ARCHIVE</button>
        <button class="nav-btn" :class="{active:view==='completed'}" @click="switchView('completed')">COMPLETED</button>
      </div>
    </div>
    <div class="nav-right">
      <button class="nav-add-btn" @click="openAddTaskModal()">+ TASK</button>
      <button class="nav-icon-btn" @click="openDevices()">⚙ DEVICES</button>
      <button class="hamburger-btn" @click="mobileMenuOpen = !mobileMenuOpen" :class="{active:mobileMenuOpen}">☰</button>
    </div>
  </div>
</nav>

<!-- ── Mobile menu ────────────────────────────────────────────────────────── -->
<div class="mobile-menu" x-show="mobileMenuOpen" x-cloak @click.outside="mobileMenuOpen=false">
  <button class="mobile-menu-item" :class="{active:view==='priority'}"  @click="switchView('priority');  mobileMenuOpen=false">PRIORITY</button>
  <button class="mobile-menu-item" :class="{active:view==='dashboard'}" @click="switchView('dashboard'); mobileMenuOpen=false">DASHBOARD</button>
  <button class="mobile-menu-item" :class="{active:view==='archive'}"   @click="switchView('archive');   mobileMenuOpen=false">ARCHIVE</button>
  <button class="mobile-menu-item" :class="{active:view==='completed'}" @click="switchView('completed'); mobileMenuOpen=false">COMPLETED</button>
</div>

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

        <div class="section-header"
             :class="{'drag-target': dragOverPri===pri && drag.priority!==pri}"
             @click="toggleSection(pri)">
          <span :class="{'pri-asap':pri==='ASAP','pri-soon':pri==='Soon','pri-back':pri==='Backlog'}"
            x-text="pri==='ASAP'?'!!':pri==='Soon'?'◈':'·'"></span>
          <span x-text="pri"></span>
          <span class="count" x-text="'('+countPriTasks(pri)+')'"></span>
          <span class="toggle" x-text="collapsedSections[pri]?'▸':'▾'"></span>
        </div>

        <div x-show="!collapsedSections[pri]">
          <template x-if="countPriTasks(pri)===0">
            <div class="empty-state">· drop tasks here or add via + TASK</div>
          </template>
          <template x-for="task in priorityTasks[pri]" :key="task.id">
                <div class="pri-task-card"
                     draggable="true"
                     :class="{'is-dragging': drag.id===task.id && drag.type==='task', 'drag-insert-left': dragOverId===task.id && drag.priority===pri}"
                     @dragstart="onTaskDragStart($event, task)"
                     @dragend="onDragEnd()"
                     @dragover.prevent.stop="onTaskDragOver($event, task)"
                     @drop.prevent.stop="onTaskDrop($event, task)">
                  <!-- Header: project tag left, buttons right -->
                  <div class="pri-card-header">
                    <span class="project-tag"
                      :style="'color:'+task.project_colour+';border-color:'+task.project_colour+'40'"
                      x-text="task.project_name"></span>
                    <div class="task-actions">
                      <button class="task-btn btn-sm" @click.stop="movePriTaskUp(task, pri)">↑</button>
                      <button class="task-btn btn-sm" @click.stop="movePriTaskDown(task, pri)">↓</button>
                      <button class="task-btn success btn-sm" @click.stop="openCompleteModal(task)">✓</button>
                      <button class="task-btn btn-sm" @click.stop="deleteTask(task.id)">✕</button>
                      <div class="dropdown-wrap" @click.outside="closeDropdown(task.id)">
                        <button class="task-btn btn-sm" @click.stop="toggleDropdown(task.id)">⋮</button>
                        <div class="dropdown-menu" x-show="openDropdownId===task.id" x-cloak @click.stop>
                          <button class="dropdown-item" @click="openHistory(task.id); closeDropdown(task.id)">View History</button>
                          <template x-for="p in projects.filter(p=>p.id!=task.project_id)" :key="p.id">
                            <button class="dropdown-item" @click="moveTask(task.id,p.id); closeDropdown(task.id)" x-text="'→ '+p.name"></button>
                          </template>
                        </div>
                      </div>
                    </div>
                  </div>
                  <!-- Task name -->
                  <span class="task-title task-title-btn" x-text="task.title" :title="task.title" @click.stop="openEditTaskModal(task)"></span>
                  <template x-if="task.description">
                    <span class="pri-card-desc" x-text="task.description"></span>
                  </template>
                  <!-- Subtasks -->
                  <template x-for="sub in task.subtasks||[]" :key="sub.id">
                    <div class="pri-subtask-row">
                      <span class="pri-subtask-pfx">└─</span>
                      <span class="task-title task-title-btn" x-text="sub.title" :title="sub.title" @click.stop="openEditTaskModal(task)"></span>
                      <div class="task-actions" style="margin-left:auto;flex-shrink:0">
                        <button class="task-btn success btn-sm" @click.stop="openCompleteModal(sub)">✓</button>
                        <button class="task-btn btn-sm" @click.stop="deleteTask(sub.id)">✕</button>
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
    <div class="board-grid">
      <template x-for="project in projects" :key="project.id">
        <div class="project-card"
             draggable="true"
             :class="{'is-dragging': drag.id===project.id && drag.type==='project', 'drag-target': dragOverId==='proj_'+project.id}"
             @dragstart="onProjDragStart($event, project)"
             @dragend="onDragEnd()"
             @dragover.prevent="onProjDragOver($event, project)"
             @drop.prevent="onProjDrop($event, project)">

          <div class="project-card-header" style="border-left:3px solid" :style="'border-left-color:'+project.colour">
            <span class="card-drag-handle">⠿</span>
            <span class="project-card-title project-card-title-btn" x-text="project.name" @click.stop="openProjectView(project)"></span>
            <div class="dropdown-wrap" @click.outside="closeDropdown('proj_'+project.id)" @click.stop>
              <button class="task-btn" @click.stop="toggleDropdown('proj_'+project.id)">⋮</button>
              <div class="dropdown-menu" x-show="openDropdownId==='proj_'+project.id" x-cloak @click.stop>
                <button class="dropdown-item" @click="openRenameProject(project); closeDropdown('proj_'+project.id)">Rename</button>
                <button class="dropdown-item" @click="moveProjectUp(project.id); closeDropdown('proj_'+project.id)">↑ Move Up</button>
                <button class="dropdown-item" @click="moveProjectDown(project.id); closeDropdown('proj_'+project.id)">↓ Move Down</button>
                <button class="dropdown-item" @click="archiveProject(project.id); closeDropdown('proj_'+project.id)">Archive</button>
                <button class="dropdown-item" @click="deleteProject(project.id); closeDropdown('proj_'+project.id)">Delete</button>
              </div>
            </div>
          </div>

          <div class="project-card-body">
            <!-- Active tasks as numbered list -->
            <template x-if="getProjectAllTasks(project.id).length===0">
              <div class="empty-state" style="padding:8px 12px">· no active tasks</div>
            </template>
            <template x-for="(task, i) in getProjectAllTasks(project.id)" :key="task.id">
              <div>
                <div class="card-task-row"
                     draggable="true"
                     :class="{'is-dragging': drag.id===task.id && drag.type==='dashboard-task', 'drag-insert-before': dragOverId===task.id}"
                     @dragstart.stop="onDashTaskDragStart($event, task, project.id)"
                     @dragend="onDragEnd()"
                     @dragover.prevent.stop="onDashTaskDragOver($event, task, project.id)"
                     @drop.prevent.stop="onDashTaskDrop($event, task, project.id)">
                  <span class="task-num" x-text="(i+1)+'.'"></span>
                  <div class="task-info">
                    <span class="task-title task-title-btn" x-text="task.title" :title="task.title" @click.stop="openEditTaskModal(task)"></span>
                    <template x-if="task.description">
                      <span class="task-desc" x-text="task.description"></span>
                    </template>
                  </div>
                  <div class="task-actions">
                    <button class="task-btn success btn-sm" @click="openCompleteModal(task)">✓</button>
                    <button class="task-btn btn-sm" @click.stop="deleteTask(task.id)">✕</button>
                  </div>
                </div>
                <template x-for="sub in task.subtasks||[]" :key="sub.id">
                  <div class="card-task-row subtask">
                    <span class="subtask-prefix">└─</span>
                    <div class="task-info">
                      <span class="task-title task-title-btn" x-text="sub.title" :title="sub.title" @click.stop="openEditTaskModal(task)"></span>
                      <template x-if="sub.description">
                        <span class="task-desc" x-text="sub.description"></span>
                      </template>
                    </div>
                    <div class="task-actions">
                      <button class="task-btn success btn-sm" @click="openCompleteModal(sub)">✓</button>
                      <button class="task-btn btn-sm" @click.stop="deleteTask(sub.id)">✕</button>
                    </div>
                  </div>
                </template>
              </div>
            </template>

            <!-- Last 3 completed tasks -->
            <template x-if="(projectCompletedCache[project.id]||[]).length > 0">
              <div>
                <hr class="card-completed-divider">
                <div class="card-section-header" style="color:var(--text-dim)">
                  <span>✓</span>
                  <span>recently completed</span>
                </div>
                <template x-for="ct in (projectCompletedCache[project.id]||[])" :key="ct.id">
                  <div class="card-completed-row">
                    <span class="card-completed-date" x-text="formatShortDate(ct.completed_at)"></span>
                    <span class="card-completed-title task-title-btn" x-text="ct.title" :title="ct.title" @click.stop="openEditTaskModal(ct)"></span>
                    <div class="task-actions">
                      <button class="task-btn btn-sm" @click.stop="reopenTaskInDashboard(ct.id, project.id)" title="Reopen">↺</button>
                      <button class="task-btn btn-sm" @click.stop="deleteTask(ct.id)">✕</button>
                    </div>
                  </div>
                </template>
              </div>
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
                    <option value="No Priority">— No Priority</option>
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
    <div class="view-header"><span>── ARCHIVED PROJECTS ──</span></div>
    <div class="board-grid">
      <template x-if="archivedProjects.length===0">
        <div class="empty-state">· no archived projects</div>
      </template>
      <template x-for="project in archivedProjects" :key="project.id">
        <div class="project-card" style="opacity:0.7">
          <div class="project-card-header" style="border-left:3px solid" :style="'border-left-color:'+project.colour">
            <span class="project-card-title" x-text="project.name"></span>
            <div style="display:flex;gap:4px">
              <button class="btn btn-sm" @click="archiveProject(project.id)">UNARCHIVE</button>
              <button class="btn btn-sm" style="color:var(--accent);border-color:var(--accent)" @click="deleteProject(project.id)">DELETE</button>
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

  <!-- ── COMPLETED BOARD VIEW ───────────────────────────────────────────── -->
  <div x-show="view==='completed'" x-cloak>
    <div class="view-header">
      <span>── COMPLETED TASKS ──
        <span style="color:var(--text-dim)" x-text="completedTasks.length ? '('+completedTasks.length+')' : ''"></span>
      </span>
      <span style="color:var(--text-dim);font-size:10px">newest first · drag within project to reorder</span>
    </div>

    <template x-if="completedTasks.length===0">
      <div class="empty-state">· no completed tasks yet</div>
    </template>

    <div class="board-grid" x-show="completedTasks.length > 0" style="align-items:start">
      <template x-for="group in completedByProject" :key="group.id">
        <div class="project-card">
          <div class="project-card-header" style="border-left:3px solid" :style="'border-left-color:'+group.colour">
            <span class="project-card-title project-card-title-btn" x-text="group.name" @click.stop="openProjectView(group)"></span>
            <span style="color:var(--text-dim);font-size:10px" x-text="group.tasks.length"></span>
          </div>
          <div class="project-card-body">
            <template x-for="task in group.tasks" :key="task.id">
              <div class="comp-card-row"
                   draggable="true"
                   :class="{'is-dragging': drag.id===task.id && drag.type==='completed', 'drag-insert-before': dragOverId==='comp_'+task.id}"
                   @dragstart="onCompletedDragStart($event, task)"
                   @dragend="onDragEnd()"
                   @dragover.prevent="onCompletedDragOver($event, task)"
                   @drop.prevent="onCompletedDrop($event, task)">
                <div class="comp-date" x-text="formatShortDate(task.completed_at)"></div>
                <div class="comp-body">
                  <div class="comp-title task-title-btn" x-text="task.title" :title="task.title" @click.stop="openEditTaskModal(task)"></div>
                  <template x-if="task.completion_note">
                    <div class="comp-note" x-text="task.completion_note"></div>
                  </template>
                  <div class="comp-pri"
                    x-text="task.priority==='ASAP'?'!! ASAP':task.priority==='Soon'?'◈ Soon':'· Backlog'"></div>
                </div>
                <div class="comp-card-actions">
                  <button class="task-btn btn-sm" @click="reopenTask(task.id)" title="Reopen">↺</button>
                  <button class="task-btn btn-sm" @click="deleteTask(task.id)" title="Delete">✕</button>
                </div>
              </div>
            </template>
          </div>
        </div>
      </template>
    </div>
  </div>

</div><!-- .content-wrap -->
</main>

<!-- ── MODALS ────────────────────────────────────────────────────────────── -->

<!-- Add task (global) -->
<div class="modal-backdrop" x-show="modal==='add-task'" x-cloak @click.self="modal=null">
  <div class="modal">
    <div class="modal-header">
      <span>─ ADD TASK ──────────────────────</span>
      <button class="task-btn" @click="modal=null">✕</button>
    </div>
    <div class="modal-body">
      <div class="modal-field">
        <label class="modal-label">Task title:</label>
        <input class="modal-input" type="text" x-model="newAnyTask.title"
          @keydown.enter="submitAddAnyTask()"
          @keydown.escape="modal=null"
          placeholder="what needs doing..."
          x-init="$nextTick(()=>$el.focus())">
      </div>
      <div class="modal-field">
        <label class="modal-label">Project:</label>
        <template x-if="!showNewProjectInline">
          <div>
            <template x-if="projects.length===0">
              <p style="color:var(--text-dim);font-size:11px;padding:6px 0">no projects yet — create one below</p>
            </template>
            <template x-if="projects.length>0">
              <select class="modal-select" x-model="newAnyTask.projectId" style="margin-bottom:6px">
                <template x-for="p in projects" :key="p.id">
                  <option :value="p.id" x-text="p.name"></option>
                </template>
              </select>
            </template>
            <button class="btn-text" style="font-size:10px" @click="showNewProjectInline=true">+ NEW PROJECT</button>
          </div>
        </template>
        <template x-if="showNewProjectInline">
          <div style="border:1px solid var(--border);padding:12px;background:var(--bg-raised)">
            <label class="modal-label">Project name:</label>
            <input class="modal-input" type="text" x-model="newProjectInlineName"
              placeholder="project name..." style="margin-bottom:10px"
              @keydown.enter="createProjectAndSelectInModal()"
              @keydown.escape="showNewProjectInline=false"
              x-init="$nextTick(()=>$el.focus())">
            <label class="modal-label">Colour:</label>
            <div class="colour-swatches" style="margin-bottom:10px">
              <template x-for="c in colourSwatches" :key="c">
                <div class="colour-swatch" :class="{selected:newProjectInlineColour===c}"
                  :style="'background:'+c" @click="newProjectInlineColour=c"></div>
              </template>
            </div>
            <div style="display:flex;gap:6px">
              <button class="btn btn-sm" @click="showNewProjectInline=false; newProjectInlineName=''">CANCEL</button>
              <button class="btn btn-accent btn-sm" @click="createProjectAndSelectInModal()">CREATE + SELECT</button>
            </div>
          </div>
        </template>
      </div>
      <div class="modal-field">
        <label class="modal-label">Priority:</label>
        <select class="modal-select" x-model="newAnyTask.priority">
          <option value="ASAP">!! ASAP</option>
          <option value="Soon">◈ Soon</option>
          <option value="Backlog">· Backlog</option>
          <option value="No Priority">— No Priority</option>
        </select>
      </div>
      <div class="modal-field">
        <label class="modal-label">Description (optional):</label>
        <textarea class="modal-textarea" x-model="newAnyTask.description"
          placeholder="additional details..." style="min-height:60px"></textarea>
      </div>
      <div class="modal-field" style="margin-bottom:0">
        <label class="modal-label">Subtasks:</label>
        <div class="modal-subtask-list">
          <template x-for="(sub, si) in newAnyTask.subtasks" :key="sub._cid">
            <div class="modal-subtask-item">
              <span class="modal-subtask-prefix">└─</span>
              <input class="modal-input" type="text" x-model="sub.title" style="flex:1">
              <button class="task-btn" @click="removeNewSubtask(si)">✕</button>
            </div>
          </template>
        </div>
        <div style="display:flex;gap:6px">
          <input class="modal-input" type="text" x-model="pendingSubtask"
            placeholder="add subtask..." style="flex:1"
            @keydown.enter.prevent="addSubtaskToNew()">
          <button class="btn btn-sm" @click="addSubtaskToNew()">+</button>
        </div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn" @click="modal=null">CANCEL</button>
      <button class="btn btn-accent" @click="submitAddAnyTask()"
        :disabled="!newAnyTask.title.trim() || !newAnyTask.projectId">ADD TASK</button>
    </div>
  </div>
</div>

<!-- Edit task -->
<div class="modal-backdrop" x-show="modal==='edit-task'" x-cloak @click.self="modal=null">
  <div class="modal">
    <div class="modal-header">
      <span>─ EDIT TASK ─────────────────────</span>
      <button class="task-btn" @click="modal=null">✕</button>
    </div>
    <div class="modal-body">
      <div class="modal-field">
        <label class="modal-label">Task title:</label>
        <input class="modal-input" type="text" x-model="editTaskForm.title"
          @keydown.enter="submitEditTask()"
          @keydown.escape="modal=null"
          x-init="$nextTick(()=>{ if(modal==='edit-task') $el.focus(); })">
      </div>
      <div class="modal-field">
        <label class="modal-label">Priority:</label>
        <select class="modal-select" x-model="editTaskForm.priority">
          <option value="ASAP">!! ASAP</option>
          <option value="Soon">◈ Soon</option>
          <option value="Backlog">· Backlog</option>
          <option value="No Priority">— No Priority</option>
        </select>
      </div>
      <div class="modal-field">
        <label class="modal-label">Project:</label>
        <select class="modal-select" x-model="editTaskForm.projectId">
          <template x-for="p in projects" :key="p.id">
            <option :value="p.id" x-text="p.name"></option>
          </template>
        </select>
      </div>
      <div class="modal-field">
        <label class="modal-label">Description (optional):</label>
        <textarea class="modal-textarea" x-model="editTaskForm.description"
          placeholder="additional details..." style="min-height:60px"></textarea>
      </div>
      <div class="modal-field" style="margin-bottom:0">
        <label class="modal-label">Subtasks:</label>
        <div class="modal-subtask-list">
          <template x-for="(sub, si) in editTaskForm.subtasks" :key="sub.id || sub._cid">
            <div class="modal-subtask-item">
              <span class="modal-subtask-prefix">└─</span>
              <input class="modal-input" type="text" x-model="sub.title" style="flex:1">
              <button class="task-btn" @click="removeEditSubtask(si)">✕</button>
            </div>
          </template>
        </div>
        <div style="display:flex;gap:6px">
          <input class="modal-input" type="text" x-model="pendingSubtask"
            placeholder="add subtask..." style="flex:1"
            @keydown.enter.prevent="addSubtaskToEdit()">
          <button class="btn btn-sm" @click="addSubtaskToEdit()">+</button>
        </div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn" @click="modal=null">CANCEL</button>
      <button class="btn btn-accent" @click="submitEditTask()"
        :disabled="!editTaskForm.title.trim()">SAVE CHANGES</button>
    </div>
  </div>
</div>

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
          <div class="colour-swatch" :class="{selected:newProjectColour===c}" :style="'background:'+c" @click="newProjectColour=c"></div>
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
          <div class="colour-swatch" :class="{selected:editProjectColour===c}" :style="'background:'+c" @click="editProjectColour=c"></div>
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
          <tr><th>ID</th><th>LABEL</th><th>LAST SEEN</th><th>REGISTERED</th><th></th></tr>
        </thead>
        <tbody>
          <template x-for="dev in devices" :key="dev.id">
            <tr>
              <td style="color:var(--text-muted)" x-text="String(dev.id).padStart(2,'0')"></td>
              <td>
                <template x-if="dev.is_current">
                  <span><span class="current-dot"></span><span x-text="dev.device_label"></span><span style="color:var(--text-dim);font-size:10px"> (this device)</span></span>
                </template>
                <template x-if="!dev.is_current"><span x-text="dev.device_label"></span></template>
              </td>
              <td style="color:var(--text-muted);font-size:11px" x-text="relativeTime(dev.last_seen)"></td>
              <td style="color:var(--text-dim);font-size:11px" x-text="dev.created_at.substring(0,10)"></td>
              <td>
                <div style="display:flex;gap:4px">
                  <button class="btn btn-sm" @click="promptLabelDevice(dev)">LABEL</button>
                  <template x-if="!dev.is_current">
                    <button class="btn btn-sm" style="color:var(--accent);border-color:var(--accent)" @click="revokeDevice(dev.id)">REVOKE</button>
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

<!-- Project view -->
<div class="modal-backdrop" x-show="modal==='project-view'" x-cloak @click.self="modal=null">
  <div class="modal" style="max-width:660px;max-height:85vh">
    <div class="modal-header" :style="'border-left:3px solid '+(projectViewProject ? projectViewProject.colour : 'var(--accent)')">
      <span x-text="projectViewProject ? projectViewProject.name : ''"></span>
      <button class="task-btn" @click="modal=null">✕</button>
    </div>
    <div class="modal-body" style="padding:0">

      <template x-if="projectViewTasks.length===0 && !loading">
        <div style="padding:12px 16px;font-size:11px;color:var(--text-dim)">· no tasks yet</div>
      </template>

      <template x-for="(task, i) in projectViewTasks" :key="task.id">
        <div>
          <div class="pv-task-row"
               draggable="true"
               :class="{'is-dragging': pvDrag.id===task.id, 'drag-insert-before': pvDragOver===task.id, 'pv-done': task.status==='completed'}"
               @dragstart.stop="onPVDragStart($event, task)"
               @dragend="onPVDragEnd()"
               @dragover.prevent.stop="onPVDragOver($event, task)"
               @drop.prevent.stop="onPVDrop($event, task)">
            <span class="pv-drag-handle">⠿</span>
            <span class="pv-task-num" x-text="(i+1)+'.'"></span>
            <div class="pv-task-actions">
              <template x-if="task.status==='active'">
                <button class="task-btn success btn-sm" @click.stop="openCompleteModal(task)">✓</button>
              </template>
              <template x-if="task.status==='completed'">
                <button class="task-btn btn-sm" @click.stop="reopenInProjectView(task.id)" title="Reopen">↺</button>
              </template>
              <button class="task-btn btn-sm" @click.stop="deleteTask(task.id)">✕</button>
            </div>
            <div class="pv-task-info">
              <div class="pv-task-title" x-text="task.title" @click.stop="openEditTaskModal(task)"></div>
              <template x-if="task.description">
                <div class="pv-task-meta" x-text="task.description"></div>
              </template>
              <div class="pv-task-meta"
                x-text="task.priority==='ASAP'?'!! ASAP':task.priority==='Soon'?'◈ Soon':task.priority==='Backlog'?'· Backlog':'— No Priority'"></div>
            </div>
          </div>
          <template x-for="sub in task.subtasks||[]" :key="sub.id">
            <div class="pv-task-row subtask" :class="{'pv-done': sub.status==='completed'}">
              <span class="pv-drag-handle" style="visibility:hidden">⠿</span>
              <span class="pv-task-num">└─</span>
              <div class="pv-task-actions">
                <template x-if="sub.status==='active'">
                  <button class="task-btn success btn-sm" @click.stop="openCompleteModal(sub)">✓</button>
                </template>
                <template x-if="sub.status==='completed'">
                  <button class="task-btn btn-sm" @click.stop="reopenInProjectView(sub.id)" title="Reopen">↺</button>
                </template>
                <button class="task-btn btn-sm" @click.stop="deleteTask(sub.id)">✕</button>
              </div>
              <div class="pv-task-info">
                <div class="pv-task-title" x-text="sub.title" @click.stop="openEditTaskModal(task)"></div>
                <template x-if="sub.description">
                  <div class="pv-task-meta" x-text="sub.description"></div>
                </template>
              </div>
            </div>
          </template>
        </div>
      </template>

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
    projectCompletedCache: {},
    projectViewProject: null,
    projectViewTasks: [],
    projectViewActive: false,
    pvDrag: { id: null },
    pvDragOver: null,
    priorityTasks: { ASAP: [], Soon: [], Backlog: [] },
    completedTasks: [],
    loading: false,
    modal: null,
    modalTask: null,
    completeNote: '',
    historyTask: null,
    historyLog: [],
    editTaskForm: { id: null, title: '', priority: 'Soon', projectId: null, description: '', subtasks: [], subtasksToDelete: [] },
    mobileMenuOpen: false,
    openDropdownId: null,
    addingTaskTo: null,
    newTaskTitle: '',
    newTaskPriority: 'Soon',
    newAnyTask: { title: '', projectId: null, priority: 'Soon', description: '', subtasks: [] },
    pendingSubtask: '',
    showNewProjectInline: false,
    newProjectInlineName: '',
    newProjectInlineColour: '#FF6777',
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

    // ── Getter: completed tasks grouped by project ────────────────────────
    get completedByProject() {
      const map = {};
      for (const task of this.completedTasks) {
        const pid = task.project_id;
        if (!map[pid]) map[pid] = { id: pid, name: task.project_name, colour: task.project_colour, tasks: [] };
        map[pid].tasks.push(task);
      }
      return Object.values(map);
    },

    // ── Init ──────────────────────────────────────────────────────────────
    async init() {
      await Promise.all([this.fetchProjects(), this.fetchPriorityTasks()]);
    },

    // ── View switching ────────────────────────────────────────────────────
    async switchView(v) {
      this.view = v;
      this.modal = null;
      if (v === 'priority')  await Promise.all([this.fetchProjects(), this.fetchPriorityTasks()]);
      if (v === 'dashboard') { await this.fetchProjects(); await this.fetchAllProjectTasks(); }
      if (v === 'archive')   await this.fetchArchivedProjects();
      if (v === 'completed') await this.fetchCompletedTasks();
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
      const tCache = {};
      const cCache = {};
      await Promise.all(this.projects.map(async p => {
        const [active, completed] = await Promise.all([
          this.api('get_tasks', { project_id: p.id }),
          this.api('get_project_completed', { project_id: p.id, limit: 3 }),
        ]);
        if (active && !active.error)     tCache[p.id] = active;
        if (completed && !completed.error) cCache[p.id] = completed;
      }));
      this.projectTasksCache    = tCache;
      this.projectCompletedCache = cCache;
    },
    async fetchProjectTasks(pid) {
      const [active, completed] = await Promise.all([
        this.api('get_tasks', { project_id: pid }),
        this.api('get_project_completed', { project_id: pid, limit: 3 }),
      ]);
      if (active && !active.error)     this.projectTasksCache    = { ...this.projectTasksCache, [pid]: active };
      if (completed && !completed.error) this.projectCompletedCache = { ...this.projectCompletedCache, [pid]: completed };
    },

    // ── Project view ─────────────────────────────────────────────────────
    async openProjectView(project) {
      this.projectViewProject = project;
      this.projectViewTasks   = [];
      this.modal = 'project-view';
      await this._fetchProjectViewData(project.id);
    },
    async _fetchProjectViewData(pid) {
      const data = await this.api('get_project_tasks_all', { project_id: pid });
      if (data && !data.error) this.projectViewTasks = data;
    },
    async reopenInProjectView(taskId) {
      const ok = await this.apiPost('reopen_task', { id: taskId });
      if (ok) {
        this.showNotification('↺ task reopened', 'info');
        await this._fetchProjectViewData(this.projectViewProject.id);
      }
    },
    onPVDragStart(e, task) {
      e.dataTransfer.effectAllowed = 'move';
      e.dataTransfer.setData('text/plain', String(task.id));
      this.pvDrag = { id: task.id };
    },
    onPVDragOver(e, task) {
      if (!this.pvDrag.id || this.pvDrag.id === task.id) return;
      this.pvDragOver = task.id;
    },
    onPVDrop(e, task) {
      if (!this.pvDrag.id || this.pvDrag.id === task.id) { this.onPVDragEnd(); return; }
      const dragId = this.pvDrag.id, targetId = task.id;
      this.onPVDragEnd();
      const arr = [...this.projectViewTasks];
      const fi  = arr.findIndex(t => t.id == dragId);
      const ti  = arr.findIndex(t => t.id == targetId);
      if (fi === -1 || ti === -1) return;
      const [item] = arr.splice(fi, 1);
      arr.splice(ti, 0, item);
      this.projectViewTasks = arr;
      this.apiPost('reorder_tasks', { order: arr.map(t => t.id) });
    },
    onPVDragEnd() {
      this.pvDrag    = { id: null };
      this.pvDragOver = null;
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
      this.projectViewActive = (this.modal === 'project-view');
      this.modalTask    = task;
      this.completeNote = '';
      this.modal        = 'complete';
    },
    async confirmComplete() {
      if (!this.modalTask) return;
      const ok = await this.apiPost('complete_task', { id: this.modalTask.id, note: this.completeNote });
      if (ok) {
        this.showNotification('✓ task marked complete', 'success');
        if (this.projectViewActive) {
          this.projectViewActive = false;
          this.modal = 'project-view';
          await this._fetchProjectViewData(this.projectViewProject.id);
        } else {
          this.modal = null;
          await this.refresh();
        }
      }
    },
    async reopenTask(id) {
      const ok = await this.apiPost('reopen_task', { id });
      if (ok) {
        this.showNotification('↺ task reopened', 'info');
        await this.fetchCompletedTasks();
        await this.fetchPriorityTasks();
      }
    },

    // ── Add task (global modal) ───────────────────────────────────────────
    openAddTaskModal() {
      this.newAnyTask = {
        title: '',
        projectId: this.projects[0] ? this.projects[0].id : null,
        priority: 'Soon',
        description: '',
        subtasks: [],
      };
      this.pendingSubtask         = '';
      this.showNewProjectInline   = false;
      this.newProjectInlineName   = '';
      this.newProjectInlineColour = '#FF6777';
      this.modal = 'add-task';
    },
    async createProjectAndSelectInModal() {
      if (!this.newProjectInlineName.trim()) return;
      const res = await this.apiPost('create_project', {
        name:   this.newProjectInlineName.trim(),
        colour: this.newProjectInlineColour,
      });
      if (res && res.id) {
        await this.fetchProjects();
        this.newAnyTask.projectId   = res.id;
        this.showNewProjectInline   = false;
        this.newProjectInlineName   = '';
        this.newProjectInlineColour = '#FF6777';
        this.showNotification('project created', 'success');
      }
    },
    async submitAddAnyTask() {
      if (!this.newAnyTask.title.trim() || !this.newAnyTask.projectId) return;
      const res = await this.apiPost('create_task', {
        project_id:  this.newAnyTask.projectId,
        title:       this.newAnyTask.title.trim(),
        priority:    this.newAnyTask.priority,
        description: this.newAnyTask.description.trim() || null,
      });
      if (res) {
        for (const sub of this.newAnyTask.subtasks) {
          if (sub.title.trim()) {
            await this.apiPost('create_task', {
              project_id:     parseInt(this.newAnyTask.projectId),
              parent_task_id: res.id,
              title:          sub.title.trim(),
              priority:       this.newAnyTask.priority,
            });
          }
        }
        this.modal = null;
        this.showNotification('task added', 'success');
        await this.refresh();
      }
    },

    // ── Edit task modal ───────────────────────────────────────────────────
    openEditTaskModal(task) {
      this.projectViewActive = (this.modal === 'project-view');
      this.editTaskForm = {
        id: task.id, title: task.title, priority: task.priority,
        projectId: task.project_id, description: task.description || '',
        subtasks: (task.subtasks || []).filter(s => s.status !== 'completed').map(s => ({ id: s.id, _cid: s.id, title: s.title })),
        subtasksToDelete: [],
      };
      this.pendingSubtask = '';
      this.modal = 'edit-task';
    },
    async submitEditTask() {
      if (!this.editTaskForm.title.trim()) return;
      const ok = await this.apiPost('update_task', {
        id:          this.editTaskForm.id,
        title:       this.editTaskForm.title.trim(),
        priority:    this.editTaskForm.priority,
        project_id:  parseInt(this.editTaskForm.projectId),
        description: this.editTaskForm.description.trim() || null,
      });
      if (ok) {
        for (const id of (this.editTaskForm.subtasksToDelete || [])) {
          await this.apiPost('delete_task', { id });
        }
        for (const sub of this.editTaskForm.subtasks) {
          if (!sub.title.trim()) continue;
          if (sub.id === null) {
            await this.apiPost('create_task', {
              project_id:     parseInt(this.editTaskForm.projectId),
              parent_task_id: this.editTaskForm.id,
              title:          sub.title.trim(),
              priority:       this.editTaskForm.priority,
            });
          } else {
            await this.apiPost('update_task', {
              id:         sub.id,
              title:      sub.title.trim(),
              priority:   this.editTaskForm.priority,
              project_id: parseInt(this.editTaskForm.projectId),
            });
          }
        }
        this.showNotification('task updated', 'success');
        if (this.projectViewActive) {
          this.projectViewActive = false;
          this.modal = 'project-view';
          await this._fetchProjectViewData(this.projectViewProject.id);
        } else {
          this.modal = null;
          await this.refresh();
        }
      }
    },

    addSubtaskToNew() {
      if (!this.pendingSubtask.trim()) return;
      this.newAnyTask.subtasks.push({ _cid: Date.now(), title: this.pendingSubtask.trim() });
      this.pendingSubtask = '';
    },
    addSubtaskToEdit() {
      if (!this.pendingSubtask.trim()) return;
      this.editTaskForm.subtasks.push({ id: null, _cid: Date.now(), title: this.pendingSubtask.trim() });
      this.pendingSubtask = '';
    },
    removeNewSubtask(si) {
      this.newAnyTask.subtasks.splice(si, 1);
    },
    removeEditSubtask(si) {
      const sub = this.editTaskForm.subtasks[si];
      if (sub && sub.id) this.editTaskForm.subtasksToDelete.push(sub.id);
      this.editTaskForm.subtasks.splice(si, 1);
    },

    // ── Delete / move task ────────────────────────────────────────────────
    async deleteTask(id) {
      if (!confirm('Delete this task?')) return;
      await this.apiPost('delete_task', { id });
      this.showNotification('task deleted', 'info');
      if (this.modal === 'project-view') {
        await this._fetchProjectViewData(this.projectViewProject.id);
      } else {
        await this.refresh();
        if (this.view === 'completed') await this.fetchCompletedTasks();
      }
    },
    async moveTask(id, projectId) {
      const task = this.findTask(id);
      if (!task) return;
      await this.apiPost('update_task', { id, title: task.title, priority: task.priority, project_id: projectId });
      this.showNotification('task moved', 'info');
      await this.refresh();
    },

    // ── Add task inline (dashboard card) ──────────────────────────────────
    startAddTask(pid) {
      this.addingTaskTo   = pid;
      this.newTaskTitle   = '';
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
    moveProjectUp(projectId) {
      const arr = [...this.projects];
      const i   = arr.findIndex(p => p.id == projectId);
      if (i <= 0) return;
      [arr[i - 1], arr[i]] = [arr[i], arr[i - 1]];
      this.projects = arr;
      this.apiPost('reorder_projects', { order: arr.map(p => p.id) });
    },
    moveProjectDown(projectId) {
      const arr = [...this.projects];
      const i   = arr.findIndex(p => p.id == projectId);
      if (i === -1 || i >= arr.length - 1) return;
      [arr[i], arr[i + 1]] = [arr[i + 1], arr[i]];
      this.projects = arr;
      this.apiPost('reorder_projects', { order: arr.map(p => p.id) });
    },
    movePriTaskUp(task, pri) {
      const arr = [...(this.priorityTasks[pri] || [])];
      const i   = arr.findIndex(t => t.id == task.id);
      if (i <= 0) return;
      [arr[i - 1], arr[i]] = [arr[i], arr[i - 1]];
      this.priorityTasks = { ...this.priorityTasks, [pri]: arr };
      this.apiPost('reorder_tasks', { order: arr.map(t => t.id) });
    },
    movePriTaskDown(task, pri) {
      const arr = [...(this.priorityTasks[pri] || [])];
      const i   = arr.findIndex(t => t.id == task.id);
      if (i === -1 || i >= arr.length - 1) return;
      [arr[i], arr[i + 1]] = [arr[i + 1], arr[i]];
      this.priorityTasks = { ...this.priorityTasks, [pri]: arr };
      this.apiPost('reorder_tasks', { order: arr.map(t => t.id) });
    },
    async reopenTaskInDashboard(taskId, projectId) {
      const ok = await this.apiPost('reopen_task', { id: taskId });
      if (ok) {
        this.showNotification('↺ task reopened', 'info');
        await this.fetchProjectTasks(projectId);
      }
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

    // ── Drag: tasks in priority view ─────────────────────────────────────
    onTaskDragStart(e, task) {
      e.dataTransfer.effectAllowed = 'move';
      e.dataTransfer.setData('text/plain', String(task.id));
      this.drag = { id: task.id, type: 'task', priority: task.priority, projectId: task.project_id };
    },
    onSectionDragOver(e, pri) {
      if (this.drag.type !== 'task') return;
      this.dragOverPri = pri;
    },
    onSectionDragLeave(e, pri) {
      if (this.dragOverPri === pri) this.dragOverPri = null;
    },
    onSectionDrop(e, pri) {
      if (this.drag.type !== 'task') return;
      // Capture and clear drag state immediately so re-render doesn't re-apply is-dragging
      const dragId  = this.drag.id;
      const dragPri = this.drag.priority;
      this.onDragEnd();
      if (dragPri !== pri) {
        this._changeTaskPriority(dragId, pri);
      }
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
      // Capture and clear drag state immediately — prevents greyed-out remnant
      const dragId  = this.drag.id;
      const dragPri = this.drag.priority;
      this.onDragEnd();
      if (dragPri === task.priority) {
        this._reorderTasksInSection(dragId, task.id, task.priority);
      } else {
        this._changeTaskPriority(dragId, task.priority);
      }
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
      // Optimistic update
      this.priorityTasks[oldPri] = (this.priorityTasks[oldPri] || []).filter(t => t.id != taskId);
      this.priorityTasks[newPri] = [...(this.priorityTasks[newPri] || []), { ...task, priority: newPri }];
      await this.apiPost('update_task', { id: taskId, title: task.title, priority: newPri, project_id: task.project_id });
      this.showNotification('priority → ' + newPri, 'success');
      await this.fetchPriorityTasks();
    },

    // ── Drag: project cards ───────────────────────────────────────────────
    onProjDragStart(e, project) {
      e.dataTransfer.effectAllowed = 'move';
      this.drag = { id: project.id, type: 'project', priority: null, projectId: null };
    },
    onProjDragOver(e, project) {
      if (this.drag.type === 'project') {
        if (this.drag.id === project.id) return;
        this.dragOverId = 'proj_' + project.id;
      } else if (this.drag.type === 'dashboard-task') {
        if (this.drag.projectId === project.id) return;
        this.dragOverId = 'proj_' + project.id;
      }
    },
    onProjDrop(e, project) {
      if (this.drag.type === 'project') {
        if (this.drag.id === project.id) { this.dragOverId = null; return; }
        const arr = [...this.projects];
        const fi  = arr.findIndex(p => p.id == this.drag.id);
        const ti  = arr.findIndex(p => p.id == project.id);
        this.onDragEnd();
        if (fi !== -1 && ti !== -1) {
          const [item] = arr.splice(fi, 1);
          arr.splice(ti, 0, item);
          this.projects = arr;
          this.apiPost('reorder_projects', { order: arr.map(p => p.id) });
        }
      } else if (this.drag.type === 'dashboard-task') {
        if (this.drag.projectId === project.id) { this.onDragEnd(); return; }
        const dragId = this.drag.id;
        this.onDragEnd();
        this._moveTaskToProject(dragId, project.id);
      }
    },

    // ── Drag: completed board ─────────────────────────────────────────────
    onCompletedDragStart(e, task) {
      e.dataTransfer.effectAllowed = 'move';
      this.drag = { id: task.id, type: 'completed', priority: null, projectId: task.project_id };
    },
    onCompletedDragOver(e, task) {
      if (this.drag.type !== 'completed' || this.drag.id === task.id) return;
      if (this.drag.projectId !== task.project_id) return; // same-card only
      this.dragOverId = 'comp_' + task.id;
    },
    onCompletedDrop(e, task) {
      if (this.drag.type !== 'completed' || this.drag.id === task.id) { this.dragOverId = null; return; }
      if (this.drag.projectId !== task.project_id) { this.dragOverId = null; return; }
      const arr = [...this.completedTasks];
      const fi  = arr.findIndex(t => t.id == this.drag.id);
      const ti  = arr.findIndex(t => t.id == task.id);
      this.onDragEnd();
      if (fi !== -1 && ti !== -1) {
        const [item] = arr.splice(fi, 1);
        arr.splice(ti, 0, item);
        this.completedTasks = arr;
        this.apiPost('reorder_tasks', { order: arr.map(t => t.id) });
      }
    },

    onDragEnd() {
      this.drag       = { id: null, type: null, priority: null, projectId: null };
      this.dragOverId  = null;
      this.dragOverPri = null;
    },

    // ── Drag: dashboard task rows ─────────────────────────────────────────
    onDashTaskDragStart(e, task, projectId) {
      e.dataTransfer.effectAllowed = 'move';
      this.drag = { id: task.id, type: 'dashboard-task', priority: task.priority, projectId: projectId };
    },
    onDashTaskDragOver(e, task, projectId) {
      if (this.drag.type !== 'dashboard-task' || this.drag.id === task.id) return;
      if (this.drag.projectId === projectId) {
        this.dragOverId = task.id;
      } else {
        this.dragOverId = 'proj_' + projectId;
      }
    },
    onDashTaskDrop(e, task, projectId) {
      if (this.drag.type !== 'dashboard-task' || this.drag.id === task.id) { this.onDragEnd(); return; }
      const dragId        = this.drag.id;
      const dragProjectId = this.drag.projectId;
      this.onDragEnd();
      if (dragProjectId === projectId) {
        this._reorderDashboardTasks(dragId, task.id, projectId);
      } else {
        this._moveTaskToProject(dragId, projectId);
      }
    },
    _reorderDashboardTasks(dragId, targetId, projectId) {
      const arr = [...(this.projectTasksCache[projectId] || [])];
      const fi  = arr.findIndex(t => t.id == dragId);
      const ti  = arr.findIndex(t => t.id == targetId);
      if (fi === -1 || ti === -1) return;
      const [item] = arr.splice(fi, 1);
      arr.splice(ti, 0, item);
      this.projectTasksCache = { ...this.projectTasksCache, [projectId]: arr };
      this.apiPost('reorder_tasks', { order: arr.map(t => t.id) });
    },
    async _moveTaskToProject(taskId, newProjectId) {
      let task = null;
      for (const pid of Object.keys(this.projectTasksCache)) {
        task = (this.projectTasksCache[pid] || []).find(t => t.id == taskId);
        if (task) break;
      }
      if (!task) task = this.findTask(taskId);
      if (!task) return;
      await this.apiPost('update_task', { id: taskId, title: task.title, priority: task.priority, project_id: parseInt(newProjectId) });
      this.showNotification('task moved', 'info');
      await this.refresh();
    },

    // ── API ───────────────────────────────────────────────────────────────
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
      if (diff < 86400)  return 'today';
      if (diff < 172800) return 'yesterday';
      return d.toLocaleDateString('en-GB', { day:'2-digit', month:'short' });
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

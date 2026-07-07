<?php
require_once __DIR__ . '/config.php';

function currentlyAuthed(): bool {
    $raw = $_COOKIE[COOKIE_NAME] ?? null;
    if (!$raw) return false;
    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $stmt = $pdo->prepare("SELECT id FROM admin_sessions WHERE token_hash = :h LIMIT 1");
        $stmt->execute([':h' => hash('sha256', $raw)]);
        return (bool)$stmt->fetch();
    } catch (Exception $e) {
        return false;
    }
}

if (currentlyAuthed()) {
    header('Location: app.php');
    exit;
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  :root {
    --bg:         #0a0a0a;
    --bg-surface: #111111;
    --bg-raised:  #1a1a1a;
    --border:     #2a2a2a;
    --accent:     #FF6777;
    --text:       #f0f0f0;
    --text-muted: #666666;
    --success:    #4caf84;
  }

  body {
    background: var(--bg);
    color: var(--text);
    font-family: 'JetBrains Mono', monospace;
    font-size: 13px;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
  }

  .login-box { border: 1px solid var(--border); min-width: 360px; max-width: 420px; }

  .login-box-top {
    border-bottom: 1px solid var(--border);
    padding: 10px 16px;
    color: var(--accent);
    letter-spacing: 0.1em;
    text-transform: uppercase;
    font-size: 12px;
    font-weight: 700;
  }

  .login-box-body { padding: 28px 24px; }

  .login-subtitle { color: var(--text-muted); margin-bottom: 24px; font-size: 12px; line-height: 1.6; }

  .login-field { display: flex; flex-direction: column; gap: 6px; margin-bottom: 20px; }

  .login-field label {
    color: var(--text-muted); font-size: 11px; letter-spacing: 0.1em; text-transform: uppercase;
  }

  .key-drop {
    background: var(--bg-raised);
    border: 1px dashed var(--border);
    color: var(--text-muted);
    font-family: 'JetBrains Mono', monospace;
    font-size: 12px;
    padding: 16px 10px;
    text-align: center;
    cursor: pointer;
    transition: border-color 120ms ease, color 120ms ease;
  }
  .key-drop:hover, .key-drop.has-file { border-color: var(--accent); color: var(--text); }
  .key-drop input { display: none; }

  .login-submit {
    background: transparent;
    border: 1px solid var(--border);
    color: var(--text);
    font-family: 'JetBrains Mono', monospace;
    font-size: 12px;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    padding: 8px 20px;
    cursor: pointer;
    width: 100%;
    transition: border-color 120ms ease, color 120ms ease;
  }
  .login-submit:hover:not(:disabled) { border-color: var(--accent); color: var(--accent); }
  .login-submit:disabled { opacity: 0.4; cursor: default; }

  .login-error   { color: var(--accent); font-size: 12px; margin-top: 14px; }
  .login-success { color: var(--success); font-size: 12px; margin-top: 14px; }

  ::-webkit-scrollbar { width: 4px; }
  ::-webkit-scrollbar-track { background: var(--bg); }
  ::-webkit-scrollbar-thumb { background: var(--border); }
</style>
</head>
<body>
<div class="login-box">
  <div class="login-box-top">─ ADMIN ──────────────────────</div>
  <div class="login-box-body">
    <p class="login-subtitle">Select your private key file. It's read and used to sign a challenge entirely in this browser tab — the file itself is never uploaded anywhere.</p>
    <form id="login-form">
      <div class="login-field">
        <label>private key file:</label>
        <label class="key-drop" id="key-drop">
          <span id="key-drop-label">click to choose a file...</span>
          <input type="file" id="key-file" accept=".pem,.key,.txt">
        </label>
      </div>
      <button type="submit" class="login-submit" id="submit-btn" disabled>ENTER  →</button>
      <p class="login-error"   id="msg-error"   style="display:none"></p>
      <p class="login-success" id="msg-success" style="display:none"></p>
    </form>
  </div>
</div>

<script>
const fileInput   = document.getElementById('key-file');
const dropLabel    = document.getElementById('key-drop');
const dropLabelTxt = document.getElementById('key-drop-label');
const submitBtn    = document.getElementById('submit-btn');
const errorEl      = document.getElementById('msg-error');
const successEl    = document.getElementById('msg-success');
const form         = document.getElementById('login-form');

fileInput.addEventListener('change', () => {
  if (fileInput.files.length) {
    dropLabelTxt.textContent = fileInput.files[0].name;
    dropLabel.classList.add('has-file');
    submitBtn.disabled = false;
  }
});

function showError(msg) {
  errorEl.textContent = msg;
  errorEl.style.display = 'block';
  successEl.style.display = 'none';
}
function showSuccess(msg) {
  successEl.textContent = msg;
  successEl.style.display = 'block';
  errorEl.style.display = 'none';
}

function pemToDer(pemText) {
  const b64 = pemText.replace(/-----BEGIN [^-]+-----/, '').replace(/-----END [^-]+-----/, '').replace(/\s+/g, '');
  const raw = atob(b64);
  const bytes = new Uint8Array(raw.length);
  for (let i = 0; i < raw.length; i++) bytes[i] = raw.charCodeAt(i);
  return bytes.buffer;
}

function bufferToBase64(buf) {
  let binary = '';
  const bytes = new Uint8Array(buf);
  for (let i = 0; i < bytes.byteLength; i++) binary += String.fromCharCode(bytes[i]);
  return btoa(binary);
}

async function getChallenge() {
  const r = await fetch('api.php?action=get_challenge');
  const data = await r.json();
  return data.nonce;
}

form.addEventListener('submit', async (e) => {
  e.preventDefault();
  submitBtn.disabled = true;
  submitBtn.textContent = 'VERIFYING...';
  try {
    const file = fileInput.files[0];
    if (!file) throw new Error('choose a key file first');

    const pemText = await file.text();
    const der = pemToDer(pemText);

    let key;
    try {
      key = await crypto.subtle.importKey('pkcs8', der, { name: 'ECDSA', namedCurve: 'P-256' }, false, ['sign']);
    } catch (err) {
      throw new Error('that file is not a valid PKCS8 EC private key');
    }

    const nonce = await getChallenge();
    const data = new TextEncoder().encode(nonce);
    const sigBuf = await crypto.subtle.sign({ name: 'ECDSA', hash: 'SHA-256' }, key, data);
    const signature = bufferToBase64(sigBuf);

    const res = await fetch('api.php?action=login', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ nonce, signature }),
    });
    const result = await res.json();
    if (res.ok && result.ok) {
      showSuccess('verified — entering...');
      window.location.href = 'app.php';
    } else {
      throw new Error(result.error || 'login failed');
    }
  } catch (err) {
    showError(err.message || String(err));
    submitBtn.disabled = false;
    submitBtn.textContent = 'ENTER  →';
  }
});
</script>
</body>
</html>

<?php
require_once __DIR__ . '/config.php';

session_start();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    if (password_verify($password, APP_PASSWORD)) {
        // Generate device token
        $token = bin2hex(random_bytes(32)); // 64 hex chars
        $hash  = hash('sha256', $token);

        try {
            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER, DB_PASS,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            $stmt = $pdo->prepare(
                "INSERT INTO device_tokens (token_hash, device_label, last_seen, created_at)
                 VALUES (:hash, 'Unnamed Device', NOW(), NOW())"
            );
            $stmt->execute([':hash' => $hash]);

            $expires = time() + (30 * 24 * 60 * 60);
            setcookie(COOKIE_NAME, $token, [
                'expires'  => $expires,
                'path'     => '/',
                'domain'   => COOKIE_DOMAIN,
                'secure'   => true,
                'httponly' => true,
                'samesite' => 'Strict',
            ]);
            header('Location: app.php');
            exit;
        } catch (PDOException $e) {
            $error = 'system error — try again';
        }
    } else {
        $error = '// invalid credentials';
    }
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Organizer</title>
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
    --accent-dim: #cc4455;
    --text:       #f0f0f0;
    --text-muted: #666666;
    --text-dim:   #444444;
    --success:    #4caf84;
    --warning:    #f0b429;
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
  }

  .login-box {
    border: 1px solid var(--border);
    padding: 0;
    min-width: 320px;
  }

  .login-box-top {
    border-bottom: 1px solid var(--border);
    padding: 10px 16px;
    color: var(--accent);
    letter-spacing: 0.1em;
    text-transform: uppercase;
    font-size: 12px;
    font-weight: 700;
  }

  .login-box-body {
    padding: 28px 24px;
  }

  .login-subtitle {
    color: var(--text-muted);
    margin-bottom: 24px;
    font-size: 12px;
  }

  .login-field {
    display: flex;
    flex-direction: column;
    gap: 6px;
    margin-bottom: 20px;
  }

  .login-field label {
    color: var(--text-muted);
    font-size: 11px;
    letter-spacing: 0.1em;
    text-transform: uppercase;
  }

  .login-field input {
    background: var(--bg-raised);
    border: 1px solid var(--border);
    color: var(--text);
    font-family: 'JetBrains Mono', monospace;
    font-size: 13px;
    padding: 8px 10px;
    outline: none;
    width: 100%;
    transition: border-color 120ms ease;
  }

  .login-field input:focus {
    border-color: var(--accent);
  }

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

  .login-submit:hover {
    border-color: var(--accent);
    color: var(--accent);
  }

  .login-error {
    color: var(--accent);
    font-size: 12px;
    margin-top: 14px;
  }

  ::-webkit-scrollbar { width: 4px; }
  ::-webkit-scrollbar-track { background: var(--bg); }
  ::-webkit-scrollbar-thumb { background: var(--border); }
</style>
</head>
<body>
<div class="login-box">
  <div class="login-box-top">─ ORGANIZER ─────────────────</div>
  <div class="login-box-body">
    <p class="login-subtitle">access restricted</p>
    <form method="POST" action="">
      <div class="login-field">
        <label for="password">password:</label>
        <input type="password" id="password" name="password" autofocus autocomplete="current-password">
      </div>
      <button type="submit" class="login-submit">ENTER  →</button>
      <?php if ($error): ?>
        <p class="login-error"><?= htmlspecialchars($error) ?></p>
      <?php endif; ?>
    </form>
  </div>
</div>
</body>
</html>

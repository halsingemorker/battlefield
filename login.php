<?php
require_once 'config.php';
session_name(SESSION_NAME);
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['password']) && $_POST['password'] === SITE_PASSWORD) {
        $_SESSION['authenticated'] = true;
        header('Location: index.html');
        exit;
    } else {
        $error = 'Wrong password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/x-icon" href="favicon.ico">
<link rel="shortcut icon" type="image/x-icon" href="favicon.ico">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ConcertsDB by MorciMetálciGrupci — Login</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=IBM+Plex+Mono:wght@400;500&family=IBM+Plex+Sans:wght@300;400;600&display=swap" rel="stylesheet">
<style>
  :root {
    --bg: #0a0a0a; --surface: #111111; --border: #2a2a2a;
    --accent: #ff8753; --text: #e8e8e8; --muted: #666;
  }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    background: var(--bg); color: var(--text);
    font-family: 'IBM Plex Sans', sans-serif;
    min-height: 100vh; display: flex; align-items: center; justify-content: center;
  }
  .login-box {
    background: var(--surface); border: 1px solid var(--border);
    padding: 48px; border-radius: 2px; width: 480px; text-align: center;
  }
  .logo {
    font-family: 'Bebas Neue', sans-serif; font-size: 36px;
    letter-spacing: 3px; color: var(--accent); margin-bottom: 32px;
    text-align: center;
    position: relative;
  }
  .logo span { color: var(--muted); font-size: 16px; letter-spacing: 1px;
    font-family: 'IBM Plex Mono', monospace; margin-left: 8px; }
  input[type="password"] {
    width: 100%; background: var(--bg); border: 1px solid var(--border);
    color: var(--text); font-family: 'IBM Plex Mono', monospace;
    font-size: 14px; padding: 12px 16px; border-radius: 2px; outline: none;
    margin-bottom: 12px; transition: border-color 0.15s;
  }
  input[type="password"]:focus { border-color: var(--accent); }
  button {
    width: 100%; background: var(--accent); border: none; color: #000;
    font-family: 'Bebas Neue', sans-serif; font-size: 20px;
    letter-spacing: 2px; padding: 12px; border-radius: 2px;
    cursor: pointer; transition: opacity 0.15s;
  }
  button:hover { opacity: 0.85; }
  .error {
    font-family: 'IBM Plex Mono', monospace; font-size: 12px;
    color: #ff4747; margin-bottom: 12px; text-align: center;
  }
</style>
</head>
<body>
<div class="login-box">
  <div class="logo">MorciMetálciGrupci's<br>ConcertsDB</div>
  <div style="font-family:'IBM Plex Mono',monospace;font-size:12px;color:var(--muted);text-align:center;margin:-16px 0 24px">v3.2</div>
  <?php if (!empty($error)): ?>
    <div class="error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <form method="POST">
    <input type="password" name="password" placeholder="Password" autofocus>
    <button type="submit">Enter</button>
  </form>
</div>
</body>
</html>

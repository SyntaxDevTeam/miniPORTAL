<?php

declare(strict_types=1);

use SyntaxDevTeam\Cms\Core\Autoloader;
use SyntaxDevTeam\Cms\Installer\Installer;

require_once __DIR__ . '/core/Autoloader.php';
require_once __DIR__ . '/installer/Installer.php';

Autoloader::register();

$https = ($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off';
session_name('MINIPORTALINSTALL');
session_set_cookie_params([
    'httponly' => true,
    'secure' => $https,
    'samesite' => 'Strict',
]);
session_start();

$nonce = base64_encode(random_bytes(18));
header('Content-Type: text/html; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'none'; style-src 'nonce-{$nonce}'; img-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");

$installer = new Installer(__DIR__);
$checks = $installer->preflight();
$modules = $installer->moduleOptions();
$token = $_SESSION['installer_csrf'] ??= bin2hex(random_bytes(32));
$error = '';
$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($token, (string) ($_POST['_token'] ?? ''))) {
        $error = 'Sesja formularza wygasła. Odśwież stronę i spróbuj ponownie.';
    } else {
        try {
            $result = $installer->install($_POST);
            unset($_SESSION['installer_csrf']);
        } catch (Throwable $exception) {
            $error = $exception->getMessage();
        }
    }
}

$escape = static fn (mixed $value): string => htmlspecialchars(
    (string) $value,
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);
$old = static fn (string $name, string $default = ''): string => isset($_POST[$name])
    && is_scalar($_POST[$name]) ? trim((string) $_POST[$name]) : $default;
$selectedModules = is_array($_POST['modules'] ?? null)
    ? array_map('strval', $_POST['modules'])
    : array_column($modules, 'id');
$preflightOk = array_all($checks, static fn (array $check): bool => $check['ok']);
$host = preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost')) ?: 'localhost';
$defaultUrl = ($https ? 'https' : 'http') . '://' . $host;
?><!doctype html>
<html lang="pl-PL">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>Instalacja miniPORTAL</title>
  <style nonce="<?= $escape($nonce) ?>">
    :root { color-scheme: dark; --bg:#07101d; --panel:#101b2b; --line:#29405c; --text:#edf6ff; --muted:#a9b8ca; --accent:#64c7ff; --ok:#61e6a5; --bad:#ff7188; }
    * { box-sizing:border-box; }
    body { margin:0; min-height:100vh; color:var(--text); background:radial-gradient(circle at 80% 0,#12345a 0,transparent 30rem),var(--bg); font:16px/1.55 system-ui,sans-serif; }
    .shell { width:min(1120px,calc(100% - 2rem)); margin:0 auto; padding:3rem 0; }
    .hero { display:flex; align-items:center; gap:1rem; margin-bottom:2rem; }
    .mark { display:grid; width:3.4rem; height:3.4rem; place-items:center; color:#07101d; background:var(--accent); border-radius:1rem; font-weight:900; }
    h1,h2 { margin:.2rem 0; line-height:1.15; }
    p { color:var(--muted); }
    .grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:1rem; }
    .panel { padding:1.25rem; background:rgba(16,27,43,.94); border:1px solid var(--line); border-radius:1rem; box-shadow:0 1rem 3rem rgba(0,0,0,.22); }
    .wide { grid-column:1/-1; }
    label,legend { color:var(--text); font-weight:750; }
    label { display:grid; gap:.35rem; margin-top:.85rem; }
    input,select { width:100%; min-height:2.8rem; padding:.65rem .75rem; color:var(--text); background:#081424; border:1px solid var(--line); border-radius:.55rem; font:inherit; }
    input:focus-visible,select:focus-visible,button:focus-visible,a:focus-visible { outline:3px solid var(--accent); outline-offset:3px; }
    fieldset { margin:0; padding:0; border:0; }
    .checks { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:.55rem; margin-top:1rem; }
    .check { display:flex; align-items:flex-start; gap:.55rem; margin:0; padding:.7rem; background:#0a1626; border:1px solid var(--line); border-radius:.55rem; font-weight:650; }
    .check input { width:1.2rem; min-height:1.2rem; margin:.2rem 0 0; }
    .status { display:flex; justify-content:space-between; gap:1rem; padding:.5rem 0; border-bottom:1px solid rgba(255,255,255,.08); }
    .status:last-child { border:0; }
    .ok { color:var(--ok); } .bad { color:var(--bad); }
    .alert { margin-bottom:1rem; padding:1rem; border:1px solid currentColor; border-radius:.7rem; }
    .alert-error { color:#ffd7de; background:#381421; }
    .alert-success { color:#d8ffed; background:#103326; }
    .actions { display:flex; align-items:center; justify-content:space-between; gap:1rem; margin-top:1.25rem; }
    button,.button { display:inline-flex; min-height:2.8rem; align-items:center; justify-content:center; padding:.65rem 1rem; color:#04111d; background:var(--accent); border:0; border-radius:.6rem; font:inherit; font-weight:850; text-decoration:none; cursor:pointer; }
    button:disabled { cursor:not-allowed; opacity:.45; }
    small { color:var(--muted); font-weight:450; }
    code { color:var(--accent); }
    @media (max-width:760px) { .grid,.checks { grid-template-columns:1fr; } .wide { grid-column:auto; } .actions { align-items:stretch; flex-direction:column; } }
  </style>
</head>
<body>
<main class="shell">
  <header class="hero">
    <span class="mark" aria-hidden="true">&lt;/&gt;</span>
    <div><p>miniPORTAL <?= $escape('0.1.0') ?></p><h1>Kreator instalacji</h1></div>
  </header>

  <?php if ($result !== null): ?>
    <section class="panel">
      <div class="alert alert-success" role="status">Instalacja została ukończona.</div>
      <h2>Witaj, <?= $escape($result['owner']) ?></h2>
      <p>Zainstalowano <?= $escape($result['installed_modules']) ?> modułów. Instalator został zablokowany plikiem w katalogu <code>config/</code>.</p>
      <a class="button" href="<?= $escape($result['login_url']) ?>">Przejdź do logowania</a>
    </section>
  <?php elseif ($installer->isInstalled()): ?>
    <?php http_response_code(410); ?>
    <section class="panel"><h2>Instalator jest zablokowany</h2><p>Ta kopia miniPORTAL została już zainstalowana.</p><a class="button" href="/">Otwórz stronę</a></section>
  <?php else: ?>
    <?php if ($error !== ''): ?><div class="alert alert-error" role="alert"><?= $escape($error) ?></div><?php endif; ?>
    <form method="post" action="install.php" autocomplete="off">
      <input type="hidden" name="_token" value="<?= $escape($token) ?>">
      <div class="grid">
        <section class="panel">
          <h2>1. Środowisko</h2>
          <p>Wymagania są sprawdzane przed zmianą bazy.</p>
          <?php foreach ($checks as $check): ?>
            <div class="status"><span><?= $escape($check['label']) ?></span><strong class="<?= $check['ok'] ? 'ok' : 'bad' ?>"><?= $escape($check['detail']) ?></strong></div>
          <?php endforeach; ?>
        </section>

        <section class="panel">
          <h2>2. Witryna</h2>
          <label>Adres strony <input name="site_url" type="url" required maxlength="255" value="<?= $escape($old('site_url', $defaultUrl)) ?>"></label>
          <label>Nazwa strony <input name="site_name" required maxlength="80" value="<?= $escape($old('site_name', 'miniPORTAL')) ?>"></label>
          <label>Strefa czasowa <input name="timezone" required value="<?= $escape($old('timezone', 'Europe/Warsaw')) ?>"></label>
          <label>Język i region <input name="locale" required maxlength="5" value="<?= $escape($old('locale', 'pl_PL')) ?>"></label>
          <label>Motyw <select name="theme"><option value="default"<?= $old('theme', 'default') === 'default' ? ' selected' : '' ?>>Default</option><option value="future"<?= $old('theme') === 'future' ? ' selected' : '' ?>>Future</option><option value="glassnight"<?= $old('theme') === 'glassnight' ? ' selected' : '' ?>>Glassnight</option></select></label>
        </section>

        <section class="panel">
          <h2>3. Baza MySQL</h2>
          <label>Host <input name="db_host" required value="<?= $escape($old('db_host', '127.0.0.1')) ?>"></label>
          <label>Port <input name="db_port" type="number" min="1" max="65535" required value="<?= $escape($old('db_port', '3306')) ?>"></label>
          <label>Nazwa bazy <input name="db_name" required pattern="[A-Za-z0-9_]+" value="<?= $escape($old('db_name', 'miniportal')) ?>"></label>
          <label>Użytkownik <input name="db_user" required autocomplete="username" value="<?= $escape($old('db_user')) ?>"></label>
          <label>Hasło <input name="db_pass" type="password" required autocomplete="new-password"></label>
          <label class="check"><input name="create_database" type="checkbox" value="1"<?= isset($_POST['create_database']) || $_SERVER['REQUEST_METHOD'] !== 'POST' ? ' checked' : '' ?>> Utwórz bazę, jeśli nie istnieje</label>
        </section>

        <section class="panel">
          <h2>4. GitHub i pierwszy Owner</h2>
          <p>Konto zostanie rozpoznane po stałym numerycznym ID GitHub.</p>
          <label>Login GitHub <input name="github_login" required maxlength="39" value="<?= $escape($old('github_login')) ?>"></label>
          <label>GitHub Client ID <input name="github_client_id" required value="<?= $escape($old('github_client_id')) ?>"></label>
          <label>GitHub Client Secret <input name="github_client_secret" type="password" required autocomplete="new-password"></label>
          <details><summary>Opcjonalnie Discord i Google</summary>
            <label>Discord Client ID <input name="discord_client_id" value="<?= $escape($old('discord_client_id')) ?>"></label>
            <label>Discord Client Secret <input name="discord_client_secret" type="password" autocomplete="new-password"></label>
            <label>Google Client ID <input name="google_client_id" value="<?= $escape($old('google_client_id')) ?>"></label>
            <label>Google Client Secret <input name="google_client_secret" type="password" autocomplete="new-password"></label>
          </details>
        </section>

        <section class="panel wide">
          <fieldset><legend><h2>5. Moduły</h2></legend><p>Zależności wybranych modułów zostaną dołączone automatycznie.</p>
            <div class="checks">
              <?php foreach ($modules as $module): ?>
                <label class="check"><input type="checkbox" name="modules[]" value="<?= $escape($module['id']) ?>"<?= in_array($module['id'], $selectedModules, true) || $module['required'] ? ' checked' : '' ?><?= $module['required'] ? ' disabled' : '' ?>>
                  <span><?= $escape($module['name']) ?><?= $module['required'] ? ' (wymagany)' : '' ?><br><small><?= $escape(implode(', ', $module['dependencies']) ?: 'bez zależności') ?></small></span>
                </label>
              <?php endforeach; ?>
            </div>
          </fieldset>
          <div class="actions"><small>Instalator przyjmuje wyłącznie pustą bazę i nie nadpisuje istniejących tabel.</small><button type="submit"<?= $preflightOk ? '' : ' disabled' ?>>Zainstaluj miniPORTAL</button></div>
        </section>
      </div>
    </form>
  <?php endif; ?>
</main>
</body>
</html>

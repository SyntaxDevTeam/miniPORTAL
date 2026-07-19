<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\RemoteTerminal;

use SyntaxDevTeam\Cms\Core\AdminMenuRegistry;
use SyntaxDevTeam\Cms\Core\ModuleInterface;
use SyntaxDevTeam\Cms\Core\Request;
use SyntaxDevTeam\Cms\Core\Router;
use SyntaxDevTeam\Cms\Core\Security;
use SyntaxDevTeam\Cms\Core\ThemeInterface;
use SyntaxDevTeam\Cms\Modules\CoreAuth\AdminAccessGate;
use SyntaxDevTeam\Cms\Modules\CoreAuth\AuditLogService;
use SyntaxDevTeam\Cms\Modules\CoreAuth\AuthService;

final class RemoteTerminalModule implements ModuleInterface
{
    private const SESSION_ID_PATTERN = '/^[a-f0-9]{32}$/';
    private const MAX_INPUT_BYTES = 8192;
    private const LOCAL_TERM = 'xterm-256color';
    private const LOCAL_COLUMNS = 200;
    private const LOCAL_ROWS = 40;

    public function __construct(
        private readonly ThemeInterface $theme,
        private readonly AdminMenuRegistry $menu,
        private readonly AuthService $auth,
        private readonly AdminAccessGate $access,
        private readonly Security $security,
        private readonly AuditLogService $audit,
        private readonly RemoteTerminalConfig $config,
        private readonly string $sessionDirectory,
    ) {
    }

    public function id(): string
    {
        return 'remote_terminal';
    }

    public function version(): string
    {
        return '1.2.11';
    }

    public function dependencies(): array
    {
        return ['core_auth'];
    }

    public function isProtected(): bool
    {
        return false;
    }

    public function requiredPermissions(): array
    {
        return ['remote_terminal.access'];
    }

    public function registerAdminMenu(AdminMenuRegistry $menu): void
    {
        $menu->add('Narzędzia', 'Terminal SSH', '/admin/remote-terminal', 'SSH', 'remote_terminal.access', 40);
    }

    public function registerRoutes(Router $router): void
    {
        $router->get('/admin/remote-terminal', fn (Request $request) => $this->guard(
            $request,
            fn () => $this->renderTerminal($request)
        ));
        $router->post('/admin/remote-terminal/launch', fn (Request $request) => $this->guard(
            $request,
            fn () => $this->launchTerminal($request)
        ));
        $router->post('/admin/remote-terminal/cleanup', fn (Request $request) => $this->guard(
            $request,
            fn () => $this->cleanupTerminalSessions($request)
        ));
        $router->post('/admin/remote-terminal/resume', fn (Request $request) => $this->guard(
            $request,
            fn () => $this->resumeLatestTerminalSession($request)
        ));
        $router->get('/admin/remote-terminal/session/{session}', fn (Request $request) => $this->guard(
            $request,
            fn () => $this->renderLocalTerminalFrame($request)
        ));
        $router->get('/admin/remote-terminal/assets/{asset}', fn (Request $request) => $this->guard(
            $request,
            fn () => $this->serveTerminalAsset($request)
        ));
        $router->get('/admin/remote-terminal/output/{session}', fn (Request $request) => $this->guard(
            $request,
            fn () => $this->readLocalTerminalOutput($request)
        ));
        $router->post('/admin/remote-terminal/input/{session}', fn (Request $request) => $this->guard(
            $request,
            fn () => $this->writeLocalTerminalInput($request)
        ));
    }

    private function renderTerminal(Request $request, string $message = '', string $variant = 'info', string $launchUrl = ''): void
    {
        $user = $this->auth->user();
        if ($user === null) {
            return;
        }

        $lead = $this->config->mode === 'gateway'
            ? 'Prywatna brama do pełnoprawnego terminala SSH uruchamianego przez zewnętrzny gateway PTY.'
            : 'Prywatny terminal SSH uruchamiany lokalnie przez miniPORTAL dla połączeń z allowlisty.';
        $this->startPage('Terminal SSH', '/admin/remote-terminal', $lead);

        if ($message !== '') {
            $this->theme->render_alert($message, $variant);
        }

        if (!$this->config->isReady()) {
            $requirements = $this->config->mode === 'gateway'
                ? 'REMOTE_TERMINAL_ENABLED=1, REMOTE_TERMINAL_MODE=gateway, REMOTE_TERMINAL_GATEWAY_URL, REMOTE_TERMINAL_SHARED_SECRET, REMOTE_TERMINAL_SSH_HOST i REMOTE_TERMINAL_SSH_USER.'
                : 'REMOTE_TERMINAL_ENABLED=1, REMOTE_TERMINAL_MODE=local, REMOTE_TERMINAL_SSH_HOST, REMOTE_TERMINAL_SSH_USER oraz host na REMOTE_TERMINAL_ALLOWED_HOSTS.';
            $this->theme->render_alert(
                'Terminal jest wyłączony albo niekompletnie skonfigurowany. Ustaw ' . $requirements,
                'warning'
            );
        }

        $this->renderOperationalStatus($request);
        $this->renderSessionMaintenance();

        echo '<section class="admin-card remote-terminal-launch">';
        echo '<div class="remote-terminal-launch__header">';
        echo '<div>';
        echo '<h2>Uruchom sesję</h2>';
        if ($this->config->mode === 'gateway') {
            echo '<p>Tryb gateway generuje krótki dostęp HMAC dla terminala webowego.</p>';
        } else {
            echo '<p>Tryb lokalny uruchamia SSH z serwera aplikacji przez prywatny strumień panelu.</p>';
        }
        echo '</div>';
        echo '</div>';
        echo '<form class="remote-terminal-launch__form" method="post" action="index.php?route=/admin/remote-terminal/launch">';
        echo '<input type="hidden" name="csrf_token" value="' . self::e($this->security->csrfToken()) . '">';
        if ($this->config->hosts !== []) {
            echo '<div class="remote-terminal-field">';
            echo '<label class="form-label" for="remote-terminal-host">Host SSH</label>';
            echo '<select class="form-select" id="remote-terminal-host" name="host">';
            foreach ($this->config->hosts as $host) {
                $label = $host['label'] . ' - ' . $host['user'] . '@' . $host['host'] . ':' . $host['port'];
                echo '<option value="' . self::e($host['key']) . '">' . self::e($label) . '</option>';
            }
            echo '</select>';
            echo '</div>';
        }
        echo '<button class="btn btn-primary" type="submit"' . (!$this->config->isReady() ? ' disabled' : '') . '>Otwórz terminal</button>';
        echo '</form>';
        echo '</section>';

        if ($launchUrl !== '') {
            echo '<section class="admin-card remote-terminal-active">';
            echo '<div class="remote-terminal-active__header">';
            echo '<div><h2>Aktywna sesja</h2><p>Sesja jest krótkotrwała. Po wygaśnięciu wygeneruj nową.</p></div>';
            echo '</div>';
            echo '<iframe class="remote-terminal-frame" title="Terminal SSH" src="' . self::e($launchUrl) . '"></iframe>';
            echo '</section>';
        }

        $this->endPage();
    }

    private function cleanupTerminalSessions(Request $request): void
    {
        if (!$this->security->validateCsrfToken($request->postString('csrf_token'))) {
            $this->audit->record($request, 'remote_terminal_cleanup', 'csrf_failed', null, $this->auth->user()?->id);
            http_response_code(419);
            $this->renderTerminal($request, 'Nieprawidłowy token CSRF. Odśwież stronę i spróbuj ponownie.', 'danger');
            return;
        }

        $result = $this->cleanupLocalSessions(true);
        $this->audit->record(
            $request,
            'remote_terminal_cleanup',
            'success',
            'terminated=' . $result['terminated'] . '; removed=' . $result['removed'],
            $this->auth->user()?->id
        );

        $this->renderTerminal(
            $request,
            'Zakończono stare sesje terminala: procesy ' . $result['terminated'] . ', katalogi ' . $result['removed'] . '.',
            'success'
        );
    }

    private function resumeLatestTerminalSession(Request $request): void
    {
        if (!$this->security->validateCsrfToken($request->postString('csrf_token'))) {
            $this->audit->record($request, 'remote_terminal_resume', 'csrf_failed', null, $this->auth->user()?->id);
            http_response_code(419);
            $this->renderTerminal($request, 'Nieprawidłowy token CSRF. Odśwież stronę i spróbuj ponownie.', 'danger');
            return;
        }

        $userId = (int) ($this->auth->user()?->id ?? 0);
        $session = $this->latestResumableLocalSession($userId);
        if ($session === null) {
            $this->audit->record($request, 'remote_terminal_resume', 'not_found', null, $this->auth->user()?->id);
            $this->renderTerminal($request, 'Nie znaleziono aktywnej sesji terminala do wznowienia.', 'warning');
            return;
        }

        $this->audit->record($request, 'remote_terminal_resume', 'success', null, $this->auth->user()?->id);
        $this->renderTerminal(
            $request,
            'Wznowiono ostatnią aktywną sesję terminala SSH.',
            'success',
            $this->localTerminalUrl($session['id'], $session['token'])
        );
    }

    private function launchTerminal(Request $request): void
    {
        if (!$this->security->validateCsrfToken($request->postString('csrf_token'))) {
            $this->audit->record($request, 'remote_terminal_launch', 'csrf_failed', null, $this->auth->user()?->id);
            http_response_code(419);
            $this->renderTerminal($request, 'Nieprawidłowy token CSRF. Odśwież stronę i spróbuj ponownie.', 'danger');
            return;
        }

        if ($this->config->requireSecureRequest && !$request->isSecure()) {
            $this->audit->record($request, 'remote_terminal_launch', 'insecure_request', null, $this->auth->user()?->id);
            $this->renderTerminal($request, 'Terminal wymaga HTTPS. Nie uruchamiam sesji przez niezabezpieczone żądanie.', 'danger');
            return;
        }

        if (!$this->config->isReady()) {
            $this->audit->record($request, 'remote_terminal_launch', 'not_configured', null, $this->auth->user()?->id);
            $this->renderTerminal($request, 'Konfiguracja terminala jest niekompletna.', 'warning');
            return;
        }

        $target = $this->config->host($request->postString('host'));
        if ($target === null) {
            $this->audit->record($request, 'remote_terminal_launch', 'host_missing', null, $this->auth->user()?->id);
            $this->renderTerminal($request, 'Nie skonfigurowano żadnego hosta terminala.', 'warning');
            return;
        }
        if ($this->config->mode === 'local' && !$this->config->isHostAllowed($target['host'])) {
            $this->audit->record($request, 'remote_terminal_launch', 'host_not_allowed', null, $this->auth->user()?->id);
            $this->renderTerminal($request, 'Wybrany host nie znajduje się na allowliście terminala.', 'danger');
            return;
        }

        $user = $this->auth->user();
        if ($this->config->mode === 'gateway') {
            $url = $this->signedGatewayUrl((int) ($user?->id ?? 0), (string) ($user?->primaryRole() ?? 'unknown'), $target);
            $this->audit->record($request, 'remote_terminal_launch', 'success', null, $user?->id);
            $this->renderTerminal($request, 'Wygenerowano krótkotrwały dostęp do terminala.', 'success', $url);
            return;
        }

        $session = $this->createLocalSession((int) ($user?->id ?? 0), (string) ($user?->primaryRole() ?? 'unknown'), $target);
        $this->audit->record($request, 'remote_terminal_launch', 'success', null, $user?->id);
        $this->renderTerminal($request, 'Utworzono lokalną sesję terminala SSH.', 'success', $this->localTerminalUrl($session['id'], $session['token']));
    }

    private function renderOperationalStatus(Request $request): void
    {
        if ($this->config->isReady()) {
            $this->theme->render_alert(
                'Terminal jest gotowy. Dostępne profile: ' . $this->hostSummary(),
                'success'
            );
            $this->renderDiagnosticsDetails($request);
            return;
        }

        $this->renderDiagnosticsTable($request, true);
    }

    private function renderSessionMaintenance(): void
    {
        if ($this->config->mode !== 'local') {
            return;
        }

        $summary = $this->localSessionSummary();
        $resumable = $this->latestResumableLocalSession((int) ($this->auth->user()?->id ?? 0));
        echo '<section class="admin-card remote-terminal-maintenance">';
        echo '<div class="remote-terminal-active__header">';
        echo '<div>';
        echo '<h2>Sesje lokalne</h2>';
        echo '<p>Aktywne: ' . self::e((string) $summary['active']) . ', wygasłe: ' . self::e((string) $summary['expired']) . ', wszystkie: ' . self::e((string) $summary['total']) . '.</p>';
        echo '</div>';
        echo '<div class="remote-terminal-maintenance__actions">';
        echo '<form method="post" action="index.php?route=/admin/remote-terminal/resume">';
        echo '<input type="hidden" name="csrf_token" value="' . self::e($this->security->csrfToken()) . '">';
        echo '<button class="btn btn-outline-primary" type="submit"' . ($resumable === null ? ' disabled' : '') . '>Wznów ostatnią aktywną sesję</button>';
        echo '</form>';
        echo '<form method="post" action="index.php?route=/admin/remote-terminal/cleanup">';
        echo '<input type="hidden" name="csrf_token" value="' . self::e($this->security->csrfToken()) . '">';
        echo '<button class="btn btn-outline-danger" type="submit"' . ($summary['expired'] === 0 ? ' disabled' : '') . '>Zakończ stare sesje</button>';
        echo '</form>';
        echo '</div>';
        echo '</div>';
        echo '</section>';
    }

    private function renderDiagnosticsDetails(Request $request): void
    {
        echo '<details class="admin-card remote-terminal-diagnostics">';
        echo '<summary>Diagnostyka konfiguracji</summary>';
        $this->renderDiagnosticsTable($request, false);
        echo '</details>';
    }

    private function renderDiagnosticsTable(Request $request, bool $withHeading): void
    {
        $rows = [
            ['Status modułu', $this->config->enabled ? 'Włączony' : 'Wyłączony'],
            ['Tryb pracy', $this->config->mode === 'gateway' ? 'Gateway PTY' : 'Lokalny SSH/PTTY'],
            ['Gateway PTY', $this->config->gatewayUrl !== '' ? $this->redactedUrl($this->config->gatewayUrl) : 'Brak'],
            ['Profile SSH', $this->hostSummary()],
            ['Allowlista hostów', $this->config->allowedHosts === ['*'] ? '*' : implode(', ', $this->config->allowedHosts)],
            ['Czas sesji lokalnej', $this->config->sessionTtl . ' s'],
            ['Wymagany HTTPS', $this->config->requireSecureRequest ? 'Tak' : 'Nie'],
            ['Bieżące żądanie HTTPS', $request->isSecure() ? 'Tak' : 'Nie'],
            ['TTL tokenu gateway', $this->config->tokenTtl . ' s'],
        ];

        if ($withHeading) {
            echo '<section class="admin-card">';
            echo '<h2>Konfiguracja środowiskowa</h2>';
        }
        echo '<div class="table-responsive"><table class="table"><tbody>';
        foreach ($rows as [$label, $value]) {
            echo '<tr><th scope="row">' . self::e($label) . '</th><td>' . self::e($value) . '</td></tr>';
        }
        echo '</tbody></table></div>';
        if ($withHeading) {
            echo '</section>';
        }
    }

    /** @param array{key: string, label: string, host: string, port: int, user: string, key_file: string} $target */
    private function signedGatewayUrl(int $userId, string $role, array $target): string
    {
        $expiresAt = time() + $this->config->tokenTtl;
        $payload = [
            'uid' => $userId,
            'role' => $role,
            'ssh_profile' => $target['key'],
            'ssh_host' => $target['host'],
            'ssh_port' => $target['port'],
            'ssh_user' => $target['user'],
            'exp' => $expiresAt,
            'nonce' => bin2hex(random_bytes(16)),
        ];
        $json = json_encode($payload, JSON_THROW_ON_ERROR);
        $body = rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
        $signature = hash_hmac('sha256', $body, $this->config->sharedSecret);
        $token = $body . '.' . $signature;

        $separator = str_contains($this->config->gatewayUrl, '?') ? '&' : '?';
        return $this->config->gatewayUrl . $separator . rawurlencode($this->config->tokenParameter) . '=' . rawurlencode($token);
    }

    private function renderLocalTerminalFrame(Request $request): void
    {
        $session = $this->loadLocalSession($request);
        if ($session === null) {
            $this->renderFrameError('Sesja terminala jest nieważna albo wygasła.');
            return;
        }

        $csrf = $this->security->csrfToken();
        $outputUrl = 'index.php?route=/admin/remote-terminal/output/' . rawurlencode($session['id']);
        $inputUrl = 'index.php?route=/admin/remote-terminal/input/' . rawurlencode($session['id']);
        $assetBase = 'index.php?route=/admin/remote-terminal/assets/';

        header('Content-Type: text/html; charset=UTF-8');
        echo '<!doctype html><html lang="pl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
        echo '<title>Terminal SSH</title>';
        echo '<link rel="stylesheet" href="' . self::e($assetBase . 'xterm.css') . '">';
        echo '<style>';
        echo 'html,body{height:100%;margin:0;background:#020617;color:#d1fae5;font:14px/1.45 ui-monospace,SFMono-Regular,Menlo,Consolas,monospace}';
        echo '*{box-sizing:border-box}.terminal{display:grid;grid-template-rows:auto 1fr;height:100%;border:1px solid rgba(148,163,184,.22)}';
        echo '.bar{display:flex;gap:.75rem;align-items:center;justify-content:space-between;padding:.65rem .85rem;background:#0f172a;color:#e2e8f0;border-bottom:1px solid rgba(148,163,184,.22)}';
        echo '.screen{min-height:0;overflow:hidden;background:#020617;padding:.35rem .5rem}.xterm{width:100%;height:100%}.xterm-screen{width:100%!important}.xterm-screen canvas{display:block}.xterm-viewport{background:#020617!important}';
        echo '</style></head><body>';
        echo '<main class="terminal" data-terminal>';
        echo '<div class="bar"><strong>' . self::e($session['label'] . ' - ' . $session['user'] . '@' . $session['host'] . ':' . $session['port']) . '</strong><span data-status>Łączenie...</span></div>';
        echo '<div class="screen" data-terminal-screen></div>';
        echo '</main>';
        echo '<script src="' . self::e($assetBase . 'xterm.js') . '"></script>';
        echo '<script src="' . self::e($assetBase . 'addon-fit.js') . '"></script>';
        echo '<script>';
        echo 'const outputUrl=' . json_encode($outputUrl, JSON_THROW_ON_ERROR) . ';';
        echo 'const inputUrl=' . json_encode($inputUrl, JSON_THROW_ON_ERROR) . ';';
        echo 'const token=' . json_encode($session['token'], JSON_THROW_ON_ERROR) . ';';
        echo 'const csrf=' . json_encode($csrf, JSON_THROW_ON_ERROR) . ';';
        echo 'const terminalCols=' . json_encode(self::LOCAL_COLUMNS, JSON_THROW_ON_ERROR) . ';';
        echo 'const terminalRows=' . json_encode(self::LOCAL_ROWS, JSON_THROW_ON_ERROR) . ';';
        echo <<<'JS'
const screen = document.querySelector('[data-terminal-screen]');
const status = document.querySelector('[data-status]');
const term = new Terminal({
  cols: terminalCols,
  rows: terminalRows,
  cursorBlink: true,
  convertEol: false,
  scrollback: 5000,
  fontFamily: 'ui-monospace, SFMono-Regular, Menlo, Consolas, monospace',
  fontSize: 15,
  lineHeight: 1.1,
  theme: {
    background: '#020617',
    foreground: '#d1fae5',
    cursor: '#67e8f9',
    selectionBackground: '#334155',
    black: '#0f172a',
    red: '#ef4444',
    green: '#22c55e',
    yellow: '#eab308',
    blue: '#38bdf8',
    magenta: '#c084fc',
    cyan: '#67e8f9',
    white: '#e2e8f0',
    brightBlack: '#475569',
    brightRed: '#f87171',
    brightGreen: '#86efac',
    brightYellow: '#fde047',
    brightBlue: '#7dd3fc',
    brightMagenta: '#d8b4fe',
    brightCyan: '#a5f3fc',
    brightWhite: '#f8fafc'
  }
});
const fitAddon = new FitAddon.FitAddon();
term.loadAddon(fitAddon);
term.open(screen);
const fit = () => {
  fitAddon.fit();
  if (term.rows > terminalRows) {
    term.resize(term.cols, terminalRows);
  }
  term.focus();
};
requestAnimationFrame(fit);
setTimeout(fit, 100);
setTimeout(fit, 350);
window.addEventListener('resize', () => requestAnimationFrame(fit));
let offset = 0;
let polling = true;
let pendingInput = '';
let inputTimer = 0;
const send = async (value) => {
  if (!value) return;
  const response = await fetch(inputUrl, {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({token, csrf_token: csrf, input: value})
  });
  if (!response.ok) {
    status.textContent = 'Nie wysłano wejścia';
  }
};
const flushInput = () => {
  inputTimer = 0;
  const value = pendingInput;
  pendingInput = '';
  send(value);
};
term.onData((data) => {
  pendingInput += data;
  if (inputTimer === 0) {
    inputTimer = window.setTimeout(flushInput, 12);
  }
});
const poll = async () => {
  if (!polling) return;
  try {
    const url = `${outputUrl}&token=${encodeURIComponent(token)}&offset=${offset}`;
    const response = await fetch(url, {headers: {'Accept': 'application/json'}});
    if (!response.ok) {
      status.textContent = 'Rozłączono';
      setTimeout(poll, 1500);
      return;
    }
    const data = await response.json();
    offset = data.offset || offset;
    if (data.output) {
      term.write(data.output, () => term.scrollToBottom());
    }
    status.textContent = data.running ? 'Połączono z procesem SSH.' : 'Sesja zamknięta.';
    setTimeout(poll, data.running ? 400 : 1500);
  } catch (_error) {
    status.textContent = 'Ponawiam połączenie...';
    setTimeout(poll, 1500);
  }
};
screen.addEventListener('click', () => term.focus());
window.addEventListener('beforeunload', () => { polling = false; });
poll();
JS;
        echo '</script></body></html>';
    }

    private function serveTerminalAsset(Request $request): void
    {
        $asset = $request->routeString('asset');
        $files = [
            'xterm.css' => ['xterm.css', 'text/css; charset=UTF-8'],
            'xterm.js' => ['xterm.js', 'application/javascript; charset=UTF-8'],
            'addon-fit.js' => ['addon-fit.js', 'application/javascript; charset=UTF-8'],
        ];
        if (!isset($files[$asset])) {
            http_response_code(404);
            return;
        }

        [$file, $contentType] = $files[$asset];
        $path = __DIR__ . '/assets/vendor/xterm/' . $file;
        if (!is_file($path)) {
            http_response_code(404);
            return;
        }

        header('Content-Type: ' . $contentType);
        header('Cache-Control: private, max-age=86400');
        readfile($path);
    }

    private function writeLocalTerminalInput(Request $request): void
    {
        $session = $this->loadLocalSession($request, true);
        $data = $request->json() ?? [];
        $csrfToken = is_scalar($data['csrf_token'] ?? null) ? (string) $data['csrf_token'] : '';
        if ($session === null || !$this->security->validateCsrfToken($csrfToken)) {
            http_response_code(403);
            $this->jsonResponse(['ok' => false]);
            return;
        }

        $input = is_scalar($data['input'] ?? null) ? (string) $data['input'] : '';
        if ($input === '' || strlen($input) > self::MAX_INPUT_BYTES) {
            http_response_code(422);
            $this->jsonResponse(['ok' => false]);
            return;
        }
        if (!$this->isSessionProcessRunning($session['id'])) {
            http_response_code(409);
            $this->jsonResponse(['ok' => false]);
            return;
        }

        $inputFile = $this->sessionPath($session['id'], 'input.pipe', false);
        $handle = @fopen($inputFile, 'wb');
        if (!is_resource($handle)) {
            http_response_code(409);
            $this->jsonResponse(['ok' => false]);
            return;
        }
        fwrite($handle, $input);
        fclose($handle);
        $this->jsonResponse(['ok' => true]);
    }

    private function readLocalTerminalOutput(Request $request): void
    {
        $session = $this->loadLocalSession($request, true);
        if ($session === null) {
            http_response_code(403);
            $this->jsonResponse(['ok' => false]);
            return;
        }

        $offset = max(0, $request->queryInt('offset', 0) ?? 0);
        $outputFile = $this->sessionPath($session['id'], 'output.log', false);
        $output = '';
        $size = is_file($outputFile) ? (filesize($outputFile) ?: 0) : 0;
        if ($size > $offset) {
            $handle = fopen($outputFile, 'rb');
            if (is_resource($handle)) {
                fseek($handle, $offset);
                $output = (string) stream_get_contents($handle, 65536);
                $position = ftell($handle);
                $offset = $position === false ? $size : $position;
                fclose($handle);
            }
        }

        $this->jsonResponse([
            'ok' => true,
            'output' => $output,
            'offset' => $offset,
            'running' => $this->isSessionProcessRunning($session['id']),
        ]);
    }

    /**
     * @param array{key: string, label: string, host: string, port: int, user: string, key_file: string} $target
     * @return array{id: string, token: string}
     */
    private function createLocalSession(int $userId, string $role, array $target): array
    {
        $this->ensureSessionDirectory();
        $this->cleanupLocalSessions();
        $id = bin2hex(random_bytes(16));
        $token = bin2hex(random_bytes(32));
        $session = [
            'id' => $id,
            'token_hash' => hash('sha256', $token),
            'resume_token' => $token,
            'user_id' => $userId,
            'role' => $role,
            'profile' => $target['key'],
            'label' => $target['label'],
            'host' => $target['host'],
            'port' => $target['port'],
            'user' => $target['user'],
            'key_file' => $target['key_file'],
            'created_at' => time(),
            'expires_at' => time() + $this->config->sessionTtl,
            'state' => 'created',
        ];
        file_put_contents($this->sessionPath($id, 'session.json'), json_encode($session, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES), LOCK_EX);
        $this->startLocalProcess($id, $target);

        return ['id' => $id, 'token' => $token];
    }

    /** @return array{id:string,token:string}|null */
    private function latestResumableLocalSession(int $userId): ?array
    {
        if ($userId <= 0) {
            return null;
        }

        $latest = null;
        $latestTime = 0;
        foreach (glob(rtrim($this->sessionDirectory, '/') . '/*/session.json') ?: [] as $file) {
            $data = json_decode((string) file_get_contents($file), true);
            if (!is_array($data)) {
                continue;
            }
            $id = is_scalar($data['id'] ?? null) ? (string) $data['id'] : basename(dirname($file));
            $token = is_scalar($data['resume_token'] ?? null) ? (string) $data['resume_token'] : '';
            $host = is_scalar($data['host'] ?? null) ? (string) $data['host'] : '';
            $createdAt = (int) ($data['created_at'] ?? 0);
            $updatedAt = (int) ($data['updated_at'] ?? 0);
            $time = max($createdAt, $updatedAt);
            if (
                preg_match(self::SESSION_ID_PATTERN, $id) !== 1
                || !preg_match('/^[a-f0-9]{64}$/', $token)
                || (int) ($data['user_id'] ?? 0) !== $userId
                || (int) ($data['expires_at'] ?? 0) < time()
                || !$this->config->isHostAllowed($host)
                || !$this->isSessionProcessRunning($id)
                || $time < $latestTime
            ) {
                continue;
            }
            $latest = ['id' => $id, 'token' => $token];
            $latestTime = $time;
        }

        return $latest;
    }

    /** @return array{id: string, token: string, label: string, host: string, port: int, user: string, key_file: string}|null */
    private function loadLocalSession(Request $request, bool $queryToken = false): ?array
    {
        $id = $request->routeString('session');
        if (preg_match(self::SESSION_ID_PATTERN, $id) !== 1) {
            return null;
        }

        $token = $queryToken ? $request->queryString('token') : $request->queryString('token');
        if ($token === '') {
            $json = $request->json();
            $token = is_scalar($json['token'] ?? null) ? (string) $json['token'] : '';
        }
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }

        $file = $this->sessionPath($id, 'session.json');
        if (!is_file($file)) {
            return null;
        }
        $data = json_decode((string) file_get_contents($file), true);
        if (!is_array($data) || (int) ($data['expires_at'] ?? 0) < time()) {
            return null;
        }
        if (!hash_equals((string) ($data['token_hash'] ?? ''), hash('sha256', $token))) {
            return null;
        }
        if ((int) ($data['user_id'] ?? 0) !== (int) ($this->auth->user()?->id ?? 0)) {
            return null;
        }

        $host = is_scalar($data['host'] ?? null) ? (string) $data['host'] : '';
        $user = is_scalar($data['user'] ?? null) ? (string) $data['user'] : '';
        $label = is_scalar($data['label'] ?? null) ? (string) $data['label'] : 'SSH';
        $port = filter_var($data['port'] ?? 22, FILTER_VALIDATE_INT);
        $keyFile = is_scalar($data['key_file'] ?? null) ? (string) $data['key_file'] : '';
        if ($host === '' || $user === '' || !$this->config->isHostAllowed($host)) {
            return null;
        }

        return [
            'id' => $id,
            'token' => $token,
            'label' => $label,
            'host' => $host,
            'port' => $port === false ? 22 : max(1, min(65535, $port)),
            'user' => $user,
            'key_file' => $keyFile,
        ];
    }

    /** @param array{host: string, port: int, user: string, key_file: string} $target */
    private function localSshCommand(array $target): string
    {
        $targetAddress = $target['user'] . '@' . $target['host'];
        $parts = [
            $this->config->sshBinary,
            '-tt',
            '-o', 'BatchMode=no',
            '-o', 'ServerAliveInterval=30',
            '-o', 'StrictHostKeyChecking=accept-new',
            '-o', 'UserKnownHostsFile=' . rtrim($this->sessionDirectory, '/') . '/known_hosts',
            '-p', (string) $target['port'],
        ];
        if ($target['key_file'] !== '') {
            $parts[] = '-i';
            $parts[] = $target['key_file'];
        }
        $parts[] = $targetAddress;
        $resize = 'stty rows ' . self::LOCAL_ROWS . ' cols ' . self::LOCAL_COLUMNS . ' 2>/dev/null; ';
        $ssh = $resize . 'TERM=' . escapeshellarg(self::LOCAL_TERM) . ' ' . implode(' ', array_map('escapeshellarg', $parts));

        if ($this->config->ptyBinary !== '' && is_executable($this->config->ptyBinary)) {
            return escapeshellarg($this->config->ptyBinary) . ' -qfec ' . escapeshellarg($ssh) . ' /dev/null';
        }

        return $ssh;
    }

    private function localTerminalUrl(string $id, string $token): string
    {
        return 'index.php?route=/admin/remote-terminal/session/' . rawurlencode($id) . '&token=' . rawurlencode($token);
    }

    private function renderFrameError(string $message): void
    {
        header('Content-Type: text/html; charset=UTF-8');
        echo '<!doctype html><html lang="pl"><head><meta charset="utf-8"><style>body{margin:0;padding:1rem;background:#020617;color:#fecaca;font:14px system-ui,sans-serif}</style></head><body>';
        echo self::e($message);
        echo '</body></html>';
    }

    /** @param array<string, mixed> $payload */
    private function jsonResponse(array $payload): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    private function ensureSessionDirectory(): void
    {
        if (!is_dir($this->sessionDirectory)) {
            mkdir($this->sessionDirectory, 0770, true);
        }
    }

    private function sessionPath(string $sessionId, string $file, bool $createDirectory = true): string
    {
        $directory = rtrim($this->sessionDirectory, '/') . '/' . $sessionId;
        if ($createDirectory && !is_dir($directory)) {
            mkdir($directory, 0770, true);
        }

        return $directory . '/' . $file;
    }

    private function writeSessionState(string $sessionId, string $state): void
    {
        $file = $this->sessionPath($sessionId, 'session.json');
        $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : [];
        if (!is_array($data)) {
            return;
        }
        $data['state'] = $state;
        $data['updated_at'] = time();
        file_put_contents($file, json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES), LOCK_EX);
    }

    /** @return array{total:int,active:int,expired:int} */
    private function localSessionSummary(): array
    {
        $total = 0;
        $active = 0;
        $expired = 0;
        foreach (glob(rtrim($this->sessionDirectory, '/') . '/*/session.json') ?: [] as $file) {
            $data = json_decode((string) file_get_contents($file), true);
            if (!is_array($data)) {
                continue;
            }
            $total++;
            if ((int) ($data['expires_at'] ?? 0) < time()) {
                $expired++;
                continue;
            }
            $active++;
        }

        return ['total' => $total, 'active' => $active, 'expired' => $expired];
    }

    /** @return array{terminated:int,removed:int} */
    private function cleanupLocalSessions(bool $forceExpired = false): array
    {
        $terminated = 0;
        $removed = 0;
        foreach (glob(rtrim($this->sessionDirectory, '/') . '/*/session.json') ?: [] as $file) {
            $data = json_decode((string) file_get_contents($file), true);
            if (!is_array($data) || (!$forceExpired && (int) ($data['expires_at'] ?? 0) >= time())) {
                continue;
            }
            if ($forceExpired && (int) ($data['expires_at'] ?? 0) >= time()) {
                continue;
            }
            $directory = dirname($file);
            $pid = (int) ($data['pid'] ?? 0);
            if ($this->terminateLocalProcess($pid)) {
                $terminated++;
            }
            foreach (glob($directory . '/*') ?: [] as $item) {
                if (!is_dir($item)) {
                    unlink($item);
                }
            }
            if (@rmdir($directory)) {
                $removed++;
            }
        }

        return ['terminated' => $terminated, 'removed' => $removed];
    }

    private function terminateLocalProcess(int $pid): bool
    {
        if ($pid <= 0 || !function_exists('posix_kill')) {
            return false;
        }
        if (!@posix_kill($pid, 0)) {
            return false;
        }

        @posix_kill(-$pid, 15);
        @posix_kill($pid, 15);
        usleep(150000);
        if (@posix_kill($pid, 0)) {
            @posix_kill(-$pid, 9);
            @posix_kill($pid, 9);
        }

        return true;
    }

    private function hostSummary(): string
    {
        if ($this->config->hosts === []) {
            return 'Brak';
        }

        $items = [];
        foreach ($this->config->hosts as $host) {
            $state = $this->config->mode === 'local' && !$this->config->isHostAllowed($host['host'])
                ? 'poza allowlistą'
                : 'gotowy';
            $items[] = $host['label'] . ': ' . $host['user'] . '@' . $host['host'] . ':' . $host['port'] . ' (' . $state . ')';
        }

        return implode('; ', $items);
    }

    /** @param array{host: string, port: int, user: string, key_file: string} $target */
    private function startLocalProcess(string $sessionId, array $target): void
    {
        $inputFile = $this->sessionPath($sessionId, 'input.pipe');
        $outputFile = $this->sessionPath($sessionId, 'output.log');
        $exitFile = $this->sessionPath($sessionId, 'exit.code');
        @unlink($inputFile);
        if (function_exists('posix_mkfifo')) {
            posix_mkfifo($inputFile, 0600);
        }
        if (!file_exists($inputFile)) {
            file_put_contents($outputFile, "Nie można utworzyć wejścia FIFO terminala.\n", LOCK_EX);
            $this->writeSessionState($sessionId, 'failed');
            return;
        }
        file_put_contents($outputFile, '', LOCK_EX);

        $runner = 'while true; do cat ' . escapeshellarg($inputFile) . '; done | '
            . $this->localSshCommand($target)
            . ' >> ' . escapeshellarg($outputFile)
            . ' 2>&1; printf "%s" "$?" > ' . escapeshellarg($exitFile);
        $command = 'setsid sh -c ' . escapeshellarg($runner) . ' >/dev/null 2>&1 & echo $!';
        $pid = (int) trim((string) shell_exec($command));
        $this->writeSessionState($sessionId, $pid > 0 ? 'running' : 'failed');

        $file = $this->sessionPath($sessionId, 'session.json');
        $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : [];
        if (is_array($data)) {
            $data['pid'] = $pid;
            file_put_contents($file, json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES), LOCK_EX);
        }
    }

    private function isSessionProcessRunning(string $sessionId): bool
    {
        $file = $this->sessionPath($sessionId, 'session.json', false);
        $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : [];
        $pid = is_array($data) ? (int) ($data['pid'] ?? 0) : 0;
        if ($pid <= 0) {
            return false;
        }
        if (is_file($this->sessionPath($sessionId, 'exit.code', false))) {
            return false;
        }

        return function_exists('posix_kill') && @posix_kill($pid, 0);
    }

    private function redactedUrl(string $url): string
    {
        $parts = parse_url($url);
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
            return 'Skonfigurowany';
        }

        return $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '') . (isset($parts['path']) ? $parts['path'] : '');
    }

    private function startPage(string $title, string $activePath, string $lead): void
    {
        $user = $this->auth->user();
        if ($user === null) {
            return;
        }

        $this->theme->start_admin_page(
            $title,
            $this->menu->visibleFor($user->permissions),
            $activePath,
            [
                'name' => $user->displayName,
                'role' => ucfirst($user->primaryRole()),
                'initials' => $user->initials(),
                'avatar_url' => $user->avatarUrl ?? '',
                'logout_action' => 'index.php?route=/admin/logout',
                'logout_token' => $this->security->csrfToken(),
            ]
        );
        $this->theme->start_admin_content(
            $title,
            $lead,
            [
                ['label' => 'Panel', 'href' => 'index.php?route=/admin'],
                ['label' => $title, 'href' => ''],
            ]
        );
    }

    private function endPage(): void
    {
        $this->theme->end_admin_content();
        $this->theme->end_admin_page();
    }

    private function guard(Request $request, callable $handler): void
    {
        $decision = $this->access->check('remote_terminal.access');
        $userId = $this->auth->user()?->id;

        if ($decision === AdminAccessGate::ALLOWED) {
            $handler();
            return;
        }

        $status = $decision === AdminAccessGate::UNAUTHENTICATED ? 401 : 403;
        $this->audit->record($request, 'remote_terminal_access', $status === 401 ? 'unauthenticated' : 'forbidden', null, $userId);
        http_response_code($status);
        $this->theme->render_admin_access_state(
            $status,
            $status === 401 ? 'Wymagane logowanie' : 'Brak uprawnienia',
            $status === 401 ? 'Ta trasa wymaga aktywnej sesji.' : 'Terminal SSH jest dostępny tylko dla Ownera i Administratora.',
            $status === 401 ? 'index.php?route=/admin/login' : 'index.php?route=/admin',
            $status === 401 ? 'Przejdź do logowania' : 'Wróć do dashboardu'
        );
    }

    private static function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

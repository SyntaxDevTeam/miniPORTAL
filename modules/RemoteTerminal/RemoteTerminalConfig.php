<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\RemoteTerminal;

final readonly class RemoteTerminalConfig
{
    public function __construct(
        public bool $enabled,
        public string $mode,
        public string $gatewayUrl,
        public string $sharedSecret,
        public string $tokenParameter,
        public int $tokenTtl,
        public string $sshHost,
        public int $sshPort,
        public string $sshUser,
        public string $sshKeyFile,
        public string $sshBinary,
        public string $ptyBinary,
        /** @var list<string> */
        public array $allowedHosts,
        /** @var list<array{key: string, label: string, host: string, port: int, user: string, key_file: string}> */
        public array $hosts,
        public int $sessionTtl,
        public bool $requireSecureRequest,
    ) {
    }

    /** @param array<string, mixed> $config */
    public static function fromModuleConfig(array $config): self
    {
        $tokenParameter = self::cleanTokenParameter((string) ($config['remote_terminal_token_parameter'] ?? 'mp_token'));
        $tokenTtl = filter_var($config['remote_terminal_token_ttl'] ?? 60, FILTER_VALIDATE_INT);
        $sshPort = filter_var($config['remote_terminal_ssh_port'] ?? 22, FILTER_VALIDATE_INT);
        $sessionTtl = filter_var($config['remote_terminal_session_ttl'] ?? 3600, FILTER_VALIDATE_INT);
        $gatewayUrl = trim((string) ($config['remote_terminal_gateway_url'] ?? ''));
        $modeDefault = $gatewayUrl !== '' ? 'gateway' : 'local';
        $mode = strtolower(trim((string) ($config['remote_terminal_mode'] ?? '')));
        $mode = $mode !== '' ? $mode : $modeDefault;
        if (!in_array($mode, ['local', 'gateway'], true)) {
            $mode = 'local';
        }
        $sshHost = trim((string) ($config['remote_terminal_ssh_host'] ?? ''));
        $sshPort = $sshPort === false ? 22 : max(1, min(65535, $sshPort));
        $sshUser = trim((string) ($config['remote_terminal_ssh_user'] ?? ''));
        $sshKeyFile = trim((string) ($config['remote_terminal_ssh_key_file'] ?? ''));
        $allowedHosts = self::cleanHostList((string) ($config['remote_terminal_allowed_hosts'] ?? '127.0.0.1,localhost,::1'));
        $hosts = self::parseHosts(
            (string) ($config['remote_terminal_hosts'] ?? ''),
            $sshHost,
            $sshPort,
            $sshUser,
            $sshKeyFile
        );

        return new self(
            ($config['remote_terminal_enabled'] ?? false) === true,
            $mode,
            $gatewayUrl,
            trim((string) ($config['remote_terminal_shared_secret'] ?? '')),
            $tokenParameter !== '' ? $tokenParameter : 'mp_token',
            $tokenTtl === false ? 60 : max(15, min(300, $tokenTtl)),
            $sshHost,
            $sshPort,
            $sshUser,
            $sshKeyFile,
            trim((string) ($config['remote_terminal_ssh_binary'] ?? '/usr/bin/ssh')),
            trim((string) ($config['remote_terminal_pty_binary'] ?? '/usr/bin/script')),
            $allowedHosts,
            $hosts,
            $sessionTtl === false ? 3600 : max(60, min(14400, $sessionTtl)),
            ($config['remote_terminal_require_secure_request'] ?? true) === true,
        );
    }

    public function isReady(): bool
    {
        return $this->mode === 'gateway' ? $this->isGatewayReady() : $this->isLocalReady();
    }

    public function isGatewayReady(): bool
    {
        return $this->enabled
            && $this->gatewayUrl !== ''
            && $this->sharedSecret !== ''
            && $this->hosts !== [];
    }

    public function isLocalReady(): bool
    {
        if (!$this->enabled || $this->sshBinary === '' || $this->hosts === []) {
            return false;
        }

        foreach ($this->hosts as $host) {
            if ($this->isHostAllowed($host['host'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{key: string, label: string, host: string, port: int, user: string, key_file: string}|null
     */
    public function host(string $key): ?array
    {
        foreach ($this->hosts as $host) {
            if ($host['key'] === $key) {
                return $host;
            }
        }

        return $this->hosts[0] ?? null;
    }

    public function isHostAllowed(string $host): bool
    {
        if ($this->allowedHosts === ['*']) {
            return true;
        }

        return in_array(strtolower(trim($host)), $this->allowedHosts, true);
    }

    private static function cleanTokenParameter(string $value): string
    {
        return preg_replace('/[^a-zA-Z0-9_]/', '', $value) ?? '';
    }

    /**
     * @return list<array{key: string, label: string, host: string, port: int, user: string, key_file: string}>
     */
    private static function parseHosts(string $value, string $defaultHost, int $defaultPort, string $defaultUser, string $defaultKeyFile): array
    {
        $hosts = [];
        foreach (explode(';', $value) as $entry) {
            $entry = trim($entry);
            if ($entry === '') {
                continue;
            }
            $parts = array_map('trim', explode('|', $entry));
            $key = self::cleanHostKey((string) ($parts[0] ?? ''));
            $host = strtolower((string) ($parts[1] ?? ''));
            $port = filter_var($parts[2] ?? $defaultPort, FILTER_VALIDATE_INT);
            $user = (string) ($parts[3] ?? $defaultUser);
            $keyFile = (string) ($parts[4] ?? $defaultKeyFile);
            if ($key === '' || !self::isValidHostName($host) || $user === '') {
                continue;
            }
            $hosts[] = [
                'key' => $key,
                'label' => self::labelFromKey($key),
                'host' => $host,
                'port' => $port === false ? $defaultPort : max(1, min(65535, $port)),
                'user' => $user,
                'key_file' => $keyFile,
            ];
        }

        if ($hosts === [] && $defaultHost !== '' && $defaultUser !== '' && self::isValidHostName($defaultHost)) {
            $key = str_contains($defaultHost, '127.0.0.1') || strtolower($defaultHost) === 'localhost' || $defaultHost === '::1'
                ? 'local'
                : self::cleanHostKey(str_replace('.', '-', $defaultHost));
            $hosts[] = [
                'key' => $key !== '' ? $key : 'default',
                'label' => self::labelFromKey($key !== '' ? $key : 'default'),
                'host' => strtolower($defaultHost),
                'port' => $defaultPort,
                'user' => $defaultUser,
                'key_file' => $defaultKeyFile,
            ];
        }

        return $hosts;
    }

    private static function cleanHostKey(string $value): string
    {
        return preg_replace('/[^a-z0-9_-]/', '', strtolower($value)) ?? '';
    }

    private static function labelFromKey(string $key): string
    {
        return $key === 'vps' ? 'VPS' : ucfirst(str_replace(['_', '-'], ' ', $key));
    }

    private static function isValidHostName(string $host): bool
    {
        return $host !== '' && preg_match('/^[a-z0-9.:-]+$/', strtolower($host)) === 1;
    }

    /**
     * @return list<string>
     */
    private static function cleanHostList(string $value): array
    {
        $hosts = [];
        foreach (preg_split('/[,\\s]+/', strtolower($value), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $host) {
            $host = trim($host);
            if ($host === '*') {
                return ['*'];
            }
            if ($host !== '' && preg_match('/^[a-z0-9.:-]+$/', $host) === 1) {
                $hosts[] = $host;
            }
        }

        return array_values(array_unique($hosts));
    }
}

# Remote Terminal

`remote_terminal` dodaje do panelu administracyjnego prywatny terminal SSH dla
Ownera i Administratora. Moduł odpowiada za ACL, CSRF, HTTPS, audyt i osadzenie
terminala w panelu. Treść komend oraz wyników terminala nie jest zapisywana w
audit logu.

## Tryby pracy

### `gateway`

Zalecany tryb produkcyjny. miniPORTAL generuje krótkotrwały token HMAC i osadza
zewnętrzny backend terminala w iframe. Rekomendowanym backendem jest Wetty
uruchomiony za lokalnym reverse proxy z ograniczonym dostępem do panelu.

Wetty pozostaje osobnym procesem Node.js; nie jest biblioteką PHP. Dzięki temu
obsługa PTY, websockets i terminala xterm.js nie blokuje procesów PHP-FPM.

### `local`

Tryb lokalny uruchamia proces `ssh` z serwera aplikacji i udostępnia go przez
prywatny strumień panelu. Jest przeznaczony do prostych lokalnych połączeń SSH
z allowlisty hostów. W środowisku produkcyjnym preferuj `gateway` z Wetty, bo
długie sesje terminalowe lepiej działają poza cyklem życia żądania PHP.
Ramka sesji lokalnej używa lokalnie vendoryzowanego `xterm.js`, żeby interpretować
sekwencje ANSI/curses wysyłane przez `screen`, `tmux`, `mc` i podobne programy.

## Zmienne środowiskowe

### Wetty / gateway

```ini
REMOTE_TERMINAL_ENABLED=1
REMOTE_TERMINAL_MODE=gateway
REMOTE_TERMINAL_GATEWAY_URL=https://syntaxdevteam.pl/wetty/
REMOTE_TERMINAL_SHARED_SECRET=change-me-long-random-secret
REMOTE_TERMINAL_TOKEN_PARAMETER=mp_token
REMOTE_TERMINAL_TOKEN_TTL=60
REMOTE_TERMINAL_SSH_HOST=127.0.0.1
REMOTE_TERMINAL_SSH_PORT=22
REMOTE_TERMINAL_SSH_USER=owner
REMOTE_TERMINAL_REQUIRE_HTTPS=1
```

Gateway musi zweryfikować token HMAC `sha256`, jeżeli reverse proxy albo wrapper
przed Wetty egzekwuje dostęp po tokenie. Token składa się z
`base64url(json_payload).hex_hmac`, gdzie HMAC liczony jest po części base64url.
Payload zawiera `uid`, `role`, `ssh_host`, `ssh_port`, `ssh_user`, `exp` i `nonce`.

Przykładowe uruchomienie Wetty za reverse proxy:

```bash
wetty --host 127.0.0.1 --port 3000 --base /wetty --ssh-host 127.0.0.1 --ssh-port 22 --ssh-user owner --ssh-auth publickey,password --allow-iframe
```

Reverse proxy powinien wystawiać Wetty wyłącznie po HTTPS, najlepiej pod ścieżką
niedostępną publicznie bez kontroli tokenu lub dodatkowej autoryzacji.

### Lokalny SSH

```ini
REMOTE_TERMINAL_ENABLED=1
REMOTE_TERMINAL_MODE=local
REMOTE_TERMINAL_HOSTS="local|127.0.0.1|22|debian|;vps|vps.syntaxdevteam.pl|22|debian|"
REMOTE_TERMINAL_ALLOWED_HOSTS=127.0.0.1,localhost,::1,vps.syntaxdevteam.pl
REMOTE_TERMINAL_SSH_HOST=127.0.0.1
REMOTE_TERMINAL_SSH_PORT=22
REMOTE_TERMINAL_SSH_USER=debian
REMOTE_TERMINAL_SSH_KEY_FILE=/var/www/.ssh/id_ed25519
REMOTE_TERMINAL_SESSION_TTL=3600
REMOTE_TERMINAL_SSH_BINARY=/usr/bin/ssh
REMOTE_TERMINAL_PTY_BINARY=/usr/bin/script
REMOTE_TERMINAL_REQUIRE_HTTPS=1
```

`REMOTE_TERMINAL_HOSTS` ma format:

```text
klucz|host|port|użytkownik|plik_klucza;drugi|host|port|użytkownik|plik_klucza
```

Wartość trzeba cytować, jeżeli zawiera średnik, ponieważ plik środowiskowy jest
parsowany jako INI. Pusty `plik_klucza` oznacza standardowe zachowanie `ssh`,
w tym możliwość interaktywnego wpisania hasła w terminalu.

## Uprawnienia

Instalacja modułu dodaje uprawnienie `remote_terminal.access` wyłącznie rolom
`owner` i `administrator`. Każda próba dostępu oraz uruchomienia sesji jest
zapisywana w audit logu.

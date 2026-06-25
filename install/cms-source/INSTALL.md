# Instalacja miniPORTAL

## Wymagania

- PHP 8.4 lub nowszy z rozszerzeniami `pdo_mysql`, `json`, `openssl`, `session` i `fileinfo`.
- Node.js dostępny dla opcjonalnego generatora favicon w panelu Branding.
- MySQL lub MariaDB oraz pusta baza danych.
- HTTPS w środowisku produkcyjnym.
- aplikacja OAuth GitHub dla logowania pierwszego Ownera.

## Uruchomienie

1. Skopiuj zawartość tego katalogu do katalogu publicznego domeny.
2. Nadaj użytkownikowi PHP prawo zapisu do katalogów `config/`, `cache/`,
   `uploads/branding/` oraz nadrzędnego katalogu `modules/`.
   Na Debianie/Ubuntu, gdzie PHP-FPM lub Apache działa w grupie `www-data`, wykonaj
   poniższe polecenia z głównego katalogu miniPORTAL:

   ```bash
   sudo chgrp -R www-data config cache uploads/branding
   sudo find config cache uploads/branding -type d -exec chmod 2770 {} \;
   sudo find config cache uploads/branding -type f -exec chmod 0660 {} \;
   sudo chgrp www-data modules
   sudo chmod 2775 modules
   ```

   Bit `2` w prawach katalogów powoduje dziedziczenie grupy `www-data` przez nowe
   pliki. Jeśli PHP działa jako inny użytkownik lub grupa, zastąp `www-data`
   wartością używaną przez PHP-FPM na danym serwerze. Nie stosuj praw `777`.
3. Otwórz `https://twoja-domena.example/install.php`.
4. Uzupełnij dane strony, bazy, aplikacji GitHub oraz wybierz moduły.
5. Po instalacji przejdź do `/admin/login` i zaloguj się wskazanym kontem GitHub.

Kreator przyjmuje wyłącznie pustą bazę, sam uruchamia migracje, tworzy pierwszego
Ownera, zapisuje sekrety w `config/installed.env` i blokuje ponowne uruchomienie
plikiem `config/installed.lock`. Oba pliki są chronione przez dołączony `.htaccess`.
Dystrybucja zawiera oficjalny publiczny klucz wydawcy modułów SyntaxDevTeam.
Kreator sprawdza go przed instalacją, a manager używa go później do weryfikacji
podpisanych aktualizacji. Klucz prywatny nie jest częścią dystrybucji.
Każda kopia miniPORTAL korzysta domyślnie wyłącznie z własnego
`config/installed.env`. Zewnętrzny plik można wskazać zmienną
`MINIPORTAL_ENV_FILE` ustawioną osobno dla danego virtual hosta.

Callback aplikacji GitHub należy ustawić na:

```text
https://twoja-domena.example/index.php?route=/admin/auth/github/callback
```

Analogiczne callbacki dla opcjonalnych integracji kończą się odpowiednio
`/discord/callback` i `/google/callback`.

Po instalacji warto dodatkowo ograniczyć prawa `config/installed.env` do odczytu
przez użytkownika PHP. Na serwerach bez Apache trzeba odtworzyć reguły blokujące
dostęp HTTP do `config/`, `core/`, `modules/`, `installer/`, `cache/` i plików SQL.

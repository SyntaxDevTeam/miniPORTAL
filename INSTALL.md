# Instalacja miniPORTAL

## Wymagania

- PHP 8.4 lub nowszy z rozszerzeniami `pdo_mysql`, `json`, `openssl`, `session` i `fileinfo`.
- Node.js dostępny dla opcjonalnego generatora favicon w panelu Branding.
- MySQL lub MariaDB oraz pusta baza danych.
- HTTPS w środowisku produkcyjnym.
- co najmniej jedna aplikacja OAuth/OIDC: GitHub, Discord, Google albo Microsoft.

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
4. W razie potrzeby zmień język samego kreatora na polski, angielski albo
   niemiecki. Ten wybór dotyczy tylko instalatora, a nie języka instalowanej
   strony.
5. Uzupełnij dane strony i bazy, wybierz co najmniej jednego dostawcę logowania
   oraz typ instalacji modułów:
   - `Podstawowa` instaluje tylko chroniony rdzeń wymagany do działania CMS.
   - `Pełna` instaluje wszystkie moduły dostępne w pakiecie.
   - `Własna` pozwala ręcznie wybrać moduły z opisami; zależności są dołączane
     automatycznie podczas instalacji.

   Moduły dedykowane Econizer, Konsola Minecraft, Licencje i Statystyki pluginów
   nie należą do czystej dystrybucji i nie pojawiają się w kreatorze. Po
   instalacji Owner może dodać ich podpisany pakiet ręcznie przez Manager modułów.
6. Po instalacji przejdź do `/admin/login`. Pierwsze poprawne logowanie zostanie
   atomowo przypisane do roli Owner.

Kreator przyjmuje wyłącznie pustą bazę, sam uruchamia migracje, przygotowuje
jednorazowy bootstrap pierwszego Ownera i blokuje ponowne uruchomienie plikiem
`config/installed.lock`. Sekrety dostawców trafiają do chronionego
`config/modules/auth-providers.env`, a pozostała konfiguracja do
`config/installed.env`.
Dystrybucja zawiera oficjalny publiczny klucz wydawcy modułów SyntaxDevTeam.
Kreator sprawdza go przed instalacją, a manager używa go później do weryfikacji
podpisanych aktualizacji. Klucz prywatny nie jest częścią dystrybucji.
Każda kopia miniPORTAL korzysta domyślnie wyłącznie z własnego
`config/installed.env`. Zewnętrzny plik można wskazać zmienną
`MINIPORTAL_ENV_FILE` ustawioną osobno dla danego virtual hosta.

Callback wybranego dostawcy należy ustawić na:

```text
https://twoja-domena.example/index.php?route=/admin/auth/{provider}/callback
```

Wartość `{provider}` to `github`, `discord`, `google` albo `microsoft`.

Po instalacji warto dodatkowo ograniczyć prawa `config/installed.env` do odczytu
przez użytkownika PHP. Na serwerach bez Apache trzeba odtworzyć reguły blokujące
dostęp HTTP do `config/`, `core/`, `modules/`, `installer/`, `cache/` i plików SQL.

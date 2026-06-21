# SyntaxCraft Mini-CMS

Prosty Mini-CMS w czystym PHP + MySQL, bez frameworkow i bibliotek `vendor`.

## Struktura

- `index.php` - publiczny widok stron.
- `admin/` - jeden panel z kontekstami: globalnym, serwerowym i konta.
- `app/CrudApp.class.php` - uniwersalna fasada bazy danych.
- `app/PanelAccess.class.php` - role, uprawnienia i konteksty panelu.
- `config/config.php` - konfiguracja aplikacji i bazy danych.
- `lib/` - adaptery aplikacji i funkcje pomocnicze.
- `database/schema.sql` - tabele oraz przykładowe strony.
- `database/migrations/` - migracje, w tym `002_panel_access.sql`.
- `assets/css/style.css` - style panelu admina i widoku konfiguracji.
- `assets/css/future-template.css` - publiczny szablon Bootstrap 5.

## Instalacja

1. Utworz baze danych MySQL, np. `syntaxcraft_cms`.
2. Zaimportuj `database/schema.sql`.
3. Ustaw dane bazy w `config/config.php`.
4. Utworz aplikacje w Discord Developer Portal i dodaj redirect URL:
   `/auth/discord/callback` na swojej domenie, np. `https://example.pl/auth/discord/callback`.
5. W `config/config.php` uzupelnij:
   - `DISCORD_CLIENT_ID`
   - `DISCORD_CLIENT_SECRET`
   - `DISCORD_ALLOWED_USER_IDS` dla kont wlascicieli/developerow
6. Wejdz na `/admin/`.

Panel nie uzywa klasycznego loginu i hasla. Kazdy loguje sie przez Discord, a `DISCORD_ALLOWED_USER_IDS` oznacza konta z globalna rola wlasciciela. Administratorzy serwerow i zwykli uzytkownicy dostaja widoki zalezne od kontekstu i uprawnien zapisanych w tabelach `panel_*`.

Konta wlascicieli maja w naglowku tryb `Podglad jako`, ktory pozwala sprawdzic panel z perspektywy developera, administratora serwera albo zwyklego uzytkownika bez zmiany realnych uprawnien konta.

## Linki

Publiczne strony dzialaja jako `/?page=slug` oraz, przy wlaczonym `mod_rewrite`, jako `/slug`.

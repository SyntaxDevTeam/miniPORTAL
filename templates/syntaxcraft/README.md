# SyntaxCraft theme

Motyw portalu hostingowego oparty na publicznym kontrakcie `ThemeInterface`.
Jest samodzielną kopią warstwy prezentacji motywu `minecraft`, więc jego
rozwój nie wymaga zmian w szablonie źródłowym miniPORTAL.

Źródłem prawdy jest katalog `templates/syntaxcraft` w macierzystej instalacji
miniPORTAL. Kopia działająca na `syntaxcraft.pl` musi być aktualizowana z tego
katalogu i zachowywać zgodność z każdą zmianą `ThemeInterface`.

## Aktywacja

W panelu przejdź do `System → Ustawienia → Szablon` i wybierz `Syntaxcraft`.
Alternatywnie ustaw w chronionym pliku środowiskowym:

```ini
APP_THEME=syntaxcraft
SITE_NAME=SyntaxCraft
SITE_DEFAULT_TITLE="SyntaxCraft — Twój serwer Minecraft. Bez komplikacji."
SITE_EYEBROW="Hosting Minecraft bez komplikacji"
SITE_THEME_COLOR="#264d46"
SITE_FOOTER_TEXT="Serwer, konsola i pliki. Jedno proste miejsce."
```

Po zmianie wyczyść cache szablonów z poziomu panelu.

## Zalecany układ strony głównej

1. `hero / split`: jasna obietnica, przyciski „Wybierz serwer” i „Zaloguj się”,
   a po prawej widżet terminala lub statusu.
2. `columns`: trzy korzyści — instalacja jednym kliknięciem, wydzielone zasoby,
   panel plików i konsola.
3. `columns`: plany Starter, Player i Network z linkami do konfiguratora.
4. `split`: opis panelu i profili optymalizacji.
5. `cta`: rozpoczęcie zakupu.
6. `contact`: pomoc techniczna, status usług i dokumentacja.

Właściwe ceny, parametry planów i dostępność usług powinny pochodzić z modułu
katalogu ofert, a nie być zapisane na stałe w motywie.

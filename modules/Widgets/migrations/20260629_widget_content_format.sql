ALTER TABLE widgets
    ADD COLUMN content_format ENUM('html', 'markdown') NOT NULL DEFAULT 'html' AFTER content;

UPDATE widgets
SET content = 'Uruchamianie SyntaxDevTerminal...
CoreAuth          READY
CorePages         READY
ThemeEngine       ONLINE
SyntaxCrudApp     CONNECTED
architecture:     MODULAR
security:         ENABLED
status:           READY_TO_USE
Witaj w SyntaxDevTerminal 0.1.5. Wpisz help i naciśnij Enter, aby zobaczyć dostępne komendy.'
WHERE widget_type = 'terminal'
  AND content = 'Witaj w SyntaxDevTerminal 0.1.5. Wpisz help i naciśnij Enter, aby zobaczyć dostępne komendy.';

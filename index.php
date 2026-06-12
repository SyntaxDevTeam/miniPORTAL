<?php

declare(strict_types=1);

use SyntaxDevTeam\Cms\Core\Bootstrap;

require_once __DIR__ . '/core/Bootstrap.php';

$config = require __DIR__ . '/config/config.php';
$application = Bootstrap::boot($config);
$theme = $application->theme();

$theme->start_page(
    'miniPORTAL - etap 1',
    'Punkt wejścia łączący konfigurację, rdzeń, warstwę CRUD i system szablonów.'
);
$theme->start_header(
    'Rdzeń jest widoczny i testowalny',
    'Index uruchamia aplikację, składa zależności i przekazuje prezentację do aktywnego motywu.'
);
$theme->end_header();

$theme->start_section();
$theme->start_grid();
$theme->start_column('lg-7');
$theme->start_card('Co działa w tym etapie', 'Namacalny rezultat');
$theme->render_text('Poniższa tabela powstaje z danych rdzenia, ale jej HTML generuje wyłącznie klasa motywu.');
$theme->end_card();
$theme->end_column();
$theme->start_column('lg-5');
$theme->start_card('Następny kierunek', 'Architektura');
$theme->render_text('Kolejne elementy rdzenia będą podłączane tutaj przez Bootstrap, a operacje bazodanowe przez CrudApp.');
$theme->render_button('Otwórz stylebook', 'templates/default/stylebook.html');
$theme->end_card();
$theme->end_column();
$theme->end_grid();

$theme->render_table(
    ['Element', 'Implementacja', 'Status'],
    $application->diagnostics()
);

$theme->render_alert(
    $application->database() === null
        ? 'Baza jest wyłączona. Ustaw DB_ENABLED=1 oraz zmienne DB_*, aby przetestować połączenie przez CrudApp.'
        : 'Połączenie z bazą zostało zestawione przez warstwę CrudApp.',
    $application->database() === null ? 'warning' : 'success'
);
$theme->end_section();

$theme->end_page();

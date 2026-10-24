<?php

declare(strict_types=1);

// Dane jawnego importu do pustej strony głównej CorePages; bez fallbacku runtime.
$section = static fn (array $data): array => array_replace([
    'type' => 'content', 'key' => '', 'eyebrow' => '', 'title' => '',
    'content_html' => '', 'content_format' => 'markdown', 'layout' => 'full',
    'button_label' => '', 'button_url' => '', 'items' => [],
], $data);
$item = static fn (string $label, string $title, string $content): array => [
    'label' => $label, 'title' => $title, 'content' => $content,
    'content_format' => 'markdown', 'item_kind' => 'card', 'icon_key' => '',
    'button_label' => '', 'button_url' => '', 'variant' => 'violet',
    'width' => 'standard', 'page_slug' => '',
];

return [
    $section([
        'type' => 'hero', 'key' => 'start', 'eyebrow' => 'MINECRAFT 26.2 · KONIE · SURVIVAL',
        'title' => "Pod jednym księżycem.\nPo swojemu.",
        'content_html' => 'Twórz stajnie. Rozwijaj hodowlę. Przeżywaj zawody. MoonVale to świat koni i survivalu, który budujemy razem z Tobą.',
        'buttons' => [['label' => 'Odkryj nasz świat ↗', 'url' => '#swiat'], ['label' => 'Co nas wyróżnia?', 'url' => '#systemy']],
    ]),
    $section([
        'key' => 'swiat', 'eyebrow' => '01 / WYBIERZ SWOJĄ DROGĘ', 'title' => 'Jeden świat. Mnóstwo możliwości.',
        'layout' => 'columns', 'content_html' => 'Dla miłośników koni, budowniczych i odkrywców. Tutaj każda pasja ma swoje miejsce.',
        'items' => [
            $item('01 / STAJNIE & HODOWLA', 'Od pierwszego konia do własnej stajni.', 'Twórz swoją stajnię i rozwijaj hodowlę. Autorskie systemy stawiają na realizm świata konnego i dają przestrzeń na Twoje pomysły.'),
            $item('02 / SPORT & EMOCJE', 'Twoja pasja. Twoje zawody.', 'Bierz udział w przeróżnych zawodach i odkrywaj kolejne wyzwania. Rozwijaj swoją przygodę z końmi po swojemu.'),
            $item('03 / SURVIVAL & WOLNOŚĆ', 'Więcej niż set i baza.', 'Eksploruj, buduj i graj w survival. Nowe funkcje i autorskie przedmioty sprawiają, że ukończona baza to początek kolejnego rozdziału.'),
        ],
    ]),
    $section([
        'key' => 'systemy', 'eyebrow' => '02 / AUTORSKIE DNA', 'title' => "Wasze pomysły.\nNasze systemy.", 'layout' => 'split',
        'content_html' => "Największy atut serwera? **Rozbudowane autorskie pluginy, które rozwijamy razem ze społecznością.**\n\nMasz realny wpływ na ich doskonalenie. Systemy stajni, hodowli i zawodów powstają z myślą o realizmie i rozgrywce, do której chce się wracać. Świat stale otrzymuje nowe funkcje i możliwości.",
    ]),
    $section([
        'key' => 'mozliwosci', 'eyebrow' => '03 / PRZESTRZEŃ NA WIĘCEJ', 'title' => 'Zbuduj coś, co będzie tylko Twoje.', 'layout' => 'columns',
        'items' => [
            $item('PRZESTRZEŃ', 'Wielkie działki. Wielkie pomysły.', 'Rozległe działki dają miejsce na stajnie, farmy i własne budowle. Nie ograniczaj wyobraźni — zaplanuj swój własny kawałek świata.'),
            $item('EKONOMIA', 'Rozwijaj swój dobytek.', 'Prace, farmy i handel między graczami otwierają wiele dróg rozwoju. Wybierz własny sposób na budowanie swojego majątku.'),
            $item('CUSTOMOWY ŚWIAT', 'Małe detale. Nowe możliwości.', 'ItemsAdder i niestandardowe przedmioty urozmaicają codzienną rozgrywkę. Odkrywaj elementy, dzięki którym ten świat ma własny charakter.'),
        ],
    ]),
    $section([
        'type' => 'cta', 'key' => 'contact', 'eyebrow' => '04 / TWÓJ KOLEJNY ROZDZIAŁ', 'title' => 'Świat, który rośnie razem z Tobą.',
        'content_html' => 'Konie czy klasyczny survival? Nie musisz wybierać. Znajdź swoje miejsce w społeczności, która współtworzy przyszłość serwera.',
        'buttons' => [['label' => 'Wróć do początku ↑', 'url' => '#start']],
    ]),
];

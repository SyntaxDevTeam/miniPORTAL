<?php declare(strict_types=1); ?>
<div class="event-schedule">
<?php if(isset($model['toolbar'])): ?><nav class="event-admin-toolbar" aria-label="Narzędzia kalendarza"><?php foreach($model['toolbar'] as $link): ?><a href="<?= $escape($link['url']) ?>"><?= $escape($link['label']) ?></a><?php endforeach ?></nav><?php endif ?>
<?php if(isset($model['workspace'])): $workspace=$model['workspace']; ?>
<header class="event-workspace"><p class="event-eyebrow">Przestrzeń zespołu</p><h2><?= $escape($workspace['title']) ?></h2><p><?= $escape($workspace['subtitle']) ?></p>
<?php if($workspace['tabs']): ?><nav class="event-tabs" aria-label="Sekcje wydarzenia"><?php foreach($workspace['tabs'] as $key=>$label): ?><a href="/admin/events?id=<?= (int)$workspace['id'] ?>&amp;tab=<?= $escape($key) ?>"<?= $workspace['active']===$key?' aria-current="page"':'' ?>><?= $escape($label) ?></a><?php endforeach ?></nav><?php endif ?>
</header>
<script defer src="/templates/shared/assets/js/event-workspace.js?v=<?= filemtime(__DIR__.'/../../shared/assets/js/event-workspace.js') ?>"></script>
<?php endif ?>
<?php if(isset($model['wizard'])): ?>
<section class="event-wizard" aria-label="Kreator nowego wpisu">
<p class="event-wizard-intro">Trzy różne cele, jasne zasady widoczności. Znak <strong>?</strong> przy polu otwiera wskazówkę z przykładem. Niczego nie zapisujemy przed ostatnim krokiem.</p>
<noscript><p>Wypełnij formularz poniżej. Dla zadania i planu wybierz „Tylko administracja”. Pola dla graczy dotyczą wyłącznie wydarzeń.</p></noscript>
<?php $this->render_form('/admin/events',$model['wizard']['fields'],'Utwórz wpis',$model['wizard']['token']); ?>
</section>
<?php endif ?>
<?php if (!empty($model['hero'])): $featured=$model['featured']??null; $activeView=$model['view']??'upcoming'; ?>
<header class="event-hero event-landing-hero">
<div class="event-landing-copy"><p class="event-eyebrow">Kalendarz wydarzeń</p><h1>Tu zaczynają się<br>wspólne historie.</h1><p class="event-landing-lead">Konkursy, spotkania i przygody tworzone przez naszą społeczność. Sprawdź, co dzieje się na serwerze — i znajdź coś dla siebie.</p><a class="event-landing-jump" href="#event-agenda">Odkryj wydarzenia <span aria-hidden="true">↓</span></a></div>
<?php if($featured): ?>
<div class="event-spotlight"><p class="event-spotlight-kicker"><span aria-hidden="true"></span><?= $featured['phase']==='live'?'Trwa właśnie teraz':'Następne wydarzenie' ?></p><span class="event-spotlight-category"><?= $escape($featured['category']) ?></span><h2><a href="<?= $escape($featured['url']) ?>"><?= $escape($featured['title']) ?></a></h2><p class="event-spotlight-date"><?= $featured['phase']==='live'?'Do ':'Start: ' ?><time datetime="<?= $escape($featured['phase']==='live'?$featured['end_iso']:$featured['start_iso']) ?>"><?= $escape($featured['phase']==='live'?$featured['end_label']:$featured['start_label']) ?></time></p><a class="event-spotlight-action" href="<?= $escape($featured['url']) ?>">Poznaj wydarzenie <span aria-hidden="true">↗</span></a></div>
<?php endif ?>
</header>
<div class="event-agenda-toolbar" id="event-agenda">
<nav class="event-tabs" aria-label="Widok kalendarza">
<?php foreach (['upcoming'=>['/events','Najbliższe wydarzenia'],'month'=>['/events?view=month','Kalendarz miesiąca'],'archive'=>['/events?view=archive','Minione wydarzenia']] as $key=>[$url,$label]): ?><a href="<?= $escape($url) ?>"<?= $activeView===$key?' aria-current="page"':'' ?>><?= $escape($label) ?></a><?php endforeach ?>
</nav><p class="event-agenda-zone">Godziny według czasu polskiego</p></div>
<?php endif ?>
<?php if (isset($model['metrics'])): ?><div class="event-metrics"><?php foreach($model['metrics'] as $label=>$number): ?><div><strong><?= $escape($number) ?></strong><span><?= $escape($label) ?></span></div><?php endforeach ?></div><?php endif ?>
<?php if (isset($model['month'])): ?>
<div class="event-month-nav"><a href="<?= $escape($model['previous']) ?>" aria-label="Poprzedni miesiąc">←</a><h2><?= $escape($model['month']) ?></h2><a href="<?= $escape($model['next']) ?>" aria-label="Następny miesiąc">→</a></div>
<div class="event-month" role="list" aria-label="Wydarzenia w miesiącu">
<?php foreach ($model['days'] as $day): ?><div class="event-day<?= $day['today']?' is-today':'' ?>" role="listitem"><span><?= $escape($day['label']) ?></span><?php foreach ($day['events'] as $event): ?><a href="<?= $escape($event['url']) ?>"><?= $escape($event['title']) ?></a><?php endforeach ?></div><?php endforeach ?>
</div>
<?php endif ?>
<?php $groups=$model['groups']??[]; $hasEvents=array_any($groups,static fn(array $items):bool=>$items!==[]); ?>
<?php if($groups!==[] && !$hasEvents): ?><section class="event-empty-state"><p class="event-eyebrow"><?= ($model['view']??'upcoming')==='archive'?'Archiwum wydarzeń':'Kolejne historie przed nami' ?></p><h2><?= ($model['view']??'upcoming')==='archive'?'Tutaj wrócimy do wspólnych chwil.':'Nowe przygody pojawią się tutaj.' ?></h2><p><?= ($model['view']??'upcoming')==='archive'?'Zakończone wydarzenia znajdziesz w tym miejscu.':'Gdy ogłosimy kolejny konkurs lub spotkanie, znajdziesz tu jego termin i wszystkie szczegóły. Zajrzyj do nas ponownie.' ?></p></section><?php endif ?>
<?php foreach ($groups as $label=>$events): if($events===[])continue; ?>
<section class="event-group"><h2><?= $escape($label) ?> <span><?= count($events) ?></span></h2>
<?php if($events===[]): ?><p class="event-empty">Na razie nie ma tutaj wydarzeń. Zajrzyj ponownie — nowe przygody są przed nami.</p><?php endif ?>
<div class="event-cards<?= count($events)===1?' event-cards--single':'' ?>"><?php foreach($events as $event): ?>
<article class="event-card<?= ($event['phase']??'')==='live'?' is-live':'' ?>">
<div class="event-date"><strong><?= $escape($event['day']) ?></strong><span><?= $escape($event['month']) ?></span></div>
<div class="event-card-content"><div class="event-tags"><span><?= $escape($event['category']) ?></span><span><?= $escape($event['phase_label']) ?></span></div>
<h3><a href="<?= $escape($event['url']) ?>"><?= $escape($event['title']) ?></a></h3>
<p class="event-range"><?= $escape($event['range']) ?></p><?php if($event['summary']!==''): ?><p><?= $escape($event['summary']) ?></p><?php endif ?>
<?php if($event['location']!==''): ?><p class="event-location">Miejsce: <?= $escape($event['location']) ?></p><?php endif ?>
<a class="event-more" href="<?= $escape($event['url']) ?>">Szczegóły wydarzenia <span aria-hidden="true">↗</span></a></div>
</article><?php endforeach ?></div></section><?php endforeach ?>
<?php if(isset($model['detail'])): $event=$model['detail']; $publicDetail=!empty($model['public_detail']); ?>
<article class="event-detail event-detail--<?= $escape($event['phase']) ?>">
<?php if($publicDetail): ?>
<nav class="event-breadcrumb" aria-label="Ścieżka nawigacji"><a href="/events"><span aria-hidden="true">←</span> Wszystkie wydarzenia</a><span aria-hidden="true">/</span><span><?= $escape($event['category']) ?></span></nav>
<?php endif ?>
<header class="event-detail-header">
<div class="event-detail-copy">
<p class="event-eyebrow">Wydarzenie społeczności</p>
<div class="event-detail-labels"><span><?= $escape($event['category']) ?></span><span class="event-status"><span aria-hidden="true"></span><?= $escape($event['phase_label']) ?></span></div>
<h1 class="event-detail-title"><?= $escape($event['title']) ?></h1>
<?php if($event['summary']!==''): ?><p class="event-detail-lead"><?= $escape($event['summary']) ?></p><?php endif ?>
</div>
<div class="event-detail-emblem" aria-hidden="true"><span>☾</span><i>✦</i></div>
</header>
<div class="event-detail-layout">
<div class="event-detail-content">
<nav class="event-content-nav" aria-label="Treść wydarzenia"><a href="#event-description">O wydarzeniu</a><?php if($event['rules']!==''): ?><a href="#event-rules">Zasady udziału</a><?php endif ?><?php if($event['rewards']!==''): ?><a href="#event-rewards">Nagrody</a><?php endif ?></nav>
<section id="event-description" class="event-detail-section"><h2>O wydarzeniu</h2><div class="event-prose<?= ($event['content_format']??'plain')==='markdown'?' is-markdown':'' ?>"><?= $renderBody($event['description'],$event['content_format']??'plain') ?></div></section>
<?php if($event['rules']!==''): ?><section id="event-rules" class="event-detail-section"><h2>Zasady uczestnictwa</h2><div class="event-prose<?= ($event['content_format']??'plain')==='markdown'?' is-markdown':'' ?>"><?= $renderBody($event['rules'],$event['content_format']??'plain') ?></div></section><?php endif ?>
<?php if($event['rewards']!==''): ?><section id="event-rewards" class="event-detail-section"><h2>Nagrody</h2><div class="event-prose<?= ($event['content_format']??'plain')==='markdown'?' is-markdown':'' ?>"><?= $renderBody($event['rewards'],$event['content_format']??'plain') ?></div></section><?php endif ?>
<?php if($publicDetail): ?><a class="event-bottom-back" href="/events">← Wróć do kalendarza wydarzeń</a><?php endif ?>
</div>
<aside class="event-detail-sidebar" aria-label="Informacje organizacyjne">
<div class="event-info-card"><p class="event-eyebrow">Zaplanuj swój udział</p><h2>Warto wiedzieć</h2>
<dl class="event-info-list">
<div><dt>Rozpoczęcie</dt><dd><time datetime="<?= $escape($event['start_iso']) ?>"><?= $escape($event['start_label']) ?></time></dd></div>
<div><dt>Zakończenie</dt><dd><time datetime="<?= $escape($event['end_iso']) ?>"><?= $escape($event['end_label']) ?></time></dd></div>
<div><dt>Miejsce</dt><dd><?= $escape($event['location']?:'Na serwerze') ?></dd></div>
</dl>
<p class="event-timezone">Godziny według czasu polskiego <span>Europe/Warsaw</span></p>
<?php if($publicDetail): ?><a class="event-calendar-action" href="/events/export?id=<?= (int)$event['id'] ?>" hx-boost="false"><span aria-hidden="true">+</span> Dodaj do kalendarza</a><p class="event-calendar-hint">Zapisz termin w kalendarzu na telefonie lub komputerze.</p><?php endif ?>
</div>
</aside>
</div>
</article>
<?php endif ?>
</div>

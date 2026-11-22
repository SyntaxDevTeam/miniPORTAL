<?php
declare(strict_types=1);
$tab = $model['tab'];
$editing = $model['editing'];
$base = '/admin/recruitment/config';
$back = $base.'?tab='.$tab;
$pagination = static fn(int $page): string => $base.'?'.http_build_query(['tab'=>$tab,'q'=>$model['query'],'state'=>$model['state'],'page'=>$page]);
$titles = ['settings'=>'Ustawienia naboru','rank'=>'Rangi w rekrutacji','question'=>'Pytania formularza'];
$descriptions = ['settings'=>'Zdecyduj, kiedy przyjmujesz podania i jak zapraszasz kandydatów do zespołu.','rank'=>'Zarządzaj dostępnymi rolami i wymaganiami dla kandydatów.','question'=>'Ułóż formularz, który pomoże Ci lepiej poznać przyszły zespół.'];
$types = ['text'=>'Krótki tekst','textarea'=>'Dłuższa odpowiedź','scale'=>'Ocena 1–10'];
?>
<div class="recruitment-config" data-recruitment-config>
    <div class="rc-overview" aria-label="Stan konfiguracji">
        <div><span class="rc-caption">NABÓR</span><strong><span class="rc-dot <?= $model['settings']['is_open'] ? 'rc-dot--active' : '' ?>" aria-hidden="true"></span><?= $model['settings']['is_open'] ? 'Otwarty' : 'Zamknięty' ?></strong><small>Przyjmowanie nowych podań</small></div>
        <div><span class="rc-caption">DOSTĘPNE RANGI</span><strong><?= $e($model['active_ranks']) ?><span class="rc-total"> / <?= $e($model['rank_count']) ?></span></strong><small>Role widoczne dla kandydatów</small></div>
        <div><span class="rc-caption">PYTANIA FORMULARZA</span><strong><?= $e($model['active_questions']) ?><span class="rc-total"> / <?= $e($model['question_count']) ?></span></strong><small>Aktywne pytania w podaniu</small></div>
    </div>
    <nav class="rc-tabs" aria-label="Konfiguracja rekrutacji">
        <?php foreach (['settings'=>['Nabór',null], 'rank'=>['Rangi',$model['rank_count']], 'question'=>['Pytania',$model['question_count']]] as $key=>[$label,$count]): ?>
        <a href="<?= $e($base.'?tab='.$key) ?>"<?= $key === $tab ? ' aria-current="page"' : '' ?>><?= $e($label) ?><?php if ($count !== null): ?><span><?= $e($count) ?></span><?php endif; ?></a>
        <?php endforeach; ?>
    </nav>
    <?php if ($model['saved']) $this->render_alert('Zmiany zostały zapisane.', 'success'); ?>
    <section class="rc-panel" aria-labelledby="rc-title">
        <header class="rc-panel-header">
            <div>
                <span class="rc-caption"><?= $editing ? ($model['id'] > 0 ? 'EDYCJA WPISU' : 'NOWY WPIS') : 'KONFIGURACJA' ?></span>
                <h2 id="rc-title"><?= $e($editing ? ($model['id'] > 0 ? ($tab === 'rank' ? 'Edytuj rangę' : 'Edytuj pytanie') : ($tab === 'rank' ? 'Dodaj rangę' : 'Dodaj pytanie')) : $titles[$tab]) ?></h2>
                <p><?= $e($descriptions[$tab]) ?></p>
            </div>
            <?php if ($editing): ?>
            <a class="rc-button" href="<?= $e($back) ?>">← Wróć do listy</a>
            <?php elseif ($tab !== 'settings'): ?>
            <a class="rc-button rc-button--primary" href="<?= $e($back.'&new=1') ?>">+ <?= $tab === 'rank' ? 'Dodaj rangę' : 'Dodaj pytanie' ?></a>
            <?php endif; ?>
        </header>
        <?php if ($tab === 'settings' || $editing): ?>
        <div class="rc-editor">
            <div class="rc-editor-fields">
                <?php $this->render_form($base, $model['fields'], $tab === 'settings' ? 'Zapisz ustawienia naboru' : ($model['id'] > 0 ? 'Zapisz zmiany' : ($tab === 'rank' ? 'Dodaj rangę' : 'Dodaj pytanie')), $token); ?>
            </div>
            <aside class="rc-help" aria-label="Wskazówki edycji">
                <span class="rc-caption">WARTO WIEDZIEĆ</span>
                <?php if ($tab === 'settings'): ?>
                <h3>Ty ustalasz tempo naboru.</h3>
                <p>Nagłówek i wprowadzenie zobaczą kandydaci na stronie rekrutacji.</p>
                <p>Zamknięcie naboru blokuje nowe zgłoszenia. Wysłane podania nadal możesz oceniać.</p>
                <p>Rangi i pytania edytujesz w osobnych zakładkach powyżej.</p>
                <?php else: ?>
                <h3><?= $tab === 'rank' ? 'Jasna rola, jasne oczekiwania.' : 'Dobre pytanie ułatwia decyzję.' ?></h3>
                <p><?= $tab === 'rank' ? 'Opisz zadania i wymagania rangi, aby kandydat mógł świadomie wybrać swoją rolę.' : 'Wybierz krótką lub dłuższą odpowiedź. Skala 1–10 sprawdzi się przy samoocenie umiejętności.' ?></p>
                <p>Niższa kolejność oznacza wcześniejsze miejsce w formularzu.</p>
                <p>Wyłączenie wpisu ukrywa go w nowych formularzach. Zachowuje wcześniejsze podania.</p>
                <?php endif; ?>
                <a href="/recruitment" class="rc-preview-link">Zobacz formularz publiczny ↗</a>
            </aside>
        </div>
        <?php else: ?>
        <form method="get" action="<?= $e($base) ?>" class="rc-filters" role="search" aria-label="Wyszukaj wpisy">
            <input type="hidden" name="tab" value="<?= $e($tab) ?>">
            <div><label for="rc-search">Szukaj <?= $tab === 'rank' ? 'rangi' : 'pytania' ?></label><input id="rc-search" name="q" type="search" maxlength="100" placeholder="<?= $tab === 'rank' ? 'Nazwa lub opis rangi…' : 'Treść pytania lub podpowiedź…' ?>" value="<?= $e($model['query']) ?>"></div>
            <div><label for="rc-state">Widoczność</label><select id="rc-state" name="state"><option value="">Wszystkie wpisy</option value="active"<?= $model['state'] === 'active' ? ' selected' : '' ?>>Aktywne</option><option value="inactive"<?= $model['state'] === 'inactive' ? ' selected' : '' ?>>Wyłączone</option></select></div>
            <button type="submit" class="rc-button">Szukaj</button>
            <?php if ($model['query'] !== '' || $model['state'] !== ''): ?><a class="rc-reset" href="<?= $e($back) ?>">Wyczyść</a><?php endif; ?>
        </form>
        <div class="rc-list-meta"><span><?= $e($model['total']) ?> <?= $tab === 'rank' ? 'rang' : 'pytań' ?></span><span>Uporządkowane według kolejności w formularzu</span></div>
        <?php if ($model['items'] === []): ?>
        <div class="rc-empty"><h3>Brak pasujących wpisów</h3><p>Zmień wyszukiwanie lub dodaj nowy wpis.</p></div>
        <?php else: ?>
        <div class="rc-list" role="list" aria-label="<?= $e($titles[$tab]) ?>">
            <?php foreach ($model['items'] as $row): ?>
            <article class="rc-row" role="listitem">
                <span class="rc-order" title="Kolejność w formularzu"><?= $e($row['sort_order']) ?></span>
                <div class="rc-row-content"><h3><a href="<?= $e($back.'&id='.$row['id']) ?>"><?= $e($row['name'] ?? $row['label']) ?></a></h3>
                    <div class="rc-row-tags"><span class="rc-status <?= $row['active'] ? 'rc-status--active' : '' ?>"><?= $row['active'] ? 'Aktywne' : 'Wyłączone' ?></span>
                    <?php if ($tab === 'question'): ?><span><?= $e($types[$row['type']] ?? $row['type']) ?></span><span><?= $row['required'] ? 'Wymagane' : 'Opcjonalne' ?></span><?php endif; ?></div>
                    <?php if (($row['description'] ?? $row['help']) !== ''): ?><p><?= $e(mb_strimwidth($row['description'] ?? $row['help'], 0, 170, '…')) ?></p><?php endif; ?>
                </div>
                <a class="rc-button rc-row-edit" href="<?= $e($back.'&id='.$row['id']) ?>" aria-label="<?= $e('Edytuj: '.($row['name'] ?? $row['label'])) ?>">Edytuj →</a>
            </article>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <nav class="rc-pagination" aria-label="Strony listy">
            <span>Strona <?= $e($model['page']) ?> z <?= $e($model['pages']) ?> · maks. 10 wpisów na stronie</span>
            <div><?php if ($model['page'] > 1): ?><a class="rc-button" href="<?= $e($pagination($model['page']-1)) ?>">← Poprzednia</a><?php endif; ?><?php if ($model['page'] < $model['pages']): ?><a class="rc-button" href="<?= $e($pagination($model['page']+1)) ?>">Następna →</a><?php endif; ?></div>
        </nav>
        <?php endif; ?>
    </section>
    <p class="rc-footnote">Zmiany dotyczą nowych formularzy. Treść wysłanych podań i historia ocen pozostają zachowane.</p>
</div>

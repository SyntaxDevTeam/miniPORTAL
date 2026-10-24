<?php
/** @var array $editor Public-only card editor model. */
$e = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$card=$editor['card'];
$labels=['palette'=>'Kolory','backdrop'=>'Sceneria','layout'=>'Położenie postaci','stat_style'=>'Wygląd statystyk'];
$available=[];
foreach ($editor['stat_labels'] as $id=>$label) {
    $value='—';
    foreach ($editor['available_statistics'] as $stat) if ($stat['label']===$label) $value=$stat['value'];
    $available[$id]=['label'=>$label,'value'=>$value];
}
?>
<section class="card-studio" data-card-studio data-card-statistics="<?= $e(json_encode($available, JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)) ?>" data-card-status="<?= $e($editor['preview']['status']) ?>">
    <template data-card-time><div class="profile-metric"><span><?= $e($editor['time_metric']['label']) ?></span><strong><?= $e($editor['time_metric']['value']) ?></strong></div></template>
    <nav class="profile-toolbar" aria-label="Nawigacja kreatora">
        <a href="/account/player-profile">← Mój profil</a>
        <a href="<?= $e($editor['public_url']) ?>">Zobacz opublikowaną kartę ↗</a>
    </nav>
    <header class="card-studio-heading"><div><span class="card-studio-kicker">Twoja wizytówka na MoonVale</span><h2>Stwórz kartę po swojemu</h2><p>Klikaj, wybieraj i obserwuj zmiany. Opublikuj, gdy wszystko pasuje.</p></div><span class="card-studio-state"><?= $editor['published'] ? 'Karta opublikowana' : 'Jeszcze nieopublikowana' ?></span></header>
    <div class="card-studio-preview" data-card-preview>
        <div class="card-studio-preview-label"><span>Podgląd publiczny</span><small>Zmiany tutaj widzisz tylko Ty</small></div>
        <?php $this->render_profile_panel($editor['preview']); ?>
    </div>
    <form class="card-studio-controls" action="<?= $e($editor['action']) ?>" method="post" data-card-form>
        <?php $this->csrf_field($editor['csrf']); ?>
        <input type="hidden" name="server" value="<?= (int)$editor['server'] ?>">
        <div class="card-studio-design">
            <?php foreach ($editor['options'] as $name=>$options): ?>
            <fieldset class="card-studio-field"><legend><?= $e($labels[$name]) ?></legend><div class="card-choice-grid card-choices-<?= $e($name) ?>">
                <?php foreach ($options as $value=>$label): ?>
                <label class="card-choice card-choice-<?= $e($value) ?>"><input type="radio" name="<?= $e($name) ?>" value="<?= $e($value) ?>"<?= $card[$name]===$value?' checked':'' ?>><span class="card-choice-visual" aria-hidden="true"></span><span><?= $e($label) ?></span></label>
                <?php endforeach; ?>
            </div></fieldset>
            <?php endforeach; ?>
        </div>
        <div class="card-studio-personal">
            <label for="card-bio">Kilka słów o Tobie <small data-card-bio-count><?= mb_strlen($card['bio']) ?>/280</small></label>
            <textarea id="card-bio" name="bio" maxlength="280" rows="2" placeholder="Konie, budowanie, przygody… co lubisz na MoonVale?"><?= $e($card['bio']) ?></textarea>
            <div class="card-studio-toggles"><label><input type="checkbox" name="show_status" value="1"<?= $card['show_status']?' checked':'' ?>><span>Status online</span></label><label><input type="checkbox" name="show_time" value="1"<?= $card['show_time']?' checked':'' ?>><span>Czas gry</span></label></div>
        </div>
        <fieldset class="card-studio-field card-studio-statistics"><legend>Co Cię wyróżnia?</legend><p>Włącz ulubione statystyki. Strzałkami ustaw kolejność wybranych kafelków.</p><div class="card-stat-choices" data-card-stat-choices>
            <?php $ordered=array_unique([...$card['statistics'],...array_keys($editor['stat_labels'])]); foreach ($ordered as $i=>$id): ?>
            <div class="card-stat-choice" data-card-stat="<?= $e($id) ?>"><label><input type="checkbox" name="stat_<?= $i ?>" value="<?= $e($id) ?>"<?= in_array($id,$card['statistics'],true)?' checked':'' ?>><span class="card-stat-check" aria-hidden="true"></span><span><?= $e($available[$id]['label']) ?><strong><?= $e($available[$id]['value']) ?></strong></span></label><div class="card-stat-move"><button type="button" data-card-move="up" aria-label="<?= $e('Przesuń wcześniej: '.$available[$id]['label']) ?>">←</button><button type="button" data-card-move="down" aria-label="<?= $e('Przesuń później: '.$available[$id]['label']) ?>">→</button></div></div>
            <?php endforeach; ?>
        </div></fieldset>
        <footer class="card-studio-save"><div><p data-card-save-state role="status">Podgląd szkicu. Zapisz lub opublikuj swój wybór.</p><small>Saldo, przedmioty i szczegóły pluginów pozostają prywatne.</small></div><div><button class="card-save-draft" type="submit" name="action" value="save">Zapisz szkic</button><button class="card-publish" type="submit" name="action" value="publish">Opublikuj kartę</button><?php if ($editor['published']): ?><button class="card-unpublish" type="submit" name="action" value="unpublish">Przywróć standardowy profil</button><?php endif; ?></div></footer>
    </form>
</section>

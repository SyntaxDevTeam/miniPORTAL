<?php
/** @var array $editor Owner-authorized editor with a closed public preview. */
$e = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$card=$editor['card'];
$labels=['pose'=>'Postać','palette'=>'Filtr obrazu','backdrop'=>'Sceneria','layout'=>'Położenie postaci'];
$available=[];
foreach ($editor['available_statistics'] as $row) $available[$row['id']]=['label'=>$row['label'],'value'=>$row['value']];
$horseChoices=[];foreach($editor['horses'] as $horse)$horseChoices[$horse['id']]=$horse;
?>
<section class="card-studio" data-card-studio data-card-statistics="<?= $e(json_encode($available, JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)) ?>" data-card-horses="<?= $e(json_encode($horseChoices, JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)) ?>">
    <nav class="profile-toolbar" aria-label="Nawigacja kreatora"><a href="/account/player-profile">← Mój profil</a><a href="<?= $e($editor['public_url']) ?>">Zobacz opublikowaną kartę ↗</a></nav>
    <header class="card-studio-heading"><div><span class="card-studio-kicker">Twoja wizytówka na MoonVale</span><h2>Card Studio <span class="studio-version">2.0</span></h2><p>Twoja postać. Twój świat. Twoja wizytówka.</p></div><span class="card-studio-state"><?= $editor['published'] ? (($editor['published_version'] ?? 2)===1 ? 'Opublikowany profil klasyczny' : 'Karta opublikowana') : 'Jeszcze nieopublikowana' ?></span></header>
    <div class="card-studio-preview" data-card-preview><div class="card-studio-preview-label"><span>Podgląd na żywo</span><div class="studio-history"><button type="button" data-studio-undo disabled aria-label="Cofnij zmianę" title="Cofnij">↶</button><button type="button" data-studio-redo disabled aria-label="Ponów zmianę" title="Ponów">↷</button></div><small>Pełna jakość PNG</small></div><?php $this->render_player_showcase($editor['preview']['showcase']); ?></div>
    <form enctype="multipart/form-data" class="card-studio-controls" action="<?= $e($editor['action']) ?>" method="post" data-card-form>
        <?php $this->csrf_field($editor['csrf']); ?>
        <input type="hidden" name="server" value="<?= (int)$editor['server'] ?>">
        <input type="hidden" name="card_version" value="2">
        <input type="hidden" name="format" value="<?= $e($card['format']) ?>">
        <input type="hidden" name="stat_style" value="<?= $e($card['stat_style']) ?>">
        <input type="hidden" name="show_status" value="0">
        <input type="hidden" name="background_id" value="<?= $e($card['background_id'] ?? '') ?>">
        <nav class="studio-sections" aria-label="Ustawienia karty"><button type="button" data-studio-tab="design" aria-pressed="true">Wygląd</button><button type="button" data-studio-tab="background" aria-pressed="false">Tło</button><button type="button" data-studio-tab="personal" aria-pressed="false">Dane</button><button type="button" data-studio-tab="horse" aria-pressed="false">Koń</button></nav>
        <div class="studio-control-scroll"><section class="studio-background" data-studio-section="background">
          <div class="studio-section-title"><h3>Twój własny krajobraz</h3><p>Zgraj kadr, kolory i światło.</p></div>
          <label class="studio-upload"><span>+</span><strong>Wgraj własne tło</strong><small>JPG, PNG lub WebP · do 8 MB</small><input type="file" name="background" accept="image/jpeg,image/png,image/webp" data-background-file></label>
          <p data-background-state role="status"></p><button type="button" class="studio-text-button" data-background-remove>Usuń własny obraz</button>
          <?php foreach (['crop_x'=>['Kadr w poziomie',0,100], 'crop_y'=>['Kadr w pionie',0,100], 'zoom'=>['Powiększenie',100,180], 'blur'=>['Rozmycie dolnej krawędzi',0,100], 'shade'=>['Przyciemnienie',0,80]] as $key=>[$label,$min,$max]): ?>
          <label class="studio-range"><?= $e($label) ?><output data-range-output="<?= $key ?>"><?= (int)$card[$key] ?>%</output><input type="range" name="<?= $key ?>" min="<?= $min ?>" max="<?= $max ?>" value="<?= (int)$card[$key] ?>"></label>
          <?php endforeach; ?>
          <button type="button" class="studio-text-button" data-background-reset>Wyzeruj korekcję tła</button>
        </section>
        <div class="card-studio-design" data-studio-section="design">
            <?php foreach ($editor['options'] as $name=>$options): ?>
            <fieldset class="card-studio-field"<?= $name==='backdrop'?' data-studio-backdrops':'' ?>><legend><?= $e($labels[$name]) ?></legend><div class="card-choice-grid card-choices-<?= $e($name) ?>">
                <?php foreach ($options as $value=>$label): ?>
                <label class="card-choice card-choice-<?= $e($value) ?><?= $name==='pose'?' card-choice-pose-'.$e($value):'' ?>"><input type="radio" name="<?= $e($name) ?>" value="<?= $e($value) ?>"<?= $card[$name]===$value?' checked':'' ?>><span class="card-choice-visual" aria-hidden="true"></span><span><?= $e($label) ?></span></label>
                <?php endforeach; ?>
            </div><?php if ($name==='pose'): ?><p class="card-studio-pose-help" data-card-pose-help role="status" hidden></p><?php endif; ?></fieldset>
            <?php endforeach; ?>
        </div>
        <div class="card-studio-horse" data-studio-section="horse">
            <label>Koń na Twojej publicznej karcie<select name="horse_id"><option value="">Nie pokazuj konia</option><?php if($card['horse_id']!==null&&!isset($horseChoices[$card['horse_id']])): ?><option value="<?= $e($card['horse_id']) ?>" selected>Poprzednio wybrany koń jest niedostępny</option><?php endif; ?><?php foreach($editor['horses'] as $horse): ?><option value="<?= $e($horse['id']) ?>"<?= $card['horse_id']===$horse['id']?' selected':'' ?>><?= $e($horse['name'].' · '.$horse['breed']) ?></option><?php endforeach; ?></select></label>
            <label><span><input type="checkbox" name="show_horse_stats" value="1"<?= $card['show_horse_stats']?' checked':'' ?>> Pokaż parametry konia</span></label>
            <p>Wybór konia oznacza pokazanie jego imienia, rasy i umaszczenia na opublikowanej karcie. Opcjonalne parametry to skok, bazowa prędkość i zdrowie. Lista pochodzi z ostatnich potwierdzonych danych Twoich koni. Poza jazdy wymaga wyboru konia.</p>
        </div>
        <div class="card-studio-personal" data-studio-section="personal">
            <label for="card-bio">Kilka słów o Tobie <small data-card-bio-count><?= mb_strlen($card['bio']) ?>/280</small></label>
            <textarea id="card-bio" name="bio" maxlength="280" rows="2" placeholder="Konie, budowanie, przygody… co lubisz na MoonVale?"><?= $e($card['bio']) ?></textarea>
            <div class="card-studio-toggles"><label><input type="checkbox" name="show_time" value="1"<?= $card['show_time']?' checked':'' ?>><span>Czas gry</span></label></div>
        </div>
        <fieldset class="card-studio-field card-studio-statistics" data-studio-section="personal"><legend>Co Cię wyróżnia?</legend><p>Wybierz maksymalnie trzy statystyki. Strzałkami ustaw kolejność.</p><label class="card-stat-limit">Szukaj statystyki <input type="search" data-card-stat-search placeholder="Np. skoki, diamenty…"></label><p class="card-stat-limit" data-card-stat-count role="status"></p><div class="card-stat-choices" data-card-stat-choices>
            <?php $ordered=array_unique([...$card['statistics'],...array_keys($available)]); foreach ($ordered as $id): $row=$available[$id]??['label'=>$id,'value'=>'—']; ?>
            <div class="card-stat-choice" data-card-stat="<?= $e($id) ?>"><label><input type="checkbox" name="statistics[]" value="<?= $e($id) ?>"<?= in_array($id,$card['statistics'],true)?' checked':'' ?>><span class="card-stat-check" aria-hidden="true"></span><span><?= $e($row['label']) ?><strong><?= $e($row['value']) ?></strong></span></label><div class="card-stat-move"><button type="button" data-card-move="up" aria-label="<?= $e('Przesuń wcześniej: '.$row['label']) ?>">←</button><button type="button" data-card-move="down" aria-label="<?= $e('Przesuń później: '.$row['label']) ?>">→</button></div></div>
            <?php endforeach; ?>
        </div></fieldset>
        </div><footer class="card-studio-save"><div><p data-card-save-state role="status">Zapisz szkic lub opublikuj kartę. Pobranie PNG nie publikuje szkicu.</p><small>PNG zachowuje stan z chwili pobrania. Link prowadzi do opublikowanej wersji. Saldo, przedmioty i pozostałe dane pozostają prywatne.</small></div><div><button class="card-save-draft" type="submit" name="action" value="save">Zapisz szkic</button><button class="card-publish" type="submit" name="action" value="publish">Opublikuj kartę</button><?php if ($editor['published']): ?><button class="card-unpublish" type="submit" name="action" value="unpublish">Wycofaj publikację</button><?php endif; ?></div></footer>
    </form>
</section>

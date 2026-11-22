<?php
/** @var array $model @var callable $escape */
$task = $model['task'] ?? null;
$public = $model['public'] ?? false;
$url = static fn (string $path, array $query = []): string => 'index.php?' . http_build_query(['route' => $path] + $query);
$statuses = $model['statuses'];
$scopes = ['private' => 'Tylko moje', 'members' => 'Dla zalogowanych', 'public' => 'Publiczny plan'];
$select = static function (string $name, string $label, array $options, mixed $value) use ($escape): void {
    echo '<label>' . $escape($label) . '<select name="' . $escape($name) . '">';
    foreach ($options as $key => $text) echo '<option value="' . $escape($key) . '"' . ((string)$value === (string)$key ? ' selected' : '') . '>' . $escape($text) . '</option>';
    echo '</select></label>';
};
$token = static function () use ($model, $escape): void { echo '<input type="hidden" name="_token" value="' . $escape($model['token'] ?? '') . '">'; };
?>
<section class="task-workspace<?= $public ? ' task-workspace--public' : ' task-workspace--admin' ?>" aria-label="Zadania i plany">
<?php if (!empty($model['message'])): ?><p role="status" class="task-notice"><?= $escape($model['message']) ?></p><?php endif; ?>
<?php if (!$public): ?>
<div class="task-toolbar">
<nav class="task-tabs" aria-label="Zakres zadań">
<?php foreach (['mine' => 'Moje zadania', 'members' => 'Wspólne', 'public' => 'Plan rozwoju'] + (!empty($model['owner']) ? ['oversight' => 'Podgląd Ownera'] : []) as $key => $label): ?>
<a href="<?= $escape($url('/account/tasks', ['scope' => $key])) ?>"<?= ($model['scope'] ?? '') === $key ? ' aria-current="page"' : '' ?>><?= $escape($label) ?></a>
<?php endforeach; ?>
</nav>
<div class="task-toolbar-actions"><a class="task-button task-button--primary" href="<?= $escape($url('/account/tasks/new')) ?>"><span aria-hidden="true">+</span> Nowy pomysł lub zadanie</a>
<?php if (!empty($model['can_discord_settings'])): ?><a class="task-button task-button--quiet" href="<?= $escape($url('/account/tasks/discord')) ?>">Ustawienia Discord</a><?php endif; ?>
</div>
</div>
<?php endif; ?>
<?php if (($model['mode'] ?? 'list') === 'discord'): ?>
<section class="task-card"><h2>Powiadomienia Discord dla testerów</h2>
<p>Webhook kanału Discord: <?= !empty($model['discord_settings']['configured']) ? 'skonfigurowany' : 'brak konfiguracji' ?>. Powiadomienia zawierają numer i link do zadania oraz wzmianki osób z połączonym kontem Discord.</p>
<form class="task-editor" method="post" action="<?= $escape($url('/account/tasks/discord')) ?>"><?php $token(); ?>
<label>Nowy adres webhooka (puste pole zachowuje zapisany)<input type="password" name="webhook" autocomplete="new-password" placeholder="https://discord.com/api/webhooks/…"></label>
<label class="task-check-option"><input type="checkbox" name="enabled" value="1"<?= !empty($model['discord_settings']['enabled']) ? ' checked' : '' ?>>Włącz powiadomienia Discord</label>
<label class="task-check-option"><input type="checkbox" name="clear_webhook" value="1">Usuń zapisany webhook</label>
<button type="submit">Zapisz ustawienia Discord</button></form>
</section>
<?php elseif (($model['mode'] ?? 'list') === 'list'): ?>
<form class="task-filters" method="get" action="index.php">
<input type="hidden" name="route" value="<?= $public ? '/roadmap' : '/account/tasks' ?>">
<?php if (!$public): ?><input type="hidden" name="scope" value="<?= $escape($model['scope']) ?>"><?php endif; ?>
<label>Szukaj<input type="search" placeholder="Szukaj zadania…" name="q" maxlength="120" value="<?= $escape($model['q'] ?? '') ?>"></label>
<?php $select('project', 'Projekt', ['' => 'Wszystkie projekty'] + $model['projects'], $model['project'] ?? ''); $select('status', 'Status', ['' => 'Wszystkie statusy'] + $statuses, $model['status'] ?? ''); ?>
<button type="submit">Filtruj</button>
</form>
<div class="task-results-heading"><h2><?= $public ? 'Plany rozwoju' : 'Lista zadań' ?></h2><span aria-label="Liczba wyników"><?= (int)$model['total'] ?></span></div>
<div class="task-cards">
<?php foreach ($model['tasks'] as $item): ?>
<article class="task-card task-card--item">
<div class="task-card-meta"><span class="task-state task-state-<?= $escape($item['status']) ?>"><?= $escape($statuses[$item['status']] ?? $item['status']) ?></span><span class="task-project"><?= $escape($item['project_name'] ?? 'Bez projektu') ?></span></div>
<h2><?php if (!$public): ?><a href="<?= $escape($url('/account/tasks/view', ['id' => $item['id']])) ?>"><?= $escape($item['title']) ?></a><?php else: ?><?= $escape($item['public_title']) ?><?php endif; ?></h2>
<?php if ($public): ?><p class="task-prose"><?= $escape($item['public_summary']) ?></p><a href="<?= $escape('/projects/' . rawurlencode($item['project_slug'])) ?>">Zobacz projekt</a>
<?php else: ?>
<div class="task-card-people"><p><span>Autor</span><?= $escape($item['author_name']) ?></p><?php if ($item['assignee_name']): ?><p><span>Przypisani</span><?= $escape($item['assignee_name']) ?></p><?php endif; ?></div>
<div class="task-card-footer"><span class="task-visibility"><?= $escape($scopes[$item['visibility']]) ?></span><span class="task-number">#<?= (int)$item['id'] ?></span></div>
<?php $done = count(array_filter($item['checklist'], static fn ($step) => $step['done'])); $count = count($item['checklist']); ?>
<?php if ($count): ?><label class="task-progress"><span><span>Postęp</span><strong><?= $done ?> / <?= $count ?> kroków</strong></span><progress max="<?= $count ?>" value="<?= $done ?>"><?= $done ?>/<?= $count ?></progress></label><?php endif; ?>
<?php endif; ?>
</article>
<?php endforeach; ?>
<?php if ($model['tasks'] === []): ?><p class="task-empty">Nie ma jeszcze zadań w tym widoku.</p><?php endif; ?>
</div>
<nav class="task-pagination" aria-label="Strony wyników">
<?php for ($page = max(1, $model['page'] - 2); $page <= min($model['pages'], $model['page'] + 2); $page++): ?>
<a href="<?= $escape($url($public ? '/roadmap' : '/account/tasks', ['scope' => $model['scope'] ?? '', 'q' => $model['q'] ?? '', 'project' => $model['project'] ?? '', 'status' => $model['status'] ?? '', 'page' => $page])) ?>"<?= $page === $model['page'] ? ' aria-current="page"' : '' ?>><?= $page ?></a>
<?php endfor; ?>
</nav>
<?php else: ?>
<?php if ($task && (empty($model['can_edit']) || empty($model['editing']))): ?>
<article class="task-card"><h2><?= $escape($task['title']) ?></h2><p class="task-prose"><?= $escape($task['description']) ?></p>
<p><?= $escape($statuses[$task['status']]) ?> · <?= $escape($scopes[$task['visibility']]) ?></p>
<?php if (!empty($task['assignee_names'])): ?><p>Przypisane osoby: <?= $escape(implode(', ', $task['assignee_names'])) ?></p><?php endif; ?>
<?php if (!empty($model['can_edit'])): ?><p><a href="<?= $escape($url('/account/tasks/edit', ['id' => $task['id']])) ?>">Edytuj opis i ustawienia</a></p><?php endif; ?>
<?php if ($task['auto_close'] !== 'manual'): ?><p class="task-muted">Zamknie się automatycznie: <?= $task['auto_close'] === 'checklist' ? 'po ukończeniu wszystkich kroków' : 'po publikacji wersji ' . $escape($task['release_version']) . ' (' . $escape($task['release_channel']) . ')' ?>.</p><?php endif; ?>
<?php if (!empty($model['can_progress'])): ?><form method="post" action="<?= $escape($url('/account/tasks/progress')) ?>"><?php $token(); ?><input type="hidden" name="id" value="<?= (int)$task['id'] ?>"><input type="hidden" name="revision" value="<?= (int)$task['revision'] ?>"><?php $select('status', 'Status', $statuses, $task['status']); ?><?php endif; ?>
<ul class="task-checklist"><?php foreach ($task['checklist'] as $index => $step): ?><li><label><input type="checkbox" name="done[]" value="<?= $index ?>"<?= $step['done'] ? ' checked' : '' ?><?= empty($model['can_progress']) ? ' disabled' : '' ?>><?= $escape($step['text']) ?></label></li><?php endforeach; ?></ul>
<?php if (!empty($model['can_progress'])): ?><button type="submit">Zapisz postęp</button></form><?php endif; ?>
</article>
<?php else: ?>
<form class="task-editor task-card" method="post" action="<?= $escape($url('/account/tasks/save')) ?>">
<?php $token(); ?><input type="hidden" name="id" value="<?= (int)($task['id'] ?? 0) ?>"><input type="hidden" name="revision" value="<?= (int)($task['revision'] ?? 0) ?>">
<label>Nazwa pomysłu lub zadania<input name="title" required maxlength="180" value="<?= $escape($task['title'] ?? '') ?>"></label>
<label>Opis i notatki<textarea name="description" rows="5" maxlength="20000"><?= $escape($task['description'] ?? '') ?></textarea></label>
<div class="task-fields"><?php $select('status', 'Status', $statuses, $task['status'] ?? 'idea'); $select('visibility', 'Widoczność', !empty($model['can_publish']) ? $scopes : array_diff_key($scopes, ['public' => true]), $task['visibility'] ?? 'private'); $select('project_id', 'Projekt', ['' => 'Bez projektu'] + $model['projects'], $task['project_id'] ?? '');  ?></div>
<fieldset><legend>Przypisane osoby (do 20, wspólne zadania)</legend><div class="task-assignees">
<?php foreach ($model['users'] as $userId => $name): ?><label class="task-check-option"><input type="checkbox" name="assignee_ids[]" value="<?= (int)$userId ?>"<?= in_array((int)$userId, $task['assignee_ids'] ?? [], true) ? ' checked' : '' ?>><?= $escape($name) ?></label><?php endforeach; ?>
</div></fieldset>
<?php if (!empty($model['discord_ready'])): ?><label class="task-check-option"><input type="checkbox" name="notify_assignees" value="1">Powiadom przypisane osoby na Discordzie po zapisaniu wspólnego zadania</label><?php endif; ?>
<label>Lista kroków — jeden na linię, [x] oznacza ukończony krok<textarea name="checklist" rows="6" maxlength="54000" placeholder="[ ] Opisz pomysł&#10;[ ] Przygotuj zmianę&#10;[x] Sprawdź wymagania"><?= $escape(implode("\n", array_map(static fn ($step) => ($step['done'] ? '[x] ' : '[ ] ') . $step['text'], $task['checklist'] ?? []))) ?></textarea></label>
<div class="task-fields"><?php $select('auto_close', 'Automatyczne zamknięcie', ['manual' => 'Wyłączone', 'checklist' => 'Wszystkie kroki ukończone', 'release' => 'Wskazana wersja opublikowana w Build Explorer v2'], $task['auto_close'] ?? 'manual'); $select('release_channel', 'Kanał wydania', ['release' => 'Release', 'snapshot' => 'Snapshot', 'dev' => 'Dev', 'wip' => 'WIP'], $task['release_channel'] ?? 'release'); ?></div>
<label>Docelowa wersja (dokładna nazwa)<input name="release_version" maxlength="120" value="<?= $escape($task['release_version'] ?? '') ?>"></label>
<p class="task-muted">Pusta checklista nigdy nie zamyka zadania. Reguła wydania wymaga projektu, dokładnej wersji, kanału i opublikowanego artefaktu. Ręczne ponowne otwarcie wymaga wyłączenia spełnionej reguły.</p>
<?php $select('wiki_page_id', 'Dokumentacja (wybierz zgodną z projektem)', ['' => 'Brak powiązania'] + $model['wiki'], $task['wiki_page_id'] ?? ''); ?>
<?php if (!empty($model['can_publish'])): ?><fieldset><legend>Komunikat publiczny</legend><p>Na roadmapę trafią wyłącznie poniższe pola i status. Opis wewnętrzny, kroki, komentarze i załączniki pozostaną dla zalogowanych.</p><label>Tytuł publiczny<input name="public_title" maxlength="180" value="<?= $escape($task['public_title'] ?? '') ?>"></label><label>Publiczny opis planu<textarea name="public_summary" rows="4" maxlength="4000"><?= $escape($task['public_summary'] ?? '') ?></textarea></label></fieldset><?php endif; ?>
<button type="submit">Zapisz zadanie</button>
</form>
<?php endif; ?>
<?php if ($task): ?>
<?php if (!empty($model['can_notify'])): ?>
<section class="task-card"><h2>Powiadomienie dla testerów</h2>
<?php if (!empty($model['notification'])): ?><p>Status: <?= $escape(['pending' => 'Oczekuje na wysłanie', 'sent' => 'Wysłano', 'failed' => 'Błąd wysłania — można ponowić', 'skipped' => 'Pominięto nieaktualną rewizję'][$model['notification']['status']] ?? '') ?></p><?php endif; ?>
<?php if (!empty($model['discord_ready'])): ?><form method="post" action="<?= $escape($url('/account/tasks/notify')) ?>"><?php $token(); ?><input type="hidden" name="id" value="<?= (int)$task['id'] ?>"><input type="hidden" name="revision" value="<?= (int)$task['revision'] ?>"><button type="submit">Powiadom testerów na Discordzie</button></form>
<?php else: ?><p>Powiadomienia Discord są wyłączone. Owner lub Administrator może skonfigurować webhook w ustawieniach Discord.</p><?php endif; ?>
</section>
<?php endif; ?>
<?php if (!$public && !empty($model['prompt'])): ?>
<details class="task-card task-prompt" data-task-prompt>
<summary>Utwórz prompt</summary>
<div class="task-editor">
<p>Gotowy komunikat do wklejenia do czatu AI/SI, na podstawie zapisanego zadania.</p>
<label for="task-prompt-text">Treść promptu<textarea id="task-prompt-text" rows="16" readonly data-task-prompt-text><?= $escape($model['prompt']) ?></textarea></label>
<button type="button" data-task-prompt-copy>Kopiuj prompt</button>
<p class="task-muted" role="status" aria-live="polite" data-task-prompt-status></p>
</div>
</details>
<?php endif; ?>
<aside class="task-card"><h2>Powiązania</h2><p><?= $escape($model['project_name'] ?? 'Bez projektu') ?></p><?php foreach ($model['links'] ?? [] as $link): ?><a class="task-link" href="<?= $escape($link['url']) ?>"><?= $escape($link['label']) ?></a><?php endforeach; ?></aside>
<section class="task-card"><h2>Screenshoty i grafiki</h2><div class="task-images"><?php foreach ($model['attachments'] as $attachment): ?><a href="<?= $escape($attachment['url']) ?>"><img loading="lazy" src="<?= $escape($attachment['url']) ?>" alt="<?= $escape($attachment['name']) ?>"></a><?php endforeach; ?></div>
<?php if (!empty($model['can_comment'])): ?>
<form class="task-editor" method="post" enctype="multipart/form-data" action="<?= $escape($url('/account/tasks/attach')) ?>"><?php $token(); ?><input type="hidden" name="id" value="<?= (int)$task['id'] ?>"><label>Dodaj chroniony screenshot (PNG, JPEG, WebP, do 4 MiB)<input type="file" name="screenshot" accept="image/png,image/jpeg,image/webp" required></label><button type="submit">Dodaj screenshot</button></form>
<?php if (!empty($model['media'])): ?><form class="task-editor" method="post" action="<?= $escape($url('/account/tasks/media')) ?>"><?php $token(); ?><input type="hidden" name="id" value="<?= (int)$task['id'] ?>"><?php $select('media_id', 'Grafika z Biblioteki (plik jest już publiczny)', ['' => 'Wybierz grafikę'] + $model['media'], ''); ?><button type="submit">Powiąż grafikę</button></form><?php endif; ?>
<?php endif; ?></section>
<section class="task-card"><h2>Komentarze</h2>
<?php foreach ($model['comments'] as $comment): ?><article class="task-comment"><p class="task-muted"><?= $escape($comment['author_name']) ?> · <time><?= $escape($comment['created_at']) ?> UTC</time></p><p class="task-prose"><?= $escape($comment['body']) ?></p></article><?php endforeach; ?>
<?php if (!empty($model['can_comment'])): ?><form class="task-editor" method="post" action="<?= $escape($url('/account/tasks/comment')) ?>"><?php $token(); ?><input type="hidden" name="id" value="<?= (int)$task['id'] ?>"><label>Komentarz<textarea name="body" rows="3" maxlength="5000" required></textarea></label><button type="submit">Dodaj komentarz</button></form><?php endif; ?>
</section>
<details class="task-card"><summary>Historia zadania</summary><ul><?php foreach ($model['history'] as $event): ?><li><?= $escape($event['created_at']) ?> UTC · <?= $escape($event['event']) ?></li><?php endforeach; ?></ul></details>
<?php endif; ?>
<?php endif; ?>
</section>

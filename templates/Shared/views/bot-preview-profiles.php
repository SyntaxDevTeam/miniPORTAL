<?php declare(strict_types=1);$profiles=$model['preview']['profiles']; ?>
<div data-bot-preview-profiles data-bot-preview-values="<?= $escape(json_encode($model['preview']['values'],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)) ?>">
<?php if(count($profiles)>1): ?><label class="bot-preview-choice">Podgląd wiadomości<select data-bot-preview-choice><?php foreach($profiles as $profile): ?><option value="<?= $escape($profile['id']) ?>"><?= $escape($profile['label']) ?></option><?php endforeach ?></select></label><?php endif ?>
<?php foreach($profiles as $index=>$profile): ?>
<div data-bot-profile="<?= $escape($profile['id']) ?>"<?= $index>0?' hidden':'' ?>>
<div class="bot-preview-meta" data-bot-profile-disabled="<?= $escape($profile['enabled']) ?>" hidden>Wysyłka tej wiadomości jest wyłączona. Podgląd pokazuje zapisaną konfigurację.</div>
<?php if($profile['topic']!==''): ?><div class="bot-preview-meta">Opis kanału: <span data-bot-source="<?= $escape($profile['topic']) ?>"></span></div><?php endif ?>
<div class="bot-discord"><div class="bot-discord-author"><span aria-hidden="true">S</span><strong>SyntaxDevBot</strong><small>BOT</small></div>
<?php if($profile['content']!==''): ?><div class="bot-markdown bot-preview-content" data-bot-source="<?= $escape($profile['content']) ?>" data-bot-render="content"<?= isset($profile['content_if'])?' data-bot-show-if="'.$escape($profile['content_if']).'"':'' ?>></div><?php endif ?>
<?php if($profile['title']!==''||isset($profile['body_literal'])): ?><div class="bot-embed" data-bot-color="<?= $escape($profile['color']) ?>" data-bot-fixed-color="<?= $escape($profile['fixed_color']) ?>">
<?php if($profile['thumbnail']): ?><div class="bot-preview-avatar" title="Przykładowy avatar użytkownika" aria-label="Przykładowy avatar użytkownika">👤</div><?php endif ?>
<strong data-bot-source="<?= $escape($profile['title']) ?>"<?= $index===0?' data-bot-preview="title"':'' ?>></strong>
<div class="bot-preview-body bot-markdown" data-bot-source="<?= $escape($profile['body']) ?>" data-bot-render="description"<?= $index===0?' data-bot-preview="body"':'' ?><?= $profile['notification']?' data-bot-notification-body data-bot-default-template="'.$escape($model['preview']['notification_default']).'"':'' ?>><?= $escape($profile['body_literal']??'') ?></div>
<?php foreach($profile['fields'] as $field): ?><div class="bot-preview-field"><strong<?= empty($field['literal'])?' data-bot-source="'.$escape($field['title']).'"':'' ?>><?= !empty($field['literal'])?$escape($field['title']):'' ?></strong><div class="bot-markdown"<?= empty($field['literal'])?' data-bot-source="'.$escape($field['body']).'" data-bot-render="field"':'' ?>><?= !empty($field['literal'])?$escape($field['body']):'' ?></div></div><?php endforeach ?>
<div class="bot-embed-footer" data-bot-source="<?= $escape($profile['footer']) ?>"<?= $profile['timestamp']?' data-bot-timestamp':'' ?>><?= $profile['timestamp']?'Przykładowy czas':'' ?></div></div><?php endif ?>
<?php if($profile['menu']): ?><div class="bot-preview-menu"><strong data-bot-source="message:ticket.select.placeholder"></strong><ul data-bot-preview-topics></ul></div><?php endif ?>
<div class="bot-preview-buttons"><?php foreach($profile['buttons'] as $button): ?><span class="is-<?= $escape($button['style']) ?>" data-bot-source="<?= $escape($button['key']) ?>" data-bot-preview="<?= $escape($button['legacy']) ?>"<?= isset($button['if'])?' data-bot-show-if="'.$escape($button['if']).'"':'' ?>></span><?php endforeach ?></div>
<div class="bot-preview-reactions"<?= $profile['notification']?' data-bot-notification-reactions':'' ?>><?php foreach($profile['reactions'] as $emoji): ?><span><?= $escape($emoji) ?></span><?php endforeach ?></div>
<?php if($profile['thread']!==''): ?><div class="bot-preview-meta">Wątek: <strong data-bot-source="<?= $escape($profile['thread']) ?>"></strong><div>Przykładowa propozycja użytkownika.</div></div><?php endif ?>
</div></div><?php endforeach ?></div>

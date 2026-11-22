<?php declare(strict_types=1); ?>
<section class="text-workspace" data-text-workspace aria-label="Translation workspace">
  <div class="text-workspace-toolbar">
    <label>Find a message<input class="form-control" type="search" data-workspace-search placeholder="Search keys, source or translation"></label>
    <label>Show<select class="form-select" data-workspace-filter><option value="all">All messages</option><option value="missing">Untranslated</option><option value="complete">Completed</option><option value="issues">Quality issues</option></select></label>
    <button class="btn btn-outline-light" type="button" data-next-missing>Next untranslated</button>
    <output data-workspace-progress aria-live="polite"></output>
  </div>
  <progress data-workspace-meter max="<?= count($items) ?>" value="0" aria-label="Translation completion"></progress>
  <p class="text-workspace-help">Translate the text. Keep placeholders, tags and colour codes. Use “Use original” when the text should stay the same. Empty fields keep their original value in the download.</p>
  <div class="text-token-legend" aria-label="Token colour legend">
    <span><span class="text-token text-token--placeholder">%player% / {count} / &lt;player&gt;</span> Placeholders</span>
    <span><span class="text-token text-token--minimessage">&lt;red&gt;…&lt;/red&gt;</span> MiniMessage formatting</span>
    <span><span class="text-token text-token--legacy">&amp;a / &amp;#aabbcc</span> Legacy colour codes</span>
  </div>
  <div class="translation-editor">
    <div class="translation-editor-head"><span>Original</span><span>Your translation</span></div>
    <?php foreach ($items as $index => $item):
        $tokens = $item['tokens'] ?? array_map(static fn (string $text): array => ['text' => $text, 'kind' => 'placeholder'], array_values(array_unique($item['protected'])));
    ?>
    <div class="translation-row" data-workspace-row data-protected="<?= $escape(json_encode($item['protected'], JSON_THROW_ON_ERROR)) ?>">
      <div class="translation-original"><small data-message-key><?= $escape($item['label']) ?></small><p><code class="translation-source-code" data-message-source><?= $highlight($item['value'], $tokens) ?></code></p>
        <div class="translation-token-actions">
        <button class="btn btn-sm btn-outline-light" type="button" data-copy-source>Use original</button>
        <?php foreach ($tokens as $token): ?><button class="btn btn-sm text-token text-token--<?= $escape($token['kind']) ?>" type="button" data-insert-token="<?= $escape($token['text']) ?>" aria-label="Insert <?= $escape($token['text']) ?>"><?= $escape($token['text']) ?></button> <?php endforeach; ?>
        </div>
      </div>
      <label class="translation-input"><span class="visually-hidden"><?= $escape($item['label']) ?></span>
        <textarea class="form-control" name="translations[<?= $escape($item['token']) ?>]" rows="3" spellcheck="true" aria-describedby="message-quality-<?= $index ?>"><?= $escape($values[$item['token']] ?? '') ?></textarea>
        <small id="message-quality-<?= $index ?>" data-message-quality></small>
      </label>
    </div>
    <?php endforeach; ?>
  </div>
  <p data-workspace-empty hidden>No messages match your filters.</p>
</section>

<?php
declare(strict_types=1);

/** @var array<string, array{string, string}> $states */
$escape = static fn(string $text): string => htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
?>
<details class="small mt-3">
    <summary>Legenda stanów</summary>
    <ul class="mt-2 mb-0 ps-3">
        <?php foreach ($states as [$label, $description]): ?>
            <li class="mb-1"><strong><?= $escape($label) ?></strong> — <?= $escape($description) ?></li>
        <?php endforeach; ?>
    </ul>
</details>

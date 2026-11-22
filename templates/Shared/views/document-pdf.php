<?php declare(strict_types=1); ?>
<!doctype html>
<html lang="pl">
<head><meta charset="utf-8"><title><?= $escape($article->title) ?></title><style><?= $css ?></style></head>
<body>
<header>
    <p class="meta"><?= $escape($article->categoryName) ?> · <?= $escape($date) ?></p>
    <h1><?= $escape($article->title) ?></h1>
    <p class="summary"><?= $escape($article->summary) ?></p>
    <p class="source">Źródło: <a href="<?= $escape($url) ?>"><?= $escape($url) ?></a></p>
</header>
<main><?= $safe ?></main>
</body>
</html>

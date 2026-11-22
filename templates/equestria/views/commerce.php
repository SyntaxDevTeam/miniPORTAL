<?php
declare(strict_types=1);
/** @var array $view Plain display model supplied through CommerceThemeInterface. */
$e=fn(mixed $value):string=>$this->escape((string)$value);
$url=static function(string $value):string {
    return str_starts_with($value,'/')&&!str_starts_with($value,'//')&&!preg_match('/[\x00-\x20\\\\]/',$value)?$value:'/itemshop';
};
$variant=static fn(string $value):string=>in_array($value,['rank','bundle','item','key'],true)?$value:'item';
$tone=static fn(string $value):string=>in_array($value,['bronze','silver','gold','neon'],true)?$value:'default';
$icon=fn(string $type)=>$this->renderCommerceSymbol($type);
$kind=$view['kind'];$catalog=$kind==='catalog';
?>
<div class="commerce commerce-<?= $e($kind) ?>">
    <div class="commerce-topline">
        <a class="commerce-brand" href="/itemshop"><span aria-hidden="true">✦</span> MoonVale <span class="commerce-brand-divider">/</span> <span>Itemshop</span></a>
        <nav aria-label="Nawigacja sklepu"><?php foreach($view['navigation'] as $link): ?><a href="<?= $e($url($link['url'])) ?>"<?= $link['active']?' aria-current="page"':'' ?>><?= $e($link['label']) ?></a><?php endforeach ?></nav>
    </div>
    <?php if($catalog): ?>
    <header class="commerce-hero">
        <div class="commerce-hero-copy"><p class="commerce-kicker">Drobne dodatki. Twoja historia.</p><h1>Nadaj przygodzie<br><span>swój charakter.</span></h1><p><?= $e($view['lead']) ?></p><a class="commerce-button" href="#oferta">Odkryj ofertę <span aria-hidden="true">↗</span></a></div>
        <div class="commerce-hero-art" aria-hidden="true"><span class="commerce-art-caption">Twoje miejsce pod księżycem</span></div>
        <div class="commerce-hero-bottom"><span><span aria-hidden="true">✧</span> Dla Ciebie lub na prezent</span><?php if($view['account']!==null): ?><a href="<?= $e($url($view['account']['url'])) ?>">Twoja postać <strong><?= $e($view['account']['name']) ?></strong><span aria-hidden="true">↗</span></a><?php else: ?><span>Świat MoonVale.pl</span><?php endif ?></div>
    </header>
    <?php else: ?>
    <nav class="commerce-breadcrumb" aria-label="Okruszki"><?php foreach($view['breadcrumbs'] as $crumbIndex=>$crumb): ?><?php if(isset($crumb['href'])): ?><a href="<?= $e($url($crumb['href'])) ?>"><?= $e($crumb['label']) ?></a><?php if($crumbIndex<count($view['breadcrumbs'])-1): ?><span aria-hidden="true">/</span><?php endif ?><?php else: ?><span aria-current="page"><?= $e($crumb['label']) ?></span><?php endif ?><?php endforeach ?></nav>
    <header class="commerce-page-heading"><p class="commerce-kicker">Twoja przygoda na MoonVale</p><h1><?= $e($view['title']) ?></h1><p><?= $e($view['lead']) ?></p></header>
    <?php if(in_array($kind,['product','checkout','order'],true)): $step=array_search($kind,['product','checkout','order'],true); ?>
    <ol class="commerce-steps" aria-label="Etapy zamówienia"><?php foreach(['Wybór produktu','Podsumowanie','Zamówienie'] as $index=>$label): ?><li class="<?= $index<=$step?'is-current':'' ?>"<?= $index===$step?' aria-current="step"':'' ?>><span><?= $index+1 ?></span><?= $e($label) ?></li><?php endforeach ?></ol>
    <?php endif ?>
    <?php endif ?>
    <p class="commerce-notice"><span class="commerce-info" aria-hidden="true">i</span><?= $e($view['notice']) ?></p>
    <?php if($catalog): ?>
    <section class="commerce-offer" id="oferta" aria-labelledby="commerce-offer-heading">
        <header class="commerce-offer-heading"><div><p class="commerce-kicker">Co wybierzesz dla siebie?</p><h2 id="commerce-offer-heading">Małe rzeczy, wielkie przygody.</h2></div><span><?php $count=count($view['products']);echo $count.' '.($count===1?'produkt':($count%10>=2&&$count%10<=4&&($count%100<12||$count%100>14)?'produkty':'produktów')); ?></span></header>
        <div class="commerce-guide" aria-label="Jak kupić w sklepie MoonVale">
            <ol>
                <li><span aria-hidden="true">01</span><div><h3>Połącz konto gracza</h3><p>Zaloguj się, <a href="/account/player-profile">połącz profil Minecraft</a> i wybierz produkt.</p></div></li>
                <li><span aria-hidden="true">02</span><div><h3>Wybierz odbiorcę i <?= ($view['live_payments']??false)?'zapłać':'potwierdź' ?></h3><p>Dla siebie lub na nick innej osoby. <?= ($view['live_payments']??false)?'Sprawdź kwotę i opłać zamówienie przez Game-host.':'Potwierdź zakup testowy — bez pobierania pieniędzy.' ?></p></div></li>
                <li><span aria-hidden="true">03</span><div><h3><?= !empty($view['test_mode'])?'Sprawdź wiadomość w grze':'Odbierz w grze' ?></h3><p><?= ($view['live_payments']??false)?'Po wpłacie wejdź do gry; przy prezencie także odbiorca.':(!empty($view['test_mode'])?'Tylko kupujący otrzyma skonfigurowaną wiadomość. Produkt nie zostanie przyznany.':'Realizację testu zatwierdza administrator.') ?> Status sprawdzisz w <a href="/itemshop/orders">Moich zakupach</a>.</p></div></li>
            </ol>
        </div>
        <?php if($view['enabled']): ?><nav class="commerce-categories" aria-label="Kategorie produktów"><?php foreach($view['categories'] as $category): ?><a href="<?= $e($url($category['url'])) ?>"<?= $category['active']?' aria-current="page"':'' ?><?= $category['child']?' class="commerce-subcategory"':'' ?>><?= $category['child']?'<span aria-hidden="true">↳</span> ':'' ?><?= $e($category['name']) ?><span class="commerce-count"><?= (int)$category['count'] ?></span></a><?php endforeach ?></nav><?php endif ?>
        <?php if($view['products']===[]): ?>
        <div class="commerce-empty"><div class="commerce-empty-art" aria-hidden="true"><?php $icon('item') ?></div><p class="commerce-kicker">Jeszcze chwila…</p><h3><?= $view['enabled']?'Tu zacznie się coś dobrego.':'Przygotowujemy coś dla Ciebie.' ?></h3><p><?= $e($view['empty']) ?></p><?php if($view['active_category']!==0): ?><a class="commerce-text-link" href="/itemshop">Zobacz wszystkie produkty <span aria-hidden="true">↗</span></a><?php endif ?></div>
        <?php else: ?><div class="commerce-product-grid"><?php foreach($view['products'] as $product): ?>
        <article class="commerce-card commerce-variant-<?= $variant($product['variant']) ?> commerce-tone-<?= $tone($product['icon_tone']??'default') ?>"><a class="commerce-card-link" href="<?= $e($url($product['url'])) ?>" aria-labelledby="commerce-product-<?= (int)$product['id'] ?>">
            <div class="commerce-card-art"><span class="commerce-card-category"><?= $e($product['category']) ?></span><div class="commerce-item-symbol"><?php $icon($product['variant']) ?></div><span class="commerce-card-star" aria-hidden="true">✧</span></div>
            <div class="commerce-card-copy"><h3 id="commerce-product-<?= (int)$product['id'] ?>"><?= $e($product['name']) ?></h3><p><?= $e($product['description']) ?></p></div>
            <div class="commerce-card-footer"><strong><?= $e($product['price']) ?></strong><span>Zobacz ofertę <span aria-hidden="true">↗</span></span></div>
        </a></article><?php endforeach ?></div><?php endif ?>
    </section>
    <?php elseif($kind==='error'): ?>
    <section class="commerce-empty" aria-label="Dalsze kroki"><div class="commerce-empty-art"><?php $icon('item') ?></div><h2>Wróćmy na właściwą ścieżkę.</h2><a class="commerce-button" href="<?= $e($url($view['action']['url'])) ?>"><?= $e($view['action']['label']) ?> <span aria-hidden="true">↗</span></a></section>
    <?php elseif($kind==='history'): ?>
    <section class="commerce-history" aria-label="Historia zakupów"><?php if($view['orders']===[]): ?><div class="commerce-empty"><div class="commerce-empty-art"><?php $icon('bundle') ?></div><h2>Twoja historia dopiero się zaczyna.</h2><p>Tutaj znajdziesz swoje zamówienia i ich aktualny stan.</p><a class="commerce-button" href="/itemshop">Odkryj ofertę <span aria-hidden="true">↗</span></a></div><?php else: ?>
    <?php foreach($view['orders'] as $order): ?><article class="commerce-order-row"><div class="commerce-order-symbol"><?php $icon($order['gift']?'bundle':'item') ?></div><div><h2><?= $e($order['name']) ?> <span>× <?= (int)$order['quantity'] ?></span></h2><p><?= $e($order['nickname']) ?><?= $order['gift']?' · Prezent':'' ?> · <time><?= $e($order['date']) ?> UTC</time></p><details><summary>Numer zamówienia</summary><code><?= $e($order['id']) ?></code></details></div><div class="commerce-order-state"><strong><?= $e($order['price']) ?></strong><span><?= $e($order['status']) ?></span><?php if(isset($order['url'])): ?><a class="commerce-back" href="<?= $e($url($order['url'])) ?>">Wróć do płatności</a><?php endif ?></div></article><?php endforeach ?><?php endif ?></section>
    <?php else: $product=$view['product']; ?>
    <div class="commerce-detail-layout"><section class="commerce-product-detail commerce-variant-<?= $variant($product['variant']) ?> commerce-tone-<?= $tone($product['icon_tone']??'default') ?>" aria-label="Wybrany produkt"><div class="commerce-detail-art"><span class="commerce-card-category"><?= $e($product['category']) ?></span><div class="commerce-item-symbol"><?php $icon($product['variant']) ?></div></div><div class="commerce-detail-copy"><p class="commerce-kicker"><?= $e($product['category']) ?></p><h2><?= $e($product['name']) ?></h2><?php if($product['description']!==''): ?><p class="commerce-description"><?= $e($product['description']) ?></p><?php endif ?><div class="commerce-detail-price"><span><?= $kind==='product'?'Cena za sztukę':(($view['live_payments']??false)?'Wartość zamówienia':'Wartość testowa') ?></span><strong><?= $e($product['price']) ?></strong></div></div></section>
        <section class="commerce-purchase" aria-labelledby="commerce-purchase-heading"<?= isset($view['status_url']) ? ' data-commerce-status-url="'.$e($url($view['status_url'])).'"' : '' ?>><p class="commerce-kicker"><?= $kind==='product'?'Dla Twojej postaci':($kind==='checkout'?'Wszystko na swoim miejscu':'Status zamówienia') ?></p><h2 id="commerce-purchase-heading"><?= $kind==='product'?'Twój wybór.':($kind==='checkout'?'Sprawdź i potwierdź.':'Twoje zamówienie.') ?></h2>
            <?php if(isset($view['account'])): ?><a class="commerce-recipient" href="<?= $e($url($view['account']['url'])) ?>"><span aria-hidden="true">✧</span><span>Twoja postać<strong><?= $e($view['account']['name']) ?></strong></span><span aria-hidden="true">↗</span></a><?php endif ?>
            <?php if(isset($view['status'])): ?><p class="commerce-status" role="status" data-commerce-status><?= $e($view['status']) ?></p><?php endif ?>
            <?php if(isset($view['details'])): ?><dl class="commerce-summary"><?php foreach($view['details'] as $detail): ?><div><dt<?= ($detail['key']??null) === 'amount' ? ' data-commerce-amount-label' : '' ?>><?= $e($detail['label']) ?></dt><dd><?= $e($detail['value']) ?></dd></div><?php endforeach ?></dl><?php endif ?>
            <?php if(isset($view['message'])): ?><p class="commerce-purchase-help" data-commerce-status-detail<?= isset($view['promotion_form']) ? ' role="alert"' : '' ?>><?= $e($view['message']) ?></p><?php endif ?>
            <?php if(isset($view['promotion_form'])): $promo=$view['promotion_form']; ?><div class="commerce-form"><?php $this->render_form($url($promo['action']),$promo['fields'],$promo['submit'],$promo['csrf']) ?></div><?php endif ?>
            <?php if(isset($view['form'])): $form=$view['form']; ?><div class="commerce-form"><?php $this->render_form($url($form['action']),$form['fields'],$form['submit'],$form['csrf']) ?></div><?php endif ?>
            <?php if(isset($view['action'])): ?><a class="commerce-button" <?= $kind === 'order' ? 'data-commerce-payment ' : '' ?>href="<?= $e($url($view['action']['url'])) ?>"><?= $e($view['action']['label']) ?> <span aria-hidden="true">↗</span></a><?php endif ?>
            <?php if($kind==='order'): ?><a class="commerce-button" href="/itemshop/orders">Moje zakupy <span aria-hidden="true">↗</span></a><?php endif ?>
            <a class="commerce-back" href="/itemshop">← Wróć do oferty</a>
            <?php if(isset($view['status_url'])): ?>
                <p class="commerce-purchase-help" data-commerce-status-fallback hidden></p>
                <noscript><p class="commerce-purchase-help">Odśwież stronę, aby sprawdzić aktualny status zamówienia.</p></noscript>
            <?php endif ?>
        </section>
    </div>
    <?php endif ?>
    <?php if($catalog): ?>
    <section class="commerce-leaderboard" aria-labelledby="shop-top-buyers">
        <header><p class="commerce-kicker">Dziękujemy za wsparcie MoonVale</p><h2 id="shop-top-buyers">Top kupujących</h2><p>Suma potwierdzonych wpłat · cały okres · PLN</p></header>
        <?php if(($view['top_buyers']??[])===[]): ?><p class="commerce-leaderboard-empty">Ranking pojawi się po pierwszej potwierdzonej wpłacie.</p>
        <?php else: ?><ol><?php foreach($view['top_buyers'] as $buyer): ?><li><span class="commerce-leaderboard-rank" aria-label="Miejsce <?= $e($buyer['rank']) ?>"><?= $e($buyer['rank']) ?></span><span class="commerce-leaderboard-name"><?= $e($buyer['nickname']) ?></span><strong><?= $e($buyer['amount']) ?></strong></li><?php endforeach ?></ol><?php endif ?>
    </section>
    <?php endif ?>
    <footer class="commerce-footer"><span aria-hidden="true">✦</span><p>Twój świat. Twoja stajnia. Twoja historia.</p><a href="/account/player-profile">Przejdź do profilu <span aria-hidden="true">↗</span></a></footer>
</div>
<?php if(isset($view['status_url'])): ?>
<script defer src="/templates/shared/assets/js/commerce-order-status.js?v=1"></script>
<?php endif ?>

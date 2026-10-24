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
    <section class="commerce-how" aria-labelledby="commerce-how-heading"><div><p class="commerce-kicker">Po prostu, po Twojemu</p><h2 id="commerce-how-heading">Od wyboru do przygody.</h2></div><ol><li><span>01</span><div><h3>Znajdź swój dodatek</h3><p>Wybierz produkt i zobacz jego opis.</p></div></li><li><span>02</span><div><h3>Wybierz odbiorcę</h3><p>Dla swojej postaci lub na prezent.</p></div></li><li><span>03</span><div><h3>Sprawdź zamówienie</h3><p>Podsumowanie i stan znajdziesz w swoich zakupach.</p></div></li></ol></section>
    <?php elseif($kind==='error'): ?>
    <section class="commerce-empty" aria-label="Dalsze kroki"><div class="commerce-empty-art"><?php $icon('item') ?></div><h2>Wróćmy na właściwą ścieżkę.</h2><a class="commerce-button" href="<?= $e($url($view['action']['url'])) ?>"><?= $e($view['action']['label']) ?> <span aria-hidden="true">↗</span></a></section>
    <?php elseif($kind==='history'): ?>
    <section class="commerce-history" aria-label="Historia zakupów"><?php if($view['orders']===[]): ?><div class="commerce-empty"><div class="commerce-empty-art"><?php $icon('bundle') ?></div><h2>Twoja historia dopiero się zaczyna.</h2><p>Tutaj znajdziesz swoje zamówienia i ich aktualny stan.</p><a class="commerce-button" href="/itemshop">Odkryj ofertę <span aria-hidden="true">↗</span></a></div><?php else: ?>
    <?php foreach($view['orders'] as $order): ?><article class="commerce-order-row"><div class="commerce-order-symbol"><?php $icon($order['gift']?'bundle':'item') ?></div><div><h2><?= $e($order['name']) ?> <span>× <?= (int)$order['quantity'] ?></span></h2><p><?= $e($order['nickname']) ?><?= $order['gift']?' · Prezent':'' ?> · <time><?= $e($order['date']) ?> UTC</time></p><details><summary>Numer zamówienia</summary><code><?= $e($order['id']) ?></code></details></div><div class="commerce-order-state"><strong><?= $e($order['price']) ?></strong><span><?= $e($order['status']) ?></span></div></article><?php endforeach ?><?php endif ?></section>
    <?php else: $product=$view['product']; ?>
    <div class="commerce-detail-layout"><section class="commerce-product-detail commerce-variant-<?= $variant($product['variant']) ?> commerce-tone-<?= $tone($product['icon_tone']??'default') ?>" aria-label="Wybrany produkt"><div class="commerce-detail-art"><span class="commerce-card-category"><?= $e($product['category']) ?></span><div class="commerce-item-symbol"><?php $icon($product['variant']) ?></div></div><div class="commerce-detail-copy"><p class="commerce-kicker"><?= $e($product['category']) ?></p><h2><?= $e($product['name']) ?></h2><?php if($product['description']!==''): ?><p class="commerce-description"><?= $e($product['description']) ?></p><?php endif ?><div class="commerce-detail-price"><span><?= $kind==='product'?'Cena za sztukę':'Wartość testowa' ?></span><strong><?= $e($product['price']) ?></strong></div></div></section>
        <section class="commerce-purchase" aria-labelledby="commerce-purchase-heading"><p class="commerce-kicker"><?= $kind==='product'?'Dla Twojej postaci':($kind==='checkout'?'Wszystko na swoim miejscu':'Status zamówienia') ?></p><h2 id="commerce-purchase-heading"><?= $kind==='product'?'Twój wybór.':($kind==='checkout'?'Sprawdź i potwierdź.':'Twoje zamówienie.') ?></h2>
            <?php if(isset($view['account'])): ?><a class="commerce-recipient" href="<?= $e($url($view['account']['url'])) ?>"><span aria-hidden="true">✧</span><span>Twoja postać<strong><?= $e($view['account']['name']) ?></strong></span><span aria-hidden="true">↗</span></a><?php endif ?>
            <?php if(isset($view['status'])): ?><p class="commerce-status"><?= $e($view['status']) ?></p><?php endif ?>
            <?php if(isset($view['details'])): ?><dl class="commerce-summary"><?php foreach($view['details'] as $detail): ?><div><dt><?= $e($detail['label']) ?></dt><dd><?= $e($detail['value']) ?></dd></div><?php endforeach ?></dl><?php endif ?>
            <?php if(isset($view['message'])): ?><p class="commerce-purchase-help"><?= $e($view['message']) ?></p><?php endif ?>
            <?php if(isset($view['form'])): $form=$view['form']; ?><div class="commerce-form"><?php $this->render_form($url($form['action']),$form['fields'],$form['submit'],$form['csrf']) ?></div><?php endif ?>
            <?php if(isset($view['action'])): ?><a class="commerce-button" href="<?= $e($url($view['action']['url'])) ?>"><?= $e($view['action']['label']) ?> <span aria-hidden="true">↗</span></a><?php endif ?>
            <?php if($kind==='order'): ?><a class="commerce-button" href="/itemshop/orders">Moje zakupy <span aria-hidden="true">↗</span></a><?php endif ?>
            <a class="commerce-back" href="/itemshop">← Wróć do oferty</a>
        </section>
    </div>
    <?php endif ?>
    <footer class="commerce-footer"><span aria-hidden="true">✦</span><p>Twój świat. Twoja stajnia. Twoja historia.</p><a href="/account/player-profile">Przejdź do profilu <span aria-hidden="true">↗</span></a></footer>
</div>

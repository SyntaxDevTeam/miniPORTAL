<?php declare(strict_types=1); ?>
<section data-shop-message-preview class="mt-3" aria-labelledby="shop-message-preview-title">
    <h3 id="shop-message-preview-title" class="h6 mb-2">Podgląd czatu w grze</h3>
    <p class="small mb-2">Podgląd używa przykładowego nicku i aktualnej nazwy produktu. MiniMessage obsługuje kolory, gradient, tęczę i dekoracje tekstu.</p>
    <p class="small mb-2" data-shop-message-format></p>
    <div class="alert alert-warning" data-shop-message-format-warning role="status" hidden>
        Wybrano format klasyczny, ale prefiks lub wiadomość zawiera znaczniki MiniMessage. W tym formacie znaczniki takie jak &lt;b&gt; i &lt;green&gt; będą widoczne jako tekst, także w grze.
        <button type="button" class="btn btn-sm btn-outline-primary mt-2" data-shop-message-use-mini>Przełącz na MiniMessage</button>
        <span class="small d-block mt-2">Po przełączeniu zastąp też kody &amp;a, &amp;l itp. znacznikami MiniMessage i zapisz produkt.</span>
    </div>
    <div class="d-grid gap-2">
        <div class="bg-dark text-white border rounded p-2">
            <strong class="small d-block mb-1">Do kupującego</strong>
            <div data-shop-message-output="player">Brak wiadomości</div>
        </div>
        <div class="bg-dark text-white border rounded p-2">
            <strong class="small d-block mb-1">Do pozostałych graczy</strong>
            <div data-shop-message-output="broadcast">Brak ogłoszenia</div>
        </div>
    </div>
    <p class="small mt-2 mb-0" data-shop-message-feedback role="status"></p>
</section>
<script defer src="/templates/shared/assets/js/shop-message-preview.js?v=2"></script>

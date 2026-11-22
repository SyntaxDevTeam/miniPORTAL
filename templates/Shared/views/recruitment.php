<?php declare(strict_types=1); ?>
<div class="recruitment-experience" data-recruitment>
    <aside class="recruitment-guide" aria-label="Przewodnik rekrutacji">
        <span class="recruitment-eyebrow">TWÓJ KOLEJNY KROK</span>
        <h2>Zbudujmy dobry zespół.</h2>
        <p>Szukamy ludzi, którzy chcą pomagać. Pokaż nam swoje podejście, doświadczenie i pomysły.</p>
        <ol><li><strong>Wybierz swoją rolę</strong><span>Znajdź miejsce dla swoich umiejętności.</span></li><li><strong>Opowiedz o sobie</strong><span>Pisz szczerze i konkretnie.</span></li><li><strong>Poznajmy się</strong><span>Śledź podanie i odpowiedź personelu.</span></li></ol>
        <div class="recruitment-progress"><label for="recruitment-progress">Gotowość podania</label><progress id="recruitment-progress" max="100" value="0"></progress><span data-recruitment-progress aria-live="polite">Uzupełnij wymagane pola</span></div>
        <p class="recruitment-private">Prywatne zgłoszenie · widoczne wyłącznie dla Ciebie i uprawnionego personelu.</p>
    </aside>
    <div class="recruitment-main">
        <div class="recruitment-ranks" aria-label="Dostępne rangi">
            <?php foreach ($ranks as $rank): ?>
            <article><span aria-hidden="true">✦</span><h3><?= $e($rank['name']) ?></h3><p><?= $e($rank['description']) ?></p></article>
            <?php endforeach; ?>
        </div>
        <section class="recruitment-paper" aria-label="Formularz rekrutacyjny">
            <span class="recruitment-eyebrow">FORMULARZ REKRUTACYJNY</span>
            <h2>Twoje miejsce zaczyna się tutaj.</h2>
            <p>Wybierz rangę i odpowiedz na pytania. Przed wysłaniem sprawdź swoje odpowiedzi. Nie odświeżaj strony podczas pisania.</p>
            <?php $this->render_form('/recruitment', $fields, 'Wyślij podanie →', $token); ?>
        </section>
    </div>
</div>

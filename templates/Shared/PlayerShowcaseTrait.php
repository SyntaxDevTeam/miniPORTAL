<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Templates\Shared;

/** Shared presentation for the optional public graphics contract. */
trait PlayerShowcaseTrait
{
    private bool $playerShowcaseAssetsLoaded = false;

    public function render_player_showcase(array $card): void
    {
        if (($card['public'] ?? false) !== true) return;
        $e = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
        if (!$this->playerShowcaseAssetsLoaded) {
            $this->playerShowcaseAssetsLoaded = true;
            foreach ([['css','player-showcase.css'], ['js','player-model-renderer.js'], ['js','player-showcase.js']] as [$kind,$name]) {
                $path = dirname(__DIR__).'/shared/assets/'.$kind.'/'.$name;
                $url = '/templates/shared/assets/'.$kind.'/'.$name.'?v='.substr(hash_file('sha256', $path),0,16);
                echo $kind === 'css' ? '<link rel="stylesheet" href="'.$e($url).'">' : '<script src="'.$e($url).'" defer></script>';
            }
        }
        // Closed artwork catalogue belongs to presentation, never user-supplied URLs.
        $card['artworks']=[];
        foreach (['world'=>'equestria/assets/img/moonvale-world-v2.webp','forest'=>'shared/assets/img/player-card-forest-v1.webp','meadow'=>'shared/assets/img/player-card-meadow-v1.webp'] as $id=>$file) {
            $path=dirname(__DIR__).'/'.$file;
            if(is_file($path))$card['artworks'][$id]='/templates/'.$file.'?v='.substr(hash_file('sha256',$path),0,16);
        }
        $card['design']['backdrop']=match($card['design']['backdrop']){'stars'=>'forest','glow'=>'meadow',default=>$card['design']['backdrop']};
        $card['artwork_url']=$card['artworks'][$card['design']['backdrop']] ?? $card['artworks']['world'] ?? null;
        $card['design']['show_status']=false;$card['status']=null;
        if (($card['context'] ?? '') === 'profile') {
            $this->renderPlayerShowcaseProfile($card);
            return;
        }
        $json = json_encode($card, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        echo '<section class="player-showcase" data-player-showcase="'.$e($json).'" data-format="'.$e($card['design']['format']).'" aria-label="Graficzna karta gracza '.$e($card['name']).'">';
        if (($card['navigation'] ?? []) !== []) {
            echo '<nav class="player-showcase-navigation" aria-label="Nawigacja profilu">';
            foreach ($card['navigation'] as $link) if (is_string($link['url'] ?? null) && preg_match('~^/(?!/)[a-zA-Z0-9_/?=&%.:-]+$~D', $link['url'])) echo '<a href="'.$e($link['url']).'">'.$e($link['label']).'</a>';
            echo '</nav>';
        }
        [$width,$height] = match ($card['design']['format']) { 'portrait'=>[1080,1920], 'square'=>[1080,1080], default=>[1920,1080] };
        echo '<figure class="player-showcase-figure"><div class="player-showcase-stage"><canvas width="'.$width.'" height="'.$height.'" role="img" aria-label="Karta gracza '.$e($card['name']).'"></canvas></div></figure>';
        echo '<div class="player-showcase-actions"><label>Format obrazu<select data-showcase-format aria-label="Format eksportu PNG">';
        foreach (['landscape'=>'Poziomy · 1920 × 1080', 'square'=>'Kwadrat · 1080 × 1080', 'portrait'=>'Pionowy · 1080 × 1920'] as $id=>$label) echo '<option value="'.$id.'"'.($card['design']['format']===$id?' selected':'').'>'.$label.'</option>';
        echo '</select></label><button type="button" data-showcase-download disabled>Pobierz PNG</button><button type="button" data-showcase-copy>Kopiuj link</button><input data-showcase-url type="url" readonly hidden aria-label="Link do publicznego profilu"></div>';
        if (is_string($card['live_image_url'] ?? null) && preg_match('~^/(?!/)[a-zA-Z0-9_/.:-]+$~D',$card['live_image_url'])) {
            echo '<div class="player-showcase-actions player-live-image"><button type="button" data-showcase-live-copy>Kopiuj link do aktualnego PNG</button><a data-showcase-live-open href="'.$e($card['live_image_url']).'" target="_blank" rel="noopener">Otwórz aktualny PNG</a><input data-showcase-live-url type="url" readonly hidden aria-label="Adres aktualizowanego obrazu PNG"></div>';
        }
        echo '<section class="player-showcase-social" aria-label="Udostępnij kartę w aplikacji"><div class="player-showcase-social-buttons">';
        foreach (['instagram'=>'Instagram','facebook'=>'Facebook','tiktok'=>'TikTok','discord'=>'Discord','whatsapp'=>'WhatsApp','more'=>'Udostępnij…'] as $id=>$label) {
            if (in_array($id,['facebook','whatsapp'],true)) echo '<a data-showcase-social="'.$id.'" target="_blank" rel="noopener noreferrer" aria-label="'.$label.' — udostępnij link">'.$label.'</a>';
            else echo '<button type="button" data-showcase-social="'.$id.'" data-share-image disabled aria-label="'.$label.' — udostępnij przez urządzenie">'.$label.'</button>';
        }
        echo '</div></section>';
        echo '<p class="player-showcase-status" data-showcase-status role="status" aria-live="polite">Włącz JavaScript, aby zobaczyć podgląd i pobrać PNG.</p>';
        echo '</section>';
    }

    private function renderPlayerShowcaseProfile(array $card): void
    {
        $e = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
        $json = json_encode($card, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        $id = 'player-export-'.substr(hash('sha256',$card['profile_url']),0,12);
        echo '<div class="player-public-profile" data-backdrop="'.$e($card['design']['backdrop']).'" data-palette="'.$e($card['design']['palette']).'" data-player-profile-showcase="'.$e($json).'">';
        echo '<div class="player-profile-switch" role="tablist" aria-label="Widok profilu"><button type="button" id="'.$id.'-classic-tab" role="tab" data-profile-view="classic" aria-selected="true" aria-controls="'.$id.'-classic">Profil klasyczny</button><button type="button" id="'.$id.'-card-tab" role="tab" data-profile-view="card" aria-selected="false" tabindex="-1" aria-controls="'.$id.'-card">Karta gracza</button></div>';
        echo '<div id="'.$id.'-classic" role="tabpanel" aria-labelledby="'.$id.'-classic-tab" data-profile-classic>';
        // Reuse each theme's responsive profile, using only the already public projection.
        $profile = [
            'title'=>$card['name'], 'subtitle'=>$card['brand'], 'private'=>false, 'read_only'=>false,
            'status'=>'', 'show_status'=>false,
            'image_url'=>$card['image_url'], 'bio'=>$card['bio'], 'card'=>$card['design'],
            'metrics'=>$card['metrics'], 'statistics'=>$card['statistics'], 'updated_ms'=>$card['updated_ms'],
            'navigation'=>$card['navigation'] ?? [], 'details'=>[], 'grids'=>[], 'world'=>null, 'action'=>null, 'showcase'=>null,
        ];
        if ($this instanceof \SyntaxDevTeam\Cms\Core\ProfilePanelThemeInterface) $this->render_profile_panel($profile);
        else $this->renderPlayerShowcaseFallbackProfile($profile);
        if ($card['horse'] !== null) {
            $horse = $card['horse'];
            echo '<section class="player-public-horse" aria-label="Wybrany koń"><canvas width="240" height="200" data-public-horse role="img" aria-label="'.$e($horse['name']).'"></canvas><div class="player-public-horse-identity"><span>W siodle</span><h3>'.$e($horse['name']).'</h3>';
            if ($horse['breed'] !== $horse['name']) echo '<p>'.$e($horse['breed']).'</p>';
            echo '</div>';
            if ($card['design']['show_horse_stats']) {
                echo '<dl>';
                foreach ($horse['statistics'] as $fact) echo '<div><dt>'.$e($fact['label']).'</dt><dd>'.$e($fact['value']).'</dd></div>';
                echo '</dl>';
            }
            echo '</section>';
        }
        echo '</div><div id="'.$id.'-card" role="tabpanel" aria-labelledby="'.$id.'-card-tab" data-profile-card hidden></div>';
        echo '<div class="player-showcase-actions player-public-share"><button type="button" data-card-export aria-haspopup="dialog" aria-controls="'.$id.'">Udostępnij kartę</button><button type="button" data-profile-copy>Kopiuj link</button><input data-profile-url type="url" readonly hidden aria-label="Link do publicznego profilu"><p data-profile-status role="status" aria-live="polite"></p></div>';
        echo '<dialog class="player-showcase-dialog" id="'.$id.'" aria-labelledby="'.$id.'-title"><header><div><h2 id="'.$id.'-title">Twoja karta gracza</h2><p>Wybierz format i pobierz PNG.</p></div><button type="button" data-card-export-close aria-label="Zamknij podgląd">✕</button></header>';
        $this->render_player_showcase(array_replace($card,['context'=>'export','navigation'=>[]]));
        echo '</dialog></div>';
    }

    private function renderPlayerShowcaseFallbackProfile(array $profile): void
    {
        $e = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
        echo '<article class="profile-panel player-public-fallback profile-layout-'.$e($profile['card']['layout']).' profile-backdrop-'.$e($profile['card']['backdrop']).' profile-palette-'.$e($profile['card']['palette']).'">';
        if ($profile['navigation'] !== []) {
            echo '<nav class="player-showcase-navigation" aria-label="Nawigacja profilu">';
            foreach ($profile['navigation'] as $link) if (preg_match('~^/(?!/)[a-zA-Z0-9_/?=&%.:-]+$~D',$link['url'])) echo '<a href="'.$e($link['url']).'">'.$e($link['label']).'</a>';
            echo '</nav>';
        }
        echo '<header class="profile-hero"><div class="profile-identity"><p class="profile-eyebrow">'.$e($profile['subtitle']).' · Profil publiczny</p><h2>'.$e($profile['title']).'</h2>';
        if ($profile['show_status']) echo '<p>'.$e($profile['status']).'</p>';
        if ($profile['bio'] !== '') echo '<p class="profile-bio">'.$e($profile['bio']).'</p>';
        echo '<div class="profile-metrics">';
        foreach ($profile['metrics'] as $fact) echo '<div><span>'.$e($fact['label']).'</span><strong>'.$e($fact['value']).'</strong></div>';
        echo '</div></div><div class="profile-portrait">';
        if (is_string($profile['image_url']) && preg_match('~^/(?!/)[a-zA-Z0-9_/?=&%.:-]+$~D',$profile['image_url'])) echo '<img src="'.$e($profile['image_url']).'" alt="Skin gracza '.$e($profile['title']).'" width="128" height="256">';
        echo '</div></header><div class="profile-stat-grid">';
        foreach ($profile['statistics'] as $fact) echo '<div class="profile-stat"><strong>'.$e($fact['value']).'</strong><span>'.$e($fact['label']).'</span></div>';
        echo '</div></article>';
    }
}

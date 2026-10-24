<?php $moonvaleLogo = $this->moonvaleLogoAsset(); ?>
<div class="eq-observatory" aria-label="Świat MoonVale" data-eq-scene>
  <div class="eq-aura eq-aura-one" aria-hidden="true"></div>
  <div class="eq-aura eq-aura-two" aria-hidden="true"></div>
  <div class="eq-starfield" aria-hidden="true"></div>
  <div class="eq-scene-world" aria-hidden="true"></div>
  <div class="eq-scene-flash" aria-hidden="true"></div>
  <div class="eq-orbit" aria-hidden="true"></div>
  <div class="eq-horse"><img src="<?= $this->escape($moonvaleLogo ?? $this->asset('img/horse.svg')) ?>" width="1254" height="1229" alt="Logo MoonVale.pl — koń w fioletowo-różowej mgławicy" fetchpriority="high"></div>
  <span class="eq-scene-label" aria-hidden="true">MOONVALE / TWOJA HISTORIA</span>
  <span class="eq-spark eq-spark-one" aria-hidden="true">✦</span><span class="eq-spark eq-spark-two" aria-hidden="true">✧</span>
</div>

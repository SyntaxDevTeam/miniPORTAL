(() => {
  'use strict';
  const script = document.currentScript;
  const assets = new URL('../img/halloween/', script.src);
  const mount = () => {
    if (document.body.classList.contains('admin-stylebook') || document.body.hasAttribute('data-storefront') || document.querySelector('.halloween-scene')) return;
    const scene = document.createElement('div');
    scene.className = 'halloween-scene';
    scene.setAttribute('aria-hidden', 'true');
    const decoration = (name, className) => {
      const image = document.createElement('img');
      image.src = new URL(name + '.svg', assets).href;
      image.alt = '';
      image.draggable = false;
      image.className = className;
      scene.append(image);
      return image;
    };
    decoration('web', 'halloween-web halloween-web-left');
    decoration('web', 'halloween-web halloween-web-right');
    decoration('pumpkins', 'halloween-pumpkins');
    decoration('bat', 'halloween-bat');
    decoration('bat', 'halloween-bat halloween-bat-small');
    const dracula = decoration('dracula', 'halloween-dracula');
    const toggle = document.createElement('button');
    toggle.type = 'button';
    toggle.className = 'halloween-motion-toggle';
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)');
    let paused = false;
    let timer;
    let previous = -1;
    const updateButton = () => {
      toggle.textContent = paused ? 'Wznów animacje Halloween' : 'Wstrzymaj animacje Halloween';
      toggle.setAttribute('aria-pressed', String(paused));
    };
    const stop = () => {
      window.clearTimeout(timer);
      dracula.classList.remove('is-visiting');
    };
    const schedule = (first = false) => {
      stop();
      if (paused || reduced.matches || document.hidden || !scene.isConnected) return;
      timer = window.setTimeout(() => {
        // Pick a different edge each time, keeping one visitor on screen.
        previous = (previous + 1 + Math.floor(Math.random() * 2)) % 3;
        dracula.dataset.entry = ['top', 'left', 'right'][previous];
        dracula.classList.add('is-visiting');
      }, first ? 9000 : 25000 + Math.random() * 25000);
    };
    toggle.addEventListener('click', () => { paused = !paused; updateButton(); schedule(); });
    dracula.addEventListener('animationend', () => schedule());
    reduced.addEventListener('change', () => schedule());
    document.addEventListener('visibilitychange', () => schedule());
    window.addEventListener('pagehide', stop);
    window.addEventListener('pageshow', () => schedule(true));
    document.body.append(scene, toggle);
    updateButton();
    schedule(true);
  };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', mount, { once: true });
  else mount();
})();

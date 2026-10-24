(() => {
  const copyTimers = new WeakMap();
  document.addEventListener('click', async event => {
    const button = event.target.closest('[data-eq-copy]');
    if (!button || button.disabled) return;
    const status = button.nextElementSibling;
    clearTimeout(copyTimers.get(button));
    status.textContent = '';
    button.disabled = true;
    try {
      await navigator.clipboard.writeText(button.dataset.eqCopy);
      status.textContent = 'Adres skopiowano';
      copyTimers.set(button, setTimeout(() => {
        status.textContent = '';
        copyTimers.delete(button);
      }, 3000));
    } catch {
      status.textContent = 'Nie udało się skopiować. Skopiuj ręcznie: ' + button.dataset.eqCopy;
    } finally {
      button.disabled = false;
    }
  });
  const reduced = window.matchMedia('(prefers-reduced-motion: reduce)');
  let paused = reduced.matches;
  function sync() {
    document.body.classList.toggle('eq-paused', paused);
    if (paused) {
      document.querySelectorAll('.reveal').forEach(element => element.classList.add('is-visible'));
      document.querySelectorAll('[data-eq-particles]').forEach(container => container.replaceChildren());
      document.getAnimations().filter(animation => animation.id === 'eq-selection').forEach(animation => animation.cancel());
    }
    document.querySelectorAll('[data-eq-motion]').forEach(button => {
      button.setAttribute('aria-pressed', String(paused));
      button.textContent = paused ? 'Włącz efekty' : 'Wstrzymaj efekty';
    });
  }
  const observer = 'IntersectionObserver' in window ? new IntersectionObserver(entries => {
    entries.forEach(entry => {
      if (!entry.isIntersecting) return;
      entry.target.classList.add('is-visible');
      observer.unobserve(entry.target);
    });
  }, {threshold: .08}) : null;
  function init() {
    document.querySelectorAll('.reveal:not(.is-visible)').forEach(element => {
      if (paused || !observer) { element.classList.add('is-visible'); return; }
      element.classList.add('eq-reveal');
      observer.observe(element);
    });
    document.querySelectorAll('.eq-observatory').forEach(scene => {
      if (scene.dataset.ready) return;
      scene.dataset.ready = 'true';
      scene.addEventListener('pointermove', event => {
        if (paused || reduced.matches || event.pointerType !== 'mouse') return;
        const rect = scene.getBoundingClientRect();
        scene.style.setProperty('--eq-x', `${((event.clientX - rect.left) / rect.width - .5) * 16}px`);
        scene.style.setProperty('--eq-y', `${((event.clientY - rect.top) / rect.height - .5) * 12}px`);
      }, {passive: true});
      scene.addEventListener('pointerleave', () => {
        scene.style.removeProperty('--eq-x');
        scene.style.removeProperty('--eq-y');
      });
    });
    sync();
  }
  document.addEventListener('click', event => {
    const world = event.target.closest('[data-eq-world]');
    if (world) {
      const elements = {
        breeding: {description: 'Własne konie, własna stajnia i autorskie systemy hodowli. Twoja pasja ma tu przestrzeń.', title: 'Twoja stajnia. Twoja historia.', label: 'ŻYWIOŁ / HODOWLA', emblem: '♘', chips: ['Konie', 'Stajnie', 'Hodowla']},
        sport: {description: 'Przeróżne zawody, nowe wyzwania i emocje. Rozwijaj swoją przygodę z końmi po swojemu.', title: 'Poczuj emocje kolejnego startu.', label: 'ŻYWIOŁ / ZAWODY', emblem: '✧', chips: ['Zawody', 'Wyzwania', 'Emocje']},
        survival: {description: 'Rozległe działki, farmy, prace i handel. Zbudowana baza otwiera kolejny rozdział Twojej historii.', title: 'Zbuduj swój kawałek świata.', label: 'ŻYWIOŁ / SURVIVAL', emblem: '◇', chips: ['Działki', 'Farmy', 'Handel']}
      };
      const selection = world.dataset.eqWorld;
      if (!Object.hasOwn(elements, selection)) return;
      const element = elements[selection];
      document.querySelectorAll('[data-eq-world]').forEach(button => button.setAttribute('aria-pressed', String(button.dataset.eqWorld === selection)));
      document.querySelectorAll('[data-eq-world-description]').forEach(text => text.textContent = element.description);
      document.querySelectorAll('[data-eq-element-title]').forEach(text => text.textContent = element.title);
      document.querySelectorAll('[data-eq-element-label]').forEach(text => text.textContent = element.label);
      document.querySelectorAll('[data-eq-emblem]').forEach(text => text.textContent = element.emblem);
      document.querySelectorAll('[data-eq-element-chips]').forEach(container => {
        container.replaceChildren(...element.chips.map(label => { const chip = document.createElement('span'); chip.textContent = label; return chip; }));
      });
      document.querySelectorAll('[data-eq-scene], [data-eq-element-panel]').forEach(scene => {
        scene.dataset.world = selection;
        scene.getAnimations().filter(animation => animation.id === 'eq-selection').forEach(animation => animation.cancel());
        if (!paused && !reduced.matches) {
          const effect = scene.matches('[data-eq-scene]') ? scene.querySelector('.eq-scene-flash') : scene.querySelector('.eq-element-emblem');
          if (effect && typeof effect.animate === 'function') {
            effect.getAnimations().forEach(animation => animation.cancel());
            effect.animate(scene.matches('[data-eq-scene]') ? [{opacity: 0, transform: 'scale(.7)'}, {opacity: .7, offset: .25}, {opacity: 0, transform: 'scale(1.15)'}] : [{transform: 'scale(.7) rotate(-20deg)', opacity: .3}, {transform: 'scale(1.12) rotate(5deg)', offset: .5}, {transform: 'scale(1) rotate(0)', opacity: 1}], {duration: 1000, easing: 'ease-out', id: 'eq-selection'});
          }
        }
      });
      document.querySelectorAll('[data-eq-particles]').forEach(container => {
        container.replaceChildren();
        if (paused || reduced.matches) return;
        const count = selection === 'sport' ? 24 : 16;
        for (let number = 0; number < count; number++) {
          const particle = document.createElement('i');
          const angle = number * Math.PI * 2 / count;
          particle.style.setProperty('--dx', `${Math.cos(angle) * (80 + number % 4 * 30)}px`);
          particle.style.setProperty('--dy', `${Math.sin(angle) * (40 + number % 4 * 20)}px`);
          particle.dataset.kind = selection;
          container.append(particle);
        }
        const particles = [...container.children];
        window.setTimeout(() => particles.forEach(particle => particle.remove()), 1400);
      });
      const index = ['breeding', 'sport', 'survival'].indexOf(selection);
      document.querySelectorAll('#swiat .showcase-card').forEach((card, number) => card.classList.toggle('eq-world-active', index === number));
      return;
    }
    if (!event.target.closest('[data-eq-motion]')) return;
    paused = !paused;
    sync();
  });
  reduced.addEventListener('change', () => { paused = reduced.matches; sync(); });
  document.addEventListener('htmx:afterSwap', init);
  init();
})();

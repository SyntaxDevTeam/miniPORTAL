(() => {
  'use strict';

  if (window.miniportalLinkStrip) {
    window.miniportalLinkStrip();
    return;
  }

  const initialized = new WeakSet();
  const initialize = () => {
    document.querySelectorAll('.link-strip').forEach((section) => {
      const track = section.querySelector('.link-strip-track');
      const list = track?.querySelector('.link-strip-list');
      const toggle = section.querySelector('.link-strip-toggle');
      if (!toggle || !list || list.children.length < 4 || initialized.has(section)) return;
      initialized.add(section);
      track.querySelector('.link-strip-list[aria-hidden="true"]')?.remove();

      const copy = list.cloneNode(true);
      copy.setAttribute('aria-hidden', 'true');
      copy.querySelectorAll('a').forEach((link) => {
        link.tabIndex = -1;
        link.addEventListener('mousedown', (event) => event.preventDefault());
      });
      track.append(copy);
      track.style.setProperty('--link-strip-duration', `${list.children.length * 10}s`);

      const motion = window.matchMedia('(prefers-reduced-motion: reduce)');
      let paused = false;
      const update = () => {
        const looping = !paused && !motion.matches;
        section.classList.toggle('is-looping', looping);
        toggle.hidden = motion.matches;
        toggle.textContent = looping ? 'Pause animation' : 'Resume animation';
      };

      toggle.addEventListener('click', () => {
        paused = !paused;
        update();
      });
      list.addEventListener('focusin', (event) => {
        if (!event.target.matches(':focus-visible')) return;
        paused = true;
        update();
      });
      motion.addEventListener('change', update);
      section.addEventListener('htmx:beforeCleanupElement', () => {
        motion.removeEventListener('change', update);
      }, { once: true });
      update();
    });
  };

  window.miniportalLinkStrip = initialize;
  initialize();
  document.addEventListener('htmx:afterSwap', initialize);
  document.addEventListener('htmx:historyRestore', initialize);
})();

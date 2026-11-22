(() => {
  'use strict';
  const initialize = () => {
    document.querySelectorAll('[data-technology-marquee]').forEach((viewport) => {
      if (viewport.dataset.technologyReady) return;
      viewport.dataset.technologyReady = '1';
      const track = viewport.querySelector('.technology-track');
      const group = track?.querySelector('.technology-list');
      if (!group) return;
      const originals = [...group.children];
      if (!originals.length) return;
      const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
      const decorativeCopy = (node) => {
        const copy = node.cloneNode(true);
        copy.dataset.technologyCopy = '1';
        copy.setAttribute('aria-hidden', 'true');
        // Copies remain clickable, but only the original links enter the tab order.
        copy.querySelectorAll('a').forEach((link) => {
          link.tabIndex = -1;
          link.addEventListener('mousedown', (event) => event.preventDefault());
        });
        copy.querySelectorAll('[id]').forEach((child) => child.removeAttribute('id'));
        return copy;
      };
      const rebuild = () => {
        track.querySelectorAll('[data-technology-copy]').forEach((copy) => copy.remove());
        viewport.classList.remove('is-looping');
        if (reducedMotion.matches || !viewport.clientWidth) return;
        // Both groups must fill the viewport, including when only one logo exists.
        const naturalWidth = group.getBoundingClientRect().width;
        if (!naturalWidth) return;
        const repetitions = Math.max(1, Math.ceil(viewport.clientWidth / naturalWidth));
        for (let i = 1; i < repetitions; i += 1) {
          originals.forEach((item) => group.append(decorativeCopy(item)));
        }
        const distance = group.getBoundingClientRect().width;
        track.append(decorativeCopy(group));
        track.style.setProperty('--technology-distance', `${distance}px`);
        track.style.setProperty('--technology-duration', `${Math.max(12, distance / 32)}s`);
        viewport.classList.add('is-looping');
      };
      viewport.addEventListener('focusin', (event) => {
        if (event.target instanceof HTMLElement && event.target.matches('a')) {
          const link = event.target.getBoundingClientRect();
          const bounds = viewport.getBoundingClientRect();
          if (link.left < bounds.left) viewport.scrollLeft -= bounds.left - link.left;
          if (link.right > bounds.right) viewport.scrollLeft += link.right - bounds.right;
        }
      });
      viewport.addEventListener('focusout', (event) => {
        if (!viewport.contains(event.relatedTarget)) viewport.scrollLeft = 0;
      });
      let frame;
      const schedule = () => { cancelAnimationFrame(frame); frame = requestAnimationFrame(rebuild); };
      if ('ResizeObserver' in window) new ResizeObserver(schedule).observe(viewport);
      else window.addEventListener('resize', schedule);
      reducedMotion.addEventListener('change', schedule);
      rebuild();
    });
  };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize, { once: true });
  else initialize();
  document.addEventListener('htmx:afterSwap', initialize);
})();

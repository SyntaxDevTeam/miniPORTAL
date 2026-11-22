(() => {
  'use strict';
  const motion = matchMedia('(prefers-reduced-motion: reduce)');
  const transparency = matchMedia('(prefers-reduced-transparency: reduce)');
  const fine = matchMedia('(hover: hover) and (pointer: fine)');
  let pane = null, frame = 0, x = 50, y = 0;
  const reset = () => {
    if (pane) { pane.style.removeProperty('--glass-x'); pane.style.removeProperty('--glass-y'); }
    pane = null;
  };
  document.addEventListener('pointermove', event => {
    if (!fine.matches || motion.matches || transparency.matches) { reset(); return; }
    const next = event.target instanceof Element
      ? event.target.closest('.tidal-hero-copy,.managed-card,.admin-panel,.metric-card,.contact-item') : null;
    if (next !== pane) { reset(); pane = next; }
    if (!pane) return;
    const rect = pane.getBoundingClientRect();
    x = Math.max(0, Math.min(100, (event.clientX - rect.left) / Math.max(1, rect.width) * 100));
    y = Math.max(0, Math.min(100, (event.clientY - rect.top) / Math.max(1, rect.height) * 100));
    if (frame) return;
    frame = requestAnimationFrame(() => {
      frame = 0;
      if (pane) { pane.style.setProperty('--glass-x', x + '%'); pane.style.setProperty('--glass-y', y + '%'); }
    });
  }, {passive:true});
  document.addEventListener('pointerleave', reset);
  window.addEventListener('blur', reset);
  motion.addEventListener('change', reset);
  transparency.addEventListener('change', reset);
})();

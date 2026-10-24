(() => {
  'use strict';
  if (window.moonvaleInventoryTooltips) return;
  window.moonvaleInventoryTooltips = true;
  let active = null;
  const trigger = (target) => target instanceof Element ? target.closest('[data-inventory-item]') : null;
  function hide() {
    if (!active) return;
    const { tip, home } = active;
    tip.hidden = true;
    if (home.isConnected) home.append(tip);
    else tip.remove();
    active = null;
  }
  function position(x, y) {
    if (!active) return;
    const { tip } = active;
    const { width, height } = tip.getBoundingClientRect();
    const edge = 8;
    const left = x + 16 + width > window.innerWidth - edge ? x - width - 16 : x + 16;
    const top = y + 16 + height > window.innerHeight - edge ? y - height - 12 : y + 16;
    tip.style.left = `${Math.max(edge, Math.min(left, window.innerWidth - width - edge))}px`;
    tip.style.top = `${Math.max(edge, Math.min(top, window.innerHeight - height - edge))}px`;
  }
  function show(button, x, y) {
    if (active?.button === button) return;
    hide();
    const home = button.parentElement;
    const tip = home?.querySelector('.inventory-tooltip');
    if (!tip) return;
    active = { button, home, tip };
    document.body.append(tip);
    tip.hidden = false;
    position(x, y);
  }
  document.addEventListener('pointerover', (event) => {
    if (event.pointerType === 'touch') return;
    const button = trigger(event.target);
    if (button) show(button, event.clientX, event.clientY);
  });
  document.addEventListener('pointermove', (event) => {
    if (active && event.pointerType !== 'touch' && trigger(event.target) === active.button) position(event.clientX, event.clientY);
  });
  document.addEventListener('pointerout', (event) => {
    if (active && trigger(event.target) === active.button && !active.button.contains(event.relatedTarget)) hide();
  });
  document.addEventListener('focusin', (event) => {
    const button = trigger(event.target);
    if (!button?.matches(':focus-visible')) return;
    const rect = button.getBoundingClientRect();
    show(button, rect.right, rect.top);
  });
  document.addEventListener('focusout', (event) => { if (active?.button === event.target) hide(); });
  document.addEventListener('keydown', (event) => { if (event.key === 'Escape') hide(); });
  // A touch preview lasts only while the finger stays on the item; no pinned popups.
  document.addEventListener('pointerdown', (event) => {
    if (event.pointerType !== 'touch') return;
    const button = trigger(event.target);
    if (button) show(button, event.clientX, event.clientY);
  });
  document.addEventListener('pointerup', (event) => { if (event.pointerType === 'touch') hide(); });
  document.addEventListener('pointercancel', hide);
  document.addEventListener('scroll', hide, { capture: true, passive: true });
  document.addEventListener('htmx:beforeSwap', hide);
  document.addEventListener('visibilitychange', hide);
  window.addEventListener('resize', hide);
  window.addEventListener('blur', hide);
})();

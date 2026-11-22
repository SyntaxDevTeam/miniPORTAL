(() => {
  'use strict';
  const started = performance.now();
  const timers = [...document.querySelectorAll('[data-countdown-until]')].flatMap(element => {
    const until = Number(element.dataset.countdownUntil);
    const now = Number(element.dataset.countdownNow);
    const output = element.querySelector('[data-countdown-output]');
    return Number.isSafeInteger(until) && until > 0 && Number.isSafeInteger(now) && now > 0 && output ? [{element, until, now, output}] : [];
  });
  const tick = () => {
    let running = false;
    timers.forEach(timer => {
      if (!timer.element.isConnected) return;
      const remaining = Math.max(0, Math.ceil((timer.until - timer.now - (performance.now() - started)) / 1000));
      if (remaining === 0) {
        timer.output.textContent = 'Termin upłynął — oczekiwanie na synchronizację';
        timer.element.classList.add('is-expired');
        return;
      }
      running = true;
      const days = Math.floor(remaining / 86400);
      const dayLabel = days > 0 ? `${days} ${days === 1 ? 'dzień' : 'dni'} ` : '';
      const h = String(Math.floor(remaining % 86400 / 3600)).padStart(2, '0');
      const m = String(Math.floor(remaining % 3600 / 60)).padStart(2, '0');
      const s = String(remaining % 60).padStart(2, '0');
      timer.output.textContent = `${timer.element.dataset.countdownPrefix || 'Twoja ranga kończy się za '}${dayLabel}${h}:${m}:${s}`;
    });
    return running;
  };
  if (tick()) {
    const interval = setInterval(() => { if (!tick()) { clearInterval(interval); document.removeEventListener('visibilitychange', tick); } }, 1000);
    document.addEventListener('visibilitychange', tick);
  }
})();

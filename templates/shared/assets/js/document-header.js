(() => {
  'use strict';
  const key = Symbol.for('miniportal.document-header');
  if (window[key]) { window[key](); return; }
  window[key] = init;
  function init() {
    document.querySelectorAll('[data-document-header]').forEach(header => {
      if (header.dataset.documentReady) return;
      header.dataset.documentReady = '1';
      const share = header.querySelector('[data-document-share]');
      const trigger = header.querySelector('[data-document-share-trigger]');
      const status = header.querySelector('[data-document-status]');
      const field = header.querySelector('[data-document-url]');
      const path = header.dataset.sharePath;
      if (!/^\/article\/[a-z0-9-]+$/.test(path || '') || !share || !trigger) return;
      const url = new URL(path, location.origin).href;
      const title = header.dataset.shareTitle;
      const track = event => {
        if (!header.hasAttribute('data-article-statistics')) return;
        void fetch(path + '/statistics', {
          method: 'POST',
          mode: 'same-origin',
          headers: {'X-Article-Event': event},
          keepalive: true
        }).catch(() => {});
      };
      if (document.prerendering) {
        document.addEventListener('prerenderingchange', () => track('view'), {once: true});
      } else {
        track('view');
      }
      field.value = url;
      header.querySelector('[data-document-facebook]').href = 'https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(url);
      header.querySelector('[data-document-mail]').href = 'mailto:?subject=' + encodeURIComponent(title) + '&body=' + encodeURIComponent(url);
      header.querySelector('[data-document-facebook]').addEventListener('click', () => track('share'));
      header.querySelector('[data-document-mail]').addEventListener('click', () => track('share'));
      let sharing = false;
      trigger.addEventListener('click', async event => {
        if (typeof navigator.share !== 'function') return;
        event.preventDefault();
        if (sharing) return;
        sharing = true;
        status.textContent = '';
        try {
          await navigator.share({title, url});
          track('share');
        }
        catch (error) {
          if (error.name !== 'AbortError') {
            share.open = true;
            status.textContent = 'Wybierz sposób udostępnienia lub skopiuj link.';
          }
        } finally { sharing = false; }
      });
      const copy = header.querySelector('[data-document-copy]');
      copy.hidden = false;
      copy.addEventListener('click', async () => {
        try {
          if (!navigator.clipboard?.writeText) throw new Error('Clipboard unavailable');
          await navigator.clipboard.writeText(url);
          track('share');
          status.textContent = 'Link skopiowany.';
          share.open = false;
          trigger.focus();
        } catch {
          status.textContent = 'Zaznaczony link możesz skopiować ręcznie.';
          field.focus(); field.select();
        }
      });
      share.addEventListener('keydown', event => {
        if (event.key === 'Escape') { share.open = false; trigger.focus(); }
      });
      // One document listener for all headers, including fragments loaded by HTMX.
    });
  }
  document.addEventListener('click', event => {
    document.querySelectorAll('[data-document-share][open]').forEach(share => {
      if (!share.contains(event.target)) share.open = false;
    });
  });
  init();
  document.addEventListener('htmx:afterSwap', init);
})();

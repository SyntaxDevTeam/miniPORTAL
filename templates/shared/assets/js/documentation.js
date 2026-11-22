(() => {
  'use strict';
  function init(root) {
    if (root.dataset.wikiReady) return;
    root.dataset.wikiReady = 'true';
    const search = root.querySelector('[data-wiki-search]');
    const normalize = value => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase().replace(/ł/g, 'l');
    search?.addEventListener('input', () => {
      const terms = normalize(search.value.trim()).split(/\s+/).filter(Boolean);
      let count = 0;
      root.querySelectorAll('[data-wiki-result]').forEach(item => {
        item.hidden = !terms.every(term => normalize(item.dataset.search).includes(term));
        if (!item.hidden) count++;
      });
      root.querySelectorAll('[data-wiki-empty]').forEach(empty => { empty.hidden = count > 0; });
      if (search.value) root.querySelector('.wiki-document-menu')?.setAttribute('open', '');
    });
    const menu = root.querySelector('.wiki-document-menu');
    if (menu && window.matchMedia('(max-width: 760px)').matches) menu.open = false;
    const prose = root.querySelector('[data-wiki-prose]');
    const toc = root.querySelector('[data-wiki-toc]');
    const inlineToc = root.querySelector('[data-wiki-toc-mobile]');
    if (!prose) return;
    const usedIds = new Set(Array.from(document.querySelectorAll('[id]'), el => el.id));
    const headings = Array.from(prose.querySelectorAll('h2,h3'));
    headings.forEach((heading, index) => {
      if (!heading.id) {
        let id = `wiki-section-${index + 1}`;
        while (usedIds.has(id)) id += '-section';
        heading.id = id;
        usedIds.add(id);
      }
      const link = document.createElement('a');
      link.href = `#${encodeURIComponent(heading.id)}`;
      link.textContent = heading.textContent;
      link.dataset.level = heading.tagName.slice(1);
      toc?.querySelector('nav').append(link);
      inlineToc?.querySelector('nav').append(link.cloneNode(true));
    });
    if (headings.length && toc) {
      toc.hidden = false;
      if (inlineToc) inlineToc.hidden = false;
      const links = Array.from(toc.querySelectorAll('nav a'));
      if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver(entries => {
          const visible = entries.filter(entry => entry.isIntersecting).sort((a, b) => a.boundingClientRect.top - b.boundingClientRect.top);
          if (!visible.length) return;
          links.forEach(link => {
            if (decodeURIComponent(link.hash.slice(1)) === visible[0].target.id) link.setAttribute('aria-current', 'location');
            else link.removeAttribute('aria-current');
          });
        }, { rootMargin: '-100px 0px -55% 0px' });
        headings.forEach(heading => observer.observe(heading));
      }
    }
    prose.querySelectorAll('table').forEach(table => {
      const scroll = document.createElement('div');
      scroll.className = 'wiki-table-scroll';
      scroll.tabIndex = 0;
      scroll.setAttribute('role', 'region');
      scroll.setAttribute('aria-label', table.querySelector('caption')?.textContent || (root.lang === 'pl' ? 'Tabela dokumentacji' : 'Documentation table'));
      table.before(scroll);
      scroll.append(table);
    });
    const article = root.querySelector('[data-wiki-article]');
    prose.querySelectorAll('pre').forEach(pre => {
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'wiki-copy';
      button.textContent = article.dataset.copy;
      pre.before(button);
      button.addEventListener('click', async () => {
        try {
          await navigator.clipboard.writeText(pre.textContent);
          button.textContent = article.dataset.copied;
        } catch {
          const range = document.createRange();
          range.selectNodeContents(pre);
          const selection = window.getSelection();
          selection.removeAllRanges();
          selection.addRange(range);
          button.textContent = article.dataset.copyError;
        }
        setTimeout(() => { button.textContent = article.dataset.copy; }, 2200);
      });
    });
  }
  const boot = () => document.querySelectorAll('[data-wiki]').forEach(init);
  boot();
  document.addEventListener('htmx:afterSwap', boot);
  document.addEventListener('keydown', event => {
    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
      const search = document.querySelector('[data-wiki-search]');
      if (search) { event.preventDefault(); search.focus(); search.select(); }
    }
  });
})();

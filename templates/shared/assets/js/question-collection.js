(() => {
    'use strict';
    const normalize = value => value.toLocaleLowerCase('pl').normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/ł/g, 'l');
    const tokens = value => normalize(value).match(/[\p{L}\p{N}_/]+/gu) || [];
    const stopWords = new Set('jak czy co czym gdzie kiedy ktory ktora ktore sie na w we z ze i o do od po za dla to jest sa moge mozna mam mi moj moja'.split(' '));
    const stem = word => {
        if (word.startsWith('/') || word.length < 4) return word;
        // Conservative inflection folding, not arbitrary substring matching.
        const suffix = word.match(/(?:iami|iach|iowi|owego|owej|owych|owie|ami|ach|iem|iom|owi|ow|om|em|ia|ie|iu|mi|ego|ej|ych|a|e|i|y|u|o)$/);
        return suffix && word.length - suffix[0].length >= 3 ? word.slice(0, -suffix[0].length) : word;
    };
    class QuestionSearchIndex {
        constructor(documents) {
            this.exact = new Map();
            this.inflected = new Map();
            this.titles = new Map();
            this.categories = new Map();
            const add = (index, key, id, weight) => {
                if (!index.has(key)) index.set(key, new Map());
                const posting = index.get(key);
                posting.set(id, Math.max(posting.get(id) || 0, weight));
            };
            documents.forEach(document => {
                this.titles.set(document.id, tokens(document.title).join(' '));
                this.categories.set(document.id, new Set(tokens(document.category || '').map(stem)));
                [['title', 40], ['keywords', 30], ['category', 20], ['answer', 6]].forEach(([field, weight]) => {
                    new Set(tokens(document[field] || '')).forEach(word => {
                        add(this.exact, word, document.id, weight);
                        add(this.inflected, stem(word), document.id, weight * 0.8);
                    });
                });
            });
        }
        search(query) {
            const words = [...new Set(tokens(query))].slice(0, 20);
            if (!words.length) return null;
            const important = words.filter(word => !stopWords.has(word));
            const terms = important.length ? important : words;
            let result = null;
            terms.forEach(term => {
                const matches = new Map();
                const merge = (posting, scale = 1) => posting?.forEach((score, id) => matches.set(id, Math.max(matches.get(id) || 0, score * scale)));
                merge(this.exact.get(term));
                merge(this.inflected.get(stem(term)));
                // Prefix suggestions only when a whole word/inflection has no match.
                // Thus "koń" finds konie/konia, but never the substring in konto.
                if (!matches.size && term.length >= 3 && !term.startsWith('/')) {
                    this.exact.forEach((posting, word) => { if (word.startsWith(term)) merge(posting, 0.4); });
                }
                if (result === null) result = matches;
                else result.forEach((score, id) => {
                    if (!matches.has(id)) result.delete(id);
                    else result.set(id, score + matches.get(id));
                });
            });
            const phrase = words.join(' ');
            result.forEach((score, id) => {
                score += terms.filter(term => this.categories.get(id).has(stem(term))).length * 12;
                if ((' ' + this.titles.get(id) + ' ').includes(' ' + phrase + ' ')) score += 80;
                result.set(id, score);
            });
            return result;
        }
    }
    // The same DOM-free index is exercised by Node regression tests.
    if (typeof module === 'object' && module.exports) { module.exports = { QuestionSearchIndex }; return; }
    const plainText = node => {
        const clone = node.cloneNode(true);
        clone.querySelectorAll('br,p,li,div,blockquote,pre,h1,h2,h3,h4,tr').forEach(block => block.after(document.createTextNode(' ')));
        return clone.textContent.replace(/\s+/g, ' ').trim();
    };
    const init = () => document.querySelectorAll('[data-question-collection]').forEach(root => {
        if (root.dataset.ready) return;
        root.dataset.ready = '1';
        root.classList.add('is-enhanced');
        root.querySelectorAll('[data-help-enhancement]').forEach(node => { node.hidden = false; });
        const input = root.querySelector('input[type="search"]');
        if (!input) return;
        const form = root.querySelector('[data-help-search]');
        const picker = root.querySelector('[data-help-category-select]');
        const feedback = root.querySelector('[data-help-feedback]');
        const allTopics = root.querySelector('[data-help-all]');
        const groups = [...root.querySelectorAll('[data-help-group]')];
        const questions = [...root.querySelectorAll('[data-help-question]')];
        const groupQuestions = new Map(groups.map(group => [group, [...group.querySelectorAll('[data-help-question]')]]));
        const answers = new Map(questions.map(node => [node, plainText(node.querySelector('.help-answer'))]));
        const searchIndex = new QuestionSearchIndex(questions.map(node => ({
            id: node,
            title: (node.querySelector('[data-help-title]') || node.querySelector('summary span')).textContent,
            category: node.closest('[data-help-group]').querySelector('h2').textContent,
            keywords: node.dataset.keywords,
            answer: answers.get(node),
        })));
        const resultsContainer = root.querySelector('.help-results');
        const empty = root.querySelector('[data-help-empty]');
        const status = root.querySelector('[data-help-status]');
        const expand = root.querySelector('[data-help-expand]');
        const clear = root.querySelector('[data-help-clear]');
        let category = 'all';
        let previousQuery = '';
        let pending;
        let composing = false;
        const updateExpand = () => {
            const visible = questions.filter(node => !node.hidden);
            const allOpen = visible.length > 0 && visible.every(node => node.open);
            expand.textContent = allOpen ? 'Zwiń odpowiedzi' : 'Rozwiń odpowiedzi';
            expand.setAttribute('aria-expanded', String(allOpen));
            expand.disabled = visible.length === 0;
        };
        const selectCategory = selected => {
            category = selected;
            if (picker) picker.value = selected;
            root.querySelectorAll('[data-help-category]').forEach(link => {
                const active = link.dataset.helpCategory === category;
                link.classList.toggle('is-active', active);
                if (active) link.setAttribute('aria-current', 'true'); else link.removeAttribute('aria-current');
            });
        };
        const refresh = () => {
            clearTimeout(pending);
            const query = input.value.trim();
            const terms = tokens(query);
            const ranked = searchIndex.search(query);
            const searching = ranked !== null;
            const changed = query !== previousQuery;
            root.classList.toggle('is-searching', searching);
            let count = 0;
            groups.forEach(group => {
                let visible = 0;
                groupQuestions.get(group).forEach(node => {
                    const answer = answers.get(node);
                    const match = (category === 'all' || category === group.dataset.helpGroup) && (!searching || ranked.has(node));
                    node.hidden = !match;
                    if (match) { visible++; count++; }
                    // Keep search results compact; the reader chooses which answer to open.
                    if (changed) node.open = false;
                    const snippet = node.querySelector('[data-help-match]');
                    if (snippet) {
                        snippet.hidden = !searching || !match;
                        if (searching && match) {
                            const normalizedAnswer = normalize(answer);
                            const positions = terms.map(term => normalizedAnswer.indexOf(term)).filter(index => index >= 0);
                            const start = Math.max(0, (positions.length ? Math.min(...positions) : 0) - 35);
                            snippet.textContent = (start ? '…' : '') + answer.slice(start, start + 155) + (answer.length > start + 155 ? '…' : '');
                        } else snippet.textContent = '';
                    }
                });
                group.hidden = visible === 0;
                const ordered = [...groupQuestions.get(group)];
                if (searching) ordered.sort((a, b) => (ranked.get(b) || 0) - (ranked.get(a) || 0));
                ordered.forEach(node => group.append(node));
            });
            const orderedGroups = [...groups];
            if (searching) {
                const groupScore = group => Math.max(0, ...groupQuestions.get(group).filter(node => !node.hidden).map(node => ranked.get(node) || 0));
                orderedGroups.sort((a, b) => groupScore(b) - groupScore(a));
            }
            orderedGroups.forEach(group => resultsContainer.insertBefore(group, empty));
            previousQuery = query;
            root.querySelector('[data-help-empty]').hidden = count !== 0;
            const label = count === 1 ? '1 odpowiedź' : count + ' odpowiedzi';
            status.textContent = label;
            const categoryLabel = category === 'all' ? 'Wszystkie tematy' : (picker?.selectedOptions[0]?.textContent.replace(/\s*\(\d+\)$/, '') || 'Wybrany temat');
            if (feedback) feedback.textContent = label + ' · ' + categoryLabel;
            if (allTopics) allTopics.hidden = category === 'all';
            clear.hidden = input.value === '';
            updateExpand();
        };
        const showResults = () => {
            refresh();
            input.blur();
            picker?.blur();
            const results = root.querySelector('.help-results');
            results.focus({ preventScroll: true });
            results.scrollIntoView({ block: 'start', behavior: 'instant' });
        };
        const reset = () => { input.value = ''; selectCategory('all'); refresh(); };
        const revealHash = () => {
            if (!/^#answer-[1-9][0-9]*$/.test(location.hash)) return;
            const node = root.querySelector(location.hash);
            if (!node) return;
            reset(); node.open = true;
            node.scrollIntoView({ block: 'start', behavior: 'instant' });
        };
        input.addEventListener('compositionstart', () => { composing = true; clearTimeout(pending); });
        input.addEventListener('compositionend', () => { composing = false; if (input.value.trim() !== previousQuery) selectCategory('all'); refresh(); });
        input.addEventListener('input', () => {
            clearTimeout(pending);
            if (composing) return;
            // A new phrase starts across all topics, rather than inheriting an invisible filter.
            if (input.value.trim() !== previousQuery) selectCategory('all');
            pending = setTimeout(refresh, 100);
        });
        input.addEventListener('search', refresh);
        input.addEventListener('keydown', event => {
            if (event.key === 'Escape') { event.preventDefault(); reset(); input.focus(); }
            if (event.key === 'Enter' && !composing && !event.isComposing) { event.preventDefault(); showResults(); }
        });
        form?.addEventListener('submit', event => { event.preventDefault(); if (!composing) showResults(); });
        picker?.addEventListener('change', () => { selectCategory(picker.value); showResults(); });
        questions.forEach(node => node.addEventListener('toggle', updateExpand));
        root.addEventListener('click', async event => {
            const link = event.target.closest('[data-help-category]');
            if (link) { event.preventDefault(); selectCategory(link.dataset.helpCategory); showResults(); }
            if (event.target.closest('[data-help-edit]')) { form?.scrollIntoView({ block: 'start', behavior: 'instant' }); input.focus({ preventScroll: true }); }
            if (event.target.closest('[data-help-clear]')) { reset(); input.focus({ preventScroll: true }); }
            if (event.target.closest('[data-help-reset]')) { reset(); input.focus(); }
            if (event.target.closest('[data-help-all]')) { selectCategory('all'); showResults(); }
            if (event.target.closest('[data-help-expand]')) {
                const open = expand.getAttribute('aria-expanded') !== 'true';
                questions.filter(node => !node.hidden).forEach(node => { node.open = open; });
                updateExpand();
            }
            const anchor = event.target.closest('[data-help-featured],[data-help-permalink]');
            if (anchor) { event.preventDefault(); history.replaceState(null, '', anchor.hash); revealHash(); }
            const copy = event.target.closest('[data-help-copy]');
            if (copy) {
                const url = new URL(location.href); url.hash = copy.closest('details').id;
                try { await navigator.clipboard.writeText(url.href); copy.textContent = 'Link skopiowany'; }
                catch { copy.textContent = 'Otwórz „Link do odpowiedzi”'; }
                setTimeout(() => { copy.textContent = 'Kopiuj link'; }, 2500);
            }
        });
        window.addEventListener('hashchange', revealHash);
        refresh(); revealHash();
    });
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
    document.addEventListener('htmx:load', init);
})();

(async () => {
    'use strict';
    const root = document.querySelector('[data-api-reference]');
    if (!root) return;
    const glossary = root.querySelector('.api-reference__glossary');
    if (glossary && window.matchMedia('(max-width: 760px)').matches) glossary.open = false;
    const loading = root.querySelector('[data-api-loading]');
    const error = root.querySelector('[data-api-error]');
    const showError = () => { loading.hidden = true; error.hidden = false; };
    try {
        if (typeof window.SwaggerUIBundle !== 'function') throw new Error('Swagger UI unavailable');
        const specUrl = new URL(root.dataset.specUrl, window.location.origin);
        if (specUrl.origin !== window.location.origin) throw new Error('External specification rejected');
        const response = await fetch(specUrl.href, {credentials: 'same-origin', headers: {Accept: 'application/json'}});
        if (!response.ok) throw new Error('Specification unavailable');
        const spec = await response.json();
        if (!spec.openapi || !spec.paths) throw new Error('Invalid specification');
        const ui = window.SwaggerUIBundle({
            spec,
            dom_id: '#api-reference-swagger',
            layout: 'BaseLayout',
            deepLinking: true,
            filter: true,
            docExpansion: 'list',
            defaultModelsExpandDepth: -1,
            defaultModelExpandDepth: 2,
            displayRequestDuration: true,
            displayOperationId: false,
            showExtensions: false,
            showCommonExtensions: false,
            tryItOutEnabled: false,
            supportedSubmitMethods: ['get', 'head'],
            validatorUrl: null,
            queryConfigEnabled: false,
            persistAuthorization: false,
            syntaxHighlight: false,
            requestInterceptor: request => {
                const target = new URL(request.url, window.location.origin);
                if (target.origin !== window.location.origin || !target.pathname.startsWith('/api/v2/builds')) {
                    throw new Error('Konsola wykonuje zapytania wyłącznie do API tej witryny.');
                }
                request.credentials = 'same-origin';
                return request;
            },
            onComplete: () => {
                loading.hidden = true;
                window.requestAnimationFrame(() => {
                    const filter = root.querySelector('.filter-container input, input[placeholder="Filter by tag or path"]');
                    if (filter) {
                        filter.placeholder = 'Szukaj endpointu lub grupy…';
                        filter.setAttribute('aria-label', 'Szukaj endpointu lub grupy');
                    }
                });
                root.dataset.apiReady = 'true';
            },
            onFailure: showError,
        });
        window.addEventListener('hashchange', () => {
            if (!window.location.hash.startsWith('#/')) return;
            try { ui.layoutActions.parseDeepLinkHash(decodeURIComponent(window.location.hash)); } catch { /* Ignore malformed fragment links. */ }
        });
    } catch {
        showError();
    }
})();

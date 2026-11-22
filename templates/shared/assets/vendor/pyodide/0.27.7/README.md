# Pyodide 0.27.7 (CPython 3.12.7)

Lokalny interpreter standardowej biblioteki Python, pobrany 2026-11-12 z
https://cdn.jsdelivr.net/pyodide/v0.27.7/full/ (oficjalna dystrybucja projektu).
Pliki JS/WASM/stdlib są niezmienione; pyodide-lock.json zapisano jako
pyodide-lock.txt ze względu na politykę publicznych plików miniPORTAL.
Nie dołączono kół pakietów dodatkowych; katalog nie obsługuje pip.

Licencja: LICENSE z https://github.com/pyodide/pyodide/blob/0.27.7/LICENSE
(MPL-2.0). Licencja CPython 3.12.7 i zawarte w niej informacje o zależnościach:
PYTHON-LICENSE z https://github.com/python/cpython/blob/v3.12.7/LICENSE.
Sumy plików: SHA256SUMS.
Dokumentacja integracji: docs/ARTICLE_CODE_EXAMPLES.md.

CORS * w .htaccess obejmuje wyłącznie te publiczne, niezmienne pliki.
Nie należy przenosić tej reguły do katalogu głównego ani endpointów aplikacji.

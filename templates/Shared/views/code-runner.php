<?php
/** @var string $runtimeBase Trusted, validated runtime asset URL. */
/** @var string $nonce */
$workerSource = file_get_contents(__DIR__ . '/../../shared/assets/js/code-runner-worker.js');
?>
<!doctype html><html lang="pl"><meta charset="utf-8"><title>Środowisko Python</title>
<script nonce="<?= htmlspecialchars($nonce, ENT_QUOTES, 'UTF-8') ?>">
'use strict';
(() => {
    const runtimeBase = <?= json_encode($runtimeBase, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const workerSource = <?= json_encode($workerSource, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    let connected = false;
    window.addEventListener('message', event => {
        if (connected || event.source !== parent || event.data !== 'python-connect' || event.ports.length !== 1) return;
        connected = true;
        const port = event.ports[0];
        const url = URL.createObjectURL(new Blob([workerSource], { type: 'text/javascript' }));
        let worker;
        try { worker = new Worker(url); }
        catch (error) { port.postMessage({ type: 'error', text: String(error) }); URL.revokeObjectURL(url); return; }
        worker.onmessage = message => { URL.revokeObjectURL(url); port.postMessage(message.data); };
        worker.onerror = () => { URL.revokeObjectURL(url); port.postMessage({ type: 'error', text: 'Nie udało się uruchomić środowiska Python. Odśwież stronę i spróbuj ponownie.' }); };
        window.addEventListener('pagehide', () => { worker.terminate(); URL.revokeObjectURL(url); });
        port.onmessage = message => {
            const data = message.data;
            if (!data || typeof data.code !== 'string' || data.code.length > 50000 || typeof data.stdin !== 'string' || data.stdin.length > 10000) return;
            worker.postMessage({ code: data.code, stdin: data.stdin, runtimeBase });
        };
        port.start();
        port.postMessage({ type: 'connected' });
    });
})();
</script></html>

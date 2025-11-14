(function () {
    const cfg = window.ACAFS_LINKER_CONFIG || {};
    const log = (...a) => console.debug('[ACAFS] linker:', ...a);

    function looksLikeLinkableUrl(text) {
        if (!text) return false;
        try { new URL(text); return true; } catch(e) {}
        // bare paths we know about
        return cfg.pathHints?.some(h => text.includes(h));
    }

    function isAllowedHost(u) {
        try {
            const host = new URL(u).host;
            return cfg.allowedHosts?.length ? cfg.allowedHosts.includes(host) : true;
        } catch(e) {
            return true;
        }
    }

    function linkifyCell(td) {
        const p = td.querySelector('p, span, div') || td;
        if (!p) return false;

        // Join lines like CF7 multi-upload (newline separated)
        const raw = p.textContent.trim();
        if (!raw) return false;

        const lines = raw.split(/\r?\n/).map(s => s.trim()).filter(Boolean);
        let changed = 0;

        // Only replace text nodes; avoid double-wrapping
        p.innerHTML = '';
        lines.forEach((line, idx) => {
            const makeA = (href) => {
                const a = document.createElement('a');
                a.href = href;
                a.target = '_blank';
                a.rel = 'noopener';
                a.textContent = href.split('/').pop() || href;
                a.className = 'acafs-file-link';
                return a;
            };

            if (looksLikeLinkableUrl(line)) {
                let href = line;
                // If it's a bare path, prepend origin
                if (!/^https?:\/\//i.test(href) && cfg.allowedHosts?.length) {
                    href = window.location.protocol + '//' + cfg.allowedHosts[0] + (href.startsWith('/') ? href : '/' + href);
                }
                if (isAllowedHost(href)) {
                    p.appendChild(makeA(href));
                    changed++;
                } else {
                    p.appendChild(document.createTextNode(line));
                }
            } else {
                p.appendChild(document.createTextNode(line));
            }

            if (idx < lines.length - 1) {
                p.appendChild(document.createElement('br'));
            }
        });

        return changed > 0;
    }

    function processTable() {
        // The exact table we saw in your markup:
        // <div id="inboundmetadiv"> ... <table class="widefat message-fields striped">
        const table = document.querySelector('#inboundmetadiv table.widefat.message-fields');
        if (!table) { log('meta table not found'); return 0; }

        const rows = table.querySelectorAll('tbody > tr');
        let changed = 0;

        rows.forEach(tr => {
            const keyCell = tr.querySelector('td.field-title');
            const valCell = tr.querySelector('td.field-value');
            if (!keyCell || !valCell) return;

            const key = keyCell.textContent.trim();
            if (cfg.skipKeys?.includes(key)) return;

            // Heuristic: only linkify if content looks like a URL/path
            const txt = valCell.textContent.trim();
            if (!txt) return;
            if (!looksLikeLinkableUrl(txt) && !txt.includes('/acafs-cf7/')) return;

            if (linkifyCell(valCell)) changed++;
        });

        log('processed, cells changed =', changed);
        return changed;
    }

    function run() {
        log('start', cfg);
        const changed = processTable();
        // Fallback: if 0 on DOMContentLoaded, try again on load
        if (changed === 0) {
            window.addEventListener('load', () => {
                log('window.load re-run');
                processTable();
            }, { once: true });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', run);
    } else {
        run();
    }
})();

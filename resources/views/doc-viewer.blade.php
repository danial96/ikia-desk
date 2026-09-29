<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Document Preview — IKIA Desk</title>
<script src="https://cdnjs.cloudflare.com/ajax/libs/mammoth/1.6.0/mammoth.browser.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<style>
    * { box-sizing: border-box; }
    html, body { margin: 0; height: 100%; background: #eef1f4; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
    #bar {
        position: sticky; top: 0; z-index: 5; height: 56px; background: #fff; border-bottom: 1px solid #e2e8f0;
        display: flex; align-items: center; gap: 12px; padding: 0 18px; box-shadow: 0 1px 3px rgba(0,0,0,.05);
    }
    #bar .icon { font-size: 20px; color: #0ea5e9; flex-shrink: 0; }
    #bar .name { font-size: 14px; font-weight: 600; color: #1e293b; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; flex: 1; min-width: 0; }
    #bar a.dl {
        display: flex; align-items: center; gap: 7px; background: #0ea5e9; color: #fff; text-decoration: none;
        font-size: 13px; font-weight: 600; padding: 8px 14px; border-radius: 8px; flex-shrink: 0; transition: background .15s;
    }
    #bar a.dl:hover { background: #0284c7; }
    #wrap { max-width: 900px; margin: 24px auto 60px; padding: 0 16px; }
    #state { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 14px; padding: 90px 20px; color: #64748b; font-size: 14px; text-align: center; }
    .spinner { width: 34px; height: 34px; border: 3px solid #cbd5e1; border-top-color: #0ea5e9; border-radius: 50%; animation: spin .8s linear infinite; }
    @keyframes spin { to { transform: rotate(360deg); } }
    #state a { color: #0ea5e9; font-weight: 600; text-decoration: none; }
    #doc-body {
        display: none; background: #fff; border-radius: 10px; box-shadow: 0 1px 4px rgba(0,0,0,.08); padding: 48px 56px;
        line-height: 1.6; color: #1e293b; overflow-wrap: break-word;
    }
    #doc-body img { max-width: 100%; height: auto; }
    #doc-body table { border-collapse: collapse; }
    #doc-body table td, #doc-body table th { border: 1px solid #cbd5e1; padding: 4px 8px; }
    #sheet-tabs { display: none; gap: 6px; margin-bottom: 14px; flex-wrap: wrap; }
    #sheet-tabs button {
        background: #fff; border: 1px solid #e2e8f0; border-radius: 7px; padding: 7px 14px; font-size: 12.5px;
        font-weight: 600; color: #475569; cursor: pointer;
    }
    #sheet-tabs button.active { background: #0ea5e9; border-color: #0ea5e9; color: #fff; }
    #sheet-wrap { display: none; background: #fff; border-radius: 10px; box-shadow: 0 1px 4px rgba(0,0,0,.08); padding: 20px; overflow: auto; }
    #sheet-wrap table { border-collapse: collapse; font-size: 12.5px; }
    #sheet-wrap table td, #sheet-wrap table th { border: 1px solid #e2e8f0; padding: 5px 9px; white-space: nowrap; }
    #sheet-wrap table tr:first-child { background: #f1f5f9; font-weight: 600; }
</style>
</head>
<body>
    <div id="bar">
        <i class="icon" id="bar-icon">📄</i>
        <div class="name" id="bar-name">Loading…</div>
        <a class="dl" id="bar-download" href="#"><span>⬇</span> Download</a>
    </div>
    <div id="wrap">
        <div id="state">
            <div class="spinner"></div>
            <div>Loading preview…</div>
        </div>
        <div id="sheet-tabs"></div>
        <div id="sheet-wrap"></div>
        <div id="doc-body"></div>
    </div>

<script>
(function () {
    const params = new URLSearchParams(location.search);
    const src = params.get('src');
    const stateEl = document.getElementById('state');
    const barName = document.getElementById('bar-name');
    const barDownload = document.getElementById('bar-download');
    const barIcon = document.getElementById('bar-icon');

    function fail(msg) {
        stateEl.style.display = 'flex';
        stateEl.innerHTML = '<div style="font-size:32px;">⚠️</div><div>' + msg + '</div>' +
            (src ? '<a href="' + src + '">Download the file instead</a>' : '');
    }

    if (!src) { fail('No file specified.'); return; }

    // Only ever preview our own uploaded files — never let this page be pointed at an
    // arbitrary third-party URL and used as an open fetch proxy under our origin.
    let url;
    try { url = new URL(src, location.origin); } catch (e) { fail('Invalid file link.'); return; }
    if (url.origin !== location.origin || !/^\/uploads\//.test(url.pathname)) { fail('Invalid file link.'); return; }

    const name = url.searchParams.get('name') || url.pathname.split('/').pop();
    const ext = (name.split('.').pop() || '').toLowerCase();
    barName.textContent = name;
    barDownload.href = url.href;
    document.title = name + ' — IKIA Desk';
    barIcon.textContent = ext === 'xlsx' || ext === 'xls' ? '📊' : '📝';

    fetch(url.href, { credentials: 'same-origin' })
        .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.arrayBuffer(); })
        .then(buf => {
            if (ext === 'docx') return renderDocx(buf);
            if (ext === 'xlsx' || ext === 'xls') return renderSheet(buf);
            throw new Error('Unsupported preview type: ' + ext);
        })
        .catch(err => fail('Could not preview this file (' + err.message + ').'));

    function renderDocx(buf) {
        if (!window.mammoth) throw new Error('viewer failed to load');
        return mammoth.convertToHtml({ arrayBuffer: buf }).then(result => {
            stateEl.style.display = 'none';
            const body = document.getElementById('doc-body');
            body.innerHTML = result.value || '<p style="color:#94a3b8;">(empty document)</p>';
            body.style.display = 'block';
        });
    }

    function renderSheet(buf) {
        if (!window.XLSX) throw new Error('viewer failed to load');
        const wb = XLSX.read(new Uint8Array(buf), { type: 'array' });
        const sheetNames = wb.SheetNames;
        if (!sheetNames.length) throw new Error('no sheets found');
        stateEl.style.display = 'none';
        const tabsEl = document.getElementById('sheet-tabs');
        const wrapEl = document.getElementById('sheet-wrap');
        wrapEl.style.display = 'block';

        function show(i) {
            wrapEl.innerHTML = XLSX.utils.sheet_to_html(wb.Sheets[sheetNames[i]]);
            Array.from(tabsEl.children).forEach((b, bi) => b.classList.toggle('active', bi === i));
        }
        if (sheetNames.length > 1) {
            tabsEl.style.display = 'flex';
            sheetNames.forEach((n, i) => {
                const b = document.createElement('button');
                b.textContent = n;
                b.onclick = () => show(i);
                tabsEl.appendChild(b);
            });
        }
        show(0);
    }
})();
</script>
</body>
</html>

// ==UserScript==
// @name         LFS scraper
// @namespace    lfs
// @version      1.1.0
// @description  Grabs Last Frame Society ratings from Letterboxd and POSTs them to the local dev server.
// @match        https://letterboxd.com/*
// @grant        GM_xmlhttpRequest
// @connect      127.0.0.1
// @run-at       document-idle
// ==/UserScript==

(() => {
    'use strict';

    const INGEST = 'http://127.0.0.1:8000/api/ingest';
    const LIST_PATH = '/wollkey/list/last-frame-society-1/';

    // Club film slugs, in list order (mirrors data/list.html).
    const SLUGS = [
        'citizen-kane', 'mulholland-drive', 'vertigo', 'jeanne-dielman-23-quai-du-commerce-1080-bruxelles',
        'tokyo-story', 'in-the-mood-for-love', 'the-rules-of-the-game', 'my-friend-ivan-lapshin',
        'spirited-away', 'man-with-a-movie-camera', 'adams-apples', 'amores-perros', 'heartbeats',
        'city-of-god', 'drunken-master', 'song-of-the-sea', 'dallas-buyers-club', 'the-number-23',
        'dogville', 'nymphomaniac-volume-i', 'earth-1930', 'moonlight-2016', 'seven-samurai', 'yi-yi',
        '2001-a-space-odyssey', 'some-like-it-hot', 'barry-lyndon', 'andrei-rublev', 'pink-flamingos',
        'blade-runner', 'im-a-cyborg-but-thats-ok', 'das-boot', 'chungking-express', 'the-godfather',
        'star-wars', 'apocalypse-now', 'its-a-wonderful-life', 'brother-1997', 'the-color-of-pomegranates',
        'there-will-be-blood', 'everybody-hates-johan', 'the-cranes-are-flying', 'incendies', 'la-dolce-vita',
        'rashomon', 'chinatown', 'the-matrix', 'mirror', 'the-seventh-seal', 'persona', '12-angry-men',
        'being-john-malkovich', 'taxi-driver', 'the-good-the-bad-and-the-ugly', 'orlando', 'vagabond',
        'stalker', 'drive-2011', 'goodfellas',
    ];

    // Club members; the same endpoint serves everyone, incl. wollkey.
    const USERNAMES = [
        'wollkey', 'lenka_penka', 'christallisme', 'psy667', 'al1vka', 'atomic_rage',
        'nickbiryukov', 'vans_von_trier', 'vika', 'alinafrolova',
    ];

    const sleep = ms => new Promise(r => setTimeout(r, ms));

    // GM_xmlhttpRequest bypasses CORS / mixed-content / Private Network Access.
    const post = (type, name, html) => new Promise((resolve, reject) => {
        GM_xmlhttpRequest({
            method: 'POST',
            url: INGEST,
            headers: {'Content-Type': 'application/json'},
            data: JSON.stringify({type, name, html}),
            onload: res => res.status >= 200 && res.status < 300
                ? resolve()
                : reject(new Error(`ingest ${res.status}`)),
            onerror: () => reject(new Error('ingest failed (is `make up` running?)')),
        });
    });

    async function run(kind, names, urlFor, type, setStatus) {
        const failed = [];

        for (const [i, name] of names.entries()) {
            setStatus(`${kind}: ${i + 1}/${names.length} ${name}`);
            try {
                const res = await fetch(urlFor(name), {credentials: 'include'});

                if (res.status === 429 || res.status === 403) {
                    setStatus(`${kind}: остановлено на ${name} (${res.status})`);
                    return;
                }
                if (!res.ok) {
                    failed.push(name);
                    continue;
                }

                await post(type, name, await res.text());
            } catch (e) {
                failed.push(name);
                console.error('[LFS]', name, e);
            }

            await sleep(2000 + Math.random() * 1500);
        }

        setStatus(`${kind}: готово${failed.length ? `, не удалось: ${failed.join(', ')}` : ''}`);
    }

    // Scroll to the bottom so lazy posters load their srcset.
    async function loadLazyPosters() {
        let last = -1;
        for (let i = 0; i < 60 && document.body.scrollHeight !== last; i++) {
            last = document.body.scrollHeight;
            window.scrollTo(0, last);
            await sleep(400);
        }
        window.scrollTo(0, 0);
        await sleep(300);
    }

    // List needs the rendered DOM (lazy posters), so capture the page, don't fetch.
    async function scrapeList(setStatus) {
        if (location.pathname !== LIST_PATH) {
            setStatus(`Открой страницу списка (${LIST_PATH}) и нажми снова`);
            return;
        }

        setStatus('Список: гружу постеры…');
        await loadLazyPosters();

        setStatus('Список: сохраняю…');
        try {
            await post('list', '', document.documentElement.outerHTML);
            setStatus('Список: готово');
        } catch (e) {
            console.error('[LFS]', e);
            setStatus(`Список: ошибка — ${e.message}`);
        }
    }

    function panel() {
        const box = document.createElement('div');
        Object.assign(box.style, {
            position: 'fixed', bottom: '16px', right: '16px', zIndex: '99999',
            background: '#14181c', color: '#9ab', font: '13px/1.4 sans-serif',
            padding: '12px', borderRadius: '8px', boxShadow: '0 2px 12px rgba(0,0,0,.5)',
            display: 'flex', flexDirection: 'column', gap: '8px', width: '220px',
        });

        const title = document.createElement('strong');
        title.textContent = 'LFS scraper';
        title.style.color = '#fff';

        const status = document.createElement('div');
        status.textContent = 'готов';
        status.style.minHeight = '1.4em';

        const buttons = document.createElement('div');
        Object.assign(buttons.style, {display: 'flex', flexDirection: 'column', gap: '6px'});

        const setStatus = text => { status.textContent = text; };

        const make = (label, task) => {
            const btn = document.createElement('button');
            btn.textContent = label;
            Object.assign(btn.style, {
                cursor: 'pointer', padding: '6px 8px', borderRadius: '6px',
                border: 'none', background: '#00c030', color: '#fff', fontWeight: '600',
            });
            btn.addEventListener('click', async () => {
                buttons.querySelectorAll('button').forEach(b => (b.disabled = true));
                try {
                    await task(setStatus);
                } finally {
                    buttons.querySelectorAll('button').forEach(b => (b.disabled = false));
                }
            });
            return btn;
        };

        buttons.append(
            make('Список', scrapeList),
            make('Фильмы', s => run('Фильмы', SLUGS,
                slug => `https://letterboxd.com/wollkey/friends/film/${slug}/`, 'friends', s)),
            make('Активность', s => run('Активность', USERNAMES,
                user => `https://letterboxd.com/ajax/activity-pagination/${user}/`, 'activity', s)),
        );

        box.append(title, buttons, status);
        document.body.append(box);
    }

    panel();
})();

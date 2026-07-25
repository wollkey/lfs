// ==UserScript==
// @name         LFS scraper
// @namespace    lfs
// @version      1.2.0
// @description  Grabs Last Frame Society ratings from Letterboxd and POSTs them to the local dev server.
// @match        https://letterboxd.com/*
// @grant        GM_xmlhttpRequest
// @connect      127.0.0.1
// @run-at       document-idle
// ==/UserScript==

(() => {
    'use strict';

    const API = 'http://127.0.0.1:8000/api';
    const LIST_PATH = '/wollkey/list/last-frame-society-1/';

    const sleep = ms => new Promise(r => setTimeout(r, ms));

    // GM_xmlhttpRequest bypasses CORS / mixed-content / Private Network Access.
    const request = (method, url, body) => new Promise((resolve, reject) => {
        GM_xmlhttpRequest({
            method,
            url,
            headers: body ? {'Content-Type': 'application/json'} : {},
            data: body,
            onload: res => res.status >= 200 && res.status < 300
                ? resolve(res.responseText)
                : reject(new Error(`${method} ${url} → ${res.status}`)),
            onerror: () => reject(new Error('local server unreachable (make up?)')),
        });
    });

    const getJson = async url => JSON.parse(await request('GET', url));
    const post = (type, name, html) => request('POST', `${API}/ingest`, JSON.stringify({type, name, html}));

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

    async function scrapeFilms(setStatus) {
        setStatus('Фильмы: получаю список…');
        const {films} = await getJson(`${API}/films`);
        await run('Фильмы', films.map(f => f.slug),
            slug => `https://letterboxd.com/wollkey/friends/film/${slug}/`, 'friends', setStatus);
    }

    async function scrapeActivity(setStatus) {
        setStatus('Активность: получаю участников…');
        const {members} = await getJson(`${API}/members`);
        const users = members.filter(m => m.status === 'active').map(m => m.username);
        await run('Активность', users,
            user => `https://letterboxd.com/ajax/activity-pagination/${user}/`, 'activity', setStatus);
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
        await post('list', '', document.documentElement.outerHTML);
        setStatus('Список: готово');
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
                } catch (e) {
                    console.error('[LFS]', e);
                    setStatus(`ошибка: ${e.message}`);
                } finally {
                    buttons.querySelectorAll('button').forEach(b => (b.disabled = false));
                }
            });
            return btn;
        };

        buttons.append(
            make('Список', scrapeList),
            make('Фильмы', scrapeFilms),
            make('Активность', scrapeActivity),
        );

        box.append(title, buttons, status);
        document.body.append(box);
    }

    panel();
})();

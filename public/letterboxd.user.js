// ==UserScript==
// @name         LFS scraper
// @namespace    lfs
// @version      2.0.0
// @description  Grabs Last Frame Society ratings from Letterboxd and POSTs them to the local dev server.
// @match        https://letterboxd.com/*
// @grant        GM_xmlhttpRequest
// @connect      127.0.0.1
// @run-at       document-idle
// ==/UserScript==

(() => {
    'use strict';

    const API = 'http://127.0.0.1:8000/api';
    const OWNER = 'wollkey';
    const LIST_PATH = `/${OWNER}/list/last-frame-society-1/`;
    const LIST_URL = `https://letterboxd.com${LIST_PATH}`;
    const ITEM = 'ul.poster-list li.posteritem [data-item-slug]';

    const sleep = ms => new Promise(r => setTimeout(r, ms));

    // Letterboxd throttles hard; stop the whole run rather than hammer it.
    class RateLimited extends Error {}

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
            onerror: () => reject(new Error('локальный сервер недоступен (make up?)')),
        });
    });

    const getJson = async url => JSON.parse(await request('GET', url));
    const post = (type, name, html) => request('POST', `${API}/ingest`, JSON.stringify({type, name, html}));

    async function fetchPage(url) {
        const res = await fetch(url, {credentials: 'include'});
        if (res.status === 429 || res.status === 403) throw new RateLimited(String(res.status));
        if (!res.ok) throw new Error(`${url} → ${res.status}`);

        return res.text();
    }

    const listedSlugs = html => [...new DOMParser()
        .parseFromString(html, 'text/html')
        .querySelectorAll(ITEM)]
        .map(el => el.dataset.itemSlug)
        .filter(Boolean);

    const friendsUrl = slug => `https://letterboxd.com/${OWNER}/friends/film/${slug}/`;
    const activityUrl = user => `https://letterboxd.com/ajax/activity-pagination/${user}/`;

    const grab = (url, type, name) => async () => post(type, name, await fetchPage(url));

    /**
     * Runs the plan one step at a time, reporting progress; a rate limit aborts
     * the rest, anything else is collected and reported at the end.
     */
    async function execute(ui, steps) {
        const failed = [];

        for (const [i, step] of steps.entries()) {
            ui.progress(i, steps.length, step.label);
            try {
                await step.run();
            } catch (e) {
                if (e instanceof RateLimited) {
                    ui.progress(i, steps.length);
                    ui.status(`остановлено на «${step.label}» (${e.message}) — попробуй позже`);

                    return;
                }
                failed.push(step.label);
                console.error('[LFS]', step.label, e);
            }

            if (i < steps.length - 1) await sleep(2000 + Math.random() * 1500);
        }

        ui.progress(steps.length, steps.length);
        ui.status(failed.length ? `готово, не удалось: ${failed.join(', ')}` : 'готово');
    }

    /**
     * The weekly update, from any Letterboxd page: the list, every film in it the
     * database does not know yet, and the activity of every active member.
     */
    async function weeklyUpdate(ui) {
        ui.status('читаю список…');
        const listHtml = await fetchPage(LIST_URL);
        const listed = listedSlugs(listHtml);

        if (listed.length === 0) {
            ui.status(`в списке 0 фильмов — открой ${LIST_PATH} и нажми «Список»`);

            return;
        }

        const {films} = await getJson(`${API}/films`);
        const known = new Set(films.map(f => f.slug));
        const fresh = listed.filter(slug => !known.has(slug));

        const {members} = await getJson(`${API}/members`);
        const users = members.filter(m => m.status === 'active').map(m => m.username);

        ui.status(`в списке ${listed.length}, новых ${fresh.length}, участников ${users.length}`);
        await sleep(1500);

        await execute(ui, [
            {label: 'список', run: () => post('list', '', listHtml)},
            ...fresh.map(slug => ({label: slug, run: grab(friendsUrl(slug), 'friends', slug)})),
            ...users.map(user => ({label: `@${user}`, run: grab(activityUrl(user), 'activity', user)})),
        ]);
    }

    async function scrapeFilms(ui) {
        ui.status('получаю список фильмов…');
        const {films} = await getJson(`${API}/films`);

        await execute(ui, films.map(f => ({label: f.slug, run: grab(friendsUrl(f.slug), 'friends', f.slug)})));
    }

    const filmSlug = () =>
        location.pathname.match(/^\/film\/([^/]+)\//)?.[1]
        ?? location.pathname.match(/\/friends\/film\/([^/]+)\//)?.[1]
        ?? null;

    async function scrapeOneFilm(ui) {
        const slug = filmSlug() ?? prompt('Slug фильма?')?.trim();
        if (!slug) {
            ui.status('открой страницу /film/<slug>/ и нажми снова');

            return;
        }

        await execute(ui, [{label: slug, run: grab(friendsUrl(slug), 'friends', slug)}]);
    }

    async function scrapeActivity(ui) {
        ui.status('получаю участников…');
        const {members} = await getJson(`${API}/members`);
        const users = members.filter(m => m.status === 'active').map(m => m.username);

        await execute(ui, users.map(user => ({label: `@${user}`, run: grab(activityUrl(user), 'activity', user)})));
    }

    // Titles and own ratings only — posters come from each film's public page,
    // so there is nothing lazy left to scroll into view.
    async function scrapeList(ui) {
        if (location.pathname !== LIST_PATH) {
            ui.status(`открой страницу списка (${LIST_PATH}) и нажми снова`);

            return;
        }

        ui.status('сохраняю список…');
        await post('list', '', document.documentElement.outerHTML);
        ui.status('готово');
    }

    function panel() {
        const box = document.createElement('div');
        Object.assign(box.style, {
            position: 'fixed', bottom: '16px', right: '16px', zIndex: '99999',
            background: '#14181c', color: '#9ab', font: '13px/1.4 sans-serif',
            padding: '12px', borderRadius: '8px', boxShadow: '0 2px 12px rgba(0,0,0,.5)',
            display: 'flex', flexDirection: 'column', gap: '8px', width: '240px',
        });

        const title = document.createElement('strong');
        title.textContent = 'LFS scraper';
        title.style.color = '#fff';

        const track = document.createElement('div');
        Object.assign(track.style, {
            height: '6px', borderRadius: '3px', background: '#2c3440', overflow: 'hidden',
        });

        const fill = document.createElement('div');
        Object.assign(fill.style, {
            height: '100%', width: '0%', background: '#00c030', transition: 'width .25s ease',
        });
        track.append(fill);

        const status = document.createElement('div');
        status.textContent = 'готов';
        Object.assign(status.style, {minHeight: '2.8em', overflowWrap: 'anywhere'});

        const buttons = document.createElement('div');
        Object.assign(buttons.style, {display: 'flex', flexDirection: 'column', gap: '6px'});

        const ui = {
            status: text => { status.textContent = text; },
            progress: (done, total, label) => {
                fill.style.width = `${total === 0 ? 0 : Math.round((done / total) * 100)}%`;
                if (label !== undefined) status.textContent = `${done + 1}/${total} · ${label}`;
            },
        };

        const make = (label, task, primary = false) => {
            const btn = document.createElement('button');
            btn.textContent = label;
            Object.assign(btn.style, {
                cursor: 'pointer', padding: primary ? '8px' : '6px 8px', borderRadius: '6px',
                border: 'none', background: primary ? '#00c030' : '#2c3440',
                color: '#fff', fontWeight: primary ? '700' : '600',
            });
            btn.addEventListener('click', () => start(task));

            return btn;
        };

        async function start(task) {
            buttons.querySelectorAll('button').forEach(b => (b.disabled = true));
            fill.style.width = '0%';
            try {
                await task(ui);
            } catch (e) {
                console.error('[LFS]', e);
                ui.status(`ошибка: ${e.message}`);
            } finally {
                buttons.querySelectorAll('button').forEach(b => (b.disabled = false));
            }
        }

        buttons.append(
            make('Обновить неделю', weeklyUpdate, true),
            make('Фильм', scrapeOneFilm),
            make('Список', scrapeList),
            make('Фильмы', scrapeFilms),
            make('Активность', scrapeActivity),
        );

        box.append(title, track, buttons, status);
        document.body.append(box);
    }

    panel();
})();

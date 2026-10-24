import { render as overview }   from './pages/overview.js';
import { render as films }      from './pages/films.js';
import { render as filmDetail } from './pages/film.js';
import { render as rounds }     from './pages/rounds.js';
import { render as members }    from './pages/members.js';
import { esc }                  from './helpers.js';

const routes = [
    { path: '/',             render: overview },
    { path: '/films',        render: films },
    { path: '/films/{slug}', render: filmDetail },
    { path: '/rounds',       render: rounds },
    { path: '/members',      render: members },
];

function matchRoute(pathname) {
    for (const route of routes) {
        const names = [];
        const pattern = route.path.replace(/\{(\w+)\}/g, (_, name) => {
            names.push(name);
            return '([^/]+)';
        });
        const found = pathname.match(new RegExp(`^${pattern}$`));
        if (found === null) continue;

        const params = {};
        names.forEach((name, i) => { params[name] = decodeURIComponent(found[i + 1]); });
        return { render: route.render, params };
    }
    return null;
}

const view = document.querySelector('#view');

function highlightNav(path) {
    document.querySelectorAll('.nav__link').forEach((link) => {
        const linkPath = new URL(link.href).pathname;
        const active = linkPath === '/' ? path === '/' : path.startsWith(linkPath);
        link.classList.toggle('nav__link--active', active);
    });
}

function renderCurrent() {
    const path = location.pathname;
    const matched = matchRoute(path);
    highlightNav(path);

    if (matched === null) {
        view.innerHTML = `
        <section class="film-break">
          <div class="film-break__frame"></div>
          <h1 class="film-break__title">Плёнка оборвалась</h1>
          <p class="film-break__text">Киномеханик уже чинит. А пока — <a href="/">на главную</a>.</p>
          <p class="film-break__path">${esc(path)}</p>
        </section>`;
        return Promise.resolve();
    }

    const timer = setTimeout(() => view.classList.add('view--loading'), 200);

    return Promise.resolve(matched.render(view, matched.params))
        .catch((err) => {
            console.error(err);
            view.innerHTML = `<p class="error">Не удалось загрузить данные</p>`;
        })
        .finally(() => {
            clearTimeout(timer);
            view.classList.remove('view--loading');
        });
}

const reducedMotion = matchMedia('(prefers-reduced-motion: reduce)');

// The clicked film's poster carries over into the film page header.
function transition(update, poster = null) {
    if (!document.startViewTransition || reducedMotion.matches) {
        update();
        return;
    }
    if (poster !== null) poster.style.viewTransitionName = 'poster';
    document.startViewTransition(update);
}

function navigate(path, poster) {
    if (path === location.pathname + location.hash) return;
    history.pushState(null, '', path);
    transition(() => {
        window.scrollTo(0, 0);
        return renderCurrent();
    }, poster);
}

document.addEventListener('click', (event) => {
    const link = event.target.closest('a');
    if (link === null) return;

    const url = new URL(link.href);
    if (url.origin !== location.origin) return;
    if (link.target === '_blank') return;
    if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

    event.preventDefault();
    const poster = url.pathname.startsWith('/films/')
        ? link.closest('.film, .card__body')?.querySelector('.poster') ?? null
        : null;
    navigate(url.pathname + url.hash, poster);
});

const pageTitle = document.title;
let titleTimer = null;

document.addEventListener('visibilitychange', () => {
    clearTimeout(titleTimer);
    if (document.hidden) {
        document.title = `Антракт · ${pageTitle}`;
        return;
    }
    document.title = `Сеанс продолжается · ${pageTitle}`;
    titleTimer = setTimeout(() => { document.title = pageTitle; }, 2000);
});

document.addEventListener('error', (event) => {
    const img = event.target;
    if (img.tagName !== 'IMG' || !img.classList.contains('poster')) return;
    if (img.dataset.fallback) return;                  // guard: don't loop if the placeholder is missing too
    img.dataset.fallback = '1';
    img.src = '/posters/_placeholder.svg';
}, true);

window.addEventListener('popstate', () => transition(renderCurrent));

renderCurrent();

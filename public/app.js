import { render as overview }   from './pages/overview.js';
import { render as films }      from './pages/films.js';
import { render as filmDetail } from './pages/film.js';
import { render as rounds }     from './pages/rounds.js';
import { render as members }    from './pages/members.js';

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
        view.innerHTML = `<p class="error">Страница не найдена: ${path}</p>`;
        return;
    }

    const timer = setTimeout(() => view.classList.add('view--loading'), 200);

    Promise.resolve(matched.render(view, matched.params))
        .catch((err) => {
            console.error(err);
            view.innerHTML = `<p class="error">Не удалось загрузить данные</p>`;
        })
        .finally(() => {
            clearTimeout(timer);
            view.classList.remove('view--loading');
        });
}

function navigate(path) {
    if (path === location.pathname) return;
    history.pushState(null, '', path);
    renderCurrent();
}

document.addEventListener('click', (event) => {
    const link = event.target.closest('a');
    if (link === null) return;

    const url = new URL(link.href);
    if (url.origin !== location.origin) return;
    if (link.target === '_blank') return;
    if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

    event.preventDefault();
    navigate(url.pathname);
});

window.addEventListener('popstate', renderCurrent);

renderCurrent();

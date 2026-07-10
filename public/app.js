import { render as overview }   from '/pages/overview.js';
import { render as films }      from '/pages/films.js';
import { render as filmDetail } from '/pages/film.js';
import { render as rounds }     from '/pages/rounds.js';
import { render as members }    from '/pages/members.js';

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
const path = location.pathname;
const matched = matchRoute(path);

document.querySelectorAll('.nav__link').forEach((link) => {
    const linkPath = new URL(link.href).pathname;
    const active = linkPath === '/' ? path === '/' : path.startsWith(linkPath);
    if (active) link.classList.add('nav__link--active');
});

if (matched === null) {
    view.innerHTML = `<p class="error">Page not found: ${path}</p>`;
} else {
    Promise.resolve(matched.render(view, matched.params)).catch((err) => {
        view.innerHTML = `<p class="error">Не загрузилось: ${err.message}</p>`;
    });
}
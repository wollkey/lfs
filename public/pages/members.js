import { letterboxdLink, plural, pluralWith, esc, filmUrl } from '../helpers.js';

let sortMode = 'default';
let members = null;
let picks = null;
let missed = null;
let currentRound = null;

const SORTS = {
    default: () => 0,
    watched: (a, b) => b.watched - a.watched,
    given:   (a, b) => (b.averageGiven ?? -1) - (a.averageGiven ?? -1),
};

const SORT_LABELS = {
    default: 'По очерёдности выбора',
    watched: 'По просмотрам',
    given: 'По средней оценке',
};

function summaryRow(member) {
    const avg = member.averageGiven === null ? '—' : member.averageGiven;
    const initial = esc(member.displayName.trim().charAt(0).toUpperCase());
    return `
      <div class="member__avatar" aria-hidden="true">${initial}</div>
      <div class="member__id">
        <span class="member__name">${esc(member.displayName)}</span>
        ${letterboxdLink(member.username)}
      </div>
      <div class="member__stats">
        <span class="stat-mini">
          <span class="stat-mini__num">${member.watched}</span>
          <span class="stat-mini__label">${plural(member.watched, ['просмотр', 'просмотра', 'просмотров'])}</span>
        </span>
        <span class="stat-mini stat-mini--accent">
          <span class="stat-mini__num">${avg}</span>
          <span class="stat-mini__label">средняя</span>
        </span>
      </div>`;
}

function picksSection(username) {
    const films = picks[username] ?? [];
    if (films.length === 0) return '';

    const rows = films.map((f) => `
      <li class="pick">
        <a class="pick__title" href="${filmUrl(f.slug)}">${esc(f.title)}</a>
        <span class="pick__avg">${f.average === null ? '—' : f.average}</span>
      </li>`).join('');

    return `
    <section class="member-block">
      <h3 class="member-block__title">Выбрал</h3>
      <ul class="pick-list">${rows}</ul>
    </section>`;
}

function missedSection(username) {
    const films = missed[username] ?? [];
    if (films.length === 0) {
        return `
        <section class="member-block">
          <h3 class="member-block__title">Не смотрел</h3>
          <p class="member-block__empty">Посмотрел всё — ни одного пропуска.</p>
        </section>`;
    }

    // Group by round; backend already sorted the films.
    const groups = new Map();
    for (const f of films) {
        if (!groups.has(f.round)) groups.set(f.round, []);
        groups.get(f.round).push(f);
    }

    const tiles = [...groups.entries()].map(([round, list]) => {
        const isCurrent = round === currentRound;
        const items = list
            .map((f) => `<li><a class="missed__link" href="${filmUrl(f.slug)}">${esc(f.title)}</a></li>`)
            .join('');
        return `
        <div class="missed-round ${isCurrent ? 'missed-round--current' : ''}">
          <div class="missed-round__head">
            <span class="missed-round__num">Круг ${round}</span>
            ${isCurrent ? `<span class="missed-round__badge">текущий</span>` : ''}
            <span class="missed-round__count">${pluralWith(list.length, ['фильм', 'фильма', 'фильмов'])}</span>
          </div>
          <ul class="missed-round__films">${items}</ul>
        </div>`;
    }).join('');

    return `
    <section class="member-block">
      <h3 class="member-block__title">Не смотрел</h3>
      ${tiles}
    </section>`;
}

function memberRow(member) {
    const summary = summaryRow(member);
    const body = member.status === 'active'
        ? picksSection(member.username) + missedSection(member.username)
        : picksSection(member.username);

    if (body === '') {
        return `<li><div class="member"><div class="member__row">${summary}</div></div></li>`;
    }

    return `
    <li>
      <details class="member">
        <summary class="member__row member__row--toggle">${summary}</summary>
        <div class="member__body">${body}</div>
      </details>
    </li>`;
}

function group(title, list) {
    if (list.length === 0) return '';
    const rows = list.map(memberRow).join('');
    return `
    <section class="member-group">
      <h2 class="member-group__title">${title}</h2>
      <ul class="member-list">${rows}</ul>
    </section>`;
}

function draw(root) {
    const sortWithin = (list) => sortMode === 'default' ? list : [...list].sort(SORTS[sortMode]);
    const active = sortWithin(members.filter((m) => m.status === 'active'));
    const former = sortWithin(members.filter((m) => m.status === 'former'));

    const sortButtons = Object.keys(SORTS).map((mode) => `
    <button class="sort-btn ${mode === sortMode ? 'sort-btn--active' : ''}" data-sort="${mode}">
      ${SORT_LABELS[mode]}
    </button>`).join('');

    root.innerHTML = `
    <div class="rounds-toolbar">
      <span class="rounds-toolbar__label">Сортировка</span>
      <div class="sort-group">${sortButtons}</div>
    </div>
    ${group('Участники', active)}
    ${group('Бывшие участники', former)}`;

    root.querySelectorAll('.sort-btn').forEach((btn) => {
        btn.addEventListener('click', () => {
            sortMode = btn.dataset.sort;
            draw(root);           // redraw from cache, no network
        });
    });
}

export async function render(root) {
    const response = await fetch('/api/members');
    if (!response.ok) throw new Error(`API статус ${response.status}`);
    const data = await response.json();
    members = data.members;
    picks = data.picks;
    missed = data.missed;
    currentRound = data.currentRound;
    draw(root);
}

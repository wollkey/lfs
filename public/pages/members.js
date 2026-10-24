import { letterboxdLink, plural, pluralWith, esc, filmUrl, roundUrl, spotlight } from '../helpers.js';

let sortMode = 'default';
let members = null;
let picks = null;
let missed = null;
let currentRound = null;
let tastes = null;

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

    // Average of the picks shown here, so the header matches the list below.
    const scored = films.filter((f) => f.average !== null);
    const avg = scored.length === 0
        ? null
        : Math.round(scored.reduce((sum, f) => sum + f.average, 0) / scored.length * 10) / 10;
    const title = avg === null
        ? 'Выбрал'
        : `Выбрал · средняя <span class="member-block__avg">${avg}</span>`;

    const rows = films.map((f) => `
      <li class="pick">
        <a class="pick__title" href="${filmUrl(f.slug)}">${esc(f.title)}</a>
        <span class="pick__avg">${f.average === null ? '—' : f.average}</span>
      </li>`).join('');

    return `
    <section class="member-block">
      <h3 class="member-block__title">${title}</h3>
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
            <a class="missed-round__num" href="${roundUrl(round)}">Круг ${round}</a>
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

function tasteSection(username) {
    const matches = tastes[username] ?? [];
    if (matches.length === 0) return '';

    const tied = (agreement) => matches.filter((m) => m.agreement === agreement);
    const row = (label, list, value) => `
      <li class="pick">
        <span class="pick__title">${label} - ${list.map((m) => esc(m.displayName)).join(', ')}</span>
        <span class="pick__avg">${value}%</span>
      </li>`;
    const closest = matches[0].agreement;
    const farthest = matches.at(-1).agreement;
    const rows = row('Близнец', tied(closest), closest)
        + (farthest === closest ? '' : row('Антипод', tied(farthest), farthest));

    return `
    <section class="member-block">
      <h3 class="member-block__title">Вкус</h3>
      <ul class="pick-list">${rows}</ul>
    </section>`;
}

function tasteMap(active) {
    const mapped = active.filter((m) => (tastes[m.username] ?? []).length > 0);
    if (mapped.length < 2) return '';

    const head = mapped.map((m) => `<th class="taste-map__col" scope="col">${esc(m.displayName)}</th>`).join('');
    const rows = mapped.map((row) => {
        const cells = mapped.map((col) => {
            if (col.username === row.username) return '<td class="taste-map__cell taste-map__cell--self"></td>';
            const match = tastes[row.username].find((m) => m.username === col.username);
            if (match === undefined) return '<td class="taste-map__cell">·</td>';
            const tip = `${row.displayName} и ${col.displayName}: совпали в ${match.agreement}% из ${pluralWith(match.shared, ['общего фильма', 'общих фильмов', 'общих фильмов'])}`;
            const strength = Math.min(Math.abs(match.agreement - 50) / 25, 1);
            return `<td class="taste-map__cell ${match.agreement < 50 ? 'taste-map__cell--opposite' : ''}"
                style="--strength: ${strength}" title="${esc(tip)}">${match.agreement}</td>`;
        }).join('');
        return `<tr><th class="taste-map__row" scope="row">${esc(row.displayName)}</th>${cells}</tr>`;
    }).join('');

    return `
    <section class="member-group">
      <h2 class="member-group__title">Совпадение вкусов <span class="hint" data-tip="Процент общих фильмов, которые обоим понравились или обоим нет - каждому по меркам своей средней оценки.">?</span></h2>
      <div class="taste-map">
        <table>
          <thead><tr><td></td>${head}</tr></thead>
          <tbody>${rows}</tbody>
        </table>
      </div>
    </section>`;
}

function memberRow(member) {
    const summary = summaryRow(member);
    const body = member.status === 'active'
        ? tasteSection(member.username) + picksSection(member.username) + missedSection(member.username)
        : picksSection(member.username);

    const key = esc(member.username);

    if (body === '') {
        return `<li><div class="member" data-member="${key}"><div class="member__row">${summary}</div></div></li>`;
    }

    return `
    <li>
      <details class="member" data-member="${key}">
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
    ${tasteMap(members.filter((m) => m.status === 'active'))}
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
    tastes = data.tastes;
    draw(root);

    const target = decodeURIComponent(location.hash.slice(1));
    const member = [...root.querySelectorAll('.member')].find((el) => el.dataset.member === target);
    if (member !== undefined) spotlight(member);
}

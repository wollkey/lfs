import { letterboxdLink, plural, esc } from '../helpers.js';

let sortMode = 'default';
let members = null;

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

function memberRow(member) {
    const avg = member.averageGiven === null ? '—' : member.averageGiven;
    const initial = esc(member.displayName.trim().charAt(0).toUpperCase());
    return `
    <li class="member">
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
      </div>
    </li>`;
}

function group(title, members) {
    if (members.length === 0) return '';
    const rows = members.map(memberRow).join('');
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
    members = (await response.json()).members;
    draw(root);
}

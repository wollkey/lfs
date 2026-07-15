import { letterboxdLink, pluralWith } from '../helpers.js';

let sortMode = 'default';

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
    return `
    <li class="member">
      <div class="member__id">
        <span class="member__name">${member.displayName}</span>
        ${letterboxdLink(member.username)}
      </div>
      <div class="member__stats">
        <span class="member__watched">${pluralWith(member.watched, ['просмотр', 'просмотра', 'просмотров'])}</span>
        <span class="member__avg-wrap">
          <span class="member__avg">${avg}</span>
          <span class="member__avg-label">средняя</span>
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

export async function render(root) {
    const response = await fetch('/api/members');
    if (!response.ok) throw new Error(`API статус ${response.status}`);
    const data = await response.json();

    const sortWithin = (list) => sortMode === 'default' ? list : [...list].sort(SORTS[sortMode]);
    const active = sortWithin(data.members.filter((m) => m.status === 'active'));
    const former = sortWithin(data.members.filter((m) => m.status === 'former'));

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
            render(root);
        });
    });
}

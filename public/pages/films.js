import {letterboxdLink, plural, posterImg, esc, filmUrl} from '../helpers.js';

let sortMode = 'position';
let films = null;

const SORTS = {
    position: (a, b) => (a.round ?? 0) - (b.round ?? 0) || (a.position ?? 0) - (b.position ?? 0),
    title:  (a, b) => a.title.localeCompare(b.title),
    best:   (a, b) => (b.average ?? -1) - (a.average ?? -1),
    worst:  (a, b) => (a.average ?? 11) - (b.average ?? 11),
    votes:  (a, b) => b.votes - a.votes,
    round:  (a, b) => (b.round ?? 0) - (a.round ?? 0),
};

const SORT_LABELS = {
    position: 'По порядку',
    title: 'По названию',
    best: 'Лучшие',
    worst: 'Худшие',
    votes: 'По оценкам',
    round: 'По кругам',
};

function filmRow(film) {
    const avg = film.average === null ? '—' : film.average;

    const parts = [];
    if (film.round !== null)    parts.push(`Круг ${film.round}`);
    if (film.pickedBy !== null) parts.push(`выбрал ${letterboxdLink(film.pickedBy)}`);
    const sub = parts.join(' · ');

    return `
    <li class="film">
      ${posterImg(film, 'poster--sm')}
      <div class="film__main">
        <a class="film__title" href="${filmUrl(film.slug)}">${esc(film.title)}</a>
        <span class="film__sub">${sub}</span>
      </div>
      <div class="film__stats">
        <span class="stat-mini">
          <span class="stat-mini__num">${film.votes}</span>
          <span class="stat-mini__label">${plural(film.votes, ['оценка', 'оценки', 'оценок'])}</span>
        </span>
        <span class="stat-mini stat-mini--accent">
          <span class="stat-mini__num">${avg}</span>
          <span class="stat-mini__label">средняя</span>
        </span>
      </div>
    </li>`;
}

function draw(root) {
    if (films.length === 0) {
        root.innerHTML = `<p class="placeholder">Пока нет фильмов.</p>`;
        return;
    }

    const sorted = [...films].sort(SORTS[sortMode]);

    const sortButtons = Object.keys(SORTS).map((mode) => `
    <button class="sort-btn ${mode === sortMode ? 'sort-btn--active' : ''}" data-sort="${mode}">
      ${SORT_LABELS[mode]}
    </button>`).join('');

    root.innerHTML = `
    <div class="rounds-toolbar">
      <span class="rounds-toolbar__label">Сортировка</span>
      <div class="sort-group">${sortButtons}</div>
    </div>
    <ul class="film-list">${sorted.map(filmRow).join('')}</ul>`;

    root.querySelectorAll('.sort-btn').forEach((btn) => {
        btn.addEventListener('click', () => {
            sortMode = btn.dataset.sort;
            draw(root);           // redraw from cache, no network
        });
    });
}

export async function render(root) {
    const response = await fetch('/api/films');
    if (!response.ok) throw new Error(`API статус ${response.status}`);
    films = (await response.json()).films;
    draw(root);
}

import { letterboxdLink, posterImg } from '../helpers.js';

function filmRow(film) {
    const avg = film.average === null ? '—' : film.average;

    const parts = [];
    if (film.round !== null)    parts.push(`Round ${film.round}`);
    if (film.pickedBy !== null) parts.push(`picked by ${letterboxdLink(film.pickedBy)}`);
    const sub = parts.join(' · ');

    return `
    <li class="film">
      ${posterImg(film, 'poster--sm')}
      <div class="film__main">
        <a class="film__title" href="/films/${film.slug}">${film.title}</a>
        <span class="film__sub">${sub}</span>
      </div>
      <div class="film__stats">
        <span class="film__votes">${film.votes} votes</span>
        <span class="film__avg">${avg}</span>
      </div>
    </li>`;
}

export async function render(root) {
    root.innerHTML = 'Loading…';
    const response = await fetch('/api/films');
    if (!response.ok) throw new Error(`API статус ${response.status}`);
    const data = await response.json();

    if (data.films.length === 0) {
        root.innerHTML = `<p class="placeholder">No films yet.</p>`;
        return;
    }

    const rows = data.films.map(filmRow).join('');
    root.innerHTML = `<ul class="film-list">${rows}</ul>`;
}

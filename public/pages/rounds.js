import { letterboxdLink, posterImg } from '../helpers.js';

// "2024-01-05" → "Jan 5, 2024". null пропускаем.
function formatDate(iso) {
    if (iso === null) return null;
    const [y, m, d] = iso.split('-').map(Number);
    const date = new Date(y, m - 1, d); // из частей → локальная дата, без сдвига UTC
    return date.toLocaleDateString('en-US', { day: 'numeric', month: 'short', year: 'numeric' });
}

function dateRange(round) {
    const from = formatDate(round.startedOn);
    const to = formatDate(round.endedOn);
    if (from === null && to === null) return '';
    if (to === null) return `${from} → present`;
    if (from === null) return `until ${to}`;
    return `${from} → ${to}`;
}

function roundFilmRow(film, winnerSlug) {
    const avg = film.average === null ? '—' : film.average;
    const isWinner = winnerSlug !== null && film.slug === winnerSlug;

    const parts = [];
    if (film.pickedBy !== null) parts.push(`picked by ${letterboxdLink(film.pickedBy)}`);
    if (isWinner) parts.push(`<span class="badge-winner">★ Winner</span>`);
    const sub = parts.join(' · ');

    return `
    <li class="film ${isWinner ? 'film--winner' : ''}">
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

function roundSection(round) {
    const winnerSlug = round.winner === null ? null : round.winner.slug;
    const dates = dateRange(round);
    const rows = round.films.map((f) => roundFilmRow(f, winnerSlug)).join('');

    return `
    <section class="round">
      <header class="round__head">
        <h2 class="round__num">Round ${round.number}</h2>
        ${dates === '' ? '' : `<span class="round__dates">${dates}</span>`}
      </header>
      <ul class="round__films">${rows}</ul>
    </section>`;
}

export async function render(root) {
    root.innerHTML = 'Loading…';
    const response = await fetch('/api/rounds');
    if (!response.ok) throw new Error(`API статус ${response.status}`);
    const data = await response.json();

    if (data.rounds.length === 0) {
        root.innerHTML = `<p class="placeholder">No rounds yet.</p>`;
        return;
    }

    root.innerHTML = data.rounds.map(roundSection).join('');
}

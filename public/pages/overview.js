import { posterImg } from '../helpers.js';

function num(value) {
    return value === null ? '—' : value;
}

function filmCard(label, film) {
    if (film === null) {
        return `
      <article class="card">
        <p class="card__label">${label}</p>
        <p class="card__empty">Not enough ratings yet</p>
      </article>`;
    }
    return `
    <article class="card">
      <p class="card__label">${label}</p>
      <div class="card__body">
        ${posterImg(film, 'poster--card')}
        <div class="card__info">
          <h3 class="card__title">${film.title}</h3>
          <p class="card__stats">
            <span class="card__avg">${film.average}</span>
            <span class="card__meta">${film.votes} votes · spread ${film.spread}</span>
          </p>
        </div>
      </div>
    </article>`;
}

export async function render(root) {
    root.innerHTML = 'Loading…';
    const response = await fetch('/api/overview');
    if (!response.ok) throw new Error(`API статус ${response.status}`);
    const data = await response.json();
    const t = data.totals;

    root.innerHTML = `
    <section class="totals">
      <div class="stat"><span class="stat__num">${t.films}</span><span class="stat__label">films</span></div>
      <div class="stat"><span class="stat__num">${t.ratings}</span><span class="stat__label">ratings</span></div>
      <div class="stat"><span class="stat__num">${t.members}</span><span class="stat__label">members</span></div>
      <div class="stat"><span class="stat__num">${num(t.currentRound)}</span><span class="stat__label">round</span></div>
    </section>

    <section class="cards">
      ${filmCard('Best film', data.bestFilm)}
      ${filmCard('Worst film', data.worstFilm)}
      ${filmCard('Most divisive', data.mostDivisive)}
    </section>`;
}

import { letterboxdLink, posterImg } from '../helpers.js';

function ratingRow(r) {
    return `
    <li class="rating">
      <div class="rating__who">
        <span class="rating__name">${r.displayName}</span>
        ${letterboxdLink(r.username)}
      </div>
      <div class="rating__bar"><span class="rating__fill" style="width: ${r.score * 10}%"></span></div>
      <span class="rating__score">${r.score}</span>
    </li>`;
}

export async function render(root, params) {
    root.innerHTML = 'Загрузка';
    const response = await fetch(`/api/films/${encodeURIComponent(params.slug)}`);

    if (response.status === 404) {
        root.innerHTML = `<p class="placeholder">Фильм не найден: ${params.slug}</p>`;
        return;
    }
    if (!response.ok) throw new Error(`API статус ${response.status}`);

    const film = await response.json();

    const parts = [];
    if (film.round !== null)    parts.push(`Раунд ${film.round}`);
    if (film.pickedBy !== null) parts.push(`выбрал ${letterboxdLink(film.pickedBy)}`);
    const sub = parts.join(' · ');

    const avg = film.average === null ? '—' : film.average;

    const ratings = [...film.ratings].sort((a, b) => b.score - a.score);
    const ratingsHtml = ratings.length === 0
        ? `<p class="placeholder">Пока нет оценок.</p>`
        : `<ul class="rating-list">${ratings.map(ratingRow).join('')}</ul>`;

    const notWatchedHtml = film.notWatched.length === 0 ? '' : `
    <section class="not-watched">
      <h2 class="section-title">Не смотрели</h2>
      <p class="not-watched__names">
        ${film.notWatched.map((m) => letterboxdLink(m.username, m.displayName)).join(', ')}
      </p>
    </section>`;

    root.innerHTML = `
    <p><a class="back-link" href="/films">← К списку фильмов</a></p>
    <article class="film-detail">
      <div class="film-detail__head">
        ${posterImg(film, 'poster--lg')}
        <div class="film-detail__meta">
          <h1 class="film-detail__title">${film.title}</h1>
          <p class="film-detail__sub">${sub}</p>
          <div class="film-detail__score">
            <span class="film-detail__avg">${avg}</span>
          </div>
        </div>
      </div>

      <section class="ratings">
        <h2 class="section-title">Оценки</h2>
        ${ratingsHtml}
      </section>

      ${notWatchedHtml}
    </article>`;
}

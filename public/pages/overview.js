import {posterImg, letterboxdLink, pluralWith, plural, esc, filmUrl} from '../helpers.js';

function num(value) {
    return value === null ? '—' : value;
}

function filmCard(label, film, statLabel = null) {
    if (film === null) {
        return `
      <article class="card">
        <p class="card__label">${label}</p>
        <p class="card__empty">Пока мало оценок</p>
      </article>`;
    }

    const meta = statLabel === null
        ? `${pluralWith(film.votes, ['оценка', 'оценки', 'оценок'])} · разброс ${film.spread}`
        : `${pluralWith(film.votes, ['оценка', 'оценки', 'оценок'])} · ${statLabel} ${film.stdDev}`;

    return `
    <article class="card">
      <p class="card__label">${label}</p>
      <div class="card__body">
        <a href="${filmUrl(film.slug)}">${posterImg(film, 'poster--card')}</a>
        <div class="card__info">
          <h3 class="card__title"><a class="card__link" href="${filmUrl(film.slug)}">${esc(film.title)}</a></h3>
          <p class="card__stats">
            <span class="card__avg">${film.average}</span>
            <span class="card__meta">${meta}</span>
          </p>
        </div>
      </div>
    </article>`;
}

function memberCard(label, member, valueText, metaText) {
    if (member === null) {
        return `
      <article class="card">
        <p class="card__label">${label}</p>
        <p class="card__empty">Пока мало данных</p>
      </article>`;
    }
    return `
    <article class="card card--member">
      <p class="card__label">${label}</p>
      <h3 class="card__title">${esc(member.displayName)}</h3>
      ${letterboxdLink(member.username)}
      <p class="card__stats">
        <span class="card__avg">${valueText}</span>
        <span class="card__meta">${metaText}</span>
      </p>
    </article>`;
}

export async function render(root) {
    const response = await fetch('/api/overview');
    if (!response.ok) throw new Error(`API статус ${response.status}`);
    const data = await response.json();
    const t = data.totals;

    const curator = data.bestCurator;
    const active = data.mostActiveMember;

    root.innerHTML = `
    <section class="totals">
      <div class="stat"><span class="stat__num">${t.films}</span><span class="stat__label">${plural(t.films, ['фильм','фильма','фильмов'])}</span></div>
      <div class="stat"><span class="stat__num">${t.ratings}</span><span class="stat__label">${plural(t.ratings, ['оценка', 'оценки', 'оценок'])}</span></div>
      <div class="stat"><span class="stat__num">${t.members}</span><span class="stat__label">${plural(t.members, ['участник','участника','участников'])}</span></div>
      <div class="stat"><span class="stat__num">${num(t.currentRound)}</span><span class="stat__label">${plural(num(t.currentRound), ['круг','круга','кругов'])}</span></div>
    </section>

    <h2 class="section-title">Фильмы</h2>
    <section class="cards">
      ${filmCard('Лучший фильм', data.bestFilm)}
      ${filmCard('Худший фильм', data.worstFilm)}
    </section>

    <h2 class="section-title">Консенсус</h2>
    <section class="cards">
      ${filmCard('Самый спорный', data.mostDivisive, 'расхождение')}
      ${filmCard('Полное согласие', data.mostAgreed, 'расхождение')}
    </section>

    <h2 class="section-title">Участники</h2>
    <section class="cards">
      ${memberCard(
        'Самый активный',
        active,
        active === null ? '' : active.watched,
        'посмотрено фильмов',
    )}
      ${memberCard(
        'Лучший вкус',
        curator,
        curator === null ? '' : curator.pickedAverage,
        curator === null ? '' : `средняя по ${pluralWith(curator.picks, ['зачётному выбору', 'зачётным выборам', 'зачётным выборам'])} <span class="hint" data-tip="Учитываются только фильмы участника, набравшие кворум (≥5 оценок). Показана средняя оценка клуба по ним.">?</span>`,
    )}
    </section>`;
}

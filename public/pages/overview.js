import { posterImg, letterboxdLink } from '../helpers.js';

function num(value) {
    return value === null ? '—' : value;
}

function filmCard(label, film, statLabel = null) {
    if (film === null) {
        return `
      <article class="card">
        <p class="card__label">${label}</p>
        <p class="card__empty">Not enough ratings yet</p>
      </article>`;
    }

    const meta = statLabel === null
        ? `${film.votes} votes · spread ${film.spread}`
        : `${film.votes} votes · ${statLabel} ${film.stdDev}`;

    return `
    <article class="card">
      <p class="card__label">${label}</p>
      <div class="card__body">
        <a href="/films/${film.slug}">${posterImg(film, 'poster--card')}</a>
        <div class="card__info">
          <h3 class="card__title"><a class="card__link" href="/films/${film.slug}">${film.title}</a></h3>
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
        <p class="card__empty">Not enough data yet</p>
      </article>`;
    }
    return `
    <article class="card card--member">
      <p class="card__label">${label}</p>
      <h3 class="card__title">${member.displayName}</h3>
      ${letterboxdLink(member.username)}
      <p class="card__stats">
        <span class="card__avg">${valueText}</span>
        <span class="card__meta">${metaText}</span>
      </p>
    </article>`;
}

export async function render(root) {
    root.innerHTML = 'Loading…';
    const response = await fetch('/api/overview');
    if (!response.ok) throw new Error(`API статус ${response.status}`);
    const data = await response.json();
    const t = data.totals;

    const curator = data.bestCurator;
    const active = data.mostActiveMember;

    root.innerHTML = `
    <section class="totals">
      <div class="stat"><span class="stat__num">${t.films}</span><span class="stat__label">films</span></div>
      <div class="stat"><span class="stat__num">${t.ratings}</span><span class="stat__label">ratings</span></div>
      <div class="stat"><span class="stat__num">${t.members}</span><span class="stat__label">members</span></div>
      <div class="stat"><span class="stat__num">${num(t.currentRound)}</span><span class="stat__label">round</span></div>
    </section>

    <h2 class="section-title">Films</h2>
    <section class="cards">
      ${filmCard('Best film', data.bestFilm)}
      ${filmCard('Worst film', data.worstFilm)}
    </section>

    <h2 class="section-title">Agreement</h2>
    <section class="cards">
      ${filmCard('Most divisive', data.mostDivisive, 'σ')}
      ${filmCard('Most agreed', data.mostAgreed, 'σ')}
    </section>

    <h2 class="section-title">People</h2>
    <section class="cards">
      ${memberCard(
        'Most active',
        active,
        active === null ? '' : active.watched,
        'films watched',
    )}
      ${memberCard(
        'Best curator',
        curator,
        curator === null ? '' : curator.pickedAverage,
        curator === null ? '' : `avg over ${curator.picks} qualifying picks`,
    )}
    </section>`;
}

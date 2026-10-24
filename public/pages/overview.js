import {posterImg, posterSrc, letterboxdLink, pluralWith, plural, esc, filmUrl} from '../helpers.js';

function num(value) {
    return value === null ? '—' : value;
}

function filmBlock(film, statLabel) {
    const meta = statLabel === null
        ? `${pluralWith(film.votes, ['оценка', 'оценки', 'оценок'])} · разброс ${film.spread}`
        : `${pluralWith(film.votes, ['оценка', 'оценки', 'оценок'])} · ${statLabel} ${film.stdDev}`;

    return `
      <div class="card__body">
        <a href="${filmUrl(film.slug)}">${posterImg(film, 'poster--card')}</a>
        <div class="card__info">
          <h3 class="card__title"><a class="card__link" href="${filmUrl(film.slug)}">${esc(film.title)}</a></h3>
          <p class="card__stats">
            <span class="card__avg">${film.average}</span>
            <span class="card__meta">${meta}</span>
          </p>
        </div>
      </div>`;
}

// films — the whole tie (usually one film); render a block per film.
// labels — [singular, plural]; the plural shows only when the tie has more than one.
function filmCard([one, many], films, statLabel = null) {
    if (films.length === 0) {
        return `
      <article class="card">
        <p class="card__label">${one}</p>
        <p class="card__empty">Пока мало оценок</p>
      </article>`;
    }

    return `
    <article class="card card--film" style="--backdrop: url(${esc(JSON.stringify(posterSrc(films[0].slug)))})">
      <p class="card__label">${films.length > 1 ? many : one}</p>
      <div class="card__films">${films.map((f) => filmBlock(f, statLabel)).join('')}</div>
    </article>`;
}

function memberBlock(member, value, meta) {
    return `
      <div class="card__member">
        <h3 class="card__title">${esc(member.displayName)}</h3>
        ${letterboxdLink(member.username)}
        <p class="card__stats">
          <span class="card__avg">${value(member)}</span>
          <span class="card__meta">${meta(member)}</span>
        </p>
      </div>`;
}

// members — the whole tie (usually one member); render a block per member.
// labels — [singular, plural]; the plural shows only when the tie has more than one.
function memberCard([one, many], members, value, meta) {
    if (members.length === 0) {
        return `
      <article class="card">
        <p class="card__label">${one}</p>
        <p class="card__empty">Пока мало данных</p>
      </article>`;
    }

    return `
    <article class="card card--member">
      <p class="card__label">${members.length > 1 ? many : one}</p>
      <div class="card__members">${members.map((m) => memberBlock(m, value, meta)).join('')}</div>
    </article>`;
}

function countUp(el, duration = 800) {
    const target = Number(el.dataset.count);
    const start = performance.now();

    const tick = (now) => {
        const progress = Math.min((now - start) / duration, 1);
        const eased = 1 - (1 - progress) ** 3;
        el.textContent = String(Math.round(target * eased));
        if (progress < 1) requestAnimationFrame(tick);
    };
    requestAnimationFrame(tick);
}

function stat(value, forms) {
    const count = value === null ? '' : ` data-count="${value}"`;
    return `<div class="stat"><span class="stat__num"${count}>${num(value)}</span><span class="stat__label">${plural(num(value), forms)}</span></div>`;
}

export async function render(root) {
    const response = await fetch('/api/overview');
    if (!response.ok) throw new Error(`API статус ${response.status}`);
    const data = await response.json();
    const t = data.totals;

    const curator = data.bestCurator;
    const active = data.mostActiveMember;

    root.innerHTML = `
    <section class="hero">
      <div class="hero__text">
        <p class="hero__tagline">Смотрим кино кругами и считаем, что из этого вышло</p>
        <section class="totals">
          ${stat(t.films, ['фильм', 'фильма', 'фильмов'])}
          ${stat(t.ratings, ['оценка', 'оценки', 'оценок'])}
          ${stat(t.members, ['участник', 'участника', 'участников'])}
          ${stat(t.currentRound, ['круг', 'круга', 'кругов'])}
        </section>
      </div>
      <img class="hero__mascot" src="/mascot.png" alt="">
    </section>

    <h2 class="section-title rise" style="--i: 1">Фильмы</h2>
    <section class="cards rise" style="--i: 1">
      ${filmCard(['Лучший фильм', 'Лучшие фильмы'], data.bestFilm)}
      ${filmCard(['Худший фильм', 'Худшие фильмы'], data.worstFilm)}
    </section>

    <h2 class="section-title rise" style="--i: 2">Консенсус</h2>
    <section class="cards rise" style="--i: 2">
      ${filmCard(['Самый спорный', 'Самые спорные'], data.mostDivisive, 'расхождение')}
      ${filmCard(['Полное согласие', 'Полное согласие'], data.mostAgreed, 'расхождение')}
    </section>

    <h2 class="section-title rise" style="--i: 3">Участники</h2>
    <section class="cards rise" style="--i: 3">
      ${memberCard(
        ['Самый активный', 'Самые активные'],
        active,
        (m) => m.watched,
        () => 'посмотрено фильмов',
    )}
      ${memberCard(
        ['Лучший вкус', 'Лучший вкус'],
        curator,
        (m) => m.pickedAverage,
        (m) => `средняя по ${pluralWith(m.picks, ['зачётному выбору', 'зачётным выборам', 'зачётным выборам'])} <span class="hint" data-tip="Учитываются только фильмы участника, набравшие кворум (≥5 оценок). Показана средняя оценка клуба по ним.">?</span>`,
    )}
    </section>`;

    if (!matchMedia('(prefers-reduced-motion: reduce)').matches) {
        root.querySelectorAll('.stat__num[data-count]').forEach((el) => countUp(el));
    }
}

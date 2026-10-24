import {posterImg, posterSrc, letterboxdLink, pluralWith, plural, esc, filmUrl, memberUrl} from '../helpers.js';

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
        <h3 class="card__title"><a class="card__link" href="${memberUrl(member.username)}">${esc(member.displayName)}</a></h3>
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

function stat(value, forms, href = null) {
    const count = value === null ? '' : ` data-count="${value}"`;
    const inner = `<span class="stat__num"${count}>${num(value)}</span><span class="stat__label">${plural(num(value), forms)}</span>`;
    return href === null
        ? `<div class="stat">${inner}</div>`
        : `<a class="stat stat--link" href="${href}">${inner}</a>`;
}

function joined(items) {
    return items.length === 1 ? items[0] : `${items.slice(0, -1).join(', ')} и ${items.at(-1)}`;
}

function names(members) {
    return joined(members.map((m) => m.displayName));
}

function titles(films) {
    return joined(films.map((f) => `«${f.title}»`));
}

// Facts the overview does not already show; phrased so they never need a member's gender.
function facts(f, totals) {
    const list = [];
    const say = (condition, text) => { if (condition) list.push(text()); };
    const many = (items, one, several) => items.length > 1 ? several : one;

    const hours = Math.round(totals.ratings * 2 / 100) * 100;
    say(hours > 0, () => `${pluralWith(totals.ratings, ['оценка', 'оценки', 'оценок'])} — это около ${pluralWith(hours, ['часа', 'часов', 'часов'])} кино на всех`);

    const harsh = f.harshestCritics;
    say(harsh.length > 0, () => `${many(harsh, 'Самый строгий зритель', 'Самые строгие зрители')} — ${names(harsh)}: в среднем ${harsh[0].value}`);
    const kind = f.kindestCritics;
    say(kind.length > 0, () => `${many(kind, 'Самый добрый зритель', 'Самые добрые зрители')} — ${names(kind)}: в среднем ${kind[0].value}`);

    const best = f.bestRounds;
    say(best.length > 0, () => `${many(best, 'Круг', 'Круги')} ${joined(best.map((r) => r.number))} — ${many(best, 'самый удачный', 'самые удачные')}: средняя ${best[0].value}`);
    const worst = f.worstRounds;
    say(worst.length > 0, () => `${many(worst, 'Круг', 'Круги')} ${joined(worst.map((r) => r.number))} — ${many(worst, 'самый тяжёлый', 'самые тяжёлые')}: средняя ${worst[0].value}`);
    const long = f.longestRounds;
    say(long.length === 1, () => `Дольше всех шёл круг ${long[0].number}: ${pluralWith(long[0].value, ['день', 'дня', 'дней'])}`);

    const tens = f.mostTens;
    say(f.tens > 0, () => `За всё время поставили ${pluralWith(f.tens, ['десятку', 'десятки', 'десяток'])}`
        + (tens.length === 1 && tens[0].value > 1 ? `. Больше всего — у «${tens[0].title}»: ${tens[0].value}` : ''));
    const ones = f.mostOnes;
    say(f.ones > 0, () => `${pluralWith(f.ones, ['единица', 'единицы', 'единиц'])} за всё время`
        + (ones.length === 1 && ones[0].value > 1 ? `, и ${ones[0].value} из них — у «${ones[0].title}»` : ''));
    for (const film of f.tenAndOne) list.push(`Фильм «${film.title}» получил и 10, и 1`);

    const all = f.completionists;
    say(all.length > 0, () => `Ни одного пропуска во всех завершённых кругах: ${names(all)}`);

    const least = f.leastWatched;
    say(least.length > 0 && least.length <= 2, () => `Меньше всего оценок у ${titles(least)}: ${least[0].value}`);
    say(least.length > 2, () => `${pluralWith(least.length, ['фильм', 'фильма', 'фильмов'])} делят последнее место по просмотрам: всего по ${pluralWith(least[0].value, ['оценке', 'оценки', 'оценок'])}`);

    const pickers = f.mostWatchedPickers;
    say(pickers.length > 0, () => `Охотнее всего смотрят фильмы, которые ${many(pickers, 'выбирает', 'выбирают')} ${names(pickers)}: в среднем ${Number.isInteger(pickers[0].value) ? pluralWith(pickers[0].value, ['оценка', 'оценки', 'оценок']) : `${pickers[0].value} оценки`} на фильм`);

    const reviewers = f.mostReviews;
    say(reviewers.length > 0, () => `Больше всего рецензий: ${names(reviewers)} — ${reviewers[0].value}`);

    for (const t of f.hotTakes) list.push(`Самое неожиданное мнение — «${t.filmTitle}»: ${t.displayName} ставит ${t.score} при средней ${t.filmAverage}`);

    for (let i = list.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [list[i], list[j]] = [list[j], list[i]];
    }
    return list;
}

// The mascot's line types itself out next to him; each click on him brings the next fact.
// Facts are fetched on the first click only, so they never slow the overview down.
function speak(root, totals) {
    const line = root.querySelector('.hero__line');
    const typed = root.querySelector('.hero__typed');
    const spoken = root.querySelector('.hero__spoken');
    const instant = matchMedia('(prefers-reduced-motion: reduce)').matches;
    let run = 0;
    let next = 0;
    let lines = null;

    const load = async () => {
        const response = await fetch('/api/facts');
        if (!response.ok) throw new Error(`API статус ${response.status}`);
        return facts(await response.json(), totals);
    };

    const type = (text) => {
        const id = ++run;
        let shown = 0;
        line.classList.add('hero__line--typing');

        const step = () => {
            if (id !== run) return;
            shown = instant ? text.length : shown + 1;
            typed.textContent = text.slice(0, shown);
            if (shown >= text.length) {
                line.classList.remove('hero__line--typing');
                return;
            }
            setTimeout(step, ',.:—'.includes(text[shown - 1]) ? 180 : 32);
        };
        step();
    };

    setTimeout(() => type('— Нажми на меня, расскажу кое-что про клуб'), instant ? 0 : 1100);

    root.querySelector('.hero__mascot-btn').addEventListener('click', async () => {
        line.classList.remove('hero__line--idle');
        try {
            lines ??= await load();
        } catch (error) {
            console.error(error);
            type('— Что-то я всё забыл. Спроси попозже');
            return;
        }
        if (lines.length === 0) {
            type('— Пока рассказывать нечего: клуб только начинается');
            return;
        }

        const fact = lines[next];
        next = (next + 1) % lines.length;
        spoken.textContent = fact;
        type(`— ${fact}`);
    });
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
          ${stat(t.films, ['фильм', 'фильма', 'фильмов'], '/films')}
          ${stat(t.ratings, ['оценка', 'оценки', 'оценок'])}
          ${stat(t.members, ['участник', 'участника', 'участников'], '/members')}
          ${stat(t.currentRound, ['круг', 'круга', 'кругов'], '/rounds')}
        </section>
      </div>
      <div class="hero__speech">
        <p class="hero__line hero__line--idle" aria-hidden="true"><span class="hero__typed"></span><span class="hero__caret"></span></p>
        <p class="visually-hidden hero__spoken" aria-live="polite"></p>
      </div>
      <div class="hero__cast">
        <button class="hero__mascot-btn" type="button" aria-label="Факт о клубе">
          <img class="hero__mascot" src="/mascot.png" alt="">
        </button>
      </div>
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

    speak(root, t);

    if (!matchMedia('(prefers-reduced-motion: reduce)').matches) {
        root.querySelectorAll('.stat__num[data-count]').forEach((el) => countUp(el));
    }
}

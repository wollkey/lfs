import { esc, posterSrc, plural, pluralWith } from '../helpers.js';

let loaded = { year: null, data: null };
let keys = null;

const fmt = (n) => (n === null ? '—' : n.toFixed(1));
const names = (list, key = 'title') => list.map((item) => esc(item[key])).join(', ');
const hours = (minutes) => Math.round(minutes / 60);

export async function render(root, { year }) {
    keys?.abort();

    if (loaded.year !== year) {
        const response = await fetch(`/api/wrapped/${encodeURIComponent(year)}`);
        if (response.status === 404) {
            root.innerHTML = `<p class="placeholder">В ${esc(year)} году клуб ещё ничего не смотрел.</p>`;
            return;
        }
        if (!response.ok) throw new Error(`API статус ${response.status}`);
        loaded = { year, data: await response.json() };
    }

    const data = loaded.data;
    const key = decodeURIComponent(location.hash.slice(1));
    const member = data.members.find((m) => m.username === key);

    if (key === 'club') play(root, data, clubSlides(data));
    else if (member !== undefined) play(root, data, memberSlides(data, member));
    else root.innerHTML = lobby(data);
}

function lobby(data) {
    const { year, club } = data;
    const members = data.members.map((m) => `
      <li>
        <a class="wrapped-member" href="/wrapped/${year}#${encodeURIComponent(m.username)}">
          <span class="member__avatar" aria-hidden="true">${esc(m.displayName.trim().charAt(0).toUpperCase())}</span>
          <span class="wrapped-member__name">${esc(m.displayName)}</span>
        </a>
      </li>`).join('');

    return `
    <section class="wrapped-lobby">
      <p class="story__eyebrow">Last Frame Society</p>
      <h1 class="wrapped-lobby__title">Итоги ${year}</h1>
      <p class="wrapped-lobby__lead">
        ${pluralWith(club.films, ['фильм', 'фильма', 'фильмов'])} ·
        ${pluralWith(hours(club.minutes), ['час', 'часа', 'часов'])} ·
        ${pluralWith(club.ratings, ['оценка', 'оценки', 'оценок'])}
      </p>
      <a class="wrapped-lobby__club" href="/wrapped/${year}#club">Итоги клуба</a>
      <h2 class="member-group__title">Личные итоги</h2>
      <ul class="wrapped-lobby__members">${members}</ul>
    </section>`;
}

function play(root, data, slides) {
    let index = 0;

    root.innerHTML = `
    <section class="story">
      <div class="story__progress">${slides.map(() => '<span class="story__bar"></span>').join('')}</div>
      <a class="story__close" href="/wrapped/${data.year}" aria-label="Закрыть">×</a>
      <div class="story__slide" aria-live="polite"></div>
      <button class="story__nav story__nav--prev" aria-label="Назад"></button>
      <button class="story__nav story__nav--next" aria-label="Дальше"></button>
    </section>`;

    const story = root.querySelector('.story');
    const bars = [...story.querySelectorAll('.story__bar')];

    const show = (next) => {
        index = Math.max(0, Math.min(slides.length - 1, next));
        const slide = slides[index];
        story.querySelector('.story__slide').innerHTML = slide.html;
        story.style.setProperty('--backdrop', slide.backdrop ? `url("${posterSrc(slide.backdrop, 'w300')}")` : 'none');
        bars.forEach((bar, i) => bar.classList.toggle('story__bar--seen', i <= index));
    };

    story.querySelector('.story__nav--prev').addEventListener('click', () => show(index - 1));
    story.querySelector('.story__nav--next').addEventListener('click', () => show(index + 1));

    keys = new AbortController();
    document.addEventListener('keydown', (event) => {
        if (!story.isConnected) {
            keys.abort();
            return;
        }
        if (event.key === 'ArrowRight' || event.key === ' ') show(index + 1);
        else if (event.key === 'ArrowLeft') show(index - 1);
        else return;
        event.preventDefault();
    }, { signal: keys.signal });

    show(0);
}

function slide(backdrop, ...parts) {
    return { backdrop, html: parts.filter(Boolean).join('') };
}

const head = (section, lead = '') => `<p class="story__eyebrow">${section}</p>${lead && `<p class="story__lead">${lead}</p>`}`;
const title = (text) => `<h2 class="story__title">${text}</h2>`;
const big = (number, caption) => `<p class="story__big">${number}</p><p class="story__caption">${caption}</p>`;
const text = (html) => `<p class="story__text">${html}</p>`;
const label = (html) => `<p class="story__caption">${html}</p>`;
const poster = (film) => `<img class="story__poster" src="${posterSrc(film.slug)}" alt="">`;
const posters = (films) => `<div class="story__posters">${films.slice(0, 4).map((f) => `<img src="${posterSrc(f.slug, 'w300')}" alt="">`).join('')}</div>`;
const tiles = (items) => `<div class="story__tiles">${items.filter(Boolean).map(([value, caption]) => `
    <div class="story__tile"><span class="story__tile-num">${value}</span><span class="story__tile-label">${caption}</span></div>`).join('')}</div>`;
const links = (items) => `<div class="story__links">${items.map(([href, caption]) => `<a class="story__link" href="${href}">${caption}</a>`).join('')}</div>`;

const films = (n) => pluralWith(n, ['фильм', 'фильма', 'фильмов']);
const hoursOf = (minutes) => pluralWith(hours(minutes), ['час', 'часа', 'часов']);

function memberSlides(data, m) {
    const { year, club } = data;
    const leaders = data.members.filter((other) => other.rank === 1).length;
    const rank = m.rank === 1
        ? (leaders > 1 ? 'Первое место в клубе по просмотрам - вместе с другими' : 'Первое место в клубе по просмотрам')
        : `${m.rank}-е место в клубе по просмотрам`;

    const slides = [
        slide(null,
            head(`Last Frame Society · ${year}`),
            title(esc(m.displayName)),
            text(`Твои итоги ${year} года в клубе. Нажимай справа, чтобы листать.`)),
        slide(null,
            head(`${year} · Просмотры`, 'Фильмы клуба за год, у которых есть твоя оценка'),
            big(m.watched, `из ${films(club.films)}`),
            text(`<b>${hoursOf(m.minutes)}</b> в кино<br>${rank}`)),
        favoritesSlide(year, m.favorites, 'Любимое', 'с твоей самой высокой оценкой'),
        favoritesSlide(year, m.leastFavorites, 'Нелюбимое', 'с твоей самой низкой оценкой'),
    ];

    if (m.hotTakes.length > 0) {
        const film = m.hotTakes[0];
        slides.push(slide(film.slug,
            head(`${year} · Спорная оценка`, 'Здесь твоя оценка дальше всего от средней клуба'),
            poster(film),
            title(esc(film.title)),
            text(`Твоя оценка - <b>${film.score}</b><br>Средняя клуба - ${fmt(film.average)}`)));
    }

    const leaning = m.leaning === null || Math.abs(m.leaning) < 0.1
        ? 'Ровно как у остальных'
        : `На ${Math.abs(m.leaning).toFixed(1)} ${m.leaning > 0 ? 'выше' : 'ниже'}, чем у остальных на тех же фильмах`;
    slides.push(slide(null,
        head(`${year} · Щедрость`, 'Твоя средняя оценка за год'),
        big(fmt(m.average), 'из 10'),
        text(leaning)));

    if (m.genres.length > 0) {
        const genre = m.genres[0];
        slides.push(slide(null,
            head(`${year} · Жанр`, 'Жанр с твоими самыми высокими оценками'),
            title(names(m.genres, 'name')),
            text(`<b>${fmt(genre.average)}</b> в среднем по ${pluralWith(genre.films, ['фильму', 'фильмам', 'фильмам'])}`)));
    }

    if (m.picks.length > 0) {
        const best = new Set(m.bestPicks.map((p) => p.slug));
        const rows = m.picks.map((p) => `
          <li class="${best.has(p.slug) ? 'story__best' : ''}">
            <span>${esc(p.title)}</span><span class="story__score">${fmt(p.average)}</span>
          </li>`).join('');
        slides.push(slide(m.bestPicks[0]?.slug ?? m.picks[0].slug,
            head(`${year} · Твои выборы`, 'Фильмы твоего выбора и средняя клуба по ним'),
            posters(m.picks),
            `<ul class="story__list">${rows}</ul>`,
            m.bestPicks.length === 0 ? '' : label('★ - самый высокий из них'),
            m.picksAverage === null ? '' : text(`В среднем - <b>${fmt(m.picksAverage)}</b>`)));
    }

    if (m.closest.length > 0) {
        slides.push(slide(null,
            head(`${year} · Вкусы`, 'Доля общих фильмов, которые обоим понравились или обоим нет'),
            label('Вкусовой близнец'),
            title(`${names(m.closest, 'displayName')} · ${m.closest[0].agreement}%`),
            m.farthest.length === 0 ? '' : label('Вкусовой антипод'),
            m.farthest.length === 0 ? '' : title(`${names(m.farthest, 'displayName')} · ${m.farthest[0].agreement}%`)));
    }

    if (m.letterboxd.length > 0) {
        const film = m.letterboxd[0];
        slides.push(slide(film.slug,
            head(`${year} · Ты и Letterboxd`, 'Здесь твоя оценка дальше всего от Letterboxd'),
            poster(film),
            title(esc(film.title)),
            text(`Твоя оценка - <b>${film.ours}</b><br>Letterboxd - ${fmt(film.letterboxd)}`)));
    }

    slides.push(slide(null,
        head(`Твой ${year}`),
        title(esc(m.displayName)),
        tiles([
            [m.watched, plural(m.watched, ['фильм', 'фильма', 'фильмов'])],
            [hours(m.minutes), plural(hours(m.minutes), ['час', 'часа', 'часов'])],
            [fmt(m.average), 'средняя'],
            [m.reviews, plural(m.reviews, ['отзыв', 'отзыва', 'отзывов'])],
            m.closest.length > 0 && [esc(m.closest[0].displayName), 'близнец'],
            m.genres.length > 0 && [esc(m.genres[0].name), 'жанр'],
        ]),
        links([[`/wrapped/${year}#club`, 'Итоги клуба'], [`/wrapped/${year}`, 'Все участники']])));

    return slides.filter(Boolean);
}

function favoritesSlide(year, list, section, which) {
    if (list.length === 0) return null;

    const film = list[0];
    const lead = `${list.length > 1 ? 'Фильмы' : 'Фильм'} года ${which}`;
    if (list.length > 4) {
        return slide(film.slug,
            head(`${year} · ${section}`, lead),
            big(list.length, `${plural(list.length, ['фильм', 'фильма', 'фильмов'])} на ${film.score}`),
            posters(list),
            text(names(list)));
    }

    return slide(film.slug,
        head(`${year} · ${section}`, lead),
        list.length === 1 ? poster(film) : posters(list),
        title(names(list)),
        text(`Твоя оценка - <b>${film.score}</b>${list.length === 1 ? `<br>Средняя клуба - ${fmt(film.average)}` : ''}`));
}

function clubSlides(data) {
    const { year, club } = data;
    const votes = (f) => pluralWith(f.votes, ['оценка', 'оценки', 'оценок']);
    const slides = [
        slide(null,
            head('Last Frame Society'),
            `<p class="story__big">${year}</p>`,
            text(`Итоги ${year} года в клубе. Нажимай справа, чтобы листать.`)),
        slide(null,
            head(`${year} · В цифрах`, `Всё, что клуб выбрал и посмотрел за ${year} год`),
            tiles([
                [club.films, plural(club.films, ['фильм', 'фильма', 'фильмов'])],
                [hours(club.minutes), plural(hours(club.minutes), ['час', 'часа', 'часов'])],
                [club.ratings, plural(club.ratings, ['оценка', 'оценки', 'оценок'])],
                [club.reviews, plural(club.reviews, ['отзыв', 'отзыва', 'отзывов'])],
                [club.countryCount, plural(club.countryCount, ['страна', 'страны', 'стран'])],
            ])),
        ratedSlide(club.best, `${year} · Фильм года`, 'Самая высокая средняя среди фильмов года', (f) => `Средняя - <b>${fmt(f.average)}</b> · ${votes(f)}`),
        ratedSlide(club.worst, `${year} · Провал года`, 'Самая низкая средняя среди фильмов года', (f) => `Средняя - <b>${fmt(f.average)}</b> · ${votes(f)}`),
        ratedSlide(club.divisive, `${year} · Самый спорный`, 'Фильм, на котором оценки разошлись сильнее всего', (f) => `От самой низкой до самой высокой - <b>${pluralWith(f.spread, ['балл', 'балла', 'баллов'])}</b>`),
    ];

    if (club.bestPicks.length > 0) {
        const pick = club.bestPicks[0];
        slides.push(slide(pick.slug,
            head(`${year} · Лучший выбор`, 'Участник, чей фильм клуб оценил выше всех'),
            poster(pick),
            title(names(club.bestPicks, 'displayName')),
            text(`${names(club.bestPicks)} - <b>${fmt(pick.average)}</b>`)));
    }

    const facts = [
        club.directors.length > 0 && [
            club.directors.length > 1 ? 'Режиссёры года' : 'Режиссёр года',
            `${names(club.directors, 'name')} - по ${pluralWith(club.directors[0].films, ['фильму', 'фильма', 'фильмов'])}`,
        ],
        club.genres.length > 0 && ['Любимый жанр', `${names(club.genres, 'name')} - ${fmt(club.genres[0].average)} в среднем`],
        club.countries.length > 0 && ['Страна года', `${names(club.countries, 'name')} - ${films(club.countries[0].films)}`],
    ].filter(Boolean);
    if (facts.length > 0) {
        slides.push(slide(null,
            head(`${year} · В именах`, 'Кого и что клуб смотрел чаще и любил больше всего'),
            ...facts.map(([caption, value]) => `${label(caption)}${text(value)}`)));
    }

    if (club.oldest.length > 0) {
        const film = club.oldest[0];
        slides.push(slide(film.slug,
            head(`${year} · Самый старый`, 'Самый ранний год выпуска среди фильмов года'),
            poster(film),
            title(names(club.oldest)),
            text(`<b>${film.value}</b> год`)));
    }

    const gap = club.belowLetterboxd[0] ?? club.aboveLetterboxd[0];
    if (gap !== undefined) {
        const line = (list, caption) => list.length === 0 ? '' : `${label(caption)}${text(
            `<b>${names(list)}</b><br>у клуба ${fmt(list[0].ours)}, на Letterboxd ${fmt(list[0].letterboxd)}`)}`;
        slides.push(slide(gap.slug,
            head(`${year} · Мы и Letterboxd`, 'Где клуб сильнее всего разошёлся с Letterboxd'),
            poster(gap),
            line(club.belowLetterboxd, 'Клубу понравилось меньше'),
            line(club.aboveLetterboxd, 'Клубу понравилось больше')));
    }

    slides.push(slide(null,
        head(`Это был ${year}`),
        title('А теперь - личные итоги'),
        links(data.members.map((m) => [`/wrapped/${year}#${encodeURIComponent(m.username)}`, esc(m.displayName)]))));

    return slides.filter(Boolean);
}

function ratedSlide(list, section, lead, line) {
    if (list.length === 0) return null;

    return slide(list[0].slug,
        head(section, lead),
        poster(list[0]),
        title(names(list)),
        text(line(list[0])));
}

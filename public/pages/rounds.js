import {letterboxdLink, pluralWith, posterImg} from '../helpers.js';

let chart = null;
let sortMode = 'position';
let openRounds = null;

const SORTS = {
    position: (a, b) => (a.position ?? 0) - (b.position ?? 0),
    best:     (a, b) => (b.average ?? -1) - (a.average ?? -1),
    worst:    (a, b) => (a.average ?? 11) - (b.average ?? 11),
};

const SORT_LABELS = {
    position: 'По порядку',
    best: 'Лучшие',
    worst: 'Худшие',
};

function formatDate(iso) {
    if (iso === null) return null;
    const [y, m, d] = iso.split('-').map(Number);
    return new Date(y, m - 1, d).toLocaleDateString('ru-RU', { day: 'numeric', month: 'short', year: 'numeric' });
}

function dateRange(round) {
    const from = formatDate(round.startedOn);
    const to = formatDate(round.endedOn);
    if (from === null && to === null) return '';
    if (to === null) return `${from} → сейчас`;
    if (from === null) return `до ${to}`;
    return `${from} → ${to}`;
}

function roundFilmRow(film, winnerSlug, worstSlug) {
    const avg = film.average === null ? '—' : film.average;

    const isWinner = winnerSlug !== null && film.slug === winnerSlug;
    const isWorst = !isWinner && worstSlug !== null && film.slug === worstSlug;

    const parts = [];
    if (film.pickedBy !== null) parts.push(`picked by ${letterboxdLink(film.pickedBy)}`);
    if (isWinner) parts.push(`<span class="badge-winner">★ Победитель</span>`);
    if (isWorst)  parts.push(`<span class="badge-worst">▼ Худший</span>`);
    const sub = parts.join(' · ');

    const modifier = isWinner ? 'film--winner' : isWorst ? 'film--worst' : '';

    return `
    <li class="film ${modifier}">
      ${posterImg(film, 'poster--sm')}
      <div class="film__main">
        <a class="film__title" href="/films/${film.slug}">${film.title}</a>
        <span class="film__sub">${sub}</span>
      </div>
      <div class="film__stats">
        <span class="film__votes">${pluralWith(film.votes, ['оценка', 'оценки', 'оценок'])}</span>
        <span class="film__avg">${avg}</span>
      </div>
    </li>`;
}

function roundSection(round) {
    const isOpen = openRounds.has(round.number);
    const winnerSlug = round.winner === null ? null : round.winner.slug;
    const worstSlug  = round.worst === null ? null : round.worst.slug;
    const dates = dateRange(round);

    const films = [...round.films].sort(SORTS[sortMode]);
    const rows = films.map((f) => roundFilmRow(f, winnerSlug, worstSlug)).join('');

    return `
    <details class="round" data-round="${round.number}" ${isOpen ? 'open' : ''}>
      <summary class="round__head">
        <span class="round__num">Раунд ${round.number}</span>
        ${dates === '' ? '' : `<span class="round__dates">${dates}</span>`}
        <span class="round__count">${round.films.length} фильмов</span>
        ${round.average === null ? '' : `<span class="round__avg">средняя ${round.average}</span>`}
      </summary>
      <ul class="round__films">${rows}</ul>
    </details>`;
}

function drawAveragesChart(canvas, rounds) {
    if (chart !== null) chart.destroy();

    chart = new Chart(canvas, {
        type: 'line',
        data: {
            labels: rounds.map((r) => `R${r.number}`),
            datasets: [{
                data: rounds.map((r) => r.average),
                borderColor: '#e0a04d',
                backgroundColor: '#e0a04d',
                tension: 0.3,
                pointRadius: 3,
                borderWidth: 2,
            }],
        },
        options: {
            maintainAspectRatio: false,
            scales: {
                y: { min: 0, max: 10, ticks: { color: '#8a8a92', stepSize: 5 }, grid: { color: '#26262c' } },
                x: { ticks: { color: '#8a8a92' }, grid: { display: false } },
            },
            plugins: { legend: { display: false } },
        },
    });
}

export async function render(root) {
    root.innerHTML = 'Загрузка';
    const response = await fetch('/api/rounds');
    if (!response.ok) throw new Error(`API статус ${response.status}`);
    const data = await response.json();

    if (data.rounds.length === 0) {
        root.innerHTML = `<p class="placeholder">Пока нет раундов</p>`;
        return;
    }

    const byNumber = [...data.rounds].sort((a, b) => a.number - b.number);
    const hasAverages = byNumber.some((r) => r.average !== null);

    if (openRounds === null) {
        openRounds = new Set([byNumber[byNumber.length - 1].number]);
    }

    const sortButtons = Object.keys(SORTS).map((mode) => `
    <button class="sort-btn ${mode === sortMode ? 'sort-btn--active' : ''}" data-sort="${mode}">
      ${SORT_LABELS[mode]}
    </button>`).join('');

    root.innerHTML = `
    ${hasAverages ? `
      <section class="chart-mini">
        <span class="chart-mini__label">Средняя оценка по кругам</span>
        <div class="chart-mini__wrap"><canvas id="roundsChart"></canvas></div>
      </section>` : ''}

    <div class="rounds-toolbar">
      <span class="rounds-toolbar__label">Сортировка</span>
      <div class="sort-group">${sortButtons}</div>
    </div>

    <div class="rounds-list">
      ${byNumber.map(roundSection).join('')}
    </div>`;

    if (hasAverages) {
        drawAveragesChart(document.querySelector('#roundsChart'), byNumber);
    }

    root.querySelectorAll('.sort-btn').forEach((btn) => {
        btn.addEventListener('click', () => {
            sortMode = btn.dataset.sort;
            render(root);
        });
    });

    root.querySelectorAll('.round').forEach((el) => {
        el.addEventListener('toggle', () => {
            const number = Number(el.dataset.round);
            if (el.open) openRounds.add(number);
            else openRounds.delete(number);
        });
    });
}

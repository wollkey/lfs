import { esc, posterSrc, pluralWith } from '../helpers.js';

let keys = null;

const NEXT = ['ArrowRight', 'ArrowDown', 'PageDown', ' ', 'Enter'];
const PREV = ['ArrowLeft', 'ArrowUp', 'PageUp'];

export async function render(root, { year }) {
    keys?.abort();

    const response = await fetch(`/api/ceremony/${encodeURIComponent(year)}`);
    if (response.status === 404) {
        root.innerHTML = `<p class="placeholder">В ${esc(year)} году клуб ещё ничего не оценил.</p>`;
        return;
    }
    if (!response.ok) throw new Error(`API статус ${response.status}`);
    const { nominations } = await response.json();

    root.innerHTML = `
    <section class="wrapped-lobby">
      <p class="story__eyebrow">Last Frame Society</p>
      <h1 class="wrapped-lobby__title">Церемония ${esc(year)}</h1>
      <p class="wrapped-lobby__lead">${pluralWith(nominations.length, ['номинация', 'номинации', 'номинаций'])} · листать стрелками, пробелом или кликером, F - во весь экран</p>
      <button class="wrapped-lobby__club ceremony-start">Начать</button>
    </section>
    <section class="ceremony" tabindex="-1"></section>`;

    const stage = root.querySelector('.ceremony');
    const steps = [
        { screen: 'intro' },
        ...nominations.flatMap((nomination) => [0, 1, 2].map((phase) => ({ screen: 'nomination', nomination, phase }))),
        { screen: 'outro' },
    ];
    let index = 0;

    const show = (next) => {
        index = Math.max(0, Math.min(steps.length - 1, next));
        stage.innerHTML = screen(year, steps[index]);
    };

    root.querySelector('.ceremony-start').addEventListener('click', () => {
        stage.classList.add('ceremony--on');
        stage.requestFullscreen?.().catch(() => {});
        stage.focus();
        show(0);
    });
    stage.addEventListener('click', () => show(index + 1));

    keys = new AbortController();
    document.addEventListener('keydown', (event) => {
        if (!stage.isConnected) {
            keys.abort();
            return;
        }
        if (!stage.classList.contains('ceremony--on')) return;

        if (NEXT.includes(event.key)) show(index + 1);
        else if (PREV.includes(event.key)) show(index - 1);
        else if (event.key === 'Escape') stage.classList.remove('ceremony--on');
        else if (event.key === 'f' || event.key === 'а') {
            if (document.fullscreenElement) document.exitFullscreen();
            else stage.requestFullscreen?.().catch(() => {});
        } else return;
        event.preventDefault();
    }, { signal: keys.signal });
}

function screen(year, step) {
    if (step.screen === 'intro') {
        return `
        <div class="ceremony__center">
          <p class="ceremony__eyebrow">Last Frame Society</p>
          <h1 class="ceremony__title">Церемония ${esc(year)}</h1>
          <p class="ceremony__lead">Итоги года клуба</p>
        </div>`;
    }

    if (step.screen === 'outro') {
        return `
        <div class="ceremony__center">
          <p class="ceremony__eyebrow">Last Frame Society · ${esc(year)}</p>
          <h1 class="ceremony__title">Спасибо за год</h1>
          <p class="ceremony__lead">До встречи на следующей церемонии</p>
        </div>`;
    }

    const { nomination, phase } = step;
    const heading = `
      <p class="ceremony__eyebrow">${esc(year)} · Номинация</p>
      <h1 class="ceremony__title">${esc(nomination.title)}</h1>
      ${nomination.lead ? `<p class="ceremony__lead">${esc(nomination.lead)}</p>` : ''}`;

    if (phase === 0) return `<div class="ceremony__center">${heading}</div>`;

    const revealed = phase === 2;
    const cards = [...nomination.nominees]
        .sort((a, b) => a.name.localeCompare(b.name, 'ru'))
        .map((nominee) => nomineeCard(nominee, revealed))
        .join('');

    return `
    <div class="ceremony__top">${heading}</div>
    <ul class="ceremony__nominees ${revealed ? 'ceremony__nominees--revealed' : ''}">${cards}</ul>`;
}

function nomineeCard(nominee, revealed) {
    const picture = nominee.slug
        ? `<img class="ceremony__poster" src="${posterSrc(nominee.slug)}" alt="">`
        : `<span class="ceremony__avatar" aria-hidden="true">${esc(initials(nominee.name))}</span>`;
    const winner = revealed && nominee.winner;

    return `
    <li class="ceremony__nominee ${winner ? 'ceremony__nominee--winner' : ''}">
      ${picture}
      <span class="ceremony__name">${esc(nominee.name)}</span>
      ${nominee.detail ? `<span class="ceremony__detail">${esc(nominee.detail)}</span>` : ''}
      ${revealed && nominee.value ? `<span class="ceremony__value">${esc(nominee.value)}</span>` : ''}
      ${winner ? '<span class="ceremony__badge">Победитель</span>' : ''}
    </li>`;
}

function initials(name) {
    return name.split(' и ').map((part) => part.trim().charAt(0).toUpperCase()).join('·');
}

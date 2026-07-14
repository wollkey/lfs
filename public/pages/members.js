import { letterboxdLink } from '../helpers.js';

function memberRow(member) {
    const avg = member.averageGiven === null ? '—' : member.averageGiven;
    return `
    <li class="member">
      <div class="member__id">
        <span class="member__name">${member.displayName}</span>
        ${letterboxdLink(member.username)}
      </div>
      <div class="member__stats">
        <span class="member__watched">${member.watched} watched</span>
        <span class="member__avg">${avg}</span>
      </div>
    </li>`;
}

function group(title, members) {
    if (members.length === 0) return '';
    const rows = members.map(memberRow).join('');
    return `
    <section class="member-group">
      <h2 class="member-group__title">${title}</h2>
      <ul class="member-list">${rows}</ul>
    </section>`;
}

function drawAverageChart(canvas, members) {
    const rated = members
        .filter((m) => m.averageGiven !== null)
        .sort((a, b) => b.averageGiven - a.averageGiven);

    new Chart(canvas, {
        type: 'bar',
        data: {
            labels: rated.map((m) => m.displayName),
            datasets: [{
                label: 'Average rating given',
                data: rated.map((m) => m.averageGiven),
                backgroundColor: '#e0a04d',
                borderRadius: 4,
            }],
        },
        options: {
            maintainAspectRatio: false,
            scales: {
                y: { beginAtZero: true, max: 10, ticks: { color: '#8a8a92', stepSize: 5 }, grid: { color: '#26262c' } },
                x: { ticks: { color: '#8a8a92', font: { size: 10 } }, grid: { display: false } },
            },
            plugins: { legend: { display: false } },
        },
    });
}

export async function render(root) {
    root.innerHTML = 'Loading…';
    const response = await fetch('/api/members');
    if (!response.ok) throw new Error(`API статус ${response.status}`);
    const data = await response.json();

    const active = data.members.filter((m) => m.status === 'active');
    const former = data.members.filter((m) => m.status === 'former');
    const hasRatings = data.members.some((m) => m.averageGiven !== null);

    root.innerHTML = `
    ${hasRatings ? `
      <section class="chart-mini">
        <span class="chart-mini__label">Harsh vs generous</span>
        <div class="chart-mini__wrap"><canvas id="avgChart"></canvas></div>
      </section>` : ''}
    ${group('Current members', active)}
    ${group('Former members', former)}`;

    if (hasRatings) {
        drawAverageChart(document.querySelector('#avgChart'), data.members);
    }
}

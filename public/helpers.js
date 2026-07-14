export function letterboxdUrl(username) {
    return `https://letterboxd.com/${username}/`;
}

export function letterboxdLink(username, label = `@${username}`) {
    return `<a class="lb-link" href="${letterboxdUrl(username)}" target="_blank" rel="noopener">${label}</a>`;
}

export function posterImg(film, className = '') {
    const cls = className ? `poster ${className}` : 'poster';
    return `
    <img class="${cls}" src="/posters/${film.slug}.jpg"
         alt="${film.title}" width="125" height="187"
         onerror="this.onerror=null; this.src='/posters/_placeholder.svg'">`;
}

const pluralRules = new Intl.PluralRules('ru-RU');

export function plural(n, forms) {
    const category = pluralRules.select(n);
    const index = { one: 0, few: 1, many: 2, other: 2 }[category];
    return forms[index];
}

export function pluralWith(n, forms) {
    return `${n} ${plural(n, forms)}`;
}
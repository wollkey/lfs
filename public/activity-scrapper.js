(async () => {
    const usernames = [
        'wollkey',
        'lenka_penka',
        'christallisme',
        'psy667',
        'al1vka',
        'atomic_rage',
        'nickbiryukov',
        'vans_von_trier',
        'vika',
        'alinafrolova',
    ];

    const dir = await window.showDirectoryPicker({mode: 'readwrite'});

    const sleep = ms => new Promise(r => setTimeout(r, ms));
    const failed = [];

    for (const [i, username] of usernames.entries()) {
        try {
            const res = await fetch(`https://letterboxd.com/ajax/activity-pagination/${username}/`, {
                credentials: 'include',
            });

            if (res.status === 429 || res.status === 403) {
                console.warn(`Стоп на ${username}: ${res.status}`);
                break;
            }
            if (!res.ok) {
                failed.push(username);
                continue;
            }

            const html = await res.text();

            const fh = await dir.getFileHandle(`${username}.html`, {create: true});
            const w = await fh.createWritable();
            await w.write(html);
            await w.close();

            console.log(`${i + 1}/${usernames.length} ${username}`);
        } catch (e) {
            failed.push(username);
            console.error(username, e);
        }

        await sleep(2000 + Math.random() * 1500);
    }

    console.log('Готово. Не удалось:', failed);
})();

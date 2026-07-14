(async () => {
    const slugs = [
        'citizen-kane',
        'mulholland-drive',
        'vertigo',
        'jeanne-dielman-23-quai-du-commerce-1080-bruxelles',
        'tokyo-story',
        'in-the-mood-for-love',
        'the-rules-of-the-game',
        'my-friend-ivan-lapshin',
        'spirited-away',
        'man-with-a-movie-camera',
        'adams-apples',
        'amores-perros',
        'heartbeats',
        'city-of-god',
        'drunken-master',
        'song-of-the-sea',
        'dallas-buyers-club',
        'the-number-23',
        'dogville',
        'nymphomaniac-volume-i',
        'earth-1930',
        'moonlight-2016',
        'seven-samurai',
        'yi-yi',
        '2001-a-space-odyssey',
        'some-like-it-hot',
        'barry-lyndon',
        'andrei-rublev',
        'pink-flamingos',
        'blade-runner',
        'im-a-cyborg-but-thats-ok',
        'das-boot',
        'chungking-express',
        'the-godfather',
        'star-wars',
        'apocalypse-now',
        'its-a-wonderful-life',
        'brother-1997',
        'the-color-of-pomegranates',
        'there-will-be-blood',
        'everybody-hates-johan',
        'the-cranes-are-flying',
        'incendies',
        'la-dolce-vita',
        'rashomon',
        'chinatown',
        'the-matrix',
        'mirror',
        'the-seventh-seal',
        'persona',
        '12-angry-men',
        'being-john-malkovich',
        'taxi-driver',
        'the-good-the-bad-and-the-ugly',
        'orlando',
        'vagabond',
        'stalker',
        'drive-2011',
    ];

    const dir = await window.showDirectoryPicker({mode: 'readwrite'});

    const sleep = ms => new Promise(r => setTimeout(r, ms));
    const failed = [];

    for (const [i, slug] of slugs.entries()) {
        try {
            const res = await fetch(`https://letterboxd.com/wollkey/friends/film/${slug}/`, {
                credentials: 'include',
            });

            if (res.status === 429 || res.status === 403) {
                console.warn(`Стоп на ${slug}: ${res.status}`);
                break;
            }
            if (!res.ok) {
                failed.push(slug);
                continue;
            }

            const html = await res.text();

            const fh = await dir.getFileHandle(`${slug}.html`, {create: true});
            const w = await fh.createWritable();
            await w.write(html);
            await w.close();

            console.log(`${i + 1}/${slugs.length} ${slug}`);
        } catch (e) {
            failed.push(slug);
            console.error(slug, e);
        }

        await sleep(2000 + Math.random() * 1500);
    }

    console.log('Готово. Не удалось:', failed);
})();

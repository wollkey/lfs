ALTER TABLE films
    ADD COLUMN release_year INTEGER NULL;
ALTER TABLE films
    ADD COLUMN runtime INTEGER NULL;
ALTER TABLE films
    ADD COLUMN letterboxd_rating REAL NULL;

CREATE TABLE film_tags
(
    film_slug TEXT NOT NULL REFERENCES films (slug),
    kind      TEXT NOT NULL CHECK (kind IN ('director', 'genre', 'country')),
    name      TEXT NOT NULL,
    PRIMARY KEY (film_slug, kind, name)
);

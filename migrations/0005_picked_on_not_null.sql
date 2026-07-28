CREATE TABLE round_films_new
(
    round_number INTEGER NOT NULL REFERENCES rounds (number),
    film_slug    TEXT    NOT NULL REFERENCES films (slug),
    picked_by    TEXT    NULL REFERENCES members (username),
    position     INTEGER NOT NULL DEFAULT 0,
    picked_on    TEXT    NOT NULL,
    PRIMARY KEY (round_number, film_slug)
);

INSERT INTO round_films_new (round_number, film_slug, picked_by, position, picked_on)
SELECT round_number, film_slug, picked_by, position, picked_on
FROM round_films;

DROP TABLE round_films;

ALTER TABLE round_films_new
    RENAME TO round_films;

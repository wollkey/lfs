CREATE TABLE reviews
(
    film_slug           TEXT    NOT NULL REFERENCES films (slug),
    member_username     TEXT    NOT NULL REFERENCES members (username),
    body                TEXT    NOT NULL,
    written_on          TEXT    NULL,
    source              TEXT    NOT NULL DEFAULT 'manual' CHECK (source IN ('manual', 'telegram')),
    telegram_message_id INTEGER NULL,
    PRIMARY KEY (film_slug, member_username)
);

INSERT INTO reviews (film_slug, member_username, body, source)
SELECT film_slug, member_username, TRIM(review), 'manual'
FROM ratings
WHERE review IS NOT NULL
  AND TRIM(review) <> '';

ALTER TABLE ratings
    DROP COLUMN review;

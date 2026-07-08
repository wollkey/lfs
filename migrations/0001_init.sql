CREATE TABLE members
(
    username     TEXT PRIMARY KEY,
    display_name TEXT NOT NULL,
    status       TEXT NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'former'))
);

CREATE TABLE films
(
    slug  TEXT PRIMARY KEY,
    title TEXT NOT NULL
);

CREATE TABLE rounds
(
    number     INTEGER PRIMARY KEY,
    started_on TEXT,
    ended_on   TEXT
);

CREATE TABLE round_films
(
    round_number INTEGER NOT NULL REFERENCES rounds (number),
    film_slug    TEXT    NOT NULL REFERENCES films (slug),
    picked_by    TEXT    NOT NULL REFERENCES members (username),
    PRIMARY KEY (round_number, film_slug),
    UNIQUE (round_number, picked_by)
);

CREATE TABLE ratings
(
    film_slug       TEXT    NOT NULL REFERENCES films (slug),
    member_username TEXT    NOT NULL REFERENCES members (username),
    score           INTEGER NOT NULL CHECK (score BETWEEN 1 AND 10),
    PRIMARY KEY (film_slug, member_username)
);

ALTER TABLE members
    ADD COLUMN telegram_user_id INTEGER NULL;

CREATE UNIQUE INDEX members_telegram_user_id ON members (telegram_user_id);

ALTER TABLE round_films
    ADD COLUMN announcement_message_id INTEGER NULL;

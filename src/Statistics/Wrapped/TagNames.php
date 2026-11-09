<?php

declare(strict_types=1);

namespace App\Statistics\Wrapped;

/**
 * Letterboxd names its genres and countries in English; the club reads Russian.
 * A name missing here is shown as Letterboxd gave it.
 */
final class TagNames
{
    private const array GENRES = [
        'Action' => 'Боевик',
        'Adventure' => 'Приключения',
        'Animation' => 'Анимация',
        'Comedy' => 'Комедия',
        'Crime' => 'Криминал',
        'Documentary' => 'Документальное',
        'Drama' => 'Драма',
        'Family' => 'Семейное',
        'Fantasy' => 'Фэнтези',
        'History' => 'История',
        'Horror' => 'Ужасы',
        'Music' => 'Музыка',
        'Mystery' => 'Детектив',
        'Romance' => 'Мелодрама',
        'Science Fiction' => 'Фантастика',
        'Thriller' => 'Триллер',
        'TV Movie' => 'Телефильм',
        'War' => 'Военное',
        'Western' => 'Вестерн',
    ];

    private const array COUNTRIES = [
        'Argentina' => 'Аргентина',
        'Australia' => 'Австралия',
        'Austria' => 'Австрия',
        'Belgium' => 'Бельгия',
        'Brazil' => 'Бразилия',
        'Canada' => 'Канада',
        'China' => 'Китай',
        'Czechia' => 'Чехия',
        'Czechoslovakia' => 'Чехословакия',
        'Denmark' => 'Дания',
        'East Germany' => 'ГДР',
        'Finland' => 'Финляндия',
        'France' => 'Франция',
        'Georgia' => 'Грузия',
        'Germany' => 'Германия',
        'Greece' => 'Греция',
        'Hong Kong' => 'Гонконг',
        'Hungary' => 'Венгрия',
        'Iceland' => 'Исландия',
        'India' => 'Индия',
        'Iran' => 'Иран',
        'Ireland' => 'Ирландия',
        'Israel' => 'Израиль',
        'Italy' => 'Италия',
        'Japan' => 'Япония',
        'Luxembourg' => 'Люксембург',
        'Mexico' => 'Мексика',
        'Netherlands' => 'Нидерланды',
        'New Zealand' => 'Новая Зеландия',
        'Norway' => 'Норвегия',
        'Philippines' => 'Филиппины',
        'Poland' => 'Польша',
        'Portugal' => 'Португалия',
        'Romania' => 'Румыния',
        'Russia' => 'Россия',
        'South Korea' => 'Южная Корея',
        'Spain' => 'Испания',
        'Sweden' => 'Швеция',
        'Switzerland' => 'Швейцария',
        'Taiwan' => 'Тайвань',
        'Turkey' => 'Турция',
        'UK' => 'Великобритания',
        'Ukraine' => 'Украина',
        'USA' => 'США',
        'USSR' => 'СССР',
        'West Germany' => 'ФРГ',
        'Yugoslavia' => 'Югославия',
    ];

    public static function genre(string $name): string
    {
        return self::GENRES[$name] ?? $name;
    }

    public static function country(string $name): string
    {
        return self::COUNTRIES[$name] ?? $name;
    }
}

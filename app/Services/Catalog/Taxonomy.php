<?php

namespace App\Services\Catalog;

class Taxonomy
{
    public const CATEGORIES = [
        'drinkware' => 'Посуда и термокружки',
        'apparel' => 'Одежда и текстиль',
        'bags' => 'Сумки и рюкзаки',
        'office' => 'Для офиса',
        'tech' => 'Электроника',
        'sets' => 'Подарочные наборы',
        'leisure' => 'Отдых и путешествия',
        'accessories' => 'Аксессуары',
    ];

    public static function detect(string $text): string
    {
        $rules = [
            'sets' => '/набор|комплект|подарочн.*бокс/ui',
            'drinkware' => '/кружк|термос|бутыл|стакан|посуд|чашк|бокал|фляг|ланч|тарел|контейнер|чайник/ui',
            'bags' => '/рюкзак|сумк|шоппер|портфел|чемодан|мешок|косметичк/ui',
            'apparel' => '/футболк|толстовк|худи|поло|кепк|бейсболк|куртк|жилет|плед|шарф|полотенц|панам|шапк|свитшот|рубашк|зонт/ui',
            'tech' => '/заряд|power.?bank|аккумулятор|колонк|наушник|флеш|usb|кабел|мышь|гаджет|ламп|светильник|час/ui',
            'office' => '/блокнот|ежедневник|ручк|карандаш|записн|календар|папк|стикер|бумаг|линейк/ui',
            'leisure' => '/туризм|путешеств|спорт|мяч|пикник|игр|велосипед|пляж|коврик/ui',
        ];
        foreach ($rules as $slug => $pattern) {
            if (preg_match($pattern, $text)) {
                return $slug;
            }
        }

        return 'accessories';
    }

    public static function text(mixed $value): string
    {
        return trim(html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    public static function image(mixed $url): ?string
    {
        if (! is_string($url)) {
            return null;
        }
        if (str_starts_with($url, '//')) {
            $url = 'https:'.$url;
        }

        return filter_var($url, FILTER_VALIDATE_URL) && in_array(parse_url($url, PHP_URL_SCHEME), ['https', 'http']) ? $url : null;
    }
}

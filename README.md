## Парсер цен с Ozon
- Собирает данные о товарах (название, цена).
- Сохраняет в CSV.
- Использует: PHP, DOM-Crawler, League\CSV.

🔧 Как это запустить:
1.Установи библиотеки (если нет Composer):

composer require symfony/dom-crawler league/csv

2.Запусти парсер:

php ozon_parser.php

3.Результат:
В папке появится файл ozon_prices.csv с данными (открой его в Excel или Google Sheets).

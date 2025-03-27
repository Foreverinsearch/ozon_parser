<?php
// Парсер цен с Ozon (упрощённый пример)
require 'vendor/autoload.php'; // Подключаем библиотеки

use Symfony\Component\DomCrawler\Crawler;
use League\Csv\Writer;

// 1. Указываем URL страницы Ozon (например, "Ноутбуки")
$url = "https://www.ozon.ru/category/noutbuki-15692/";

// 2. Загружаем HTML (используем file_get_contents или CURL)
$html = file_get_contents($url);
if (!$html) {
    die("Не удалось загрузить страницу. Проверь URL или подключение к интернету.");
}

// 3. Парсим данные
$crawler = new Crawler($html);
$products = [];

$crawler->filter('.ui9')->each(function (Crawler $node) use (&$products) {
    $name = $node->filter('.ui9 span')->text(); // Название товара
    $price = $node->filter('.ui9 .ui7')->text(); // Цена
    
    // Очищаем данные
    $price = preg_replace('/[^0-9]/', '', $price);
    
    $products[] = [
        'name' => $name,
        'price' => $price
    ];
});

// 4. Сохраняем в CSV
$csv = Writer::createFromPath('ozon_prices.csv', 'w+');
$csv->insertOne(['Название', 'Цена (руб)']); // Заголовки

foreach ($products as $product) {
    $csv->insertOne([$product['name'], $product['price']]);
}

echo "Готово! Данные сохранены в ozon_prices.csv";
?>
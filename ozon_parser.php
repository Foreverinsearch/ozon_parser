<?php
require 'vendor/autoload.php';

use Symfony\Component\DomCrawler\Crawler;
use League\Csv\Writer;

// ==================== Конфигурация ====================
const PROXY_LIST = [
    '123.123.123.123:3128', // Заменить на реальные прокси
    '111.222.111.222:8080'
];
const TELEGRAM_BOT_TOKEN = 'YOUR_BOT_TOKEN';
const TELEGRAM_CHAT_ID = 'YOUR_CHAT_ID';
const OZON_URLS = [
    'https://www.ozon.ru/category/noutbuki-15692/',
    'https://www.ozon.ru/category/smartfony-15502/'
];
const USER_AGENTS = [
    'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)'
];
// ======================================================

// Настройка логов
set_error_handler(function($errno, $errstr) {
    logError($errstr);
    throw new Exception($errstr);
});

try {
    $startTime = microtime(true);
    logMessage("Запуск парсера");

    $products = parseMultiPages(OZON_URLS);
    saveToCsv($products);
    saveToJson($products);
    sendTelegramReport(count($products), $startTime);

    logMessage("Парсинг успешно завершен");
    echo "Готово! Проверьте файлы:\n- ozon_prices.csv\n- ozon_prices.json";

} catch (Exception $e) {
    logError("Критическая ошибка: " . $e->getMessage());
    die("Ошибка! Подробности в логах (parser.log)");
}

// ==================== Основные функции ====================

function parseMultiPages(array $urls): array {
    $mh = curl_multi_init();
    $handles = [];
    $products = [];

    foreach ($urls as $i => $url) {
        $handles[$i] = curl_init($url);
        curl_setopt_array($handles[$i], [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_PROXY => PROXY_LIST[array_rand(PROXY_LIST)],
            CURLOPT_USERAGENT => USER_AGENTS[array_rand(USER_AGENTS)],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true
        ]);
        curl_multi_add_handle($mh, $handles[$i]);
    }

    do {
        curl_multi_exec($mh, $running);
        curl_multi_select($mh);
    } while ($running > 0);

    foreach ($handles as $handle) {
        $html = curl_multi_getcontent($handle);
        $products = array_merge($products, parsePage($html));
        curl_multi_remove_handle($mh, $handle);
        curl_close($handle);
    }

    curl_multi_close($mh);
    return $products;
}

function parsePage(string $html): array {
    $crawler = new Crawler($html);
    $pageProducts = [];

    $crawler->filter('.ui9')->each(function (Crawler $node) use (&$pageProducts) {
        try {
            $name = $node->filter('.ui9 span')->text();
            $price = $node->filter('.ui9 .ui7')->text();
            $price = preg_replace('/[^0-9]/', '', $price);

            $pageProducts[] = [
                'name' => trim($name),
                'price' => (int)$price,
                'timestamp' => time()
            ];
        } catch (Exception $e) {
            logError("Ошибка парсинга товара: " . $e->getMessage());
        }
    });

    return $pageProducts;
}

function saveToCsv(array $products): void {
    $csv = Writer::createFromPath('ozon_prices.csv', 'w+');
    $csv->insertOne(['Название', 'Цена (руб)', 'Дата']);

    foreach ($products as $product) {
        $csv->insertOne([
            $product['name'],
            $product['price'],
            date('Y-m-d H:i:s', $product['timestamp'])
        ]);
    }

    // Добавляем график цен
    $chartUrl = 'https://chart.googleapis.com/chart?cht=lc&chs=800x300&chd=t:'.
        implode(',', array_column($products, 'price')).'&chds=a&chxt=x,y&chxl=0:|'.implode('|', range(1, count($products))).'&chco=FF0000';
    $csv->insertOne(['', '=HYPERLINK("'.$chartUrl.'", "График цен")', '']);
}

function saveToJson(array $products): void {
    $jsonData = json_encode([
        'metadata' => [
            'source' => 'Ozon',
            'parsed_at' => date('c'),
            'count' => count($products)
        ],
        'products' => $products
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

    file_put_contents('ozon_prices.json', $jsonData);
}

function sendTelegramReport(int $count, float $startTime): void {
    $duration = round(microtime(true) - $startTime, 2);
    $message = "✅ Парсинг Ozon завершен\n";
    $message .= "🕒 Время выполнения: {$duration} сек\n";
    $message .= "📊 Товаров получено: {$count}\n";
    $message .= "📆 " . date('Y-m-d H:i:s');

    $url = "https://api.telegram.org/bot".TELEGRAM_BOT_TOKEN."/sendMessage?" . http_build_query([
        'chat_id' => TELEGRAM_CHAT_ID,
        'text' => $message
    ]);

    file_get_contents($url);
}

// ==================== Вспомогательные функции ====================

function logMessage(string $message): void {
    file_put_contents('parser.log', date('[Y-m-d H:i:s] ') . $message . "\n", FILE_APPEND);
}

function logError(string $error): void {
    file_put_contents('parser.log', date('[Y-m-d H:i:s] ERROR: ') . $error . "\n", FILE_APPEND);
}

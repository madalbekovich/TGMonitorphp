<?php
require 'vendor/autoload.php';

use danog\MadelineProto\API;
use danog\MadelineProto\Logger;
use danog\MadelineProto\Settings;

// Настройки
$settings = new Settings;
$settings->getLogger()->setLevel(Logger::LEVEL_ULTRA_VERBOSE);

$madeline = new API('session.madeline', $settings);

// Ваши данные API
$apiId = 12345; // Замените на ваш API ID
$apiHash = 'ваш API'; // Замените на ваш API Hash

// ID вашего канала для пересылки (например, @my_channel)
$targetChannel = '';

// ID каналов/групп для мониторинга

    $sourceChats = [
    'sotka_dnr_lnr',
    'AvtoRynok_DNR',
    'DNR_AVTO_180',
    'CarMarketDNR',
    'auto_donetskv'
];


// Ключевые слова для поиска (как отдельные слова)
$keywords = ['здесь ключи', '', ];

try {
    $madeline->start();

    $me = $madeline->getSelf();
    echo "Авторизован как: {$me['username']}\n";

    foreach ($sourceChats as $chat) {
        echo "Проверяем канал: $chat\n";
        
        try {
            $messages = $madeline->messages->getHistory([
                'peer' => $chat,
                'limit' => 100
            ]);
            
            foreach ($messages['messages'] as $message) {
                if (!isset($message['message']) || empty($message['message'])) {
                    continue;
                }
                
                $text = $message['message'];
                $foundKeywords = [];
                
                // Проверяем на ключевые слова как отдельные слова
                foreach ($keywords as $keyword) {
                    // Регулярное выражение для поиска слова как отдельного
                    if (preg_match("/\b" . preg_quote($keyword, '/') . "\b/ui", $text)) {
                        $foundKeywords[] = $keyword;
                    }
                }
                
                if (!empty($foundKeywords)) {
                    echo "Найдено ключевое слово: " . implode(', ', $foundKeywords) . " в сообщении: $text\n";
                    if (isset($message['media']) && $message['media']['_'] === 'messageMediaPhoto') {
                        $madeline->messages->forwardMessages([
                            'from_peer' => $chat,
                            'to_peer' => $targetChannel,
                            'id' => [$message['id']],
                            'drop_author' => false,
                            'drop_media_captions' => false,
                            'with_my_score' => false
                        ]);
                        
                        echo "Сообщение с медиа переслано\n";
                    } else {
                        // Если нет медиа, просто пересылаем текст
                        $madeline->messages->sendMessage([
                            'peer' => $targetChannel,
                            'message' => "Ключевые слова: " . implode(', ', $foundKeywords) . "\n\n" . $text
                        ]);
                        
                        echo "Текстовое сообщение переслано\n";
                    }
                }
            }
        } catch (Exception $e) {
            echo "Ошибка при обработке канала $chat: " . $e->getMessage() . "\n";
        }
    }
    
    echo "Скрипт завершил работу\n";
} catch (Exception $e) {
    echo "Ошибка: " . $e->getMessage() . "\n";
}
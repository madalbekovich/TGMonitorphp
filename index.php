<?php declare(strict_types=1);

require 'vendor/autoload.php';

use danog\MadelineProto\API;
use danog\MadelineProto\EventHandler;
use danog\MadelineProto\EventHandler\Message;
use danog\MadelineProto\EventHandler\Media\Photo;
use danog\MadelineProto\Settings;
use danog\MadelineProto\Settings\AppInfo;
use danog\MadelineProto\Logger;
use App\Env;

// Загружаем настройки из .env
Env::load();

class ForwardBot extends EventHandler
{
    private array $sourceChannels;
    private string $targetChannel;
    private array $keywords;
    private array $lastCheck = [];
    private array $processedGroups = [];

    public function onStart(): void
    {
        // Получаем список каналов из .env
        $this->sourceChannels = explode(',', Env::get('SOURCE_CHANNELS'));
        $this->targetChannel = Env::get('TARGET_CHANNEL');
        $this->keywords = explode(',', Env::get('KEYWORDS'));
        
        $this->logger("Бот запущен");
        $this->logger("Мониторинг каналов: " . implode(', ', $this->sourceChannels));
        $this->logger("Целевой канал: " . $this->targetChannel);
        $this->logger("Ключевые слова: " . implode(', ', $this->keywords));
        
        // Проверяем доступ к каналам
        foreach ($this->sourceChannels as $channel) {
            try {
                $info = $this->getInfo($channel);
                $this->logger("Доступ к каналу $channel: OK");
            } catch (\Exception $e) {
                $this->logger("Ошибка доступа к каналу $channel: " . $e->getMessage());
            }
        }
    }

    private function containsKeywords(string $text): bool
    {
        // Приводим текст к нижнему регистру для сравнения
        $textLower = mb_strtolower($text, 'UTF-8');
        
        foreach ($this->keywords as $keyword) {
            // Приводим ключевое слово к нижнему регистру
            $keywordLower = mb_strtolower($keyword, 'UTF-8');
            
            // Проверяем наличие ключевого слова в тексте без учета регистра
            if (mb_stripos($text, $keyword) !== false) {
                $this->logger("Найдено ключевое слово: $keyword");
                return true;
            }
            
            // Проверяем варианты с ё/е
            if (mb_strpos($keyword, 'ё') !== false) {
                $keywordWithE = str_replace('ё', 'е', $keywordLower);
                if (mb_stripos($text, $keywordWithE) !== false) {
                    $this->logger("Найдено ключевое слово (вариант с е): $keyword");
                    return true;
                }
            }
            
            if (mb_strpos($keyword, 'е') !== false) {
                $keywordWithYo = str_replace('е', 'ё', $keywordLower);
                if (mb_stripos($text, $keywordWithYo) !== false) {
                    $this->logger("Найдено ключевое слово (вариант с ё): $keyword");
                    return true;
                }
            }
        }
        
        $this->logger("Текст сообщения: $text");
        return false;
    }

    public function onUpdateNewChannelMessage(array $update): void
    {
        $this->logger("Получено новое сообщение из канала");
        $this->onUpdateNewMessage($update);
    }

    public function onUpdateNewMessage(array $update): void
    {
        if ($update['message']['_'] === 'messageEmpty') {
            return;
        }

        $message = $update['message'];
        
        try {
            // Получаем информацию о канале
            $chat = $this->getInfo($message['peer_id']);
            $channelUsername = null;
            
            if (isset($chat['Chat']['username'])) {
                $channelUsername = '@' . $chat['Chat']['username'];
            } elseif (isset($chat['Channel']['username'])) {
                $channelUsername = '@' . $chat['Channel']['username'];
            }
            
            if (!$channelUsername) {
                $this->logger("Не удалось получить username канала");
                return;
            }

            $this->logger("Сообщение из канала: $channelUsername");

            // Проверяем, что сообщение из нужного нам канала
            if (!in_array($channelUsername, $this->sourceChannels)) {
                $this->logger("Канал $channelUsername не в списке мониторинга");
                return;
            }

            // Проверяем наличие ключевых слов
            $messageText = $message['message'] ?? '';
            if (!$this->containsKeywords($messageText)) {
                $this->logger("Сообщение не содержит ключевых слов");
                return;
            }

            // Проверяем, не обрабатывали ли мы уже эту группу
            if (isset($message['grouped_id'])) {
                if (in_array($message['grouped_id'], $this->processedGroups)) {
                    $this->logger("Группа уже обработана: " . $message['grouped_id']);
                    return;
                }
                $this->processedGroups[] = $message['grouped_id'];

                // Получаем историю сообщений, чтобы найти все сообщения из этой группы
                $this->logger("Получение всех сообщений из группы: " . $message['grouped_id']);
                
                // Получаем историю сообщений для поиска всех сообщений из этой медиа-группы
                $history = $this->messages->getHistory([
                    'peer' => $message['peer_id'],
                    'limit' => 50  // Берем последние 50 сообщений, чтобы найти все из группы
                ]);
                
                $messageIds = [];
                if (isset($history['messages']) && !empty($history['messages'])) {
                    $this->logger("История получена, количество сообщений: " . count($history['messages']));
                    $this->logger("ID текущего сообщения: " . $message['id'] . ", grouped_id: " . $message['grouped_id']);
                    
                    foreach ($history['messages'] as $historyMessage) {
                        // Проверяем, принадлежит ли сообщение этой группе
                        if (isset($historyMessage['grouped_id']) && $historyMessage['grouped_id'] == $message['grouped_id']) {
                            $messageIds[] = $historyMessage['id'];
                            $this->logger("Добавлен ID сообщения в группу: " . $historyMessage['id']);
                        }
                    }
                }

                if (!empty($messageIds)) {
                    $this->logger("Пересылка группы из " . count($messageIds) . " сообщений");
                    $this->messages->forwardMessages([
                        'from_peer' => $message['peer_id'],
                        'to_peer' => $this->targetChannel,
                        'id' => $messageIds,
                        'drop_author' => true,
                        'drop_media_captions' => false
                    ]);
                    $this->logger("Группа сообщений успешно переслана");
                    return;
                } else {
                    $this->logger("Не удалось найти сообщения для группы. Пробуем другой метод.");
                    
                    // Альтернативный метод - пробуем получить медиа-группу напрямую
                    try {
                        $messageIds = [$message['id']];
                        
                        // Получаем сообщения с тем же ID группы из последних 100
                        $additionalMessages = $this->messages->search([
                            'peer' => $message['peer_id'],
                            'limit' => 100,
                            'filter' => ['_' => 'inputMessagesFilterEmpty']
                        ]);
                        
                        if (isset($additionalMessages['messages'])) {
                            foreach ($additionalMessages['messages'] as $additionalMessage) {
                                if (isset($additionalMessage['grouped_id']) && 
                                    $additionalMessage['grouped_id'] == $message['grouped_id'] &&
                                    $additionalMessage['id'] != $message['id']) {
                                    $messageIds[] = $additionalMessage['id'];
                                    $this->logger("Альтернативный метод: добавлен ID " . $additionalMessage['id']);
                                }
                            }
                        }
                        
                        if (count($messageIds) > 1) {
                            $this->logger("Пересылка группы (альтернативный метод) из " . count($messageIds) . " сообщений");
                            $this->messages->forwardMessages([
                                'from_peer' => $message['peer_id'],
                                'to_peer' => $this->targetChannel,
                                'id' => $messageIds,
                                'drop_author' => true,
                                'drop_media_captions' => false
                            ]);
                            $this->logger("Группа сообщений успешно переслана (альтернативный метод)");
                            return;
                        }
                    } catch (\Exception $e) {
                        $this->logger("Ошибка при использовании альтернативного метода: " . $e->getMessage());
                    }
                }
            }

            // Если это не группа или не удалось получить группу, пересылаем одиночное сообщение
            $this->logger("Пересылка одиночного сообщения");
            $this->messages->forwardMessages([
                'from_peer' => $message['peer_id'],
                'to_peer' => $this->targetChannel,
                'id' => [$message['id']],
                'drop_author' => true,
                'drop_media_captions' => false
            ]);
            $this->logger("Сообщение успешно переслано");

        } catch (\Exception $e) {
            $this->logger("Ошибка при обработке сообщения: " . $e->getMessage());
        }
    }
}

$settings = new Settings;
$settings->getAppInfo()
    ->setApiId((int)Env::get('API_ID'))
    ->setApiHash(Env::get('API_HASH'));

$settings->getLogger()
    ->setLevel(Logger::LEVEL_VERBOSE)
    ->setType(Logger::ECHO_LOGGER);

ForwardBot::startAndLoop('session.madeline', $settings);
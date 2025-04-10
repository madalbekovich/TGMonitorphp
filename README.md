# Telegram Forward Bot

Бот для пересылки сообщений из нескольких каналов в один целевой канал.

## Установка

1. Установите PHP 8.0 или выше
2. Установите Composer
3. Выполните команду:
```bash
composer install
```

## Настройка

1. Получите API ID и API Hash на сайте https://my.telegram.org
2. Создайте файл `.env` в корневой директории проекта и заполните его:
```
API_ID=your_api_id
API_HASH=your_api_hash
TARGET_CHANNEL=@your_target_channel
SOURCE_CHANNELS=@channel1,@channel2,@channel3,@channel4,@channel5
KEYWORDS=Продаётся,продам,hyundai,Продается,Продаю,бензин,kia,ваз,Машина,Автомобиль,год,Год,Продам,Объем,Авто,Бот,Цена,цена,Продажа,автохимия,машина
```

```bash
php index.php
```

При первом запуске вам нужно будет авторизоваться в Telegram, введя код подтверждения.

## Функциональность

- Пересылает все сообщения из указанных каналов в целевой канал
- Логирует ошибки при пересылке
- Настройки хранятся в .env файле 
<?php

use App\Telegram\Handlers\HistoryHandler;
use App\Telegram\Handlers\PhotoHandler;
use SergiX44\Nutgram\Nutgram;

/** @var Nutgram $bot */

$bot->onCommand('start', function (Nutgram $bot) {
    $bot->sendMessage(
        text: "👋 Привет! Я помогу определить характеристики керамогранита.\n\nПросто отправь мне *фотографию* плитки, и я постараюсь определить:\n• Бренд и коллекцию\n• Размер и поверхность\n• Имитацию материала\n• Цвет и назначение\n\n📋 /history — история твоих запросов",
        parse_mode: 'Markdown'
    );
});

$bot->onCommand('history', HistoryHandler::class);

$bot->onPhoto(PhotoHandler::class);

$bot->onMessage(function (Nutgram $bot) {
    $bot->sendMessage("Отправь мне фотографию керамогранита 📷\n\n📋 /history — история запросов");
});

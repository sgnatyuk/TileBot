<?php

namespace App\Telegram\Handlers;

use App\Models\TileRequest;
use SergiX44\Nutgram\Nutgram;

class HistoryHandler
{
    public function __invoke(Nutgram $bot): void
    {
        $userId = $bot->user()->id;

        $requests = TileRequest::where('telegram_user_id', $userId)
            ->latest()
            ->take(10)
            ->get();

        if ($requests->isEmpty()) {
            $bot->sendMessage('У тебя пока нет сохранённых запросов. Отправь фото керамогранита!');
            return;
        }

        $bot->sendMessage(
            text: "🗂 *История запросов* (последние {$requests->count()}):",
            parse_mode: 'Markdown'
        );

        foreach ($requests as $request) {
            $date = $request->created_at->format('d.m.Y H:i');

            $caption = "📅 {$date}\n📝 {$request->description}\n\n{$request->full_result}";

            if ($request->comment) {
                $caption .= "\n\n💬 *Комментарий:* {$request->comment}";
            }

            $bot->sendPhoto(
                photo: $request->file_id,
                caption: $caption,
                parse_mode: 'Markdown'
            );
        }
    }
}

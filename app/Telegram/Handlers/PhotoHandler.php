<?php

namespace App\Telegram\Handlers;

use App\Models\TileRequest;
use App\Services\TileAnalyzerService;
use Gemini\Exceptions\ErrorException;
use SergiX44\Nutgram\Nutgram;

class PhotoHandler
{
    public function __construct(
        private TileAnalyzerService $analyzer
    ) {}

    public function __invoke(Nutgram $bot): void
    {
        $photos = $bot->message()->photo;

        if (empty($photos)) {
            $bot->sendMessage('Не удалось получить фото. Попробуй ещё раз.');
            return;
        }

        $photo = end($photos);
        $fileId = $photo->file_id;
        $user = $bot->user();
        $comment = $bot->message()->caption ?? null;

        $bot->sendMessage('Анализирую изображение... ⏳');

        try {
            $file = $bot->getFile($fileId);
            $fileUrl = "https://api.telegram.org/file/bot" . config('nutgram.token') . "/{$file->file_path}";

            $result = $this->analyzer->analyze($fileUrl);

            if (!$result->success) {
                $bot->sendMessage(
                    text: "❌ *Не удалось распознать керамогранит*\n\n{$result->reason}\n\nПопробуй отправить другое фото.",
                    parse_mode: 'Markdown'
                );
                return;
            }

            TileRequest::create([
                'telegram_user_id' => $user->id,
                'telegram_username' => $user->username ?? null,
                'file_id' => $fileId,
                'description' => $result->description,
                'full_result' => $result->fullResult,
                'comment' => $comment,
            ]);

            $text = "🔍 *Результат анализа керамогранита:*\n\n{$result->fullResult}";
            if ($comment) {
                $text .= "\n\n💬 *Твой комментарий:* {$comment}";
            }

            $bot->sendMessage(text: $text, parse_mode: 'Markdown');

        } catch (ErrorException $e) {
            report($e);
            if (str_contains($e->getMessage(), 'quota') || str_contains($e->getMessage(), 'rate')) {
                $bot->sendMessage('⏳ Слишком много запросов к ИИ. Подожди 1-2 минуты и попробуй снова.');
            } else {
                $bot->sendMessage('Произошла ошибка при анализе. Попробуй ещё раз или отправь другое фото.');
            }
        } catch (\Throwable $e) {
            report($e);
            $bot->sendMessage('Произошла ошибка при анализе. Попробуй ещё раз или отправь другое фото.');
        }
    }
}

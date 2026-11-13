<?php

namespace App\Services;

use Gemini;
use Gemini\Data\Blob;
use Gemini\Data\Content;
use Gemini\Enums\MimeType;
use Illuminate\Support\Facades\Http;

class TileAnalyzerService
{
    private const PROMPT = <<<'PROMPT'
    Ты эксперт по керамограниту с 20-летним опытом работы на российском рынке.
    Ты знаешь продукцию всех крупных производителей: Kerama Marazzi, Laparet, Ceramica Classic, Alma Ceramica,
    Cersanit, Atlas Concorde, Porcelanosa, Rex, Marazzi, Ragno, Emil Ceramica, Imola, Fondovalle, Cerim,
    Fap Ceramiche, Abk, Casa Dolce Casa, Brennero, Vives, Pamesa, Gayafores, Love Tiles, STN Ceramica,
    Grespania, Azulev, Keraben, Tau Ceramica, Rak Ceramics, Geotiles и других.

    Внимательно изучи изображение. Обрати внимание на:
    - Характерный рисунок и текстуру (у каждого бренда есть фирменный стиль коллекций)
    - Качество и детализацию печати
    - Стиль оформления (итальянский, испанский, российский и т.д.)
    - Любые видимые логотипы, маркировки, надписи на плитке или упаковке
    - Характерные паттерны конкретных известных коллекций

    Сначала определи: это изображение керамогранита или керамической плитки?

    Если НЕТ — верни строго такой JSON:
    {"success": false, "reason": "краткое объяснение на русском"}

    Если ДА — проанализируй и верни строго такой JSON:
    {
      "success": true,
      "description": "Краткое описание в одну строку: бренд/коллекция, цвет, поверхность (для истории)",
      "brand": "название бренда если уверен, или перечисли 2-3 наиболее вероятных бренда через '/', или 'Не определён'",
      "collection": "название коллекции если узнаваема, или 'Не определена'",
      "size": "формат плитки если виден масштаб или пропорции (например 60x60, 120x60), или 'Не определён'",
      "surface": "тип поверхности: полированная / матовая / лаппатированная / структурированная / сатинированная",
      "imitation": "имитация материала: мрамор / дерево / бетон / камень / цемент / металл / ткань / другое",
      "color": "основной цвет и подробное описание рисунка/текстуры",
      "usage": "пол / стена / универсальная / улица",
      "confidence": "высокий / средний / низкий"
    }

    Отвечай ТОЛЬКО валидным JSON, без markdown-блоков, без лишнего текста.
    PROMPT;

    public function analyze(string $imageUrl): AnalysisResult
    {
        $imageData = Http::get($imageUrl)->body();
        $base64 = base64_encode($imageData);

        $client = Gemini::client(config('services.gemini.key'));

        $response = $client->generativeModel('gemini-2.5-flash')->generateContent(
            Content::parse(part: [self::PROMPT, new Blob(
                mimeType: MimeType::IMAGE_JPEG,
                data: $base64,
            )])
        );

        $raw = trim($response->text());

        // Убираем markdown-блоки если модель всё же обернула
        $raw = preg_replace('/^```(?:json)?\s*/i', '', $raw);
        $raw = preg_replace('/\s*```$/i', '', $raw);

        $data = json_decode($raw, true);

        if (!is_array($data) || !isset($data['success'])) {
            return new AnalysisResult(success: false, reason: 'Не удалось разобрать ответ от ИИ.');
        }

        if (!$data['success']) {
            return new AnalysisResult(success: false, reason: $data['reason'] ?? 'Не удалось определить тип изображения.');
        }

        $fullResult = implode("\n", [
            "🏷 *Бренд:* {$data['brand']}",
            "📦 *Коллекция:* {$data['collection']}",
            "📐 *Размер:* {$data['size']}",
            "✨ *Поверхность:* {$data['surface']}",
            "🪨 *Имитация:* {$data['imitation']}",
            "🎨 *Цвет:* {$data['color']}",
            "🏠 *Применение:* {$data['usage']}",
            "📊 *Уверенность в бренде:* {$data['confidence']}",
        ]);

        return new AnalysisResult(
            success: true,
            fullResult: $fullResult,
            description: $data['description'],
        );
    }
}

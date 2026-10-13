<?php

namespace App\Http\Controllers;

use App\Jobs\HandleMaxUpdate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Вебхук бота MAX. Секрет приходит в заголовке X-Max-Bot-Api-Secret (его задаёт max:webhook-set).
 * На любое принятое событие отвечаем 200 сразу, обработка — после ответа или в очереди.
 * В лог пишем только тип события и id сообщения, без текста и вложений.
 */
class MaxWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $secret = (string) config('services.max.webhook_secret');
        $given = (string) ($request->header('X-Max-Bot-Api-Secret') ?? $request->query('secret', ''));

        if ($secret === '' || ! hash_equals($secret, $given)) {
            return response()->json(['ok' => false], 403);
        }

        $update = $request->json()->all();
        if (! is_array($update) || ! isset($update['update_type'])) {
            return response()->json(['ok' => true]);
        }

        Log::info('MAX update', [
            'type' => $update['update_type'],
            'mid' => $update['message']['body']['mid'] ?? null,
            'callback' => isset($update['callback']['callback_id']) ? 'yes' : null,
        ]);

        // По умолчанию — сразу после ответа MAX (без ожидания воркера раз в минуту); MAX_QUEUE=true — через очередь
        config('services.max.queue')
            ? HandleMaxUpdate::dispatch($update)
            : HandleMaxUpdate::dispatchAfterResponse($update);

        return response()->json(['ok' => true]);
    }
}

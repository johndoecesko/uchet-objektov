<?php

namespace App\Jobs;

use App\Support\Max\MaxBot;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Обработка события от MAX вне запроса вебхука */
class HandleMaxUpdate implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(public array $update) {}

    public function handle(MaxBot $bot): void
    {
        $bot->handle($this->update);
    }
}

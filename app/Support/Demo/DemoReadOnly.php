<?php

namespace App\Support\Demo;

use Symfony\Component\HttpKernel\Exception\HttpException;

/** Запись в демо (или от имени демо-пользователя) — запрещена */
class DemoReadOnly extends HttpException
{
    public function __construct(string $message = 'В демо можно только смотреть')
    {
        parent::__construct(403, $message);
    }
}

<?php

namespace App\Support\Social;

use RuntimeException;

/** Провайдер не отдал пользователя (отказ, просроченный код, сбой). Сообщение — для лога, пользователю — общее */
class SocialAuthFailed extends RuntimeException {}

<?php

namespace App\Support\Social;

/** Кого вернул провайдер. emailVerified — почта подтверждена провайдером: только по такой можно привязать существующую учётку */
final class SocialUser
{
    public function __construct(
        public readonly string $provider,
        public readonly string $id,
        public readonly ?string $email,
        public readonly bool $emailVerified,
        public readonly ?string $name,
    ) {}

    public function toArray(): array
    {
        return get_object_vars($this);
    }

    public static function fromArray(array $data): self
    {
        return new self($data['provider'], $data['id'], $data['email'], $data['emailVerified'], $data['name']);
    }
}

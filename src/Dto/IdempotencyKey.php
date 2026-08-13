<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class IdempotencyKey
{
    #[Assert\NotBlank(message: 'Header is required.')]
    #[Assert\Length(max: 255, maxMessage: 'Header must contain at most {{ limit }} characters.')]
    #[Assert\Regex(
        pattern: '/^[\x21-\x7E]+$/',
        message: 'Header must contain printable ASCII characters without spaces.',
    )]
    public string $value;

    public function __construct(string $value)
    {
        $this->value = trim($value);
    }
}

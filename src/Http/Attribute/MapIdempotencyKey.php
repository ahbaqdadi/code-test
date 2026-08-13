<?php

declare(strict_types=1);

namespace App\Http\Attribute;

use App\Http\ArgumentResolver\IdempotencyKeyValueResolver;
use Symfony\Component\HttpKernel\Attribute\ValueResolver;

#[\Attribute(\Attribute::TARGET_PARAMETER)]
final class MapIdempotencyKey extends ValueResolver
{
    public function __construct(public readonly string $header = 'Idempotency-Key')
    {
        parent::__construct(IdempotencyKeyValueResolver::class);
    }
}


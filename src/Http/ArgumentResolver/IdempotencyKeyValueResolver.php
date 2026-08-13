<?php

declare(strict_types=1);

namespace App\Http\ArgumentResolver;

use App\Dto\IdempotencyKey;
use App\Http\ApiProblemException;
use App\Http\Attribute\MapIdempotencyKey;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsTargetedValueResolver;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsTargetedValueResolver]
final readonly class IdempotencyKeyValueResolver implements ValueResolverInterface
{
    public function __construct(private ValidatorInterface $validator)
    {
    }

    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        $mapping = $argument->getAttributes(MapIdempotencyKey::class, ArgumentMetadata::IS_INSTANCEOF)[0] ?? null;
        if (!$mapping instanceof MapIdempotencyKey) {
            return [];
        }
        if ($argument->getType() !== IdempotencyKey::class) {
            throw new \LogicException(sprintf('#[%s] requires an %s argument.', MapIdempotencyKey::class, IdempotencyKey::class));
        }

        $key = new IdempotencyKey((string) $request->headers->get($mapping->header, ''));
        $violations = $this->validator->validate($key);
        if (count($violations) > 0) {
            $messages = [];
            foreach ($violations as $violation) {
                $messages[] = $violation->getMessage();
            }

            throw new ApiProblemException(
                422,
                'The request contains invalid data.',
                '/problems/validation-error',
                ['errors' => [$mapping->header => implode(' ', $messages)]],
            );
        }

        return [$key];
    }
}


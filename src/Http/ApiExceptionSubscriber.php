<?php

declare(strict_types=1);

namespace App\Http;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;

#[AsEventListener(event: 'kernel.exception')]
final class ApiExceptionSubscriber
{
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        $path = $event->getRequest()->getPathInfo();
        if (!str_starts_with($path, '/api/') && $path !== '/api' && $path !== '/health') {
            return;
        }

        if ($exception instanceof ApiProblemException) {
            $status = $exception->statusCode;
            $headers = [];
            $body = array_merge([
                'type' => $exception->type,
                'title' => $exception->getMessage(),
                'status' => $status,
            ], $exception->details);
        } elseif ($exception instanceof HttpExceptionInterface) {
            $status = $exception->getStatusCode();
            $headers = $exception->getHeaders();
            if ($status === 422 && $exception->getPrevious() instanceof ValidationFailedException) {
                $errors = [];
                foreach ($exception->getPrevious()->getViolations() as $violation) {
                    $path = $this->snakeCasePath($violation->getPropertyPath()) ?: 'payload';
                    $errors[$path][] = $violation->getMessage();
                }

                $body = [
                    'type' => '/problems/validation-error',
                    'title' => 'The request contains invalid data.',
                    'status' => $status,
                    'errors' => array_map(static fn (array $messages): string => implode(' ', $messages), $errors),
                ];
            } else {
                $body = [
                    'type' => 'about:blank',
                    'title' => Response::$statusTexts[$status] ?? 'Request failed',
                    'status' => $status,
                ];
            }
        } else {
            $status = 500;
            $headers = [];
            $body = [
                'type' => 'about:blank',
                'title' => 'Internal Server Error',
                'status' => $status,
            ];
            $this->logger->error('Unhandled API exception.', ['exception' => $exception]);
        }

        $event->setResponse(new JsonResponse(
            $body,
            $status,
            array_merge($headers, ['Content-Type' => 'application/problem+json']),
        ));
    }

    private function snakeCasePath(string $path): string
    {
        return preg_replace_callback(
            '/[A-Z]/',
            static fn (array $match): string => '_'.strtolower($match[0]),
            $path,
        ) ?? $path;
    }
}

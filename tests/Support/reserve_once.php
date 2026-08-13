<?php

declare(strict_types=1);

use App\Service\ReservationService;
use App\Kernel;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$payload = json_decode(base64_decode($argv[1], true), true, 512, JSON_THROW_ON_ERROR);
$startAt = (float) $argv[2];
$delay = $startAt - microtime(true);
if ($delay > 0) {
    usleep((int) ($delay * 1_000_000));
}

$kernel = new Kernel('test', false);
$kernel->boot();
/** @var ReservationService $service */
$service = $kernel->getContainer()->get('test.service_container')->get(ReservationService::class);

try {
    $result = $service->reserve(
        $payload['items'],
        $payload['ttl_seconds'],
        $payload['key'],
        $payload['hash'],
    );
    echo 'created:'.$result->reservation['id'];
} catch (Throwable $exception) {
    echo $exception instanceof App\Http\ApiProblemException ? (string) $exception->statusCode : get_class($exception);
} finally {
    $kernel->shutdown();
}

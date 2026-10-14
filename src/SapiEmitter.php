<?php

declare(strict_types=1);

namespace Sabroan\Http\Emitter;

use Sabroan\Http\Emitter\Exception\ExceptionInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * @api
 */
final class SapiEmitter implements EmitterInterface
{
    #[\Override]
    public function emit(ResponseInterface $response): bool
    {
        if (\headers_sent()) {
            throw new class ('Headers already sent') extends \RuntimeException implements ExceptionInterface {};
        }

        if (\ob_get_level() > 0 && \ob_get_length() > 0) {
            throw new class ('Output buffer not empty') extends \RuntimeException implements ExceptionInterface {};
        }

        $statusCode = $response->getStatusCode();
        $reasonPhrase = $response->getReasonPhrase();
        $protocolVersion = $response->getProtocolVersion();

        $status = 'HTTP/' . $protocolVersion . ' ' . $statusCode;
        if ($reasonPhrase !== '') {
            $status .= ' ' . $reasonPhrase;
        }

        \header($status, true, $statusCode);

        foreach ($response->getHeaders() as $name => $values) {
            $name = (string) $name;
            $replace = 0 !== \strcasecmp('Set-Cookie', $name);

            foreach ($values as $value) {
                \header($name . ': ' . $value, $replace);
                $replace = false;
            }
        }

        if ($statusCode < 200 || \in_array(
            $statusCode,
            [204, 205, 304],
            true,
        )) {
            return true;
        }

        $body = $response->getBody();

        if ($body->isSeekable()) {
            $body->rewind();
        }

        if (!$body->isReadable()) {
            return true;
        }

        while (!$body->eof()) {
            echo $body->read(8192);
        }

        return true;
    }
}

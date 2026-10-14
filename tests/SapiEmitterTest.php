<?php

declare(strict_types=1);

namespace Sabroan\Http\Emitter\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Sabroan\Http\Emitter\Exception\ExceptionInterface;
use Sabroan\Http\Emitter\SapiEmitter;

#[CoversClass(SapiEmitter::class)]
final class SapiEmitterTest extends TestCase
{
    private function createEmitter(): SapiEmitter
    {
        return new SapiEmitter();
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testEmitsStatusHeadersAndBody(): void
    {
        $stream = self::createMock(StreamInterface::class);
        $stream->method('isSeekable')->willReturn(true);
        $stream->expects($this->once())->method('rewind');
        $stream->method('isReadable')->willReturn(true);
        $stream->method('eof')->willReturnOnConsecutiveCalls(false, false, true);
        $stream->expects($this->exactly(2))
            ->method('read')
            ->with(8192)
            ->willReturnOnConsecutiveCalls('{"success":', 'true}');

        $response = self::createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getReasonPhrase')->willReturn('OK');
        $response->method('getProtocolVersion')->willReturn('1.1');
        $response->method('getBody')->willReturn($stream);

        $headers = [
            'Content-Type' => ['application/json'],
            'Set-Cookie' => ['theme=dark', 'session=abc123xyz'],
            404 => ['numeric_header_value'],
        ];

        $response->method('getHeaders')->willReturn($headers);

        \ob_start();

        $emitter = $this->createEmitter();
        $result = $emitter->emit($response);
        $bodyOutput = \ob_get_clean();

        self::assertTrue($result);
        self::assertSame('{"success":true}', $bodyOutput);
        self::assertSame(200, \http_response_code());
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testEmitsStatusWithoutReasonPhrase(): void
    {
        $stream = self::createStub(StreamInterface::class);
        $stream->method('isSeekable')->willReturn(false);
        $stream->method('isReadable')->willReturn(false);

        $response = self::createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getReasonPhrase')->willReturn('');
        $response->method('getProtocolVersion')->willReturn('2.0');
        $response->method('getHeaders')->willReturn([]);
        $response->method('getBody')->willReturn($stream);

        \ob_start();

        $emitter = $this->createEmitter();
        $result = $emitter->emit($response);

        \ob_end_clean();

        self::assertTrue($result);
        self::assertSame(200, \http_response_code());
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testThrowsExceptionWhenOutputBufferHasContent(): void
    {
        \ob_start();
        echo 'Stray unhandled buffered text';

        $response = self::createStub(ResponseInterface::class);
        $emitter = $this->createEmitter();

        try {
            $this->expectException(ExceptionInterface::class);
            $emitter->emit($response);
        } finally {
            \ob_end_clean();
        }
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testDoesNotEmitBodyIfUnreadable(): void
    {
        $stream = self::createMock(StreamInterface::class);
        $stream->method('isSeekable')->willReturn(false);
        $stream->method('isReadable')->willReturn(false);
        $stream->expects($this->never())->method('read');

        $response = self::createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getReasonPhrase')->willReturn('OK');
        $response->method('getProtocolVersion')->willReturn('1.1');
        $response->method('getHeaders')->willReturn([]);
        $response->method('getBody')->willReturn($stream);

        \ob_start();

        $emitter = $this->createEmitter();
        $result = $emitter->emit($response);
        $output = \ob_get_clean();

        self::assertTrue($result);
        self::assertSame('', $output);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    #[DataProvider('noBodyStatusCodeProvider')]
    public function testDoesNotEmitBodyForNoContentStatusCodes(
        int $statusCode,
    ): void {
        $stream = self::createMock(StreamInterface::class);
        $stream->expects($this->never())->method('read');

        $response = self::createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn($statusCode);
        $response->method('getReasonPhrase')->willReturn('');
        $response->method('getProtocolVersion')->willReturn('1.1');
        $response->method('getHeaders')->willReturn([]);
        $response->method('getBody')->willReturn($stream);

        \ob_start();

        $emitter = $this->createEmitter();
        $result = $emitter->emit($response);
        $output = \ob_get_clean();

        self::assertTrue($result);
        self::assertSame('', $output);
        self::assertSame($statusCode, \http_response_code());
    }

    /**
     * @return array<string, array{int}>
     */
    public static function noBodyStatusCodeProvider(): array
    {
        return [
            '100 Continue' => [100],
            '101 Switching Protocols' => [101],
            '204 No Content' => [204],
            '205 Reset Content' => [205],
            '304 Not Modified' => [304],
        ];
    }
}

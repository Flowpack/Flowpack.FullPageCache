<?php

declare(strict_types=1);

namespace Flowpack\FullPageCache\Domain\Dto;

use GuzzleHttp\Psr7\Message;
use Psr\Http\Message\ResponseInterface;

readonly class CacheEntry
{
    public function __construct(
        public int $timestamp,
        public string $responseAsString,
    ) {
    }

    public static function createFromResponse(ResponseInterface $response): CacheEntry
    {
        $responseAsString = Message::toString($response);
        $response->getBody()->rewind();

        return new self(
            time(),
            $responseAsString
        );
    }

    public function getResponse(): ResponseInterface
    {
        return Message::parseResponse($this->responseAsString)
            ->withHeader('Age', (string)(time() - $this->timestamp));
    }
}

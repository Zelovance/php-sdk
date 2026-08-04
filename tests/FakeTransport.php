<?php

declare(strict_types=1);

namespace Zelovance\Sdk\Tests;

use RuntimeException;
use Zelovance\Sdk\Contracts\TransportInterface;
use Zelovance\Sdk\Http\Response;

final class FakeTransport implements TransportInterface
{
    /** @var list<Response> */
    private array $responses;

    /** @var list<array{method:string,url:string,headers:array<string,string>,body:?string,timeout:int}> */
    public array $requests = [];

    public function __construct(Response ...$responses)
    {
        $this->responses = $responses;
    }

    public function request(string $method, string $url, array $headers = [], ?string $body = null, int $timeout = 30): Response
    {
        $this->requests[] = compact('method', 'url', 'headers', 'body', 'timeout');

        if ($this->responses === []) {
            throw new RuntimeException('Aucune réponse factice disponible.');
        }

        return array_shift($this->responses);
    }
}

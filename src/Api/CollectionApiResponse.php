<?php

namespace ProcessWire;

class CollectionApiResponse
{
    public function __construct(
        public readonly array $data,
        public readonly int   $status = 200,
    ) {}

    public static function success(array $data, array $meta = []): self
    {
        $body = ['ok' => true, 'data' => $data];
        if ($meta) $body['meta'] = $meta;
        return new self($body, 200);
    }

    public static function created(array $data): self
    {
        return new self(['ok' => true, 'data' => $data, 'meta' => ['created' => true]], 201);
    }

    public static function error(string $code, string $message, int $status): self
    {
        return new self([
            'ok'      => false,
            'error'   => $code,
            'message' => $message,
            'status'  => $status,
        ], $status);
    }

    public static function fromCached(string $json): self
    {
        return new self(json_decode($json, true) ?? [], 200);
    }
}

class CollectionApiException extends \RuntimeException
{
    public function __construct(string $message, int $code = 500)
    {
        parent::__construct($message, $code);
    }
}

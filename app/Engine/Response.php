<?php

declare(strict_types=1);

namespace App\Engine;

/**
 * HTTP response builder.
 *
 * Returns HTML (via View), JSON, or redirects. Sets headers. Manages status codes.
 */
final class Response
{
    private int $statusCode = 200;
    private array $headers = [];
    private string $body = '';

    public function status(int $code): self
    {
        $this->statusCode = $code;

        return $this;
    }

    public function header(string $name, string $value): self
    {
        $this->headers[$name] = $value;

        return $this;
    }

    public function body(string $content): self
    {
        $this->body = $content;

        return $this;
    }

    public static function html(string $content, int $status = 200): self
    {
        $response = new self();
        $response->statusCode = $status;
        $response->headers['Content-Type'] = 'text/html; charset=UTF-8';
        $response->body = $content;

        return $response;
    }

    public static function json(mixed $data, int $status = 200): self
    {
        $response = new self();
        $response->statusCode = $status;
        $response->headers['Content-Type'] = 'application/json';
        $response->body = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $response;
    }

    public static function redirect(string $url, int $status = 302): self
    {
        $response = new self();
        $response->statusCode = $status;
        $response->headers['Location'] = $url;

        return $response;
    }

    public static function empty(int $status = 204): self
    {
        $response = new self();
        $response->statusCode = $status;

        return $response;
    }

    public function send(): void
    {
        if (headers_sent()) {
            echo $this->body;

            return;
        }

        http_response_code($this->statusCode);

        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }

        echo $this->body;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getBody(): string
    {
        return $this->body;
    }
}

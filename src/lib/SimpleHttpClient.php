<?php
namespace App\Lib;

/**
 * Lightweight HTTP client using PHP cURL
 * Provides GuzzleHttp-compatible interface as fallback
 */
class Client
{
    private array $config;
    private string $baseUri;
    private int $timeout;
    private bool $httpErrors;

    public function __construct(array $config = [])
    {
        $this->baseUri = rtrim($config['base_uri'] ?? '', '/');
        $this->timeout = $config['timeout'] ?? 30;
        $this->httpErrors = $config['http_errors'] ?? true;
        $this->config = $config;
    }

    public function request(string $method, string $path, array $options = []): Response
    {
        $url = $this->baseUri ? $this->baseUri . '/' . ltrim($path, '/') : $path;
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        $headers = $options['headers'] ?? [];
        $formattedHeaders = [];
        foreach ($headers as $key => $value) {
            $formattedHeaders[] = "$key: $value";
        }

        if (!empty($formattedHeaders)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $formattedHeaders);
        }

        if (isset($options['json'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($options['json']));
            $formattedHeaders[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_HTTPHEADER, $formattedHeaders);
        } elseif (isset($options['body'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $options['body']);
        }

        $body = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return new Response($statusCode, $body);
    }
}

class Response
{
    private int $statusCode;
    private string $body;

    public function __construct(int $statusCode, string $body)
    {
        $this->statusCode = $statusCode;
        $this->body = $body;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getBody(): Body
    {
        return new Body($this->body);
    }
}

class Body
{
    private string $content;

    public function __construct(string $content)
    {
        $this->content = $content;
    }

    public function getContents(): string
    {
        return $this->content;
    }
}

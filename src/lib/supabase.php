<?php
namespace App\Lib;

// Load fallback HTTP client if GuzzleHttp is not available
if (!class_exists('\GuzzleHttp\Client')) {
    require_once __DIR__ . '/SimpleHttpClient.php';
    // Create alias for compatibility
    class_alias('App\Lib\Client', 'GuzzleHttp\Client');
    class_alias('App\Lib\Response', 'GuzzleHttp\Psr7\Response');
    class_alias('App\Lib\Body', 'GuzzleHttp\Psr7\Stream');
}

class Supabase
{
    private $client;
    private string $url;
    private string $anonKey;
    private string $serviceKey;

    public function __construct()
    {
        $config = include __DIR__ . '/../config/config.php';
        $this->url = SUPABASE_URL;
        $this->anonKey = SUPABASE_ANON_KEY;
        $this->serviceKey = SUPABASE_SERVICE_ROLE_KEY;
        $this->client = new \GuzzleHttp\Client([
            'base_uri' => $this->url,
            'timeout' => 30,
            'http_errors' => false,
        ]);
    }

    protected function headers(bool $useServiceKey = false, ?string $jwt = null): array
    {
        $key = $useServiceKey ? $this->serviceKey : $this->anonKey;
        $h = [
            'apikey' => $key,
            'Authorization' => 'Bearer ' . $key,
            'Content-Type' => 'application/json',
            'Prefer' => 'return=representation',
        ];
        if ($jwt) {
            $h['Authorization'] = 'Bearer ' . $jwt;
        }
        return $h;
    }

    public function getHeaders(bool $useServiceKey = false, ?string $jwt = null): array
    {
        return $this->headers($useServiceKey, $jwt);
    }

    public function getServiceKey(): string
    {
        return $this->serviceKey;
    }

    public function from(string $table): SupabaseQuery
    {
        return new SupabaseQuery($this, $table);
    }

    public function auth(): SupabaseAuth
    {
        return new SupabaseAuth($this);
    }

    public function storage(): SupabaseStorage
    {
        return new SupabaseStorage($this);
    }

    public function request(string $method, string $path, array $options = [], bool $useServiceKey = false, ?string $jwt = null): array
    {
        $options['headers'] = $this->headers($useServiceKey, $jwt);
        $response = $this->client->request($method, $path, $options);
        $body = $response->getBody()->getContents();
        $status = $response->getStatusCode();
        $data = json_decode($body, true);
        if ($status >= 400) {
            return ['error' => true, 'status' => $status, 'message' => $data['message'] ?? $data['msg'] ?? $body];
        }
        return ['error' => false, 'status' => $status, 'data' => $data];
    }
}

class SupabaseQuery
{
    private Supabase $sb;
    private string $table;
    private string $selectCols = '*';
    private array $filters = [];
    private array $orderBys = [];
    private ?int $limitVal = null;
    private ?int $offsetVal = null;

    public function __construct(Supabase $sb, string $table)
    {
        $this->sb = $sb;
        $this->table = $table;
    }

    public function select(string $cols = '*'): self
    {
        $this->selectCols = $cols;
        return $this;
    }

    public function eq(string $col, $val): self
    {
        $this->filters[] = "$col=eq.$val";
        return $this;
    }

    public function neq(string $col, $val): self
    {
        $this->filters[] = "$col=neq.$val";
        return $this;
    }

    public function gt(string $col, $val): self
    {
        $this->filters[] = "$col=gt.$val";
        return $this;
    }

    public function gte(string $col, $val): self
    {
        $this->filters[] = "$col=gte.$val";
        return $this;
    }

    public function or(string $condition): self
    {
        $this->filters[] = "or=($condition)";
        return $this;
    }

    public function lt(string $col, $val): self
    {
        $this->filters[] = "$col=lt.$val";
        return $this;
    }

    public function in(string $col, array $vals): self
    {
        $this->filters[] = "$col=in.(" . implode(',', $vals) . ")";
        return $this;
    }

    public function like(string $col, string $pattern): self
    {
        $this->filters[] = "$col=like.$pattern";
        return $this;
    }

    public function is(string $col, $val): self
    {
        $this->filters[] = "$col=is.$val";
        return $this;
    }

    public function order(string $col, bool $asc = true): self
    {
        $this->orderBys[] = "$col." . ($asc ? 'asc' : 'desc');
        return $this;
    }

    public function limit(int $n): self
    {
        $this->limitVal = $n;
        return $this;
    }

    public function offset(int $n): self
    {
        $this->offsetVal = $n;
        return $this;
    }

    public function get(bool $useServiceKey = false, ?string $jwt = null): array
    {
        $path = "/rest/v1/{$this->table}?select=" . urlencode($this->selectCols);
        if (!empty($this->filters)) {
            $path .= '&' . implode('&', $this->filters);
        }
        if (!empty($this->orderBys)) {
            $path .= '&order=' . implode(',', $this->orderBys);
        }
        if ($this->limitVal !== null) {
            $path .= "&limit={$this->limitVal}";
        }
        if ($this->offsetVal !== null) {
            $path .= "&offset={$this->offsetVal}";
        }
        return $this->sb->request('GET', $path, [], $useServiceKey, $jwt);
    }

    public function insert(array $data, bool $useServiceKey = true): array
    {
        return $this->sb->request('POST', "/rest/v1/{$this->table}", [
            'json' => $data,
        ], $useServiceKey);
    }

    public function update(array $data, bool $useServiceKey = true): array
    {
        $path = "/rest/v1/{$this->table}";
        if (!empty($this->filters)) {
            $path .= '?' . implode('&', $this->filters);
        }
        return $this->sb->request('PATCH', $path, [
            'json' => $data,
        ], $useServiceKey);
    }

    public function delete(bool $useServiceKey = true): array
    {
        $path = "/rest/v1/{$this->table}";
        if (!empty($this->filters)) {
            $path .= '?' . implode('&', $this->filters);
        }
        return $this->sb->request('DELETE', $path, [], $useServiceKey);
    }
}

class SupabaseAuth
{
    private Supabase $sb;

    public function __construct(Supabase $sb)
    {
        $this->sb = $sb;
    }

    public function signUp(string $email, string $password, array $metadata = []): array
    {
        $body = [
            'email' => $email,
            'password' => $password,
            'email_confirm' => true,
        ];
        if (!empty($metadata)) {
            $body['user_metadata'] = $metadata;
        }
        return $this->sb->request('POST', '/auth/v1/signup', [
            'json' => $body,
        ], true);
    }

    public function signIn(string $email, string $password): array
    {
        return $this->sb->request('POST', '/auth/v1/token?grant_type=password', [
            'json' => [
                'email' => $email,
                'password' => $password,
            ],
        ], false);
    }

    public function getUser(string $jwt): array
    {
        return $this->sb->request('GET', '/auth/v1/user', [], false, $jwt);
    }

    public function updateUser(string $jwt, array $attrs): array
    {
        return $this->sb->request('PUT', '/auth/v1/user', [
            'json' => $attrs,
        ], false, $jwt);
    }

    public function adminCreateUser(string $email, string $password, array $attrs = []): array
    {
        $body = [
            'email' => $email,
            'password' => $password,
            'email_confirm' => true,
            'user_metadata' => $attrs['user_metadata'] ?? [],
        ];
        if (isset($attrs['app_metadata'])) {
            $body['app_metadata'] = $attrs['app_metadata'];
        }
        return $this->sb->request('POST', '/auth/v1/admin/users', [
            'json' => $body,
        ], true);
    }

    public function adminDeleteUser(string $userId): array
    {
        return $this->sb->request('DELETE', "/auth/v1/admin/users/$userId", [], true);
    }
}

class SupabaseStorage
{
    private Supabase $sb;

    public function __construct(Supabase $sb)
    {
        $this->sb = $sb;
    }

    public function upload(string $bucket, string $path, string $filePath, string $contentType = 'application/octet-stream'): array
    {
        $fileContent = file_get_contents($filePath);
        $headers = $this->sb->getHeaders(true);
        return $this->sb->request('POST', "/storage/v1/object/$bucket/$path", [
            'headers' => [
                'apikey' => $headers['apikey'],
                'Authorization' => 'Bearer ' . $headers['apikey'],
                'Content-Type' => $contentType,
                'x-upsert' => 'true',
            ],
            'body' => $fileContent,
        ], true);
    }

    public function uploadBinary(string $bucket, string $path, string $data, string $contentType = 'application/octet-stream'): array
    {
        $serviceKey = $this->sb->getServiceKey();
        $client = new \GuzzleHttp\Client();
        $response = $client->request('POST', SUPABASE_URL . "/storage/v1/object/$bucket/$path", [
            'headers' => [
                'apikey' => $serviceKey,
                'Authorization' => 'Bearer ' . $serviceKey,
                'Content-Type' => $contentType,
                'x-upsert' => 'true',
            ],
            'body' => $data,
            'http_errors' => false,
        ]);
        $body = $response->getBody()->getContents();
        $status = $response->getStatusCode();
        $result = json_decode($body, true);
        if ($status >= 400) {
            return ['error' => true, 'status' => $status, 'message' => $result['message'] ?? $body];
        }
        return ['error' => false, 'status' => $status, 'data' => $result];
    }

    public function getPublicUrl(string $bucket, string $path): string
    {
        return SUPABASE_URL . "/storage/v1/object/public/$bucket/$path";
    }

    public function createSignedUrl(string $bucket, string $path, int $expires = 3600): array
    {
        return $this->sb->request('POST', "/storage/v1/object/sign/$bucket/$path", [
            'json' => ['expiresIn' => $expires],
        ], true);
    }
}

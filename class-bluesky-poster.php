<?php
declare(strict_types=1);

class BlueskyPoster {
    private string $baseUrl;
    private string $username;
    private string $password;
    private ?string $token = null;
    private ?string $did = null;

    public function __construct(
        string $username,
        string $password,
        string $baseUrl = 'https://bsky.social'
    ) {
        $this->username = $username;
        $this->password = $password;
        $this->baseUrl = $baseUrl;
    }

    public function postContent(string $content, string $extraText = '', string $weatherUrl = ''): void {
        if (!$this->authenticate()) {
            throw new RuntimeException('Authentication failed');
        }

        if (!empty($extraText)) {
            $content .= "\n\n" . $extraText;
        }

        if (!empty($weatherUrl)) {
            $this->createPostWithLink($content, $weatherUrl);
        } else {
            $this->createPost($content);
        }
    }

    private function authenticate(): bool {
        $endpoint = "{$this->baseUrl}/xrpc/com.atproto.server.createSession";
        
        $response = $this->makeRequest($endpoint, [
            'identifier' => $this->username,
            'password' => $this->password
        ]);

        if (isset($response['accessJwt'], $response['did'])) {
            $this->token = $response['accessJwt'];
            $this->did = $response['did'];
            return true;
        }

        return false;
    }

    private function createPost(string $content): void {
        $endpoint = "{$this->baseUrl}/xrpc/com.atproto.repo.createRecord";
        
        $payload = [
            'repo' => $this->did,
            'collection' => 'app.bsky.feed.post',
            'record' => [
                'text' => $content,
                'createdAt' => gmdate('Y-m-d\TH:i:s\Z')
            ]
        ];

        $this->makeRequest($endpoint, $payload, true);
    }

    private function createPostWithLink(string $content, string $weatherUrl): void {
        $endpoint = "{$this->baseUrl}/xrpc/com.atproto.repo.createRecord";
        
        $payload = [
            'repo' => $this->did,
            'collection' => 'app.bsky.feed.post',
            'record' => [
                'text' => $content . "\n\n📍 Live weather: " . $weatherUrl,
                'createdAt' => gmdate('Y-m-d\TH:i:s\Z'),
                'facets' => [
                    [
                        'index' => [
                            'byteStart' => strlen($content) + strlen("\n\n📍 Live weather: "),
                            'byteEnd' => strlen($content) + strlen("\n\n📍 Live weather: ") + strlen($weatherUrl)
                        ],
                        'features' => [
                            [
                                '$type' => 'app.bsky.richtext.facet#link',
                                'uri' => $weatherUrl
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $this->makeRequest($endpoint, $payload, true);
    }

    private function makeRequest(string $endpoint, array $data, bool $requiresAuth = false): array {
        $ch = curl_init($endpoint);
        
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json']
        ];

        if ($requiresAuth) {
            $options[CURLOPT_HTTPHEADER][] = "Authorization: Bearer {$this->token}";
        }

        curl_setopt_array($ch, $options);
        
        $response = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $statusCode >= 400) {
            throw new RuntimeException('API request failed: ' . $response);
        }

        return json_decode($response, true) ?? [];
    }
}

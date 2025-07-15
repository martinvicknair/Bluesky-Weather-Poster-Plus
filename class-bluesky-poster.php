<?php
/**
 * Bluesky_Poster: Posts status updates (with facets & optional image embed) to Bluesky.
 * Supports uploading images via Bluesky's blob API.
 * 
 * Author: Martin Vicknair - https://github.com/martinvicknair
 * Based on: Marcus Hazel-McGown - https://github.com/TheLich2112/bluesky-weather-poster
 */

class Bluesky_Poster {
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

    /**
     * Posts a pre-formatted status string to Bluesky.
     * Optionally, accepts facets array and $embed for image upload.
     * 
     * @param string $status The post content
     * @param array|null $facets
     * @param array|null $embed (['image_url'=>..., 'alt'=>...]) OR a finished embed array
     * @return mixed API response array or string
     */
    public function post_status(string $status, ?array $facets = null, $embed = null) {
        if (!$this->authenticate()) {
            throw new RuntimeException('Authentication failed');
        }
        // If embed is an image upload request, process it.
        if (is_array($embed) && isset($embed['image_url'])) {
            $upload_embed = $this->upload_and_build_embed($embed['image_url'], $embed['alt'] ?? '');
            return $this->createPost($status, $facets, $upload_embed);
        }
        // Otherwise, if already prepared
        return $this->createPost($status, $facets, $embed);
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

    /**
     * Downloads the image, uploads to Bluesky's blob API, returns embed array
     */
    private function upload_and_build_embed($image_url, $alt = 'Webcam Image') {
    // Download image to memory
    $img_data = file_get_contents($image_url);
    if ($img_data === false) {
        throw new RuntimeException("Failed to download image: $image_url");
    }

    // Get MIME type from image data
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->buffer($img_data);

    // Upload to Bluesky
    $endpoint = "{$this->baseUrl}/xrpc/com.atproto.repo.uploadBlob";
    $ch = curl_init($endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $img_data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: $mime",
        "Authorization: Bearer {$this->token}"
    ]);
    $resp = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($resp === false || $status >= 400) {
        throw new RuntimeException('Image upload to Bluesky failed: ' . $resp);
    }
    $resp_data = json_decode($resp, true);
    if (!isset($resp_data['blob']['ref']['$link'])) {
        throw new RuntimeException('Bluesky did not return blob reference for uploaded image.');
    }
    $blob_ref = $resp_data['blob']['ref']['$link'];

    // Return an embed images structure
    return [
        '$type' => 'app.bsky.embed.images',
        'images' => [[
            'alt' => $alt ?: 'Webcam Image',
            'image' => [
                '$type' => 'blob',
                'ref' => ['$link' => $blob_ref],
                'mimeType' => $mime,
                'size' => strlen($img_data)
            ]
        ]]
    ];
}


    private function createPost(string $content, ?array $facets = null, $embed = null) {
        $endpoint = "{$this->baseUrl}/xrpc/com.atproto.repo.createRecord";
        $record = [
            'text' => $content,
            'createdAt' => gmdate('Y-m-d\TH:i:s\Z')
        ];
        if ($facets && is_array($facets) && count($facets)) {
            $record['facets'] = $facets;
        }
        if ($embed) {
            $record['embed'] = $embed;
        }
        $payload = [
            'repo' => $this->did,
            'collection' => 'app.bsky.feed.post',
            'record' => $record
        ];
        return $this->makeRequest($endpoint, $payload, true);
    }

    private function makeRequest(string $endpoint, array $data, bool $requiresAuth = false): array {
        $ch = curl_init($endpoint);
        $headers = ['Content-Type: application/json'];
        if ($requiresAuth) {
            $headers[] = "Authorization: Bearer {$this->token}";
        }
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => $headers
        ];
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

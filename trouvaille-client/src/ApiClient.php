<?php

class ApiClient
{
    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = rtrim($_ENV['API_URL'] ?? 'http://localhost:3000/api', '/');
    }

    public function get(string $endpoint, ?string $token = null): array
    {
        return $this->request('GET', $endpoint, null, $token);
    }

    public function post(string $endpoint, array $data = [], ?string $token = null): array
    {
        return $this->request('POST', $endpoint, $data, $token);
    }

    public function patch(string $endpoint, array $data = [], ?string $token = null): array
    {
        return $this->request('PATCH', $endpoint, $data, $token);
    }

    public function delete(string $endpoint, ?string $token = null): array
    {
        return $this->request('DELETE', $endpoint, null, $token);
    }

    public function uploadFile(
        string  $endpoint,
        string  $filePath,
        string  $mimeType,
        string  $fieldName = 'image',
        ?string $token = null,
        array   $extraFields = []
    ): array {
        $url   = $this->baseUrl . $endpoint;
        $ch    = curl_init($url);
        $cfile = new CURLFile($filePath, $mimeType, basename($filePath));

        $headers = ['Accept: application/json'];
        if ($token) {
            $headers[] = 'Authorization: Bearer ' . $token;
        }

        $postFields = array_merge([$fieldName => $cfile], $extraFields);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $postFields,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 30,
        ]);

        $raw    = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ['status' => $status, 'body' => json_decode($raw ?: '', true) ?? []];
    }

    private function request(
        string  $method,
        string  $endpoint,
        ?array  $data = null,
        ?string $token = null
    ): array {
        $url = $this->baseUrl . $endpoint;
        $ch  = curl_init($url);

        $headers = ['Content-Type: application/json', 'Accept: application/json'];
        if ($token) {
            $headers[] = 'Authorization: Bearer ' . $token;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 15,
        ]);

        if ($data !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $raw    = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ['status' => $status, 'body' => json_decode($raw ?: '', true) ?? []];
    }
}

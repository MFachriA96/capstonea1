<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CVService
{
    protected $apiUrl;
    protected $apiKey;
    protected $timeout;

    public function __construct()
    {
        $this->apiUrl = env('CV_API_URL');
        $this->apiKey = env('CV_API_KEY');
        $this->timeout = env('CV_API_TIMEOUT', 30);
    }

    public function processPhoto(string $fileUrl): array
    {
        try {
            $response = Http::withHeaders([
                'CV-API-KEY' => $this->apiKey,
                'Accept' => 'application/json',
            ])->timeout($this->timeout)->post($this->apiUrl, [
                'file_url' => $fileUrl,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'jumlah_terdeteksi' => $data['jumlah_terdeteksi'] ?? 0,
                    'cacat_terdeteksi' => $data['cacat_terdeteksi'] ?? false,
                    'confidence_score' => $data['confidence_score'] ?? 0.0,
                    'model_version' => $data['model_version'] ?? 'unknown',
                ];
            }

            Log::error('CV API returned an error', ['status' => $response->status(), 'response' => $response->body()]);
        } catch (\Exception $e) {
            Log::error('CV API request failed: ' . $e->getMessage());
        }

        return [
            'jumlah_terdeteksi' => 0,
            'cacat_terdeteksi' => false,
            'confidence_score' => 0.0,
            'model_version' => 'unknown',
        ];
    }
}

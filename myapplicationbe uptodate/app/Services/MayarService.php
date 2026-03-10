<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * MayarService
 *
 * Wraps the Mayar Single Payment Request API.
 *
 * Sandbox : base URL = https://api.mayar.club/hl/v1  (MAYAR_SANDBOX=true)
 * Production: base URL = https://api.mayar.id/hl/v1   (MAYAR_SANDBOX=false)
 *
 * Endpoints used:
 *   POST /hl/v1/invoice/create    → Create payment request
 *   GET  /hl/v1/payment/{id}      → Get detail (check status)
 *   GET  /hl/v1/payment/close/{id}→ Close / cancel payment request
 */
class MayarService
{
    private string $baseUrl;
    private string $apiKey;

    public function __construct()
    {
        $sandbox = config('services.mayar.sandbox', true);
        $this->baseUrl = $sandbox
            ? 'https://api.mayar.club/hl/v1'
            : 'https://api.mayar.id/hl/v1';
        $this->apiKey = config('services.mayar.api_key', '');
    }

    // ─────────────────────────────────────────────────────────────
    // Create a new Single Payment Request
    // Returns: ['id' => '...', 'link' => 'https://...'] or throws
    // ─────────────────────────────────────────────────────────────
    public function createPayment(array $data): array
    {
        // Normalize phone from user's `phone` column (may be stored without leading 0)
        // Mayar requires `mobile` with minimum 10 characters.
        $mobile = $data['mobile'] ?? '';
        if ($mobile !== '' && !str_starts_with($mobile, '0')) {
            $mobile = '0' . $mobile;      // e.g. "8123456789" → "08123456789"
        }
        if (strlen($mobile) < 10) {
            $mobile = '08000000000';      // fallback if still too short
        }

        $payload = [
            'name'        => $data['name'],
            'email'       => $data['email'],
            'amount'      => (int) $data['amount'],
            'mobile'      => $mobile,
            'redirectUrl' => $data['redirectUrl'] ?? config('app.url'),
            'description' => $data['description'] ?? '',
            'expiredAt'   => $data['expiredAt']
                ?? now()->addHours(24)->toIso8601String(),
            'items'       => $data['items'] ?? [
                [
                    'name'        => $data['description'] ?? 'Pesanan',
                    'description' => $data['description'] ?? 'Pesanan',
                    'quantity'    => 1,
                    'rate'        => (int) $data['amount'],
                ]
            ],
        ];

        $response = Http::withToken($this->apiKey)
            ->timeout(15)
            ->post("{$this->baseUrl}/invoice/create", $payload);

        if (!$response->successful()) {
            Log::error('Mayar createPayment failed', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            throw new \RuntimeException(
                'Gagal membuat payment request: ' . $response->status()
            );
        }

        $body = $response->json();
        // Response: { statusCode, messages, data: { id, transactionId, link, expiredAt } }
        $d = $body['data'] ?? $body;

        return [
            'id'             => $d['id'],
            'transaction_id' => $d['transactionId'] ?? $d['transaction_id'] ?? null,
            'link'           => $d['link'],
        ];
    }

    // ─────────────────────────────────────────────────────────────
    // Get detail of a payment request
    // Returns status: 'unpaid' | 'paid' | 'closed'
    // ─────────────────────────────────────────────────────────────
    public function getPaymentDetail(string $paymentId): array
    {
        $response = Http::withToken($this->apiKey)
            ->timeout(10)
            ->get("{$this->baseUrl}/payment/{$paymentId}");

        if (!$response->successful()) {
            Log::error('Mayar getPaymentDetail failed', [
                'id'     => $paymentId,
                'status' => $response->status(),
            ]);
            throw new \RuntimeException(
                'Gagal mengambil detail payment: ' . $response->status()
            );
        }

        return $response->json('data', []);
    }

    // ─────────────────────────────────────────────────────────────
    // Close / cancel a payment request
    // ─────────────────────────────────────────────────────────────
    public function closePayment(string $paymentId): bool
    {
        $response = Http::withToken($this->apiKey)
            ->timeout(10)
            ->get("{$this->baseUrl}/payment/close/{$paymentId}");

        if (!$response->successful()) {
            Log::warning('Mayar closePayment failed', [
                'id'     => $paymentId,
                'status' => $response->status(),
            ]);
            return false;
        }

        return $response->json('statusCode') === 200;
    }
}

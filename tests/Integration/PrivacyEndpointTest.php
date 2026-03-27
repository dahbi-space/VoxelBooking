<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Integration tests for the privacy and retention endpoints.
 *
 * Verifies:
 * - Privacy endpoint returns 404 for invalid tenant/customer
 * - Cron retention endpoint requires valid token
 * - Cron retention endpoint rejects requests without token
 *
 * Requires the app running at APP_TEST_URL.
 */
final class PrivacyEndpointTest extends TestCase
{
    private string $baseUrl;

    protected function setUp(): void
    {
        $this->baseUrl = rtrim($_ENV['APP_TEST_URL'] ?? 'https://voxelbooking-app.test', '/');

        $ch = curl_init($this->baseUrl . '/health');
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_TIMEOUT => 5]);
        $r = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code !== 200 || !str_contains((string) $r, 'VoxelBooking')) {
            $this->markTestSkipped('App not reachable at ' . $this->baseUrl);
        }
    }

    /**
     * Privacy endpoint for non-existent tenant returns 404.
     */
    public function testPrivacyEndpointInvalidTenantReturns404(): void
    {
        $response = $this->get('/book/nonexistent-tenant/privacy/01ABCDEFGHJKLMNPQRSTWXYZ');
        $this->assertSame(404, $response['code']);
    }

    /**
     * Privacy endpoint for non-existent customer returns 404.
     */
    public function testPrivacyEndpointInvalidCustomerReturns404(): void
    {
        // Even if the tenant existed, a random customer ID should 404
        $response = $this->get('/book/test-salon/privacy/01ABCDEFGHJKLMNPQRSTWXYZ');
        $this->assertSame(404, $response['code']);
    }

    /**
     * Cron retention endpoint without token returns 403.
     */
    public function testRetentionCronRequiresToken(): void
    {
        $response = $this->get('/cron/retention');
        $this->assertSame(403, $response['code']);
    }

    /**
     * Cron retention endpoint with invalid token returns 403.
     */
    public function testRetentionCronRejectsInvalidToken(): void
    {
        $response = $this->get('/cron/retention?token=invalid-token-12345');
        $this->assertSame(403, $response['code']);
    }

    // ── Helper ──

    /**
     * @return array{code: int, body: string}
     */
    private function get(string $path): array
    {
        $ch = curl_init($this->baseUrl . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HEADER         => true,
        ]);
        $response = (string) curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        return [
            'code' => $code,
            'body' => substr($response, $headerSize),
        ];
    }
}

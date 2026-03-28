<?php

declare(strict_types=1);

namespace Tests\Unit\Engine;

use App\Engine\AgentAuth;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for AgentAuth engine.
 *
 * These tests verify scope logic and key generation structure
 * without requiring a database (DB-backed tests would be integration tests).
 */
final class AgentAuthTest extends TestCase
{
    // ════════════════════════════════════════════════════════════════
    // Scope Constants & Role Defaults
    // ════════════════════════════════════════════════════════════════

    public function testAllScopesContainsExpectedScopes(): void
    {
        $this->assertContains('tenants:read', AgentAuth::ALL_SCOPES);
        $this->assertContains('tenants:write', AgentAuth::ALL_SCOPES);
        $this->assertContains('bookings:read', AgentAuth::ALL_SCOPES);
        $this->assertContains('bookings:write', AgentAuth::ALL_SCOPES);
        $this->assertContains('services:read', AgentAuth::ALL_SCOPES);
        $this->assertContains('availability:read', AgentAuth::ALL_SCOPES);
        $this->assertContains('customers:read', AgentAuth::ALL_SCOPES);
        $this->assertContains('settings:read', AgentAuth::ALL_SCOPES);
        $this->assertContains('reports:read', AgentAuth::ALL_SCOPES);
    }

    public function testSettingsWriteIsNotInAllScopes(): void
    {
        // settings:write is intentionally excluded (per .ai/22 §5)
        $this->assertNotContains('settings:write', AgentAuth::ALL_SCOPES);
    }

    public function testAgentRoleHasWriteScopes(): void
    {
        $agentScopes = AgentAuth::ROLE_DEFAULTS['agent'];

        $this->assertContains('tenants:write', $agentScopes);
        $this->assertContains('bookings:write', $agentScopes);
        $this->assertContains('services:write', $agentScopes);
        $this->assertContains('customers:write', $agentScopes);
    }

    public function testViewerRoleHasOnlyReadScopes(): void
    {
        $viewerScopes = AgentAuth::ROLE_DEFAULTS['viewer'];

        $this->assertContains('tenants:read', $viewerScopes);
        $this->assertContains('bookings:read', $viewerScopes);
        $this->assertNotContains('tenants:write', $viewerScopes);
        $this->assertNotContains('bookings:write', $viewerScopes);
    }

    // ════════════════════════════════════════════════════════════════
    // hasScope()
    // ════════════════════════════════════════════════════════════════

    public function testHasScopeReturnsTrueWhenPresent(): void
    {
        $key = ['scopes_array' => ['bookings:read', 'tenants:read']];

        $this->assertTrue(AgentAuth::hasScope($key, 'bookings:read'));
    }

    public function testHasScopeReturnsFalseWhenAbsent(): void
    {
        $key = ['scopes_array' => ['bookings:read']];

        $this->assertFalse(AgentAuth::hasScope($key, 'bookings:write'));
    }

    public function testHasScopeReturnsFalseWithEmptyScopes(): void
    {
        $key = ['scopes_array' => []];

        $this->assertFalse(AgentAuth::hasScope($key, 'bookings:read'));
    }

    public function testHasScopeReturnsFalseWithMissingScopesArray(): void
    {
        $key = [];

        $this->assertFalse(AgentAuth::hasScope($key, 'bookings:read'));
    }

    // ════════════════════════════════════════════════════════════════
    // extractBearerToken()
    // ════════════════════════════════════════════════════════════════

    public function testExtractBearerTokenFromValidHeader(): void
    {
        $this->assertSame('abc123', AgentAuth::extractBearerToken('Bearer abc123'));
    }

    public function testExtractBearerTokenReturnsEmptyForNoBearer(): void
    {
        $this->assertSame('', AgentAuth::extractBearerToken('Basic abc123'));
    }

    public function testExtractBearerTokenReturnsEmptyForEmptyHeader(): void
    {
        $this->assertSame('', AgentAuth::extractBearerToken(''));
    }

    public function testExtractBearerTokenHandlesLongKeys(): void
    {
        $longKey = 'vb_' . str_repeat('a', 64);
        $this->assertSame($longKey, AgentAuth::extractBearerToken('Bearer ' . $longKey));
    }

    // ════════════════════════════════════════════════════════════════
    // validate() — empty token
    // ════════════════════════════════════════════════════════════════

    public function testValidateReturnsNullForEmptyToken(): void
    {
        $this->assertNull(AgentAuth::validate(''));
    }

    // ════════════════════════════════════════════════════════════════
    // Role defaults coverage
    // ════════════════════════════════════════════════════════════════

    public function testEveryDefaultScopeIsInAllScopes(): void
    {
        foreach (AgentAuth::ROLE_DEFAULTS as $role => $scopes) {
            foreach ($scopes as $scope) {
                $this->assertContains(
                    $scope,
                    AgentAuth::ALL_SCOPES,
                    "Role '{$role}' has scope '{$scope}' which is not in ALL_SCOPES"
                );
            }
        }
    }

    public function testAgentRoleHasAllReaderScopes(): void
    {
        $viewerScopes = AgentAuth::ROLE_DEFAULTS['viewer'];
        $agentScopes = AgentAuth::ROLE_DEFAULTS['agent'];

        foreach ($viewerScopes as $scope) {
            $this->assertContains(
                $scope,
                $agentScopes,
                "Viewer scope '{$scope}' missing from agent role (agent should be superset of viewer)"
            );
        }
    }
}

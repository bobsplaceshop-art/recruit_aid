<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Resource-gating acceptance criteria for the platform surface
 * (dashboard + analytics + delegation UI).
 *
 * These tests are SKIPPED until the /cas/* route surface exists. Each skip
 * message is a commitment: when the corresponding feature lands, the test
 * must be filled in and pass. A dashboard that bypasses a resource gate
 * will fail RG-01/RG-02 loudly once they are un-skipped.
 */
final class PlatformSurfaceTest extends TestCase
{
    /**
     * RG-01: cas.dashboard.access grants the dashboard — NOT the resources
     * linked from it. A cas_admin with no tenant role must be denied every
     * tenant-scoped route target reachable from the dashboard.
     */
    public function testDashboardPermissionDoesNotGrantResourcePermission(): void
    {
        self::markTestSkipped(
            'Deferred until /cas/* routes exist. When they do: create a cas_admin '
            . 'user with no tenant role, log them in, and assert that clicking any '
            . 'tenant-resource tile from the dashboard returns 403. The dashboard '
            . 'renders; the tenant route denies.'
        );
    }

    /**
     * RG-02: Every tile target in the dashboard view resolves to a route that
     * carries a permission: middleware. No tile may be a permission bypass.
     */
    public function testEveryDashboardTileTargetHasPermissionMiddleware(): void
    {
        self::markTestSkipped(
            'Deferred until the dashboard view exists. When it does: parse the '
            . 'view for href targets, resolve each against the router, and assert '
            . 'every matched route has a "permission:" middleware entry. This is a '
            . 'structural test — no DB fixtures needed beyond the route registry.'
        );
    }

    /**
     * RG-03: Analytics queries return aggregates only — no PII, no SELECT *.
     */
    public function testAnalyticsQueriesReturnAggregatesOnly(): void
    {
        self::markTestSkipped(
            'Deferred until the analytics layer exists. When it does: run the '
            . 'analytics repositories through the SQL analyzer and assert no query '
            . 'selects per-record columns (email, name, phone, date_of_birth). '
            . 'Only aggregate expressions (COUNT, SUM, AVG) are permitted.'
        );
    }
}

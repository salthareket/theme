<?php

namespace SaltHareket\Notifications;

/**
 * NotifyPriority
 * Notification öncelik seviyeleri.
 *
 * @version 1.0.0
 * @changelog
 *   1.0.0 - 2026-05-04 — Initial release
 */
enum NotifyPriority: string
{
    case LOW      = 'low';
    case NORMAL   = 'normal';
    case HIGH     = 'high';
    case CRITICAL = 'critical';

    /**
     * CRITICAL ve HIGH öncelikler throttle/quiet-hours'u bypass eder.
     */
    public function bypassesThrottle(): bool
    {
        return match( $this ) {
            self::CRITICAL, self::HIGH => true,
            default                    => false,
        };
    }

    /**
     * CRITICAL her zaman gider — sessiz saatler dahil.
     */
    public function bypassesQuietHours(): bool
    {
        return $this === self::CRITICAL;
    }
}

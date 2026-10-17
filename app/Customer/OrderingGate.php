<?php

declare(strict_types=1);

namespace App\Customer;

use App\Settings\ClosedReason;
use App\Settings\Settings;
use App\Settings\StoreStatus;
use Carbon\CarbonInterface;

/**
 * The computed ordering gate the public site renders: whether the diner is
 * open, whether an order can be started, the badge label, and — when ordering
 * is blocked — a plain, customer-facing reason (PLAN §4 *Customer site*).
 *
 * Ordering is blocked for two independent causes: the store is closed (hours,
 * force-close, or a scheduled closure, computed by #20's {@see StoreStatus}),
 * or the payment block does not exist yet (the GCash number and QR are both
 * required before a QR can be sent). The closed cause wins so the badge and the
 * reason never contradict the state, and the reason is a plain sentence — no
 * error codes, no blame.
 */
final readonly class OrderingGate
{
    private function __construct(
        public bool $open,
        public bool $canOrder,
        public string $badge,
        public ?string $detail,
        public ?string $reason,
    ) {}

    /**
     * Compute the gate from the settings value (read once by the caller) and
     * the instant to evaluate.
     */
    public static function for(Settings $settings, StoreStatus $status, CarbonInterface $when): self
    {
        $state = $status->stateFor($settings, $when);
        $label = $state->reason === ClosedReason::ScheduledClosure ? $state->label : null;

        if (! $state->isOpen) {
            return new self(
                open: false,
                canOrder: false,
                badge: 'Closed',
                detail: $label !== null ? "Closed for {$label}" : null,
                reason: $label !== null
                    ? "Ordering is closed — {$label}."
                    : 'Ordering is closed right now.',
            );
        }

        if (! $settings->hasPaymentBlock()) {
            return new self(
                open: true,
                canOrder: false,
                badge: 'Open now',
                detail: null,
                reason: 'Ordering opens once the GCash number and QR are configured.',
            );
        }

        return new self(
            open: true,
            canOrder: true,
            badge: 'Open now',
            detail: null,
            reason: null,
        );
    }
}

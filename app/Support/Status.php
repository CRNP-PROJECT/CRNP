<?php

namespace App\Support;

/**
 * Status — human-readable labels and badges for order/booking/payment statuses,
 * plus the order tracking stepper HTML.
 */
class Status {

    public static function orderLabel(string $status): array {
        $map = [
            'pending'            => ['Pending',     'badge--warn'],
            'accepted'           => ['Accepted',    'badge--info'],
            'preparing'          => ['Preparing',   'badge--info'],
            'ready'              => ['Ready',       'badge--gold'],
            'done'               => ['Completed',   'badge--ok'],
            'cancelled'          => ['Cancelled',   'badge--muted'],
            'cashier_cancelled'  => ['Cancelled',   'badge--muted'],
        ];
        return $map[$status] ?? [ucfirst($status), 'badge--muted'];
    }

    public static function bookingLabel(string $status): array {
        $map = [
            'pending'   => ['Pending',   'badge--warn'],
            'accepted'  => ['Approved',  'badge--info'],
            'rejected'  => ['Rejected',  'badge--muted'],
            'returned'  => ['Returned',  'badge--ok'],
            'cancelled' => ['Cancelled', 'badge--muted'],
        ];
        return $map[$status] ?? [ucfirst($status), 'badge--muted'];
    }

    public static function paymentStatusLabel(string $status): array {
        $map = [
            'pending_verification'  => ['Verifying',  'badge--warn'],
            'paid'                  => ['Paid',       'badge--ok'],
            'unpaid'                => ['Unpaid',     'badge--danger'],
            'no_payment_required'   => ['At counter', 'badge--muted'],
        ];
        return $map[$status] ?? [ucfirst($status), 'badge--muted'];
    }

    public static function paymentMethodLabel(string $method): array {
        $map = [
            'gcash'   => ['GCash',   'badge--blue'],
            'counter' => ['Counter', 'badge--muted'],
        ];
        return $map[$method] ?? [ucfirst($method), 'badge--muted'];
    }

    public static function orderTrackerHtml(string $status): string {
        $steps = [
            'pending'   => ['Pending', 0],
            'accepted'  => ['Accepted', 1],
            'preparing' => ['Preparing', 2],
            'ready'     => ['Ready', 3],
            'done'      => 'done',
        ];
        $cancelled = in_array($status, ['cancelled', 'cashier_cancelled'], true);
        if ($cancelled) {
            return '<div class="badge badge--muted">Cancelled</div>';
        }
        $order = ['pending','accepted','preparing','ready','done'];
        $currentIdx = array_search($status, $order, true);
        if ($currentIdx === false) $currentIdx = 0;
        $labels = ['Pending', 'Accepted', 'Preparing', 'Ready', 'Done'];
        $html = '<div class="tracker" role="list">';
        foreach ($labels as $i => $label) {
            $cls = '';
            if ($i < $currentIdx) $cls = 'tracker__step--done';
            elseif ($i === $currentIdx) $cls = 'tracker__step--current';
            $icon = $i < $currentIdx ? '✓' : ($i + 1);
            $html .= '<div class="tracker__step ' . $cls . '" role="listitem">';
            $html .= '<span class="tracker__dot">' . $icon . '</span>';
            $html .= '<span class="tracker__label">' . Output::escape($label) . '</span>';
            $html .= '</div>';
        }
        $html .= '</div>';
        return $html;
    }
}

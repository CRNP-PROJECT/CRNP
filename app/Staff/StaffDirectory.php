<?php

declare(strict_types=1);

namespace App\Staff;

use App\Auth\RtdbUser;
use App\Rtdb\Contracts\RtdbClient;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * The staff list at `staff/{uid}` — the source of truth for who is staff and
 * what role they hold.
 *
 * A staff row is created by an admin (name + Google email) or by the first
 * admin seeder; both carry only an email, never a Google `sub`. So the node
 * key is a stable, RTDB-safe id derived from the email (`idForEmail`), and a
 * signed-in Google account resolves to its row by that same email. The role is
 * read through `RtdbClient::cached` behind a short TTL, and every staff write
 * evicts the key so a demotion or deletion ends access on the next request.
 */
final readonly class StaffDirectory
{
    public function __construct(private RtdbClient $client) {}

    /**
     * The stable node id for an email: base64url of the lowercased address, so
     * the key never carries an RTDB-forbidden character and distinct emails
     * never collide.
     */
    public static function idForEmail(string $email): string
    {
        return rtrim(strtr(base64_encode(strtolower(trim($email))), '+/', '-_'), '=');
    }

    /**
     * The staff row for a signed-in account, or null when the account is not
     * on the staff list — a customer, or a Google account the admin removed.
     */
    public function forUser(?Authenticatable $user): ?Staff
    {
        if (! $user instanceof RtdbUser || $user->email === null) {
            return null;
        }

        return $this->findByEmail($user->email);
    }

    public function findByEmail(string $email): ?Staff
    {
        return $this->findById(self::idForEmail($email));
    }

    public function findById(string $id): ?Staff
    {
        $node = $this->client->cached(
            $this->cacheKey($id),
            "staff/{$id}",
            self::roleCacheTtl(),
        );

        return is_array($node) ? Staff::fromNode($id, $node) : null;
    }

    /**
     * Every staff row, keyed by node id. The staff tree is one of the small
     * config trees PLAN §4 allows reading whole.
     *
     * @return array<string, Staff>
     */
    public function all(): array
    {
        $node = $this->client->get('staff');

        if (! is_array($node)) {
            return [];
        }

        $staff = [];

        foreach ($node as $id => $row) {
            if (! is_string($id) || ! is_array($row)) {
                continue;
            }

            $member = Staff::fromNode($id, $row);

            if ($member !== null) {
                $staff[$id] = $member;
            }
        }

        return $staff;
    }

    public function anyExists(): bool
    {
        return $this->all() !== [];
    }

    /**
     * Evict a cached role so the next request re-reads `staff/{uid}`.
     */
    public function evict(string $id): void
    {
        $this->client->forget($this->cacheKey($id));
    }

    private function cacheKey(string $id): string
    {
        return "staff-role:{$id}";
    }

    /**
     * The short window a role read is served from cache; any staff write
     * evicts the key, so this only bounds reads no write has touched.
     */
    private static function roleCacheTtl(): int
    {
        $ttl = config('staff.role_cache_ttl');

        return is_numeric($ttl) ? (int) $ttl : 60;
    }
}

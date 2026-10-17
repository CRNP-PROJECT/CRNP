<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Staff\Actions\CreateStaff;
use App\Staff\StaffDirectory;
use App\Staff\StaffRole;
use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * One-shot bootstrap of the first admin from environment variables.
 *
 * `php artisan staff:seed --admin` reads `STAFF_ADMIN_NAME` and
 * `STAFF_ADMIN_EMAIL`, creates the first `staff/{uid}` row, and refuses to run
 * again once any staff row exists — so no installer route is ever left open and
 * no credential lives in the repo.
 */
final class StaffSeedCommand extends Command
{
    protected $signature = 'staff:seed {--admin : Seed the first admin from the environment}';

    protected $description = 'Create the first admin account from environment variables (one-shot).';

    public function handle(StaffDirectory $directory, CreateStaff $createStaff): int
    {
        if (! $this->option('admin')) {
            $this->components->error('Nothing to do: pass --admin to seed the first admin.');

            return self::FAILURE;
        }

        if ($directory->anyExists()) {
            $this->components->error('A staff account already exists; staff:seed refuses to run again.');

            return self::FAILURE;
        }

        /** @var mixed $name */
        $name = config('staff.admin.name');
        /** @var mixed $email */
        $email = config('staff.admin.email');

        if (! is_string($name) || trim($name) === '' || ! is_string($email) || trim($email) === '') {
            $this->components->error('Set STAFF_ADMIN_NAME and STAFF_ADMIN_EMAIL before seeding.');

            return self::FAILURE;
        }

        try {
            $createStaff($name, $email, StaffRole::Admin);
        } catch (InvalidArgumentException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info("Seeded the first admin [{$email}].");

        return self::SUCCESS;
    }
}

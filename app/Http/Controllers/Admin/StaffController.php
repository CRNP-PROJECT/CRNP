<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreStaffRequest;
use App\Http\Requests\Admin\UpdateStaffRoleRequest;
use App\Staff\Actions\CreateStaff;
use App\Staff\Actions\DeleteStaff;
use App\Staff\Actions\SetStaffRole;
use App\Staff\StaffDirectory;
use App\Staff\StaffRole;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * The admin's staff surface: create, demote, and delete staff accounts.
 *
 * Three primary actions, matching the density budget — each write routes through
 * an action that evicts the account's cached role, so a demotion or deletion is
 * enforced on the account's next request.
 */
final class StaffController extends Controller
{
    public function __construct(private readonly StaffDirectory $directory) {}

    public function index(): View
    {
        return view('admin.staff.index', [
            'staff' => $this->directory->all(),
            'roles' => StaffRole::cases(),
        ]);
    }

    public function store(StoreStaffRequest $request, CreateStaff $createStaff): RedirectResponse
    {
        /** @var array{name: string, email: string, role: string} $validated */
        $validated = $request->validated();

        $createStaff($validated['name'], $validated['email'], StaffRole::from($validated['role']));

        return redirect()
            ->route('admin.staff.index')
            ->with('status', 'Staff account created.');
    }

    public function update(UpdateStaffRoleRequest $request, string $staff, SetStaffRole $setStaffRole): RedirectResponse
    {
        /** @var array{role: string} $validated */
        $validated = $request->validated();

        $setStaffRole($staff, StaffRole::from($validated['role']));

        return redirect()
            ->route('admin.staff.index')
            ->with('status', 'Staff role updated.');
    }

    public function destroy(string $staff, DeleteStaff $deleteStaff): RedirectResponse
    {
        $deleteStaff($staff);

        return redirect()
            ->route('admin.staff.index')
            ->with('status', 'Staff account deleted.');
    }
}

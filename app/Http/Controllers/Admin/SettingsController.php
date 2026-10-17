<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreClosureRequest;
use App\Http\Requests\Admin\UpdateBannerRequest;
use App\Http\Requests\Admin\UpdateBusinessHoursRequest;
use App\Http\Requests\Admin\UpdateCapacitiesRequest;
use App\Http\Requests\Admin\UpdatePaymentRequest;
use App\Settings\Actions\AddClosure;
use App\Settings\Actions\RemoveClosure;
use App\Settings\Actions\RotateKitchenSecret;
use App\Settings\Actions\UpdateBanner;
use App\Settings\Actions\UpdateBusinessHours;
use App\Settings\Actions\UpdateCapacities;
use App\Settings\Actions\UpdatePayment;
use App\Settings\Closure;
use App\Settings\ReservationArea;
use App\Settings\SettingsRepository;
use App\Settings\Weekday;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * The admin's business-settings surface: hours, force-close, scheduled
 * closures, the announcement banner, the GCash payment block, the kitchen
 * secret, and reservation capacities.
 *
 * Every write delegates to a single-purpose action; each action persists the
 * whole `settings` node through {@see SettingsRepository}, which evicts the
 * catalog cache so a force-close reaches the customer site on the next request.
 */
final class SettingsController extends Controller
{
    public function __construct(private readonly SettingsRepository $settings) {}

    public function edit(): View
    {
        return view('admin.settings.edit', [
            'settings' => $this->settings->current(),
            'weekdays' => Weekday::cases(),
            'areas' => ReservationArea::cases(),
        ]);
    }

    public function updateHours(UpdateBusinessHoursRequest $request, UpdateBusinessHours $update): RedirectResponse
    {
        /** @var array{hours?: array<string, mixed>, forceClose?: mixed} $validated */
        $validated = $request->validated();

        $update($validated['hours'] ?? [], (bool) ($validated['forceClose'] ?? false));

        return $this->back('Business hours saved.');
    }

    public function updateBanner(UpdateBannerRequest $request, UpdateBanner $update): RedirectResponse
    {
        /** @var array{banner?: string|null, bannerVisible?: mixed} $validated */
        $validated = $request->validated();

        $update($validated['banner'] ?? '', (bool) ($validated['bannerVisible'] ?? false));

        return $this->back('Announcement banner saved.');
    }

    public function storeClosure(StoreClosureRequest $request, AddClosure $addClosure): RedirectResponse
    {
        /** @var array{from: string, to: string, label?: string|null} $validated */
        $validated = $request->validated();

        $closure = Closure::fromInput($validated['from'], $validated['to'], $validated['label'] ?? null);

        if ($closure === null) {
            return $this->back('That closure range is not valid.');
        }

        $addClosure($closure);

        return $this->back('Scheduled closure added.');
    }

    public function destroyClosure(string $closure, RemoveClosure $removeClosure): RedirectResponse
    {
        $removeClosure($closure);

        return $this->back('Scheduled closure removed.');
    }

    public function updatePayment(UpdatePaymentRequest $request, UpdatePayment $update): RedirectResponse
    {
        /** @var array{gcashNumber: string} $validated */
        $validated = $request->validated();

        $update($validated['gcashNumber'], $request->file('qr'));

        return $this->back('GCash payment block saved.');
    }

    public function rotateKitchenSecret(RotateKitchenSecret $rotate): RedirectResponse
    {
        $rotate();

        return $this->back('Kitchen secret rotated — the old link no longer works.');
    }

    public function updateCapacities(UpdateCapacitiesRequest $request, UpdateCapacities $update): RedirectResponse
    {
        /** @var array{capacities?: array<string, mixed>} $validated */
        $validated = $request->validated();

        $update($validated['capacities'] ?? []);

        return $this->back('Reservation capacities saved.');
    }

    private function back(string $status): RedirectResponse
    {
        return redirect()
            ->route('admin.settings.edit')
            ->with('status', $status);
    }
}

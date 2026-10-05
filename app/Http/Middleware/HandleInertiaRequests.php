<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use Closure;
use Inertia\Inertia;


class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     * @param  \Illuminate\Http\Request  $request
     * @return string|null
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Defines the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
                        // The browser title must identify the portal a person is using, not
            // simply fall back to the admin panel for every Inertia page.
            'portalTitle' => function () use ($request) {
                // Dispatcher and Dispatch-Pro use the same role. The selected
                // dynamic login URL preserves that otherwise unavailable detail.
                if ($portalTitle = $request->session()->get('portal_title')) {
                    return $portalTitle;
                }

                $user = $request->user();
                $role = $user?->roles?->first()?->slug;

                return match ($role) {
                    'user' => 'User-Web',
                    'owner', 'fleet_owner' => 'Owner',
                    'dispatcher', 'delivery-dispatcher' => 'Dispatcher',
                    'agent' => 'Agent',
                    'franchise_owner' => 'Franchise',
                    default => 'Admin',
                };
            },
            'flash' => [
                'successMessage' => $request->session()->get('successMessage'),
                'error' => $request->session()->get('error'),
            ],
            'whatsappAddonEnabled' => fn () => (int) get_settings('whatsapp-addon') === 1,
            'whatsappModuleInstalled' => fn () => $this->whatsappModuleInstalled(),
            'whatsappNumber' => fn () => $this->whatsappNumber(),
        ]);
    }

    protected function whatsappModuleInstalled(): bool
    {
        return class_exists(\Modules\WhatsApp\WhatsAppServiceProvider::class);
    }

    protected function whatsappNumber(): ?string
    {
        if (class_exists(\Modules\WhatsApp\Models\WhatsAppAccount::class)) {
            try {
                $account = \Modules\WhatsApp\Models\WhatsAppAccount::query()
                    ->where('is_active', true)
                    ->latest('id')
                    ->first();
                if ($account && filled($account->display_phone_number)) {
                    return $account->display_phone_number;
                }
            } catch (\Throwable $e) {
            }
        }

        return  null;
    }




}

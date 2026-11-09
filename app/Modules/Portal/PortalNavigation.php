<?php

namespace App\Modules\Portal;

use App\Models\User;
use Illuminate\Support\Facades\Route;

class PortalNavigation
{
    /**
     * @return array<int, array{key: string, title: ?string, open: bool, items: array<int, array{label: string, icon: string, url: string, current: bool, badge: ?string}>}>
     */
    public static function sections(string $portal, User $user): array
    {
        $items = match ($portal) {
            'investor' => [
                ['Overview', 'home', 'investor.dashboard', 'investor.dashboard', 'investor.portal.view'],
                ['My Investments', 'briefcase', 'investor.investments.index', 'investor.investments.*', 'investor.portal.investments.view'],
                ['Projects', 'building', 'investor.projects.index', 'investor.projects.*', 'investor.portal.project.progress.view'],
                ['Statements', 'file', 'investor.statements.index', 'investor.statements.*', 'investor.portal.investments.view'],
                ['Documents', 'archive', 'investor.documents.index', 'investor.documents.*', 'investor.portal.documents.download'],
                ['Profile', 'user', 'investor.profile', 'investor.profile', 'investor.portal.view'],
            ],
            default => [
                ['Overview', 'home', 'customer.dashboard', 'customer.dashboard', 'customer.portal.view'],
                ['My Units', 'door', 'customer.flats.index', 'customer.flats.*', 'customer.portal.flats.view'],
                ['Installments', 'calendar', 'customer.installments.index', 'customer.installments.*', 'customer.portal.installments.view'],
                ['Payments', 'tag', 'customer.payments.index', 'customer.payments.*', 'customer.portal.payments.view'],
                ['Statements', 'file', 'customer.statements.index', 'customer.statements.*', 'customer.portal.statements.download'],
                ['Profile', 'user', 'customer.profile', 'customer.profile', 'customer.portal.view'],
            ],
        };

        $list = [];
        foreach ($items as [$label, $icon, $route, $active, $permission]) {
            if (Route::has($route) && $user->can($permission)) {
                $list[] = ['label' => $label, 'icon' => $icon, 'url' => route($route), 'current' => request()->routeIs($active), 'badge' => null];
            }
        }

        return [['key' => $portal, 'title' => null, 'open' => true, 'items' => $list]];
    }
}

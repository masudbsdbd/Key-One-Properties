<?php

namespace App\Modules\Investors\Policies;

use App\Models\Investor;
use App\Models\InvestorCapitalTransaction;
use App\Models\InvestorFlatAgreement;
use App\Models\User;
use App\Support\Attachments\AttachableRegistry;
use Illuminate\Support\Facades\Gate;

class InvestorAccess
{
    public static function register(): void
    {
        Gate::define('investor:files-view', fn (User $u, Investor $i) => $u->can('investor.view'));
        Gate::define('investor:files-manage', fn (User $u, Investor $i) => $u->can('investor.edit'));
        Gate::define('investor-agreement:files-view', fn (User $u, InvestorFlatAgreement $a) => $u->can('investor.view') && $a->isVisibleTo($u));
        Gate::define('investor-agreement:files-manage', fn (User $u, InvestorFlatAgreement $a) => $u->canAny(['investor.agreement.create', 'investor.agreement.edit']) && $a->isVisibleTo($u));
        Gate::define('investor-capital:files-view', fn (User $u, InvestorCapitalTransaction $t) => $u->can('investor.view') && $t->isVisibleTo($u));
        Gate::define('investor-capital:files-manage', fn (User $u, InvestorCapitalTransaction $t) => $u->canAny(['investor.capital.record', 'investor.capital.return']) && $t->isVisibleTo($u));

        AttachableRegistry::register('investor', Investor::class, 'investor:files-view', 'investor:files-manage');
        AttachableRegistry::register('investor-agreement', InvestorFlatAgreement::class, 'investor-agreement:files-view', 'investor-agreement:files-manage');
        AttachableRegistry::register('investor-capital', InvestorCapitalTransaction::class, 'investor-capital:files-view', 'investor-capital:files-manage');
    }
}

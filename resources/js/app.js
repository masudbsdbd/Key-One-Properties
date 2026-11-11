import $ from 'jquery';
import select2 from 'select2';

window.$ = window.jQuery = $;
select2($);

import { http } from './core/http';
import { toast } from './components/toast';
import { modal } from './components/modal';
import { confirmDialog } from './components/confirm';
import { initSelects } from './components/select';
import { initAjaxForms } from './components/form';
import { initAjaxTables } from './components/table';
import { initShell } from './components/shell';
import { initPageProgress } from './components/progress';
import { initTabs } from './components/tabs';
import { initAuthPages } from './pages/auth';
import { initProfilePage } from './pages/profile';
import { initRemoteModal, openRemoteModal } from './components/remote-modal';
import { initActions } from './components/actions';
import { initPermissionForms } from './pages/permissions';
import { initSettingsPage, initStageTemplates } from './pages/settings';
import { initPartials, reloadPartials } from './components/partials';
import { initTooltips } from './components/tooltip';
import { initProjectPages } from './pages/projects';
import { initFlatPages } from './pages/flats';
import { initChequeFields, initJournalForm, initMoneyMoveForm, initOpeningForm, initReportFilters } from './pages/accounting';
import { initCustomerPages } from './pages/customers';
import { initCalculators } from './pages/calc';
import { bindLineItems } from './components/lines';
import { initInventoryPages } from './pages/inventory';
import { initPurchasingPages, initExpenseForms } from './pages/purchasing';
import { initContractorPages } from './pages/contractors';
import { initCollectionPages } from './pages/collections';
import { initInvestorPages } from './pages/investors';
import { initReportPages } from './pages/reports';

window.App = { http, toast, modal, confirm: confirmDialog, initSelects, openRemoteModal, reloadPartials };

$(() => {
    initPageProgress();
    initShell();
    initTabs(document);
    initSelects(document);
    initAjaxForms(document);
    initAjaxTables(document);
    initAuthPages();
    initProfilePage();
    initRemoteModal();
    initActions();
    initPermissionForms();
    initSettingsPage();
    initStageTemplates();
    initPartials();
    initTooltips();
    initProjectPages();
    initFlatPages();
    initJournalForm();
    initMoneyMoveForm();
    initOpeningForm();
    initChequeFields();
    initReportFilters();
    initCustomerPages();
    initCalculators();
    bindLineItems();
    initInventoryPages();
    initPurchasingPages();
    initContractorPages();
    initCollectionPages();
    initInvestorPages();
    initReportPages();
    initExpenseForms();

    const params = new URLSearchParams(window.location.search);
    const autoCreate = document.querySelector('[data-auto-create]');
    if (params.get('create') && autoCreate) autoCreate.click();

    const flash = document.getElementById('flash-data');
    if (flash) {
        try {
            const data = JSON.parse(flash.textContent || '{}');
            if (data.success) toast.success(data.success);
            if (data.error) toast.error(data.error);
        } catch {
            /* ignore malformed flash payload */
        }
    }

    document.dispatchEvent(new CustomEvent('app:ready'));
});

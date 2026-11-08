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
import { initTabs } from './components/tabs';
import { initAuthPages } from './pages/auth';
import { initProfilePage } from './pages/profile';
import { initRemoteModal, openRemoteModal } from './components/remote-modal';
import { initActions } from './components/actions';
import { initPermissionForms } from './pages/permissions';
import { initSettingsPage } from './pages/settings';
import { initPartials, reloadPartials } from './components/partials';
import { initProjectPages } from './pages/projects';
import { initFlatPages } from './pages/flats';
import { initCustomerPages } from './pages/customers';

window.App = { http, toast, modal, confirm: confirmDialog, initSelects, openRemoteModal, reloadPartials };

$(() => {
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
    initPartials();
    initProjectPages();
    initFlatPages();
    initCustomerPages();

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

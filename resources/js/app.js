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

window.App = { http, toast, modal, confirm: confirmDialog, initSelects };

$(() => {
    initShell();
    initTabs(document);
    initSelects(document);
    initAjaxForms(document);
    initAjaxTables(document);

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

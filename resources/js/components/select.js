import $ from 'jquery';

const STATIC_SEARCH_THRESHOLD = 7;

/**
 * Enhances <select data-select> elements.
 *  - data-select-url: remote, paginated search (expects {results, pagination}).
 *  - data-depends-on="#parent": clears and reloads when the parent changes;
 *    the parent value is sent as data-depends-param (default "parent").
 *  - Static selects get a search box only when they have more than 7 options.
 */
export function initSelects(root = document) {
    $(root)
        .find('select[data-select]')
        .each(function () {
            const $el = $(this);
            if ($el.data('select2')) return;

            const url = $el.data('select-url');
            const dropdownParent = $el.closest('[data-modal]').length ? $el.closest('.dialog-panel, .drawer-panel') : $(document.body);
            const options = {
                width: '100%',
                placeholder: $el.data('placeholder') || 'Select',
                allowClear: $el.data('clearable') !== false && !$el.prop('required'),
                dropdownParent,
            };

            if (url) {
                const dependsOn = $el.data('depends-on');
                const dependsParam = $el.data('depends-param') || 'parent';
                Object.assign(options, {
                    minimumInputLength: Number($el.data('min-input') ?? 0),
                    ajax: {
                        url,
                        delay: 300,
                        dataType: 'json',
                        data: (params) => {
                            const query = { q: params.term || '', page: params.page || 1 };
                            if (dependsOn) query[dependsParam] = $(dependsOn).val() || '';
                            return query;
                        },
                        processResults: (body) => ({
                            results: body.results || [],
                            pagination: body.pagination || { more: false },
                        }),
                        cache: true,
                    },
                });

                if (dependsOn) {
                    $(dependsOn).on('change.dependent', () => {
                        $el.val(null).trigger('change');
                        $el.prop('disabled', !$(dependsOn).val());
                    });
                    $el.prop('disabled', !$(dependsOn).val());
                }
            } else {
                const count = $el.find('option').filter((_, o) => o.value !== '').length;
                if (count <= STATIC_SEARCH_THRESHOLD) options.minimumResultsForSearch = Infinity;
            }

            $el.select2(options);
            $el.on('change', () => $el.removeAttr('aria-invalid').closest('[data-field]').find('[data-error]').text('').prop('hidden', true));
        });
}

@php
    $settingService = app(\App\Modules\Settings\Services\SettingService::class);
    $antiCopyEnabled = filter_var($settingService->get('general', 'public_anti_copy_enabled', false), FILTER_VALIDATE_BOOLEAN);
    $disableRightClick = filter_var($settingService->get('general', 'public_disable_right_click', false), FILTER_VALIDATE_BOOLEAN);
    $disableTextSelection = filter_var($settingService->get('general', 'public_disable_text_selection', false), FILTER_VALIDATE_BOOLEAN);
    $disableCopyShortcuts = filter_var($settingService->get('general', 'public_disable_copy_shortcuts', false), FILTER_VALIDATE_BOOLEAN);
    $disableDevtoolShortcuts = filter_var($settingService->get('general', 'public_disable_devtool_shortcuts', false), FILTER_VALIDATE_BOOLEAN);
@endphp

@if ($antiCopyEnabled)
    {{-- DevTools cannot be fully disabled; this is only a basic deterrent. --}}
    @if ($disableTextSelection)
        <style>
            html.zc-no-select body {
                -webkit-user-select: none;
                user-select: none;
            }

            html.zc-no-select input,
            html.zc-no-select textarea,
            html.zc-no-select select,
            html.zc-no-select [contenteditable="true"] {
                -webkit-user-select: text;
                user-select: text;
            }
        </style>
    @endif

    <script>
        (function () {
            const settings = {
                rightClick: @json($disableRightClick),
                textSelection: @json($disableTextSelection),
                copyShortcuts: @json($disableCopyShortcuts),
                devtoolShortcuts: @json($disableDevtoolShortcuts)
            };

            const isEditable = function (target) {
                if (!target) {
                    return false;
                }

                const tagName = (target.tagName || '').toUpperCase();

                return ['INPUT', 'TEXTAREA', 'SELECT', 'OPTION', 'BUTTON'].includes(tagName)
                    || target.isContentEditable;
            };

            if (settings.textSelection) {
                document.documentElement.classList.add('zc-no-select');
            }

            if (settings.rightClick) {
                document.addEventListener('contextmenu', function (event) {
                    event.preventDefault();
                });
            }

            document.addEventListener('keydown', function (event) {
                const key = String(event.key || '').toLowerCase();
                const editable = isEditable(event.target);

                if (settings.copyShortcuts && !editable && (event.ctrlKey || event.metaKey) && ['c', 'x', 'u'].includes(key)) {
                    event.preventDefault();
                    return;
                }

                if (settings.devtoolShortcuts && !editable) {
                    const devtoolsCombo = event.key === 'F12'
                        || ((event.ctrlKey || event.metaKey) && event.shiftKey && ['i', 'j'].includes(key))
                        || ((event.ctrlKey || event.metaKey) && key === 'u');

                    if (devtoolsCombo) {
                        event.preventDefault();
                    }
                }
            });
        })();
    </script>
@endif

{{--
    Sienna accessibility toolbar (contrast, text size, dyslexia font, etc.).
    Loaded from a CDN and deferred, so it never blocks first paint.
    Disable per environment with ACCESSIBILITY_WIDGET=false in .env.
--}}
@if (config('app.accessibility_widget', true))
    {{--
        Recolour the widget from its stock blue (#0848ca) to the municipal green.
        The bundle exposes no colour option on auto-init (only lang, position and
        offset are read from data attributes), so the only hook is its own
        --asw-primary custom property.

        The launcher button reads --asw-primary from the .asw-widget rule in an
        injected stylesheet, so !important here outranks it. The panel is
        different: the script sets --asw-primary *inline* with !important on
        .asw-menu, which no author stylesheet can beat on that element — so the
        panel is re-themed via .asw-menu *, where our value is what descendants
        inherit. The two derived shades are recomputed there for the same reason.
    --}}
    <style>
        .asw-widget,
        .asw-menu-btn {
            --asw-primary: var(--green, #167a3a) !important;
        }

        .asw-menu * {
            --asw-primary: var(--green, #167a3a) !important;
            --asw-primary-subtle: color-mix(in srgb, var(--green, #167a3a), transparent 90%) !important;
            --asw-primary-faint: color-mix(in srgb, var(--green, #167a3a), transparent 95%) !important;
        }

        /* The vendor footer hardcodes the blue instead of reading --asw-primary,
           so those three declarations need naming directly. */
        .asw-menu .asw-footer {
            border-top-color: color-mix(in srgb, var(--green, #167a3a), transparent 90%) !important;
        }

        .asw-menu .asw-footer-powered {
            color: var(--green, #167a3a) !important;
            outline-color: var(--green, #167a3a) !important;
        }
    </style>

    <script src="https://cdn.jsdelivr.net/npm/sienna-accessibility/dist/sienna-accessibility.umd.js" async></script>
@endif

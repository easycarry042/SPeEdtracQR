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

        /* The launcher sits over the left nav, so it rests tucked into the edge
           with only a sliver showing — enough to stay findable — and slides
           out on hover, keyboard focus, or a click (.asw-revealed, set below). */
        .asw-menu-btn {
            transform: translateX(-96%) !important;
            opacity: .55;
            transition: transform 200ms cubic-bezier(.22, 1, .36, 1), opacity 200ms ease;
        }

        .asw-menu-btn:hover,
        .asw-menu-btn:focus,
        .asw-menu-btn:focus-within,
        html.asw-revealed .asw-menu-btn {
            transform: none !important;
            opacity: 1;
        }

        @media (prefers-reduced-motion: reduce) {
            .asw-menu-btn { transition: none; }
        }
    </style>

    <script src="https://cdn.jsdelivr.net/npm/sienna-accessibility/dist/sienna-accessibility.umd.js" async></script>

    <script>
        (function () {
            var LAUNCHER = '.asw-menu-btn, .asw-menu';
            var LINGER_MS = 5000;

            var root = document.documentElement;
            var hideTimer = null;

            /* Set while the panel is open (opened by a click), so the linger
               timer never slides the button away mid-use. */
            var pinned = false;

            /**
             * @param {EventTarget|null} node
             * @returns {Element|null}
             */
            function launcherFrom(node) {
                return node && node.closest ? node.closest(LAUNCHER) : null;
            }

            function reveal() {
                window.clearTimeout(hideTimer);
                root.classList.add('asw-revealed');
            }

            function hide() {
                window.clearTimeout(hideTimer);
                pinned = false;
                root.classList.remove('asw-revealed');
            }

            /** Keeps the button out for a beat after the pointer leaves it. */
            function scheduleHide() {
                window.clearTimeout(hideTimer);

                if (pinned) {
                    return;
                }

                hideTimer = window.setTimeout(function () {
                    root.classList.remove('asw-revealed');
                }, LINGER_MS);
            }

            document.addEventListener('mouseover', function (event) {
                if (launcherFrom(event.target)) {
                    reveal();
                }
            });

            document.addEventListener('mouseout', function (event) {
                if (! launcherFrom(event.target) || launcherFrom(event.relatedTarget)) {
                    return;
                }

                scheduleHide();
            });

            document.addEventListener('click', function (event) {
                if (launcherFrom(event.target)) {
                    pinned = true;
                    reveal();

                    return;
                }

                hide();
            });

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') {
                    hide();
                }
            });
        })();
    </script>
@endif

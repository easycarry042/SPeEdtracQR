{{-- whenEcho(cb) — run cb once window.Echo exists.

     @vite ships app.js as a deferred <script type="module">, so it runs AFTER
     every inline <script> in the page body. Pages that subscribed behind a
     plain `if (window.Echo)` were testing an undefined global: the guard was
     always false, the subscription never happened, and no live update ever
     arrived. This must be a classic script and must come before @vite, so any
     page script can call it; echo.js fires `echo:ready` once Echo is built.

     Every layout that loads the bundle includes this (app, citizen, guest). --}}
<script>
    window.whenEcho = function (cb) {
        window.Echo
            ? cb(window.Echo)
            : window.addEventListener('echo:ready', function () { cb(window.Echo); }, { once: true });
    };
</script>

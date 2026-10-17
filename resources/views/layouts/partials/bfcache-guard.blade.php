{{-- Defense-in-depth against the browser back/forward cache (bfcache) restoring
     a page from history without contacting the server. Public pages are
     cacheable (good for performance), so the Back button can otherwise show a
     stale guest landing/login page after the user has already signed in — and
     any link from it drops them back into their account. Reloading on a
     bfcache restore re-hits the server, where auth + the guest/authed redirects
     run again (an authenticated user is bounced to their dashboard). --}}
<script>
    window.addEventListener('pageshow', function (event) {
        if (event.persisted) {
            window.location.reload();
        }
    });
</script>

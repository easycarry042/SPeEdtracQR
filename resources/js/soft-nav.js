/**
 * Soft navigation for the signed-in shell.
 *
 * A sidebar link (marked data-soft-nav-link) fetches the next page in the
 * background and swaps in only what changes — the page title, the page's
 * header actions, the optional header slot, <main>, and the sidebar links (so
 * the active row moves). The sidebar and header controls stay put instead of
 * the whole page flashing white and reloading.
 *
 * Anything this cannot do safely falls back to an ordinary full load, so it can
 * never strand someone: a non-HTML or failed response, a page outside the
 * shell (an expired session lands on /login), or a page that needs a script
 * bundle the current page has not loaded (the PDF editor, the request wizard).
 */

const REGIONS = ['title', 'actions', 'header', 'main', 'links'];

let inFlight = null;
let lastPath = location.pathname + location.search;
let bar = null;

const shell = () => document.querySelector('[data-soft-nav-root]');

function hardLoad(url) {
    window.location.assign(url);
}

/** Every script and stylesheet a page's <head> asks for. */
function headAssets(doc) {
    return new Set(
        [...doc.head.querySelectorAll('script[src], link[rel="stylesheet"][href], link[rel="modulepreload"][href]')]
            .map((el) => el.getAttribute('src') || el.getAttribute('href')),
    );
}

/** True when the next page needs an asset this page never loaded. */
function needsOwnAssets(doc) {
    const loaded = headAssets(document);

    return [...headAssets(doc)].some((asset) => ! loaded.has(asset));
}

function progress(state) {
    if (! bar) {
        bar = document.createElement('div');
        bar.className = 'soft-nav-bar';
        bar.setAttribute('aria-hidden', 'true');
        document.body.appendChild(bar);
    }

    bar.classList.remove('is-loading', 'is-done');
    void bar.offsetWidth; // restart the transition
    bar.classList.add(state === 'start' ? 'is-loading' : 'is-done');
}

/**
 * Run the scripts that came in with the new content. Scripts parsed by
 * DOMParser never execute, so each is recreated. Pages register Alpine
 * components on `alpine:init` (and the odd one waits for DOMContentLoaded);
 * both fired long ago, so those listeners are collected and called directly.
 */
function runScripts(scripts) {
    const pending = [];
    const addEventListener = document.addEventListener;

    document.addEventListener = function (type, listener, options) {
        if (type === 'alpine:init' || type === 'DOMContentLoaded') {
            pending.push(listener);

            return;
        }

        return addEventListener.call(this, type, listener, options);
    };

    try {
        for (const old of scripts) {
            const fresh = document.createElement('script');
            for (const attribute of old.attributes) {
                fresh.setAttribute(attribute.name, attribute.value);
            }
            fresh.textContent = old.textContent;
            old.replaceWith(fresh);
        }
    } finally {
        document.addEventListener = addEventListener;
    }

    for (const listener of pending) {
        try {
            typeof listener === 'function' ? listener(new Event('alpine:init')) : listener.handleEvent(new Event('alpine:init'));
        } catch (error) {
            console.error(error);
        }
    }
}

function swap(doc) {
    const Alpine = window.Alpine;

    // Live-update channels belong to the page being left; the next page
    // subscribes to its own.
    window.Echo?.leaveAllChannels?.();

    document.title = doc.title;
    document.documentElement.className = doc.documentElement.className;
    document.body.className = doc.body.className;

    const swapped = [];
    const scripts = [];

    const replace = () => {
        for (const name of REGIONS) {
            const current = document.querySelector(`[data-soft-nav="${name}"]`);
            const next = doc.querySelector(`[data-soft-nav="${name}"]`);
            if (! current || ! next) {
                continue;
            }

            Alpine?.destroyTree(current);
            const incoming = document.importNode(next, true);
            current.replaceWith(incoming);
            swapped.push(incoming);
            scripts.push(...incoming.querySelectorAll('script'));
        }

        runScripts(scripts);
    };

    // Insert with Alpine's observer paused, so components start only after
    // their scripts have registered them.
    Alpine ? Alpine.mutateDom(replace) : replace();
    swapped.forEach((region) => Alpine?.initTree(region));
}

async function navigate(url, { push = true } = {}) {
    inFlight?.abort();
    inFlight = new AbortController();
    progress('start');

    let response;
    try {
        response = await fetch(url, {
            signal: inFlight.signal,
            credentials: 'same-origin',
            headers: { Accept: 'text/html', 'X-Soft-Nav': '1' },
        });
    } catch (error) {
        if (error.name !== 'AbortError') {
            hardLoad(url);
        }

        return;
    }

    if (! response.ok || ! (response.headers.get('content-type') || '').includes('text/html')) {
        hardLoad(response.url || url);

        return;
    }

    const doc = new DOMParser().parseFromString(await response.text(), 'text/html');

    if (! doc.querySelector('[data-soft-nav-root]') || needsOwnAssets(doc)) {
        hardLoad(response.url || url);

        return;
    }

    swap(doc);

    // response.url is where redirects ended up (e.g. /home → the role's dashboard).
    const finalUrl = response.url || url;
    if (push) {
        history.pushState({ softNav: true }, '', finalUrl);
    } else if (finalUrl !== location.href) {
        history.replaceState({ softNav: true }, '', finalUrl);
    }
    lastPath = location.pathname + location.search;

    window.scrollTo(0, 0);
    document.querySelector('[data-soft-nav="title"]')?.focus({ preventScroll: true });
    progress('done');
    document.dispatchEvent(new CustomEvent('soft-nav:loaded', { detail: { url: finalUrl } }));
}

document.addEventListener('click', (event) => {
    const link = event.target.closest?.('a[data-soft-nav-link]');

    if (! link || event.defaultPrevented || event.button !== 0
        || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey
        || (link.target && link.target !== '_self') || ! shell()) {
        return;
    }

    const url = new URL(link.href, location.href);
    if (url.origin !== location.origin) {
        return;
    }

    event.preventDefault();
    navigate(url.href);
});

// Back / forward between soft-loaded pages. A change of hash alone is not a
// new page.
window.addEventListener('popstate', () => {
    const path = location.pathname + location.search;
    if (path === lastPath || ! shell()) {
        return;
    }

    navigate(location.href, { push: false });
});

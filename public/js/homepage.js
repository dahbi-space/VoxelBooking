/**
 * Homepage scripts — anti-spam timestamp + scroll reveal.
 *
 * Loaded as external script to maintain strict CSP (script-src 'self').
 * No inline JavaScript on the public homepage.
 */

// Anti-spam: populate timestamp field with current time
(function() {
    var tsField = document.getElementById('ra-ts');
    if (tsField) {
        tsField.value = String(Date.now());
    }
})();

// Scroll reveal via IntersectionObserver
(function() {
    var els = document.querySelectorAll('.lp-reveal');
    if (!els.length) return;

    if (!('IntersectionObserver' in window)) {
        els.forEach(function(el) { el.classList.add('is-visible'); });
        return;
    }

    var obs = new IntersectionObserver(function(entries) {
        entries.forEach(function(entry) {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible');
                obs.unobserve(entry.target);
            }
        });
    }, { threshold: 0.15 });

    els.forEach(function(el) { obs.observe(el); });
})();

/**
 * Theme detection — must load in <head> before paint to prevent FOUC.
 * Reads localStorage preference or falls back to system color scheme.
 */
(function(){
    var t = localStorage.getItem('vb-theme');
    if (!t) t = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    document.documentElement.setAttribute('data-theme', t);
})();

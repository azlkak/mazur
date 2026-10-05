/* Language paths and compatibility with the former ?lang= URLs. */
(() => {
  const languages = ['pl', 'en', 'uk', 'ru'];
  const segments = location.pathname.split('/').filter(Boolean);
  const pathLanguage = languages.includes(segments[0]) && segments[0] !== 'pl' ? segments[0] : 'pl';
  const query = new URLSearchParams(location.search);
  const oldLanguage = query.get('lang');
  const validOldLanguage = languages.includes(oldLanguage);
  const cleanPath = '/' + segments.slice(pathLanguage === 'pl' ? 0 : 1).join('/');
  const routePath = cleanPath === '/' || cleanPath.endsWith('/') ? cleanPath : cleanPath.replace(/index\.html$/, '');
  const normalizedPath = routePath || '/';
  const pathFor = (path, lang) => (lang === 'pl' ? path : `/${lang}${path}`);
  window.MAZUR_LANG = pathLanguage;
  window.mazurLanguagePath = pathFor;
  window.mazurLocalizedUrl = (target, lang = window.MAZUR_LANG) => {
    const url = new URL(target, document.baseURI);
    if (url.origin !== location.origin) return url.href;
    const parts = url.pathname.split('/').filter(Boolean);
    const bare = languages.includes(parts[0]) ? parts.slice(1) : parts;
    let path = '/' + bare.join('/');
    if (url.pathname.endsWith('/') && !path.endsWith('/')) path += '/';
    if (path.endsWith('/index.html')) path = path.slice(0, -10);
    if (path === '/index.html') path = '/';
    // Offer details use the Polish source content until their translations exist.
    if (path === '/oferta/' && url.searchParams.has('id')) lang = 'pl';
    url.pathname = pathFor(path, lang);
    url.searchParams.delete('lang');
    return url.pathname + url.search + url.hash;
  };
  const isOfferDetail = normalizedPath.replace(/\/$/, '') === '/oferta' && query.has('id');
  if (isOfferDetail && pathLanguage !== 'pl' && location.protocol !== 'file:') {
    query.delete('lang');
    location.replace('/oferta/' + (query.size ? '?' + query : '') + location.hash);
    return;
  }
  if (validOldLanguage) {
    query.delete('lang');
    const destination = (isOfferDetail ? '/oferta/' : pathFor(normalizedPath, oldLanguage)) + (query.size ? '?' + query : '') + location.hash;
    if (location.protocol !== 'file:' && destination !== location.pathname + location.search + location.hash) {
      location.replace(destination);
      return;
    }
  }
  // Some older links are still created by page scripts. Normalize them as soon as they appear.
  const switcher = 'a:is(.chrome-language a, .language-picker a, .language-menu a, #language-picker-home a)';
  const rewrite = root => {
    const anchors = root.matches?.('a[href]') ? [root] : root.querySelectorAll?.('a[href]') || [];
    anchors.forEach(anchor => {
      const raw = anchor.getAttribute('href');
      if (!raw || /^(?:mailto:|tel:|javascript:)/i.test(raw)) return;
      if (raw.startsWith('#')) {
        const next = location.pathname + location.search + raw;
        if (raw !== next) anchor.setAttribute('href', next);
        return;
      }
      const target = new URL(raw, document.baseURI);
      if (target.origin !== location.origin) return;
      if (anchor.matches(switcher) && languages.includes(target.searchParams.get('lang')))
        anchor.dataset.targetLang = target.searchParams.get('lang');
      const selected = anchor.matches(switcher) && languages.includes(anchor.dataset.targetLang)
        ? anchor.dataset.targetLang : window.MAZUR_LANG;
      const next = window.mazurLocalizedUrl(raw, selected);
      if (raw !== next) anchor.setAttribute('href', next);
    });
  };
  const start = () => {
    rewrite(document.body);
    new MutationObserver(records => records.forEach(record => {
      if (record.type === 'attributes') rewrite(record.target);
      else record.addedNodes.forEach(node => { if (node.nodeType === 1) rewrite(node); });
    })).observe(document.body, {childList: true, subtree: true, attributes: true, attributeFilter: ['href']});
  };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
  else start();
})();

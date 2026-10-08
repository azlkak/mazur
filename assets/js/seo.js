/* Shared metadata for language paths. */
(() => {
  const config = window.MAZUR_SEO;
  if (!config) return;
  // The PHP offer route supplies offer-specific metadata in the initial HTML.
  // Never replace it with the generic, noindex metadata for /oferta/.
  if (document.head.querySelector('meta[name="mazur-offer-ssr"]')) return;
  // Static language-path offer pages load their real title from the offer API.
  // Keep their generated noindex metadata, but do not overwrite that title.
  if (/\/oferta\/?$/.test(location.pathname) && new URLSearchParams(location.search).has('id')) return;
  const segments = location.pathname.replace(/index\.html$/, '').split('/').filter(Boolean);
  const candidate = segments.length ? segments[segments.length - 1] + '/' : '';
  const route = config.pages[candidate] ? candidate : '';
  const requested = (window.MAZUR_LANG || new URLSearchParams(location.search).get('lang'));
  const lang = config.languages.includes(requested) ? requested : 'pl';
  const page = config.pages[route];
  const canonical = config.base + (lang === 'pl' ? '' : lang + '/') + route;
  function meta(name, content, property = false) {
    const attr = property ? 'property' : 'name';
    let el = document.head.querySelector(`meta[${attr}="${name}"]`);
    if (!el) { el = document.createElement('meta'); el.setAttribute(attr, name); document.head.appendChild(el); }
    if (el.content !== content) el.content = content;
  }
  function apply() {
    const title = page.names[lang] + ' | MazurEstate';
    if (document.title !== title) document.title = title;
    document.documentElement.lang = lang;
    meta('description', page.descriptions[lang]);
    meta('robots', page.index ? 'index,follow,max-image-preview:large' : 'noindex,follow');
    let link = document.head.querySelector('link[rel="canonical"]');
    if (!link) { link = document.createElement('link'); link.rel = 'canonical'; document.head.appendChild(link); }
    link.href = canonical;
    meta('og:title', title, true); meta('og:description', page.descriptions[lang], true);
    meta('og:url', canonical, true); meta('og:type', 'website', true); meta('og:site_name', 'MazurEstate', true);
    meta('og:locale', {pl:'pl_PL',en:'en_GB',uk:'uk_UA',ru:'ru_RU'}[lang], true);
    let schema = document.getElementById('seo-structured-data');
    if (!schema) { schema=document.createElement('script'); schema.id='seo-structured-data'; schema.type='application/ld+json'; document.head.appendChild(schema); }
    const businessId=config.base+'#business';
    const graph=[{'@type':'RealEstateAgent','@id':businessId,name:'MazurEstate',url:config.base,telephone:'+48503937749',email:'info@mazurestate.pl',areaServed:{'@type':'City',name:'Warszawa'},contactPoint:{'@type':'ContactPoint',contactType:'customer service',telephone:'+48503937749',availableLanguage:config.languages}},
      {'@type':'WebSite','@id':config.base+'#website',url:config.base,name:'MazurEstate',publisher:{'@id':businessId},inLanguage:config.languages},
      {'@type':'WebPage','@id':canonical+'#webpage',url:canonical,name:title,description:page.descriptions[lang],inLanguage:lang,isPartOf:{'@id':config.base+'#website'},about:{'@id':businessId}}];
    if(page.service) graph.push({'@type':'Service','@id':canonical+'#service',name:page.names[lang],description:page.descriptions[lang],url:canonical,provider:{'@id':businessId}});
    schema.textContent=JSON.stringify({'@context':'https://schema.org','@graph':graph});
  }
  apply();
  document.addEventListener('DOMContentLoaded', () => {
    apply();
    document.querySelectorAll('[data-seo-service]').forEach(link => {
      const service = link.getAttribute('data-seo-service');
      link.href = window.mazurLocalizedUrl(service, lang);
      link.textContent = config.pages[service].names[lang];
    });
  });
  window.addEventListener('load',apply);
  // Existing translation modules load asynchronously and also set document.title.
  // Observe only the title to restore the route-specific search metadata.
  new MutationObserver(apply).observe(document.querySelector('title'),{childList:true,characterData:true,subtree:true});
})();

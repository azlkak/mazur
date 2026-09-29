(() => {
  const params = new URLSearchParams(location.search);
  const lang = ['pl', 'en', 'uk', 'ru'].includes(params.get('lang')) ? params.get('lang') : 'pl';
  const copy = {
    pl: ['Wybrane oferty', 'Nieruchomości warte uwagi', 'Zobacz wszystkie oferty', 'powierzchnia', 'pokoje', 'Poprzednie oferty', 'Następne oferty'],
    en: ['Featured properties', 'Properties worth exploring', 'See all properties', 'area', 'rooms', 'Previous properties', 'Next properties'],
    uk: ['Обрані пропозиції', 'Нерухомість, варта уваги', 'Усі пропозиції', 'площа', 'кімнат', 'Попередні пропозиції', 'Наступні пропозиції'],
    ru: ['Избранные предложения', 'Недвижимость, достойная внимания', 'Все предложения', 'площадь', 'комнат', 'Предыдущие предложения', 'Следующие предложения']
  }[lang];
  let offers = [];
  function updateControls(grid, previous, next) {
    const last = grid.scrollWidth - grid.clientWidth;
    previous.disabled = grid.scrollLeft <= 1;
    next.disabled = last <= 1 || grid.scrollLeft >= last - 1;
  }
  function move(grid, direction) {
    const cardWidth = grid.querySelector('.featured-card')?.getBoundingClientRect().width || grid.clientWidth;
    const gap = Number.parseFloat(getComputedStyle(grid).gap) || 0;
    grid.scrollBy({left: direction * (cardWidth + gap), behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth'});
  }
  const api = 'https://api.mazurestate.pl/api/';
  const make = (tag, className, value) => { const el = document.createElement(tag); if (className) el.className = className; if (value !== undefined) el.textContent = value; return el; };
  const number = new Intl.NumberFormat(lang === 'en' ? 'en-GB' : 'pl-PL');
  function card(offer) {
    const anchor = make('a', 'featured-card');
    anchor.href = `oferta/?id=${encodeURIComponent(offer.id)}&lang=${lang}`;
    if (offer.images?.[0]) {
      const img = make('img'); img.src = offer.images[0]; img.alt = offer.title || ''; img.loading = 'lazy'; img.decoding = 'async'; anchor.append(img);
    }
    const body = make('div', 'featured-card-body');
    const place = [offer.city, offer.district].filter(Boolean).join(' · ');
    body.append(make('span', 'featured-card-location', place || copy[0]));
    body.append(make('h3', '', offer.title || offer.type || copy[0]));
    const meta = make('p', 'featured-card-meta');
    if (Number(offer.area) > 0) meta.append(make('span', '', `${copy[3]} ${number.format(offer.area)} m²`));
    if (Number(offer.rooms) > 0) meta.append(make('span', '', `${number.format(offer.rooms)} ${copy[4]}`));
    if (offer.number) meta.append(make('span', '', `nr ${offer.number}`));
    body.append(meta);
    if (Number(offer.price) > 0) body.append(make('p', 'featured-card-price', `${number.format(offer.price)} ${offer.currency || 'PLN'}`));
    anchor.append(body); return anchor;
  }
  function render() {
    const section = document.getElementById('featured-offers');
    const grid = document.getElementById('featured-grid');
    if (!section || !grid) return;
    if (!offers.length) { section.hidden = true; return; }
    if (grid.querySelector('.featured-card')) return;
    document.getElementById('featured-eyebrow').textContent = copy[0];
    document.getElementById('featured-title').textContent = copy[1];
    const all = document.getElementById('featured-all');
    all.textContent = copy[2] + ' →';
    all.href = `wyniki-wyszukiwania/?type=mieszkania&transaction=sprzedaz&lang=${lang}`;
    const previous = document.getElementById('featured-prev');
    const next = document.getElementById('featured-next');
    grid.setAttribute('aria-label', copy[0]);
    previous.setAttribute('aria-label', copy[5]);
    next.setAttribute('aria-label', copy[6]);
    grid.append(...offers.map(card));
    section.hidden = false;
    previous.onclick = () => move(grid, -1);
    next.onclick = () => move(grid, 1);
    grid.onscroll = () => updateControls(grid, previous, next);
    updateControls(grid, previous, next);
    const categoriesLink = document.querySelector('#categories a[href*="wyniki-wyszukiwania"]');
    if (categoriesLink) categoriesLink.href = '#featured-offers';
  }
  new MutationObserver(render).observe(document.body, {childList: true, subtree: true});
  window.addEventListener('resize', render);
  render();
  (async () => {
    const response = await fetch(api + 'featured-offers.php');
    if (!response.ok) return;
    const { ids } = await response.json();
    if (!Array.isArray(ids) || !ids.length) return;
    const selected = await Promise.all(ids.slice(0, 10).map(async id => {
      try {
        const response = await fetch(api + 'mls-offer.php?id=' + encodeURIComponent(id));
        return response.ok ? (await response.json()).offer : null;
      } catch { return null; }
    }));
    offers = selected.filter(Boolean);
    render();
  })().catch(() => {});
})();

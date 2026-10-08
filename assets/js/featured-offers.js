(() => {
  const params = new URLSearchParams(location.search);
  const lang = ['pl', 'en', 'uk', 'ru'].includes((window.MAZUR_LANG || params.get('lang'))) ? (window.MAZUR_LANG || params.get('lang')) : 'pl';
  const copy = {
    pl: ['Wybrane oferty', 'Nieruchomości warte uwagi', 'Zobacz wszystkie oferty', 'powierzchnia', 'pokoje', 'Poprzednie oferty', 'Następne oferty'],
    en: ['Featured properties', 'Properties worth exploring', 'See all properties', 'area', 'rooms', 'Previous properties', 'Next properties'],
    uk: ['Обрані пропозиції', 'Нерухомість, варта уваги', 'Усі пропозиції', 'площа', 'кімнат', 'Попередні пропозиції', 'Наступні пропозиції'],
    ru: ['Избранные предложения', 'Недвижимость, достойная внимания', 'Все предложения', 'площадь', 'комнат', 'Предыдущие предложения', 'Следующие предложения']
  }[lang];
  let offers = [];
  const localPreview = location.protocol === 'file:' || ['localhost', '127.0.0.1'].includes(location.hostname);
  const previewNote = {
    pl: 'Wybrane oferty są dostępne na stronie online. Podgląd lokalny nie może pobrać ich z API.',
    en: 'Featured properties are available on the live site. The local preview cannot load them from the API.',
    uk: 'Обрані пропозиції доступні на сайті. Локальний перегляд не може завантажити їх через API.',
    ru: 'Избранные предложения доступны на сайте. Локальный просмотр не может загрузить их через API.'
  }[lang];
  const propertyNumberLabel = {pl:'nr',en:'no.',uk:'№',ru:'№'}[lang];
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
  const number = new Intl.NumberFormat({pl:'pl-PL',en:'en-GB',uk:'uk-UA',ru:'ru-RU'}[lang]);
  function card(offer) {
    const anchor = make('a', 'featured-card');
    anchor.href = window.mazurLocalizedUrl(`oferta/?id=${encodeURIComponent(offer.id)}`);
    const photos = Array.isArray(offer.images) ? offer.images.filter(src => typeof src === 'string' && src.trim()) : [];
    if (photos.length) {
      const media = make('div', 'featured-card-media');
      const img = make('img'); img.src = photos[0]; img.alt = offer.title || ''; img.loading = 'lazy'; img.decoding = 'async'; media.append(img);
      const count = make('span', 'featured-photo-count', `1 / ${photos.length}`); count.setAttribute('aria-live', 'polite'); media.append(count);
      if (photos.length > 1) {
        let index = 0, start = null, suppressClickUntil = 0;
        media.addEventListener('touchstart', event => { start = event.touches.length === 1 ? {x:event.touches[0].clientX,y:event.touches[0].clientY} : null; }, {passive:true});
        media.addEventListener('touchend', event => { if (!start || !event.changedTouches.length) return; const dx = event.changedTouches[0].clientX - start.x, dy = event.changedTouches[0].clientY - start.y; start = null; if (Math.abs(dx) < 40 || Math.abs(dx) < Math.abs(dy) * 1.25) return; index = (index + (dx < 0 ? 1 : -1) + photos.length) % photos.length; img.src = photos[index]; count.textContent = `${index + 1} / ${photos.length}`; suppressClickUntil = Date.now() + 500; }, {passive:true});
        media.addEventListener('touchcancel', () => { start = null; }, {passive:true});
        anchor.addEventListener('click', event => { if (Date.now() < suppressClickUntil) event.preventDefault(); });
      }
      anchor.append(media);
    }
    const body = make('div', 'featured-card-body');
    const place = [offer.city, offer.district].filter(Boolean).join(' · ');
    body.append(make('span', 'featured-card-location', place || copy[0]));
    const heading = make('h3', '', offer.title || offer.type || copy[0]);
    heading.lang = offer.descriptionLanguage || 'pl';
    body.append(heading);
    const meta = make('p', 'featured-card-meta');
    if (Number(offer.area) > 0) meta.append(make('span', '', `${copy[3]} ${number.format(offer.area)} m²`));
    if (Number(offer.rooms) > 0) meta.append(make('span', '', `${number.format(offer.rooms)} ${copy[4]}`));
    if (offer.number) meta.append(make('span', '', `${propertyNumberLabel} ${offer.number}`));
    body.append(meta);
    if (Number(offer.price) > 0) body.append(make('p', 'featured-card-price', `${number.format(offer.price)} ${offer.currency || 'PLN'}`));
    anchor.append(body); return anchor;
  }
  function render() {
    const section = document.getElementById('featured-offers');
    const grid = document.getElementById('featured-grid');
    if (!section || !grid) return;
    if (!offers.length) {
      if (localPreview) {
        section.hidden = false;
        if (document.getElementById('featured-eyebrow').textContent !== copy[0]) document.getElementById('featured-eyebrow').textContent = copy[0];
        if (document.getElementById('featured-title').textContent !== copy[1]) document.getElementById('featured-title').textContent = copy[1];
        if (!grid.querySelector('.featured-preview-note')) grid.append(make('p', 'featured-preview-note', previewNote));
      } else section.hidden = true;
      return;
    }
    grid.querySelector('.featured-preview-note')?.remove();
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
        const response = await fetch(api + 'mls-offer.php?id=' + encodeURIComponent(id) + '&lang=' + encodeURIComponent(lang));
        return response.ok ? (await response.json()).offer : null;
      } catch { return null; }
    }));
    offers = selected.filter(Boolean);
    render();
  })().catch(() => {});
})();

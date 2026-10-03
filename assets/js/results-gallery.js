/** Full search-result galleries. No detail requests until an arrow is used. */
export function createResultsGallery({
  lang = 'pl',
  endpoint = 'https://api.mazurestate.pl/api/mls-offer.php',
  fetchImpl = globalThis.fetch.bind(globalThis),
  timeoutMs = 12000,
} = {}) {
  const copy = ({
    pl: { prev: 'Poprzednie zdjęcie', next: 'Następne zdjęcie', loading: 'Ładowanie zdjęć…', error: 'Nie udało się wczytać zdjęć. Kliknij strzałkę, aby spróbować ponownie.', empty: 'Brak zdjęć', unknown: 'Pełna liczba zdjęć zostanie wczytana po kliknięciu strzałki.' },
    en: { prev: 'Previous photo', next: 'Next photo', loading: 'Loading photos…', error: 'Could not load photos. Use an arrow to try again.', empty: 'No photos', unknown: 'The full photo count will load when you use an arrow.' },
    uk: { prev: 'Попереднє фото', next: 'Наступне фото', loading: 'Завантаження фото…', error: 'Не вдалося завантажити фото. Натисніть стрілку, щоб спробувати ще раз.', empty: 'Немає фото', unknown: 'Повна кількість фото з’явиться після натискання стрілки.' },
    ru: { prev: 'Предыдущее фото', next: 'Следующее фото', loading: 'Загрузка фото…', error: 'Не удалось загрузить фото. Нажмите стрелку, чтобы повторить.', empty: 'Нет фото', unknown: 'Полное количество фото появится после нажатия на стрелку.' },
  })[lang] || null;
  const labels = copy || { prev: 'Previous photo', next: 'Next photo', loading: 'Loading photos…', error: 'Could not load photos. Use an arrow to try again.', empty: 'No photos', unknown: 'Use an arrow to load the full photo count.' };
  const states = new Map();
  const escape = value => String(value ?? '').replace(/[&<>"']/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[char]);
  const validImages = images => Array.isArray(images) ? images.filter(src => typeof src === 'string' && src.trim() !== '') : [];

  function stateFor(offer) {
    const id = String(offer.id);
    if (!states.has(id)) {
      const images = offer.hasPhotos ? validImages(offer.gallery) : [];
      const providedCount = offer.imageCount;
      const known = Number.isSafeInteger(providedCount) && providedCount >= images.length;
      // Older API versions cap previews at five and do not provide imageCount.
      // Never present that cap as the full count: show 1/… until interaction.
      const total = known ? providedCount : images.length < 5 ? images.length : null;
      states.set(id, {
        id, title: offer.title, images, total, index: 0,
        complete: total !== null && total === images.length,
        fallback: offer.fallbackImage || '../assets/images/category-apartments.webp',
        loading: false, error: '',
      });
    }
    return states.get(id);
  }

  const canMove = state => state.total === null || state.total > 1;
  const countText = state => state.total === 0 ? labels.empty : `▣ ${state.index + 1}/${state.total ?? '…'}`;
  function dotsMarkup(state) {
    const total = state.total ?? state.images.length;
    const count = Math.min(5, total);
    const start = Math.max(0, Math.min(state.index - 2, total - count));
    return Array.from({ length: count }, (_, offset) => {
      const index = start + offset;
      return `<span data-photo-index="${index}" class="${index === state.index ? 'active' : ''}"></span>`;
    }).join('');
  }

  function markup(offer) {
    const state = stateFor(offer);
    const disabled = state.loading || !canMove(state);
    const message = state.loading ? labels.loading : state.error;
    return `<div class="gallery" data-offer="${escape(state.id)}" aria-busy="${state.loading}">
      <img src="${escape(state.images[state.index] || state.fallback)}" alt="${escape(state.title)}" loading="lazy" decoding="async">
      <button type="button" class="gallery-arrow prev" data-gallery="prev" data-id="${escape(state.id)}" aria-label="${escape(labels.prev)}" aria-disabled="${disabled}"${!canMove(state) ? ' style="display:none" tabindex="-1"' : ''}>‹</button>
      <button type="button" class="gallery-arrow next" data-gallery="next" data-id="${escape(state.id)}" aria-label="${escape(labels.next)}" aria-disabled="${disabled}"${!canMove(state) ? ' style="display:none" tabindex="-1"' : ''}>›</button>
      <div class="gallery-dots" aria-hidden="true">${dotsMarkup(state)}</div>
      <span class="gallery-count" aria-live="polite" aria-atomic="true" title="${escape(state.total === null ? labels.unknown : '')}">${escape(countText(state))}</span>
      <span class="gallery-message" role="status" style="position:absolute;z-index:3;left:12px;right:12px;bottom:58px;padding:8px 12px;border-radius:10px;background:rgba(20,31,44,.94);color:#fff;font-size:13px;line-height:1.4;pointer-events:none"${message ? '' : ' hidden'}>${escape(message)}</span>
    </div>`;
  }

  function updateCard(state, list) {
    // The user may have changed page/sort while the request was in flight.
    const gallery = [...list.querySelectorAll('.gallery[data-offer]')].find(node => node.dataset.offer === state.id);
    if (!gallery) return;
    const image = gallery.querySelector('img');
    const src = state.images[state.index] || state.fallback;
    if (image.getAttribute('src') !== src) image.setAttribute('src', src);
    gallery.setAttribute('aria-busy', String(state.loading));
    gallery.querySelectorAll('[data-gallery]').forEach(button => {
      button.setAttribute('aria-disabled', String(state.loading || !canMove(state)));
      button.style.display = canMove(state) ? '' : 'none';
      if (canMove(state)) button.removeAttribute('tabindex');
      else button.setAttribute('tabindex', '-1');
    });
    gallery.querySelector('.gallery-dots').innerHTML = dotsMarkup(state);
    const counter = gallery.querySelector('.gallery-count');
    counter.textContent = countText(state);
    counter.title = state.total === null ? labels.unknown : '';
    const message = gallery.querySelector('.gallery-message');
    message.textContent = state.loading ? labels.loading : state.error;
    message.hidden = !message.textContent;
  }

  async function step(offer, delta, list) {
    const state = stateFor(offer);
    if (state.loading || !canMove(state)) return;
    state.error = '';
    if (!state.complete) {
      state.loading = true;
      updateCard(state, list);
      const controller = new AbortController();
      const timer = setTimeout(() => controller.abort(), timeoutMs);
      try {
        const url = new URL(endpoint);
        url.searchParams.set('id', state.id);
        const response = await fetchImpl(url.href, { headers: { Accept: 'application/json' }, signal: controller.signal, credentials: 'omit' });
        if (!response.ok) throw new Error('Gallery API unavailable');
        const payload = await response.json();
        if (!payload?.offer || String(payload.offer.id) !== state.id || !Array.isArray(payload.offer.images)) throw new Error('Invalid gallery response');
        const images = validImages(payload.offer.images);
        // Accept public HTTPS image URLs only; never render arbitrary schemes.
        if (images.length !== payload.offer.images.length || images.some(src => { try { return new URL(src).protocol !== 'https:'; } catch { return true; } })) throw new Error('Invalid gallery images');
        const currentSrc = state.images[state.index];
        state.images = images;
        state.total = images.length;
        state.index = Math.max(0, images.indexOf(currentSrc));
        state.complete = true;
      } catch (error) {
        // Keep the preview and permit a retry; do not silently wrap at photo 5.
        state.error = labels.error;
        return;
      } finally {
        clearTimeout(timer);
        state.loading = false;
        updateCard(state, list);
      }
    }
    if (state.images.length) state.index = (state.index + (delta < 0 ? -1 : 1) + state.images.length) % state.images.length;
    updateCard(state, list);
  }

  return { markup, step };
}

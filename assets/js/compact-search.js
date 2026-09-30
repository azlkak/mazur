(() => {
  const script = document.currentScript;
  const type = script?.dataset.searchType;
  if (!['lokale', 'dzialki', 'mieszkania'].includes(type)) return;
  if ((new URLSearchParams(location.search).get('lang') || 'pl') !== 'pl') return;

  const label = { lokale: 'Lokale komercyjne', dzialki: 'Działki', mieszkania: 'Mieszkania' }[type];
  const start = () => {
    const main = document.querySelector('main');
    if (!main) return;
    const section = document.createElement('section');
    section.className = 'compact-search';
    section.setAttribute('aria-labelledby', 'compact-search-title');
    section.innerHTML = `<div class="compact-search-intro"><span>PRZEGLĄDAJ OFERTY</span><h2 id="compact-search-title">Wolisz poszukać samodzielnie?</h2><p>Możesz też przejrzeć dostępne oferty. Typ nieruchomości jest już dopasowany do tej strony.</p></div>
      <div class="compact-search-type">${label}</div>
      <form class="compact-search-form" action="../wyniki-wyszukiwania/" method="get">
        <input type="hidden" name="type" value="${type}">
        <div class="compact-search-field compact-search-location">
          <label for="compact-location">Lokalizacja</label>
          <input id="compact-location" name="location" placeholder="Wpisz miasto lub dzielnicę" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="compact-location-options">
          <ul class="compact-search-locations" id="compact-location-options" role="listbox" hidden></ul>
        </div>
        <div class="compact-search-field compact-search-transaction"><span class="compact-search-label" id="compact-transaction-label">Transakcja</span><input type="hidden" name="transaction" value="sprzedaz"><button id="compact-transaction" class="compact-search-transaction-trigger" type="button" aria-labelledby="compact-transaction-label compact-transaction-value" aria-haspopup="listbox" aria-controls="compact-transaction-options" aria-expanded="false"><span id="compact-transaction-value">Sprzedaż</span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg></button><div class="compact-search-transaction-options" id="compact-transaction-options" role="listbox" aria-labelledby="compact-transaction-label" hidden><button type="button" role="option" data-value="sprzedaz" aria-selected="true">Sprzedaż</button><button type="button" role="option" data-value="wynajem" aria-selected="false">Wynajem</button></div></div>
        <div class="compact-search-field"><label for="compact-price">Cena max</label><input id="compact-price" name="price" inputmode="numeric" placeholder="np. 1 000 000 PLN"></div>
        <div class="compact-search-field"><label for="compact-area">Powierzchnia</label><input id="compact-area" name="area" inputmode="decimal" placeholder="min m²"></div>
        <input type="hidden" name="lang" value="pl">
        <button class="compact-search-submit" type="submit"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m16.5 16.5 5 5"/></svg>Szukaj</button>
      </form>`;
    main.appendChild(section);

    const input = section.querySelector('#compact-location');
    const list = section.querySelector('#compact-location-options');
    const transaction = section.querySelector('#compact-transaction');
    const transactionMenu = section.querySelector('#compact-transaction-options');
    const closeTransaction = () => { transactionMenu.hidden = true; transaction.setAttribute('aria-expanded', 'false'); };
    transaction.addEventListener('click', () => {
      const open = transactionMenu.hidden;
      transactionMenu.hidden = !open;
      transaction.setAttribute('aria-expanded', String(open));
    });
    transactionMenu.querySelectorAll('[data-value]').forEach(option => option.addEventListener('click', () => {
      section.querySelector('input[name="transaction"]').value = option.dataset.value;
      section.querySelector('#compact-transaction-value').textContent = option.textContent;
      transactionMenu.querySelectorAll('[data-value]').forEach(item => item.setAttribute('aria-selected', String(item === option)));
      closeTransaction();
      transaction.focus();
    }));
    const normalize = value => String(value || '').toLocaleLowerCase('pl').replace(/ł/g, 'l').normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim();
    let locations = [];
    const close = () => { list.hidden = true; input.setAttribute('aria-expanded', 'false'); };
    const render = () => {
      const query = normalize(input.value);
      list.replaceChildren();
      if (!locations.length) return close();
      const matches = locations.filter(name => normalize(name).includes(query)).slice(0, 12);
      if (!matches.length) return close();
      matches.forEach(name => {
        const item = document.createElement('li');
        const button = document.createElement('button');
        button.type = 'button';
        button.setAttribute('role', 'option');
        button.textContent = name;
        button.addEventListener('click', () => { input.value = name; close(); input.focus(); });
        item.appendChild(button);
        list.appendChild(item);
      });
      list.hidden = false;
      input.setAttribute('aria-expanded', 'true');
    };
    input.addEventListener('input', render);
    input.addEventListener('focus', render);
    input.addEventListener('keydown', event => {
      if (event.key === 'Escape') close();
      if (event.key === 'ArrowDown' && !list.hidden) { event.preventDefault(); list.querySelector('button')?.focus(); }
    });
    section.addEventListener('keydown', event => { if (event.key === 'Escape') { close(); closeTransaction(); } });
    document.addEventListener('pointerdown', event => {
      if (!section.querySelector('.compact-search-location').contains(event.target)) close();
      if (!section.querySelector('.compact-search-transaction').contains(event.target)) closeTransaction();
    });

    const readLocations = payload => {
      const names = new Set();
      (payload.locations || payload.offers || []).forEach(offer => {
        const city = String(offer.city || '').trim();
        const district = String(offer.district || '').trim();
        if (city) names.add(city);
        if (city && district) names.add(`${city}, ${district}`);
      });
      locations = [...names].sort((a, b) => a.localeCompare(b, 'pl'));
      render();
    };
    fetch('https://api.mazurestate.pl/api/mls-locations.php')
      .then(response => response.ok ? response.json() : Promise.reject(new Error('Locations unavailable')))
      .catch(() => fetch('https://api.mazurestate.pl/api/mls-test.php').then(response => response.ok ? response.json() : Promise.reject(new Error('Locations unavailable'))))
      .then(readLocations).catch(() => {});
  };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start); else start();
})();

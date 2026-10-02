(() => {
  const script = document.currentScript;
  const type = script?.dataset.searchType;
  if (!['lokale', 'dzialki', 'mieszkania'].includes(type)) return;
  const requestedLang = (window.MAZUR_LANG || new URLSearchParams(location.search).get('lang')) || document.documentElement.lang;
  const lang = ['pl', 'en', 'uk', 'ru'].includes(requestedLang) ? requestedLang : 'pl';
  const copy = {
    pl: { types: { lokale: 'Lokale komercyjne', dzialki: 'Działki', mieszkania: 'Mieszkania' }, eyebrow: 'PRZEGLĄDAJ OFERTY', title: 'Wolisz poszukać samodzielnie?', intro: 'Możesz też przejrzeć dostępne oferty. Typ nieruchomości jest już dopasowany do tej strony.', location: 'Lokalizacja', locationHint: 'Wpisz miasto lub dzielnicę', transaction: 'Transakcja', sale: 'Sprzedaż', rent: 'Wynajem', price: 'Cena max', priceHint: 'np. 1 000 000 PLN', area: 'Powierzchnia', areaHint: 'min m²', search: 'Szukaj' },
    en: { types: { lokale: 'Commercial properties', dzialki: 'Land', mieszkania: 'Apartments' }, eyebrow: 'BROWSE PROPERTIES', title: 'Prefer to search on your own?', intro: 'You can also browse available properties. The property type is already selected for this page.', location: 'Location', locationHint: 'Enter a city or district', transaction: 'Transaction', sale: 'Sale', rent: 'Rent', price: 'Max price', priceHint: 'e.g. 1,000,000 PLN', area: 'Area', areaHint: 'min m²', search: 'Search' },
    uk: { types: { lokale: 'Комерційні приміщення', dzialki: 'Земельні ділянки', mieszkania: 'Квартири' }, eyebrow: 'ПЕРЕГЛЯНУТИ ПРОПОЗИЦІЇ', title: 'Хочете пошукати самостійно?', intro: 'Ви також можете переглянути доступні пропозиції. Тип нерухомості вже обрано для цієї сторінки.', location: 'Локація', locationHint: 'Введіть місто або район', transaction: 'Операція', sale: 'Продаж', rent: 'Оренда', price: 'Макс. ціна', priceHint: 'напр. 1 000 000 PLN', area: 'Площа', areaHint: 'від м²', search: 'Шукати' },
    ru: { types: { lokale: 'Коммерческие помещения', dzialki: 'Участки', mieszkania: 'Квартиры' }, eyebrow: 'СМОТРЕТЬ ПРЕДЛОЖЕНИЯ', title: 'Предпочитаете искать самостоятельно?', intro: 'Вы также можете посмотреть доступные предложения. Тип недвижимости уже выбран для этой страницы.', location: 'Расположение', locationHint: 'Введите город или район', transaction: 'Сделка', sale: 'Продажа', rent: 'Аренда', price: 'Макс. цена', priceHint: 'напр. 1 000 000 PLN', area: 'Площадь', areaHint: 'от м²', search: 'Найти' }
  }[lang];
  const label = copy.types[type];
  const start = () => {
    const main = document.querySelector('main');
    if (!main) return;
    const section = document.createElement('section');
    section.className = 'compact-search shell';
    section.setAttribute('aria-labelledby', 'compact-search-title');
    section.innerHTML = `<div class="compact-search-intro"><span>${copy.eyebrow}</span><h2 id="compact-search-title">${copy.title}</h2><p>${copy.intro}</p></div>
      <div class="compact-search-type">${label}</div>
      <form class="compact-search-form" action="${window.mazurLocalizedUrl("../wyniki-wyszukiwania/")}" method="get">
        <input type="hidden" name="type" value="${type}">
        <div class="compact-search-field compact-search-location">
          <label for="compact-location">${copy.location}</label>
          <input id="compact-location" name="location" placeholder="${copy.locationHint}" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="compact-location-options">
          <ul class="compact-search-locations" id="compact-location-options" role="listbox" hidden></ul>
        </div>
        <div class="compact-search-field compact-search-transaction"><span class="compact-search-label" id="compact-transaction-label">${copy.transaction}</span><input type="hidden" name="transaction" value="sprzedaz"><button id="compact-transaction" class="compact-search-transaction-trigger" type="button" aria-labelledby="compact-transaction-label compact-transaction-value" aria-haspopup="listbox" aria-controls="compact-transaction-options" aria-expanded="false"><span id="compact-transaction-value">${copy.sale}</span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg></button><div class="compact-search-transaction-options" id="compact-transaction-options" role="listbox" aria-labelledby="compact-transaction-label" hidden><button type="button" role="option" data-value="sprzedaz" aria-selected="true">${copy.sale}</button><button type="button" role="option" data-value="wynajem" aria-selected="false">${copy.rent}</button></div></div>
        <div class="compact-search-field"><label for="compact-price">${copy.price}</label><input id="compact-price" name="price" inputmode="numeric" placeholder="${copy.priceHint}"></div>
        <div class="compact-search-field"><label for="compact-area">${copy.area}</label><input id="compact-area" name="area" inputmode="decimal" placeholder="${copy.areaHint}"></div>
        <button class="compact-search-submit" type="submit"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m16.5 16.5 5 5"/></svg>${copy.search}</button>
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
    let locationRequested = false;
    const close = () => { locationRequested = false; list.hidden = true; input.setAttribute('aria-expanded', 'false'); };
    const render = () => {
      if (!locationRequested || document.activeElement !== input) return close();
      const query = normalize(input.value);
      list.replaceChildren();
      if (!locations.length) { list.hidden = true; input.setAttribute('aria-expanded', 'false'); return; }
      const matches = locations.filter(name => normalize(name).includes(query)).slice(0, 12);
      if (!matches.length) return close();
      matches.forEach(name => {
        const item = document.createElement('li');
        const button = document.createElement('button');
        button.type = 'button';
        button.setAttribute('role', 'option');
        button.textContent = name;
        button.addEventListener('click', () => { input.value = name; input.focus(); close(); });
        item.appendChild(button);
        list.appendChild(item);
      });
      list.hidden = false;
      input.setAttribute('aria-expanded', 'true');
    };
    const openLocations = () => { locationRequested = true; render(); };
    input.addEventListener('input', openLocations);
    input.addEventListener('pointerdown', openLocations);
    input.addEventListener('focus', openLocations);
    section.querySelector('.compact-search-location').addEventListener('focusout', event => {
      if (!event.currentTarget.contains(event.relatedTarget)) close();
    });
    input.addEventListener('keydown', event => {
      if (event.key === 'Escape') close();
      if (event.key === 'ArrowDown') {
        event.preventDefault();
        openLocations();
        list.querySelector('button')?.focus();
      }
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

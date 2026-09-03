(() => {
  const copy = {
    pl: { choose:'Wybierz nieruchomość', advisory:'Doradztwo', why:'Dlaczego my', developers:'Dla deweloperów', contact:'Kontakt', contactUs:'Skontaktuj się', nav:'Nawigacja', offers:'Oferty', projects:'Projekty', about:'Kim jesteśmy', investor:'Dla inwestora', rent:'Wynajem', sale:'Sprzedaż mieszkań', agency:'Pośrednictwo', commercial:'Nieruchomość komercyjna', apartments:'Mieszkania', houses:'Domy', land:'Działki', commercialPlural:'Lokale komercyjne', intro:'Agencja nieruchomości w Warszawie. Sprzedaż, wynajem i zarządzanie nieruchomościami dla klientów lokalnych i zagranicznych.', rights:'Wszelkie prawa zastrzeżone.', privacy:'Polityka prywatności', terms:'Regulamin' },
    en: { choose:'Choose a property', advisory:'Advisory', why:'Why us', developers:'For developers', contact:'Contact', contactUs:'Contact us', nav:'Navigation', offers:'Properties', projects:'Services', about:'About us', investor:'For investors', rent:'Rentals', sale:'Apartment sales', agency:'Brokerage', commercial:'Commercial property', apartments:'Apartments', houses:'Houses', land:'Land', commercialPlural:'Commercial properties', intro:'Real estate agency in Warsaw. Sales, rentals and property management for local and international clients.', rights:'All rights reserved.', privacy:'Privacy policy', terms:'Terms' },
    uk: { choose:'Обрати нерухомість', advisory:'Консультації', why:'Чому ми', developers:'Для забудовників', contact:'Контакти', contactUs:'Зв’язатися', nav:'Навігація', offers:'Пропозиції', projects:'Послуги', about:'Про нас', investor:'Для інвестора', rent:'Оренда', sale:'Продаж квартир', agency:'Посередництво', commercial:'Комерційна нерухомість', apartments:'Квартири', houses:'Будинки', land:'Земельні ділянки', commercialPlural:'Комерційні приміщення', intro:'Агенція нерухомості у Варшаві. Продаж, оренда та управління нерухомістю для місцевих та іноземних клієнтів.', rights:'Усі права захищені.', privacy:'Політика конфіденційності', terms:'Правила' },
    ru: { choose:'Выбрать недвижимость', advisory:'Консультации', why:'Почему мы', developers:'Для застройщиков', contact:'Контакты', contactUs:'Связаться', nav:'Навигация', offers:'Предложения', projects:'Услуги', about:'О нас', investor:'Для инвестора', rent:'Аренда', sale:'Продажа квартир', agency:'Посредничество', commercial:'Коммерческая недвижимость', apartments:'Квартиры', houses:'Дома', land:'Участки', commercialPlural:'Коммерческие помещения', intro:'Агентство недвижимости в Варшаве. Продажа, аренда и управление недвижимостью для местных и иностранных клиентов.', rights:'Все права защищены.', privacy:'Политика конфиденциальности', terms:'Правила' }
  };
  const langFromUrl = () => { const value = new URLSearchParams(location.search).get('lang'); return ['pl','en','uk','ru'].includes(value) ? value : 'pl'; };
  const withLang = (url, lang) => `${url}${url.includes('?') ? '&' : '?'}lang=${lang}`;
  window.applySiteChrome = () => {
    const header = document.querySelector('header.site-header');
    const footer = document.querySelector('footer.site-footer, footer.footer');
    if (!header && !footer) return;
    const lang = langFromUrl(), t = copy[lang], current = './';
    const preserved = new URLSearchParams(location.search); preserved.delete('lang');
    const currentBase = `${current}${preserved.toString() ? `?${preserved}` : ''}`;
    const home = `../index.html?lang=${lang}`;
    if (header) header.innerHTML = `<div class="header-bar shell"><a class="brand" href="${home}" aria-label="MazurEstate"><img src="../assets/images/hf_20260726_144142_ac3b284c-3072-4dfc-aeb0-5e7625c91362.png" alt="MazurEstate"></a><nav class="main-nav"><a href="${home}#categories">${t.choose}</a><a href="${home}#about">${t.advisory}</a><a href="${home}#why-us">${t.why}</a><a href="${home}#services">${t.developers}</a><a href="tel:+48503937749">${t.contact}</a></nav><div class="header-actions"><div class="chrome-language"><button type="button" aria-expanded="false">🌐 <span>${lang==='en'?'ENG':lang==='uk'?'UKR':lang.toUpperCase()}</span><b>⌄</b></button><div>${[['pl','PL'],['uk','UKR'],['en','ENG'],['ru','RU']].map(([code,label])=>`<a href="${withLang(currentBase,code)}">${label}</a>`).join('')}</div></div><a class="phone" href="tel:+48503937749">+48 503 937 749</a><a class="button chrome-primary" href="tel:+48503937749">${t.contactUs}</a></div></div>`;
    if (header) {
      const bar = header.querySelector('.header-bar');
      const menuButton = document.createElement('button');
      menuButton.className = 'chrome-menu-button';
      menuButton.type = 'button';
      menuButton.setAttribute('aria-label', 'Menu');
      menuButton.setAttribute('aria-expanded', 'false');
      menuButton.setAttribute('aria-controls', 'chrome-mobile-nav');
      menuButton.innerHTML = '<span></span><span></span><span></span>';
      const mobileNav = document.createElement('nav');
      mobileNav.className = 'chrome-mobile-nav shell';
      mobileNav.id = 'chrome-mobile-nav';
      mobileNav.innerHTML = `<a href="${home}#categories">${t.choose}</a><a href="${home}#about">${t.advisory}</a><a href="${home}#why-us">${t.why}</a><a href="${home}#services">${t.developers}</a><a href="tel:+48503937749">${t.contact}</a><a class="mobile-call" href="tel:+48503937749">+48 503 937 749</a>`;
      bar.appendChild(menuButton);
      header.appendChild(mobileNav);
      menuButton.addEventListener('click', () => {
        const open = mobileNav.classList.toggle('open');
        menuButton.classList.toggle('open', open);
        menuButton.setAttribute('aria-expanded', String(open));
      });
      mobileNav.addEventListener('click', () => {
        mobileNav.classList.remove('open');
        menuButton.classList.remove('open');
        menuButton.setAttribute('aria-expanded', 'false');
      });
      document.addEventListener('keydown', event => {
        if (event.key !== 'Escape' || !mobileNav.classList.contains('open')) return;
        mobileNav.classList.remove('open');
        menuButton.classList.remove('open');
        menuButton.setAttribute('aria-expanded', 'false');
        menuButton.focus();
      });
    }
    if (footer) { footer.className = 'site-footer'; footer.innerHTML = `<div class="shell footer-grid"><div><div class="footer-logo"><img src="../assets/images/hf_20260726_144142_ac3b284c-3072-4dfc-aeb0-5e7625c91362.png" alt="MazurEstate"></div><p>${t.intro}</p><div class="footer-social"><a href="https://www.instagram.com/mazurestate" target="_blank" rel="noopener" aria-label="Instagram">◎</a><a href="https://www.facebook.com/MazurEstate" target="_blank" rel="noopener" aria-label="Facebook">f</a><a href="https://www.threads.com/@mazurestate" target="_blank" rel="noopener" aria-label="Threads">@</a><a href="https://www.youtube.com/@mazurestate" target="_blank" rel="noopener" aria-label="YouTube">▶</a></div></div><div><h2>${t.nav}</h2><a href="${home}#categories">${t.choose}</a><a href="${home}#about">${t.advisory}</a><a href="../kim-jestesmy/?lang=${lang}">${t.about}</a><a href="${home}#services">${t.developers}</a><a href="tel:+48503937749">${t.contact}</a></div><div><h2>${t.offers}</h2><a href="../wyniki-wyszukiwania/?type=mieszkania&transaction=sprzedaz&lang=${lang}">${t.apartments}</a><a href="../wyniki-wyszukiwania/?type=domy&transaction=sprzedaz&lang=${lang}">${t.houses}</a><a href="../wyniki-wyszukiwania/?type=dzialki&transaction=sprzedaz&lang=${lang}">${t.land}</a><a href="../wyniki-wyszukiwania/?type=lokale&transaction=wynajem&lang=${lang}">${t.commercialPlural}</a></div><div><h2>${t.projects}</h2><a href="${home}#services">${t.investor}</a><a href="../wyniki-wyszukiwania/?transaction=wynajem&lang=${lang}">${t.rent}</a><a href="../wyniki-wyszukiwania/?transaction=sprzedaz&lang=${lang}">${t.sale}</a><a href="${home}#about">${t.agency}</a><a href="../wyniki-wyszukiwania/?type=lokale&lang=${lang}">${t.commercial}</a></div><div><h2>${t.contact}</h2><p>ul. Franciszka Klimczaka 10A, lok. 5<br>02-797 Warszawa</p><a href="tel:+48503937749">+48 503 937 749</a><a href="mailto:info@mazurestate.com">info@mazurestate.com</a></div></div><div class="shell copyright"><span>© 2026 MazurEstate. ${t.rights}</span><span><a href="../polityka-prywatnosci/">${t.privacy}</a> · <a href="../polityka-cookies/">Cookies</a> · <a href="../regulamin/">${t.terms}</a></span></div>`; }
    const legalBar=footer?.querySelector('.copyright span:last-child');
    if(legalBar){legalBar.className='legal-links';legalBar.innerHTML=`<a href="../polityka-prywatnosci/?lang=${lang}">${t.privacy}</a><a href="../polityka-cookies/?lang=${lang}">Cookies</a><a href="../regulamin/?lang=${lang}">${t.terms}</a>`;}
    const picker = header?.querySelector('.chrome-language');
    picker?.querySelector('button').addEventListener('click', e => { e.stopPropagation(); const open = picker.classList.toggle('open'); e.currentTarget.setAttribute('aria-expanded', String(open)); });
    document.addEventListener('click', event => {
      if (!picker || picker.contains(event.target)) return;
      picker.classList.remove('open');
      picker.querySelector('button')?.setAttribute('aria-expanded', 'false');
    });
  };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', window.applySiteChrome); else window.applySiteChrome();
  const translations=document.createElement('script');translations.src='../assets/js/page-translations.js';document.body.appendChild(translations);
})();

if (location.protocol === 'file:') {
  const makeLocalLinksExplicit=()=>document.querySelectorAll('a[href]').forEach(link=>{const href=link.getAttribute('href');if(!href||href.startsWith('#')||/^(?:https?:|mailto:|tel:)/.test(href))return;link.setAttribute('href',href.replace(/\/(\?|#|$)/,'/index.html$1'))});
  new MutationObserver(makeLocalLinksExplicit).observe(document.documentElement,{childList:true,subtree:true});
  makeLocalLinksExplicit();
}

const chromeResponsiveStyles=document.createElement('link');chromeResponsiveStyles.rel='stylesheet';chromeResponsiveStyles.href='../assets/css/responsive.css';document.head.appendChild(chromeResponsiveStyles);

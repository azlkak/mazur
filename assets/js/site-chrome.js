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
    const advisoryUrl = `../doradztwo/?lang=${lang}`;
    const developersUrl = `../dla-deweloperow/?lang=${lang}`;
    const desktopAdvisory = header?.querySelector('.main-nav a:nth-child(2)');
    if (desktopAdvisory) desktopAdvisory.href = advisoryUrl;
    const desktopDevelopers = header?.querySelector('.main-nav a:nth-child(4)');
    if (desktopDevelopers) desktopDevelopers.href = developersUrl;
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
      mobileNav.querySelector('a:nth-child(2)').href = advisoryUrl;
      mobileNav.querySelector('a:nth-child(4)').href = developersUrl;
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
    if (footer) {
      const socialIcons = [
        '<svg width="17" height="17" viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="3.5" y="3.5" width="17" height="17" rx="5" stroke="white" stroke-width="1.8"/><circle cx="12" cy="12" r="3.7" stroke="white" stroke-width="1.8"/><circle cx="17.4" cy="6.7" r="1.1" fill="white"/></svg>',
        '<svg width="17" height="17" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M14 8.2H16.5V4.4C16.1 4.35 14.65 4.2 12.95 4.2C9.55 4.2 7.2 6.25 7.2 10V13.2H3.5V17.45H7.2V24H11.75V17.45H15.55L16.15 13.2H11.75V10.4C11.75 9.15 12.1 8.2 14 8.2Z" fill="white" transform="translate(2 -2) scale(.92)"/></svg>',
        '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M17.7 10.3C17.45 6.55 15.35 4.5 12.05 4.5C8.35 4.5 6.2 7.2 6.2 11.95C6.2 16.7 8.25 19.5 12 19.5C15.05 19.5 17.15 17.85 17.15 15.5C17.15 13.45 15.5 12.25 13.2 12.25C10.95 12.25 9.55 13.4 9.55 15C9.55 16.3 10.6 17.15 12.05 17.15C14.45 17.15 17.85 15.25 17.85 11.5C17.85 10.9 17.8 10.55 17.7 10.3Z" stroke="white" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        '<svg width="19" height="19" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M21 8.2C20.8 6.75 20.1 5.9 18.65 5.7C16.8 5.45 14.85 5.35 12 5.35C9.15 5.35 7.2 5.45 5.35 5.7C3.9 5.9 3.2 6.75 3 8.2C2.85 9.25 2.8 10.25 2.8 12C2.8 13.75 2.85 14.75 3 15.8C3.2 17.25 3.9 18.1 5.35 18.3C7.2 18.55 9.15 18.65 12 18.65C14.85 18.65 16.8 18.55 18.65 18.3C20.1 18.1 20.8 17.25 21 15.8C21.15 14.75 21.2 13.75 21.2 12C21.2 10.25 21.15 9.25 21 8.2Z" stroke="white" stroke-width="1.6"/><path d="M10.2 9.1L15.2 12L10.2 14.9V9.1Z" fill="white"/></svg>'
      ];
      footer.querySelectorAll('.footer-social a').forEach((link, index) => {
        link.innerHTML = socialIcons[index] || '';
      });
    }
    const footerAdvisory = footer?.querySelector('.footer-grid>div:nth-child(2) a:nth-of-type(2)');
    if (footerAdvisory) footerAdvisory.href = advisoryUrl;
    const footerDevelopers = footer?.querySelector('.footer-grid>div:nth-child(2) a:nth-of-type(4)');
    if (footerDevelopers) footerDevelopers.href = developersUrl;
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

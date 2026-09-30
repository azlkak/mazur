(() => {
  const requestedLang = new URLSearchParams(location.search).get('lang') || document.documentElement.lang;
  const lang = ['pl', 'en', 'uk', 'ru'].includes(requestedLang) ? requestedLang : 'pl';
  document.body.classList.add('contact-options-enabled');
  const copy = {
    pl: { title: 'Jak chcesz się z nami skontaktować?', intro: 'Wybierz najwygodniejszy sposób. Chętnie pomożemy.', close: 'Zamknij okno kontaktu', call: 'Zadzwoń', write: 'Napisz do nas', email: 'E-mail', openMail: 'otwórz pocztę', copy: 'Skopiuj adres e-mail', form: 'Formularz', describe: 'Opisz, czego potrzebujesz', contact: 'Skontaktuj się', aria: 'Wybierz sposób kontaktu z MazurEstate', copied: 'Adres skopiowany.', copyFailed: 'Nie udało się skopiować adresu.' },
    en: { title: 'How would you like to contact us?', intro: 'Choose the way that works best for you. We are happy to help.', close: 'Close contact options', call: 'Call us', write: 'Message us', email: 'Email', openMail: 'open email app', copy: 'Copy email address', form: 'Contact form', describe: 'Tell us what you need', contact: 'Contact us', aria: 'Choose how to contact MazurEstate', copied: 'Email address copied.', copyFailed: 'Could not copy the email address.' },
    uk: { title: 'Як вам зручно зв’язатися з нами?', intro: 'Оберіть зручний спосіб. Ми радо допоможемо.', close: 'Закрити вікно контактів', call: 'Зателефонувати', write: 'Напишіть нам', email: 'Електронна пошта', openMail: 'відкрити пошту', copy: 'Скопіювати адресу', form: 'Контактна форма', describe: 'Опишіть, що вам потрібно', contact: 'Зв’язатися', aria: 'Оберіть спосіб зв’язку з MazurEstate', copied: 'Адресу скопійовано.', copyFailed: 'Не вдалося скопіювати адресу.' },
    ru: { title: 'Как вам удобно связаться с нами?', intro: 'Выберите удобный способ. Мы рады помочь.', close: 'Закрыть окно контактов', call: 'Позвонить', write: 'Напишите нам', email: 'Электронная почта', openMail: 'открыть почту', copy: 'Скопировать адрес', form: 'Контактная форма', describe: 'Расскажите, что вам нужно', contact: 'Связаться', aria: 'Выберите способ связи с MazurEstate', copied: 'Адрес скопирован.', copyFailed: 'Не удалось скопировать адрес.' }
  }[lang];

  const phone = '+48 503 937 749';
  const phoneHref = 'tel:+48503937749';
  const email = 'info@mazurestate.pl';
  const formOnPage = () => document.querySelector('#contact-form, .contact-card [data-contact-form]');
  const homeFormHref = formOnPage() ? '#contact-form' : `../index.html?lang=${lang}#contact-form`;

  const callIcon = '<svg viewBox="0 0 24 24" width="19" height="19" fill="none" aria-hidden="true"><path d="M7 3.5 4.8 5.7a2 2 0 0 0-.5 2c2.2 6 7 10.8 13 13a2 2 0 0 0 2-.5l2.2-2.2-4.4-4.4-2.3 2.3a16 16 0 0 1-6.7-6.7l2.3-2.3L7 3.5Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>';
  const arrowIcon = '<svg viewBox="0 0 24 24" width="17" height="17" fill="none" aria-hidden="true"><path d="m9 5 7 7-7 7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>';
  const icon = path => `<svg viewBox="0 0 24 24" width="21" height="21" fill="none" aria-hidden="true"><path d="${path}" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>`;

  const dialog = document.createElement('dialog');
  dialog.className = 'contact-options-dialog';
  dialog.setAttribute('aria-labelledby', 'contact-options-title');
  dialog.innerHTML = `<div class="contact-options-head"><div><span class="contact-options-eyebrow">MAZURESTATE</span><h2 id="contact-options-title">${copy.title}</h2><p>${copy.intro}</p></div><button class="contact-options-close" type="button" aria-label="${copy.close}">×</button></div><div class="contact-options-list">
    <a href="${phoneHref}" class="contact-options-item"><span class="contact-options-icon">${callIcon}</span><span><strong>${copy.call}</strong><small>${phone}</small></span>${arrowIcon}</a>
    <a href="https://wa.me/48503937749" target="_blank" rel="noopener noreferrer" class="contact-options-item"><span class="contact-options-icon">${icon('M20 11.5a8.5 8.5 0 0 1-12.7 7.4L3 20l1.2-4.2A8.5 8.5 0 1 1 20 11.5Z M8.5 8.5c1 3 2 4.5 5 6l2-.8 1.4 1.4c-.8 1.6-2.4 2-4.1 1.4-2.7-1-4.8-3.2-5.7-5.8-.6-1.7-.1-3.3 1.4-4.2l1.3 1.3-.8 1.7Z')}</span><span><strong>WhatsApp</strong><small>${copy.write}</small></span>${arrowIcon}</a>
    <a href="mailto:${email}" class="contact-options-item contact-options-email"><span class="contact-options-icon">${icon('M3 6.5h18v11H3v-11Z M3 7l9 7 9-7')}</span><span><strong>${copy.email}</strong><small>${email} · ${copy.openMail}</small></span>${arrowIcon}</a>
    <div class="contact-options-email-actions"><button type="button" data-copy-email>${copy.copy}</button><span data-email-status role="status" aria-live="polite"></span></div>
    <a href="${homeFormHref}" class="contact-options-item" data-contact-form-link><span class="contact-options-icon">${icon('M6 3.5h9l3 3v14H6v-17Z M15 3.5v3h3 M9 11h6 M9 15h6')}</span><span><strong>${copy.form}</strong><small>${copy.describe}</small></span>${arrowIcon}</a>
  </div>`;

  const showDialog = () => {
    if (dialog.open) return;
    dialog.showModal();
  };
  const prepareHeader = header => {
    if (!header) return;
    const actions = header.querySelector('#header-actions, .header-actions');
    const cta = actions?.querySelector('a.me-cta, a.button');
    if (!actions || !cta) return;
    actions.querySelectorAll('a[href^="tel:"]:not(.me-cta):not(.button)').forEach(link => link.remove());
    header.querySelector('#main-nav a[href^="tel:"], .main-nav a[href^="tel:"]')?.remove();
    if (!cta.classList.contains('contact-options-trigger')) {
      cta.removeAttribute('href');
      cta.setAttribute('role', 'button');
      cta.setAttribute('tabindex', '0');
      cta.setAttribute('aria-label', copy.aria);
      cta.setAttribute('aria-haspopup', 'dialog');
      cta.classList.add('contact-options-trigger');
      cta.textContent = copy.contact;
    }
  };
  const updateEmail = () => {
    document.querySelectorAll('a[href^="mailto:info@mazurestate.com"]').forEach(link => {
      link.href = link.href.replace('info@mazurestate.com', email);
      if (link.textContent.trim() === 'info@mazurestate.com') link.textContent = email;
    });
  };
  const contentCtas = [
    '.hero-actions a[href^="tel:"]',
    '.hero-copy > a[href^="tel:"]',
    '.service-card a[href^="tel:"]',
    '.customer-copy a[href^="tel:"]',
    '.advisory-cta a[href^="tel:"]',
    '.developer-cta a[href^="tel:"]',
    '.final-cta .cta-actions a[href^="tel:"]',
    '.cta.shell a[href^="tel:"]',
    '.contact-strip a[href^="tel:"]',
    '.contact-card > a[href^="tel:"]',
    '#services a[href^="tel:"]'
  ].join(',');
  const prepareContentCtas = () => {
    document.querySelectorAll('.final-cta .cta-actions').forEach(actions => {
      const call = actions.querySelector('a[href^="tel:"]');
      const mail = actions.querySelector('a[href^="mailto:"]');
      if (call && mail) mail.remove();
    });
    document.querySelectorAll(contentCtas).forEach(link => {
      if (link.classList.contains('contact-options-trigger')) return;
      if (/^\+48/.test(link.textContent.trim()) || link.closest('.contact-card')) link.textContent = copy.contact;
      link.classList.add('contact-options-trigger');
      link.setAttribute('aria-label', copy.aria);
      link.setAttribute('aria-haspopup', 'dialog');
    });
  };

  const init = () => {
    if (!dialog.isConnected) document.body.appendChild(dialog);
    prepareHeader(document.querySelector('#site-header, header.site-header'));
    prepareContentCtas();
    updateEmail();
  };
  document.addEventListener('click', event => {
    const mobileContact = event.target.closest('#mobile-menu-panel a[href^="tel:"], .chrome-mobile-nav a[href^="tel:"]');
    const trigger = event.target.closest('.contact-options-trigger') || (mobileContact && mobileContact.classList.contains('mobile-menu-contact-link') ? mobileContact : null);
    if (trigger) {
      event.preventDefault();
      showDialog();
      return;
    }
    if (event.target.closest('[data-copy-email]')) {
      const status = dialog.querySelector('[data-email-status]');
      navigator.clipboard.writeText(email).then(() => {
        status.textContent = copy.copied;
      }).catch(() => {
        status.textContent = copy.copyFailed;
      });
      return;
    }
    if (event.target.closest('.contact-options-close')) dialog.close();
    if (event.target === dialog) dialog.close();
    if (event.target.closest('[data-contact-form-link]') && formOnPage()) {
      event.preventDefault();
      dialog.close();
      formOnPage().scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  }, true);
  document.addEventListener('keydown', event => {
    if ((event.key === 'Enter' || event.key === ' ') && event.target.matches('.contact-options-trigger')) {
      event.preventDefault();
      showDialog();
    }
  });
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
  new MutationObserver(() => init()).observe(document.body, { childList: true, subtree: true });
})();

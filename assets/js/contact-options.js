(() => {
  const lang = new URLSearchParams(location.search).get('lang') || 'pl';
  if (lang !== 'pl') return;
  document.body.classList.add('contact-options-pl');

  const phone = '+48 503 937 749';
  const phoneHref = 'tel:+48503937749';
  const email = 'info@mazurestate.pl';
  const formOnPage = () => document.querySelector('#contact-form, .contact-card [data-contact-form]');
  const homeFormHref = location.pathname === '/' || location.pathname.endsWith('/index.html')
    ? '#contact-form'
    : '../index.html?lang=pl#contact-form';

  const callIcon = '<svg viewBox="0 0 24 24" width="19" height="19" fill="none" aria-hidden="true"><path d="M7 3.5 4.8 5.7a2 2 0 0 0-.5 2c2.2 6 7 10.8 13 13a2 2 0 0 0 2-.5l2.2-2.2-4.4-4.4-2.3 2.3a16 16 0 0 1-6.7-6.7l2.3-2.3L7 3.5Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>';
  const arrowIcon = '<svg viewBox="0 0 24 24" width="17" height="17" fill="none" aria-hidden="true"><path d="m9 5 7 7-7 7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>';
  const icon = path => `<svg viewBox="0 0 24 24" width="21" height="21" fill="none" aria-hidden="true"><path d="${path}" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>`;

  const dialog = document.createElement('dialog');
  dialog.className = 'contact-options-dialog';
  dialog.setAttribute('aria-labelledby', 'contact-options-title');
  dialog.innerHTML = `<div class="contact-options-head"><div><span class="contact-options-eyebrow">MAZURESTATE</span><h2 id="contact-options-title">Jak chcesz się z nami skontaktować?</h2><p>Wybierz najwygodniejszy sposób. Chętnie pomożemy.</p></div><button class="contact-options-close" type="button" aria-label="Zamknij okno kontaktu">×</button></div><div class="contact-options-list">
    <a href="${phoneHref}" class="contact-options-item"><span class="contact-options-icon">${callIcon}</span><span><strong>Zadzwoń</strong><small>${phone}</small></span>${arrowIcon}</a>
    <a href="https://wa.me/48503937749" target="_blank" rel="noopener noreferrer" class="contact-options-item"><span class="contact-options-icon">${icon('M20 11.5a8.5 8.5 0 0 1-12.7 7.4L3 20l1.2-4.2A8.5 8.5 0 1 1 20 11.5Z M8.5 8.5c1 3 2 4.5 5 6l2-.8 1.4 1.4c-.8 1.6-2.4 2-4.1 1.4-2.7-1-4.8-3.2-5.7-5.8-.6-1.7-.1-3.3 1.4-4.2l1.3 1.3-.8 1.7Z')}</span><span><strong>WhatsApp</strong><small>Napisz do nas</small></span>${arrowIcon}</a>
    <a href="mailto:${email}" class="contact-options-item contact-options-email"><span class="contact-options-icon">${icon('M3 6.5h18v11H3v-11Z M3 7l9 7 9-7')}</span><span><strong>E-mail</strong><small>${email} · otwórz pocztę</small></span>${arrowIcon}</a>
    <div class="contact-options-email-actions"><button type="button" data-copy-email>Skopiuj adres e-mail</button><span data-email-status role="status" aria-live="polite"></span></div>
    <a href="${homeFormHref}" class="contact-options-item" data-contact-form-link><span class="contact-options-icon">${icon('M6 3.5h9l3 3v14H6v-17Z M15 3.5v3h3 M9 11h6 M9 15h6')}</span><span><strong>Formularz</strong><small>Opisz, czego potrzebujesz</small></span>${arrowIcon}</a>
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
      cta.setAttribute('aria-label', 'Wybierz sposób kontaktu z MazurEstate');
      cta.setAttribute('aria-haspopup', 'dialog');
      cta.classList.add('contact-options-trigger');
      cta.textContent = 'Skontaktuj się';
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
      if (/^\+48/.test(link.textContent.trim()) || link.closest('.contact-card')) link.textContent = 'Skontaktuj się';
      link.classList.add('contact-options-trigger');
      link.setAttribute('aria-label', 'Wybierz sposób kontaktu z MazurEstate');
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
    const trigger = event.target.closest('.contact-options-trigger') || (mobileContact && ['Kontakt', 'Skontaktuj się'].includes(mobileContact.textContent.trim()) ? mobileContact : null);
    if (trigger) {
      event.preventDefault();
      showDialog();
      return;
    }
    if (event.target.closest('[data-copy-email]')) {
      const status = dialog.querySelector('[data-email-status]');
      navigator.clipboard.writeText(email).then(() => {
        status.textContent = 'Adres skopiowany.';
      }).catch(() => {
        status.textContent = 'Nie udało się skopiować adresu.';
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

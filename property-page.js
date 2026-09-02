const pages = {
  mieszkania: {
    eyebrow: 'Mieszkania',
    title: 'Mieszkania dopasowane do Twojego życia',
    lead: 'Pomagamy znaleźć mieszkanie do zakupu, najmu lub bezpiecznej inwestycji — w odpowiedniej lokalizacji, standardzie i budżecie.',
    image: 'uploads/category-apartments.webp',
    intro: 'Od pierwszej selekcji ofert po podpisanie umowy prowadzimy cały proces jasno i bez zbędnych komplikacji.',
    benefits: [
      ['Dopasowana selekcja', 'Wybieramy tylko mieszkania zgodne z Twoimi priorytetami i budżetem.'],
      ['Sprawdzenie oferty', 'Analizujemy cenę, stan prawny i realny potencjał nieruchomości.'],
      ['Negocjacje i formalności', 'Wspieramy Cię w rozmowach, dokumentach i bezpiecznej finalizacji.']
    ]
  },
  domy: {
    eyebrow: 'Domy',
    title: 'Dom, w którym naprawdę chcesz zamieszkać',
    lead: 'Łączymy potrzeby rodziny, lokalizację i budżet, aby znaleźć dom dający komfort dziś oraz dobrą perspektywę na przyszłość.',
    image: 'uploads/category-houses.webp',
    intro: 'Porównujemy nie tylko wygląd domu, ale także dojazd, otoczenie, koszty utrzymania i możliwości dalszego rozwoju.',
    benefits: [
      ['Analiza potrzeb', 'Ustalamy kluczowe kryteria: lokalizację, metraż, działkę i standard.'],
      ['Weryfikacja nieruchomości', 'Pomagamy sprawdzić dokumenty, stan budynku i ważne koszty.'],
      ['Bezpieczny zakup', 'Koordynujemy negocjacje oraz kolejne etapy aż do przekazania domu.']
    ]
  },
  dzialki: {
    eyebrow: 'Działki',
    title: 'Działka z właściwym potencjałem',
    lead: 'Pomagamy ocenić lokalizację, przeznaczenie i możliwości zabudowy, zanim podejmiesz decyzję inwestycyjną.',
    image: 'uploads/category-land.webp',
    intro: 'Dobra działka to nie tylko powierzchnia. Liczą się media, dojazd, plan miejscowy i realne możliwości wykorzystania terenu.',
    benefits: [
      ['Dobór lokalizacji', 'Szukamy terenów odpowiadających planowanej funkcji i budżetowi.'],
      ['Analiza potencjału', 'Zwracamy uwagę na dostęp, media oraz uwarunkowania planistyczne.'],
      ['Wsparcie transakcji', 'Pomagamy przejść od pierwszej analizy do bezpiecznej finalizacji.']
    ]
  },
  lokale: {
    eyebrow: 'Lokale komercyjne',
    title: 'Przestrzeń, która wspiera Twój biznes',
    lead: 'Znajdujemy lokale dopasowane do profilu działalności, lokalizacji, przepływu klientów i planów rozwoju firmy.',
    image: 'uploads/category-commercial.webp',
    intro: 'Patrzymy na lokal biznesowo: analizujemy funkcję, koszty, widoczność, warunki umowy i potencjał danej lokalizacji.',
    benefits: [
      ['Brief biznesowy', 'Poznajemy działalność, potrzeby operacyjne i oczekiwaną lokalizację.'],
      ['Porównanie możliwości', 'Oceniamy układ, koszty oraz potencjał dostępnych powierzchni.'],
      ['Warunki najmu lub zakupu', 'Pomagamy negocjować zapisy i bezpiecznie zamknąć transakcję.']
    ]
  }
};

const key = document.body.dataset.propertyType;
const page = pages[key] || pages.mieszkania;
document.title = `${page.eyebrow} | MazurEstate`;

document.body.innerHTML = `
  <header class="site-header">
    <div class="shell header-bar">
      <a href="MazurEstate%20Home.dc.html" aria-label="MazurEstate — strona główna"><img class="logo" src="uploads/hf_20260726_144142_ac3b284c-3072-4dfc-aeb0-5e7625c91362.png" alt="MazurEstate"></a>
      <nav class="nav" aria-label="Nawigacja główna">
        <a href="MazurEstate%20Home.dc.html#categories">Nieruchomości</a>
        <a href="MazurEstate%20Home.dc.html#why-us">Dlaczego my</a>
        <a href="MazurEstate%20Home.dc.html#faq">FAQ</a>
      </nav>
      <a class="button button-primary" href="tel:+48503937749">Skontaktuj się</a>
    </div>
  </header>

  <main>
    <section class="hero">
      <div class="shell hero-card">
        <img class="hero-image" src="${page.image}" alt="${page.eyebrow}">
        <a class="back-link" href="MazurEstate%20Home.dc.html#categories" aria-label="Wróć do kategorii">← Wróć do kategorii</a>
        <div class="hero-copy">
          <p class="eyebrow">${page.eyebrow}</p>
          <h1>${page.title}</h1>
          <p class="lead">${page.lead}</p>
          <a class="button button-light" href="tel:+48503937749">Zapytaj o aktualne oferty</a>
        </div>
      </div>
    </section>

    <section class="content">
      <div class="shell">
        <div class="section-head">
          <div><p class="eyebrow">Jak działamy</p><h2>Wsparcie od wyboru do finalizacji</h2></div>
          <p>${page.intro}</p>
        </div>
        <div class="benefits">
          ${page.benefits.map((item, index) => `<article class="benefit"><div class="benefit-number">0${index + 1}</div><h3>${item[0]}</h3><p>${item[1]}</p></article>`).join('')}
        </div>
        <div class="contact-strip">
          <div><h2>Porozmawiajmy o Twoich potrzebach</h2><p>Zadzwoń — przygotujemy dopasowane propozycje i omówimy kolejne kroki.</p></div>
          <a class="button button-light" href="tel:+48503937749">+48 503 937 749</a>
        </div>
      </div>
    </section>
  </main>

  <footer class="footer"><div class="shell footer-row"><span>© 2026 MazurEstate</span><span>Warszawa · info@mazurestate.com</span></div></footer>
`;

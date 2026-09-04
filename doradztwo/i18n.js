(() => {
  const lang = new URLSearchParams(location.search).get('lang') || 'pl';
  if (lang === 'pl') return;

  const translations = {
    en: {
      title: 'Real estate advisory | MazurEstate Warsaw',
      description: 'MazurEstate real estate advisory in Warsaw: medical and hospitality premises, land acquisition, investment apartments and commercial property analysis.',
      hero: ['REAL ESTATE ADVISORY', 'Good decisions begin with the right questions', 'We turn an idea into a clear plan. We analyse your needs, the market, a property’s potential and its risks, so choosing premises, land or an investment apartment is informed and well prepared.', 'Let’s talk', 'Explore our advisory services →'],
      stats: ['years of experience', 'clients served', 'business premises opened', 'apartments under management'],
      note: ['Experience and accountability', 'Experts on your side'],
      intro: ['FROM NEED TO DECISION', 'First we understand the goal. Then we find the solution.', 'Every property has a different purpose. A medical practice must suit its services and technical requirements. A restaurant needs the right layout, installations and conditions for food service. Land must match the planned project not only in price, but also in designation and restrictions.', 'That is why we do not begin by sending random listings. We define priorities, budget, timeline and non-negotiable conditions. We then create selection criteria, compare options and coordinate consultations with the specialists required for the project.'],
      processHead: ['HOW WE WORK', 'A transparent advisory process', 'You know what we check, why we check it and what supports each recommendation.'],
      process: [['Needs assessment', 'We define the objective, business or investment model, budget, location, timeline and essential conditions.'], ['Criteria and strategy', 'We build the property brief, set priorities and prepare a verification plan for the specific project.'], ['Analysis and selection', 'We compare the market, legal status, technical parameters, potential and costs, rejecting options that do not meet the brief.'], ['Decision and execution', 'We support negotiations, bring in the right experts and coordinate the process through to secure completion.']],
      servicesHead: ['ADVISORY AREAS', 'How can we help?', 'We combine market expertise with the coordination of analyses required for non-standard purchases, leases and investments.'],
      services: [
        ['SPECIALIST PREMISES', 'Premises for a medical or dental practice', 'We help define the requirements resulting from the planned services and find a space that can be functionally adapted. We analyse accessibility, layout, installations, circulation and the ability to consult the project with the appropriate specialists.', 'Learn more →'],
        ['HOSPITALITY', 'Premises for a restaurant, café or bar', 'We assess the property’s potential for the intended concept: permitted use, ventilation, back-of-house facilities, deliveries, installations, surroundings and fit-out options. We help reduce the risk of choosing a space that would be too costly or impossible to adapt.', 'Discuss the premises →'],
        ['INVESTMENT LAND', 'Buying land for development', 'We coordinate the review of local plans or development conditions, the land register, road access, utilities, boundaries, terrain and potential restrictions. We compare the findings with the investor’s objective and expected costs.', 'Assess the land’s potential →'],
        ['INVESTING', 'Buying an apartment to rent', 'We build a strategy from choosing the location and developer to the apartment type, size and standard. We analyse tenant demand, competition, costs, achievable rent, resale liquidity and the management model.', 'Build your rental strategy →'],
        ['COMMERCIAL PROPERTY', 'Office, showroom or service premises', 'We match the property to how the company works, serves customers and plans to grow. We compare location, accessibility, layout, fixed costs, lease terms and spatial flexibility.', 'Find space for your business →'],
        ['PURCHASE ANALYSIS', 'Property verification before a decision', 'We help organise due diligence: documents, market environment, condition and potential, additional costs and transaction risks. When needed, we coordinate cooperation with a lawyer, architect or valuer.', 'Consult a property →']
      ],
      expertise: ['EXPERT SUPPORT', 'You do not need to know every answer', 'Our role is to know which questions to ask, what to verify and when to involve another expert. This allows you to compare options based on facts rather than first impressions.', ['clearly defined criteria and priorities,', 'selection tailored to your objective,', 'coordination of legal and technical analyses,', 'support in negotiations and completion.']],
      cta: ['LET’S START WITH A CONVERSATION', 'Tell us about your plans', 'Purchase, lease, investment or business premises — together we will identify the right first step.'],
      alts: ['MazurEstate female founder', 'MazurEstate male founder', 'Modern dental practice', 'Modern restaurant and bar premises', 'Land assessed for development', 'Apartment prepared as a rental investment', 'Modern commercial space for a business', 'Property documents and floor plan under review']
    },
    uk: {
      title: 'Консультації з нерухомості | MazurEstate Варшава',
      description: 'Консультації MazurEstate у Варшаві: медичні та гастрономічні приміщення, купівля землі, інвестиційні квартири й аналіз комерційної нерухомості.',
      hero: ['КОНСУЛЬТАЦІЇ З НЕРУХОМОСТІ', 'Вдалі рішення починаються з правильних запитань', 'Допомагаємо перетворити ідею на конкретний план. Аналізуємо потреби, ринок, потенціал нерухомості та ризики, щоб вибір приміщення, землі або інвестиційної квартири був усвідомленим і добре підготовленим.', 'Поговорімо', 'Переглянути напрями консультацій →'],
      stats: ['років досвіду', 'клієнтів обслуговано', 'відкритих закладів', 'квартир в управлінні'],
      note: ['Досвід і відповідальність', 'Експерти на Вашому боці'],
      intro: ['ВІД ПОТРЕБИ ДО РІШЕННЯ', 'Спочатку розуміємо мету. Потім шукаємо рішення.', 'Кожна нерухомість має своє завдання. Кабінет повинен відповідати профілю діяльності й технічним вимогам. Ресторан потребує належного планування, комунікацій та умов для гастрономії. Земля має відповідати запланованому проєкту не лише за ціною, а й за призначенням та обмеженнями.', 'Тому ми не починаємо з випадкових пропозицій. Визначаємо пріоритети, бюджет, строки й обов’язкові умови. Потім формуємо критерії вибору, порівнюємо варіанти та координуємо консультації з потрібними фахівцями.'],
      processHead: ['ЯК МИ ПРАЦЮЄМО', 'Прозорий консультаційний процес', 'Ви знаєте, що ми перевіряємо, навіщо це робимо й на чому ґрунтуємо рекомендації.'],
      process: [['Визначення потреб', 'Уточнюємо мету, бізнес- або інвестиційну модель, бюджет, локацію, строки й обов’язкові умови.'], ['Критерії та стратегія', 'Створюємо профіль нерухомості, визначаємо пріоритети та план перевірки для конкретного проєкту.'], ['Аналіз і відбір', 'Порівнюємо ринок, правовий статус, технічні параметри, потенціал і витрати. Відхиляємо невідповідні варіанти.'], ['Рішення та реалізація', 'Підтримуємо в переговорах, залучаємо потрібних експертів і координуємо процес до безпечного завершення.']],
      servicesHead: ['НАПРЯМИ КОНСУЛЬТАЦІЙ', 'Чим можемо допомогти?', 'Поєднуємо знання ринку з координацією аналізів, необхідних для нестандартних покупок, оренди та інвестицій.'],
      services: [
        ['СПЕЦІАЛІЗОВАНІ ПРИМІЩЕННЯ', 'Приміщення для медичного чи стоматологічного кабінету', 'Допомагаємо визначити вимоги до майбутньої діяльності та знайти простір, який можна функціонально адаптувати. Аналізуємо доступність, планування, комунікації, рух усередині приміщення та можливість консультації проєкту з відповідними фахівцями.', 'Дізнатися більше →'],
        ['ГАСТРОНОМІЯ', 'Приміщення для ресторану, кафе або бару', 'Перевіряємо потенціал приміщення: призначення, вентиляцію, підсобні зони, доставку, комунікації, оточення та можливості облаштування. Допомагаємо уникнути простору, адаптація якого буде надто дорогою або неможливою.', 'Обговорити приміщення →'],
        ['ІНВЕСТИЦІЙНА ЗЕМЛЯ', 'Купівля землі під забудову', 'Координуємо аналіз місцевого плану або умов забудови, земельної книги, доступу до дороги, комунікацій, меж, рельєфу й обмежень. Зіставляємо результати з метою інвестора та очікуваними витратами.', 'Оцінити потенціал землі →'],
        ['ІНВЕСТУВАННЯ', 'Купівля квартири для оренди', 'Будуємо стратегію від вибору локації та забудовника до типу, площі й стандарту квартири. Аналізуємо попит, конкуренцію, витрати, можливу орендну плату, ліквідність і модель управління.', 'Створити стратегію оренди →'],
        ['КОМЕРЦІЙНА НЕРУХОМІСТЬ', 'Офіс, шоурум або сервісне приміщення', 'Добираємо нерухомість до способу роботи, обслуговування клієнтів і планів розвитку компанії. Порівнюємо локацію, доступність, планування, постійні витрати, умови оренди та гнучкість простору.', 'Знайти простір для бізнесу →'],
        ['АНАЛІЗ ПОКУПКИ', 'Перевірка нерухомості перед рішенням', 'Допомагаємо організувати due diligence: документи, ринкове оточення, стан і потенціал, додаткові витрати та ризики угоди. За потреби координуємо роботу юриста, архітектора або оцінювача.', 'Проконсультуватися →']
      ],
      expertise: ['ЕКСПЕРТНА ПІДТРИМКА', 'Вам не потрібно знати відповіді на всі запитання', 'Наша роль — знати, які запитання поставити, що перевірити й коли залучити додаткового експерта. Завдяки цьому Ви порівнюєте варіанти на основі фактів, а не першого враження.', ['чітко визначені критерії та пріоритети,', 'відбір відповідно до Вашої мети,', 'координація юридичних і технічних перевірок,', 'підтримка в переговорах і завершенні угоди.']],
      cta: ['ПОЧНІМО З РОЗМОВИ', 'Розкажіть нам про свої плани', 'Купівля, оренда, інвестиція або приміщення для бізнесу — разом визначимо правильний перший крок.'],
      alts: ['Засновниця MazurEstate', 'Засновник MazurEstate', 'Сучасний стоматологічний кабінет', 'Сучасний ресторан і бар', 'Земля для інвестиційного проєкту', 'Квартира для інвестиції в оренду', 'Сучасний комерційний простір', 'Аналіз документів і плану нерухомості']
    },
    ru: {
      title: 'Консультации по недвижимости | MazurEstate Варшава',
      description: 'Консультации MazurEstate в Варшаве: медицинские и гастрономические помещения, покупка земли, инвестиционные квартиры и анализ коммерческой недвижимости.',
      hero: ['КОНСУЛЬТАЦИИ ПО НЕДВИЖИМОСТИ', 'Удачные решения начинаются с правильных вопросов', 'Помогаем превратить идею в конкретный план. Анализируем потребности, рынок, потенциал недвижимости и риски, чтобы выбор помещения, земли или инвестиционной квартиры был осознанным и подготовленным.', 'Давайте поговорим', 'Посмотреть направления консультаций →'],
      stats: ['лет опыта', 'клиентов обслужено', 'открытых заведений', 'квартир в управлении'],
      note: ['Опыт и ответственность', 'Эксперты на вашей стороне'],
      intro: ['ОТ ПОТРЕБНОСТИ К РЕШЕНИЮ', 'Сначала понимаем цель. Затем ищем решение.', 'У каждой недвижимости своя задача. Кабинет должен соответствовать профилю деятельности и техническим требованиям. Ресторану нужны подходящая планировка, коммуникации и условия для гастрономии. Земля должна соответствовать проекту не только по цене, но и по назначению и ограничениям.', 'Поэтому мы не начинаем со случайных предложений. Определяем приоритеты, бюджет, сроки и обязательные условия. Затем формируем критерии выбора, сравниваем варианты и координируем консультации с необходимыми специалистами.'],
      processHead: ['КАК МЫ РАБОТАЕМ', 'Прозрачный консультационный процесс', 'Вы знаете, что мы проверяем, зачем это делаем и на чём основаны наши рекомендации.'],
      process: [['Определение потребностей', 'Уточняем цель, бизнес- или инвестиционную модель, бюджет, локацию, сроки и обязательные условия.'], ['Критерии и стратегия', 'Создаём профиль недвижимости, определяем приоритеты и план проверки для конкретного проекта.'], ['Анализ и отбор', 'Сравниваем рынок, правовой статус, технические параметры, потенциал и затраты. Исключаем неподходящие варианты.'], ['Решение и реализация', 'Поддерживаем в переговорах, привлекаем нужных экспертов и координируем процесс до безопасного завершения.']],
      servicesHead: ['НАПРАВЛЕНИЯ КОНСУЛЬТАЦИЙ', 'Чем мы можем помочь?', 'Объединяем знание рынка с координацией проверок, необходимых при нестандартных покупках, аренде и инвестициях.'],
      services: [
        ['СПЕЦИАЛИЗИРОВАННЫЕ ПОМЕЩЕНИЯ', 'Помещение для медицинского или стоматологического кабинета', 'Помогаем определить требования будущей деятельности и найти пространство, которое можно функционально адаптировать. Анализируем доступность, планировку, коммуникации и возможность консультации проекта с профильными специалистами.', 'Подробнее →'],
        ['ГАСТРОНОМИЯ', 'Помещение для ресторана, кафе или бара', 'Проверяем назначение, вентиляцию, подсобные зоны, доставку, коммуникации, окружение и возможности обустройства. Помогаем избежать помещения, адаптация которого окажется слишком дорогой или невозможной.', 'Обсудить помещение →'],
        ['ИНВЕСТИЦИОННАЯ ЗЕМЛЯ', 'Покупка земли под застройку', 'Координируем анализ местного плана или условий застройки, земельной книги, доступа к дороге, коммуникаций, границ, рельефа и ограничений. Сопоставляем результаты с целью инвестора и ожидаемыми затратами.', 'Оценить потенциал земли →'],
        ['ИНВЕСТИРОВАНИЕ', 'Покупка квартиры для аренды', 'Строим стратегию от выбора локации и застройщика до типа, площади и стандарта квартиры. Анализируем спрос, конкуренцию, расходы, возможную арендную плату, ликвидность и модель управления.', 'Создать стратегию аренды →'],
        ['КОММЕРЧЕСКАЯ НЕДВИЖИМОСТЬ', 'Офис, шоурум или сервисное помещение', 'Подбираем недвижимость под работу компании, обслуживание клиентов и планы развития. Сравниваем локацию, доступность, планировку, постоянные расходы, условия аренды и гибкость пространства.', 'Найти пространство для бизнеса →'],
        ['АНАЛИЗ ПОКУПКИ', 'Проверка недвижимости перед решением', 'Помогаем организовать due diligence: документы, рынок, состояние и потенциал объекта, дополнительные расходы и риски сделки. При необходимости координируем работу юриста, архитектора или оценщика.', 'Проконсультироваться →']
      ],
      expertise: ['ЭКСПЕРТНАЯ ПОДДЕРЖКА', 'Вам не нужно знать ответы на все вопросы', 'Наша роль — знать, какие вопросы задать, что проверить и когда привлечь дополнительного эксперта. Благодаря этому вы сравниваете варианты на основе фактов, а не первого впечатления.', ['чётко определённые критерии и приоритеты,', 'отбор в соответствии с вашей целью,', 'координация юридических и технических проверок,', 'поддержка в переговорах и завершении сделки.']],
      cta: ['НАЧНЁМ С РАЗГОВОРА', 'Расскажите нам о своих планах', 'Покупка, аренда, инвестиция или помещение для бизнеса — вместе определим правильный первый шаг.'],
      alts: ['Основательница MazurEstate', 'Основатель MazurEstate', 'Современный стоматологический кабинет', 'Современный ресторан и бар', 'Земля для инвестиционного проекта', 'Квартира для инвестиции в аренду', 'Современное коммерческое пространство', 'Анализ документов и плана недвижимости']
    }
  };

  const d = translations[lang];
  if (!d) return;
  const q = selector => document.querySelector(selector);
  const qa = selector => [...document.querySelectorAll(selector)];
  const text = (selector, value) => { const el = q(selector); if (el) el.textContent = value; };

  document.documentElement.lang = lang;
  document.title = d.title;
  q('meta[name="description"]')?.setAttribute('content', d.description);
  const hero = q('.advisory-hero');
  [hero?.querySelector('.eyebrow'), hero?.querySelector('h1'), hero?.querySelector('.hero-lead'), hero?.querySelector('.button'), hero?.querySelector('.text-link')].forEach((el, i) => { if (el) el.textContent = d.hero[i]; });
  qa('.trust-row span').forEach((el, i) => { const strong = el.querySelector('strong'); if (strong && d.stats[i]) el.replaceChildren(strong, document.createTextNode(` ${d.stats[i]}`)); });
  const note = qa('.expert-note span,.expert-note strong'); note.forEach((el, i) => el.textContent = d.note[i]);
  const intro = q('.intro'); [intro?.querySelector('.eyebrow'), intro?.querySelector('h2'), ...qa('.intro-copy p')].forEach((el, i) => { if (el) el.textContent = d.intro[i]; });
  const processHeading = q('.process-section .section-heading'); [processHeading?.querySelector('.eyebrow'), processHeading?.querySelector('h2'), processHeading?.querySelector('p:last-child')].forEach((el, i) => { if (el) el.textContent = d.processHead[i]; });
  qa('.process-grid article').forEach((card, i) => { card.querySelector('h3').textContent = d.process[i][0]; card.querySelector('p').textContent = d.process[i][1]; });
  const servicesHeading = q('.services-heading'); [servicesHeading?.querySelector('.eyebrow'), servicesHeading?.querySelector('h2'), servicesHeading?.querySelector(':scope > p')].forEach((el, i) => { if (el) el.textContent = d.servicesHead[i]; });
  qa('.service-card').forEach((card, i) => { const x = d.services[i]; card.querySelector('.service-label').textContent = x[0]; card.querySelector('h3').textContent = x[1]; card.querySelector(':scope > p:not(.service-label)').textContent = x[2]; card.querySelector('a').textContent = x[3]; });
  const medicalLink = q('.service-card:first-child a'); if (medicalLink) medicalLink.href = `../lokal-medyczny/?lang=${lang}`;
  const hospitalityLink = q('.service-card:nth-child(2) a'); if (hospitalityLink) hospitalityLink.href = `../lokal-gastronomiczny/?lang=${lang}`;
  const landLink = q('.service-card:nth-child(3) a'); if (landLink) landLink.href = `../grunt-pod-budowe/?lang=${lang}`;
  const rentalLink = q('.service-card:nth-child(4) a'); if (rentalLink) rentalLink.href = `../mieszkanie-pod-wynajem/?lang=${lang}`;
  const expertise = q('.expertise'); text('.expertise .eyebrow', d.expertise[0]); text('.expertise h2', d.expertise[1]); expertise.querySelector(':scope > div:last-child > p').textContent = d.expertise[2]; qa('.expertise li').forEach((el, i) => el.textContent = d.expertise[3][i]);
  [q('.advisory-cta .eyebrow'), q('.advisory-cta h2'), q('.advisory-cta p:not(.eyebrow)')].forEach((el, i) => { if (el) el.textContent = d.cta[i]; });
  qa('.expert-photo img,.service-media img').forEach((img, i) => { if (d.alts[i]) img.alt = d.alts[i]; });
})();

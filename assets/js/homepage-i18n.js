(function () {
  const params = new URLSearchParams(window.location.search);
  const requestedLanguage = params.has('lang')
    ? params.get('lang')
    : ((navigator.languages && navigator.languages[0]) || navigator.language || 'pl').toLowerCase().split('-')[0];
  const lang = ['pl', 'uk', 'en', 'ru'].includes(requestedLanguage) ? requestedLanguage : 'pl';
  if (!['uk', 'en', 'ru'].includes(lang)) return;

  const rows = [
    ['Wybierz nieruchomość', 'Обрати нерухомість', 'Choose a property', 'Выбрать недвижимость'],
    ['Doradztwo', 'Консультації', 'Advisory', 'Консультации'],
    ['Dlaczego my', 'Чому ми', 'Why us', 'Почему мы'],
    ['Dla deweloperów', 'Для забудовників', 'For developers', 'Для застройщиков'],
    ['Kontakt', 'Контакти', 'Contact', 'Контакты'],
    ['Skontaktuj się', 'Зв’язатися', 'Contact us', 'Связаться'],
    ['Menu', 'Меню', 'Menu', 'Меню'],
    ['Agencja nieruchomości', 'Агенція нерухомості', 'Real estate agency', 'Агентство недвижимости'],
    ['Znajdź miejsce, którego szukasz', 'Знайдіть місце, яке шукаєте', 'Find the place you are looking for', 'Найдите место, которое ищете'],
    ['Przeglądaj aktualne oferty sprzedaży i wynajmu oraz skorzystaj ze wsparcia doświadczonych doradców. Pomożemy Ci znaleźć przestrzeń do życia, rozwoju biznesu lub bezpiecznej inwestycji.', 'Переглядайте актуальні пропозиції продажу й оренди та скористайтеся підтримкою досвідчених консультантів. Ми допоможемо знайти простір для життя, розвитку бізнесу або надійної інвестиції.', 'Browse current properties for sale and rent with support from experienced advisers. We will help you find a place to live, grow your business or invest securely.', 'Просматривайте актуальные предложения продажи и аренды при поддержке опытных консультантов. Мы поможем найти пространство для жизни, развития бизнеса или надёжной инвестиции.'],
    ['Zobacz oferty', 'Переглянути пропозиції', 'View properties', 'Посмотреть предложения'],
    ['zadowolonych klientów', 'задоволених клієнтів', 'satisfied clients', 'довольных клиентов'],
    ['Mieszkania', 'Квартири', 'Apartments', 'Квартиры'],
    ['Domy', 'Будинки', 'Houses', 'Дома'],
    ['Działki', 'Земельні ділянки', 'Land', 'Участки'],
    ['Lokale', 'Комерційні приміщення', 'Commercial', 'Коммерческие помещения'],
    ['Lokalizacja', 'Локація', 'Location', 'Расположение'],
    ['Warszawa, dzielnica lub adres', 'Варшава, район або адреса', 'Warsaw, district or address', 'Варшава, район или адрес'],
    ['Wpisz miasto lub dzielnicę', 'Введіть місто або район', 'Enter a city or district', 'Введите город или район'],
    ['Pokaż lokalizacje', 'Показати локації', 'Show locations', 'Показать местоположения'],
    ['Brak pasujących lokalizacji', 'Немає відповідних локацій', 'No matching locations', 'Нет подходящих местоположений'],
    ['Transakcja', 'Операція', 'Transaction', 'Сделка'],
    ['Sprzedaż', 'Продаж', 'Sale', 'Продажа'],
    ['Wynajem', 'Оренда', 'Rent', 'Аренда'],
    ['Cena max', 'Макс. ціна', 'Max price', 'Макс. цена'],
    ['Powierzchnia', 'Площа', 'Area', 'Площадь'],
    ['Szukaj', 'Шукати', 'Search', 'Найти'],
    ['Wybierz typ nieruchomości', 'Оберіть тип нерухомості', 'Choose a property type', 'Выберите тип недвижимости'],
    ['Znajdź nieruchomość', 'Знайдіть нерухомість', 'Find a property', 'Найдите недвижимость'],
    ['dopasowaną do', 'відповідно до', 'tailored to', 'подходящую под'],
    ['Twoich potrzeb', 'Ваших потреб', 'your needs', 'ваши потребности'],
    ['Zobacz wybrane oferty', 'Переглянути вибрані пропозиції', 'View featured properties', 'Посмотреть избранные предложения'],
    ['Ostatnio dodane', 'Нещодавно додані', 'Recently added', 'Недавно добавленные'],
    ['Wybrane nieruchomości', 'Вибрана нерухомість', 'Featured properties', 'Избранная недвижимость'],
    ['Poprzednie ogłoszenia', 'Попередні оголошення', 'Previous listings', 'Предыдущие объявления'],
    ['Następne ogłoszenia', 'Наступні оголошення', 'Next listings', 'Следующие объявления'],
    ['Sprzedaż', 'Продаж', 'Sale', 'Продажа'],
    ['Wynajem', 'Оренда', 'Rent', 'Аренда'],
    ['pokoje', 'кімнати', 'rooms', 'комнаты'],
    ['pokoi', 'кімнат', 'rooms', 'комнат'],
    ['pomieszczenia', 'приміщення', 'spaces', 'помещения'],
    ['Działka', 'Ділянка', 'Land', 'Участок'],
    ['Nowoczesne mieszkania', 'Сучасні квартири', 'Modern apartments', 'Современные квартиры'],
    ['Komfortowe domy rodzinne', 'Комфортні сімейні будинки', 'Comfortable family homes', 'Комфортные семейные дома'],
    ['Grunty pod inwestycje', 'Земля для інвестицій', 'Land for investment', 'Земля для инвестиций'],
    ['Lokale komercyjne', 'Комерційні приміщення', 'Commercial properties', 'Коммерческие помещения'],
    ['Przestrzeń dla biznesu', 'Простір для бізнесу', 'Space for business', 'Пространство для бизнеса'],
    ['Sprawdź oferty', 'Переглянути пропозиції', 'View properties', 'Посмотреть предложения'],
    ['Dlaczego MazurEstate?', 'Чому MazurEstate?', 'Why MazurEstate?', 'Почему MazurEstate?'],
    ['Nieruchomości wymagają dobrych decyzji. Pomagamy podejmować je świadomie, bezpiecznie i bez zbędnych komplikacji.', 'Нерухомість потребує правильних рішень. Ми допомагаємо приймати їх усвідомлено, безпечно й без зайвих складнощів.', 'Property decisions matter. We help you make them confidently, safely and without unnecessary complications.', 'Недвижимость требует взвешенных решений. Мы помогаем принимать их осознанно, безопасно и без лишних сложностей.'],
    ['Indywidualne podejście', 'Індивідуальний підхід', 'Personal approach', 'Индивидуальный подход'],
    ['Nie pracujemy według jednego schematu. Najpierw poznajemy Twoje potrzeby, plany i oczekiwania, a następnie dopasowujemy sposób działania do konkretnej sytuacji.', 'Ми не працюємо за одним шаблоном. Спочатку дізнаємося про Ваші потреби, плани й очікування, а потім підбираємо спосіб роботи для конкретної ситуації.', 'We do not use a one-size-fits-all process. We first understand your needs, plans and expectations, then tailor our approach to your situation.', 'Мы не работаем по одному шаблону. Сначала узнаём ваши потребности, планы и ожидания, а затем подбираем подход к конкретной ситуации.'],
    ['Wsparcie na każdym etapie', 'Підтримка на кожному етапі', 'Support at every stage', 'Поддержка на каждом этапе'],
    ['Znajomość rynku', 'Знання ринку', 'Market expertise', 'Знание рынка'],
    ['Jasna komunikacja', 'Зрозуміла комунікація', 'Clear communication', 'Понятная коммуникация'],
    ['Obsługa w wielu językach', 'Обслуговування багатьма мовами', 'Multilingual service', 'Обслуживание на нескольких языках'],
    ['Porozmawiajmy o Twojej nieruchomości', 'Поговорімо про Вашу нерухомість', 'Let’s talk about your property', 'Давайте обсудим вашу недвижимость'],
    ['Niezależnie od tego, czy chcesz kupić, sprzedać, wynająć czy zainwestować — zacznijmy od rozmowy.', 'Незалежно від того, чи хочете Ви купити, продати, орендувати або інвестувати — почнімо з розмови.', 'Whether you want to buy, sell, rent or invest, let’s start with a conversation.', 'Хотите купить, продать, арендовать или инвестировать — давайте начнём с разговора.'],
    ['Skontaktuj się z nami', 'Зв’язатися з нами', 'Contact us', 'Связаться с нами'],
    ['Masz pytania?', 'Є запитання?', 'Questions?', 'Есть вопросы?'],
    ['Najczęściej zadawane pytania', 'Найчастіші запитання', 'Frequently asked questions', 'Часто задаваемые вопросы'],
    ['Jaka jest prowizja za pośrednictwo?', 'Яка комісія за посередництво?', 'What is the agency fee?', 'Какова комиссия агентства?'],
    ['Czy pomagacie klientom spoza Polski?', 'Чи допомагаєте клієнтам з-за кордону?', 'Do you help clients from outside Poland?', 'Вы помогаете клиентам из других стран?'],
    ['Ile trwa sprzedaż lub wynajem nieruchomości?', 'Скільки триває продаж або оренда?', 'How long does a sale or rental take?', 'Сколько длится продажа или аренда?'],
    ['Czy zajmujecie się zarządzaniem najmem?', 'Чи займаєтеся Ви управлінням орендою?', 'Do you provide rental management?', 'Вы занимаетесь управлением арендой?'],
    ['Jak wygląda pierwszy krok współpracy?', 'Який перший крок співпраці?', 'What is the first step?', 'Как выглядит первый шаг сотрудничества?'],
    ['Opinie klientów', 'Відгуки клієнтів', 'Client reviews', 'Отзывы клиентов'],
    ['Co nasi klienci mówią o nas', 'Що про нас говорять клієнти', 'What our clients say', 'Что говорят о нас клиенты'],
    ['Więcej opinii w Google Maps', 'Більше відгуків у Google Maps', 'More reviews on Google Maps', 'Больше отзывов в Google Maps'],
    ['Nawigacja', 'Навігація', 'Navigation', 'Навигация'],
    ['Oferty', 'Пропозиції', 'Properties', 'Предложения'],
    ['Projekty', 'Проєкти', 'Projects', 'Проекты'],
    ['Wszelkie prawa zastrzeżone.', 'Усі права захищені.', 'All rights reserved.', 'Все права защищены.'],
    ['Polityka prywatności', 'Політика конфіденційності', 'Privacy policy', 'Политика конфиденциальности'],
    ['Cookies', 'Cookies', 'Cookies', 'Cookies'],
    ['Regulamin', 'Умови використання', 'Terms', 'Правила'],
    ['Towarzyszymy Ci od pierwszej rozmowy, przez wybór lub przygotowanie nieruchomości, prezentacje i negocjacje, aż do finalizacji transakcji.', 'Ми супроводжуємо Вас від першої розмови, вибору або підготовки нерухомості, показів і переговорів до завершення угоди.', 'We support you from the first conversation through property selection or preparation, viewings and negotiations, all the way to completion.', 'Мы сопровождаем вас от первого разговора, выбора или подготовки объекта, показов и переговоров до завершения сделки.'],
    ['Analizujemy aktualne oferty, ceny i potencjał nieruchomości, pomagając podejmować decyzje oparte na realnych danych i sytuacji rynkowej.', 'Ми аналізуємо актуальні пропозиції, ціни та потенціал нерухомості, допомагаючи приймати рішення на основі реальних даних і ринкової ситуації.', 'We analyse current listings, prices and property potential, helping you make decisions based on real data and market conditions.', 'Мы анализируем актуальные предложения, цены и потенциал недвижимости, помогая принимать решения на основе реальных данных и рыночной ситуации.'],
    ['Dbamy o przejrzysty przebieg współpracy. Informujemy o kolejnych krokach, odpowiadamy na pytania i pozostajemy w stałym kontakcie przez cały proces.', 'Ми забезпечуємо прозору співпрацю: пояснюємо наступні кроки, відповідаємо на запитання та залишаємося на зв’язку протягом усього процесу.', 'We keep the process transparent, explain every next step, answer questions and stay in touch throughout.', 'Мы обеспечиваем прозрачное сотрудничество: объясняем следующие шаги, отвечаем на вопросы и остаёмся на связи в течение всего процесса.'],
    ['Wspieramy zarówno klientów z Polski, jak i osoby z zagranicy, zapewniając sprawną komunikację oraz pomoc w zrozumieniu procesu zakupu, sprzedaży lub najmu.', 'Ми працюємо з клієнтами з Польщі та з-за кордону, забезпечуючи зрозумілу комунікацію й допомогу в процесі купівлі, продажу або оренди.', 'We assist clients from Poland and abroad, ensuring clear communication and support in understanding the purchase, sale or rental process.', 'Мы помогаем клиентам из Польши и других стран, обеспечивая понятную коммуникацию и поддержку в процессе покупки, продажи или аренды.'],
    ['Nasza prowizja jest jedną z najniższych na rynku warszawskim. Dokładną stawkę ustalamy indywidualnie w zależności od rodzaju transakcji i zakresu usług — skontaktuj się z nami po szczegóły.', 'Наша комісія — одна з найнижчих на варшавському ринку. Точну ставку визначаємо індивідуально залежно від типу угоди та обсягу послуг — зверніться до нас за деталями.', 'Our fee is among the most competitive in the Warsaw market. The exact rate is agreed individually based on the transaction and scope of services—contact us for details.', 'Наша комиссия — одна из самых конкурентных на рынке Варшавы. Точную ставку определяем индивидуально в зависимости от сделки и объёма услуг — свяжитесь с нами для деталей.'],
    ['Tak, obsługujemy klientów w języku polskim, ukraińskim, angielskim i rosyjskim. Prowadzimy przez cały proces krok po kroku, tłumacząc formalności i lokalne realia rynku.', 'Так, ми обслуговуємо клієнтів польською, українською, англійською та російською мовами. Покроково супроводжуємо весь процес, пояснюючи формальності та місцеві реалії ринку.', 'Yes. We serve clients in Polish, Ukrainian, English and Russian, guiding them step by step and explaining formalities and local market realities.', 'Да. Мы обслуживаем клиентов на польском, украинском, английском и русском языках, пошагово объясняя формальности и особенности местного рынка.'],
    ['Czas zależy od lokalizacji, ceny i stanu nieruchomości. Dzięki bazie zweryfikowanych klientów i aktywnemu marketingowi większość ofert znajduje nabywcę lub najemcę w ciągu kilku tygodni.', 'Термін залежить від локації, ціни та стану нерухомості. Завдяки базі перевірених клієнтів і активному маркетингу більшість пропозицій знаходить покупця або орендаря протягом кількох тижнів.', 'Timing depends on location, price and condition. Thanks to our verified client base and active marketing, most properties find a buyer or tenant within a few weeks.', 'Срок зависит от расположения, цены и состояния объекта. Благодаря базе проверенных клиентов и активному маркетингу большинство объектов находят покупателя или арендатора за несколько недель.'],
    ['Tak, oferujemy pełne zarządzanie najmem — od znalezienia najemcy, przez formalności, po bieżącą obsługę nieruchomości w trakcie trwania umowy.', 'Так, ми пропонуємо повне управління орендою — від пошуку орендаря й оформлення документів до поточного обслуговування нерухомості.', 'Yes. We offer full rental management—from finding a tenant and handling paperwork to ongoing property support during the lease.', 'Да. Мы предлагаем полное управление арендой — от поиска арендатора и оформления документов до текущего обслуживания объекта.'],
    ['Wystarczy się z nami skontaktować telefonicznie, mailowo lub przez formularz na stronie. Umówimy się na rozmowę, poznamy Twoje potrzeby i zaproponujemy dalsze kroki.', 'Зв’яжіться з нами телефоном, електронною поштою або через форму на сайті. Ми домовимося про розмову, з’ясуємо Ваші потреби та запропонуємо наступні кроки.', 'Simply contact us by phone, email or through the website form. We will arrange a conversation, understand your needs and suggest the next steps.', 'Свяжитесь с нами по телефону, электронной почте или через форму на сайте. Мы договоримся о разговоре, уточним ваши потребности и предложим следующие шаги.'],
    ['Agencja nieruchomości w Warszawie. Sprzedaż, wynajem i zarządzanie nieruchomościami dla klientów lokalnych i zagranicznych.', 'Агенція нерухомості у Варшаві. Продаж, оренда та управління нерухомістю для місцевих та іноземних клієнтів.', 'A Warsaw real estate agency providing sales, rentals and property management for local and international clients.', 'Агентство недвижимости в Варшаве. Продажа, аренда и управление недвижимостью для местных и иностранных клиентов.'],
    ['Kim jesteśmy', 'Хто ми', 'About us', 'О нас'],
    ['Dla inwestora', 'Для інвестора', 'For investors', 'Для инвесторов'],
    ['Sprzedaż mieszkań', 'Продаж квартир', 'Apartment sales', 'Продажа квартир'],
    ['Pośrednictwo', 'Посередництво', 'Brokerage', 'Посредничество'],
    ['Nieruchomość komercyjna', 'Комерційна нерухомість', 'Commercial real estate', 'Коммерческая недвижимость'],
    ['Biura', 'Офіси', 'Offices', 'Офисы'],
    ['Zadzwoń do MazurEstate: +48 503 937 749', 'Зателефонувати до MazurEstate: +48 503 937 749', 'Call MazurEstate: +48 503 937 749', 'Позвонить в MazurEstate: +48 503 937 749'],
    ['Profesjonalne podejście od pierwszego kontaktu. Mieszkanie sprzedaliśmy szybciej niż zakładaliśmy, a cała formalna strona przebiegła bez stresu.', 'Професійний підхід із першого контакту. Ми продали квартиру швидше, ніж очікували, а всі формальності пройшли без стресу.', 'Professional from the first contact. We sold the apartment faster than expected, and all the paperwork was stress-free.', 'Профессиональный подход с первого контакта. Мы продали квартиру быстрее, чем ожидали, а все формальности прошли без стресса.'],
    ['Bardzo doceniam obsługę w moim języku. Agencja pomogła mi zrozumieć każdy etap wynajmu i znaleźć mieszkanie, które naprawdę mi odpowiada.', 'Я дуже ціную обслуговування моєю мовою. Агенція допомогла зрозуміти кожен етап оренди й знайти квартиру, яка мені справді підходить.', 'I truly appreciated service in my own language. The agency explained every stage of the rental and found an apartment that really suited me.', 'Я очень ценю обслуживание на моём языке. Агентство помогло понять каждый этап аренды и найти квартиру, которая действительно мне подходит.'],
    ['Znajomość lokalnego rynku widać na każdym kroku. Doradcy jasno tłumaczyli każdą decyzję i negocjowali w naszym imieniu.', 'Знання місцевого ринку відчувається на кожному кроці. Консультанти зрозуміло пояснювали рішення та вели переговори від нашого імені.', 'Their local market expertise shows at every step. The advisers explained each decision clearly and negotiated on our behalf.', 'Знание местного рынка заметно на каждом этапе. Консультанты понятно объясняли решения и вели переговоры от нашего имени.'],
    ['Szybka i sprawna obsługa. Znaleźli najemcę w niecałe dwa tygodnie i zajęli się wszystkimi formalnościami umowy.', 'Швидке та ефективне обслуговування. Орендаря знайшли менш ніж за два тижні й взяли на себе всі формальності.', 'Fast and efficient service. They found a tenant in under two weeks and handled all the contract formalities.', 'Быстрое и эффективное обслуживание. Арендатора нашли менее чем за две недели и взяли на себя все формальности.'],
    ['Cały proces zakupu tłumaczony krok po kroku w moim języku. Czułem się bezpiecznie na każdym etapie transakcji.', 'Увесь процес купівлі пояснювали крок за кроком моєю мовою. Я почувався впевнено на кожному етапі угоди.', 'The entire purchase process was explained step by step in my language. I felt secure throughout the transaction.', 'Весь процесс покупки объясняли пошагово на моём языке. Я чувствовал себя уверенно на каждом этапе сделки.'],
    ['Rzetelna wycena i skuteczny marketing oferty. Dom sprzedał się w bardzo dobrej cenie i bez zbędnych opóźnień.', 'Надійна оцінка та ефективний маркетинг. Будинок продали за дуже доброю ціною й без зайвих затримок.', 'A reliable valuation and effective marketing. The house sold at a very good price without unnecessary delays.', 'Надёжная оценка и эффективный маркетинг. Дом продали по очень хорошей цене и без лишних задержек.'],
    ['Doceniam obsługę w moim ojczystym języku i jasne wyjaśnienie każdego dokumentu. Polecam osobom spoza Polski.', 'Ціную обслуговування рідною мовою та зрозуміле пояснення кожного документа. Рекомендую клієнтам з-за меж Польщі.', 'I appreciated service in my native language and clear explanations of every document. Recommended for clients from outside Poland.', 'Ценю обслуживание на родном языке и понятное объяснение каждого документа. Рекомендую клиентам из других стран.'],
    ['Doradcy dobrze znają rynek komercyjny w Warszawie. Pomogli wynegocjować korzystne warunki najmu lokalu.', 'Консультанти добре знають комерційний ринок Варшави. Вони допомогли домовитися про вигідні умови оренди приміщення.', 'The advisers know Warsaw’s commercial market well. They helped negotiate favourable lease terms.', 'Консультанты хорошо знают коммерческий рынок Варшавы. Они помогли согласовать выгодные условия аренды помещения.'],
    ['Sprzedaż mieszkania, Wola', 'Продаж квартири, Воля', 'Apartment sale, Wola', 'Продажа квартиры, Воля'],
    ['Najem, klientka z Ukrainy', 'Оренда, клієнтка з України', 'Rental, client from Ukraine', 'Аренда, клиентка из Украины'],
    ['Zakup domu, Ząbki', 'Купівля будинку, Зомбки', 'House purchase, Ząbki', 'Покупка дома, Зомбки'],
    ['Wynajem, Wilanów', 'Оренда, Вілянув', 'Rental, Wilanów', 'Аренда, Вилянув'],
    ['Zakup mieszkania, klient z Ukrainy', 'Купівля квартири, клієнт з України', 'Apartment purchase, client from Ukraine', 'Покупка квартиры, клиент из Украины'],
    ['Sprzedaż domu, Wiązowna', 'Продаж будинку, Вьонзовна', 'House sale, Wiązowna', 'Продажа дома, Вёнзовна'],
    ['Najem, klient z Rosji', 'Оренда, клієнт із Росії', 'Rental, client from Russia', 'Аренда, клиент из России'],
    ['Nieruchomość komercyjna, Mokotów', 'Комерційна нерухомість, Мокотув', 'Commercial property, Mokotów', 'Коммерческая недвижимость, Мокотув']
  ];

  const index = { uk: 1, en: 2, ru: 3 }[lang];
  const dictionary = new Map(rows.map(row => [row[0], row[index]]));
  const entries = [...dictionary.entries()].sort((a, b) => b[0].length - a[0].length);
  const normalize = value => value.replace(/\s+/g, ' ').trim();

  function translatedValue(value) {
    const key = normalize(value || '');
    if (dictionary.has(key)) return value.replace(key, dictionary.get(key));
    let result = value;
    entries.forEach(([source, target]) => { if (result.includes(source)) result = result.replaceAll(source, target); });
    return result;
  }

  function translate(root) {
    const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
    const nodes = [];
    while (walker.nextNode()) nodes.push(walker.currentNode);
    nodes.forEach(node => {
      const translated = translatedValue(node.nodeValue || '');
      if (translated !== node.nodeValue) node.nodeValue = translated;
    });
    root.querySelectorAll?.('input[placeholder]').forEach(input => {
      const key = normalize(input.placeholder);
      if (dictionary.has(key)) input.placeholder = dictionary.get(key);
    });
    root.querySelectorAll?.('[aria-label]').forEach(element => {
      const key = normalize(element.getAttribute('aria-label') || '');
      if (dictionary.has(key)) element.setAttribute('aria-label', dictionary.get(key));
    });
    root.querySelectorAll?.('a[href$=".html"]').forEach(link => {
      if (!link.href.includes('index.html')) link.href += `${link.href.includes('?') ? '&' : '?'}lang=${lang}`;
    });
    root.querySelectorAll?.('a[href^="dla-deweloperow/"]').forEach(link => {
      link.href = `dla-deweloperow/?lang=${lang}`;
    });
  }

  document.documentElement.lang = lang === 'uk' ? 'uk' : lang;
  document.title = `${lang === 'uk' ? 'Нерухомість' : lang === 'en' ? 'Real estate' : 'Недвижимость'} | MazurEstate`;
  translate(document);
  const observer = new MutationObserver(records => records.forEach(record => {
    if (record.type === 'characterData') {
      const translated = translatedValue(record.target.nodeValue || '');
      if (translated !== record.target.nodeValue) record.target.nodeValue = translated;
    }
    record.addedNodes.forEach(node => {
      if (node.nodeType === 1) translate(node);
      else if (node.nodeType === 3) {
        const translated = translatedValue(node.nodeValue || '');
        if (translated !== node.nodeValue) node.nodeValue = translated;
      }
    });
  }));
  observer.observe(document.body, { childList: true, characterData: true, subtree: true });
  setTimeout(() => translate(document), 1500);
  setTimeout(() => translate(document), 3000);
})();

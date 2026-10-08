const MLS_DETAIL_API='https://api.mazurestate.pl/api/mls-offer.php';

// Resolve from this shared script, including language-prefixed and project URLs.
const descriptionAssets=new URL('../assets/',document.currentScript?.src||document.baseURI);
const descriptionRenderer=import(new URL('js/offer-description.js?v=20261006-offer-translation',descriptionAssets).href).catch(()=>null);

const text=(selector,value)=>{const element=document.querySelector(selector);if(element)element.textContent=value};
const categoryFor=value=>{const name=String(value||'').toLocaleLowerCase('pl');if(name.includes('mieszkan'))return 'mieszkania';if(name.includes('dom'))return 'domy';if(name.includes('dział')||name.includes('grunt'))return 'dzialki';if(/lokal|komerc|biuro|magazyn|hala|obiekt/.test(name))return 'lokale';return 'mieszkania'};
const offerUi={
  pl:{home:'Strona główna',categories:{mieszkania:'Mieszkania',domy:'Domy',dzialki:'Działki',lokale:'Lokale komercyjne'},rent:'na wynajem',sale:'na sprzedaż',monthly:'miesięcznie',priceOnRequest:'Cena na zapytanie',meta:'Cena i szczegóły oferty MazurEstate.',photo:'zdjęcie',allPhotos:'Zobacz wszystkie zdjęcia',labels:['Powierzchnia','Liczba pokoi','Powierzchnia działki','Piętro','Typ nieruchomości','Rok budowy','Numer oferty'],room:['pokój','pokoje','pokoi'],description:'O NIERUCHOMOŚCI',descriptionEmpty:'Skontaktuj się z nami, aby poznać szczegóły tej nieruchomości.',features:'UDOGODNIENIA',highlights:'Najważniejsze atuty',featureEmpty:'Szczegóły dostępne u doradcy',enquiry:'ZAPYTAJ O OFERTĘ',viewing:'Umów prezentację',enquiryIntro:'Zostaw dane kontaktowe. Numer tej oferty dołączymy automatycznie do wiadomości.',orCall:'Lub zadzwoń: +48 503 937 749',reviews:'OPINIE KLIENTÓW',reviewsTitle:'Co nasi klienci mówią o nas',moreReviews:'Więcej opinii w Google Maps',previousReview:'Poprzednia opinia',nextReview:'Następna opinia',unavailable:'Oferta niedostępna',unavailableHeading:'Nie udało się wyświetlić oferty',missingId:'Brakuje numeru oferty w adresie strony.',tryAgain:'Spróbuj ponownie za chwilę.',typeNames:{'Mieszkanie':'Mieszkanie','Apartament':'Apartament','Dom':'Dom','Dom wolnostojący':'Dom wolnostojący','Działka':'Działka','Lokal użytkowy':'Lokal użytkowy','Lokal komercyjny':'Lokal komercyjny','Biuro':'Biuro','Magazyn':'Magazyn','Hala':'Hala'}},
  en:{home:'Home',categories:{mieszkania:'Apartments',domy:'Houses',dzialki:'Land',lokale:'Commercial properties'},rent:'for rent',sale:'for sale',monthly:'per month',priceOnRequest:'Price on request',meta:'Price and property details from MazurEstate.',photo:'photo',allPhotos:'View all photos',labels:['Area','Rooms','Plot area','Floor','Property type','Year built','Property number'],room:['room','rooms','rooms'],description:'ABOUT THE PROPERTY',descriptionEmpty:'Contact us for details about this property.',features:'FEATURES',highlights:'Key features',featureEmpty:'Details available from an adviser',enquiry:'ASK ABOUT THIS PROPERTY',viewing:'Book a viewing',enquiryIntro:'Leave your contact details. We will automatically include the property number in your message.',orCall:'Or call: +48 503 937 749',reviews:'CLIENT REVIEWS',reviewsTitle:'What our clients say about us',moreReviews:'More reviews on Google Maps',previousReview:'Previous review',nextReview:'Next review',unavailable:'Property unavailable',unavailableHeading:'This property could not be displayed',missingId:'The property number is missing from the page address.',tryAgain:'Please try again shortly.',typeNames:{'Mieszkanie':'Apartment','Apartament':'Apartment','Dom':'House','Dom wolnostojący':'Detached house','Działka':'Plot','Lokal użytkowy':'Commercial unit','Lokal komercyjny':'Commercial property','Biuro':'Office','Magazyn':'Warehouse','Hala':'Industrial hall'}},
  uk:{home:'Головна',categories:{mieszkania:'Квартири',domy:'Будинки',dzialki:'Земельні ділянки',lokale:'Комерційні приміщення'},rent:'в оренду',sale:'на продаж',monthly:'на місяць',priceOnRequest:'Ціна за запитом',meta:'Ціна та деталі пропозиції MazurEstate.',photo:'фото',allPhotos:'Переглянути всі фото',labels:['Площа','Кількість кімнат','Площа ділянки','Поверх','Тип нерухомості','Рік побудови','Номер пропозиції'],room:['кімната','кімнати','кімнат'],description:'ПРО НЕРУХОМІСТЬ',descriptionEmpty:'Зв’яжіться з нами, щоб дізнатися більше про цю нерухомість.',features:'ОСОБЛИВОСТІ',highlights:'Головні переваги',featureEmpty:'Подробиці у консультанта',enquiry:'ЗАПИТАТИ ПРО ПРОПОЗИЦІЮ',viewing:'Домовитися про перегляд',enquiryIntro:'Залиште контактні дані. Номер пропозиції автоматично додамо до повідомлення.',orCall:'Або зателефонуйте: +48 503 937 749',reviews:'ВІДГУКИ КЛІЄНТІВ',reviewsTitle:'Що про нас говорять клієнти',moreReviews:'Більше відгуків у Google Maps',previousReview:'Попередній відгук',nextReview:'Наступний відгук',unavailable:'Пропозиція недоступна',unavailableHeading:'Не вдалося показати пропозицію',missingId:'В адресі сторінки немає номера пропозиції.',tryAgain:'Спробуйте ще раз пізніше.',typeNames:{'Mieszkanie':'Квартира','Apartament':'Апартаменти','Dom':'Будинок','Dom wolnostojący':'Окремий будинок','Działka':'Земельна ділянка','Lokal użytkowy':'Комерційне приміщення','Lokal komercyjny':'Комерційне приміщення','Biuro':'Офіс','Magazyn':'Склад','Hala':'Виробниче приміщення'}},
  ru:{home:'Главная',categories:{mieszkania:'Квартиры',domy:'Дома',dzialki:'Участки',lokale:'Коммерческие помещения'},rent:'в аренду',sale:'на продажу',monthly:'в месяц',priceOnRequest:'Цена по запросу',meta:'Цена и подробности предложения MazurEstate.',photo:'фото',allPhotos:'Посмотреть все фото',labels:['Площадь','Количество комнат','Площадь участка','Этаж','Тип недвижимости','Год постройки','Номер предложения'],room:['комната','комнаты','комнат'],description:'О НЕДВИЖИМОСТИ',descriptionEmpty:'Свяжитесь с нами, чтобы узнать подробности об этой недвижимости.',features:'ОСОБЕННОСТИ',highlights:'Главные преимущества',featureEmpty:'Подробности у консультанта',enquiry:'ВОПРОС ПО ПРЕДЛОЖЕНИЮ',viewing:'Записаться на просмотр',enquiryIntro:'Оставьте контактные данные. Номер предложения мы автоматически добавим к сообщению.',orCall:'Или позвоните: +48 503 937 749',reviews:'ОТЗЫВЫ КЛИЕНТОВ',reviewsTitle:'Что говорят о нас клиенты',moreReviews:'Больше отзывов в Google Maps',previousReview:'Предыдущий отзыв',nextReview:'Следующий отзыв',unavailable:'Предложение недоступно',unavailableHeading:'Не удалось показать предложение',missingId:'В адресе страницы нет номера предложения.',tryAgain:'Попробуйте ещё раз позже.',typeNames:{'Mieszkanie':'Квартира','Apartament':'Апартаменты','Dom':'Дом','Dom wolnostojący':'Отдельный дом','Działka':'Участок','Lokal użytkowy':'Коммерческое помещение','Lokal komercyjny':'Коммерческое помещение','Biuro':'Офис','Magazyn':'Склад','Hala':'Производственное помещение'}}
};
const offerLanguage=()=>['pl','en','uk','ru'].includes(window.MAZUR_LANG||new URLSearchParams(location.search).get('lang'))?(window.MAZUR_LANG||new URLSearchParams(location.search).get('lang')):'pl';
const localizedType=(value,lang,category)=>offerUi[lang].typeNames[String(value||'').trim()]||(lang==='pl'?value:offerUi[lang].categories[category]);
const provinceNames={en:{'MAZOWIECKIE':'Masovian Voivodeship'},uk:{'MAZOWIECKIE':'Мазовецьке воєводство'},ru:{'MAZOWIECKIE':'Мазовецкое воеводство'}};
const localizedProvince=(value,lang)=>provinceNames[lang]?.[String(value||'').toLocaleUpperCase('pl')]||value;
// Only translate the closed set of feature labels produced by the importer.
// Free-text tags supplied with an offer remain source content.
const featureNames={
  en:{Balkon:'Balcony',Taras:'Terrace','Ogród':'Garden','Miejsce parkingowe':'Parking space','Parking podziemny':'Underground parking','Garaż':'Garage',Winda:'Lift',Internet:'Internet','Teren zamknięty':'Gated property'},
  uk:{Balkon:'Балкон',Taras:'Тераса','Ogród':'Сад','Miejsce parkingowe':'Паркомісце','Parking podziemny':'Підземний паркінг','Garaż':'Гараж',Winda:'Ліфт',Internet:'Інтернет','Teren zamknięty':'Закрита територія'},
  ru:{Balkon:'Балкон',Taras:'Терраса','Ogród':'Сад','Miejsce parkingowe':'Парковочное место','Parking podziemny':'Подземная парковка','Garaż':'Гараж',Winda:'Лифт',Internet:'Интернет','Teren zamknięty':'Закрытая территория'}
};
const polishPlural=(value,one,few,many)=>{const n=Math.abs(Number(value));if(n===1)return one;if(n%10>=2&&n%10<=4&&(n%100<12||n%100>14))return few;return many};
const localizedRoom=(value,lang)=>lang==='pl'?polishPlural(value,...offerUi.pl.room):lang==='en'?Number(value)===1?offerUi.en.room[0]:offerUi.en.room[1]:polishPlural(value,...offerUi[lang].room);
const formatNumber=(value,lang='pl')=>new Intl.NumberFormat({pl:'pl-PL',en:'en-GB',uk:'uk-UA',ru:'ru-RU'}[lang],{maximumFractionDigits:2}).format(Number(value)||0);

// Reviews are fixed site copy, separate from MLS/EstiCRM listing content.
const reviewUi={
  en:{quotes:[
    '“Professional from the first contact. We sold the apartment faster than expected, and all the paperwork was stress-free.”',
    '“I truly appreciated service in my own language. The agency explained every stage of the rental and found an apartment that really suited me.”',
    '“Their local market expertise shows at every step. The advisers explained each decision clearly and negotiated on our behalf.”',
    '“Fast and efficient service. They found a tenant in under two weeks and handled all the contract formalities.”',
    '“The entire purchase process was explained step by step in my language. I felt secure throughout the transaction.”',
    '“A reliable valuation and effective marketing. The house sold at a very good price without unnecessary delays.”',
    '“I appreciated service in my native language and clear explanations of every document. Recommended for clients from outside Poland.”',
    '“The advisers know Warsaw’s commercial market well. They helped negotiate favourable lease terms.”'
  ],details:['Apartment sale, Wola','Rental, client from Ukraine','House purchase, Ząbki','Rental, Wilanów','Apartment purchase, client from Ukraine','House sale, Wiązowna','Rental','Commercial property, Mokotów']},
  uk:{quotes:[
    '«Професійний підхід із першого контакту. Ми продали квартиру швидше, ніж очікували, а всі формальності пройшли без стресу.»',
    '«Я дуже ціную обслуговування моєю мовою. Агенція допомогла зрозуміти кожен етап оренди й знайти квартиру, яка мені справді підходить.»',
    '«Знання місцевого ринку відчувається на кожному кроці. Консультанти зрозуміло пояснювали рішення та вели переговори від нашого імені.»',
    '«Швидке та ефективне обслуговування. Орендаря знайшли менш ніж за два тижні й взяли на себе всі формальності.»',
    '«Увесь процес купівлі пояснювали крок за кроком моєю мовою. Я почувався впевнено на кожному етапі угоди.»',
    '«Надійна оцінка та ефективний маркетинг. Будинок продали за дуже доброю ціною й без зайвих затримок.»',
    '«Ціную обслуговування рідною мовою та зрозуміле пояснення кожного документа. Рекомендую клієнтам з-за меж Польщі.»',
    '«Консультанти добре знають комерційний ринок Варшави. Вони допомогли домовитися про вигідні умови оренди приміщення.»'
  ],details:['Продаж квартири, Воля','Оренда, клієнтка з України','Купівля будинку, Зомбки','Оренда, Вілянув','Купівля квартири, клієнт з України','Продаж будинку, Вьонзовна','Оренда','Комерційна нерухомість, Мокотув']},
  ru:{quotes:[
    '«Профессиональный подход с первого контакта. Мы продали квартиру быстрее, чем ожидали, а все формальности прошли без стресса.»',
    '«Я очень ценю обслуживание на моём языке. Агентство помогло понять каждый этап аренды и найти квартиру, которая действительно мне подходит.»',
    '«Знание местного рынка заметно на каждом этапе. Консультанты понятно объясняли решения и вели переговоры от нашего имени.»',
    '«Быстрое и эффективное обслуживание. Арендатора нашли менее чем за две недели и взяли на себя все формальности.»',
    '«Весь процесс покупки объясняли пошагово на моём языке. Я чувствовал себя уверенно на каждом этапе сделки.»',
    '«Надёжная оценка и эффективный маркетинг. Дом продали по очень хорошей цене без лишних задержек.»',
    '«Ценю обслуживание на родном языке и понятное объяснение каждого документа. Рекомендую клиентам из других стран.»',
    '«Консультанты хорошо знают коммерческий рынок Варшавы. Они помогли согласовать выгодные условия аренды помещения.»'
  ],details:['Продажа квартиры, Воля','Аренда, клиентка из Украины','Покупка дома, Зомбки','Аренда, Вилянув','Покупка квартиры, клиент из Украины','Продажа дома, Вёнзовна','Аренда','Коммерческая недвижимость, Мокотув']}
};

function applyStaticOfferUi(lang){
  const copy=offerUi[lang];
  text('.breadcrumbs a:first-child',copy.home);
  text('.property-head h1',({pl:'Wczytywanie oferty…',en:'Loading property…',uk:'Завантаження пропозиції…',ru:'Загрузка предложения…'})[lang]);
  text('.features .eyebrow',copy.features);text('.features h2',copy.highlights);
  text('.contact-card .eyebrow',copy.enquiry);text('.contact-card h2',copy.viewing);text('.contact-card p',copy.enquiryIntro);text('.contact-card > a',copy.orCall);
  text('.home-testimonials-heading span',copy.reviews);text('.home-testimonials-heading h2',copy.reviewsTitle);
  text('.google-reviews a span:first-of-type',copy.moreReviews);
  document.getElementById('testi-arrow-left')?.setAttribute('aria-label',copy.previousReview);
  document.getElementById('testi-arrow-right')?.setAttribute('aria-label',copy.nextReview);
  document.querySelector('#lightbox .close')?.setAttribute('aria-label',({pl:'Zamknij galerię',en:'Close gallery',uk:'Закрити галерею',ru:'Закрыть галерею'})[lang]);
  document.querySelector('#lightbox .prev')?.setAttribute('aria-label',({pl:'Poprzednie zdjęcie',en:'Previous photo',uk:'Попереднє фото',ru:'Предыдущее фото'})[lang]);
  document.querySelector('#lightbox .next')?.setAttribute('aria-label',({pl:'Następne zdjęcie',en:'Next photo',uk:'Наступне фото',ru:'Следующее фото'})[lang]);
  if(lang==='pl')return;
  document.querySelectorAll('#testimonials-track [data-tcard]').forEach((card,index)=>{
    textIn(card,'p',reviewUi[lang].quotes[index]);
    textIn(card,'.review-author small',reviewUi[lang].details[index]);
  });
}
function textIn(root,selector,value){const element=root.querySelector(selector);if(element&&value)element.textContent=value}

function setupGallery(images,title,lang=offerLanguage()){
  const copy=offerUi[lang];
  const gallery=document.getElementById('gallery');
  const safeImages=images.length?images:['../assets/images/category-apartments.webp'];
  gallery.innerHTML='';
  safeImages.slice(0,5).forEach((src,index)=>{
    const button=document.createElement('button');
    if(index===0)button.className='gallery-main';
    if(index===4&&safeImages.length>5)button.className='gallery-more';
    const image=document.createElement('img');image.src=src;image.alt=`${title} — ${copy.photo} ${index+1}`;image.loading=index?'lazy':'eager';
    button.appendChild(image);
    if(index===4&&safeImages.length>5){const label=document.createElement('span');label.textContent=`${copy.allPhotos} · ${safeImages.length}`;button.appendChild(label)}
    gallery.appendChild(button);
  });
  const main=gallery.querySelector('.gallery-main'),mainImage=main?.querySelector('img');
  const mobileCounter=document.createElement('span');mobileCounter.className='gallery-mobile-count';mobileCounter.setAttribute('aria-live','polite');gallery.appendChild(mobileCounter);
  const buttons=[...gallery.querySelectorAll('button')],box=document.getElementById('lightbox'),lightboxImage=box?.querySelector('img'),counter=box?.querySelector('.lb-count');
  if(!box||!lightboxImage||!counter)return;
  let current=0;
  const select=index=>{current=(index+safeImages.length)%safeImages.length;if(mainImage){mainImage.src=safeImages[current];mainImage.alt=`${title} — ${copy.photo} ${current+1}`}mobileCounter.textContent=`${current+1} / ${safeImages.length}`;lightboxImage.src=safeImages[current];lightboxImage.alt=`${title} — ${copy.photo} ${current+1}`;counter.textContent=`${current+1} / ${safeImages.length}`};
  const show=index=>{select(index);box.classList.add('open');box.setAttribute('aria-hidden','false');document.body.style.overflow='hidden'};
  const close=()=>{box.classList.remove('open');box.setAttribute('aria-hidden','true');document.body.style.overflow=''};
  select(0);
  let suppressClickUntil=0;
  buttons.forEach((button,index)=>button.addEventListener('click',event=>{if(Date.now()<suppressClickUntil){event.preventDefault();return}show(index===0?current:index)}));
  const addSwipe=(element,onStep)=>{let start=null;element.addEventListener('touchstart',event=>{start=event.touches.length===1?{x:event.touches[0].clientX,y:event.touches[0].clientY}:null},{passive:true});element.addEventListener('touchend',event=>{if(!start||!event.changedTouches.length)return;const dx=event.changedTouches[0].clientX-start.x,dy=event.changedTouches[0].clientY-start.y;start=null;if(Math.abs(dx)<40||Math.abs(dx)<Math.abs(dy)*1.25)return;suppressClickUntil=Date.now()+500;onStep(dx<0?1:-1)},{passive:true});element.addEventListener('touchcancel',()=>{start=null},{passive:true})};
  if(main)addSwipe(main,delta=>select(current+delta));
  addSwipe(box,delta=>select(current+delta));
  box.querySelector('.close')?.addEventListener('click',close);box.querySelector('.prev')?.addEventListener('click',()=>select(current-1));box.querySelector('.next')?.addEventListener('click',()=>select(current+1));
  box.addEventListener('click',event=>{if(event.target===box)close()});
  document.addEventListener('keydown',event=>{if(event.key==='Escape')close();if(box.classList.contains('open')&&event.key==='ArrowLeft')show(current-1);if(box.classList.contains('open')&&event.key==='ArrowRight')show(current+1)});
}

function renderParameters(offer,category,lang){
  const copy=offerUi[lang];
  const values=[
    [copy.labels[0],offer.area?`${formatNumber(offer.area,lang)} m²`:'—'],
    [category==='dzialki'?copy.labels[2]:copy.labels[1],category==='dzialki'?(offer.plotArea?`${formatNumber(offer.plotArea,lang)} m²`:'—'):(offer.rooms?`${offer.rooms} ${localizedRoom(offer.rooms,lang)}`:'—')],
    [copy.labels[3],offer.floor!==''?(offer.buildingFloors?`${offer.floor} / ${offer.buildingFloors}`:offer.floor):'—'],
    [copy.labels[4],localizedType(offer.type,lang,category)||'—'],
    [copy.labels[5],offer.buildingYear||'—'],
    [copy.labels[6],offer.number||offer.id]
  ];
  document.querySelectorAll('.parameter-grid article').forEach((article,index)=>{const pair=values[index];article.querySelector('span').textContent=pair[0];article.querySelector('strong').textContent=pair[1]});
}

function renderDescription(offer,lang){
  const section=document.querySelector('.description');if(!section)return;
  section.innerHTML='';
  const eyebrow=document.createElement('p');eyebrow.className='eyebrow';eyebrow.textContent=offerUi[lang].description;
  const heading=document.createElement('h2');heading.textContent=offer.title;heading.lang=offer.descriptionLanguage||'pl';
  section.append(eyebrow,heading);
  const paragraphs=String(offer.description||offerUi[lang].descriptionEmpty).split(/\n+/).map(item=>item.trim()).filter(Boolean);
  const reader=document.createElement('div');reader.className='description-reader';reader.lang=offer.descriptionLanguage||'pl';
  paragraphs.forEach(content=>{const paragraph=document.createElement('p');paragraph.textContent=content;reader.appendChild(paragraph)});
  section.appendChild(reader);
  // The plain description is always available, even if the new module fails.
  // Only the description changes: photos, prices and the enquiry form do not wait.
  void descriptionRenderer.then(module=>{
    if(!module||!section.isConnected)return;
    try{module.renderOfferDescription(section,offer,{language:document.documentElement.lang})}
    catch(error){console.warn('Description formatting unavailable; plain text retained.')}
  });
}

function renderFeatures(features,lang){
  const section=document.querySelector('.features'),container=section?.querySelector('div');if(!section||!container)return;
  container.innerHTML='';
  const list=features.length?features:[offerUi[lang].featureEmpty];
  list.slice(0,12).forEach(feature=>{const item=document.createElement('span');item.textContent=`✓ ${featureNames[lang]?.[feature]||feature}`;container.appendChild(item)});
}

function showUnavailable(message,lang=offerLanguage()){
  const copy=offerUi[lang];document.title=`${copy.unavailable} | MazurEstate`;text('.breadcrumbs strong',copy.unavailable);text('.property-head .eyebrow',copy.unavailable.toLocaleUpperCase(lang));text('.property-head h1',copy.unavailableHeading);text('.property-head .location',message);
  const price=document.querySelector('.head-price');if(price)price.hidden=true;
  const gallery=document.getElementById('gallery');if(gallery)gallery.hidden=true;
  const layout=document.querySelector('.property-layout');if(layout)layout.hidden=true;
}

async function loadOffer(){
  const params=new URLSearchParams(location.search),id=params.get('id'),lang=['pl','uk','en','ru'].includes((window.MAZUR_LANG || params.get('lang')))?(window.MAZUR_LANG || params.get('lang')):'pl';
  document.documentElement.lang=lang;
  applyStaticOfferUi(lang);
  document.querySelectorAll('.language-picker a').forEach(link=>{const next=new URL(link.href);const label=link.textContent.trim();next.searchParams.set('id',id||'');next.searchParams.set('lang',label==='UKR'?'uk':label.toLowerCase());link.href=next.toString()});
  if(!id){showUnavailable(offerUi[lang].missingId,lang);return}
  try{
    const serverSeed=document.getElementById('server-offer');
    let payload;
    if(serverSeed){
      payload=JSON.parse(serverSeed.textContent);
    }else{
      const response=await fetch(`${MLS_DETAIL_API}?id=${encodeURIComponent(id)}&lang=${encodeURIComponent(lang)}`,{headers:{Accept:'application/json'}});
      payload=await response.json();
      if(!response.ok)throw new Error(offerUi[lang].unavailable);
    }
    if(!payload.offer||String(payload.offer.id)!==id)throw new Error(offerUi[lang].unavailable);
    const offer=payload.offer,category=categoryFor(offer.type),isRent=offer.transaction==='wynajem';
    const copy=offerUi[lang],locationName=[offer.city,offer.district].filter(Boolean).join(', '),locationFull=[locationName,localizedProvince(offer.province,lang)].filter(Boolean).join(' · ');
    const transactionText=isRent?copy.rent:copy.sale,eyebrow=`${localizedType(offer.type,lang,category)} ${transactionText}`.toLocaleUpperCase(lang);
    const currency=offer.currency||'PLN',price=offer.price?`${formatNumber(offer.price,lang)} ${currency}`:copy.priceOnRequest;
    const unit=offer.price&&offer.area?`${formatNumber(Math.round(offer.price/offer.area),lang)} ${currency}/m²`:'';
    document.title=`${offer.title} | MazurEstate`;document.querySelector('meta[name="description"]')?.setAttribute('content',`${offer.title}. ${locationName}. ${copy.meta}`);
    text('.property-head .eyebrow',eyebrow);text('.property-head h1',offer.title);document.querySelector('.property-head h1')?.setAttribute('lang',offer.descriptionLanguage||'pl');text('.property-head .location',locationFull);text('.head-price strong',price);text('.head-price span',[isRent?copy.monthly:'',unit].filter(Boolean).join(' · '));
    const crumbs=document.querySelectorAll('.breadcrumbs a');if(crumbs[0])crumbs[0].href=`../index.html?lang=${lang}`;if(crumbs[1]){crumbs[1].textContent=`${copy.categories[category]} ${transactionText}`;crumbs[1].href=`../wyniki-wyszukiwania/?type=${category}&transaction=${isRent?'wynajem':'sprzedaz'}&lang=${lang}`};text('.breadcrumbs strong',locationName||offer.title);
    document.querySelector('.head-price').hidden=false;document.getElementById('gallery').hidden=false;document.querySelector('.property-layout').hidden=false;
    renderParameters(offer,category,lang);renderDescription(offer,lang);renderFeatures(offer.features||[],lang);setupGallery(offer.images||[],offer.title,lang);window.MazurContactForm?.setOffer({id:offer.id,number:offer.number||offer.id,title:offer.title});
  }catch(error){
    showUnavailable(error.message===offerUi[lang].unavailable?error.message:offerUi[lang].tryAgain,lang);
  }
}

function setupTestimonials(){const track=document.getElementById('testimonials-track');if(!track)return;const step=()=>{const card=track.querySelector('[data-tcard]');return card?card.getBoundingClientRect().width+24:364};document.getElementById('testi-arrow-left')?.addEventListener('click',()=>track.scrollBy({left:-step(),behavior:'smooth'}));document.getElementById('testi-arrow-right')?.addEventListener('click',()=>track.scrollBy({left:step(),behavior:'smooth'}));setInterval(()=>{const end=track.scrollLeft+track.clientWidth>=track.scrollWidth-4;track.scrollTo({left:end?0:track.scrollLeft+step(),behavior:'smooth'})},10000)}

loadOffer().finally(()=>{
  setupTestimonials();
  const sharedChromeScript=document.createElement('script');sharedChromeScript.src='../assets/js/site-chrome.js?v=20261008-static-i18n';document.body.appendChild(sharedChromeScript);
});

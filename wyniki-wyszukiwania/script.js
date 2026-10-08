(async () => {
  const params = new URLSearchParams(location.search);
  const type = ['mieszkania','domy','dzialki','lokale'].includes(params.get('type')) ? params.get('type') : 'mieszkania';
  const transaction = params.get('transaction') === 'wynajem' ? 'wynajem' : 'sprzedaz';
  const locationFilter = params.get('location') || '';
  const requestedLang = window.MAZUR_LANG || params.get('lang');
  const lang = ['pl','uk','en','ru'].includes(requestedLang) ? requestedLang : 'pl';
  // Keep search usable if a stale CDN cache temporarily cannot load the module.
  let galleryController = null;
  const galleryPromise = import('../assets/js/results-gallery.js?v=20261003-module-mime')
    .then(({createResultsGallery}) => { galleryController = createResultsGallery({lang}); })
    .catch(error => { console.warn('Full gallery module unavailable', error); });
  const translations = {
    pl:{chooseProperty:'Wybierz nieruchomość',advisory:'Doradztwo',whyUs:'Dlaczego my',developers:'Dla deweloperów',contact:'Kontakt',contactUs:'Skontaktuj się',back:'← Wróć do wyszukiwarki',offersEyebrow:'OFERTY MAZURESTATE',sort:'Sortuj',newest:'Data dodania: najnowsze',oldest:'Data dodania: najstarsze',priceAsc:'Cena: od najniższej',priceDesc:'Cena: od najwyższej',areaAsc:'Powierzchnia: od najmniejszej',areaDesc:'Powierzchnia: od największej',perPage:'Pokaż na stronie',navigation:'Nawigacja',reviews:'Opinie klientów',offers:'Oferty',footerIntro:'Agencja nieruchomości w Warszawie. Sprzedaż, wynajem i kompleksowa obsługa nieruchomości.',rights:'Wszelkie prawa zastrzeżone.',types:{mieszkania:'Mieszkania',domy:'Domy',dzialki:'Działki',lokale:'Lokale komercyjne'},sale:'na sprzedaż',rent:'na wynajem',found:'Znaleziono {n} dopasowanych ofert',to:'do',from:'od',rooms:'pokoi',spaces:'pomieszczenia',plot:'Działka',utilities:'Media w drodze',floors:'2 kondygnacje',ground:'Parter',floor:'piętro',offer:'Oferta MazurEstate',prevPhoto:'Poprzednie zdjęcie',nextPhoto:'Następne zdjęcie',favorite:'Dodaj do ulubionych',prevPage:'Poprzednia strona',nextPage:'Następna strona'},
    uk:{chooseProperty:'Обрати нерухомість',advisory:'Консультації',whyUs:'Чому ми',developers:'Для забудовників',contact:'Контакти',contactUs:'Зв’язатися',back:'← Повернутися до пошуку',offersEyebrow:'ПРОПОЗИЦІЇ MAZURESTATE',sort:'Сортувати',newest:'Дата додавання: найновіші',oldest:'Дата додавання: найстаріші',priceAsc:'Ціна: від найнижчої',priceDesc:'Ціна: від найвищої',areaAsc:'Площа: від найменшої',areaDesc:'Площа: від найбільшої',perPage:'Показати на сторінці',navigation:'Навігація',reviews:'Відгуки клієнтів',offers:'Пропозиції',footerIntro:'Агенція нерухомості у Варшаві. Продаж, оренда та комплексний супровід нерухомості.',rights:'Усі права захищені.',types:{mieszkania:'Квартири',domy:'Будинки',dzialki:'Земельні ділянки',lokale:'Комерційні приміщення'},sale:'на продаж',rent:'в оренду',found:'Знайдено {n} відповідних пропозицій',to:'до',from:'від',rooms:'кімнат',spaces:'приміщення',plot:'Ділянка',utilities:'Комунікації поруч',floors:'2 поверхи',ground:'Перший поверх',floor:'поверх',offer:'Пропозиція MazurEstate',prevPhoto:'Попереднє фото',nextPhoto:'Наступне фото',favorite:'Додати до обраного',prevPage:'Попередня сторінка',nextPage:'Наступна сторінка'},
    en:{chooseProperty:'Choose a property',advisory:'Advisory',whyUs:'Why us',developers:'For developers',contact:'Contact',contactUs:'Contact us',back:'← Back to search',offersEyebrow:'MAZURESTATE PROPERTIES',sort:'Sort',newest:'Date added: newest',oldest:'Date added: oldest',priceAsc:'Price: lowest first',priceDesc:'Price: highest first',areaAsc:'Area: smallest first',areaDesc:'Area: largest first',perPage:'Show per page',navigation:'Navigation',reviews:'Client reviews',offers:'Properties',footerIntro:'A Warsaw real estate agency providing sales, rentals and comprehensive property support.',rights:'All rights reserved.',types:{mieszkania:'Apartments',domy:'Houses',dzialki:'Land',lokale:'Commercial properties'},sale:'for sale',rent:'for rent',found:'{n} matching properties found',to:'up to',from:'from',rooms:'rooms',spaces:'spaces',plot:'Plot',utilities:'Utilities nearby',floors:'2 storeys',ground:'Ground floor',floor:'floor',offer:'MazurEstate property',prevPhoto:'Previous photo',nextPhoto:'Next photo',favorite:'Add to favourites',prevPage:'Previous page',nextPage:'Next page'},
    ru:{chooseProperty:'Выбрать недвижимость',advisory:'Консультации',whyUs:'Почему мы',developers:'Для застройщиков',contact:'Контакты',contactUs:'Связаться',back:'← Вернуться к поиску',offersEyebrow:'ПРЕДЛОЖЕНИЯ MAZURESTATE',sort:'Сортировать',newest:'Дата добавления: новые',oldest:'Дата добавления: старые',priceAsc:'Цена: сначала ниже',priceDesc:'Цена: сначала выше',areaAsc:'Площадь: сначала меньше',areaDesc:'Площадь: сначала больше',perPage:'Показать на странице',navigation:'Навигация',reviews:'Отзывы клиентов',offers:'Предложения',footerIntro:'Агентство недвижимости в Варшаве. Продажа, аренда и комплексное сопровождение недвижимости.',rights:'Все права защищены.',types:{mieszkania:'Квартиры',domy:'Дома',dzialki:'Участки',lokale:'Коммерческие помещения'},sale:'на продажу',rent:'в аренду',found:'Найдено {n} подходящих предложений',to:'до',from:'от',rooms:'комнат',spaces:'помещения',plot:'Участок',utilities:'Коммуникации рядом',floors:'2 этажа',ground:'Первый этаж',floor:'этаж',offer:'Предложение MazurEstate',prevPhoto:'Предыдущее фото',nextPhoto:'Следующее фото',favorite:'Добавить в избранное',prevPage:'Предыдущая страница',nextPage:'Следующая страница'}
  };
  const t=translations[lang];
  const filterCopy={
    pl:['Typ nieruchomości','Lokalizacja','Transakcja','Cena maks.','Powierzchnia','Miasto lub dzielnica','Szukaj','Sprzedaż','Wynajem','Warszawa — dzielnice','Miasta i okolice','Brak pasujących lokalizacji'],
    en:['Property type','Location','Transaction','Max. price','Area','City or district','Search','Sale','Rent','Warsaw — districts','Cities and surroundings','No matching locations'],
    uk:['Тип нерухомості','Розташування','Операція','Макс. ціна','Площа','Місто або район','Шукати','Продаж','Оренда','Варшава — райони','Міста та околиці','Немає відповідних локацій'],
    ru:['Тип недвижимости','Расположение','Сделка','Макс. цена','Площадь','Город или район','Найти','Продажа','Аренда','Варшава — районы','Города и окрестности','Подходящих локаций нет']
  }[lang];
  const advancedCopy={
    pl:{price:'Cena',area:'Powierzchnia',rooms:'Liczba pokoi',priceMin:'Cena od',priceMax:'Cena do',areaMin:'Powierzchnia od',areaMax:'Powierzchnia do',from:'od',to:'do',clear:'Wyczyść filtry',show:'Pokaż oferty',roomsChip:'pok.',loading:'Ładowanie ofert…',empty:'Brak ofert spełniających wybrane kryteria.'},
    en:{price:'Price',area:'Area',rooms:'Rooms',priceMin:'Price from',priceMax:'Price to',areaMin:'Area from',areaMax:'Area to',from:'from',to:'to',clear:'Clear filters',show:'Show properties',roomsChip:'rooms',loading:'Loading properties…',empty:'No properties match these filters.'},
    uk:{price:'Ціна',area:'Площа',rooms:'Кімнати',priceMin:'Ціна від',priceMax:'Ціна до',areaMin:'Площа від',areaMax:'Площа до',from:'від',to:'до',clear:'Очистити фільтри',show:'Показати пропозиції',roomsChip:'кімн.',loading:'Завантаження пропозицій…',empty:'Немає пропозицій за цими критеріями.'},
    ru:{price:'Цена',area:'Площадь',rooms:'Комнаты',priceMin:'Цена от',priceMax:'Цена до',areaMin:'Площадь от',areaMax:'Площадь до',from:'от',to:'до',clear:'Сбросить фильтры',show:'Показать предложения',roomsChip:'комн.',loading:'Загрузка предложений…',empty:'По этим критериям предложений нет.'}
  }[lang];
  const moreCopy={
    pl:{any:'Dowolna',more:'Więcej filtrów',less:'Mniej filtrów',floor:'Piętro',anyFloor:'Dowolne',ground:'Parter',higher:'4 i wyżej',unitPrice:'Cena za m²',plot:'Powierzchnia działki',photos:'Tylko oferty ze zdjęciami',roomNames:['1 pokój','2 pokoje','3 pokoje','4 pokoje','5 pokoi','6+ pokoi']},
    en:{any:'Any',more:'More filters',less:'Fewer filters',floor:'Floor',anyFloor:'Any floor',ground:'Ground floor',higher:'4 or higher',unitPrice:'Price per m²',plot:'Plot area',photos:'Only with photos',roomNames:['1 room','2 rooms','3 rooms','4 rooms','5 rooms','6+ rooms']},
    uk:{any:'Будь-яка',more:'Більше фільтрів',less:'Менше фільтрів',floor:'Поверх',anyFloor:'Будь-який',ground:'Перший поверх',higher:'4 і вище',unitPrice:'Ціна за м²',plot:'Площа ділянки',photos:'Лише з фото',roomNames:['1 кімната','2 кімнати','3 кімнати','4 кімнати','5 кімнат','6+ кімнат']},
    ru:{any:'Любая',more:'Больше фильтров',less:'Меньше фильтров',floor:'Этаж',anyFloor:'Любой',ground:'Первый этаж',higher:'4 и выше',unitPrice:'Цена за м²',plot:'Площадь участка',photos:'Только с фото',roomNames:['1 комната','2 комнаты','3 комнаты','4 комнаты','5 комнат','6+ комнат']}
  }[lang];
  const numericValue=(value,decimal=false)=>Number(decimal?String(value||'').replace(/\s/g,'').replace(',','.').replace(/[^\d.]/g,''):String(value||'').replace(/\D/g,''))||0;
  const minPrice=numericValue(params.get('priceMin'));
  const maxPrice=numericValue(params.get('priceMax')||params.get('price'))||Infinity;
  const minArea=numericValue(params.get('areaMin')||params.get('area'),true);
  const maxArea=numericValue(params.get('areaMax'),true)||Infinity;
  const selectedRooms=(params.get('rooms')||'').split(',').filter(value=>/^[1-6]$/.test(value)).map(Number);
  const selectedFloor=params.get('floor')||'';
  const minUnitPrice=numericValue(params.get('unitMin'));
  const maxUnitPrice=numericValue(params.get('unitMax'))||Infinity;
  const minPlot=numericValue(params.get('plotMin'));
  const maxPlot=numericValue(params.get('plotMax'))||Infinity;
  const photosOnly=params.get('photos')==='1';
  const typeLabels=t.types;
  const transactionLabel = transaction === 'wynajem' ? t.rent : t.sale;
  const images = ['../assets/images/category-apartments.webp','../assets/images/hf_20260726_144524_c09ad56f-4bf3-454e-ad5c-3ebd0166d985.webp','../assets/images/category-houses.webp','../assets/images/category-commercial.webp','../assets/images/individual-approach-v2.webp','../assets/images/hf_20260726_144517_90fd2149-4cca-4eef-974e-74aa570050e6.webp'];
  const locations = ['Warszawa, Wilanów','Warszawa, Mokotów','Warszawa, Wola','Warszawa, Ochota','Konstancin-Jeziorna','Piaseczno','Warszawa, Żoliborz','Warszawa, Praga Południe','Ząbki','Wiązowna','Warszawa, Ursynów','Warszawa, Śródmieście'];
  let offers = [];
  const apiUrl='https://api.mazurestate.pl/api/mls-test.php';
  const locationsUrl='https://api.mazurestate.pl/api/mls-locations.php';
  const normalize=value=>String(value||'').toLocaleLowerCase('pl').trim();
  const normalizeLocation=value=>normalize(value).replace(/ł/g,'l').normalize('NFD').replace(/[\u0300-\u036f]/g,'');
  const categoryFor=value=>{const name=normalize(value);if(name.startsWith('działka')||name.includes('grunt'))return 'dzialki';if(name.startsWith('mieszkanie')||name.startsWith('apartament'))return 'mieszkania';if(name.startsWith('dom'))return 'domy';if(/lokal|komerc|biuro|magazyn|hala|obiekt/.test(name))return 'lokale';return null};
  const fallbackImages={mieszkania:images[0],domy:images[2],dzialki:images[4],lokale:images[3]};
  let loadError=false;
  let loading=true;
  let availableLocations=[];
  const offersPromise=(async()=>{try {
    const controller=new AbortController();
    const timeout=setTimeout(()=>controller.abort(),12000);
    try {
    const [response,locationsResponse]=await Promise.all([
      fetch(`${apiUrl}?lang=${encodeURIComponent(lang)}`,{headers:{Accept:'application/json'},signal:controller.signal}),
      fetch(locationsUrl,{headers:{Accept:'application/json'},signal:controller.signal}).catch(()=>null)
    ]);
    if(!response.ok)throw new Error('MLS API unavailable');
    const payload=await response.json();
    let locationRows=payload.offers;
    if(locationsResponse?.ok){try{const index=await locationsResponse.json();if(Array.isArray(index.locations))locationRows=index.locations}catch(error){/* Keep the offer-list fallback. */}}
    const cityMap=new Map();
    locationRows.forEach(item=>{const city=String(item.city||'').trim(),district=String(item.district||'').trim();if(!city)return;const key=normalizeLocation(city);if(!cityMap.has(key))cityMap.set(key,{city,districts:new Set()});if(district)cityMap.get(key).districts.add(district)});
    availableLocations=[...cityMap.values()].map(entry=>({city:entry.city,districts:[...entry.districts].sort((a,b)=>a.localeCompare(b,'pl'))})).sort((a,b)=>a.city.localeCompare(b.city,'pl'));
    offers=payload.offers.map(item=>{
      const category=categoryFor(item.type);
      const itemTransaction=item.transaction==='wynajem'?'wynajem':'sprzedaz';
      const location=[item.city,item.district].filter(Boolean).join(', ');
      const gallery=item.images?.length?item.images:[fallbackImages[category]||images[0]];
      const rooms=category==='dzialki'?t.plot:category==='lokale'?(item.rooms?`${item.rooms} ${t.spaces}`:t.spaces):(item.rooms?`${item.rooms} ${t.rooms}`:'');
      const floor=category==='dzialki'?(item.plotArea?`${item.plotArea} m²`:t.utilities):category==='domy'?t.floors:category==='lokale'?t.ground:(item.floor!==''?`${item.floor} ${t.floor}`:'');
      const floorRaw=String(item.floor??'').toLocaleLowerCase('pl');
      const floorNumber=/parter|ground/.test(floorRaw)?0:/^\d+$/.test(floorRaw)?Number(floorRaw):null;
      return {id:item.id,title:item.title,titleLanguage:item.titleLanguage||'pl',location,area:Number(item.area)||0,price:Number(item.price)||0,plotArea:Number(item.plotArea)||0,gallery,imageCount:item.imageCount,fallbackImage:fallbackImages[category]||images[0],hasPhotos:!!item.images?.length,rooms,roomsCount:Number(item.rooms)||0,floor,floorNumber,date:Date.parse(item.exportedAt)||0,category,transaction:itemTransaction};
    }).filter(item=>item.category===type&&item.transaction===transaction&&normalizeLocation(item.location).includes(normalizeLocation(locationFilter))&&item.price>=minPrice&&item.price<=maxPrice&&item.area>=minArea&&item.area<=maxArea&&(!selectedRooms.length||type!=='mieszkania'||selectedRooms.some(count=>count===6?item.roomsCount>=6:item.roomsCount===count))&&(!selectedFloor||type!=='mieszkania'||(item.floorNumber!==null&&(selectedFloor==='4'?item.floorNumber>=4:item.floorNumber===Number(selectedFloor))))&&(!minUnitPrice||item.area>0&&item.price/item.area>=minUnitPrice)&&(!Number.isFinite(maxUnitPrice)||item.area>0&&item.price/item.area<=maxUnitPrice)&&(type!=='dzialki'||item.plotArea>=minPlot&&item.plotArea<=maxPlot)&&(!photosOnly||item.hasPhotos));
    } finally {clearTimeout(timeout)}
  } catch(error) {
    loadError=true;
    offers=[];
  }})();
  let currentPage=1;
  const galleryIndex={};
  const list=document.getElementById('offer-list');
  const perPage=document.getElementById('per-page');
  const pagination=document.getElementById('pagination');
  const sort=document.getElementById('sort-select');
  const money=n=>new Intl.NumberFormat(lang==='en'?'en-GB':lang==='uk'?'uk-UA':lang==='ru'?'ru-RU':'pl-PL').format(n)+' zł'+(transaction==='wynajem'?(lang==='en'?' / month':lang==='uk'?' / міс.':lang==='ru'?' / мес.':' / mies.'):'');
  const unit=o=>Math.round(o.price/o.area).toLocaleString(lang==='en'?'en-GB':'pl-PL')+' zł/m²';
  document.documentElement.lang=lang;
  const filterIds=['filter-type-label','filter-location-label','filter-transaction-label'];
  filterIds.forEach((id,i)=>document.getElementById(id).textContent=filterCopy[i]);
  document.getElementById('filter-price-label').textContent=advancedCopy.price;
  document.getElementById('filter-area-label').textContent=advancedCopy.area;
  document.getElementById('rooms-label').textContent=advancedCopy.rooms;
  [['price-min-label',advancedCopy.priceMin],['price-max-label',advancedCopy.priceMax],['area-min-label',advancedCopy.areaMin],['area-max-label',advancedCopy.areaMax]].forEach(([id,label])=>document.getElementById(id).textContent=label);
  document.querySelectorAll('.range-inputs input').forEach(input=>input.placeholder=input.id.endsWith('max')||input.id==='filter-price'?advancedCopy.to:advancedCopy.from);
  document.getElementById('filter-clear').textContent=advancedCopy.clear;
  document.getElementById('filter-submit').textContent=advancedCopy.show;
  [['floor-label',moreCopy.floor],['unit-price-label',moreCopy.unitPrice],['plot-label',moreCopy.plot],['photos-label',moreCopy.photos],['unit-min-label',`${moreCopy.unitPrice} ${advancedCopy.from}`],['unit-max-label',`${moreCopy.unitPrice} ${advancedCopy.to}`],['plot-min-label',`${moreCopy.plot} ${advancedCopy.from}`],['plot-max-label',`${moreCopy.plot} ${advancedCopy.to}`]].forEach(([id,label])=>document.getElementById(id).textContent=label);
  const floorSelect=document.getElementById('filter-floor');
  floorSelect.options[0].textContent=moreCopy.anyFloor;floorSelect.options[1].textContent=moreCopy.ground;floorSelect.options[5].textContent=moreCopy.higher;
  document.querySelectorAll('#room-options label>span').forEach((span,i)=>{span.textContent=moreCopy.roomNames[i]});
  const syncTypeFilters=()=>{document.getElementById('rooms-filter').hidden=filterType.value!=='mieszkania';document.getElementById('floor-filter').hidden=filterType.value!=='mieszkania';document.getElementById('plot-filter').hidden=filterType.value!=='dzialki'};
  document.getElementById('filter-location').placeholder=filterCopy[5];
  const filterType=document.getElementById('filter-type'),filterTransaction=document.getElementById('filter-transaction');
  filterType.value=type;filterTransaction.value=transaction;syncTypeFilters();
  function setupFilterMenu(input,triggerId,menuId,options){
    const trigger=document.getElementById(triggerId),menu=document.getElementById(menuId),wrapper=trigger.closest('.custom-filter');
    const paint=()=>{trigger.querySelector('span').textContent=options.find(option=>option.value===input.value)?.label||'';menu.innerHTML=options.map(option=>`<button type="button" role="option" aria-selected="${option.value===input.value}" class="${option.value===input.value?'selected':''}" data-value="${option.value}">${option.label}</button>`).join('')};
    const close=()=>{wrapper.classList.remove('open');trigger.setAttribute('aria-expanded','false')};
    const toggle=()=>{const willOpen=!wrapper.classList.contains('open');document.querySelectorAll('.custom-filter.open').forEach(element=>{element.classList.remove('open');element.querySelector('.filter-trigger')?.setAttribute('aria-expanded','false')});wrapper.classList.toggle('open',willOpen);trigger.setAttribute('aria-expanded',String(willOpen));if(willOpen&&!matchMedia('(pointer:coarse)').matches)requestAnimationFrame(()=>menu.querySelector('.selected')?.focus())};
    paint();trigger.addEventListener('click',event=>{event.preventDefault();event.stopImmediatePropagation();toggle()});
    trigger.addEventListener('keydown',event=>{if(event.key==='ArrowDown'){event.preventDefault();toggle()}if(event.key==='Escape')close()});
    menu.addEventListener('click',event=>{const option=event.target.closest('[data-value]');if(!option)return;input.value=option.dataset.value;paint();close();input.dispatchEvent(new Event('change',{bubbles:true}))});
    menu.addEventListener('keydown',event=>{const buttons=[...menu.querySelectorAll('[data-value]')],index=buttons.indexOf(document.activeElement);if(event.key==='Escape'){event.preventDefault();close();trigger.focus()}else if(event.key==='ArrowDown'||event.key==='ArrowUp'){event.preventDefault();buttons[(index+(event.key==='ArrowDown'?1:-1)+buttons.length)%buttons.length]?.focus()}});
    document.addEventListener('click',event=>{if(!wrapper.contains(event.target))close()});
  }
  setupFilterMenu(filterType,'filter-type-trigger','filter-type-menu',Object.entries(typeLabels).map(([value,label])=>({value,label})));
  setupFilterMenu(filterTransaction,'filter-transaction-trigger','filter-transaction-menu',[{value:'sprzedaz',label:filterCopy[7]},{value:'wynajem',label:filterCopy[8]}]);
  setupFilterMenu(sort,'sort-trigger','sort-menu',[{value:'newest',label:t.newest},{value:'oldest',label:t.oldest},{value:'price-asc',label:t.priceAsc},{value:'price-desc',label:t.priceDesc},{value:'area-asc',label:t.areaAsc},{value:'area-desc',label:t.areaDesc}]);
  const perPageControl=document.querySelector('.per-page-control'),perPageMenu=document.getElementById('per-page-menu'),perPageCurrent=document.getElementById('per-page-current');
  perPageMenu.addEventListener('click',event=>{const option=event.target.closest('[data-value]');if(!option)return;perPage.value=option.dataset.value;perPageCurrent.textContent=option.dataset.value;perPageMenu.querySelectorAll('[data-value]').forEach(button=>{const selected=button===option;button.classList.toggle('selected',selected);button.setAttribute('aria-selected',String(selected))});perPageControl.removeAttribute('open');perPage.dispatchEvent(new Event('change',{bubbles:true}))});
  document.addEventListener('click',event=>{if(!perPageControl.contains(event.target))perPageControl.removeAttribute('open')});
  document.getElementById('filter-location').value=locationFilter;
  document.getElementById('filter-price-min').value=params.get('priceMin')||'';
  document.getElementById('filter-price').value=params.get('priceMax')||params.get('price')||'';
  document.getElementById('filter-area').value=params.get('areaMin')||params.get('area')||'';
  document.getElementById('filter-area-max').value=params.get('areaMax')||'';
  document.querySelectorAll('#room-options input').forEach(input=>{input.checked=selectedRooms.includes(Number(input.value))});
  const roomsDropdown=document.querySelector('.rooms-dropdown'),roomCurrent=document.getElementById('rooms-current');
  const updateRooms=()=>{const values=[...document.querySelectorAll('#room-options input:checked')].map(input=>Number(input.value));roomCurrent.textContent=values.length?values.map(n=>n===6?'6+':n).join(', ')+` ${advancedCopy.roomsChip}`:moreCopy.any};
  document.getElementById('room-options').addEventListener('change',updateRooms);updateRooms();
  document.addEventListener('click',event=>{if(!roomsDropdown.contains(event.target))roomsDropdown.removeAttribute('open')});
  floorSelect.value=selectedFloor;
  setupFilterMenu(floorSelect,'filter-floor-trigger','filter-floor-menu',[...floorSelect.options].map(option=>({value:option.value,label:option.textContent})));
  [['filter-unit-min','unitMin'],['filter-unit-max','unitMax'],['filter-plot-min','plotMin'],['filter-plot-max','plotMax']].forEach(([id,key])=>{document.getElementById(id).value=params.get(key)||''});
  document.getElementById('filter-photos').checked=photosOnly;
  filterType.addEventListener('change',syncTypeFilters);
  const moreButton=document.getElementById('more-filters'),advancedPanel=document.getElementById('advanced-panel');
  const updateMore=()=>{const open=!advancedPanel.hidden;moreButton.setAttribute('aria-expanded',String(open));moreButton.innerHTML=`${open?moreCopy.less:moreCopy.more} <span aria-hidden="true">${open?'⌃':'⌄'}</span>`};
  moreButton.addEventListener('click',()=>{advancedPanel.hidden=!advancedPanel.hidden;updateMore()});updateMore();
  const locationInput=document.getElementById('filter-location'),locationMenu=document.getElementById('filter-locations'),locationToggle=document.getElementById('location-toggle');
  const locationCopy={
    pl:{cities:'Miejscowości z ofertami',districts:'Dzielnice',back:'Wróć do miast',all:'Całe miasto',city:'miasto',district:'dzielnica',empty:'Brak pasujących lokalizacji w dostępnych ofertach'},
    en:{cities:'Cities with properties',districts:'Districts',back:'Back to cities',all:'Entire city',city:'city',district:'district',empty:'No matching locations in available properties'},
    uk:{cities:'Міста з пропозиціями',districts:'Райони',back:'Назад до міст',all:'Усе місто',city:'місто',district:'район',empty:'Немає відповідних локацій серед пропозицій'},
    ru:{cities:'Города с предложениями',districts:'Районы',back:'Назад к городам',all:'Весь город',city:'город',district:'район',empty:'Нет подходящих мест среди предложений'}
  }[lang];
  const safeLocation=value=>String(value).replace(/[&<>"']/g,char=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]));
  let locationScope='';
  const cityOption=entry=>`<div class="location-city-row"><button type="button" role="option" data-location="${safeLocation(entry.city)}"><span><b>${safeLocation(entry.city)}</b><small>${locationCopy.city}</small></span></button>${entry.districts.length?`<button type="button" class="location-city-expand" data-city="${safeLocation(entry.city)}" aria-label="${locationCopy.districts}: ${safeLocation(entry.city)}">›</button>`:''}</div>`;
  const districtOption=(city,district)=>`<button type="button" role="option" data-location="${safeLocation(city)}, ${safeLocation(district)}"><span><b>${safeLocation(district)}</b><small>${safeLocation(city)} · ${locationCopy.district}</small></span></button>`;
  function renderLocations(){
    const query=normalizeLocation(locationInput.value);
    if(locationScope){const entry=availableLocations.find(item=>item.city===locationScope);if(!entry){locationScope='';return renderLocations()}locationMenu.innerHTML=`<button type="button" class="location-back" data-back="true">← ${locationCopy.back}</button><div class="location-group"><strong>${safeLocation(entry.city)}</strong><button type="button" role="option" data-location="${safeLocation(entry.city)}"><span><b>${locationCopy.all}</b><small>${safeLocation(entry.city)}</small></span></button>${entry.districts.map(district=>districtOption(entry.city,district)).join('')}</div>`;return}
    const matches=availableLocations.map(entry=>({entry,cityMatch:normalizeLocation(entry.city).includes(query),districts:entry.districts.filter(district=>normalizeLocation(district).includes(query))})).filter(result=>!query||result.cityMatch||result.districts.length);
    if(!matches.length){locationMenu.innerHTML=`<p>${locationCopy.empty}</p>`;return}
    locationMenu.innerHTML=`<div class="location-group"><strong>${locationCopy.cities}</strong>${matches.map(({entry,cityMatch,districts})=>cityOption(entry)+(query?(cityMatch?entry.districts.slice(0,4):districts).map(district=>districtOption(entry.city,district)).join(''):'' )).join('')}</div>`;
  }
  function openLocations(){renderLocations();locationMenu.classList.add('open');locationInput.setAttribute('aria-expanded','true')}
  function closeLocations(){locationMenu.classList.remove('open');locationInput.setAttribute('aria-expanded','false');locationScope=''}
  locationInput.addEventListener('focus',openLocations);locationInput.addEventListener('input',()=>{locationScope='';openLocations()});
  locationInput.addEventListener('keydown',event=>{if(event.key==='Escape')closeLocations();if(event.key==='ArrowDown'){event.preventDefault();openLocations();locationMenu.querySelector('button')?.focus()}});
  locationToggle.addEventListener('click',()=>locationMenu.classList.contains('open')?closeLocations():(locationInput.focus(),openLocations()));
  locationMenu.addEventListener('click',event=>{const back=event.target.closest('[data-back]');if(back){locationScope='';renderLocations();locationMenu.querySelector('[data-location]')?.focus();return}const city=event.target.closest('[data-city]');if(city){locationScope=city.dataset.city;renderLocations();locationMenu.querySelector('[data-location]')?.focus();return}const option=event.target.closest('[data-location]');if(option){locationInput.value=option.dataset.location;locationInput.focus();closeLocations()}});
  locationMenu.addEventListener('keydown',event=>{if(event.key==='Escape'){locationInput.focus();closeLocations()}if(event.key==='ArrowDown'||event.key==='ArrowUp'){const buttons=[...locationMenu.querySelectorAll('button')],index=buttons.indexOf(document.activeElement);event.preventDefault();buttons[(index+(event.key==='ArrowDown'?1:-1)+buttons.length)%buttons.length]?.focus()}});
  document.addEventListener('click',event=>{if(!event.target.closest('.location-filter'))closeLocations()});
  const searchForm=document.getElementById('results-search');
  searchForm.addEventListener('submit',event=>{event.preventDefault();const next=new URLSearchParams({type:filterType.value,transaction:filterTransaction.value});const values={location:locationInput.value.trim(),priceMin:document.getElementById('filter-price-min').value.trim(),priceMax:document.getElementById('filter-price').value.trim(),areaMin:document.getElementById('filter-area').value.trim(),areaMax:document.getElementById('filter-area-max').value.trim(),rooms:[...document.querySelectorAll('#room-options input:checked')].map(input=>input.value).join(','),floor:floorSelect.value,unitMin:document.getElementById('filter-unit-min').value.trim(),unitMax:document.getElementById('filter-unit-max').value.trim(),plotMin:document.getElementById('filter-plot-min').value.trim(),plotMax:document.getElementById('filter-plot-max').value.trim(),photos:document.getElementById('filter-photos').checked?'1':''};if(filterType.value!=='mieszkania'){values.rooms='';values.floor=''}if(filterType.value!=='dzialki'){values.plotMin='';values.plotMax=''}Object.entries(values).forEach(([key,value])=>{if(value)next.set(key,value)});location.search=next.toString()});
  searchForm.addEventListener('reset',event=>{event.preventDefault();location.search=new URLSearchParams({type:filterType.value,transaction:filterTransaction.value}).toString()});
  document.title=`${typeLabels[type]} ${transactionLabel} | MazurEstate`;
  document.querySelectorAll('[data-i18n]').forEach(el=>{el.textContent=t[el.dataset.i18n]});
  document.querySelectorAll('[href^="../index.html"]').forEach(a=>{const parts=a.getAttribute('href').split('#');a.href=`../index.html?lang=${lang}${parts[1]?`#${parts[1]}`:''}`});
  document.querySelectorAll('[data-type-link]').forEach(a=>{const q=new URLSearchParams(a.search);q.set('lang',lang);a.search=q.toString();a.textContent=typeLabels[a.dataset.typeLink]});
  const langLabels={pl:'PL',uk:'UKR',en:'ENG',ru:'RU'};
  const languageCurrent=document.getElementById('language-current');
  if(languageCurrent)languageCurrent.textContent=langLabels[lang];
  document.querySelector(`[data-lang="${lang}"]`)?.classList.add('active');
  document.getElementById('results-title').textContent=`${typeLabels[type]} ${transactionLabel}${locationFilter?` — ${locationFilter}`:''}`;
  const summary=document.getElementById('results-summary');
  const loadErrorText={pl:'Nie udało się pobrać ofert. Spróbuj ponownie za chwilę.',en:'Properties could not be loaded. Please try again shortly.',uk:'Не вдалося завантажити пропозиції. Спробуйте ще раз пізніше.',ru:'Не удалось загрузить предложения. Попробуйте ещё раз позже.'};
  const updateSummary=()=>{summary.textContent=loading?advancedCopy.loading:loadError?loadErrorText[lang]:t.found.replace('{n}',offers.length)};
  updateSummary();
  const chips=[typeLabels[type],transactionLabel,locationFilter,params.get('priceMin')&&`${advancedCopy.price} ${advancedCopy.from} ${params.get('priceMin')} zł`,(params.get('priceMax')||params.get('price'))&&`${advancedCopy.price} ${advancedCopy.to} ${params.get('priceMax')||params.get('price')} zł`,(params.get('areaMin')||params.get('area'))&&`${advancedCopy.area} ${advancedCopy.from} ${params.get('areaMin')||params.get('area')} m²`,params.get('areaMax')&&`${advancedCopy.area} ${advancedCopy.to} ${params.get('areaMax')} m²`,selectedRooms.length&&`${selectedRooms.map(n=>n===6?'6+':n).join(', ')} ${advancedCopy.roomsChip}`,selectedFloor&&`${moreCopy.floor}: ${selectedFloor==='0'?moreCopy.ground:selectedFloor==='4'?moreCopy.higher:selectedFloor}`,params.get('unitMin')&&`${moreCopy.unitPrice} ${advancedCopy.from} ${params.get('unitMin')}`,params.get('unitMax')&&`${moreCopy.unitPrice} ${advancedCopy.to} ${params.get('unitMax')}`,params.get('plotMin')&&`${moreCopy.plot} ${advancedCopy.from} ${params.get('plotMin')} m²`,params.get('plotMax')&&`${moreCopy.plot} ${advancedCopy.to} ${params.get('plotMax')} m²`,photosOnly&&moreCopy.photos].filter(Boolean);

  function ordered(){const data=[...offers];if(sort.value==='price-asc')data.sort((a,b)=>a.price-b.price);if(sort.value==='price-desc')data.sort((a,b)=>b.price-a.price);if(sort.value==='area-asc')data.sort((a,b)=>a.area-b.area);if(sort.value==='area-desc')data.sort((a,b)=>b.area-a.area);if(sort.value==='newest')data.sort((a,b)=>b.date-a.date);if(sort.value==='oldest')data.sort((a,b)=>a.date-b.date);return data}
  const escapeHtml=value=>String(value??'').replace(/[&<>'"]/g,char=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[char]));
  document.getElementById('active-filters').innerHTML=chips.map(x=>`<span class="filter-chip">${escapeHtml(x)}</span>`).join('');
  function render(){if(loading){list.innerHTML=`<p>${advancedCopy.loading}</p>`;pagination.innerHTML='';return}const count=Number(perPage.value);const data=ordered();const pages=Math.ceil(data.length/count);if(!pages){currentPage=1;list.innerHTML=`<p>${advancedCopy.empty}</p>`;pagination.innerHTML='';return}currentPage=Math.min(currentPage,pages);const visible=data.slice((currentPage-1)*count,currentPage*count);list.innerHTML=visible.map(card).join('');const pageNumbers=Array.from({length:pages},(_,i)=>i+1).filter(n=>n===1||n===pages||Math.abs(n-currentPage)<=1);const pageLinks=pageNumbers.map((n,i)=>`${i&&n-pageNumbers[i-1]>1?'<span class="page-ellipsis" aria-hidden="true">…</span>':''}<button class="page-button ${currentPage===n?'active':''}" data-page="${n}" ${currentPage===n?'aria-current="page"':''}>${n}</button>`).join('');pagination.innerHTML=`<button class="page-button" data-page="${Math.max(1,currentPage-1)}" aria-label="${t.prevPage}">‹</button>${pageLinks}<button class="page-button" data-page="${Math.min(pages,currentPage+1)}" aria-label="${t.nextPage}">›</button>`}
  function card(o){const gi=galleryIndex[o.id]||0;const offerHref=window.mazurLocalizedUrl(`../oferta/?id=${encodeURIComponent(o.id)}`);return `<article class="offer-card" data-offer-card="${escapeHtml(o.id)}">${galleryController?galleryController.markup(o):`<div class="gallery" data-offer="${escapeHtml(o.id)}"><img src="${escapeHtml(o.gallery[gi])}" alt="${escapeHtml(o.title)}"><button class="gallery-arrow prev" data-gallery="prev" data-id="${escapeHtml(o.id)}" aria-label="${t.prevPhoto}">‹</button><button class="gallery-arrow next" data-gallery="next" data-id="${escapeHtml(o.id)}" aria-label="${t.nextPhoto}">›</button><div class="gallery-dots">${o.gallery.map((_,i)=>`<span class="${i===gi?'active':''}"></span>`).join('')}</div><span class="gallery-count">▣ ${gi+1}/${o.gallery.length}</span></div>`}<div class="offer-content"><div class="offer-top"><div><span class="price">${money(o.price)}</span><span class="unit-price">${unit(o)}</span></div><button class="favorite" aria-label="${t.favorite}">♡</button></div><h2 lang="${escapeHtml(o.titleLanguage)}"><a href="${escapeHtml(offerHref)}">${escapeHtml(o.title)}</a></h2><p class="address">${escapeHtml(o.location)}</p><div class="details"><span class="detail"><i class="detail-icon">⌂</i>${escapeHtml(o.rooms)}</span><span class="detail"><i class="detail-icon">↗</i>${escapeHtml(o.area)} m²</span><span class="detail"><i class="detail-icon">▦</i>${escapeHtml(o.floor)}</span></div><div class="offer-footer"><span class="offer-kind">${typeLabels[type]} ${transactionLabel}</span></div></div></article>`}
  let swipeStart=null,swipeClickUntil=0;
  list.addEventListener('touchstart',e=>{const gallery=e.target.closest('.gallery[data-offer]');swipeStart=gallery&&e.touches.length===1?{id:gallery.dataset.offer,x:e.touches[0].clientX,y:e.touches[0].clientY}:null},{passive:true});
  list.addEventListener('touchend',e=>{if(!swipeStart||!e.changedTouches.length)return;const start=swipeStart;swipeStart=null;const dx=e.changedTouches[0].clientX-start.x,dy=e.changedTouches[0].clientY-start.y;if(Math.abs(dx)<40||Math.abs(dx)<Math.abs(dy)*1.25)return;swipeClickUntil=Date.now()+500;const offer=offers.find(item=>String(item.id)===start.id);if(!offer)return;const delta=dx<0?1:-1;if(galleryController)void galleryController.step(offer,delta,list);else{galleryIndex[start.id]=((galleryIndex[start.id]||0)+delta+offer.gallery.length)%offer.gallery.length;render()}},{passive:true});
  list.addEventListener('touchcancel',()=>{swipeStart=null},{passive:true});
  list.addEventListener('click',e=>{if(Date.now()<swipeClickUntil){e.preventDefault();return}const arrow=e.target.closest('[data-gallery]');if(arrow){const id=arrow.dataset.id,o=offers.find(x=>String(x.id)===id),delta=arrow.dataset.gallery==='next'?1:-1;if(!o)return;if(galleryController){void galleryController.step(o,delta,list);return}galleryIndex[id]=((galleryIndex[id]||0)+delta+o.gallery.length)%o.gallery.length;render();return}const fav=e.target.closest('.favorite');if(fav){fav.classList.toggle('active');fav.textContent=fav.classList.contains('active')?'♥':'♡';return}if(e.target.closest('a[href]'))return;const card=e.target.closest('[data-offer-card]');if(card)location.href=window.mazurLocalizedUrl(`../oferta/?id=${encodeURIComponent(card.dataset.offerCard)}`)});
  pagination.addEventListener('click',e=>{const b=e.target.closest('[data-page]');if(b){currentPage=Number(b.dataset.page);render();scrollTo({top:0,behavior:'smooth'})}});
  perPage.addEventListener('change',()=>{currentPage=1;render()});sort.addEventListener('change',()=>{currentPage=1;render()});
  const picker=document.getElementById('language-picker');
  if(picker){const trigger=picker.querySelector('.language-trigger');trigger.addEventListener('click',()=>{const open=picker.classList.toggle('open');trigger.setAttribute('aria-expanded',String(open))});picker.querySelectorAll('[data-lang]').forEach(button=>button.addEventListener('click',()=>{params.delete('lang');location.href=window.mazurLocalizedUrl(`../wyniki-wyszukiwania/?${params.toString()}`,button.dataset.lang)}));document.addEventListener('click',e=>{if(!picker.contains(e.target)){picker.classList.remove('open');trigger.setAttribute('aria-expanded','false')}})}
  render();
  void offersPromise.then(()=>{loading=false;updateSummary();render()});
  void galleryPromise.then(()=>{if(!loading)render()});
})();

if (location.protocol === 'file:') {
  document.querySelectorAll('a[href]').forEach(link=>{const href=link.getAttribute('href');if(!href||href.startsWith('#')||/^(?:https?:|mailto:|tel:)/.test(href))return;link.setAttribute('href',href.replace(/\/(\?|#|$)/,'/index.html$1'))});
}

const responsiveStyles=document.createElement('link');responsiveStyles.rel='stylesheet';responsiveStyles.href='../assets/css/responsive.css?v=6';document.head.appendChild(responsiveStyles);

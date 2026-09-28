const MLS_DETAIL_API='https://api.mazurestate.pl/api/mls-offer.php';

const text=(selector,value)=>{const element=document.querySelector(selector);if(element)element.textContent=value};
const categoryFor=value=>{const name=String(value||'').toLocaleLowerCase('pl');if(name.includes('mieszkan'))return 'mieszkania';if(name.includes('dom'))return 'domy';if(name.includes('dział')||name.includes('grunt'))return 'dzialki';if(/lokal|komerc|biuro|magazyn|hala|obiekt/.test(name))return 'lokale';return 'mieszkania'};
const categoryNames={mieszkania:'Mieszkania',domy:'Domy',dzialki:'Działki',lokale:'Lokale komercyjne'};
const polishPlural=(value,one,few,many)=>{const n=Math.abs(Number(value));if(n===1)return one;if(n%10>=2&&n%10<=4&&(n%100<12||n%100>14))return few;return many};
const formatNumber=value=>new Intl.NumberFormat('pl-PL',{maximumFractionDigits:2}).format(Number(value)||0);

function setupGallery(images,title){
  const gallery=document.getElementById('gallery');
  const safeImages=images.length?images:['../assets/images/category-apartments.webp'];
  gallery.innerHTML='';
  safeImages.slice(0,5).forEach((src,index)=>{
    const button=document.createElement('button');
    if(index===0)button.className='gallery-main';
    if(index===4&&safeImages.length>5)button.className='gallery-more';
    const image=document.createElement('img');image.src=src;image.alt=`${title} — zdjęcie ${index+1}`;image.loading=index?'lazy':'eager';
    button.appendChild(image);
    if(index===4&&safeImages.length>5){const label=document.createElement('span');label.textContent=`Zobacz wszystkie zdjęcia · ${safeImages.length}`;button.appendChild(label)}
    gallery.appendChild(button);
  });
  const buttons=[...gallery.querySelectorAll('button')],box=document.getElementById('lightbox'),lightboxImage=box?.querySelector('img'),counter=box?.querySelector('.lb-count');
  if(!box||!lightboxImage||!counter)return;
  let current=0;
  const show=index=>{current=(index+safeImages.length)%safeImages.length;lightboxImage.src=safeImages[current];lightboxImage.alt=`${title} — zdjęcie ${current+1}`;counter.textContent=`${current+1} / ${safeImages.length}`;box.classList.add('open');box.setAttribute('aria-hidden','false');document.body.style.overflow='hidden'};
  const close=()=>{box.classList.remove('open');box.setAttribute('aria-hidden','true');document.body.style.overflow=''};
  buttons.forEach((button,index)=>button.addEventListener('click',()=>show(index)));
  box.querySelector('.close')?.addEventListener('click',close);box.querySelector('.prev')?.addEventListener('click',()=>show(current-1));box.querySelector('.next')?.addEventListener('click',()=>show(current+1));
  box.addEventListener('click',event=>{if(event.target===box)close()});
  document.addEventListener('keydown',event=>{if(event.key==='Escape')close();if(box.classList.contains('open')&&event.key==='ArrowLeft')show(current-1);if(box.classList.contains('open')&&event.key==='ArrowRight')show(current+1)});
}

function renderParameters(offer,category){
  const values=[
    ['Powierzchnia',offer.area?`${formatNumber(offer.area)} m²`:'—'],
    [category==='dzialki'?'Powierzchnia działki':'Liczba pokoi',category==='dzialki'?(offer.plotArea?`${formatNumber(offer.plotArea)} m²`:'—'):(offer.rooms?`${offer.rooms} ${polishPlural(offer.rooms,'pokój','pokoje','pokoi')}`:'—')],
    ['Piętro',offer.floor!==''?(offer.buildingFloors?`${offer.floor} / ${offer.buildingFloors}`:offer.floor):'—'],
    ['Typ nieruchomości',offer.type||'—'],
    ['Rok budowy',offer.buildingYear||'—'],
    ['Numer oferty',offer.number||offer.id]
  ];
  document.querySelectorAll('.parameter-grid article').forEach((article,index)=>{const pair=values[index];article.querySelector('span').textContent=pair[0];article.querySelector('strong').textContent=pair[1]});
}

function renderDescription(offer){
  const section=document.querySelector('.description');if(!section)return;
  section.innerHTML='';
  const eyebrow=document.createElement('p');eyebrow.className='eyebrow';eyebrow.textContent='O NIERUCHOMOŚCI';
  const heading=document.createElement('h2');heading.textContent=offer.title;
  section.append(eyebrow,heading);
  const paragraphs=String(offer.description||'Skontaktuj się z nami, aby poznać szczegóły tej nieruchomości.').split(/\n+/).map(item=>item.trim()).filter(Boolean);
  paragraphs.forEach(content=>{const paragraph=document.createElement('p');paragraph.textContent=content;section.appendChild(paragraph)});
}

function renderFeatures(features){
  const section=document.querySelector('.features'),container=section?.querySelector('div');if(!section||!container)return;
  container.innerHTML='';
  const list=features.length?features:['Szczegóły dostępne u doradcy'];
  list.slice(0,12).forEach(feature=>{const item=document.createElement('span');item.textContent=`✓ ${feature}`;container.appendChild(item)});
}

function showUnavailable(message){
  text('.property-head .eyebrow','OFERTA NIEDOSTĘPNA');text('.property-head h1','Nie udało się wyświetlić oferty');text('.property-head .location',message);
  const price=document.querySelector('.head-price');if(price)price.hidden=true;
  const gallery=document.getElementById('gallery');if(gallery)gallery.hidden=true;
  const layout=document.querySelector('.property-layout');if(layout)layout.hidden=true;
}

async function loadOffer(){
  const params=new URLSearchParams(location.search),id=params.get('id'),lang=['pl','uk','en','ru'].includes(params.get('lang'))?params.get('lang'):'pl';
  document.documentElement.lang=lang;
  document.querySelectorAll('.language-picker a').forEach(link=>{const next=new URL(link.href);const label=link.textContent.trim();next.searchParams.set('id',id||'');next.searchParams.set('lang',label==='UKR'?'uk':label.toLowerCase());link.href=next.toString()});
  if(!id){showUnavailable('Brakuje numeru oferty w adresie strony.');return}
  try{
    const response=await fetch(`${MLS_DETAIL_API}?id=${encodeURIComponent(id)}`,{headers:{Accept:'application/json'}});
    const payload=await response.json();if(!response.ok||!payload.offer)throw new Error(payload.error||'Oferta nie jest dostępna');
    const offer=payload.offer,category=categoryFor(offer.type),isRent=offer.transaction==='wynajem';
    const locationName=[offer.city,offer.district].filter(Boolean).join(', '),locationFull=[locationName,offer.province].filter(Boolean).join(' · ');
    const transactionText=isRent?'na wynajem':'na sprzedaż',eyebrow=`${offer.type} ${transactionText}`.toLocaleUpperCase('pl');
    const currency=offer.currency||'PLN',price=offer.price?`${formatNumber(offer.price)} ${currency}`:'Cena na zapytanie';
    const unit=offer.price&&offer.area?`${formatNumber(Math.round(offer.price/offer.area))} ${currency}/m²`:'';
    document.title=`${offer.title} | MazurEstate`;document.querySelector('meta[name="description"]')?.setAttribute('content',`${offer.title}. ${locationName}. Cena i szczegóły oferty MazurEstate.`);
    text('.property-head .eyebrow',eyebrow);text('.property-head h1',offer.title);text('.property-head .location',locationFull);text('.head-price strong',price);text('.head-price span',[isRent?'miesięcznie':'',unit].filter(Boolean).join(' · '));
    const crumbs=document.querySelectorAll('.breadcrumbs a');if(crumbs[0])crumbs[0].href=`../index.html?lang=${lang}`;if(crumbs[1]){crumbs[1].textContent=`${categoryNames[category]} ${transactionText}`;crumbs[1].href=`../wyniki-wyszukiwania/?type=${category}&transaction=${isRent?'wynajem':'sprzedaz'}&lang=${lang}`};text('.breadcrumbs strong',locationName||offer.title);
    renderParameters(offer,category);renderDescription(offer);renderFeatures(offer.features||[]);setupGallery(offer.images||[],offer.title);window.MazurContactForm?.setOffer({id:offer.id,number:offer.number||offer.id,title:offer.title});
  }catch(error){showUnavailable(error.message||'Spróbuj ponownie za chwilę.')}
}

function setupTestimonials(){const track=document.getElementById('testimonials-track');if(!track)return;const step=()=>{const card=track.querySelector('[data-tcard]');return card?card.getBoundingClientRect().width+24:364};document.getElementById('testi-arrow-left')?.addEventListener('click',()=>track.scrollBy({left:-step(),behavior:'smooth'}));document.getElementById('testi-arrow-right')?.addEventListener('click',()=>track.scrollBy({left:step(),behavior:'smooth'}));setInterval(()=>{const end=track.scrollLeft+track.clientWidth>=track.scrollWidth-4;track.scrollTo({left:end?0:track.scrollLeft+step(),behavior:'smooth'})},10000)}

loadOffer().finally(()=>{
  setupTestimonials();
  const sharedChromeScript=document.createElement('script');sharedChromeScript.src='../assets/js/site-chrome.js';document.body.appendChild(sharedChromeScript);
});

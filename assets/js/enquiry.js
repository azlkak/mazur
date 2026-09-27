(() => {
  const API='https://darkgreen-rabbit-981798.hostingersite.com/api/enquiry.php';
  const params=new URLSearchParams(location.search);
  const lang=['pl','en','uk','ru'].includes(params.get('lang'))?params.get('lang'):'pl';
  const copy={
    pl:{first:'Imię',last:'Nazwisko',phone:'Telefon',email:'E-mail',message:'Wiadomość',send:'Wyślij wiadomość',consent:'Zapoznałem/am się z {privacy} i zgadzam się na kontakt w sprawie mojego zapytania.',privacy:'polityką prywatności',offer:'Zapytanie dotyczące oferty nr {number}',sending:'Wysyłanie…',success:'Dziękujemy. Wiadomość została wysłana — skontaktujemy się z Tobą.',error:'Nie udało się wysłać wiadomości. Spróbuj ponownie za chwilę.',contactRequired:'Podaj numer telefonu lub adres e-mail.'},
    en:{first:'First name',last:'Last name',phone:'Phone',email:'Email',message:'Message',send:'Send message',consent:'I have read the {privacy} and agree to be contacted about my enquiry.',privacy:'privacy policy',offer:'Enquiry about property no. {number}',sending:'Sending…',success:'Thank you. Your message has been sent — we will contact you shortly.',error:'The message could not be sent. Please try again shortly.',contactRequired:'Enter a phone number or email address.'},
    uk:{first:'Ім’я',last:'Прізвище',phone:'Телефон',email:'Електронна пошта',message:'Повідомлення',send:'Надіслати повідомлення',consent:'Я ознайомився(-лась) із {privacy} та погоджуюся на зв’язок щодо мого запиту.',privacy:'політикою конфіденційності',offer:'Запит щодо пропозиції № {number}',sending:'Надсилання…',success:'Дякуємо. Повідомлення надіслано — ми зв’яжемося з вами.',error:'Не вдалося надіслати повідомлення. Спробуйте ще раз пізніше.',contactRequired:'Вкажіть номер телефону або електронну пошту.'},
    ru:{first:'Имя',last:'Фамилия',phone:'Телефон',email:'Электронная почта',message:'Сообщение',send:'Отправить сообщение',consent:'Я ознакомился(-лась) с {privacy} и согласен(-на) на связь по моему запросу.',privacy:'политикой конфиденциальности',offer:'Запрос по предложению № {number}',sending:'Отправка…',success:'Спасибо. Сообщение отправлено — мы свяжемся с вами.',error:'Не удалось отправить сообщение. Попробуйте ещё раз позже.',contactRequired:'Укажите номер телефона или адрес электронной почты.'}
  }[lang];
  let currentOffer=null;
  const privacyPrefix=location.pathname.includes('/oferta/')?'../':'';
  function consentHtml(){const parts=copy.consent.split('{privacy}');return `${parts[0]}<a href="${privacyPrefix}polityka-prywatnosci/?lang=${lang}" target="_blank" rel="noopener">${copy.privacy}</a>${parts[1]}`}
  function render(root){
    const offerId=root.dataset.offerId||params.get('id')||'';
    root.innerHTML=`<form class="mazur-contact" novalidate><p class="mazur-contact__context" ${offerId?'':'hidden'}></p><div class="mazur-contact__grid"><div class="mazur-contact__field"><label>${copy.first} *</label><input name="first_name" autocomplete="given-name" maxlength="80" required></div><div class="mazur-contact__field"><label>${copy.last}</label><input name="last_name" autocomplete="family-name" maxlength="100"></div><div class="mazur-contact__field"><label>${copy.phone}</label><input name="phone" type="tel" autocomplete="tel" maxlength="32"></div><div class="mazur-contact__field"><label>${copy.email}</label><input name="email" type="email" autocomplete="email" maxlength="190"></div><div class="mazur-contact__field mazur-contact__field--wide"><label>${copy.message} *</label><textarea name="message" minlength="10" maxlength="3000" required></textarea></div></div><div class="mazur-contact__trap" aria-hidden="true"><label>Website<input name="website" tabindex="-1" autocomplete="off"></label></div><label class="mazur-contact__consent"><input name="consent" type="checkbox" required><span>${consentHtml()}</span></label><button class="mazur-contact__submit" type="submit">${copy.send}</button><p class="mazur-contact__status" role="status" aria-live="polite"></p></form>`;
    root.dataset.startedAt=String(Date.now());
    updateContext(root,{id:offerId,number:root.dataset.offerNumber||offerId,title:root.dataset.offerTitle||''});
    root.querySelector('form').addEventListener('submit',event=>submit(event,root));
  }
  function initialize(root){
    if(root.dataset.enquiryReady==='1')return;
    root.dataset.enquiryReady='1';render(root);if(currentOffer&&root.dataset.context==='offer')updateContext(root,currentOffer);
  }
  function scan(scope=document){
    if(scope.matches?.('[data-contact-form]'))initialize(scope);
    scope.querySelectorAll?.('[data-contact-form]').forEach(initialize);
  }
  function updateContext(root,offer){
    if(!offer?.id)return;
    root.dataset.offerId=String(offer.id);root.dataset.offerNumber=String(offer.number||offer.id);root.dataset.offerTitle=String(offer.title||'');
    const context=root.querySelector('.mazur-contact__context');if(context){context.hidden=false;context.textContent=copy.offer.replace('{number}',root.dataset.offerNumber)}
  }
  async function submit(event,root){
    event.preventDefault();const form=event.currentTarget,status=form.querySelector('.mazur-contact__status'),button=form.querySelector('button[type="submit"]');
    if(!form.reportValidity())return;
    const data=new FormData(form),phone=String(data.get('phone')||'').trim(),email=String(data.get('email')||'').trim();
    if(!phone&&!email){status.dataset.state='error';status.textContent=copy.contactRequired;form.elements.phone.focus();return}
    const payload={first_name:data.get('first_name'),last_name:data.get('last_name'),phone,email,message:data.get('message'),website:data.get('website'),consent:data.get('consent')==='on',language:lang,source:root.dataset.context||'website',source_url:location.href,offer_id:root.dataset.offerId||'',started_at:Number(root.dataset.startedAt||Date.now())};
    button.disabled=true;button.textContent=copy.sending;status.textContent='';status.dataset.state='';
    try{const response=await fetch(API,{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json'},body:JSON.stringify(payload)});const result=await response.json();if(!response.ok)throw new Error(result.code||'error');form.reset();root.dataset.startedAt=String(Date.now());status.dataset.state='success';status.textContent=copy.success}
    catch(error){status.dataset.state='error';status.textContent=copy.error}
    finally{button.disabled=false;button.textContent=copy.send}
  }
  scan();
  new MutationObserver(records=>records.forEach(record=>record.addedNodes.forEach(node=>{if(node.nodeType===1)scan(node)}))).observe(document.body,{childList:true,subtree:true});
  [100,500,1500,3000].forEach(delay=>setTimeout(()=>scan(),delay));
  window.MazurContactForm={setOffer(offer){currentOffer=offer;document.querySelectorAll('[data-contact-form][data-context="offer"]').forEach(root=>{initialize(root);updateContext(root,offer)})}};
})();

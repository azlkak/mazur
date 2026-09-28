(() => {
  const API='https://api.mazurestate.pl/api/enquiry.php';
  const params=new URLSearchParams(location.search);
  const lang=['pl','en','uk','ru'].includes(params.get('lang'))?params.get('lang'):'pl';
  const copy={
    pl:{first:'Imię',last:'Nazwisko',phone:'Telefon',email:'E-mail',contactLegend:'Dane kontaktowe — wymagany telefon lub e-mail',message:'Wiadomość',send:'Wyślij wiadomość',consent:'Zapoznałem/am się z {privacy} i zgadzam się na kontakt w sprawie mojego zapytania.',privacy:'polityką prywatności',offerLabel:'Zapytanie dotyczące oferty',offerNumber:'Numer oferty: {number}',sending:'Wysyłanie…',success:'Dziękujemy. Wiadomość została wysłana — skontaktujemy się z Tobą.',error:'Nie udało się wysłać wiadomości. Spróbuj ponownie za chwilę.',contactRequired:'Podaj numer telefonu lub adres e-mail.',invalidPhone:'Podaj prawidłowy numer telefonu (co najmniej 6 znaków).',invalidEmail:'Podaj prawidłowy adres e-mail.',rateLimited:'Wysłano zbyt wiele prób. Odczekaj 15 minut i spróbuj ponownie.',invalidTiming:'Odczekaj chwilę i spróbuj wysłać formularz ponownie.'},
    en:{first:'First name',last:'Last name',phone:'Phone',email:'Email',contactLegend:'Contact details — phone or email required',message:'Message',send:'Send message',consent:'I have read the {privacy} and agree to be contacted about my enquiry.',privacy:'privacy policy',offerLabel:'Enquiry about this property',offerNumber:'Property no. {number}',sending:'Sending…',success:'Thank you. Your message has been sent — we will contact you shortly.',error:'The message could not be sent. Please try again shortly.',contactRequired:'Enter a phone number or email address.',invalidPhone:'Enter a valid phone number (at least 6 characters).',invalidEmail:'Enter a valid email address.',rateLimited:'Too many attempts. Wait 15 minutes and try again.',invalidTiming:'Wait a moment and try sending the form again.'},
    uk:{first:'Ім’я',last:'Прізвище',phone:'Телефон',email:'Електронна пошта',contactLegend:'Контактні дані — потрібен телефон або e-mail',message:'Повідомлення',send:'Надіслати повідомлення',consent:'Я ознайомився(-лась) із {privacy} та погоджуюся на зв’язок щодо мого запиту.',privacy:'політикою конфіденційності',offerLabel:'Запит щодо цієї пропозиції',offerNumber:'Номер пропозиції: {number}',sending:'Надсилання…',success:'Дякуємо. Повідомлення надіслано — ми зв’яжемося з вами.',error:'Не вдалося надіслати повідомлення. Спробуйте ще раз пізніше.',contactRequired:'Вкажіть номер телефону або електронну пошту.',invalidPhone:'Вкажіть правильний номер телефону (щонайменше 6 символів).',invalidEmail:'Вкажіть правильну електронну адресу.',rateLimited:'Забагато спроб. Зачекайте 15 хвилин і спробуйте ще раз.',invalidTiming:'Зачекайте мить і спробуйте надіслати форму ще раз.'},
    ru:{first:'Имя',last:'Фамилия',phone:'Телефон',email:'Электронная почта',contactLegend:'Контактные данные — требуется телефон или e-mail',message:'Сообщение',send:'Отправить сообщение',consent:'Я ознакомился(-лась) с {privacy} и согласен(-на) на связь по моему запросу.',privacy:'политикой конфиденциальности',offerLabel:'Запрос по этому предложению',offerNumber:'Номер предложения: {number}',sending:'Отправка…',success:'Спасибо. Сообщение отправлено — мы свяжемся с вами.',error:'Не удалось отправить сообщение. Попробуйте ещё раз позже.',contactRequired:'Укажите номер телефона или адрес электронной почты.',invalidPhone:'Укажите правильный номер телефона (не менее 6 символов).',invalidEmail:'Укажите правильный адрес электронной почты.',rateLimited:'Слишком много попыток. Подождите 15 минут и попробуйте снова.',invalidTiming:'Подождите немного и попробуйте отправить форму ещё раз.'}
  }[lang];
  const validationCopy={
    pl:{first:'Wpisz imię.',last:'Wpisz nazwisko.',message:'Wpisz wiadomość — co najmniej 10 znaków.',consent:'Zaznacz zgodę, aby wysłać wiadomość.'},
    en:{first:'Enter your first name.',last:'Enter your last name.',message:'Enter a message of at least 10 characters.',consent:'Accept the consent to send the message.'},
    uk:{first:'Вкажіть ім’я.',last:'Вкажіть прізвище.',message:'Введіть повідомлення щонайменше з 10 символів.',consent:'Підтвердьте згоду, щоб надіслати повідомлення.'},
    ru:{first:'Укажите имя.',last:'Укажите фамилию.',message:'Введите сообщение не менее чем из 10 символов.',consent:'Подтвердите согласие, чтобы отправить сообщение.'}
  }[lang];
  let currentOffer=null;
  const privacyPrefix=location.pathname.includes('/oferta/')?'../':'';
  function consentHtml(){const parts=copy.consent.split('{privacy}');return `${parts[0]}<a href="${privacyPrefix}polityka-prywatnosci/?lang=${lang}" target="_blank" rel="noopener">${copy.privacy}</a>${parts[1]}`}
  function render(root){
    const offerId=root.dataset.offerId||params.get('id')||'';
    root.innerHTML=`<form class="mazur-contact" novalidate><div class="mazur-contact__context" ${offerId?'':'hidden'}><span class="mazur-contact__context-label"></span><strong class="mazur-contact__context-title"></strong><span class="mazur-contact__context-number"></span></div><div class="mazur-contact__grid"><div class="mazur-contact__field"><label>${copy.first} <span aria-hidden="true">*</span></label><input name="first_name" autocomplete="given-name" maxlength="80" required></div><div class="mazur-contact__field"><label>${copy.last} <span aria-hidden="true">*</span></label><input name="last_name" autocomplete="family-name" maxlength="100" required></div><p class="mazur-contact__contact-note mazur-contact__field--wide">${copy.contactLegend} <span aria-hidden="true">*</span></p><div class="mazur-contact__field"><label>${copy.phone}</label><input name="phone" type="tel" autocomplete="tel" inputmode="tel" minlength="6" maxlength="32" pattern="[0-9+() .-]{6,32}"></div><div class="mazur-contact__field"><label>${copy.email}</label><input name="email" type="email" autocomplete="email" maxlength="190"></div><div class="mazur-contact__field mazur-contact__field--wide"><label>${copy.message} <span aria-hidden="true">*</span></label><textarea name="message" minlength="10" maxlength="3000" required></textarea></div></div><div class="mazur-contact__trap" aria-hidden="true"><label>Website<input name="website" tabindex="-1" autocomplete="off"></label></div><label class="mazur-contact__consent"><input name="consent" type="checkbox" required><span>${consentHtml()} <strong aria-hidden="true">*</strong></span></label><button class="mazur-contact__submit" type="submit">${copy.send}</button><p class="mazur-contact__status" role="status" aria-live="polite"></p></form>`;
    root.dataset.startedAt=String(Date.now());
    updateContext(root,{id:offerId,number:root.dataset.offerNumber||offerId,title:root.dataset.offerTitle||''});
    const form=root.querySelector('form');
    form.addEventListener('submit',event=>submit(event,root));
    form.addEventListener('input',()=>clearHelp(form));
    form.addEventListener('change',()=>clearHelp(form));
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
    const context=root.querySelector('.mazur-contact__context');if(context){context.hidden=false;context.querySelector('.mazur-contact__context-label').textContent=copy.offerLabel;const title=context.querySelector('.mazur-contact__context-title');title.textContent=root.dataset.offerTitle;title.hidden=!root.dataset.offerTitle;context.querySelector('.mazur-contact__context-number').textContent=copy.offerNumber.replace('{number}',root.dataset.offerNumber)}
  }
  function clearHelp(form){
    form.querySelectorAll('.mazur-contact__help').forEach(element=>element.remove());
    form.querySelectorAll('[aria-invalid="true"]').forEach(element=>{element.removeAttribute('aria-invalid');element.removeAttribute('aria-describedby')});
  }
  function showHelp(form,field,message){
    clearHelp(form);
    const anchor=field.closest('.mazur-contact__field,.mazur-contact__consent');
    const help=document.createElement('span');
    const id=`contact-help-${Math.random().toString(36).slice(2)}`;
    help.className='mazur-contact__help';help.id=id;help.setAttribute('role','alert');help.textContent=message;
    field.setAttribute('aria-invalid','true');field.setAttribute('aria-describedby',id);anchor.append(help);field.focus({preventScroll:false});
    return false;
  }
  function validate(form){
    const first=form.elements.first_name,last=form.elements.last_name,phone=form.elements.phone,email=form.elements.email,message=form.elements.message,consent=form.elements.consent;
    const phoneValue=phone.value.trim(),emailValue=email.value.trim();
    if(!first.value.trim())return showHelp(form,first,validationCopy.first);
    if(!last.value.trim())return showHelp(form,last,validationCopy.last);
    if(!phoneValue&&!emailValue)return showHelp(form,phone,copy.contactRequired);
    if(phoneValue&&!/^[0-9+() .-]{6,32}$/.test(phoneValue))return showHelp(form,phone,copy.invalidPhone);
    if(emailValue&&!email.validity.valid)return showHelp(form,email,copy.invalidEmail);
    if(message.value.trim().length<10)return showHelp(form,message,validationCopy.message);
    if(!consent.checked)return showHelp(form,consent,validationCopy.consent);
    clearHelp(form);return true;
  }
  async function submit(event,root){
    event.preventDefault();const form=event.currentTarget,status=form.querySelector('.mazur-contact__status'),button=form.querySelector('button[type="submit"]');
    status.textContent='';status.dataset.state='';
    if(!validate(form))return;
    const data=new FormData(form),phone=String(data.get('phone')||'').trim(),email=String(data.get('email')||'').trim();
    const payload={first_name:data.get('first_name'),last_name:data.get('last_name'),phone,email,message:data.get('message'),website:data.get('website'),consent:data.get('consent')==='on',language:lang,source:root.dataset.context||'website',source_url:location.href,offer_id:root.dataset.offerId||'',offer_number:root.dataset.offerNumber||'',offer_title:root.dataset.offerTitle||'',started_at:Number(root.dataset.startedAt||Date.now())};
    button.disabled=true;button.textContent=copy.sending;
    try{const response=await fetch(API,{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-Requested-With':'MazurEstateContact'},body:JSON.stringify(payload)});const result=await response.json();if(!response.ok)throw new Error(result.code||'error');form.reset();root.dataset.startedAt=String(Date.now());status.dataset.state='success';status.textContent=copy.success}
    catch(error){const messages={invalid_phone:copy.invalidPhone,invalid_email:copy.invalidEmail,missing_contact:copy.contactRequired,rate_limited:copy.rateLimited,invalid_timing:copy.invalidTiming};status.dataset.state='error';status.textContent=messages[error.message]||copy.error}
    finally{button.disabled=false;button.textContent=copy.send}
  }
  scan();
  new MutationObserver(records=>records.forEach(record=>record.addedNodes.forEach(node=>{if(node.nodeType===1)scan(node)}))).observe(document.body,{childList:true,subtree:true});
  [100,500,1500,3000].forEach(delay=>setTimeout(()=>scan(),delay));
  window.MazurContactForm={setOffer(offer){currentOffer=offer;document.querySelectorAll('[data-contact-form][data-context="offer"]').forEach(root=>{initialize(root);updateContext(root,offer)})}};
})();

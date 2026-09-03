(() => {
  const key='mazurestate-cookie-consent';
  if(localStorage.getItem(key)) return;
  const lang=new URLSearchParams(location.search).get('lang')||document.documentElement.lang||'pl';
  const copy={
    pl:['Dbamy o Twoją prywatność','Używamy niezbędnych plików cookies, a za Twoją zgodą również narzędzi analitycznych i marketingowych.','Polityka cookies','Odrzuć','Akceptuję'],
    en:['Your privacy matters','We use necessary cookies and, with your consent, analytics and marketing tools.','Cookie policy','Reject','Accept'],
    uk:['Ми дбаємо про Вашу конфіденційність','Ми використовуємо необхідні cookies, а за Вашою згодою — аналітичні та маркетингові інструменти.','Політика cookies','Відхилити','Прийняти'],
    ru:['Мы заботимся о вашей конфиденциальности','Мы используем необходимые cookies, а с вашего согласия — аналитические и маркетинговые инструменты.','Политика cookies','Отклонить','Принять']
  }[['pl','en','uk','ru'].includes(lang)?lang:'pl'];
  const onHome=!location.pathname.split('/').filter(Boolean).at(-1)||location.pathname.endsWith('/mazur/')||location.pathname.endsWith('/index.html');
  const policy=`${onHome?'':'../'}polityka-cookies/?lang=${lang}`;
  const banner=document.createElement('aside');
  banner.className='cookie-consent';banner.setAttribute('role','dialog');banner.setAttribute('aria-label',copy[0]);
  banner.innerHTML=`<div class="cookie-consent__copy"><strong>${copy[0]}</strong><p>${copy[1]} <a href="${policy}">${copy[2]}</a></p></div><div class="cookie-consent__actions"><button type="button" data-cookie="reject">${copy[3]}</button><button type="button" data-cookie="accept">${copy[4]}</button></div>`;
  banner.addEventListener('click',event=>{const button=event.target.closest('[data-cookie]');if(!button)return;localStorage.setItem(key,button.dataset.cookie);window.dispatchEvent(new CustomEvent('mazurestate:cookie-consent',{detail:{choice:button.dataset.cookie}}));banner.remove()});
  document.body.appendChild(banner);
})();

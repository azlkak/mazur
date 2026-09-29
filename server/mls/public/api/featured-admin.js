(() => {
  const $ = id => document.getElementById(id);
  const endpoint = 'featured-admin.php';
  let csrf = '';
  let selected = [];
  let catalog = [];
  let hidden = [];
  let inactive = new Set();
  const notice = (message, error = false) => { $('message').textContent = message; $('message').classList.toggle('error', error); };
  async function post(action, details = {}) {
    const response = await fetch(endpoint, {method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({action, csrf, ...details})});
    const data = await response.json();
    if (!response.ok) {
      const error = new Error(data.error || 'Wystąpił błąd');
      error.retryAfter = Number(data.retry_after) || 0;
      throw error;
    }
    return data;
  }
  function text(parent, tag, value) { const node = document.createElement(tag); node.textContent = value; parent.append(node); return node; }
  function offerLabel(offer, id) {
    return offer ? `${offer.title || 'Oferta'} · ${offer.city || 'bez miejscowości'} · nr ${offer.number || id}` : `Oferta MLS ${id}`;
  }
  function renderSelected() {
    const list = $('selected'); list.replaceChildren(); $('count').textContent = `(${selected.length}/10)`;
    for (const [position, id] of selected.entries()) {
      const offer = catalog.find(item => String(item.id) === id);
      const li = document.createElement('li');
      if (inactive.has(id)) li.classList.add('inactive');
      const info = document.createElement('div'); text(info, 'strong', offerLabel(offer, id));
      text(info, 'span', inactive.has(id) ? 'Oferta wycofana — nie jest wyświetlana' : `Pozycja ${position + 1}`);
      const actions = document.createElement('div'); actions.className = 'actions';
      for (const [label, delta] of [['↑', -1], ['↓', 1]]) {
        const button = text(actions, 'button', label); button.type = 'button'; button.setAttribute('aria-label', delta < 0 ? 'Przesuń w górę' : 'Przesuń w dół');
        button.disabled = position + delta < 0 || position + delta >= selected.length;
        button.onclick = () => { [selected[position], selected[position + delta]] = [selected[position + delta], selected[position]]; renderSelected(); };
      }
      const remove = text(actions, 'button', 'Usuń z wybranych'); remove.type = 'button'; remove.className = 'quiet';
      remove.onclick = () => { selected = selected.filter(value => value !== id); inactive.delete(id); renderSelected(); renderResults(); };
      li.append(info, actions); list.append(li);
    }
  }
  function renderResults() {
    const query = $('search').value.trim().toLocaleLowerCase('pl');
    const list = $('results'); list.replaceChildren();
    if (query.length < 2) { $('search-hint').textContent = 'Wpisz przynajmniej 2 znaki.'; return; }
    const matches = catalog.filter(offer => [offer.number, offer.id, offer.title, offer.city, offer.district].some(value => String(value || '').toLocaleLowerCase('pl').includes(query))).slice(0, 30);
    $('search-hint').textContent = matches.length ? `Pierwsze ${matches.length} wyników` : 'Nie znaleziono oferty.';
    for (const offer of matches) {
      const id = String(offer.id); const li = document.createElement('li');
      text(li, 'span', offerLabel(offer, id));
      const actions = document.createElement('div'); actions.className = 'actions';
      const canFeature = !id.startsWith('esti-');
      const add = text(actions, 'button', !canFeature ? 'Tylko MLS w wybranych' : selected.includes(id) ? 'Dodano' : 'Do wybranych'); add.type = 'button'; add.disabled = !canFeature || selected.includes(id) || selected.length >= 10;
      add.onclick = () => { selected.push(id); renderSelected(); renderResults(); };
      const hide = text(actions, 'button', 'Ukryj z portalu'); hide.type = 'button'; hide.className = 'danger';
      hide.onclick = async () => {
        if (!window.confirm(`Ukryć ofertę nr ${offer.number || id} na całym portalu? Możesz ją później przywrócić w tym panelu.`)) return;
        hide.disabled = true;
        try { await post('hide', {id}); notice('Oferta ukryta na portalu. Możesz ją przywrócić poniżej.'); await initialize(); }
        catch { notice('Nie udało się ukryć oferty. Spróbuj ponownie.', true); hide.disabled = false; }
      };
      li.append(actions);
      list.append(li);
    }
  }
  function renderHidden() {
    $('hidden-count').textContent = `(${hidden.length})`;
    const list = $('hidden'); list.replaceChildren();
    if (!hidden.length) { text(list, 'p', 'Nie ma ukrytych ofert.'); return; }
    for (const offer of hidden) {
      const li = document.createElement('li');
      const info = document.createElement('div');
      text(info, 'strong', `${offer.title || 'Oferta'} · nr ${offer.offer_number || offer.id}`);
      text(info, 'span', offer.source === 'esticrm' ? 'EstiCRM' : 'MLS');
      const restore = text(li, 'button', 'Przywróć'); restore.type = 'button'; restore.className = 'quiet';
      restore.onclick = async () => {
        restore.disabled = true;
        try { await post('restore', {id: offer.id}); notice('Oferta przywrócona na portalu.'); await initialize(); }
        catch { notice('Nie udało się przywrócić oferty. Spróbuj ponownie.', true); restore.disabled = false; }
      };
      li.prepend(info); list.append(li);
    }
  }
  async function initialize() {
    const response = await fetch(endpoint + '?action=status', {credentials: 'same-origin', cache: 'no-store'});
    const state = await response.json();
    $('login').hidden = state.authorized; $('editor').hidden = !state.authorized;
    if (!state.authorized) return;
    csrf = state.csrf; $('account').textContent = state.email;
    selected = state.selected.map(row => String(row.source_id));
    hidden = state.hidden || [];
    inactive = new Set(state.selected.filter(row => Number(row.publishable) !== 1).map(row => String(row.source_id)));
    renderSelected(); renderHidden();
    try {
      const response = await fetch('mls-test.php', {cache: 'no-store'});
      if (!response.ok) throw new Error();
      catalog = (await response.json()).offers || [];
      renderSelected(); renderResults();
    } catch { notice('Nie udało się pobrać katalogu ofert. Odśwież stronę.', true); }
  }
  $('email-form').onsubmit = async event => {
    event.preventDefault();
    const button = $('email-form').querySelector('button');
    button.disabled = true;
    try { await post('request-code', {email: $('email').value}); $('code-form').hidden = false; notice('Jeśli adres ma dostęp, kod został wysłany. Sprawdź pocztę.'); }
    catch (error) {
      if (error.message === 'wait_before_retry') notice(`Za dużo próśb o kod. Spróbuj ponownie za ${Math.ceil(error.retryAfter / 60)} min.`, true);
      else notice('Nie udało się wysłać kodu. Spróbuj później.', true);
    }
    finally { button.disabled = false; }
  };
  $('code-form').onsubmit = async event => {
    event.preventDefault();
    try { await post('verify-code', {code: $('code').value}); notice('Zalogowano.'); await initialize(); }
    catch { notice('Kod jest nieprawidłowy lub wygasł.', true); }
  };
  $('search').oninput = renderResults;
  $('save').onclick = async () => {
    $('save').disabled = true;
    try { await post('save', {ids: selected}); notice('Zapisano. Zmiana pojawi się na stronie w ciągu kilku minut.'); await initialize(); }
    catch (error) { notice(error.message === 'offer_unavailable' ? 'Jedna z ofert nie jest już dostępna. Usuń ją z listy.' : 'Nie udało się zapisać zmian.', true); }
    finally { $('save').disabled = false; }
  };
  $('logout').onclick = async () => { try { await post('logout'); location.reload(); } catch { notice('Nie udało się wylogować.', true); } };
  initialize().catch(() => notice('Panel jest chwilowo niedostępny.', true));
})();

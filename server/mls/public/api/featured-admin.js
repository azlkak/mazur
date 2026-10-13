(() => {
  const $ = id => document.getElementById(id);
  const endpoint = 'featured-admin.php';
  const tabs = ['integration', 'translations', 'offers'];
  const tabFromHash = () => tabs.includes(location.hash.slice(1)) ? location.hash.slice(1) : 'integration';
  let activeTab = tabFromHash();
  let csrf = '';
  let selected = [];
  let catalog = [];
  let hidden = [];
  let inactive = new Set();
  let integrationPending = false;
  let translationPending = false;
  let monitoringGeneration = 0;
  let integrationController = null;
  let translationController = null;
  let translationRequestVersion = 0;
  let integrationRequestVersion = 0;
  let translationRetryPending = false;
  let translationRetryNeedsRefresh = false;
  let translationRetryConfirmation = null;
  let latestTranslationStatus = null;
  const languages = [['en', 'Angielski'], ['uk', 'Ukraiński'], ['ru', 'Rosyjski']];
  const queueStates = [['pending', 'Oczekujące'], ['processing', 'W trakcie'], ['retry', 'Do ponowienia'], ['failed', 'Z błędem'], ['done', 'Zakończone']];
  const count = value => Math.max(0, Math.floor(Number(value) || 0));
  const formatCount = value => count(value).toLocaleString('pl-PL');
  const notice = (message, error = false) => { $('message').textContent = message; $('message').classList.toggle('error', error); };
  const dateTime = value => value ? new Intl.DateTimeFormat('pl-PL', {dateStyle: 'short', timeStyle: 'short', timeZone: 'Europe/Warsaw'}).format(Number(value) * 1000) : 'Brak danych';
  async function post(action, details = {}) {
    const response = await fetch(endpoint, {method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({action, csrf, ...details})});
    const data = await response.json();
    if (!response.ok) {
      const error = new Error(data.error || 'Wystąpił błąd');
      error.retryAfter = Number(data.retry_after) || 0;
      error.status = response.status;
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
      const canFeature = /^\d+$/.test(id);
      const add = text(actions, 'button', !canFeature ? 'Tylko MLS w wybranych' : selected.includes(id) ? 'Dodano' : 'Do wybranych'); add.type = 'button'; add.disabled = !canFeature || selected.includes(id) || selected.length >= 10;
      add.onclick = () => { selected.push(id); renderSelected(); renderResults(); };
      const hide = text(actions, 'button', 'Ukryj z portalu'); hide.type = 'button'; hide.className = 'danger';
      let hideConfirmed = false;
      hide.onclick = async () => {
        if (!hideConfirmed) {
          hideConfirmed = true;
          hide.textContent = 'Potwierdź ukrycie';
          return;
        }
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
      text(info, 'span', offer.source === 'esticrm' ? 'EstiCRM' : offer.source === 'vixcrm' ? 'VixCRM' : 'MLS');
      const restore = text(li, 'button', 'Przywróć'); restore.type = 'button'; restore.className = 'quiet';
      restore.onclick = async () => {
        restore.disabled = true;
        try { await post('restore', {id: offer.id}); notice('Oferta przywrócona na portalu.'); await initialize(); }
        catch { notice('Nie udało się przywrócić oferty. Spróbuj ponownie.', true); restore.disabled = false; }
      };
      li.prepend(info); list.append(li);
    }
  }
  function renderIntegration(data) {
    $('integration-intro').textContent = `Stan z ${dateTime(data.checked_at)} · paczki w archiwum są przechowywane ${data.retention_days} dni. Dane odświeżają się przy wejściu w zakładkę lub kliknięciu „Odśwież”.`;
    const cards = $('integration-cards'); cards.replaceChildren();
    const labels = {ok: 'Importer działa', running: 'Import trwa', quiet: 'Brak nowych paczek', stale: 'Sprawdź harmonogram', error: 'Wymaga uwagi', unknown: 'Brak telemetrii', disabled: 'Niewłączone'};
    for (const [source, name] of [['mls', 'MLS'], ['esticrm', 'EstiCRM'], ['vixcrm', 'VixCRM']]) {
      const feed = data.feeds[source];
      if (!feed) continue;
      const card = document.createElement('article'); card.className = 'integration-card';
      const heading = document.createElement('div'); heading.className = 'integration-card-header';
      text(heading, 'h3', name);
      const badge = text(heading, 'span', labels[feed.state] || 'Stan nieznany'); badge.className = `integration-badge ${feed.state}`;
      card.append(heading);
      const descriptions = {
        ok: 'Ostatnie uruchomienie importera zakończyło się bez błędu.',
        running: 'Importer rozpoczął pracę. Odśwież, aby sprawdzić wynik.',
        quiet: feed.batches.length ? 'Importer działa, ale od ponad trzech godzin nie zaimportował nowej paczki MLS. Sprawdź wysyłkę FTP, jeśli oczekujesz aktualizacji co godzinę.' : 'Importer działa, lecz nie ma jeszcze zaimportowanych paczek MLS.',
        stale: feed.state_reason === 'incoming' ? 'Paczka oczekuje ponad dwie godziny na import.'
          : feed.state_reason === 'processing' ? 'Paczka pozostaje w trakcie przetwarzania ponad 90 minut.'
          : 'Od ponad dwóch godzin nie ma potwierdzenia uruchomienia importera.',
        error: feed.last_error || 'W katalogu są paczki wymagające sprawdzenia.',
        unknown: 'Nie ma jeszcze danych o uruchomieniach. Historia paczek jest dostępna poniżej.',
        disabled: 'Import tego źródła nie jest włączony.'
      };
      text(card, 'p', descriptions[feed.state] || 'Nie udało się ustalić stanu.');
      const details = document.createElement('dl');
      for (const [label, value] of [
        ['Ostatnie uruchomienie', dateTime(feed.last_run_at)],
        ['Ostatni import', dateTime(feed.batches[0]?.imported_at)],
        ['Aktywne w imporcie', `${feed.offers_publishable} / ${feed.offers_total}`],
        ...(source === 'vixcrm' ? [] : [
          ['Oczekujące / w trakcie', `${feed.incoming} / ${feed.processing}`],
          ['Paczki z błędem', String(feed.errors)]
        ])
      ]) { text(details, 'dt', label); text(details, 'dd', value); }
      card.append(details); cards.append(card);
    }
    const batches = $('integration-batches'); batches.replaceChildren();
    const recent = Object.entries(data.feeds).flatMap(([source, feed]) => feed.batches.map(batch => ({...batch, source})))
      .sort((a, b) => b.imported_at - a.imported_at).slice(0, 8);
    if (!recent.length) { text(batches, 'li', 'Nie ma jeszcze zaimportowanych paczek.'); return; }
    for (const batch of recent) {
      const item = document.createElement('li'); const info = document.createElement('div');
      text(info, 'strong', `${batch.source === 'mls' ? 'MLS' : batch.source === 'vixcrm' ? 'VixCRM' : 'EstiCRM'} · ${batch.file_name}`);
      text(info, 'span', `${dateTime(batch.imported_at)} · ${batch.export_type === 'full' ? 'pełna' : 'przyrostowa'} · ${batch.offer_count} rekordów`);
      item.append(info); batches.append(item);
    }
  }
  function translationActionNotice(message, error = false) {
    $('translation-action-message').textContent = message;
    $('translation-action-message').classList.toggle('error', error);
  }
  function updateTranslationControls() {
    $('translation-refresh').disabled = translationRetryPending || translationPending;
    $('integration-refresh').disabled = translationRetryPending || integrationPending;
    for (const button of $('translation-cards').querySelectorAll('[data-translation-retry]')) button.disabled = translationRetryPending || translationRetryNeedsRefresh;
  }
  function clearTranslationConfirmation() {
    if (!translationRetryConfirmation) return;
    translationRetryConfirmation = null;
    if (latestTranslationStatus) renderTranslationStatus(latestTranslationStatus);
  }
  function renderTranslationRetryControls(card, queue, queueKey, name) {
    const failed = count(queue.counts?.failed);
    if (!failed) return;
    const controls = document.createElement('div'); controls.className = 'translation-retry-controls';
    const confirming = translationRetryConfirmation?.queue === queueKey && translationRetryConfirmation.failed === failed;
    const button = (label, handler, className = '') => {
      const node = text(controls, 'button', label); node.type = 'button'; node.className = className;
      node.setAttribute('data-translation-retry', ''); node.setAttribute('aria-label', `${label}: ${name}`);
      node.disabled = translationRetryPending || translationRetryNeedsRefresh; node.onclick = handler; return node;
    };
    if (confirming) {
      const limit = count(latestTranslationStatus?.max_jobs_per_run) || 30;
      const explanation = text(controls, 'p', `Ponowimy dokładnie ${formatCount(failed)} zadań ze statusem „Z błędem” w kolejce „${name}”. Ponowne tłumaczenie korzysta z płatnego API OpenAI. Limit pozostaje ${formatCount(limit)} zadań na przebieg.`);
      explanation.id = 'translation-retry-confirmation-' + queueKey;
      const confirm = button('Potwierdź ponowienie', () => retryTranslations(queueKey, failed));
      confirm.setAttribute('aria-describedby', explanation.id);
      button('Anuluj', clearTranslationConfirmation, 'quiet');
    } else {
      button('Ponów błędne', () => {
        if (translationRetryPending || translationRetryNeedsRefresh || $('editor').hidden || activeTab !== 'translations' || document.hidden) return;
        translationRetryConfirmation = {queue: queueKey, failed};
        translationActionNotice('');
        renderTranslationStatus(latestTranslationStatus);
        $('translation-retry-confirmation-' + queueKey)?.nextElementSibling?.focus();
      }, 'quiet');
    }
    card.append(controls);
  }
  async function retryTranslations(queue, expectedFailed) {
    if (translationRetryPending || translationRetryNeedsRefresh || $('editor').hidden || activeTab !== 'translations' || document.hidden) return;
    const current = latestTranslationStatus?.queues?.[queue];
    if (!current?.available || count(current.counts?.failed) !== expectedFailed || translationRetryConfirmation?.queue !== queue || translationRetryConfirmation.failed !== expectedFailed) {
      clearTranslationConfirmation();
      translationActionNotice('Stan kolejki się zmienił. Odśwież dane i potwierdź ponowienie jeszcze raz.', true);
      loadTranslationStatus(); return;
    }
    const generation = monitoringGeneration;
    translationRetryPending = true;
    translationRetryNeedsRefresh = true;
    translationRetryConfirmation = null;
    abortMonitoringRequests();
    renderTranslationStatus(latestTranslationStatus);
    updateTranslationControls();
    translationActionNotice('Dodaję błędne zadania ponownie do kolejki…');
    try {
      const result = await post('retry-translations', {queue, expected_failed: expectedFailed, confirmed: true});
      if (generation !== monitoringGeneration || $('editor').hidden) return;
      translationActionNotice(`Dodano ponownie ${formatCount(result.requeued)} zadań do kolejki. Translator wykona je automatycznie w dotychczasowym limicie.`);
    } catch (error) {
      if (generation !== monitoringGeneration || $('editor').hidden) return;
      if (error.status === 401 || error.message === 'login_required') { monitoringSessionExpired(); return; }
      if (error.message === 'invalid_csrf') {
        monitoringSessionExpired(); notice('Dane sesji są nieaktualne. Zaloguj się ponownie przed ponowieniem tłumaczeń.', true); return;
      }
      const messages = {
        translation_busy: 'Translator właśnie pracuje. Spróbuj ponowić błędne zadania za chwilę.',
        queue_changed: 'Liczba błędnych zadań zmieniła się. Sprawdź odświeżone dane i potwierdź ponowienie jeszcze raz.',
        unavailable: 'Kolejka jest chwilowo niedostępna. Spróbuj odświeżyć dane za chwilę.'
      };
      translationActionNotice(messages[error.message] || (error.status === 503 ? messages.unavailable : 'Nie udało się potwierdzić wyniku ponowienia. Sprawdź odświeżoną kolejkę przed kolejną próbą.'), true);
    } finally {
      if (generation === monitoringGeneration) {
        translationRetryPending = false; updateTranslationControls();
        if (!$('editor').hidden && !document.hidden) loadTranslationStatus();
      }
    }
  }
  function renderTranslationQueue(parent, queue, queueKey, name, description) {
    const card = document.createElement('article'); card.className = 'integration-card translation-card';
    const heading = document.createElement('div'); heading.className = 'integration-card-header';
    text(heading, 'h3', name);
    const totals = queue?.counts || {};
    const waiting = count(totals.pending) + count(totals.processing) + count(totals.retry);
    const status = !queue?.available ? 'unknown' : count(totals.failed) ? 'error' : waiting ? 'running' : 'ok';
    const badge = text(heading, 'span', !queue?.available ? 'Brak danych' : count(totals.failed) ? 'Wymaga uwagi' : waiting ? 'W kolejce' : 'Kolejka pusta');
    badge.className = `integration-badge ${status}`;
    card.append(heading);
    text(card, 'p', description);
    if (!queue?.available) {
      text(card, 'p', 'Dane tej kolejki są chwilowo niedostępne. Odśwież, aby sprawdzić ponownie.');
      parent.append(card); return;
    }
    const metrics = document.createElement('dl'); metrics.className = 'translation-metrics';
    for (const [state, label] of queueStates) {
      const metric = document.createElement('div'); metric.className = `translation-metric ${state}`;
      text(metric, 'dt', label); text(metric, 'dd', formatCount(totals[state])); metrics.append(metric);
    }
    card.append(metrics);
    if (count(queue.stale_done)) text(card, 'p', `W tym ${formatCount(queue.stale_done)} pominiętych, nieaktualnych zadań.`).className = 'translation-note';
    renderTranslationRetryControls(card, queue, queueKey, name);
    const table = document.createElement('table'); table.className = 'translation-language-table';
    text(table, 'caption', 'Zadania według języka');
    const thead = document.createElement('thead'); const headings = document.createElement('tr');
    for (const label of ['Język', 'Do zrobienia', 'Z błędem', 'Zakończone']) {
      const th = text(headings, 'th', label); th.scope = 'col';
    }
    thead.append(headings); table.append(thead);
    const tbody = document.createElement('tbody');
    for (const [language, label] of languages) {
      const counts = queue.languages?.[language] || {};
      const row = document.createElement('tr'); const th = text(row, 'th', label); th.scope = 'row';
      text(row, 'td', formatCount(count(counts.pending) + count(counts.processing) + count(counts.retry)));
      text(row, 'td', formatCount(counts.failed)); text(row, 'td', formatCount(counts.done)); tbody.append(row);
    }
    table.append(tbody); card.append(table);
    text(card, 'p', '„Do zrobienia” łączy oczekujące, wykonywane i przeznaczone do ponowienia.').className = 'translation-note';
    const activity = document.createElement('dl'); activity.className = 'translation-activity';
    for (const [label, value] of [['Ostatnia aktywność', queue.last_activity_at], ['Ostatnie zakończenie', queue.last_completed_at]]) {
      text(activity, 'dt', label); text(activity, 'dd', dateTime(value));
    }
    card.append(activity); parent.append(card);
  }
  function renderTranslationStatus(data) {
    const retryFocusLabel = $('translation-cards').contains(document.activeElement) ? document.activeElement?.getAttribute('aria-label') : null;
    latestTranslationStatus = data;
    if (translationRetryConfirmation) {
      const queue = data.queues?.[translationRetryConfirmation.queue];
      if (!queue?.available || count(queue.counts?.failed) !== translationRetryConfirmation.failed) {
        translationRetryConfirmation = null;
        translationActionNotice('Stan kolejki się zmienił. Sprawdź aktualną liczbę błędów i potwierdź ponowienie jeszcze raz.');
      }
    }
    $('translation-intro').classList.remove('error');
    $('translation-intro').textContent = `Stan z ${dateTime(data.checked_at)} · odświeżanie przy wejściu w zakładkę lub kliknięciu „Odśwież”. Limit: ${formatCount(data.max_jobs_per_run)} zadań na przebieg.`;
    const cards = $('translation-cards'); cards.replaceChildren();
    renderTranslationQueue(cards, data.queues?.offers, 'offers', 'Tytuły i opisy ofert', 'Nowe i zmienione fragmenty ofert tłumaczymy na trzy języki. Gotowe tłumaczenia niezmienionych fragmentów wykorzystujemy ponownie.');
    renderTranslationQueue(cards, data.queues?.parameters, 'parameters', 'Wspólne parametry i atuty', 'Jedno tłumaczenie parametru wykorzystujemy we wszystkich ofertach, które go zawierają.');
    const publication = $('translation-publication'); publication.replaceChildren();
    if (!data.publication?.available) {
      text(publication, 'p', 'Nie udało się ustalić liczby gotowych tekstów. Spróbuj odświeżyć.');
    } else {
      const active = count(data.publication.active_offers);
      for (const [language, label] of languages) {
        const ready = count(data.publication.languages?.[language]);
        const row = document.createElement('div'); row.className = 'translation-progress';
        const heading = document.createElement('div'); heading.className = 'translation-progress-heading';
        text(heading, 'strong', label); text(heading, 'span', `${formatCount(ready)} / ${formatCount(active)} ofert`);
        const bar = document.createElement('progress'); bar.max = Math.max(active, 1); bar.value = Math.min(ready, active);
        bar.setAttribute('aria-label', `${label}: gotowe teksty ${formatCount(ready)} z ${formatCount(active)} aktywnych ofert w imporcie`);
        row.append(heading, bar); publication.append(row);
      }
      if (!active) text(publication, 'p', 'Brak aktywnych ofert w imporcie.');
    }
    const errors = $('translation-errors'); errors.replaceChildren();
    if (data.errors_available === false) text(errors, 'li', 'Lista problemów jest niepełna — nie udało się odczytać wszystkich danych.').className = 'translation-empty';
    if (!Array.isArray(data.errors) || !data.errors.length) {
      if (data.errors_available !== false) text(errors, 'li', 'Brak ostatnich problemów wymagających pokazania.').className = 'translation-empty';
    } else {
      for (const error of data.errors.slice(0, 10)) {
        const item = document.createElement('li');
        const heading = document.createElement('div'); heading.className = 'translation-error-heading';
        const language = languages.find(([key]) => key === error.language)?.[1] || 'Nieznany język';
        const label = error.queue === 'parameters' ? 'Parametry i atuty' : 'Tytuły i opisy';
        text(heading, 'strong', `${label} · ${language}`);
        const state = text(heading, 'span', error.state === 'retry' ? 'Do ponowienia' : 'Wymaga uwagi');
        state.className = `integration-badge ${error.state === 'retry' ? 'stale' : 'error'}`;
        item.append(heading);
        const details = [];
        if (error.offer_number || error.offer_id) details.push(`Oferta nr ${error.offer_number || error.offer_id}`);
        if (error.source) details.push(error.source === 'esticrm' ? 'EstiCRM' : error.source === 'vixcrm' ? 'VixCRM' : 'MLS');
        if (error.phrase) details.push(`Parametr: ${String(error.phrase).slice(0, 180)}`);
        details.push(`Próby: ${formatCount(error.attempts)}`, dateTime(error.updated_at));
        text(item, 'span', details.join(' · ')).className = 'translation-error-details';
        text(item, 'p', error.message || 'Zadanie wymaga sprawdzenia.'); errors.append(item);
      }
    }
    if (retryFocusLabel) {
      const matching = [...cards.querySelectorAll('[data-translation-retry]')].find(button => button.getAttribute('aria-label') === retryFocusLabel);
      matching?.focus({preventScroll: true});
    }
  }
  async function loadTranslationStatus() {
    if (translationRetryPending || translationPending || $('editor').hidden || document.hidden) return;
    const generation = monitoringGeneration;
    const requestVersion = ++translationRequestVersion;
    translationPending = true;
    translationController = new AbortController();
    const button = $('translation-refresh'); button.disabled = true;
    try {
      const response = await fetch(endpoint + '?action=translation-status', {credentials: 'same-origin', cache: 'no-store', signal: translationController.signal});
      if (response.status === 401) { if (generation === monitoringGeneration && requestVersion === translationRequestVersion) monitoringSessionExpired(); return; }
      if (!response.ok) throw new Error('unavailable');
      const data = await response.json();
      if (generation === monitoringGeneration && requestVersion === translationRequestVersion && !translationRetryPending && !$('editor').hidden) {
        translationRetryNeedsRefresh = false;
        renderTranslationStatus(data);
      }
    } catch (error) {
      if (error.name !== 'AbortError' && generation === monitoringGeneration && requestVersion === translationRequestVersion) {
        $('translation-intro').textContent = 'Nie udało się pobrać kolejki tłumaczeń. Widoczne dane mogą być nieaktualne. Spróbuj odświeżyć.';
        $('translation-intro').classList.add('error');
      }
    } finally {
      if (generation === monitoringGeneration && requestVersion === translationRequestVersion) { translationPending = false; translationController = null; updateTranslationControls(); }
    }
  }
  function refreshMonitoring() {
    if (activeTab === 'integration') loadIntegrationStatus();
    if (activeTab === 'translations') loadTranslationStatus();
  }
  function selectTab(name, {navigate = false, focus = false} = {}) {
    if (!tabs.includes(name)) return;
    if (name !== activeTab) abortMonitoringRequests();
    if (name !== 'translations') clearTranslationConfirmation();
    activeTab = name;
    for (const tab of tabs) {
      const selected = tab === name;
      $('tab-' + tab).setAttribute('aria-selected', String(selected));
      $('tab-' + tab).tabIndex = selected ? 0 : -1;
      $('panel-' + tab).hidden = !selected;
    }
    if (navigate) {
      const url = new URL(location.href); url.hash = name;
      history.replaceState(history.state, '', url);
      $('admin-tabs').scrollIntoView({block: 'start'});
    }
    if (focus) $('tab-' + name).focus({preventScroll: true});
    if (!$('editor').hidden) refreshMonitoring();
  }
  function abortMonitoringRequests() {
    integrationRequestVersion++; translationRequestVersion++;
    integrationController?.abort(); translationController?.abort();
    integrationController = null; translationController = null;
    integrationPending = false; translationPending = false;
    updateTranslationControls();
  }
  function stopMonitoring() {
    monitoringGeneration++;
    abortMonitoringRequests();
    translationRetryConfirmation = null; translationRetryPending = false; translationRetryNeedsRefresh = false; latestTranslationStatus = null;
    $('translation-action-message').textContent = '';
    $('translation-cards').replaceChildren();
    $('integration-refresh').disabled = false; $('translation-refresh').disabled = false;
  }
  function monitoringSessionExpired() {
    stopMonitoring(); csrf = '';
    $('editor').hidden = true; $('login').hidden = false; $('code-form').hidden = true;
    notice('Sesja wygasła. Zaloguj się ponownie, aby zobaczyć aktualne dane.', true);
  }
  async function loadIntegrationStatus() {
    if (translationRetryPending || integrationPending || $('editor').hidden || document.hidden) return;
    const generation = monitoringGeneration;
    const requestVersion = ++integrationRequestVersion;
    integrationPending = true;
    integrationController = new AbortController();
    const button = $('integration-refresh'); button.disabled = true;
    try {
      const response = await fetch(endpoint + '?action=integration-status', {credentials: 'same-origin', cache: 'no-store', signal: integrationController.signal});
      if (response.status === 401) { if (generation === monitoringGeneration && requestVersion === integrationRequestVersion) monitoringSessionExpired(); return; }
      if (!response.ok) throw new Error('unavailable');
      const data = await response.json();
      if (generation === monitoringGeneration && requestVersion === integrationRequestVersion && !$('editor').hidden) renderIntegration(data);
    } catch (error) {
      if (error.name !== 'AbortError' && generation === monitoringGeneration && requestVersion === integrationRequestVersion) $('integration-intro').textContent = 'Nie udało się pobrać stanu integracji. Spróbuj odświeżyć.';
    } finally {
      if (generation === monitoringGeneration && requestVersion === integrationRequestVersion) { integrationPending = false; integrationController = null; updateTranslationControls(); }
    }
  }
  async function initialize() {
    const response = await fetch(endpoint + '?action=status', {credentials: 'same-origin', cache: 'no-store'});
    const state = await response.json();
    $('login').hidden = state.authorized; $('editor').hidden = !state.authorized;
    if (!state.authorized) { stopMonitoring(); return; }
    csrf = state.csrf; $('account').textContent = state.email;
    refreshMonitoring();
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
  $('integration-refresh').onclick = loadIntegrationStatus;
  $('translation-refresh').onclick = loadTranslationStatus;
  for (const name of tabs) $('tab-' + name).onclick = () => selectTab(name, {navigate: true});
  $('admin-tabs').onkeydown = event => {
    const position = tabs.findIndex(name => event.target === $('tab-' + name));
    if (position < 0) return;
    let next;
    if (event.key === 'ArrowRight') next = (position + 1) % tabs.length;
    else if (event.key === 'ArrowLeft') next = (position + tabs.length - 1) % tabs.length;
    else if (event.key === 'Home') next = 0;
    else if (event.key === 'End') next = tabs.length - 1;
    else return;
    event.preventDefault();
    selectTab(tabs[next], {navigate: true, focus: true});
  };
  document.addEventListener('visibilitychange', () => {
    if (document.hidden) { abortMonitoringRequests(); clearTranslationConfirmation(); }
  });
  window.addEventListener('hashchange', () => {
    const focused = document.activeElement;
    const focus = $('admin-tabs').contains(focused) || $('panel-' + activeTab).contains(focused);
    selectTab(tabFromHash(), {focus});
  });
  selectTab(activeTab);
  $('save').onclick = async () => {
    $('save').disabled = true;
    try { await post('save', {ids: selected}); notice('Zapisano. Zmiana pojawi się na stronie w ciągu kilku minut.'); await initialize(); }
    catch (error) { notice(error.message === 'offer_unavailable' ? 'Jedna z ofert nie jest już dostępna. Usuń ją z listy.' : 'Nie udało się zapisać zmian.', true); }
    finally { $('save').disabled = false; }
  };
  $('logout').onclick = async () => { try { await post('logout'); stopMonitoring(); location.reload(); } catch { notice('Nie udało się wylogować.', true); } };
  if (location.protocol === 'file:') {
    const message = $('message');
    message.classList.add('error');
    message.textContent = 'To jest lokalny podgląd pliku. Aby korzystać z panelu, otwórz ';
    const link = document.createElement('a');
    link.href = 'https://api.mazurestate.pl/api/featured-admin.php';
    link.textContent = 'panel ofert na stronie MazurEstate';
    message.append(link, '.');
  } else {
    initialize().catch(() => notice('Panel jest chwilowo niedostępny.', true));
  }
})();

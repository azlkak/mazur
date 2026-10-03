/** MazurEstate offer descriptions v1. No HTML insertion, rewriting or translation.
 * API returns allowlisted text/block data; older APIs use a plain-text fallback.
 */
const HEADING = /^(?:lokalizacja|układ|układ pomieszczeń|rozkład|rozkład pomieszczeń|standard|wykończenie|opłaty|koszty|dodatkowe informacje|warunki najmu|budynek|atuty|komunikacja|stan prawny|media|wyposażenie|nieruchomość|powierzchnia|działka|okolica)\s*:?$/iu;
const HEADING_WITH_TEXT = /^((?:lokalizacja|układ|układ pomieszczeń|rozkład|rozkład pomieszczeń|standard|wykończenie|opłaty|koszty|dodatkowe informacje|warunki najmu|budynek|atuty|komunikacja|stan prawny|media|wyposażenie|nieruchomość|powierzchnia|działka|okolica))\s*:\s*(\S.*)$/iu;
const clean = s => String(s).replace(/\r\n?/g,'\n').replace(/[\u00a0\u202f]/g,' ');
const flatText = runs => (runs || []).map(r => r.text).join('');
function compact(runs) {
  const out = [];
  for (const r of runs) {
    let text = clean(r.text).replace(/\s+/gu, ' ');
    if (!out.length || out.at(-1).text.endsWith(' ')) text = text.replace(/^ +/, '');
    if (!text) continue;
    const normalized = {text};
    if (r.strong) normalized.strong = true;
    if (r.em) normalized.em = true;
    const prev = out.at(-1);
    if (prev && !!prev.strong === !!r.strong && !!prev.em === !!r.em) prev.text += text;
    else out.push(normalized);
  }
  if (out.length) {
    out.at(-1).text = out.at(-1).text.trimEnd();
    if (!out.at(-1).text) out.pop();
  }
  return out;
}
function dropPrefix(runs, length) {
  const out = [];
  for (const r of runs) {
    if (length >= r.text.length) { length -= r.text.length; continue; }
    out.push({...r, text:r.text.slice(length)}); length = 0;
  }
  return compact(out);
}
function marker(block) {
  if (block.type !== 'paragraph') return null;
  const m = flatText(block.runs).match(/^(?:(?<bullet>[•●▪◦*–—-])\s+|(?<number>\d{1,4})[.)]\s+)(?=\S)/u);
  return m ? {length:m[0].length, ordered:m.groups.number !== undefined, number:Number(m.groups.number)} : null;
}
function structure(blocks, enabled) {
  const nested = blocks.map(b => b.type === 'list' ? {...b, items:b.items.map(item => ({...item,blocks:structure(item.blocks,enabled)}))} : b);
  if (!enabled) return nested;
  const out = [];
  for (let i=0;i<nested.length;i++) {
    const b=nested[i], start=marker(b);
    if (start) {
      let j=i+1;
      while (j<nested.length) {
        const next=marker(nested[j]);
        if (!next || next.ordered!==start.ordered || (start.ordered && next.number!==start.number+j-i)) break;
        j++;
      }
      // A lone dash / number is ambiguous: preserve the original paragraph.
      if (j-i>=2) {
        out.push({type:'list',ordered:start.ordered,start:start.ordered?start.number:1,items:nested.slice(i,j).map(p=>({blocks:[{type:'paragraph',runs:dropPrefix(p.runs,marker(p).length)}]}))});
        i=j-1; continue;
      }
    }
    if (b.type==='paragraph' && HEADING.test(flatText(b.runs))) out.push({...b,type:'heading'});
    else if (b.type==='paragraph' && HEADING_WITH_TEXT.test(flatText(b.runs))) {
      const match = flatText(b.runs).match(HEADING_WITH_TEXT);
      const prefix = flatText(b.runs).indexOf(':') + 1;
      out.push({type:'heading',runs:[{text:match[1]}]});
      out.push({type:'paragraph',runs:dropPrefix(b.runs,prefix)});
    } else out.push(b);
  }
  return out;
}

const COPY = {
  pl: {eyebrow:'O NIERUCHOMOŚCI', heading:'Opis nieruchomości', empty:'Opis nie został podany. Skontaktuj się z nami, aby poznać szczegóły.', source:''},
  en: {eyebrow:'ABOUT THE PROPERTY', heading:'Property description', empty:'No description has been provided. Contact us for details.', source:'This listing description is available in Polish. A translation is not yet available.'},
  uk: {eyebrow:'ПРО НЕРУХОМІСТЬ', heading:'Опис нерухомості', empty:'Опис не надано. Зв’яжіться з нами, щоб дізнатися подробиці.', source:'Опис цієї пропозиції доступний польською мовою. Переклад поки недоступний.'},
  ru: {eyebrow:'О НЕДВИЖИМОСТИ', heading:'Описание недвижимости', empty:'Описание не предоставлено. Свяжитесь с нами, чтобы узнать подробности.', source:'Описание этого предложения доступно на польском языке. Перевод пока недоступен.'},
};

/** Copy only recognized fields, with limits, before creating any DOM elements. */
function validateDocument(value) {
  if (!value || value.schemaVersion !== 1 || value.language !== 'pl' || !Array.isArray(value.blocks)) throw new TypeError('Unsupported description document');
  let nodes = 0, length = 0;
  const visit = (blocks, depth = 0) => {
    if (!Array.isArray(blocks) || depth > 40) throw new TypeError('Invalid description blocks');
    return blocks.map(block => {
      if (++nodes > 15000 || !block || typeof block !== 'object') throw new TypeError('Invalid description block');
      if (block.type === 'list') {
        if (!Array.isArray(block.items) || typeof block.ordered !== 'boolean') throw new TypeError('Invalid description list');
        const list = {type:'list', ordered:block.ordered, items:block.items.map(item => {
          if (++nodes > 15000 || !item || typeof item !== 'object') throw new TypeError('Invalid list item');
          const result = {blocks:visit(item.blocks, depth + 1)};
          if (Number.isSafeInteger(item.value) && Math.abs(item.value) <= 999999) result.value = item.value;
          return result;
        })};
        if (Number.isSafeInteger(block.start) && Math.abs(block.start) <= 999999) list.start = block.start;
        if (block.reversed === true && block.ordered) list.reversed = true;
        return list;
      }
      if (!['paragraph','heading'].includes(block.type) || !Array.isArray(block.runs)) throw new TypeError('Invalid text block');
      const runs = block.runs.map(run => {
        if (++nodes > 15000 || !run || typeof run.text !== 'string') throw new TypeError('Invalid text run');
        length += run.text.length;
        if (length > 800000) throw new RangeError('Description text limit');
        const result = {text:run.text};
        if (run.strong === true) result.strong = true;
        if (run.em === true) result.em = true;
        return result;
      });
      return {type:block.type,runs};
    });
  };
  return {schemaVersion:1,language:'pl',blocks:visit(value.blocks)};
}

/** Public data API, also used by regression tests. Never guesses lost HTML. */
export function prepareDescription(offer = {}) {
  if (offer.descriptionDocument) {
    try {
      const doc = validateDocument(offer.descriptionDocument);
      return {...doc,blocks:structure(doc.blocks,true)};
    } catch (error) { /* Keep the entire plain description if structured data fails. */ }
  }
  const source = typeof offer.description === 'string' ? offer.description : '';
  const blocks = clean(source).split(/\n+/u)
    .map(line => ({type:'paragraph',runs:compact([{text:line}])}))
    .filter(block => block.runs.length);
  // A very large legacy description is kept intact, not truncated or summarized.
  return {schemaVersion:1,language:'pl',blocks:blocks.length <= 15000 ? structure(blocks,true) : [{type:'paragraph',runs:[{text:source}]}]};
}

export function renderOfferDescription(section, offer, {language = 'pl'} = {}) {
  if (!section) return;
  const uiLanguage = Object.hasOwn(COPY,language) ? language : 'pl';
  const copy = COPY[uiLanguage], doc = prepareDescription(offer);
  const owner = section.ownerDocument;
  const fragment = owner.createDocumentFragment();
  const eyebrow = owner.createElement('p');
  eyebrow.className = 'eyebrow'; eyebrow.lang = uiLanguage;
  eyebrow.textContent = copy.eyebrow;
  const heading = owner.createElement('h2');
  heading.id = 'offer-description-heading'; heading.lang = offer.title ? 'pl' : uiLanguage;
  heading.textContent = offer.title || copy.heading;
  fragment.append(eyebrow,heading);
  if (uiLanguage !== 'pl' && doc.blocks.length) {
    const note = owner.createElement('p');
    note.className = 'description-language-note'; note.lang = uiLanguage;
    note.textContent = copy.source; fragment.append(note);
  }
  const reader = owner.createElement('div');
  reader.className = 'description-reader'; reader.lang = 'pl';
  const append = (parent, blocks) => {
    for (const block of blocks) {
      if (block.type === 'list') {
        const list = owner.createElement(block.ordered ? 'ol' : 'ul');
        if (block.ordered && Number.isInteger(block.start)) list.start = block.start;
        if (block.ordered && block.reversed) list.reversed = true;
        for (const item of block.items) {
          const li = owner.createElement('li');
          if (block.ordered && Number.isInteger(item.value)) li.value = item.value;
          append(li,item.blocks); list.append(li);
        }
        parent.append(list);
      } else {
        const element = owner.createElement(block.type === 'heading' ? 'h3' : 'p');
        for (const run of block.runs) {
          let node = owner.createTextNode(run.text);
          if (run.em) { const em = owner.createElement('em'); em.append(node); node = em; }
          if (run.strong) { const strong = owner.createElement('strong'); strong.append(node); node = strong; }
          element.append(node);
        }
        parent.append(element);
      }
    }
  };
  append(reader,doc.blocks);
  if (!doc.blocks.length) {
    const empty = owner.createElement('p'); empty.lang = uiLanguage;
    empty.className = 'empty-note'; empty.textContent = copy.empty; reader.append(empty);
  }
  fragment.append(reader);
  // Build off-DOM first: malformed data cannot partially erase the current offer.
  section.replaceChildren(fragment);
  section.setAttribute('aria-labelledby',heading.id);
}

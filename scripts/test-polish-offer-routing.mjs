import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';
import test from 'node:test';
import assert from 'node:assert/strict';

const routing = readFileSync(new URL('../assets/js/language-routing.js', import.meta.url), 'utf8');

function route(pathname, search = '', hash = '') {
  const redirects = [];
  const location = {
    pathname, search, hash, origin: 'https://mazurestate.pl', protocol: 'https:',
    replace(destination) { redirects.push(destination); }
  };
  const window = {};
  const document = { baseURI: `https://mazurestate.pl${pathname}${search}`, readyState: 'loading', addEventListener() {} };
  runInNewContext(routing, { location, window, document, URL, URLSearchParams });
  return { redirects, window };
}

function anchor(href, switcher = false) {
  return {
    nodeType: 1,
    dataset: {},
    getAttribute(name) { return name === 'href' ? href : null; },
    setAttribute(name, value) { if (name === 'href') href = value; },
    matches(selector) { return selector === 'a[href]' || switcher; }
  };
}

// Keep DOM readiness separate from rendering to reproduce slow React startup.
function routedDom(pathname, anchors, { search = '', hash = '', baseURI = 'https://mazurestate.pl/' } = {}) {
  const redirects = [];
  const observers = [];
  const readyHandlers = [];
  const location = {
    pathname, search, hash, origin: 'https://mazurestate.pl', protocol: 'https:',
    replace(destination) { redirects.push(destination); }
  };
  const document = {
    baseURI, readyState: 'loading',
    body: { matches() { return false; }, querySelectorAll() { return anchors; } },
    addEventListener(name, callback) { if (name === 'DOMContentLoaded') readyHandlers.push(callback); }
  };
  class MutationObserver {
    constructor(callback) { observers.push(callback); }
    observe() {}
  }
  const window = {};
  runInNewContext(routing, { location, window, document, MutationObserver, URL, URLSearchParams });
  return {
    redirects,
    ready() { document.readyState = 'interactive'; readyHandlers.forEach(callback => callback()); },
    render(rendered) {
      anchors = rendered;
      observers.forEach(callback => callback([{ type: 'childList', addedNodes: rendered }]));
    },
    changed(link) { observers.forEach(callback => callback([{ type: 'attributes', target: link }])); }
  };
}

const languages = ['pl', 'en', 'uk', 'ru'];
const homePath = lang => lang === 'pl' ? '/' : `/${lang}/`;
const offerPath = lang => `${homePath(lang)}oferta/`;

for (const sourceLang of languages) {
  test(`${sourceLang} home preserves unrendered link templates and normalizes rendered language choices`, () => {
    const templates = ['{{ lang.href }}', '{{ cat.href }}', '{{ featuredOffersHref }}'];
    const pending = templates.map((href, index) => anchor(href, index === 0));
    const dom = routedDom(homePath(sourceLang), pending);
    dom.ready();
    assert.deepEqual(pending.map(link => link.getAttribute('href')), templates);

    const choices = languages.map(lang => anchor(`index.html?lang=${lang}`, true));
    const category = anchor('wyniki-wyszukiwania/?type=lokale&transaction=wynajem#offers');
    dom.render([...choices, category]);
    assert.deepEqual(choices.map(link => link.getAttribute('href')), languages.map(homePath));
    assert.equal(category.getAttribute('href'), `${homePath(sourceLang)}wyniki-wyszukiwania/?type=lokale&transaction=wynajem#offers`);
    // A renderer updating an existing link must use the same normalization.
    choices[0].setAttribute('href', 'index.html?lang=ru');
    dom.changed(choices[0]);
    assert.equal(choices[0].getAttribute('href'), '/ru/');
  });

  test(`${sourceLang} rendered offer language choices preserve offer ID, query and hash`, () => {
    const pending = anchor('{{ lang.href }}', true);
    const dom = routedDom(offerPath(sourceLang), [pending], {
      search: '?id=esti-12645035&utm_source=search', hash: '#gallery',
      baseURI: 'https://mazurestate.pl/oferta/'
    });
    dom.ready();
    assert.equal(pending.getAttribute('href'), '{{ lang.href }}');
    const choices = languages.map(lang => anchor(`?id=esti-12645035&utm_source=search&lang=${lang}#gallery`, true));
    dom.render(choices);
    assert.deepEqual(choices.map(link => link.getAttribute('href')), languages.map(lang => `${offerPath(lang)}?id=esti-12645035&utm_source=search#gallery`));
  });

  test(`legacy ${sourceLang} home language parameter redirects before template rewriting`, () => {
    const pending = anchor('{{ lang.href }}', true);
    const dom = routedDom('/index.html', [pending], { search: `?lang=${sourceLang}`, hash: '#categories' });
    assert.deepEqual(dom.redirects, [`${homePath(sourceLang)}#categories`]);
    dom.ready();
    assert.equal(pending.getAttribute('href'), '{{ lang.href }}');
  });
}

for (const lang of ['en', 'uk', 'ru']) {
  test(`${lang} offer detail keeps its language path`, () => {
    const { redirects } = route(`/${lang}/oferta/`, '?id=esti-12645035&utm_source=search', '#gallery');
    assert.deepEqual(redirects, []);
  });
}

test('legacy language parameter on an offer resolves to its language path', () => {
  assert.deepEqual(route('/oferta/', '?id=34964566021&lang=en').redirects, ['/en/oferta/?id=34964566021']);
});

test('links to offer details retain the selected language', () => {
  const { window } = route('/en/wyniki-wyszukiwania/');
  assert.equal(window.mazurLocalizedUrl('../oferta/?id=34964566021'), '/en/oferta/?id=34964566021');
  assert.equal(window.mazurLocalizedUrl('../doradztwo/'), '/en/doradztwo/');
});

test('localized home paths remain normalized', () => {
  assert.equal(route('/en/').window.MAZUR_LANG, 'en');
  assert.deepEqual(route('/', '?lang=en').redirects, ['/en/']);
});

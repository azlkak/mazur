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

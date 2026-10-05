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
  test(`${lang} offer detail opens its Polish source page`, () => {
    const { redirects } = route(`/${lang}/oferta/`, '?id=esti-12645035&utm_source=search', '#gallery');
    assert.deepEqual(redirects, ['/oferta/?id=esti-12645035&utm_source=search#gallery']);
  });
}

test('legacy language parameter on an offer resolves directly to Polish', () => {
  assert.deepEqual(route('/oferta/', '?id=34964566021&lang=en').redirects, ['/oferta/?id=34964566021']);
});

test('links to offer details use Polish while other pages retain their language', () => {
  const { window } = route('/en/wyniki-wyszukiwania/');
  assert.equal(window.mazurLocalizedUrl('../oferta/?id=34964566021'), '/oferta/?id=34964566021');
  assert.equal(window.mazurLocalizedUrl('../doradztwo/'), '/en/doradztwo/');
});

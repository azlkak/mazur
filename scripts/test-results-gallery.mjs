import test from 'node:test';
import assert from 'node:assert/strict';
import { createResultsGallery } from '../assets/js/results-gallery.js';

test('mobile results gallery loads every photo after the preview', async () => {
  const images = Array.from({ length: 10 }, (_, index) => `https://images.example/${index + 1}.jpg`);
  let requests = 0;
  const gallery = createResultsGallery({
    fetchImpl: async url => {
      requests += 1;
      assert.equal(new URL(url).searchParams.get('id'), '311148');
      return { ok: true, json: async () => ({ offer: { id: '311148', images } }) };
    },
  });
  const offer = { id: '311148', title: 'Oferta', hasPhotos: true, gallery: images.slice(0, 5), imageCount: images.length };
  const list = { querySelectorAll: () => [] };

  assert.match(gallery.markup(offer), /1\/10/);
  await gallery.step(offer, 1, list);
  assert.match(gallery.markup(offer), /2\/10/);
  await gallery.step(offer, 1, list);
  assert.match(gallery.markup(offer), /3\/10/);
  assert.equal(requests, 1);

  for (let index = 3; index < images.length; index += 1) await gallery.step(offer, 1, list);
  assert.match(gallery.markup(offer), /10\/10/);
});

test('result cards request cached thumbnails while keeping the original URL as fallback', async () => {
  const first = `https://api.mazurestate.pl/api/mls-image.php?name=${'a'.repeat(64)}.jpg`;
  const second = `https://api.mazurestate.pl/api/mls-image.php?name=${'b'.repeat(64)}.png`;
  const gallery = createResultsGallery();
  const offer = { id: '2', title: 'Oferta', hasPhotos: true, gallery: [first, second], imageCount: 2 };
  const firstMarkup = gallery.markup(offer);
  assert.match(firstMarkup, /w=800 800w/);
  assert.match(firstMarkup, /w=1200 1200w/);
  assert.match(firstMarkup, new RegExp(`src="${first.replace(/[.*+?^${}()|[\]\\]/g, '\\$&').replace('&', '&amp;')}"`));
  await gallery.step(offer, 1, { querySelectorAll: () => [] });
  assert.match(gallery.markup(offer), new RegExp(`${'b'.repeat(64)}\\.png&amp;w=800`));

  const external = { id: '3', title: 'Zewnętrzna', hasPhotos: true, gallery: ['https://vixcrm.com/photo/e/1/photo.png'], imageCount: 1 };
  assert.doesNotMatch(gallery.markup(external), /srcset=/);
});

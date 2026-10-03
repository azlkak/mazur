"""Read-only smoke tests; never submits a form or starts an import."""
import json
import re
import sys
import time
import urllib.error
import urllib.parse
import urllib.request
import xml.etree.ElementTree as ET

BASE = 'https://mazurestate.pl'
API = 'https://api.mazurestate.pl/api'


def get(url, expected=200):
    request = urllib.request.Request(url, headers={'User-Agent': 'MazurEstate-Healthcheck/1.0'})
    try:
        with urllib.request.urlopen(request, timeout=35) as response:
            code, body = response.status, response.read()
    except urllib.error.HTTPError as error:
        code, body = error.code, error.read()
    if code != expected:
        raise AssertionError(f'{url}: expected HTTP {expected}, got {code}')
    return body


def check():
    assert b'<html' in get(BASE + '/').lower(), 'Homepage is not HTML'
    robots = get(BASE + '/robots.txt').decode()
    assert 'sitemap-oferty.xml' in robots, 'Offer sitemap missing from robots.txt'
    ET.fromstring(get(BASE + '/sitemap.xml'))
    tree = ET.fromstring(get(BASE + '/sitemap-oferty.xml'))
    urls = [el.text for el in tree.findall('{*}url/{*}loc')]
    assert urls, 'No public offers in sitemap'
    url = urls[0]
    assert url.startswith(BASE + '/oferta/?id='), 'Unexpected offer URL'
    html = get(url).decode()
    assert re.search(r'name=["\']robots["\'][^>]+content=["\']index,\s*follow', html, re.I), 'Offer is not indexable'
    assert 'Przykładowa oferta' not in re.search(r'<title>(.*?)</title>', html, re.S).group(1), 'Generic title'
    assert url.replace('&', '&amp;') in html, 'Offer canonical missing'
    offer_id = urllib.parse.parse_qs(urllib.parse.urlsplit(url).query)['id'][0]
    data = json.loads(get(API + '/mls-offer.php?id=' + urllib.parse.quote(offer_id)))
    offer = data['offer']
    assert str(offer['id']) == offer_id, 'API returned wrong offer'
    if offer.get('images'):
        image = get(offer['images'][0])
        assert len(image) > 100, 'Empty image'
    get(BASE + '/oferta/?id=esti-215181', expected=404)
    # An internal helper must not expose source code.
    helper = get(BASE + '/site-live/api/offer-seo-lib.php')
    assert b'<?php' not in helper, 'PHP source exposed'
    return {'ok': True, 'offers_in_sitemap': len(urls), 'sample_offer': offer_id}


if __name__ == '__main__':
    for attempt in range(3):
        try:
            print(json.dumps(check(), ensure_ascii=False))
            sys.exit(0)
        except Exception as error:
            if attempt == 2:
                print(json.dumps({'ok': False, 'error': str(error)}, ensure_ascii=False))
                sys.exit(1)
            time.sleep(10)

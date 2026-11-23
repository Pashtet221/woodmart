#!/usr/bin/env python3
"""Sync actual Appearance > Menus entries via WordPress REST. Dry run unless --apply.
Existing demonstration entries are preserved in an unassigned archive menu.
Credentials: WORDPRESS_URL, WORDPRESS_USERNAME, WORDPRESS_APP_PASSWORD.
"""
import argparse
import base64
import json
import os
from pathlib import Path
import urllib.request


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--apply', action='store_true')
    args = parser.parse_args()
    config = json.loads((Path(__file__).resolve().parents[1] / 'config/navigation.json').read_text())
    base = os.environ['WORDPRESS_URL'].rstrip('/')
    credentials = os.environ['WORDPRESS_USERNAME'] + ':' + os.environ['WORDPRESS_APP_PASSWORD']

    def api(path, payload=None):
        request = urllib.request.Request(base + '/wp-json/wp/v2/' + path,
            headers={'Authorization': 'Basic ' + base64.b64encode(credentials.encode()).decode(),
                     'Content-Type': 'application/json', 'Cookie': 'beget=begetok'},
            data=json.dumps(payload).encode() if payload is not None else None)
        with urllib.request.urlopen(request, timeout=60) as response:
            return json.load(response)

    menus = api('menus?per_page=100&context=edit')
    archive = next((menu for menu in menus if menu['slug'] == 'mitkin-navigation-archive'), None)
    for slug in config['menus']:
        menu = next((menu for menu in menus if menu['slug'] == slug), None)
        if not menu:
            raise RuntimeError('Expected menu missing: ' + slug)
        name = config.get('menu_names', {}).get(slug, menu['name'])
        if menu['name'] != name:
            print(slug + ': rename to ' + name)
            if args.apply:
                api('menus/' + str(menu['id']), {'name': name})
        old = api('menu-items?menus=' + str(menu['id']) + '&per_page=100&context=edit&orderby=menu_order&order=asc')
        expected = [(row['title'], base + row['path']) for row in config['items']]
        actual = [(row['title']['raw'], row['url']) for row in old]
        if actual == expected and all(not row['parent'] for row in old):
            print(slug + ': already current')
            continue
        print(slug + ': ' + ', '.join(title for title, url in expected))
        if not args.apply:
            continue
        # New entries have no old WoodMart mega-menu block metadata attached.
        for position, row in enumerate(config['items'], 1):
            api('menu-items', {'title': row['title'], 'url': base + row['path'],
                'type': 'custom', 'object': 'custom', 'object_id': 0, 'status': 'publish',
                'parent': 0, 'menu_order': position, 'menus': menu['id']})
        if old:
            if archive is None:
                archive = api('menus', {'name': 'MITKIN — архив прежних пунктов', 'slug': 'mitkin-navigation-archive'})
            for row in old:
                api('menu-items/' + str(row['id']), {'menus': archive['id']})
        verified = api('menu-items?menus=' + str(menu['id']) + '&per_page=100&context=edit&orderby=menu_order&order=asc')
        if [(row['title']['raw'], row['url']) for row in verified] != expected:
            raise RuntimeError('Menu verification failed: ' + slug)
        print(slug + ': saved and verified')


if __name__ == '__main__':
    main()

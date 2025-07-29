import './page/rhae-tweakwise-feed-list';
import './page/rhae-tweakwise-feed-create';
import './page/rhae-tweakwise-feed-detail';
import '../components/rhae-tweakwise-settings-icon';
import nlNL from './snippet/nl-NL.json';
import enGB from './snippet/en-GB.json';

Shopware.Module.register('rhae-tweakwise-feed', {
    type: 'plugin',
    name: 'Tweakwise Feed',
    title: 'rhae-tweakwise-feed.main.menuLabel',
    description: 'rhae-tweakwise-feed.main.menuDescription',
    color: '#028eca',

    snippets: {
        'nl-NL': nlNL,
        'en-GB': enGB
    },

    routes: {
        list: {
            component: 'rhae-tweakwise-feed-list',
            path: 'list',
            meta: {
                parentPath: 'sw.settings.index.plugins'
            }
        },
        detail: {
            component: 'rhae-tweakwise-feed-detail',
            path: 'detail/:id',
            meta: {
                parentPath: 'rhae.tweakwise.feed.list'
            }
        },
        create: {
            component: 'rhae-tweakwise-feed-create',
            path: 'create',
            meta: {
                parentPath: 'rhae.tweakwise.feed.list'
            }
        }
    },

    settingsItem: {
        name: 'rhae-tweakwise-feed-list',
        to: 'rhae.tweakwise.feed.list',
        label: 'rhae-tweakwise-feed.main.menuLabel',
        group: 'plugins',
        iconComponent: 'rhae-tweakwise-settings-icon',
    }
});

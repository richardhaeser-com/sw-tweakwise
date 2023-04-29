import './page/rhae-tweakwise-feed-list';
import nlNL from './snippet/nl-NL.json';
import enGB from './snippet/en-GB.json';

Shopware.Module.register('rhae-tweakwise-feed', {
    type: 'plugin',
    name: 'Tweakwise Feed',
    title: 'rhae-tweakwise-feed.main.menuLabel',
    description: 'rhae-tweakwise-feed.main.menuDescription',
    color: '#01b7cd',
    icon: 'default-eye-open',

    snippets: {
        'nl-NL': nlNL,
        'en-GB': enGB
    },

    routes: {
        list: {
            component: 'rhae-tweakwise-feed-list',
            path: 'list',
            meta: {
                parentPath: 'sw.settings.index'
            }
        }
    },

    settingsItem: {
        name: 'rhae-tweakwise-feed-list',
        to: 'rhae.tweakwise.feed.list',
        label: 'rhae-tweakwise-feed.main.menuLabel',
        group: 'plugins',
        icon: 'default-symbol-content'
    }
});

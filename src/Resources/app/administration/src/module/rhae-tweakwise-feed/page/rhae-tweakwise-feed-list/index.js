import template from './rhae-tweakwise-feed-list.html.twig';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('rhae-tweakwise-feed-list', {
    template,

    inject: [
        'repositoryFactory'
    ],

    data() {
        return {
            repository: null,
            feed: null
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle()
        };
    },
    computed: {
        columns() {
            return [
                {
                    property: 'name',
                    dataIndex: 'name',
                    label: this.$t('rhae-tweakwise-feed.list.columns.name'),
                    routerLink: 'rhae.tweakwise.feed.detail',
                    inlineEdit: 'string',
                    allowResize: true,
                    primary: true
                }
            ]
        }
    },

    methods: {
        getList() {
            this.repository = this.repositoryFactory.create('s_plugin_rhae_tweakwise_feed');
            const criteria = new Criteria();
            criteria.addSorting(Criteria.sort('name', 'ASC'));

            this.repository
                .search(criteria, Shopware.Context.api)
                .then((result) => {
                    this.feed = result;
                });
        },

        changeLanguage() {
            this.getList();
        }
    },

    created() {
        this.getList();
    }
});

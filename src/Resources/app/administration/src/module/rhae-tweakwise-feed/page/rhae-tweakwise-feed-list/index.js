import template from './rhae-tweakwise-feed-list.html.twig';

const { Component, Mixin, Context } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('rhae-tweakwise-feed-list', {
    template,

    inject: [
        'repositoryFactory',
        'numberRangeService',
        'context'
    ],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('listing'),
    ],

    data() {
        return {
            items: null,
            sortBy: 'name'
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle()
        };
    },

    computed: {
        repository() {
            return this.repositoryFactory.create('s_plugin_rhae_tweakwise_feed');
        },

        searchContext() {
            return {
                ...Context.api,
                inheritance: true
            };
        },

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
                },
                {
                    property: 'type',
                    dataIndex: 'type',
                    label: this.$t('rhae-tweakwise-feed.list.columns.type'),
                    inlineEdit: 'string'
                },
                {
                    property: 'domains',
                    dataIndex: 'domains',
                    label: this.$t('rhae-tweakwise-feed.list.columns.domains'),
                },
                {
                    property: 'lastGeneratedAt',
                    dataIndex: 'lastGeneratedAt',
                    label: this.$t('rhae-tweakwise-feed.list.columns.lastGeneratedAt'),
                }
            ]
        }
    },

    methods: {
        async getList() {
            const criteria = new Criteria(this.page, this.limit, this.term);
            criteria.addAssociation('salesChannelDomains');

            this.isLoading = true;

            try {
                const items = await this.repository.search(criteria, Shopware.Context.api);

                this.total = items.total;
                this.isLoading = false;
                this.items = items;
                this.selection = {};
            } catch {
                this.isLoading = false;
            }
        },

        changeLanguage() {
            this.getList();
        },

        updateSelection() {
        },

        updateTotal({total}) {
            this.total = total;
        },

        onDuplicate(reference) {
            this.repository.clone(reference.id, Shopware.Context.api, {
                name: `${reference.name} ${this.$tc('sw-product.general.copy')}`,
                locked: false
            }).then((duplicate) => {
                this.$router.push({name: 'rhae.tweakwise.feed.detail', params: {id: duplicate.id}});
            });
        }
    },

    created() {
    }
});

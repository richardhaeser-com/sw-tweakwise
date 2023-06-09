import template from './rhae-tweakwise-frontend-list.html.twig';

const { Component, Mixin, Context } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('rhae-tweakwise-frontend-list', {
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
            return this.repositoryFactory.create('s_plugin_rhae_tweakwise_frontend');
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
                    label: this.$t('rhae-tweakwise-frontend.list.columns.name'),
                    routerLink: 'rhae.tweakwise.frontend.detail',
                    inlineEdit: 'string',
                    allowResize: true,
                    primary: true
                }
            ]
        }
    },

    methods: {
        async getList() {
            const criteria = new Criteria(this.page, this.limit, this.term);

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
                this.$router.push({name: 'rhae.tweakwise.frontend.detail', params: {id: duplicate.id}});
            });
        }
    },

    created() {
    }
});

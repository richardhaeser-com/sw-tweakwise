import template from "./sw-product-detail.html.twig";

const { Criteria } = Shopware.Data;

Shopware.Component.override('sw-product-detail', {
    template,
    inject: ['repositoryFactory', 'feature', 'acl'],

    data() {
        return {
            frontendCount: 0,
            isLoadingFrontends: false,
        }
    },

    computed: {
        twFeedRepository() {
            return this.repositoryFactory.create('s_plugin_rhae_tweakwise_frontend');
        },
        hasEnabledFrontends() {
            return this.frontendCount > 0;
        }
    },
    methods: {
        async createdComponent() {
            this.$super('createdComponent');

            this.isLoadingFrontends = true;

            try {
                const criteria = new Criteria(1, 1);
                criteria.addFilter(Criteria.equals('backendSyncEnabled', true));

                const result = await this.twFeedRepository.search(criteria, Shopware.Context.api);

                this.frontendCount = result.total;
            } finally {
                this.isLoadingFrontends = false;
            }
        },
    }
});

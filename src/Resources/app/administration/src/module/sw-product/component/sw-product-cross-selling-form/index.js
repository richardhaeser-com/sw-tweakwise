import template from "./sw-product-cross-selling-form.html.twig";

const { Criteria } = Shopware.Data;
const { Component, Context } = Shopware;
const { mapPropertyErrors, mapGetters, mapState } = Component.getComponentHelper();

Shopware.Component.override('sw-product-cross-selling-form', {
    template,

    created() {
        if (!this.crossSelling.extensions.tweakwise) {
            this.crossSelling.extensions.tweakwise = this.repositoryFactory.create('product_cross_selling_tweakwise').create(Shopware.Context.api);
        }
    },
    computed: {
        crossSellingTypes() {
            return [{
                label: this.$tc('sw-product.crossselling.tweakwiseType'),
                value: 'tweakwiseRecommendation',
            }, {
                label: this.$tc('sw-product.crossselling.productStreamType'),
                value: 'productStream',
            }, {
                label: this.$tc('sw-product.crossselling.productListType'),
                value: 'productList',
            }];
        }
    },
    data() {
        return {
            showDeleteModal: false,
            showModalPreview: false,
            productStream: null,
            productStreamFilter: [],
            productStreamFilterTree: null,
            optionSearchTerm: '',
            useManualAssignment: false,
            useTweakwise: false,
            sortBy: 'name',
            sortDirection: 'ASC',
            assignmentKey: 0,
        };
    },
    watch: {
        'crossSelling.productStreamId'() {
            if (!this.useManualAssignment && !this.useTweakwise) {
                this.loadStreamPreview();
            }
        },
    },
    methods: {
        createdComponent() {
            this.useManualAssignment = this.crossSelling.type === 'productList';
            this.useTweakwise = this.crossSelling.type === 'tweakwiseRecommendation';
        },
        onTypeChanged(value) {
            this.useManualAssignment = value === 'productList';
            this.useTweakwise = value === 'tweakwiseRecommendation';
        }
    }
});

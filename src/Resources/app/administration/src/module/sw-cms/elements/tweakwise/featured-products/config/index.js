import template from './sw-cms-el-config-tweakwise-featured-products.html.twig';

Shopware.Component.register('sw-cms-el-config-tweakwise-featured-products', {
    template,

    mixins: [
        'cms-element'
    ],

    computed: {
        groupId: {
            get() {
                return this.element.config.groupId.value;
            },

            set(value) {
                this.element.config.groupId.value = value;
            }
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.initElementConfig('tweakwise-featured-products');
        },

        onElementUpdate(value) {
            this.element.config.groupId.value = value;

            this.$emit('element-update', this.element);
        }
    }
});

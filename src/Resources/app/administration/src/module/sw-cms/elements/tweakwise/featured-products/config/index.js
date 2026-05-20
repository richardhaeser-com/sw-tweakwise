import template from './sw-cms-el-config-tweakwise-featured-products.html.twig';
import './sw-cms-el-config-tweakwise-featured-products.scss';

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
        },

        itemsDesktop: {
            get() {
                return this.element.config.itemsDesktop.value;
            },

            set(value) {
                this.element.config.itemsDesktop.value = value;
            }
        },

        itemsTablet: {
            get() {
                return this.element.config.itemsTablet.value;
            },

            set(value) {
                this.element.config.itemsTablet.value = value;
            }
        },

        itemsMobile: {
            get() {
                return this.element.config.itemsMobile.value;
            },

            set(value) {
                this.element.config.itemsMobile.value = value;
            }
        },

        viewDesktop: {
            get() {
                return this.element.config.viewDesktop.value;
            },

            set(value) {
                this.element.config.viewDesktop.value = value;
            }
        },

        viewTablet: {
            get() {
                return this.element.config.viewTablet.value;
            },

            set(value) {
                this.element.config.viewTablet.value = value;
            }
        },

        viewMobile: {
            get() {
                return this.element.config.viewMobile.value;
            },

            set(value) {
                this.element.config.viewMobile.value = value;
            }
        },

        viewOptions() {
            return [
                { value: 'carousel', label: this.$tc('sw-cms.elements.tweakwiseFeaturedProducts.config.view.carousel') },
                { value: 'grid', label: this.$tc('sw-cms.elements.tweakwiseFeaturedProducts.config.view.grid') },
            ];
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
            this.$emit('element-update', this.element);
        }
    }
});

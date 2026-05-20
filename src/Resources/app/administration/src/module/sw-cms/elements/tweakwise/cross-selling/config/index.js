import template from './sw-cms-el-config-tweakwise-cross-selling.html.twig';
import './sw-cms-el-config-tweakwise-cross-selling.scss';

Shopware.Component.register('sw-cms-el-config-tweakwise-cross-selling', {
    template,

    mixins: [
        'cms-element'
    ],

    computed: {
        groupKey: {
            get() {
                return this.element.config.groupKey.value;
            },

            set(value) {
                this.element.config.groupKey.value = value;
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

    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.initElementConfig('tweakwise-cross-selling');
        },

        onElementUpdate(value) {
            this.$emit('element-update', this.element);
        }
    }
});
import template from './sw-cms-el-config-tweakwise-attribute-landing-page.html.twig';

Shopware.Component.register('sw-cms-el-config-tweakwise-attribute-landing-page', {
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
            this.initElementConfig('tweakwise-attribute-landing-page');
        },

        onElementUpdate(value) {
            this.$emit('element-update', this.element);
        }
    }
});

import template from './sw-cms-el-config-tweakwise-cross-selling.html.twig';

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
        }
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

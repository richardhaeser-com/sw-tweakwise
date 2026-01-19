import template from './sw-cms-el-config-tweakwise-attribute-landing-page.html.twig';
import './sw-cms-el-config-tweakwise-attribute-landing-page.scss';

Shopware.Component.register('sw-cms-el-config-tweakwise-attribute-landing-page', {
    template,

    mixins: [
        'cms-element'
    ],

    data() {
        return {
            categories: [],
            filterTemplates: [],
            sortTemplates: [],
            builderTemplates: [],
            maxRules: 10,
            attributeOptions: [],
            valueOptionsByRuleIndex: [],
            isLoadingAttributes: false,
            isLoadingValuesByRuleIndex: {}
        }
    },

    computed: {
        rules() {
            let rules = this.element.config.rules.value;
            if (!rules) {
                rules = [];
            }

            for (let index = 0; index < rules.length; index++) {
                if (rules[index].attributeId) {
                    this.loadValuesForRule(index, rules[index].attributeId);
                }
            }
            return rules;
        },
        canAddRule() {
            return this.rules.length < this.maxRules;
        },
        category: {
            get() {
                return this.element.config.category.value;
            },

            set(value) {
                this.element.config.category.value = value;
            }
        },
        filterTemplate: {
            get() {
                return this.element.config.filterTemplate.value;
            },

            set(value) {
                this.element.config.filterTemplate.value = value;
            }
        },
        sortTemplate: {
            get() {
                return this.element.config.sortTemplate.value;
            },

            set(value) {
                this.element.config.sortTemplate.value = value;
            }
        },
        builderTemplate: {
            get() {
                return this.element.config.builderTemplate.value;
            },

            set(value) {
                this.element.config.builderTemplate.value = value;
            }
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.initElementConfig('tweakwise-attribute-landing-page');
            this.loadCategoryOptions();
            this.loadFilterTemplates();
            this.loadSortTemplates();
            this.loadBuilderTemplates();
            this.loadFilterAttributes();
        },

        onElementUpdate(value) {
            this.$emit('element-update', this.element);
        },

        addRule() {
            if (!this.canAddRule) return;

            this.rules.push({
                attributeId: null,
                valueId: null
            });
        },
        removeRule(index) {
            this.rules.splice(index, 1);
            this.$delete(this.valueOptionsByRuleIndex, index);
            this.$delete(this.isLoadingValuesByRuleIndex, index);
        },
        async onAttributeChange(index, value) {
            this.rules[index].attributeId = value;
            const rule = this.rules[index];

            rule.valueId = null;
            if (!rule.attributeId) {
                this.valueOptionsByRuleIndex[index] = [];
            }
            if (rule.attributeId) {
                await this.loadValuesForRule(index, rule.attributeId);
            }
        },
        async onAttributeValueChange(index, value) {
            const rule = this.rules[index];
            rule.valueId = value;
        },

        async onFilterTemplateChange() {
            await this.loadFilterAttributes();
        },

        async loadValuesForRule(index, attributeId) {
            this.isLoadingValuesByRuleIndex[index] = true;

            const token = Shopware.Service('loginService').getToken();
            const headers = { headers: { Authorization: `Bearer ${token}` } };
            const httpClient = Shopware.Application.getContainer('init').httpClient;
            const url = `_action/rhae-tweakwise/filterAttributeValues`;
            const response = await httpClient.get(url, {
                ...headers,
                params: { urlKey: attributeId },
            });
            if (response.status !== 200) {
                console.warn('oops');
            } else {
                this.valueOptionsByRuleIndex[index] = response.data;
            }

            this.isLoadingValuesByRuleIndex[index] = false;
        },
        async loadCategoryOptions() {
            this.isLoading = true;
            const token = Shopware.Service('loginService').getToken();
            const headers = { headers: { Authorization: `Bearer ${token}` } };
            const httpClient = Shopware.Application.getContainer('init').httpClient;
            const url = `/_action/rhae-tweakwise/categoryTree`;
            const response = await httpClient.get(url, headers);

            if (response.status !== 200) {
                console.warn('oops');
            } else {
                this.categories = response.data;
            }

            this.isLoading = false;
        },

        async loadFilterTemplates() {
            this.isLoading = true;
            const token = Shopware.Service('loginService').getToken();
            const headers = { headers: { Authorization: `Bearer ${token}` } };
            const httpClient = Shopware.Application.getContainer('init').httpClient;
            const url = `/_action/rhae-tweakwise/filterTemplates`;
            const response = await httpClient.get(url, headers);

            if (response.status !== 200) {
                console.warn('oops');
            } else {
                this.filterTemplates = response.data;
            }

            this.isLoading = false;
        },

        async loadFilterAttributes() {
            this.isLoadingAttributes = true;
            const selectedCategoryId = this.category;
            const filterTemplateId = this.filterTemplate;

            const token = Shopware.Service('loginService').getToken();
            const headers = { headers: { Authorization: `Bearer ${token}` } };
            const httpClient = Shopware.Application.getContainer('init').httpClient;
            const url = `/_action/rhae-tweakwise/filterAttributes`;
            const response = await httpClient.get(url, {
                ...headers,
                params: { categoryId: selectedCategoryId, filterTemplateId: filterTemplateId },
            });

            if (response.status !== 200) {
                console.warn('oops');
            } else {
                this.attributeOptions = response.data;
            }

            this.isLoadingAttributes = false;
        },

        async loadSortTemplates() {
            this.isLoading = true;
            const token = Shopware.Service('loginService').getToken();
            const headers = { headers: { Authorization: `Bearer ${token}` } };
            const httpClient = Shopware.Application.getContainer('init').httpClient;
            const url = `/_action/rhae-tweakwise/sortTemplates`;
            const response = await httpClient.get(url, headers);

            if (response.status !== 200) {
                console.warn('oops');
            } else {
                this.sortTemplates = response.data;
            }

            this.isLoading = false;
        },

        async loadBuilderTemplates() {
            this.isLoading = true;
            const token = Shopware.Service('loginService').getToken();
            const headers = { headers: { Authorization: `Bearer ${token}` } };
            const httpClient = Shopware.Application.getContainer('init').httpClient;
            const url = `/_action/rhae-tweakwise/builderTemplates`;
            const response = await httpClient.get(url, headers);

            if (response.status !== 200) {
                console.warn('oops');
            } else {
                this.builderTemplates = response.data;
            }

            this.isLoading = false;
        },
    }
});

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
            maxRules: 20,
            attributeOptions: [],
            valueOptionsByRuleIndex: [],
            isLoading: false,
            isLoadingAttributes: false,
            isLoadingValuesByRuleIndex: {}
        };
    },

    computed: {
        rules() {
            let rules = this.element.config.rules.value;

            if (!rules) {
                rules = [];
                this.element.config.rules.value = rules;
            }

            for (let index = 0; index < rules.length; index++) {
                const rule = rules[index];

                if (!rule.type) {
                    rule.type = 'standard';
                }

                if (rule.type === 'custom') {
                    if (typeof rule.attributeId !== 'string') {
                        rule.attributeId = rule.attributeId ? String(rule.attributeId) : '';
                    }

                    if (typeof rule.customValue !== 'string') {
                        if (Array.isArray(rule.valueIds) && rule.valueIds.length > 0) {
                            rule.customValue = rule.valueIds.join('|');
                        } else if (rule.valueId) {
                            rule.customValue = rule.valueId;
                        } else {
                            rule.customValue = '';
                        }
                    }

                    if (typeof rule.selectionAttributeId === 'undefined') {
                        rule.selectionAttributeId = null;
                    }

                    rule.valueIds = [];
                } else {
                    if (!Array.isArray(rule.valueIds)) {
                        if (Array.isArray(rule.valueId)) {
                            rule.valueIds = rule.valueId;
                        } else if (rule.valueId) {
                            rule.valueIds = [rule.valueId];
                        } else {
                            rule.valueIds = [];
                        }
                    }
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

        showSelectedFilters: {
            get() {
                return this.element.config.showSelectedFilters.value;
            },

            set(value) {
                this.element.config.showSelectedFilters.value = value;
            }
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.initElementConfig('tweakwise-attribute-landing-page');
            this.ensureRuleStructure();
            this.loadCategoryOptions();
            this.loadFilterTemplates();
            this.loadSortTemplates();
            this.loadBuilderTemplates();
            this.loadFilterAttributes();
            this.restoreRuleValueOptions();
        },

        ensureRuleStructure() {
            if (!this.element.config.rules.value) {
                this.element.config.rules.value = [];
            }

            this.element.config.rules.value = this.element.config.rules.value.map((rule) => {
                const isCustom = rule.type === 'custom';

                if (isCustom) {
                    let customValue = '';

                    if (typeof rule.customValue === 'string') {
                        customValue = rule.customValue;
                    } else if (Array.isArray(rule.valueIds) && rule.valueIds.length > 0) {
                        customValue = rule.valueIds.join('|');
                    } else if (rule.valueId) {
                        customValue = rule.valueId;
                    }

                    return {
                        type: 'custom',
                        attributeId: rule.attributeId || '',
                        customValue,
                        selectionAttributeId: rule.selectionAttributeId || null,
                        valueIds: []
                    };
                }

                let valueIds = [];

                if (Array.isArray(rule.valueIds)) {
                    valueIds = rule.valueIds;
                } else if (Array.isArray(rule.valueId)) {
                    valueIds = rule.valueId;
                } else if (rule.valueId) {
                    valueIds = [rule.valueId];
                }

                return {
                    type: 'standard',
                    attributeId: rule.attributeId || null,
                    valueIds
                };
            });
        },

        parseCustomValues(customValue) {
            return String(customValue || '')
                .split('|')
                .map((value) => value.trim())
                .filter(Boolean);
        },

        findMatchingAttributeOption(input) {
            const normalizedInput = String(input || '').trim().toLowerCase();

            if (!normalizedInput) {
                return null;
            }

            return this.attributeOptions.find((option) => {
                const optionValue = String(option.value || '').trim().toLowerCase();
                const optionLabel = String(option.label || '').trim().toLowerCase();

                return optionValue === normalizedInput || optionLabel === normalizedInput;
            }) || null;
        },

        resolveCustomRuleSelectionAttribute(index) {
            const rule = this.rules[index];

            if (!rule || rule.type !== 'custom') {
                return;
            }

            const matchingOption = this.findMatchingAttributeOption(rule.attributeId);
            rule.selectionAttributeId = matchingOption ? matchingOption.value : null;
        },

        resolveExistingCustomRules() {
            this.rules.forEach((rule, index) => {
                if (rule.type === 'custom') {
                    this.resolveCustomRuleSelectionAttribute(index);
                }
            });
        },

        restoreRuleValueOptions() {
            const rules = this.element.config.rules.value || [];

            rules.forEach((rule, index) => {
                if (rule.type === 'standard' && rule.attributeId) {
                    this.loadValuesForRule(index, rule.attributeId);
                }
            });
        },

        onElementUpdate() {
            this.$emit('element-update', this.element);
        },

        addRule() {
            if (!this.canAddRule) {
                return;
            }

            this.rules.push({
                type: 'standard',
                attributeId: null,
                valueIds: []
            });

            this.onElementUpdate();
        },

        addCustomRule() {
            if (!this.canAddRule) {
                return;
            }

            this.rules.push({
                type: 'custom',
                attributeId: '',
                customValue: '',
                selectionAttributeId: null,
                valueIds: []
            });

            this.onElementUpdate();
        },

        removeRule(index) {
            this.rules.splice(index, 1);

            delete this.valueOptionsByRuleIndex[index];
            delete this.isLoadingValuesByRuleIndex[index];

            this.onElementUpdate();
        },

        onCustomAttributeChange(index, value) {
            const rule = this.rules[index];
            rule.attributeId = value;
            this.resolveCustomRuleSelectionAttribute(index);
            this.onElementUpdate();
        },

        onCustomValueChange(index, value) {
            const rule = this.rules[index];
            rule.customValue = value;
            this.resolveCustomRuleSelectionAttribute(index);
            this.onElementUpdate();
        },

        async onAttributeChange(index, value) {
            const rule = this.rules[index];

            rule.attributeId = value;
            rule.valueIds = [];

            if (!rule.attributeId) {
                this.valueOptionsByRuleIndex[index] = [];
                this.onElementUpdate();
                return;
            }

            await this.loadValuesForRule(index, rule.attributeId);
            this.onElementUpdate();
        },

        async onCategoryChange() {
            await this.loadFilterAttributes();

            this.rules.forEach((rule, index) => {
                if (rule.type === 'standard') {
                    rule.valueIds = [];
                    this.valueOptionsByRuleIndex[index] = [];
                } else if (rule.type === 'custom') {
                    rule.selectionAttributeId = null;
                }
            });

            this.restoreRuleValueOptions();
            this.resolveExistingCustomRules();
            this.onElementUpdate();
        },

        async onAttributeValueChange(index, value) {
            const rule = this.rules[index];
            rule.valueIds = Array.isArray(value) ? value : [];
            this.onElementUpdate();
        },

        async onFilterTemplateChange() {
            await this.loadFilterAttributes();

            this.rules.forEach((rule, index) => {
                if (rule.type === 'standard') {
                    rule.attributeId = null;
                    rule.valueIds = [];
                    this.valueOptionsByRuleIndex[index] = [];
                } else if (rule.type === 'custom') {
                    rule.selectionAttributeId = null;
                }
            });

            this.resolveExistingCustomRules();
            this.onElementUpdate();
        },

        async loadValuesForRule(index, attributeId) {
            this.isLoadingValuesByRuleIndex[index] = true;

            const existingRules = this.element.config.rules.value || [];
            const existingValues = existingRules[index]?.valueIds || [];

            const selectedCategoryId = this.category;
            if (!selectedCategoryId) {
                this.isLoadingValuesByRuleIndex[index] = false;
                return;
            }

            const token = Shopware.Service('loginService').getToken();
            const headers = { headers: { Authorization: `Bearer ${token}` } };
            const httpClient = Shopware.Application.getContainer('init').httpClient;
            const url = '_action/rhae-tweakwise/filterAttributeValues';

            const response = await httpClient.get(url, {
                ...headers,
                params: { urlKey: attributeId, categoryId: selectedCategoryId }
            });

            if (response.status !== 200) {
                console.warn('Could not load attribute values');
            } else {
                const fetchedOptions = Array.isArray(response.data) ? response.data : [];
                const mergedOptions = [...fetchedOptions];

                existingValues.forEach((selectedValue) => {
                    const exists = mergedOptions.some((option) => option.value === selectedValue);

                    if (!exists) {
                        mergedOptions.push({
                            label: selectedValue,
                            value: selectedValue
                        });
                    }
                });

                this.valueOptionsByRuleIndex[index] = mergedOptions;
            }

            this.isLoadingValuesByRuleIndex[index] = false;
        },

        async loadCategoryOptions() {
            this.isLoading = true;

            const token = Shopware.Service('loginService').getToken();
            const headers = { headers: { Authorization: `Bearer ${token}` } };
            const httpClient = Shopware.Application.getContainer('init').httpClient;
            const url = '/_action/rhae-tweakwise/categoryTree';
            const response = await httpClient.get(url, headers);

            if (response.status !== 200) {
                console.warn('Could not load categories');
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
            const url = '/_action/rhae-tweakwise/filterTemplates';
            const response = await httpClient.get(url, headers);

            if (response.status !== 200) {
                console.warn('Could not load filter templates');
            } else {
                this.filterTemplates = response.data;
            }

            this.isLoading = false;
        },

        async loadFilterAttributes() {
            this.isLoadingAttributes = true;

            if (!this.category || !this.filterTemplate) {
                this.attributeOptions = [];
                this.isLoadingAttributes = false;
                return;
            }

            const selectedCategoryId = this.category;
            const filterTemplateId = this.filterTemplate;

            const token = Shopware.Service('loginService').getToken();
            const headers = { headers: { Authorization: `Bearer ${token}` } };
            const httpClient = Shopware.Application.getContainer('init').httpClient;
            const url = '/_action/rhae-tweakwise/filterAttributes';

            const response = await httpClient.get(url, {
                ...headers,
                params: {
                    categoryId: selectedCategoryId,
                    filterTemplateId: filterTemplateId
                }
            });

            if (response.status !== 200) {
                console.warn('Could not load filter attributes');
            } else {
                this.attributeOptions = Array.isArray(response.data) ? response.data : [];
                this.resolveExistingCustomRules();
            }

            this.isLoadingAttributes = false;
        },

        async loadSortTemplates() {
            this.isLoading = true;

            const token = Shopware.Service('loginService').getToken();
            const headers = { headers: { Authorization: `Bearer ${token}` } };
            const httpClient = Shopware.Application.getContainer('init').httpClient;
            const url = '/_action/rhae-tweakwise/sortTemplates';
            const response = await httpClient.get(url, headers);

            if (response.status !== 200) {
                console.warn('Could not load sort templates');
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
            const url = '/_action/rhae-tweakwise/builderTemplates';
            const response = await httpClient.get(url, headers);

            if (response.status !== 200) {
                console.warn('Could not load builder templates');
            } else {
                this.builderTemplates = response.data;
            }

            this.isLoading = false;
        }
    }
});
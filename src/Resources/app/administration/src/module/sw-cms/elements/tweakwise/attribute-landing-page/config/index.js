import template from './sw-cms-el-config-tweakwise-attribute-landing-page.html.twig';
import './sw-cms-el-config-tweakwise-attribute-landing-page.scss';

const CUSTOM_ATTRIBUTE_PREFIX = '__custom_attribute__:';

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
            customAttributeByRuleIndex: {},
            customValueByRuleIndex: {},
            showCustomAttributeInputByRuleIndex: {},
            showCustomValueInputByRuleIndex: {},
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
                if (!Array.isArray(rules[index].valueIds)) {
                    if (Array.isArray(rules[index].valueId)) {
                        rules[index].valueIds = rules[index].valueId;
                    } else if (rules[index].valueId) {
                        rules[index].valueIds = [rules[index].valueId];
                    } else {
                        rules[index].valueIds = [];
                    }
                }

                if (rules[index].attributeId) {
                    this.ensureCustomAttributeOptionExists(rules[index].attributeId);
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

        focusInputByRefName(refName) {
            this.$nextTick(() => {
                const fieldComponent = this.$refs[refName];

                if (!fieldComponent) {
                    return;
                }

                const fieldElement = Array.isArray(fieldComponent) ? fieldComponent[0] : fieldComponent;
                const input = fieldElement?.$el?.querySelector('input');

                if (input) {
                    input.focus();
                    input.select();
                }
            });
        },

        isCustomAttributeValue(value) {
            return typeof value === 'string' && value.startsWith(CUSTOM_ATTRIBUTE_PREFIX);
        },

        encodeCustomAttributeValue(value) {
            return `${CUSTOM_ATTRIBUTE_PREFIX}${value}`;
        },

        decodeCustomAttributeValue(value) {
            if (!this.isCustomAttributeValue(value)) {
                return value;
            }

            return value.substring(CUSTOM_ATTRIBUTE_PREFIX.length);
        },

        ensureCustomAttributeOptionExists(value) {
            if (!this.isCustomAttributeValue(value)) {
                return;
            }

            const exists = this.attributeOptions.some((option) => option.value === value);

            if (exists) {
                return;
            }

            this.attributeOptions = [
                ...this.attributeOptions,
                {
                    label: this.decodeCustomAttributeValue(value),
                    value
                }
            ];
        },

        restoreCustomAttributeOptionsFromRules() {
            const rules = this.element.config.rules.value || [];

            rules.forEach((rule) => {
                if (rule.attributeId) {
                    this.ensureCustomAttributeOptionExists(rule.attributeId);
                }
            });
        },

        restoreRuleValueOptions() {
            const rules = this.element.config.rules.value || [];

            rules.forEach((rule, index) => {
                if (rule.attributeId) {
                    this.ensureCustomAttributeOptionExists(rule.attributeId);
                    this.loadValuesForRule(index, rule.attributeId);
                }
            });
        },

        ensureRuleStructure() {
            if (!this.element.config.rules.value) {
                this.element.config.rules.value = [];
            }

            this.element.config.rules.value = this.element.config.rules.value.map((rule) => {
                let valueIds = [];

                if (Array.isArray(rule.valueIds)) {
                    valueIds = rule.valueIds;
                } else if (Array.isArray(rule.valueId)) {
                    valueIds = rule.valueId;
                } else if (rule.valueId) {
                    valueIds = [rule.valueId];
                }

                return {
                    attributeId: rule.attributeId || null,
                    valueIds
                };
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
                attributeId: null,
                valueIds: []
            });

            this.onElementUpdate();
        },

        removeRule(index) {
            this.rules.splice(index, 1);

            delete this.valueOptionsByRuleIndex[index];
            delete this.customAttributeByRuleIndex[index];
            delete this.customValueByRuleIndex[index];
            delete this.showCustomAttributeInputByRuleIndex[index];
            delete this.showCustomValueInputByRuleIndex[index];
            delete this.isLoadingValuesByRuleIndex[index];

            this.onElementUpdate();
        },

        showCustomAttributeInput(index) {
            const rule = this.rules[index];

            if (this.isCustomAttributeValue(rule.attributeId)) {
                this.customAttributeByRuleIndex[index] = this.decodeCustomAttributeValue(rule.attributeId);
            }

            this.showCustomAttributeInputByRuleIndex[index] = true;
            this.focusInputByRefName(`customAttributeInput-${index}`);
        },

        hideCustomAttributeInput(index) {
            this.showCustomAttributeInputByRuleIndex[index] = false;
            this.customAttributeByRuleIndex[index] = '';
        },

        showCustomValueInput(index) {
            this.showCustomValueInputByRuleIndex[index] = true;
            this.focusInputByRefName(`customValueInput-${index}`);
        },

        hideCustomValueInput(index) {
            this.showCustomValueInputByRuleIndex[index] = false;
            this.customValueByRuleIndex[index] = '';
        },

        addCustomAttribute(index) {
            const rule = this.rules[index];
            const customAttribute = (this.customAttributeByRuleIndex[index] || '').trim();

            if (!customAttribute) {
                return;
            }

            const storedValue = this.encodeCustomAttributeValue(customAttribute);

            this.ensureCustomAttributeOptionExists(storedValue);

            rule.attributeId = storedValue;
            rule.valueIds = [];
            this.valueOptionsByRuleIndex[index] = [];
            this.customValueByRuleIndex[index] = '';
            this.showCustomAttributeInputByRuleIndex[index] = false;
            this.showCustomValueInputByRuleIndex[index] = false;
            this.customAttributeByRuleIndex[index] = '';

            this.onElementUpdate();
        },

        async onAttributeChange(index, value) {
            const rule = this.rules[index];

            rule.attributeId = value;
            rule.valueIds = [];
            this.customValueByRuleIndex[index] = '';
            this.showCustomAttributeInputByRuleIndex[index] = false;
            this.showCustomValueInputByRuleIndex[index] = false;

            if (!rule.attributeId) {
                this.valueOptionsByRuleIndex[index] = [];
                this.onElementUpdate();
                return;
            }

            if (this.isCustomAttributeValue(rule.attributeId)) {
                this.ensureCustomAttributeOptionExists(rule.attributeId);
                await this.loadValuesForRule(index, rule.attributeId);
                this.onElementUpdate();
                return;
            }

            await this.loadValuesForRule(index, rule.attributeId);
            this.onElementUpdate();
        },

        async onCategoryChange() {
            await this.loadFilterAttributes();

            this.rules.forEach((rule, index) => {
                rule.valueIds = [];
                this.valueOptionsByRuleIndex[index] = [];
                this.customAttributeByRuleIndex[index] = '';
                this.customValueByRuleIndex[index] = '';
                this.showCustomAttributeInputByRuleIndex[index] = false;
                this.showCustomValueInputByRuleIndex[index] = false;
            });

            this.restoreRuleValueOptions();
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
                rule.attributeId = null;
                rule.valueIds = [];
                this.valueOptionsByRuleIndex[index] = [];
                this.customAttributeByRuleIndex[index] = '';
                this.customValueByRuleIndex[index] = '';
                this.showCustomAttributeInputByRuleIndex[index] = false;
                this.showCustomValueInputByRuleIndex[index] = false;
            });

            this.onElementUpdate();
        },

        addCustomValue(index) {
            const rule = this.rules[index];
            const customValue = (this.customValueByRuleIndex[index] || '').trim();

            if (!customValue) {
                return;
            }

            const currentOptions = this.valueOptionsByRuleIndex[index] || [];
            const existingOption = currentOptions.find((option) => option.value === customValue);

            if (!existingOption) {
                this.valueOptionsByRuleIndex[index] = [
                    ...currentOptions,
                    {
                        label: customValue,
                        value: customValue
                    }
                ];
            }

            if (!Array.isArray(rule.valueIds)) {
                rule.valueIds = [];
            }

            if (!rule.valueIds.includes(customValue)) {
                rule.valueIds = [
                    ...rule.valueIds,
                    customValue
                ];
            }

            this.customValueByRuleIndex[index] = '';
            this.showCustomValueInputByRuleIndex[index] = false;
            this.onElementUpdate();
        },

        async loadValuesForRule(index, attributeId) {
            this.isLoadingValuesByRuleIndex[index] = true;

            const existingRules = this.element.config.rules.value || [];
            const existingCustomValues = existingRules[index]?.valueIds || [];

            if (this.isCustomAttributeValue(attributeId)) {
                this.valueOptionsByRuleIndex[index] = existingCustomValues.map((value) => ({
                    label: value,
                    value
                }));
                this.isLoadingValuesByRuleIndex[index] = false;
                return;
            }

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

                existingCustomValues.forEach((selectedValue) => {
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
                this.restoreCustomAttributeOptionsFromRules();
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
                this.restoreCustomAttributeOptionsFromRules();
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
import template from './rhae-tweakwise-frontend-detail.html.twig';
import './rhae-tweakwise-frontend-detail.scss';

const { Component, Mixin, Context } = Shopware;
const { EntityCollection, Criteria } = Shopware.Data;
const { mapPropertyErrors } = Shopware.Component.getComponentHelper();

Component.register('rhae-tweakwise-frontend-detail', {
    template,

    inject: [
        'repositoryFactory',
        'context'
    ],

    mixins: [
        Mixin.getByName('notification')
    ],

    metaInfo() {
        return {
            title: this.$createTitle()
        };
    },

    data() {
        return {
            item: null,
            salesChannelDomains: null,
            isLoading: false,
            validToken: false,
            tokenError: null,
            suggestionsAvailable: false,
            recommendationsAvailable: false,
            processSuccess: false,
            salesChannelDomainIds: [],
            backendSyncOptions: []
        };
    },

    computed: {

        integrationTypes() {
            return [
                {
                    id: 1,
                    value: 'no-integration',
                    label: this.$tc('rhae-tweakwise-frontend.detail.label.integrationOptions.no-integration'),
                },
                {
                    id: 2,
                    value: 'pluginstudio',
                    label: this.$tc('rhae-tweakwise-frontend.detail.label.integrationOptions.pluginstudio'),
                },
                {
                    id: 3,
                    value: 'javascript',
                    label: this.$tc('rhae-tweakwise-frontend.detail.label.integrationOptions.javascript'),
                },
            ];
        },
        wayOfSearchTypes() {
            if (this.suggestionsAvailable) {
                return [
                    {
                        id: 1,
                        value: 'instant-search',
                        label: this.$tc('rhae-tweakwise-frontend.detail.label.wayOfSearchOptions.instant-search'),
                    },
                    {
                        id: 2,
                        value: 'suggestions',
                        label: this.$tc('rhae-tweakwise-frontend.detail.label.wayOfSearchOptions.suggestions'),
                    },
                ];
            } else {
                return [
                    {
                        id: 1,
                        value: 'instant-search',
                        label: this.$tc('rhae-tweakwise-frontend.detail.label.wayOfSearchOptions.instant-search'),
                    },
                ];
            }
        },
        checkoutSalesTypes() {
            return [
                {
                    id: 1,
                    value: 'no-checkout-sales',
                    label: this.$tc('rhae-tweakwise-frontend.detail.label.checkoutSalesOptions.no-checkout-sales'),
                },
                {
                    id: 2,
                    value: 'featured-products',
                    label: this.$tc('rhae-tweakwise-frontend.detail.label.checkoutSalesOptions.featured-products'),
                },
                {
                    id: 3,
                    value: 'recommendations',
                    label: this.$tc('rhae-tweakwise-frontend.detail.label.checkoutSalesOptions.recommendations'),
                },
            ];
        },
        repository() {
            return this.repositoryFactory.create('s_plugin_rhae_tweakwise_frontend');
        },

        salesChannelDomainRepository() {
            return this.repositoryFactory.create('sales_channel_domain');
        },

        salesChannelDomainIds: {
            get() {
                return this.salesChannelDomainIds || [];
            },
            set(salesChannelDomainIds) {
                this.salesChannelDomainIds = { ...this.salesChannelDomainIds, salesChannelDomainIds };
            },
        },

        defaultCriteria() {
            return new Criteria();
        }
    },

    created() {
        this.getItem();
        this.getSalesChannelDomains();
        this.getBackendSyncOptions();
    },

    methods: {
        async getBackendSyncOptions() {
            this.isLoading = true;
            const token = Shopware.Service('loginService').getToken();
            const headers = { headers: { Authorization: `Bearer ${token}` } };
            const httpClient = Shopware.Application.getContainer('init').httpClient;
            const url = `/_action/rhae-tweakwise/sync-options`;
            const response = await httpClient.get(url, headers);

            let options;
            if (response.status !== 200) {
                options = {
                    'main': [],
                    'options': [],
                    'customFields': []
                }
            } else {
                this.backendSyncOptions = response.data;
            }

            this.isLoading = false;
        },
        isChecked(group, option) {
            return !!(this.item?.backendSyncProperties
                && this.item.backendSyncProperties[group]
                && this.item.backendSyncProperties[group][option]);
        },
        async checkPossibilities() {
            this.validToken = false;
            this.suggestionsAvailable = false
            this.recommendationsAvailable = false

            if (this.item.token) {
                try {
                    this.isLoading = true;
                    const httpClient = Shopware.Application.getContainer('init').httpClient;
                    const headers = {
                        headers: {
                            Authorization: `Bearer ${Shopware.Service('loginService').getToken()}`,
                        },
                    };

                    const response = await httpClient.get('/_action/rhae-tweakwise/check-possibilities/' + this.item.token, headers);

                    this.validToken = response.data.validToken === true;
                    this.suggestionsAvailable = response.data.features.suggestions === true;
                    this.recommendationsAvailable = response.data.features.recommendations === true;
                } catch (e) {
                } finally {
                    this.tokenError = null;
                    if (!this.validToken) {
                        this.tokenError = {
                            detail: this.$tc('rhae-tweakwise-frontend.notification.tokenError')
                        };
                    }

                    this.isLoading = false;
                }
            }
        },

        getSalesChannelDomains() {
            this.salesChannelDomains = new EntityCollection(
                this.salesChannelDomainRepository.route,
                this.salesChannelDomainRepository.entityName,
                Context.api,
            );
        },

        getItem() {
            var criteria = new Criteria();
            criteria.addAssociation('salesChannelDomains');

            this.repository
                .get(this.$route.params.id, Context.api, criteria)
                .then((entity) => {
                    this.item = entity;
                    this.checkPossibilities();
                    // Zorg dat het hoofd-object altijd een object is
                    if (!this.item.backendSyncProperties || Array.isArray(this.item.backendSyncProperties)) {
                        this.item.backendSyncProperties = {};
                    }

                    // Zorg dat de groepen objecten zijn
                    const groups = ['main', 'properties', 'customFields'];
                    groups.forEach((g) => {
                        if (!this.item.backendSyncProperties[g] || Array.isArray(this.item.backendSyncProperties[g])) {
                            this.item.backendSyncProperties[g] = {};
                        }
                    });
                });
        },
        onChangeValue(value, fieldName, valueChange = true) {
            this.item[fieldName] = value;

            this.$emit('change-value', fieldName, value);
        },
        setSelectedProperty(value, fieldName, group, valueChange = true) {
            if (!this.item.backendSyncProperties || Array.isArray(this.item.backendSyncProperties)) {
                this.item.backendSyncProperties = {};
            }
            if (!this.item.backendSyncProperties[group] || Array.isArray(this.item.backendSyncProperties[group])) {
                this.item.backendSyncProperties[group] = {};
            }

            this.item.backendSyncProperties[group][String(fieldName)] = !!value;

            this.$emit('change-value', fieldName, value);
        },
        ensureOptionKeysExist() {
            const groups = ['main', 'properties', 'customFields'];
            groups.forEach(g => {
                if (!this.item.backendSyncProperties[g]) this.$set(this.item.backendSyncProperties, g, {});
                const list = this.backendSyncOptions?.[g] || [];
                list.forEach(opt => {
                    if (typeof this.item.backendSyncProperties[g][opt] === 'undefined') {
                        this.$set(this.item.backendSyncProperties[g], opt, false);
                    }
                });
            });
        },
        onChangeToggle(value, fieldName) {
            this.onChangeValue(value, fieldName, false);
        },
        async onClickSave() {
            this.isLoading = true;

            // this.item.backendSyncProperties = JSON.stringify(this.item.backendSyncProperties);

            try {
                await this.repository.save(this.item, Context.api);
                await this.getItem();
                this.processSuccess = true;
            } catch (exception) {
                this.createNotificationError({
                    title: this.$tc('rhae-tweakwise-frontend.notification.errorTitle'),
                    message: exception
                });
            } finally {
                this.isLoading = false;
            }
        },
        setSalesChannelDomains(salesChannelDomains) {
            this.salesChannelDomains = salesChannelDomains;
            this.item.salesChannelDomains = salesChannelDomains;
        },

        saveFinish() {
            this.processSuccess = false;
        },
    }
});

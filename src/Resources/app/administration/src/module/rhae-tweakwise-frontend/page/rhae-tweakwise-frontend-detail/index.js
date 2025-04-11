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
        };
    },

    computed: {
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
    },

    methods: {
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
                });
        },
        onChangeValue(value, fieldName, valueChange = true) {
            this.item[fieldName] = value;

            this.$emit('change-value', fieldName, value);
        },
        onChangeToggle(value, fieldName) {
            this.onChangeValue(value, fieldName, false);
        },
        onClickSave() {
            this.isLoading = true;
            this.repository
                .save(this.item, Context.api)
                .then(() => {
                    this.getItem();
                    this.isLoading = false;
                    this.processSuccess = true;
                })
                .catch((exception) => {
                    this.isLoading = false;
                    this.createNotificationError({
                        title: this.$tc('rhae-tweakwise-frontend.notification.errorTitle'),
                        message: exception
                    });
                });
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

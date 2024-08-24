import template from './rhae-tweakwise-feed-detail.html.twig';
import './rhae-tweakwise-feed-detail.scss';

const { Component, Mixin, Context } = Shopware;
const { EntityCollection, Criteria } = Shopware.Data;
const { mapPropertyErrors } = Shopware.Component.getComponentHelper();

Component.register('rhae-tweakwise-feed-detail', {
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
            salesChannelDomainIds: [],
            processes: {
                generateFeed: false,
            },
            processSuccess: {
                general: false,
                generateFeed: false,
            },
        };
    },

    computed: {
        repository() {
            return this.repositoryFactory.create('s_plugin_rhae_tweakwise_feed');
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
        dateFilter() {
            return Shopware.Filter.getByName('date');
        },
        defaultCriteria() {
            return new Criteria();
        },
    },

    created() {
        this.getItem();
        this.getSalesChannelDomains();
    },

    methods: {
        getSalesChannelDomains() {
            this.salesChannelDomains = new EntityCollection(
                this.salesChannelDomainRepository.route,
                this.salesChannelDomainRepository.entityName,
                Context.api,
            );
        },
        resetButtons() {
            this.processSuccess = {
                general: false,
                generateFeed: false,
            };
        },
        generateFeedNow() {
            this.isLoading = true;
            this.item.nextGenerationAt = null;
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
                        title: 'Unknown error',
                        message: exception
                    });
                });
        },
        getItem() {
            var criteria = new Criteria();
            criteria.addAssociation('salesChannelDomains');

            this.repository
                .get(this.$route.params.id, Context.api, criteria)
                .then((entity) => {
                    this.item = entity;
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
                        title: this.$tc('rhae-tweakwise-feed.notification.errorTitle'),
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

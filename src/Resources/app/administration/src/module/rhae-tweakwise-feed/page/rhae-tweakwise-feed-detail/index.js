import template from './rhae-tweakwise-feed-detail.html.twig';
import './rhae-tweakwise-feed-detail.scss';

const { Component, Mixin, Context, Utils } = Shopware;
const { EntityCollection, Criteria } = Shopware.Data;
const { mapPropertyErrors } = Shopware.Component.getComponentHelper();
const { dom, format } = Utils;

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
        feedTypes() {
            return [
                {
                    id: 1,
                    value: 'full',
                    label: this.$tc('rhae-tweakwise-feed.detail.label.typeOptions.full'),
                },
            ];
        },
        batchSizes() {
            return [
                {
                    id: 1,
                    value: '1',
                    label: '1',
                },
                {
                    id: 10,
                    value: '10',
                    label: '10',
                },
                {
                    id: 25,
                    value: '25',
                    label: '25',
                },
                {
                    id: 50,
                    value: '50',
                    label: '50',
                },
                {
                    id: 100,
                    value: '100',
                    label: '100',
                },
                {
                    id: 250,
                    value: '250',
                    label: '250',
                },
            ];
        },

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
        getFeedUrl() {
            return this.item.salesChannelDomains[0].url + '/tweakwise/feed-' + this.item.id + '.xml';
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

        convert(value, fieldName) {
            console.log(value);
        },

        onChangeToggleRevert(value, fieldName) {
            console.log(value);
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
        async copyLinkToClipboard() {
            if (this.item) {
                try {
                    await dom.copyStringToClipboard(this.getFeedUrl());
                    this.createNotificationSuccess({
                        message: this.$tc('sw-media.general.notification.urlCopied.message'),
                    });
                } catch (err) {
                    this.createNotificationError({
                        title: this.$tc('global.default.error'),
                        message: this.$tc('global.sw-field.notification.notificationCopyFailureMessage'),
                    });
                }
            }
        },
    }
});

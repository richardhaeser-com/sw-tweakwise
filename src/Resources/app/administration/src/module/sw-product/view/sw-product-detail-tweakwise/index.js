import template from './sw-product-detail-tweakwise.html.twig';
import './sw-product-detail-tweakwise.scss';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-product-detail-tweakwise', {
   template,
   namespaced: true,
   inject: ['repositoryFactory', 'feature', 'acl'],
   mixins: [
      Mixin.getByName('notification')
   ],

   data() {
      return {
         frontends: null,
         availability: [],
         tweakwiseData: [],
         productId: [],
         product: null,
         isLoading: false,
         feedsPerFrontend: {},
         syncingFeedId: null,
         showVariantSyncConfirm: false,
         variantSyncPendingFrontendId: null,
         variantSyncPendingFeedId: null,
         variantSyncAction: 'variants',
         variantSyncConfirmTitleKey: 'sw-product.detail.tab.syncVariantsConfirmTitle',
         variantSyncConfirmMessageKey: 'sw-product.detail.tab.syncVariantsConfirmMessage',
         variantSyncConfirmButtonKey: 'sw-product.detail.tab.syncVariantsConfirmButton',
      };
   },

   created() {
      this.createdComponent();
   },

   computed: {
      twFrontendRepository() {
         return this.repositoryFactory.create('s_plugin_rhae_tweakwise_frontend');
      },
      twFeedRepository() {
         return this.repositoryFactory.create('s_plugin_rhae_tweakwise_feed');
      },
   },

   methods: {
      createdComponent() {
         this.isLoading = true;
         this.getData();
         this.isLoading = false;
      },

      async getData() {
         const criteria = new Criteria(this.page, this.limit, this.term);
         criteria.addAssociation('salesChannelDomains');

         const httpClient = Shopware.Application.getContainer('init').httpClient;
         const token = Shopware.Service('loginService').getToken();
         const headers = { headers: { Authorization: `Bearer ${token}` } };

         const frontends = await this.twFrontendRepository.search(criteria, Shopware.Context.api);
         this.frontends = frontends;

         const feedsPerFrontend = {};
         for (const frontend of frontends) {
            const domainIds = (frontend.salesChannelDomains || []).map(d => d.id);
            if (domainIds.length > 0) {
               const feedCriteria = new Criteria();
               feedCriteria.addFilter(Criteria.equalsAny('salesChannelDomains.id', domainIds));
               const feedResult = await this.twFeedRepository.search(feedCriteria, Shopware.Context.api);
               feedsPerFrontend[frontend.id] = feedResult.map(f => ({ id: f.id, name: f.name, groupedProducts: !!f.groupedProducts }));
            } else {
               feedsPerFrontend[frontend.id] = [];
            }
         }
         this.feedsPerFrontend = feedsPerFrontend;

         const requests = frontends.map(f => {
            const url = `/_action/rhae-tweakwise/check-data/${f.id}/${this.$route.params.id}`;
            return httpClient.get(url, headers);
         });

         const responses = await Promise.all(requests);
         responses.forEach(r => {
            if (r.data.error) {
               this.availability[r.data.frontendId] = false;
            } else {
               this.availability[r.data.frontendId] = true;
               this.tweakwiseData[r.data.frontendId] = r.data.productData;
            }
            this.productId[r.data.frontendId] = r.data.productId;
            this.product = r.data.product;
         });
      },

      async onSyncForFeed(frontendId, feedId, isGroupedFeed = false) {
         if (isGroupedFeed && this.product?.childCount > 0) {
            this.variantSyncAction = 'variants';
            this.variantSyncConfirmTitleKey = 'sw-product.detail.tab.syncVariantsConfirmTitle';
            this.variantSyncConfirmMessageKey = 'sw-product.detail.tab.syncVariantsConfirmMessage';
            this.variantSyncConfirmButtonKey = 'sw-product.detail.tab.syncVariantsConfirmButton';
            this.variantSyncPendingFrontendId = frontendId;
            this.variantSyncPendingFeedId = feedId;
            this.showVariantSyncConfirm = true;
            return;
         }
         this.syncingFeedId = feedId;
         await this.doSync(frontendId, feedId);
         this.syncingFeedId = null;
      },

      async doSync(frontendId, feedId) {
         const token = Shopware.Service('loginService').getToken();
         const headers = { headers: { Authorization: `Bearer ${token}` } };
         const httpClient = Shopware.Application.getContainer('init').httpClient;
         const url = `/_action/rhae-tweakwise/sync-data/${frontendId}/${this.$route.params.id}?feedId=${feedId}`;

         const response = await httpClient.get(url, headers);
         const data = response.data;

         if (response.status === 200 && data.updated) {
            this.createNotificationSuccess({
               title: this.$tc('sw-product.detail.tab.syncSuccessTitle'),
               message: this.$tc('sw-product.detail.tab.syncSuccessMessage'),
            });
            await this.getData();
            return;
         }

         if (response.status === 200 && data.error && data.code === 'PARENT_NOT_IN_GROUPED_FEED') {
            this.variantSyncAction = 'variants';
            this.variantSyncConfirmTitleKey = 'sw-product.detail.tab.syncVariantsConfirmTitle';
            this.variantSyncConfirmMessageKey = 'sw-product.detail.tab.syncVariantsConfirmMessage';
            this.variantSyncConfirmButtonKey = 'sw-product.detail.tab.syncVariantsConfirmButton';
            this.variantSyncPendingFrontendId = frontendId;
            this.variantSyncPendingFeedId = feedId;
            this.showVariantSyncConfirm = true;
            return;
         }

         if (response.status === 200 && data.error && data.code === 'PARENT_USES_VARIANT_LISTING') {
            this.variantSyncAction = 'variants';
            this.variantSyncConfirmTitleKey = 'sw-product.detail.tab.parentUsesVariantListingConfirmTitle';
            this.variantSyncConfirmMessageKey = 'sw-product.detail.tab.parentUsesVariantListingConfirmMessage';
            this.variantSyncConfirmButtonKey = 'sw-product.detail.tab.syncVariantsConfirmButton';
            this.variantSyncPendingFrontendId = frontendId;
            this.variantSyncPendingFeedId = feedId;
            this.showVariantSyncConfirm = true;
            return;
         }

         if (response.status === 200 && data.error && data.code === 'PARENT_HAS_MAIN_VARIANT') {
            this.variantSyncAction = 'mainVariant';
            this.variantSyncConfirmTitleKey = 'sw-product.detail.tab.parentHasMainVariantConfirmTitle';
            this.variantSyncConfirmMessageKey = 'sw-product.detail.tab.parentHasMainVariantConfirmMessage';
            this.variantSyncConfirmButtonKey = 'sw-product.detail.tab.syncMainVariantConfirmButton';
            this.variantSyncPendingFrontendId = frontendId;
            this.variantSyncPendingFeedId = feedId;
            this.showVariantSyncConfirm = true;
            return;
         }

         if (response.status === 200 && data.error && data.code === 'CHILD_EXCLUDED_FROM_FEED') {
            this.createNotificationWarning({
               title: this.$tc('sw-product.detail.tab.syncSkippedChildTitle'),
               message: this.$tc('sw-product.detail.tab.syncSkippedChildMessage'),
            });
            return;
         }

         this.createNotificationWarning({
            title: this.$tc('sw-product.detail.tab.syncFailedTitle'),
            message: this.$tc('sw-product.detail.tab.syncFailedMessage'),
         });
         console.warn(response);
      },

      onConfirmVariantSync() {
         const frontendId = this.variantSyncPendingFrontendId;
         const feedId = this.variantSyncPendingFeedId;
         const action = this.variantSyncAction;
         this.showVariantSyncConfirm = false;
         this.variantSyncPendingFrontendId = null;
         this.variantSyncPendingFeedId = null;
         this.syncingFeedId = feedId;
         const promise = action === 'mainVariant'
            ? this.doSyncMainVariant(frontendId, feedId)
            : this.doSyncVariants(frontendId, feedId);
         promise.finally(() => {
            this.syncingFeedId = null;
         });
      },

      onCancelVariantSync() {
         this.showVariantSyncConfirm = false;
         this.variantSyncPendingFrontendId = null;
         this.variantSyncPendingFeedId = null;
      },

      async doSyncMainVariant(frontendId, feedId) {
         const token = Shopware.Service('loginService').getToken();
         const headers = { headers: { Authorization: `Bearer ${token}` } };
         const httpClient = Shopware.Application.getContainer('init').httpClient;
         const url = `/_action/rhae-tweakwise/sync-data/${frontendId}/${this.$route.params.id}?feedId=${feedId}&syncMainVariant=true`;

         const response = await httpClient.get(url, headers);
         const data = response.data;

         if (response.status === 200 && data.updated) {
            this.createNotificationSuccess({
               title: this.$tc('sw-product.detail.tab.syncMainVariantSuccessTitle'),
               message: this.$tc('sw-product.detail.tab.syncMainVariantSuccessMessage'),
            });
            await this.getData();
            return;
         }

         this.createNotificationWarning({
            title: this.$tc('sw-product.detail.tab.syncFailedTitle'),
            message: this.$tc('sw-product.detail.tab.syncFailedMessage'),
         });
         console.warn(response);
      },

      async doSyncVariants(frontendId, feedId) {
         const token = Shopware.Service('loginService').getToken();
         const headers = { headers: { Authorization: `Bearer ${token}` } };
         const httpClient = Shopware.Application.getContainer('init').httpClient;
         const url = `/_action/rhae-tweakwise/sync-data/${frontendId}/${this.$route.params.id}?feedId=${feedId}&syncVariants=true`;

         const response = await httpClient.get(url, headers);
         const data = response.data;

         if (response.status === 200 && data.updated) {
            this.createNotificationSuccess({
               title: this.$tc('sw-product.detail.tab.syncVariantsSuccessTitle'),
               message: this.$tc('sw-product.detail.tab.syncVariantsSuccessMessage'),
            });
            await this.getData();
            return;
         }

         this.createNotificationWarning({
            title: this.$tc('sw-product.detail.tab.syncFailedTitle'),
            message: this.$tc('sw-product.detail.tab.syncFailedMessage'),
         });
         console.warn(response);
      },
   }
});

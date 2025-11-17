import template from './sw-product-detail-tweakwise.html.twig';

const { Component, Mixin, Context } = Shopware;
const { EntityCollection, Criteria } = Shopware.Data;

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
         backendSyncOptions: []
      };
   },

   created() {
      this.createdComponent();
   },

   computed: {
      twFeedRepository() {
         return this.repositoryFactory.create('s_plugin_rhae_tweakwise_frontend');
      },
   },

   methods: {
      createdComponent() {
         this.isLoading = true;
         this.getData();
      },

      async getData() {
         const criteria = new Criteria(this.page, this.limit, this.term);
         const httpClient = Shopware.Application.getContainer('init').httpClient;

         const frontends = await this.twFeedRepository.search(criteria, Shopware.Context.api);
         this.frontends = frontends;

         const token = Shopware.Service('loginService').getToken();
         const headers = { headers: { Authorization: `Bearer ${token}` } };

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

         return true;

      },

      async onSync(frontendId, productId) {
         const token = Shopware.Service('loginService').getToken();
         const headers = { headers: { Authorization: `Bearer ${token}` } };
         const httpClient = Shopware.Application.getContainer('init').httpClient;
         const url = `/_action/rhae-tweakwise/sync-data/${frontendId}/${this.$route.params.id}`;
         const response = await httpClient.get(url, headers);
         if (response.status === 200 && response.data.updated) {
            this.createNotificationSuccess({
               title: this.$tc('sw-product.detail.tab.syncSuccessTitle'),
               message: this.$tc('sw-product.detail.tab.syncSuccessMessage')
            });
            await this.getData();
         } else {
            this.createNotificationWarning({
               title: this.$tc('sw-product.detail.tab.syncFailedTitle'),
               message: this.$tc('sw-product.detail.tab.syncFailedMessage')
            });
            console.warn(response);
         }
      }
   }
});
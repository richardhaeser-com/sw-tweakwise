const { Component } = Shopware;

import template from '../rhae-tweakwise-feed-detail/rhae-tweakwise-feed-detail.html.twig';

Component.extend('rhae-tweakwise-feed-create', 'rhae-tweakwise-feed-detail', {
    template,

    methods: {
        getItem() {
            this.item = this.repository.create(Shopware.Context.api);
            this.item.includeHiddenCategories = false;

            this.item.limit = 10;

            this.isLoading = false;
        },

        onClickSave() {
            this.isLoading = true;

            this.repository
                .save(this.item, Shopware.Context.api)
                .then(() => {
                    this.isLoading = false;
                    this.$router.push({ name: 'rhae.tweakwise.feed.detail', params: { id: this.item.id } });
                }).catch((exception) => {
                    this.isLoading = false;
                    this.createNotificationError({
                        title: this.$tc('rhae-tweakwise.notification.errorTitle'),
                        message: exception
                    });
            });
        }
    }
});

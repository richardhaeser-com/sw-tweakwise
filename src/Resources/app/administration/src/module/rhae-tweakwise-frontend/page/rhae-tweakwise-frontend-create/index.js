const { Component } = Shopware;

import template from '../rhae-tweakwise-frontend-detail/rhae-tweakwise-frontend-detail.html.twig';

Component.extend('rhae-tweakwise-frontend-create', 'rhae-tweakwise-frontend-detail', {
    template,

    methods: {
        getItem() {
            this.item = this.repository.create(Shopware.Context.api);
            this.isLoading = false;
        },

        onClickSave() {
            this.isLoading = true;

            this.repository
                .save(this.item, Shopware.Context.api)
                .then(() => {
                    this.isLoading = false;
                    this.$router.push({ name: 'rhae.tweakwise.frontend.detail', params: { id: this.item.id } });
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

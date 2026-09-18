import template from './act-stock-importer-info.html.twig';
import './act-stock-importer-info.scss';

const { Component } = Shopware;

const HOW_TO_ITEM_COUNT = 5;

Component.register('act-stock-importer-info', {
    template,

    computed: {
        howToItems() {
            return Array.from(
                { length: HOW_TO_ITEM_COUNT },
                (_, index) => `act-stock-importer.settings.info.howTo.item${index + 1}`,
            );
        },
    },
});

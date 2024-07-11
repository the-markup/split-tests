import { cleanForSlug } from '@wordpress/url';

import "./index.scss";

jQuery(document).ready($ => {
    if (typeof acf === 'undefined') {
        console.log('ACF is undefined');
        return;
    }

    $('.acf-field[data-name="headline"] input[type="text"]').change(e => {
        const headline = $(e.target).val();
        const url_slug = cleanForSlug(headline);
        $('.acf-field[data-name="url_slug"] input[type="text"]').val(url_slug);
    });
});
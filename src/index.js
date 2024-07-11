import { cleanForSlug } from '@wordpress/url';
import { useSelect } from '@wordpress/data';

import "./index.scss";

jQuery(document).ready(async $ => {
    if (typeof acf === 'undefined') {
        console.log('ACF is undefined');
        return;
    }

    // Update the URL slug to be a sanitized version of the headline
    $('.acf-field[data-name="headline"] input[type="text"]').change(e => {
        const headline = $(e.target).val();
        const url_slug = cleanForSlug(headline);
        $('.acf-field[data-name="url_slug"] input[type="text"]').val(url_slug);
    });

    // Update the button to edit title test details
    if (targetPostId) {
        $('.title-test-details').attr('href', `/wp-admin/post.php?post=${targetPostId}&action=edit#acf-group_668d4e50066d3`);
        $('.title-test-details').addClass('button-primary');
    } else {
        $('.title-test-details').hide();
        $('.title-test-details').after('<p>Post Title Split Tests can be created by editing Posts directly.</p><p>Look for "Split Tests: Title" below the content editor.</p>');
    }

});
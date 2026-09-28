(function($) {

    function escapeHtml(str) {
        return $('<div>').text(str == null ? '' : str).html();
    }

    function buildReviewCardHtml(review) {
        return '<div class="review-card" data-review-id="' + review.id + '" style="border:1px solid #ddd; padding:15px; margin-bottom:15px;">' +
            '<div class="review-rating">' + review.stars + '</div>' +
            '<h4>' + escapeHtml(review.title) + '</h4>' +
            '<p>' + escapeHtml(review.content) + '</p>' +
            '<small>' + escapeHtml(review.date) + ' — ' + escapeHtml(review.author) + '</small>' +
            '<p><a href="#" class="delete-review-link" data-review-id="' + review.id + '">Dzēst atsauksmi</a></p>' +
            '</div>';
    }

    //Priekš "Atsauksmes (n)" WooCommerce tab nav, lai uzskaita jaunu atsauksmi uzreiz
    function bumpReviewsTabCount() {
        var tabLink = $('li.reviews_tab > a').first();
        if (!tabLink.length) {
            return;
        }
        var text = tabLink.text();
        var match = text.match(/\((\d+)\)/);
        if (match) {
            var newCount = parseInt(match[1], 10) + 1;
            tabLink.text(text.replace(/\(\d+\)/, '(' + newCount + ')'));
        }
    }

    $(document).ready(function() {

        //Kad forma tiek iesniegta
        $(document).on('submit', '.custom-review-form', function(e) {
            e.preventDefault();

            var form = $(this);
            var submitButton = form.find('input[type="submit"]');
            var responseDiv = form.find('.review-form-response');
            var checkedRating = form.find('input[name="review_rating"]:checked');

            responseDiv.html('');

            //Rating check
            if (checkedRating.length === 0) {
                responseDiv.html('<p style="color: red;">Lūdzu, izvēlieties vērtējumu no 1 līdz 5 zvaigznēm.</p>');
                return;
            }

            submitButton.prop('disabled', true);
            submitButton.val('Notiek iesniegšana...');

            $.ajax({
                url: custom_reviews_obj.ajax_url,
                type: 'POST',
                data: {
                    action: 'submit_product_review',
                    nonce: custom_reviews_obj.nonce,
                    review_product_id: form.find('input[name="review_product_id"]').val(),
                    review_title: form.find('input[name="review_title"]').val(),
                    review_content: form.find('textarea[name="review_content"]').val(),
                    review_rating: checkedRating.val()
                },
                success: function(response) {
                    if (response.success) {
                        //Veiksmīga iesniegšana
                        responseDiv.html('<p style="color: green;">' + response.data.message + '</p>');
                        form[0].reset();

                        //Ja uzreiz publicē, uzreiz sarakstā ieliek
                        if (response.data.review) {
                            var list = $('#atsauksmes-list');
                            list.find('.no-reviews-msg').remove();
                            list.prepend(buildReviewCardHtml(response.data.review));
                            bumpReviewsTabCount();
                        }
                    } else {
                        //Kļūda
                        responseDiv.html('<p style="color: red;">' + response.data.message + '</p>');
                    }
                },
                error: function() {
                    responseDiv.html('<p style="color: red;">Notika tehniska kļūda. Lūdzu, mēģiniet vēlreiz.</p>');
                },
                complete: function() {
                    submitButton.prop('disabled', false);
                    submitButton.val('Iesniegt atsauksmi');
                }
            });
        });

        //Savas atsauksmes dzēšana
        $(document).on('click', '.delete-review-link', function(e) {
            e.preventDefault();

            var link = $(this);
            var reviewId = link.data('review-id');
            var card = link.closest('.review-card');

            if (!window.confirm('Vai tiešām vēlaties dzēst šo atsauksmi?')) {
                return;
            }

            link.text('Dzēš...');

            $.ajax({
                url: custom_reviews_obj.ajax_url,
                type: 'POST',
                data: {
                    action: 'delete_product_review',
                    nonce: custom_reviews_obj.delete_nonce,
                    review_id: reviewId
                },
                success: function(response) {
                    if (response.success) {
                        card.fadeOut(200, function() {
                            $(this).remove();
                        });
                    } else {
                        alert(response.data.message);
                        link.text('Dzēst atsauksmi');
                    }
                },
                error: function() {
                    alert('Notika tehniska kļūda. Lūdzu, mēģiniet vēlreiz.');
                    link.text('Dzēst atsauksmi');
                }
            });
        });

    });
})(jQuery);

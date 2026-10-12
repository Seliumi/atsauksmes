(function($) {
    //Pašreizējā kārtošana / zvaigžņu filtrs / lappuse
    var state = { sort: 'date_desc', rating: 0, page: 1 };
 
    function resetState() {
        state = { sort: 'date_desc', rating: 0, page: 1 };
        $('#atsauksmes-sort').val('date_desc');
        $('#atsauksmes-filter-rating').val('0');
    }
 
    //Atjauno skaitu "Atsauksmes (n)" WooCommerce tab nav
    function setReviewsTabCount(count) {
        var tabLink = $('li.reviews_tab > a').first();
        if (tabLink.length) {
            tabLink.text(tabLink.text().replace(/\(\d+\)/, '(' + count + ')'));
        }
    }
 
    //Ielādē atsauksmju sarakstu no servera ar pašreizējiem iestatījumiem
    function loadReviews(scrollToTop) {
        var list = $('#atsauksmes-list');
 
        list.css('opacity', 0.5);
 
        $.ajax({
            url: custom_reviews_obj.ajax_url,
            type: 'POST',
            data: {
                action: 'atsauksmes_load_reviews',
                product_id: $('.custom-reviews').data('product-id'),
                sort: state.sort,
                rating: state.rating,
                page: state.page
            },
            success: function(response) {
                if (!response.success) {
                    return;
                }
 
                //Var labot lappuses nr
                state.page = response.data.page;
 
                list.html(response.data.list_html);
                $('#atsauksmes-pagination').html(response.data.pagination_html);
                $('.atsauksmes-controls').toggle(response.data.total_count > 0);
                setReviewsTabCount(response.data.total_count);
 
                if (scrollToTop) {
                    $('.custom-reviews')[0].scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            },
            error: function() {
                list.prepend('<p style="color: red;">Neizdevās ielādēt atsauksmes. Lūdzu, mēģiniet vēlreiz.</p>');
            },
            complete: function() {
                list.css('opacity', 1);
            }
        });
    }


    $(document).ready(function() {

        resetState();
 
        //Kārtošanas maiņa
        $(document).on('change', '#atsauksmes-sort', function() {
            state.sort = $(this).val();
            state.page = 1;
            loadReviews();
        });
 
        //Zvaigžņu filtra maiņa
        $(document).on('change', '#atsauksmes-filter-rating', function() {
            state.rating = parseInt($(this).val(), 10) || 0;
            state.page = 1;
            loadReviews();
        });
 
        //Lappušu navigācija
        $(document).on('click', '.atsauksmes-page-link', function(e) {
            e.preventDefault();
            state.page = parseInt($(this).data('page'), 10) || 1;
            loadReviews(true);
        });

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

                        //Ja uzreiz publicē, uzreiz pārlādē sarakstu
                        if (response.data.status === 'publish') {
                            resetState();
                            loadReviews();
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
                        loadReviews();
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

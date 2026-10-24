/* Browse page: filters update the product grid without reloading the page,
   and the URL keeps the filters so a filtered view can be shared or bookmarked. */
(function ($, SM) {
    'use strict';

    var $root = $('#browse-root');
    if (!$root.length) { return; }

    var $form = $('#filters');
    var $grid = $('#product-grid');
    var icon = $('#tile-icon').html();
    var request = null;
    var page = 1;

    // Restore filters from the URL (?max_price=20000&must[]=5g ...)
    var params = new URLSearchParams(window.location.search);
    params.forEach(function (value, key) {
        var $field = $form.find('[name="' + key + '"]');
        if (!$field.length) { $field = $('[name="' + key + '"]'); }
        if ($field.is(':checkbox')) {
            $field.filter('[value="' + CSS.escape(value) + '"]').prop('checked', true);
        } else if ($field.length) {
            $field.val(value);
        }
    });
    page = parseInt(params.get('page') || '1', 10) || 1;

    function filters() {
        var data = { category: $root.data('category'), page: page };
        $.each($form.serializeArray(), function (_, f) {
            if (f.value === '') { return; }
            if (f.name.slice(-2) === '[]') {
                var key = f.name.slice(0, -2);   // jQuery adds the [] itself when sending arrays
                (data[key] = data[key] || []).push(f.value);
            } else {
                data[f.name] = f.value;
            }
        });
        data.sort = $('#sort').val();
        return data;
    }

    function load() {
        var data = filters();
        if (request) { request.abort(); }   // only the latest filter change matters
        $grid.addClass('is-loading');

        request = SM.api('GET', $root.data('api'), data)
            .done(function (res) {
                render(res);
                updateUrl(data);
            })
            .fail(function (xhr) {
                if (xhr.statusText === 'abort') { return; }
                $('#results-count').text(SM.errorMessage(xhr));
            })
            .always(function () { $grid.removeClass('is-loading'); });
    }

    function render(res) {
        $('#results-count').text(res.total + ' product' + (res.total === 1 ? '' : 's'));
        $grid.empty();

        if (!res.products.length) {
            $grid.html('<div class="panel empty-state"><h2>Nothing matches these filters</h2><p class="muted">Remove a must-have or widen the price range.</p></div>');
        }

        $.each(res.products, function (_, p) {
            var thumb = p.image_url ? '<img src="' + SM.esc(p.image_url) + '" alt="" loading="lazy">' : icon;
            var mrp = p.mrp && p.mrp > p.price ? ' <span class="mrp num">' + SM.inr(p.mrp) + '</span>' : '';
            $grid.append(
                '<a class="product-tile" href="' + SM.esc(p.url) + '">' +
                '<div class="thumb">' + thumb + '</div>' +
                '<h3>' + SM.esc(p.name) + '</h3>' +
                '<div><span class="price">' + SM.inr(p.price) + '</span>' + mrp + (p.approx ? ' <span class="tag tag-approx">approx.</span>' : '') + '</div>' +
                '<div class="meta num">' + (p.rating !== null ? 'Rated ' + p.rating + ' from ' + SM.number(p.review_count) + ' reviews. ' : '') +
                (p.offers_count > 1 ? 'Cheapest of ' + p.offers_count + ' stores (' + SM.esc(p.store) + ').' : 'At ' + SM.esc(p.store) + '.') +
                '</div></a>');
        });

        var $pager = $('#pager').empty();
        if (res.last_page > 1) {
            if (res.page > 1) { $pager.append('<button type="button" class="btn btn-secondary btn-small" data-page="' + (res.page - 1) + '">Previous</button>'); }
            $pager.append('<span class="muted num" style="align-self:center">Page ' + res.page + ' of ' + res.last_page + '</span>');
            if (res.page < res.last_page) { $pager.append('<button type="button" class="btn btn-secondary btn-small" data-page="' + (res.page + 1) + '">Next</button>'); }
        }
    }

    function updateUrl(data) {
        var qs = new URLSearchParams();
        $.each(data, function (key, value) {
            if (key === 'category' || (key === 'page' && value === 1) || (key === 'sort' && value === 'best')) { return; }
            if (Array.isArray(value)) {
                $.each(value, function (_, v) { qs.append(key + '[]', v); });
            } else {
                qs.append(key, value);
            }
        });
        var url = window.location.pathname + (qs.toString() ? '?' + qs.toString() : '');
        window.history.replaceState(null, '', url);
    }

    var reload = function () { page = 1; load(); };
    $form.on('change', 'input[type=checkbox], select', reload);
    $form.on('input', 'input[type=number]', SM.debounce(reload, 400));   // wait until typing pauses
    $('#sort').on('change', reload);
    $form.on('submit', function (e) { e.preventDefault(); reload(); });
    $form.on('reset', function () { setTimeout(reload, 0); });

    $('#pager').on('click', '[data-page]', function () {
        page = parseInt($(this).data('page'), 10);
        load();
        $('html, body').animate({ scrollTop: $root.offset().top }, 150);
    });

    $('.filter-toggle').on('click', function () {
        var open = !$form.hasClass('open');
        $form.toggleClass('open', open);
        $(this).attr('aria-expanded', open);
    });

    load();
})(jQuery, window.SM);

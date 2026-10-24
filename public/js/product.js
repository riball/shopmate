/* Product page: price-history chart (Chart.js) and the price-drop alert form. */
(function ($, SM) {
    'use strict';

    var $root = $('#product-root');
    if (!$root.length) { return; }

    // One line per store; colours chosen to stay distinguishable for colour-blind readers.
    var COLOURS = ['#1B2840', '#C9820A', '#0D7560', '#5B4BC4', '#B42318'];

    SM.api('GET', $root.data('history')).done(function (res) {
        var dates = [];
        $.each(res.series, function (_, s) {
            $.each(s.points, function (_, p) { if (dates.indexOf(p.date) === -1) { dates.push(p.date); } });
        });
        dates.sort();

        if (dates.length < 2) {
            $('#history-note').text('Not enough price history yet. It builds up as prices are checked.');
            return;
        }

        var datasets = $.map(res.series, function (s, i) {
            var byDate = {};
            $.each(s.points, function (_, p) { byDate[p.date] = p.price; });
            return {
                label: s.store,
                data: dates.map(function (d) { return byDate[d] === undefined ? null : byDate[d]; }),
                borderColor: COLOURS[i % COLOURS.length],
                backgroundColor: COLOURS[i % COLOURS.length],
                borderWidth: 2,
                stepped: 'before',   // a price holds until the next change
                spanGaps: true,
                pointRadius: 2
            };
        });

        var labels = dates.map(function (d) {
            return new Date(d + 'T00:00:00').toLocaleDateString('en-IN', { day: 'numeric', month: 'short' });
        });

        new window.Chart(document.getElementById('history-chart'), {
            type: 'line',
            data: { labels: labels, datasets: datasets },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 12 } },
                    tooltip: { callbacks: { label: function (ctx) { return ctx.dataset.label + ': ' + SM.inr(ctx.parsed.y); } } }
                },
                scales: {
                    y: { ticks: { callback: function (v) { return SM.inr(v); } }, grid: { color: '#E7EBEF' } },
                    x: { grid: { display: false }, ticks: { maxTicksLimit: 8 } }
                }
            }
        });

        var lowest = Math.min.apply(null, $.map(res.series, function (s) { return $.map(s.points, function (p) { return p.price; }); }));
        $('#history-note').text('Lowest price in the last 90 days: ' + SM.inr(lowest) + '.');
    });

    $('#alert-form').on('submit', function (e) {
        e.preventDefault();
        var $btn = $(this).find('button').prop('disabled', true);
        SM.api('POST', $root.data('alerts'), {
            product_id: $root.data('product'),
            email: $.trim($('#alert-email').val()),
            target_price: $('#alert-price').val()
        }).done(function (res) {
            show(res.message, true);
        }).fail(function (xhr) {
            show(SM.errorMessage(xhr), false);
        }).always(function () {
            $btn.prop('disabled', false);
        });
    });

    function show(text, ok) {
        $('#alert-message').removeClass('alert-ok alert-error').addClass(ok ? 'alert-ok' : 'alert-error').text(text).prop('hidden', false);
    }
})(jQuery, window.SM);

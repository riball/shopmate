/* Shared helpers for every page (jQuery). */
(function ($) {
    'use strict';

    var inrFormat = new Intl.NumberFormat('en-IN', { maximumFractionDigits: 0 });

    window.SM = {
        /** 1799 -> "₹1,799" (Indian digit grouping: 1,50,000) */
        inr: function (value) {
            return value === null || value === undefined ? '—' : '₹' + inrFormat.format(value);
        },

        number: function (value) {
            return value === null || value === undefined ? '—' : inrFormat.format(value);
        },

        /** Escape text before putting it into HTML, so data can never inject markup. */
        esc: function (text) {
            return $('<div>').text(text === null || text === undefined ? '' : String(text)).html();
        },

        /** JSON request helper. */
        api: function (method, url, data) {
            return $.ajax({
                url: url,
                method: method,
                data: method === 'GET' ? data : JSON.stringify(data || {}),
                contentType: method === 'GET' ? undefined : 'application/json',
                dataType: 'json',
                headers: { 'Accept': 'application/json' },
                traditional: false
            });
        },

        /** Turn a failed request into a message a shopper can act on. */
        errorMessage: function (xhr) {
            var json = xhr && xhr.responseJSON;
            if (json && json.errors) {
                var first = Object.keys(json.errors)[0];
                return json.errors[first][0];
            }
            if (json && json.message) { return json.message; }
            if (xhr && xhr.status === 429) { return 'Too many requests in a minute. Wait a moment and try again.'; }
            if (xhr && xhr.status === 0) { return 'Could not reach the server. Check your connection and try again.'; }
            return 'Something went wrong on the server. Try again in a moment.';
        },

        toast: function (message) {
            var $toast = $('.toast');
            $toast.text(message).prop('hidden', false);
            clearTimeout($toast.data('timer'));
            $toast.data('timer', setTimeout(function () { $toast.prop('hidden', true); }, 3500));
        },

        /** Run fn at most once per `wait` ms of quiet (for typing in filters). */
        debounce: function (fn, wait) {
            var timer;
            return function () {
                var args = arguments, ctx = this;
                clearTimeout(timer);
                timer = setTimeout(function () { fn.apply(ctx, args); }, wait);
            };
        }
    };

    // Mobile menu
    $(document).on('click', '.nav-toggle', function () {
        var $nav = $('#site-nav');
        var open = !$nav.hasClass('open');
        $nav.toggleClass('open', open);
        $(this).attr('aria-expanded', open);
    });
})(jQuery);

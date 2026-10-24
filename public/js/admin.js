/* SEO editor: live character counters and a search-result preview. */
(function ($) {
    'use strict';

    var $serp = $('#serp');
    if (!$serp.length) { return; }

    function truncate(text, max) {
        return text.length > max ? text.slice(0, max - 1).trim() + '…' : text;
    }

    function update() {
        var title = $.trim($('#title').val()) || $serp.data('default-title');
        var desc = $.trim($('#description').val()) || $serp.data('default-description');
        $('#serp-title').text(truncate(title, 60));
        $('#serp-desc').text(truncate(desc, 155));

        $('.counter').each(function () {
            var $c = $(this);
            var length = $('#' + $c.data('for')).val().length;
            var max = $c.data('max');
            $c.text(length + ' / ' + max).toggleClass('over', length > max);
        });
    }

    $('#title, #description').on('input', update);
    update();
})(jQuery);

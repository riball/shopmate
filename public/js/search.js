/* Search: the request form on the home page, and the live results page. */
(function ($, SM) {
    'use strict';

    /* ------------------------------------------------------------------
       Home page: send the request, then go to its results page
       ------------------------------------------------------------------ */
    var $form = $('#search-form');
    if ($form.length) {
        var $query = $('#query');
        var $error = $('#search-error');

        $('.example-chip').on('click', function () {
            $query.val($(this).data('example')).trigger('focus');
        });

        $query.on('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); $form.trigger('submit'); }
        });

        $form.on('submit', function (e) {
            e.preventDefault();
            var text = $.trim($query.val());
            if (text.length < 3) {
                $error.text('Describe what you want in a few words, e.g. "earbuds under ₹2,000".').prop('hidden', false);
                $query.trigger('focus');
                return;
            }
            $error.prop('hidden', true);
            var $button = $form.find('button[type=submit]').prop('disabled', true).text('Starting…');

            SM.api('POST', '/api/searches', { query: text })
                .done(function (res) { window.location.href = res.url; })
                .fail(function (xhr) {
                    $error.text(SM.errorMessage(xhr)).prop('hidden', false);
                    $button.prop('disabled', false).text('Find my 3 picks');
                });
        });
    }

    /* ------------------------------------------------------------------
       Results page: poll for trace steps, then render the picks
       ------------------------------------------------------------------ */
    var $root = $('#search-root');
    if (!$root.length) { return; }

    var api = $root.data('api');
    var lastSeq = 0;
    var startedAt = Date.now();
    var offers = {};          // offer id -> details for the cart modal

    var NODE_NAMES = {
        planner: 'Plan', plan: 'Plan', clarify: 'Clarify', search: 'Search', replan: 'Replan', replan_pick: 'Replan',
        rank: 'Rank', explain: 'Explain', rewrite: 'Rewrite', verify: 'Verify', respond: 'Respond', error: 'Error'
    };
    var PROGRESS = {
        planner: 'Searching listings…', clarify: 'Waiting for your answer…', search: 'Ranking the matches…',
        replan: 'Relaxing the search…', rank: 'Writing explanations…', explain: 'Checking every statement…',
        verify: 'Almost done…'
    };
    var STATUS_TEXT = {
        met: 'Confirmed by the listing',
        unknown: 'Couldn\'t confirm: the listing doesn\'t say',
        conflict: 'Couldn\'t confirm: the listing contradicts itself',
        unsupported: 'Couldn\'t confirm: no automatic check for this'
    };

    function poll() {
        SM.api('GET', api, { after: lastSeq })
            .done(function (data) {
                renderSteps(data.steps);
                renderWorkOrder(data.work_order);
                renderReplans(data.replans);
                $('#trace-meta').text(traceMeta(data));

                switch (data.status) {
                    case 'done': return renderDone(data);
                    case 'needs_clarification': return renderClarify(data);
                    case 'no_results': return renderEmpty(data);
                    case 'failed': return renderFailed();
                    case 'queued':
                        $('#progress-text').text(data.queued_seconds > 8
                            ? 'Still waiting to start. If this stays here, the background worker isn\'t running (php artisan queue:work).'
                            : 'Waiting to start…');
                        break;
                }
                schedule();
            })
            .fail(function (xhr) {
                if (xhr.status === 404) { return renderFailed('This search no longer exists.'); }
                schedule(3000);
            });
    }

    function schedule(ms) {
        var elapsed = Date.now() - startedAt;
        setTimeout(poll, ms || (elapsed < 20000 ? 900 : 2000));
    }

    function traceMeta(data) {
        var parts = [];
        if (data.ai) { parts.push('AI: ' + data.ai + '.'); }
        if (data.duration_ms) { parts.push('Finished in ' + (data.duration_ms / 1000).toFixed(1) + ' s.'); }
        return parts.length ? parts.join(' ') : 'Each step is recorded as it happens.';
    }

    /* ---------- Trace ---------- */

    function renderSteps(steps) {
        var $trace = $('#trace');
        $.each(steps || [], function (_, s) {
            lastSeq = Math.max(lastSeq, s.seq);
            var classes = [s.level === 'warning' ? 'warning' : '', s.step_id ? '' : 'note'].join(' ');
            var html = '<li class="' + classes + '"' + (s.step_id ? ' id="step-' + SM.esc(s.step_id) + '"' : '') + '>' +
                '<div class="step-head">' +
                (s.step_id ? '<span class="step-id">' + SM.esc(s.step_id) + '</span>' : '') +
                '<span class="step-node">' + SM.esc(NODE_NAMES[s.node] || s.node) + '</span></div>';
            if (s.thought) { html += '<p class="step-line"><b>Thought:</b> ' + SM.esc(s.thought) + '</p>'; }
            if (s.action) { html += '<p class="step-line"><b>Action:</b> <code>' + SM.esc(s.action) + '</code></p>'; }
            if (s.observation) { html += '<p class="step-line">' + (s.step_id ? '<b>Observation:</b> ' : '') + SM.esc(s.observation) + '</p>'; }
            $trace.append(html + '</li>');
            if (PROGRESS[s.node]) { $('#progress-text').text(PROGRESS[s.node]); }
        });
    }

    /* ---------- Work order and replans ---------- */

    function renderWorkOrder(wo) {
        if (!wo) { return; }
        $('#work-order').prop('hidden', false);
        $('#wo-category').text(wo.category_name || 'Not stated');
        $('#wo-budget').text(wo.budget ? SM.inr(wo.budget) : 'No budget given');
        var $list = $('#wo-musts').empty();
        if (!wo.must_haves || !wo.must_haves.length) {
            $list.append('<li>None</li>');
        }
        $.each(wo.must_haves || [], function (_, m) {
            var cls = m.dropped ? 'dropped' : (m.supported ? '' : 'unsupported');
            var title = m.dropped ? 'Given up to find matches' : (m.supported ? '' : 'Can\'t be checked automatically');
            $list.append('<li class="' + cls + '" title="' + SM.esc(title) + '">' + SM.esc(m.label) + '</li>');
        });
    }

    function renderReplans(replans) {
        if (!replans || !replans.length) { return; }
        var $list = $('#replan-list').empty();
        $.each(replans, function (_, r) {
            var who = r.picked_by === 'ai' ? 'chosen by the AI' : 'chosen by a fixed rule';
            $list.append('<li>' + SM.esc(capitalise(r.summary)) + ' (' + who + '; brings back ' + r.unlocks + ' product' + (r.unlocks === 1 ? '' : 's') + ')</li>');
        });
        $('#replan-note').prop('hidden', false);
    }

    /* ---------- Final states ---------- */

    function renderDone(data) {
        $('#status-area').empty();
        var b = data.banner;
        var text = b.checked + ' statement check' + (b.checked === 1 ? '' : 's') + ' ran against the listing data. ' +
            b.shown + ' statement' + (b.shown === 1 ? ' is' : 's are') + ' shown' +
            (b.removed ? ', and ' + b.removed + ' that stayed wrong after a rewrite ' + (b.removed === 1 ? 'was' : 'were') + ' removed.' : '. None had to be removed.');
        $('#banner-text').text(text);
        $('#banner').prop('hidden', false);

        var $cards = $('#cards').empty();
        $.each(data.results, function (_, r) { $cards.append(card(r, data)); });
        renderCompare(data.results);
        $('#results').prop('hidden', false);
        if (window.matchMedia('(max-width: 1000px)').matches) { $('#trace-panel').prop('open', false); }
    }

    function renderClarify(data) {
        $('#status-area').html(
            '<div class="panel clarify-box">' +
            '<h2>One quick question</h2>' +
            '<p>' + SM.esc(data.clarify_question) + '</p>' +
            '<form id="clarify-form"><label class="visually-hidden" for="clarify-answer">Your answer</label>' +
            '<input type="text" id="clarify-answer" maxlength="200" placeholder="e.g. a smartwatch under ₹5,000" required>' +
            '<button class="btn" type="submit">Continue</button></form>' +
            '<p class="field-error" id="clarify-error" hidden></p></div>');
        $('#clarify-answer').trigger('focus');
    }

    $(document).on('submit', '#clarify-form', function (e) {
        e.preventDefault();
        var answer = $.trim($('#clarify-answer').val());
        if (answer.length < 2) { return; }
        var $btn = $(this).find('button').prop('disabled', true);
        SM.api('POST', $root.data('clarify'), { answer: answer })
            .done(function () {
                $('#clarified-answer').text(answer);
                $('#clarified-line').prop('hidden', false);
                $('#trace').empty();
                lastSeq = 0;
                startedAt = Date.now();
                $('#status-area').html('<div class="panel progress"><span class="spinner" aria-hidden="true"></span><span id="progress-text">Reading your answer…</span></div>');
                poll();
            })
            .fail(function (xhr) {
                $('#clarify-error').text(SM.errorMessage(xhr)).prop('hidden', false);
                $btn.prop('disabled', false);
            });
    });

    function renderEmpty() {
        $('#status-area').html(
            '<div class="panel empty-state"><h2>No listing fits this request</h2>' +
            '<p class="muted">Even after relaxing it, nothing matched. The steps on the right show what was tried.</p>' +
            '<p>Try a higher budget, fewer must-haves, or name the product type, e.g. "earbuds under ₹2,000".</p>' +
            '<a class="btn" href="/">Start a new search</a></div>');
    }

    function renderFailed(message) {
        $('#status-area').html(
            '<div class="panel empty-state"><h2>The search stopped</h2>' +
            '<p class="muted">' + SM.esc(message || 'Something went wrong while searching. Your request is fine; please try it again.') + '</p>' +
            '<a class="btn" href="/">Start a new search</a></div>');
    }

    /* ---------- Cards ---------- */

    function card(r, data) {
        var p = r.product, o = r.offer;
        offers[o.id] = { product: p.name, store: o.store, price: o.price };

        var tags = '';
        if (o.approx) { tags += ' <span class="tag tag-approx" title="Converted: ' + SM.esc(o.fx) + '">approx.</span>'; }
        if (o.within_budget === false) { tags += ' <span class="tag tag-over">Over your budget</span>'; }
        if (o.stale) { tags += ' <span class="tag tag-stale" title="Not refreshed recently">Price may be out of date</span>'; }
        if (p.is_demo) { tags += ' <span class="tag tag-demo" title="Fictional demo catalog">Demo</span>'; }

        var checklist = '';
        if (r.checklist.length) {
            checklist = '<ul class="checklist" aria-label="Your must-haves">';
            $.each(r.checklist, function (_, c) {
                var met = c.status === 'met';
                checklist += '<li><span class="status status-' + SM.esc(c.status) + '" aria-hidden="true">' + (met ? '✓' : '?') + '</span>' +
                    '<span>' + SM.esc(c.label) + '<small>' + SM.esc(STATUS_TEXT[c.status] || c.status) + '</small></span></li>';
            });
            checklist += '</ul>';
        }

        var claims = '<ul class="claims" aria-label="Checked statements">';
        $.each(r.claims, function (_, c) {
            claims += '<li>' + SM.esc(c.text) +
                (c.cites ? '<button type="button" class="cite-chip" data-step="' + SM.esc(c.cites) + '" title="Show step ' + SM.esc(c.cites) + ' in the trace" aria-label="Checked against step ' + SM.esc(c.cites) + '">' + SM.esc(c.cites) + '</button>' : '') +
                '</li>';
        });
        if (!r.claims.length) { claims += '<li class="muted">No statement about this product passed the check, so none is shown.</li>'; }
        claims += '</ul>';

        var mrp = o.mrp && o.mrp > o.price ? '<span class="mrp num">' + SM.inr(o.mrp) + '</span>' : '';
        var rating = p.rating !== null
            ? 'Rated ' + p.rating + ' from ' + SM.number(p.review_count) + ' review' + (p.review_count === 1 ? '' : 's') + '. '
            : 'No rating yet. ';
        var stores = p.offers_count > 1 ? 'Cheapest of ' + p.offers_count + ' stores.' : 'One store.';

        return $('<article class="card rank-' + r.rank + '">' +
            '<div class="card-top">' +
            '<div class="card-rank">' + (r.rank === 1 ? 'Best match' : '#' + r.rank) + '</div>' +
            '<h3><a href="' + SM.esc(p.url) + '">' + SM.esc(p.name) + '</a></h3>' +
            '<div class="price-row"><span class="price">' + SM.inr(o.price) + '</span>' + mrp + '<span class="price-store">at ' + SM.esc(o.store) + '</span>' + tags + '</div>' +
            '<div class="rating-row num">' + rating + stores + '</div>' +
            '</div>' +
            '<div class="card-body">' + checklist + claims + '</div>' +
            '<div class="card-foot">' +
            '<p class="source-line">Source: ' + SM.esc(p.source) + (p.captured_on ? ', captured ' + SM.esc(p.captured_on) : '') + '.</p>' +
            '<div class="card-actions">' +
            '<button type="button" class="btn btn-small" data-select-offer="' + o.id + '">Select this</button>' +
            '<a class="btn btn-secondary btn-small" href="' + SM.esc(p.url) + '">Compare stores</a>' +
            '</div></div></article>');
    }

    function renderCompare(results) {
        var $table = $('#compare').empty();
        if (!results.length) { return; }

        var head = '<thead><tr><th scope="col"><span class="visually-hidden">Detail</span></th>';
        $.each(results, function (_, r) { head += '<th scope="col">' + SM.esc(r.product.name) + '</th>'; });
        head += '</tr></thead>';

        var prices = results.map(function (r) { return r.offer.price; });
        var minPrice = Math.min.apply(null, prices);

        var rows = '';
        rows += row('Price', results, function (r) { return { html: SM.inr(r.offer.price), best: r.offer.price === minPrice && results.length > 1 }; });
        rows += row('Cheapest store', results, function (r) { return { html: SM.esc(r.offer.store) }; });
        rows += row('Rating', results, function (r) { return { html: r.product.rating !== null ? r.product.rating + ' / 5' : '—' }; });
        rows += row('Reviews', results, function (r) { return { html: SM.number(r.product.review_count) }; });

        $.each(results[0].checklist, function (i, c) {
            rows += row(c.label, results, function (r) {
                var item = r.checklist[i];
                return { html: '<span class="status status-' + SM.esc(item.status) + '" title="' + SM.esc(STATUS_TEXT[item.status]) + '">' + (item.status === 'met' ? '✓' : '?') + '</span>' };
            });
        });

        var specKeys = [];
        $.each(results, function (_, r) {
            $.each(r.specs, function (_, s) { if (specKeys.indexOf(s.key) === -1) { specKeys.push(s.key); } });
        });
        $.each(specKeys.slice(0, 7), function (_, key) {
            var label = key;
            $.each(results, function (_, r) { $.each(r.specs, function (_, s) { if (s.key === key) { label = s.label; } }); });
            rows += row(label, results, function (r) {
                var found = null;
                $.each(r.specs, function (_, s) { if (s.key === key) { found = s.value; } });
                return { html: found === null ? '<span class="muted">Not stated</span>' : SM.esc(found) };
            });
        });

        $table.html(head + '<tbody>' + rows + '</tbody>');
    }

    function row(label, results, cell) {
        var html = '<tr><th scope="row">' + SM.esc(label) + '</th>';
        $.each(results, function (_, r) {
            var c = cell(r);
            html += '<td class="num' + (c.best ? ' best' : '') + '">' + c.html + '</td>';
        });
        return html + '</tr>';
    }

    // Statement chip -> show the trace step it came from
    $(document).on('click', '.cite-chip[data-step]', function () {
        var $step = $('#step-' + $(this).data('step'));
        if (!$step.length) { return; }
        $('#trace-panel').prop('open', true);
        $step.removeClass('flash');
        $step[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
        setTimeout(function () { $step.addClass('flash'); }, 50);
    });

    /* ---------- Cart approval modal ---------- */

    var $modal = $('#mandate-modal');
    var mandate = null;
    var selected = null;
    var $opener = null;

    $(document).on('click', '[data-select-offer]', function () {
        $opener = $(this);
        selected = $.extend({ id: $(this).data('select-offer') }, offers[$(this).data('select-offer')]);
        mandate = null;
        $('#mandate-qty').val('1');
        $('#mandate-consent').prop('checked', false);
        $('#mandate-step-consent').prop('hidden', false);
        $('#mandate-step-signed').prop('hidden', true);
        $('#mandate-approve').prop({ hidden: false, disabled: true });
        $('#mandate-checkout').prop('hidden', true);
        $('#mandate-message').prop('hidden', true);
        renderSummary(selected.price, 1, selected.store, selected.product, false);
        $modal.prop('hidden', false);
        $('#mandate-qty').trigger('focus');
    });

    function renderSummary(unit, qty, store, product, signed) {
        $('#cart-summary').html(
            '<div><span>Item</span><strong>' + SM.esc(product) + '</strong></div>' +
            '<div><span>Store</span><span>' + SM.esc(store) + '</span></div>' +
            '<div><span>Price each</span><span class="num">' + SM.inr(unit) + (signed ? ' (confirmed by server)' : '') + '</span></div>' +
            '<div><span>Quantity</span><span class="num">' + qty + '</span></div>' +
            '<div class="total"><span>Total</span><span class="num">' + SM.inr(unit * qty) + '</span></div>');
    }

    $('#mandate-qty').on('change', function () {
        renderSummary(selected.price, parseInt($(this).val(), 10), selected.store, selected.product, false);
    });

    $('#mandate-consent').on('change', function () {
        $('#mandate-approve').prop('disabled', !this.checked);
    });

    $('#mandate-approve').on('click', function () {
        var $btn = $(this).prop('disabled', true);
        SM.api('POST', $root.data('mandates'), {
            offer_id: selected.id,
            quantity: parseInt($('#mandate-qty').val(), 10),
            consent: $('#mandate-consent').is(':checked'),
            search_session_id: $root.data('id')
        }).done(function (res) {
            mandate = res;
            var p = res.payload;
            renderSummary(p.unit_price, p.quantity, p.store, p.product_name, true);
            $('#mandate-step-consent').prop('hidden', true);
            $('#mandate-expiry').text(new Date(res.expires_at).toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit' }));
            $('#mandate-signature').text(res.signature);
            $('#mandate-step-signed').prop('hidden', false);
            $btn.prop('hidden', true);
            $('#mandate-checkout').prop({ hidden: false, disabled: false }).trigger('focus');
        }).fail(function (xhr) {
            message(SM.errorMessage(xhr), false);
            $btn.prop('disabled', false);
        });
    });

    $('#mandate-checkout').on('click', function () {
        var $btn = $(this).prop('disabled', true);
        SM.api('POST', '/api/cart-mandates/' + encodeURIComponent(mandate.mandate_id) + '/checkout', {
            payload: mandate.payload,
            signature: mandate.signature
        }).done(function (res) {
            message(res.message + ' Reference: ' + res.order_ref + '.', true);
            $btn.prop('hidden', true);
        }).fail(function (xhr) {
            message(SM.errorMessage(xhr), false);
            $btn.prop('disabled', false);
        });
    });

    function message(text, ok) {
        $('#mandate-message').removeClass('alert-ok alert-error').addClass(ok ? 'alert-ok' : 'alert-error').text(text).prop('hidden', false);
    }

    function closeModal() {
        $modal.prop('hidden', true);
        if ($opener) { $opener.trigger('focus'); }
    }

    $modal.on('click', '[data-close-modal]', closeModal);
    $modal.on('click', function (e) { if (e.target === this) { closeModal(); } });
    $(document).on('keydown', function (e) { if (e.key === 'Escape' && !$modal.prop('hidden')) { closeModal(); } });

    function capitalise(text) { return text ? text.charAt(0).toUpperCase() + text.slice(1) : text; }

    poll();
})(jQuery, window.SM);

/* Split basics: the API with the session's CSRF token, modals and money. */
(function () {
    'use strict';

    var token = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

    function toast(message, kind) {
        if (window.PlainShow && window.PlainShow.toast) window.PlainShow.toast(message, kind);
    }

    function api(path, data, method) {
        return fetch('/api/v1/' + path.replace(/^\//, ''), {
            method: method || 'POST',
            credentials: 'same-origin',
            headers: Object.assign({ 'X-CSRF-Token': token, 'Accept': 'application/json' }, data ? { 'Content-Type': 'application/json' } : {}),
            body: data ? JSON.stringify(data) : undefined
        }).then(function (response) {
            return response.json().catch(function () { return {}; }).then(function (body) {
                if (!response.ok || body.ok === false) throw new Error(body.message || psT('Något gick fel. Försök igen.'));
                return body;
            });
        });
    }

    /** Öre to "1 234,50 kr". */
    function money(cents) {
        var negative = cents < 0;
        var text = (Math.abs(cents) / 100).toLocaleString('sv-SE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).replace(/,00$/, '');
        return (negative ? '−' : '') + text + ' kr';
    }
    /** "249,50" to öre, or NaN. */
    function parseMoney(text) {
        var clean = String(text || '').replace(/\s|kr/g, '').replace(',', '.');
        if (!/^\d{1,7}(\.\d{1,2})?$/.test(clean)) return NaN;
        return Math.round(parseFloat(clean) * 100);
    }

    var lastFocus = null;
    function openModal(id) {
        var modal = document.getElementById(id);
        if (!modal) return;
        lastFocus = document.activeElement;
        modal.hidden = false;
        document.documentElement.classList.add('split-locked');
        var first = modal.querySelector('input:not([type=hidden]), select, textarea, button');
        if (first) window.setTimeout(function () { first.focus(); }, 30);
    }
    function closeModal(modal) {
        modal.hidden = true;
        if (!document.querySelector('.ps-modal-backdrop:not([hidden])')) document.documentElement.classList.remove('split-locked');
        if (lastFocus && lastFocus.focus) lastFocus.focus();
    }
    document.addEventListener('click', function (event) {
        var target = event.target instanceof Element ? event.target : null;
        if (!target) return;
        var opener = target.closest('[data-modal-open]');
        if (opener) { event.preventDefault(); openModal(opener.getAttribute('data-modal-open')); return; }
        var backdrop = target.closest('.ps-modal-backdrop');
        if (backdrop && (target === backdrop || target.closest('[data-modal-close]'))) closeModal(backdrop);
    });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') document.querySelectorAll('.ps-modal-backdrop:not([hidden])').forEach(closeModal);
    });

    window.Split = { api: api, toast: toast, money: money, parseMoney: parseMoney, openModal: openModal, closeModal: closeModal };
})();

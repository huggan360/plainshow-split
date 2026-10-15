/* The groups page: a new group, and filtering people. */
(function () {
    'use strict';

    document.addEventListener('input', function (event) {
        var filter = event.target;
        if (!filter.matches || !filter.matches('[data-people-filter]')) return;
        var query = filter.value.trim().toLowerCase();
        filter.parentNode.querySelectorAll('[data-people] .split-person').forEach(function (row) {
            row.hidden = query !== '' && row.dataset.name.indexOf(query) === -1;
        });
    });

    var form = document.querySelector('[data-group-new]');
    if (!form) return;
    form.addEventListener('submit', function (event) {
        event.preventDefault();
        var members = Array.prototype.map.call(form.querySelectorAll('input[name="members"]:checked'), function (box) { return Number(box.value); });
        var button = form.querySelector('[type="submit"]');
        button.disabled = true;
        Split.api('groups', { name: form.name.value, members: members })
            .then(function (body) { window.location.href = body.redirect; })
            .catch(function (error) { button.disabled = false; Split.toast(error.message, 'error'); });
    });
})();

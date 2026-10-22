/* The groups page: a new group, and filtering people. */
(function () {
    'use strict';

    document.addEventListener('input', function (event) {
        var filter = event.target;
        if (!filter.matches || !filter.matches('[data-people-filter]')) return;
        var query = filter.value.trim().toLowerCase();
        filter.closest('form').querySelectorAll('[data-people] .split-person').forEach(function (row) {
            row.hidden = query !== '' && row.dataset.name.indexOf(query) === -1;
        });
    });

    document.addEventListener('click', function (event) {
        var source = event.target.closest && event.target.closest('input[name="sourceGroupId"]');
        if (source && source.checked && source.dataset.wasChecked === '1') {
            source.checked = false;
            source.dataset.wasChecked = '0';
        } else if (source) {
            source.closest('form').querySelectorAll('input[name="sourceGroupId"]').forEach(function (radio) { radio.dataset.wasChecked = radio === source ? '1' : '0'; });
        }
        var group = event.target.closest && event.target.closest('[data-source-members]');
        if (!group) return;
        var ids = JSON.parse(group.dataset.sourceMembers || '[]').map(Number);
        var form = group.closest('form');
        form.querySelectorAll('input[name="members"]').forEach(function (box) {
            if (ids.indexOf(Number(box.value)) !== -1) box.checked = true;
        });
    });

    var form = document.querySelector('[data-group-new]');
    if (!form) return;
    form.addEventListener('submit', function (event) {
        event.preventDefault();
        var members = Array.prototype.map.call(form.querySelectorAll('input[name="members"]:checked'), function (box) { return Number(box.value); });
        var button = form.querySelector('[type="submit"]');
        button.disabled = true;
        Split.api('groups', { name: form.name.value, members: members, sourceGroupId: Number(form.elements.sourceGroupId ? form.elements.sourceGroupId.value : 0) })
            .then(function (body) { window.location.href = body.redirect; })
            .catch(function (error) { button.disabled = false; Split.toast(error.message, 'error'); });
    });
})();

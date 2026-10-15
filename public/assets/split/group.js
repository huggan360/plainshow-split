/*
 * The group page: add and change expenses with a live view of each share,
 * record payments, and manage the group. Changes reload the page so every
 * balance is recalculated by the server.
 */
(function () {
    'use strict';

    var data = JSON.parse(document.getElementById('group-data').textContent);
    var base = 'groups/' + data.id;
    var byId = {};
    data.members.forEach(function (person) { byId[person.id] = person; });
    var expenses = {};
    data.expenses.forEach(function (expense) { expenses[expense.id] = expense; });

    function done(message) {
        try { sessionStorage.setItem('split-toast', message); } catch (e) { /* private mode */ }
        window.location.reload();
    }
    try {
        var pending = sessionStorage.getItem('split-toast');
        if (pending) { sessionStorage.removeItem('split-toast'); window.setTimeout(function () { Split.toast(pending); }, 300); }
    } catch (e) { /* private mode */ }
    function fail(error) { Split.toast(error.message, 'error'); }

    /* ---------- Expense editor ---------- */
    var form = document.querySelector('[data-expense-form]');
    var rows = Array.prototype.slice.call(form.querySelectorAll('[data-person]'));
    var sumNode = form.querySelector('[data-shares-sum]');
    var deleteButton = form.querySelector('[data-expense-delete]');
    var mode = 'equal';
    var editing = null;

    function setMode(next) {
        mode = next;
        form.querySelectorAll('[data-mode]').forEach(function (tab) {
            tab.classList.toggle('is-active', tab.dataset.mode === next);
            tab.setAttribute('aria-selected', tab.dataset.mode === next ? 'true' : 'false');
        });
        form.classList.toggle('is-equal', next === 'equal');
        rows.forEach(function (row) {
            var input = row.querySelector('.split-share__input');
            input.placeholder = next === 'percent' ? '%' : 'kr';
        });
        update();
    }

    /** Shares in öre, worked out the same way as the server (largest remainder). */
    function shares() {
        var amount = Split.parseMoney(form.amount.value);
        var chosen = rows.filter(function (row) { return row.querySelector('input[type=checkbox]').checked; });
        var result = {};
        if (!chosen.length || isNaN(amount)) return { shares: result, ok: false, amount: amount };
        if (mode === 'amount') {
            var sum = 0;
            chosen.forEach(function (row) {
                var value = Split.parseMoney(row.querySelector('.split-share__input').value);
                result[row.dataset.person] = isNaN(value) ? 0 : value;
                sum += isNaN(value) ? 0 : value;
            });
            return { shares: result, ok: sum === amount, amount: amount, sum: sum };
        }
        var weights = {};
        var total = 0;
        chosen.forEach(function (row) {
            var weight = mode === 'percent' ? parseFloat(String(row.querySelector('.split-share__input').value).replace(',', '.')) || 0 : 1;
            weights[row.dataset.person] = weight;
            total += weight;
        });
        if (total <= 0) return { shares: result, ok: false, amount: amount };
        var remainders = [];
        var given = 0;
        Object.keys(weights).forEach(function (id) {
            var exact = amount * weights[id] / total;
            result[id] = Math.floor(exact);
            given += result[id];
            remainders.push([id, exact - result[id]]);
        });
        remainders.sort(function (a, b) { return b[1] - a[1]; });
        for (var i = 0; i < amount - given; i++) result[remainders[i][0]]++;
        return { shares: result, ok: mode === 'equal' || Math.abs(total - 100) < 0.01, amount: amount, percent: total };
    }

    function update() {
        var outcome = shares();
        rows.forEach(function (row) {
            var on = row.querySelector('input[type=checkbox]').checked;
            row.classList.toggle('is-off', !on);
            var value = outcome.shares[row.dataset.person];
            row.querySelector('.split-share__result').textContent = on && value !== undefined && !isNaN(outcome.amount) ? Split.money(value) : '';
        });
        var text = '';
        if (!isNaN(outcome.amount)) {
            if (mode === 'amount') text = outcome.ok ? 'Stämmer: ' + Split.money(outcome.amount) : 'Fördelat ' + Split.money(outcome.sum || 0) + ' av ' + Split.money(outcome.amount);
            if (mode === 'percent') text = outcome.ok ? 'Stämmer: 100 %' : 'Andelarna blir ' + String(Math.round((outcome.percent || 0) * 100) / 100).replace('.', ',') + ' % av 100 %';
        }
        sumNode.textContent = text;
        sumNode.classList.toggle('is-wrong', !!text && !outcome.ok);
    }

    function openEditor(expense) {
        editing = expense ? expense.id : null;
        document.getElementById('expense-title').textContent = expense ? 'Ändra utgift' : 'Ny utgift';
        form.title.value = expense ? expense.title : '';
        form.amount.value = expense ? (expense.amount / 100).toFixed(2).replace('.', ',').replace(/,00$/, '') : '';
        form.spent_on.value = expense ? expense.spent_on : new Date().toISOString().slice(0, 10);
        form.paid_by.value = String(expense ? expense.paid_by : data.me);
        form.notes.value = expense && expense.notes ? expense.notes : '';
        rows.forEach(function (row) {
            var id = row.dataset.person;
            var share = expense ? expense.shares[id] : undefined;
            row.querySelector('input[type=checkbox]').checked = expense ? share !== undefined : true;
            var input = row.querySelector('.split-share__input');
            input.value = '';
            if (expense && share !== undefined) {
                if (expense.mode === 'amount') input.value = (share / 100).toFixed(2).replace('.', ',');
                if (expense.mode === 'percent') input.value = String(Math.round(share / expense.amount * 10000) / 100).replace('.', ',');
            }
        });
        deleteButton.hidden = !expense;
        setMode(expense ? expense.mode : 'equal');
        Split.openModal('expense');
    }

    form.addEventListener('input', update);
    form.addEventListener('change', update);
    form.querySelectorAll('[data-mode]').forEach(function (tab) {
        tab.addEventListener('click', function () { setMode(tab.dataset.mode); });
    });

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        var outcome = shares();
        if (!form.title.value.trim()) { Split.toast('Skriv vad det gällde.', 'error'); form.title.focus(); return; }
        if (isNaN(outcome.amount) || outcome.amount <= 0) { Split.toast('Skriv ett belopp, till exempel 249,50.', 'error'); form.amount.focus(); return; }
        if (!Object.keys(outcome.shares).length) { Split.toast('Välj minst en person som delar på kostnaden.', 'error'); return; }
        if (!outcome.ok) { Split.toast(sumNode.textContent || 'Fördelningen stämmer inte.', 'error'); return; }
        var people = Object.keys(outcome.shares).map(Number);
        var inputs = {};
        rows.forEach(function (row) { if (row.querySelector('input[type=checkbox]').checked) inputs[row.dataset.person] = row.querySelector('.split-share__input').value; });
        var button = form.querySelector('[type=submit]');
        button.disabled = true;
        Split.api(base + '/expenses' + (editing ? '/' + editing : ''), {
            title: form.title.value, amount: form.amount.value, spent_on: form.spent_on.value, paid_by: Number(form.paid_by.value),
            notes: form.notes.value, mode: mode, people: people, inputs: mode === 'equal' ? {} : inputs
        }).then(function () { done(editing ? 'Utgiften är ändrad.' : 'Utgiften är tillagd.'); })
          .catch(function (error) { button.disabled = false; fail(error); });
    });

    deleteButton.addEventListener('click', function () {
        if (!editing || !window.confirm('Ta bort utgiften?')) return;
        Split.api(base + '/expenses/' + editing, null, 'DELETE').then(function () { done('Utgiften är borttagen.'); }).catch(fail);
    });

    /* Read-only view for expenses you cannot change. */
    function showExpense(expense) {
        document.getElementById('expense-view-title').textContent = expense.title;
        var body = document.querySelector('[data-expense-view]');
        body.innerHTML = '';
        var lead = document.createElement('p');
        lead.textContent = (byId[expense.paid_by] ? byId[expense.paid_by].name : 'Någon') + ' betalade ' + Split.money(expense.amount) + ' den ' + expense.spent_on + '.';
        body.appendChild(lead);
        var list = document.createElement('ul');
        list.className = 'ps-list';
        Object.keys(expense.shares).forEach(function (id) {
            var row = document.createElement('li');
            row.className = 'ps-row';
            row.innerHTML = '<div class="ps-row__body"><div class="ps-row__title"></div></div><strong></strong>';
            row.querySelector('.ps-row__title').textContent = byId[id] ? byId[id].name : 'Har lämnat gruppen';
            row.querySelector('strong').textContent = Split.money(expense.shares[id]);
            list.appendChild(row);
        });
        body.appendChild(list);
        if (expense.notes) {
            var notes = document.createElement('p');
            notes.className = 'ps-muted';
            notes.textContent = expense.notes;
            body.appendChild(notes);
        }
        var hint = document.createElement('p');
        hint.className = 'ps-help';
        hint.textContent = 'Den som lade till eller betalade utgiften kan ändra den.';
        body.appendChild(hint);
        Split.openModal('expense-view');
    }

    /* ---------- Payments ---------- */
    var payment = document.querySelector('[data-payment-form]');
    function openPayment(from, to, amount) {
        payment.from.value = String(from);
        payment.to.value = String(to);
        payment.amount.value = amount ? (amount / 100).toFixed(2).replace('.', ',').replace(/,00$/, '') : '';
        payment.method.value = 'swish';
        payment.note.value = '';
        Split.openModal('payment');
    }
    payment.addEventListener('submit', function (event) {
        event.preventDefault();
        Split.api(base + '/payments', {
            from: Number(payment.from.value), to: Number(payment.to.value), amount: payment.amount.value, method: payment.method.value, note: payment.note.value
        }).then(function () { done('Betalningen är registrerad.'); }).catch(fail);
    });

    /* ---------- Clicks ---------- */
    document.addEventListener('click', function (event) {
        var target = event.target instanceof Element ? event.target : null;
        if (!target) return;
        if (target.closest('[data-expense-new]')) { openEditor(null); return; }
        var row = target.closest('[data-expense]');
        if (row) {
            var expense = expenses[row.dataset.expense];
            if (expense) expense.editable ? openEditor(expense) : showExpense(expense);
            return;
        }
        if (target.closest('[data-payment-new]')) {
            var other = data.members.filter(function (p) { return p.id !== data.me; })[0];
            openPayment(data.me, other ? other.id : data.me, 0);
            return;
        }
        var settle = target.closest('[data-settle]');
        if (settle) {
            var parts = settle.dataset.settle.split(',').map(Number);
            openPayment(parts[0], parts[1], parts[2]);
            return;
        }
        var removePayment = target.closest('[data-payment-delete]');
        if (removePayment && window.confirm('Ta bort betalningen?')) {
            Split.api(base + '/payments/' + removePayment.dataset.paymentDelete, null, 'DELETE').then(function () { done('Betalningen är borttagen.'); }).catch(fail);
            return;
        }
        var removeMember = target.closest('[data-member-remove]');
        if (removeMember && window.confirm('Ta bort personen från gruppen?')) {
            Split.api(base + '/members/' + removeMember.dataset.memberRemove, null, 'DELETE').then(function () { done('Personen är borttagen.'); }).catch(fail);
            return;
        }
        if (target.closest('[data-group-leave]') && window.confirm('Lämna gruppen? Du måste vara kvitt först.')) {
            Split.api(base + '/members/' + data.me, null, 'DELETE').then(function (body) { window.location.href = body.redirect || '/'; }).catch(fail);
            return;
        }
        if (target.closest('[data-group-delete]') && window.confirm('Ta bort gruppen med alla utgifter och betalningar? Det går inte att ångra.')) {
            Split.api(base, null, 'DELETE').then(function (body) { window.location.href = body.redirect || '/'; }).catch(fail);
        }
    });

    var addMembers = document.querySelector('[data-members-add]');
    if (addMembers) {
        addMembers.addEventListener('submit', function (event) {
            event.preventDefault();
            var ids = Array.prototype.map.call(addMembers.querySelectorAll('input[name=members]:checked'), function (box) { return Number(box.value); });
            if (!ids.length) { Split.toast('Välj minst en person.', 'error'); return; }
            Split.api(base + '/members', { members: ids }).then(function () { done('Personerna är tillagda.'); }).catch(fail);
        });
    }
    var rename = document.querySelector('[data-group-rename]');
    if (rename) {
        rename.addEventListener('submit', function (event) {
            event.preventDefault();
            Split.api(base, { name: rename.name.value }).then(function () { done('Gruppen har nytt namn.'); }).catch(fail);
        });
    }
})();

/**
 * Kanban de fases — Processos (Unio Jurídico)
 * Drag-and-drop nativo (HTML5) para mover processos entre fases, com
 * atualização otimista e rollback em caso de erro.
 */
(function (document) {
    'use strict';

    var board = document.getElementById('jurProcessosKanban');
    if (!board) return;

    var urlBase = board.dataset.faseUrlBase;
    var token = board.dataset.token;
    var draggedCard = null;
    var originColumn = null;

    board.addEventListener('dragstart', function (e) {
        var card = e.target.closest('.jur-kanban-card');
        if (!card) return;
        draggedCard = card;
        originColumn = card.closest('[data-fase-drop]');
        card.classList.add('is-dragging');
        e.dataTransfer.effectAllowed = 'move';
        try { e.dataTransfer.setData('text/plain', card.dataset.processoId); } catch (err) { /* Safari */ }
    });

    board.addEventListener('dragend', function (e) {
        var card = e.target.closest('.jur-kanban-card');
        if (card) card.classList.remove('is-dragging');
        board.querySelectorAll('.jur-kanban-col__body.is-dragover').forEach(function (el) {
            el.classList.remove('is-dragover');
        });
    });

    board.addEventListener('dragover', function (e) {
        var col = e.target.closest('[data-fase-drop]');
        if (!col) return;
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        col.classList.add('is-dragover');
    });

    board.addEventListener('dragleave', function (e) {
        var col = e.target.closest('[data-fase-drop]');
        if (col && !col.contains(e.relatedTarget)) {
            col.classList.remove('is-dragover');
        }
    });

    board.addEventListener('drop', function (e) {
        var col = e.target.closest('[data-fase-drop]');
        if (!col || !draggedCard) return;
        e.preventDefault();
        col.classList.remove('is-dragover');

        var novaFase = col.dataset.faseDrop;
        var origemFase = originColumn ? originColumn.dataset.faseDrop : null;
        if (novaFase === origemFase) return;

        var processoId = draggedCard.dataset.processoId;
        col.appendChild(draggedCard);
        atualizarContadores();

        fetch(urlBase + '/' + processoId + '/fase', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ _token: token, fase: novaFase })
        })
            .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
            .then(function (result) {
                if (!result.ok || result.data.error) {
                    throw new Error(result.data && result.data.error ? result.data.error : 'Não foi possível mover o processo.');
                }
                if (novaFase === 'encerrado') {
                    var flag = draggedCard.querySelector('.jur-kanban-card__flag');
                    if (flag) flag.remove();
                }
            })
            .catch(function (err) {
                if (originColumn) {
                    originColumn.appendChild(draggedCard);
                    atualizarContadores();
                }
                window.alert(err.message || 'Não foi possível mover o processo. Tente novamente.');
            });
    });

    function atualizarContadores() {
        board.querySelectorAll('.jur-kanban-col').forEach(function (col) {
            var body = col.querySelector('.jur-kanban-col__body');
            var count = body ? body.querySelectorAll('.jur-kanban-card').length : 0;
            var counter = col.querySelector('.jur-kanban-col__count');
            if (counter) counter.textContent = String(count);
            var empty = body ? body.querySelector('.jur-kanban-col__empty') : null;
            if (empty) empty.hidden = count > 0;
        });
    }
})(document);

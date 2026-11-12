/* Quitação antecipada: o pagamento acontece fora do Fio. */
(() => {
    const eligible = new Set(['active', 'overdue', 'paid_off']);
    const statuses = {
        requested: 'Aguardando resposta do recebedor',
        countered: 'Valor integral proposto',
        accepted: 'Valor de quitação aceito',
        receipt_submitted: 'Aguardando confirmação do recebimento',
        declined: 'Solicitação recusada',
        cancelled: 'Solicitação cancelada',
        confirmed: 'Acordo quitado antecipadamente',
    };
    const money = cents => brl(Number(cents) / 100);
    const endpoint = dealId => '/v1/deals/' + dealId + '/early-payoff';

    function element(tag, text, className) {
        const node = document.createElement(tag);
        node.textContent = text;
        if (className) node.className = className;
        return node;
    }

    async function attach(deal) {
        if (!deal?.formalized_at || !eligible.has(deal.status)) return;
        const slot = element('div', '', 'card');
        slot.id = 'earlyPayoff';
        slot.hidden = true;
        dealDetailBody.prepend(slot);
        try {
            const state = await apiFetch(endpoint(deal.id));
            if (!slot.isConnected || Number(activeDeal?.id) !== Number(deal.id)) return;
            if (state.can_request || state.payoff) render(slot, deal.id, state);
        } catch {
            // Older API releases do not expose this optional feature.
        }
    }

    function render(slot, dealId, state) {
        const payoff = state.payoff;
        const feedback = element('p', '', 'muted');
        feedback.setAttribute('role', 'status');
        const controls = element('div', '');
        const add = (label, handler, primary = false) => {
            const button = element('button', label, primary ? 'btn gold' : 'btn ghost');
            button.type = 'button';
            button.addEventListener('click', handler);
            controls.append(button);
            return button;
        };
        let busy = false;
        async function run(payload, create = false) {
            if (busy) return;
            busy = true;
            const buttons = [...controls.querySelectorAll('button')];
            buttons.forEach(button => button.disabled = true);
            feedback.textContent = 'Salvando…';
            try {
                const result = await apiFetch(endpoint(dealId) + (create ? '' : '/' + payoff.id), {
                    method: 'POST', body: JSON.stringify(payload),
                });
                if (!slot.isConnected || Number(activeDeal?.id) !== Number(dealId)) return;
                if (result.payoff?.status === 'confirmed') {
                    render(slot, dealId, result);
                    await loadDeals();
                    if (slot.isConnected && Number(activeDeal?.id) === Number(dealId)) openDealDetail(dealId);
                } else render(slot, dealId, result);
            } catch (error) {
                feedback.textContent = error.message || 'Não foi possível concluir a ação.';
            } finally {
                busy = false;
                buttons.forEach(button => button.disabled = false);
            }
        }
        async function confirmAction(action, title, message, confirmLabel) {
            if (await appConfirm(message, {title, confirmLabel})) await run({action});
        }

        slot.replaceChildren(element('h3', 'Quitação antecipada'));
        slot.hidden = false;
        if (payoff) {
            slot.append(element('p', statuses[payoff.status] || 'Solicitação registrada', 'badge'));
            slot.append(element('p', 'Saldo da solicitação: ' + money(payoff.balance_cents), 'muted'));
            slot.append(element('p', 'Proposta do pagador: ' + money(payoff.proposed_cents), 'muted'));
            if (payoff.agreed_cents != null) {
                const delta = Number(payoff.balance_cents) - Number(payoff.agreed_cents);
                slot.append(element('p', (payoff.status === 'confirmed' ? 'Valor recebido: ' : 'Valor aceito: ')
                    + money(payoff.agreed_cents) + (delta ? ' · Abatimento: ' + money(delta) : '')));
            }
        }
        slot.append(controls, feedback);
        if (state.can_request) {
            add('Solicitar quitação antecipada', () => {
                const label = element('label', 'Valor proposto para quitar (R$)', 'label');
                label.htmlFor = 'earlyPayoffAmount';
                const input = document.createElement('input');
                input.id = label.htmlFor;
                input.className = 'input';
                input.inputMode = 'decimal';
                input.value = (state.balance_cents / 100).toFixed(2).replace('.', ',');
                controls.replaceChildren(element('p', 'Saldo em aberto: ' + money(state.balance_cents), 'muted'), label, input);
                add('Enviar proposta', () => {
                    const match = /^(\d{1,12})(?:[,.](\d{1,2}))?$/.exec(input.value.trim());
                    const cents = match ? Number(match[1]) * 100 + Number((match[2] || '').padEnd(2, '0')) : 0;
                    if (cents < 1 || cents > state.balance_cents) {
                        feedback.textContent = 'Informe um valor válido até ' + money(state.balance_cents) + ', sem separador de milhar.';
                        input.focus();
                        return;
                    }
                    run({amount_cents:cents}, true);
                }, true);
                add('Voltar', () => render(slot, dealId, state));
                input.focus();
            }, true);
        }
        if (!payoff) return;
        if (!state.payer && payoff.status === 'requested') {
            add('Aceitar proposta', () => confirmAction('accept', 'Aceitar quitação?', 'Valor para quitação: ' + money(payoff.proposed_cents) + '. O acordo continua em aberto até você confirmar o recebimento.', 'Aceitar valor'), true);
            if (Number(payoff.proposed_cents) < Number(payoff.balance_cents)) {
                add('Manter saldo integral', () => confirmAction('full_amount', 'Manter o saldo integral?', 'O pagador poderá aceitar ou recusar ' + money(payoff.balance_cents) + ' como valor de quitação.', 'Enviar contraproposta'));
            }
            add('Recusar solicitação', () => confirmAction('decline', 'Recusar solicitação?', 'O parcelamento original continua.', 'Recusar'));
        }
        if (state.payer && payoff.status === 'countered') {
            slot.insertBefore(element('p', 'O recebedor manteve o saldo integral de ' + money(payoff.balance_cents) + '.'), controls);
            add('Aceitar valor integral', () => confirmAction('accept', 'Aceitar valor integral?', 'Valor para quitação: ' + money(payoff.balance_cents) + '.', 'Aceitar valor'), true);
            add('Recusar e manter parcelas', () => confirmAction('decline', 'Manter o parcelamento?', 'A solicitação será encerrada e as parcelas continuam como antes.', 'Manter parcelas'));
        }
        if (state.payer && ['requested','countered','accepted'].includes(payoff.status) && !payoff.receipt_id) {
            add('Cancelar solicitação', () => confirmAction('cancel', 'Cancelar solicitação?', 'Cancele somente se você ainda não pagou. O parcelamento original continua.', 'Cancelar solicitação'));
        }
        if (state.payer && payoff.status === 'accepted') {
            const label = element('label', 'Comprovante da quitação', 'label');
            label.htmlFor = 'earlyPayoffReceipt';
            const input = document.createElement('input');
            input.id = label.htmlFor;
            input.type = 'file';
            input.className = 'input';
            input.accept = 'application/pdf,image/jpeg,image/png,image/webp';
            controls.prepend(element('p', 'Pague diretamente ao recebedor e anexe o comprovante aqui.', 'muted'), label, input);
            add('Enviar comprovante', () => {
                const file = input.files[0];
                if (!file || file.size > 10 * 1024 * 1024) {
                    feedback.textContent = 'Selecione um comprovante de até 10 MB.';
                    return;
                }
                const reader = new FileReader();
                reader.onerror = () => feedback.textContent = 'Não foi possível ler o arquivo.';
                reader.onload = () => run({action:'submit_receipt',file_name:file.name,file_base64:reader.result});
                reader.readAsDataURL(file);
            }, true);
        }
        if (payoff.receipt_id && ['receipt_submitted','confirmed'].includes(payoff.status)) {
            add('Baixar comprovante da quitação', () => downloadHistoryDocument(payoff.receipt_id));
        }
        if (!state.payer && payoff.status === 'receipt_submitted') {
            add('Confirmar recebimento e quitar', () => confirmAction('confirm_receipt', 'Confirmar recebimento?', 'Confirme somente se recebeu ' + money(payoff.agreed_cents) + '. O Fio encerrará o acordo e disponibilizará o termo de quitação.', 'Confirmar quitação'), true);
            add('Pedir novo comprovante', () => confirmAction('return_receipt', 'Pedir novo comprovante?', 'O pagador poderá substituir o comprovante. O valor aceito continua igual.', 'Pedir comprovante'));
        }
        if (payoff.term_id && payoff.status === 'confirmed') {
            add('Baixar termo de quitação', () => downloadHistoryDocument(payoff.term_id), true);
        }
    }

    const previous = openDealDetail;
    openDealDetail = function (id) {
        previous(id);
        const deal = activeDeal;
        if (Number(deal?.id) === Number(id)) void attach(deal);
    };
})();

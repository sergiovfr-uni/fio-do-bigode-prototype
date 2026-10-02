/* Subscription dates are supplied by the API; charging stays inactive. */
function fdbSubscriptionSummary(subscriptions) {
    const candidates = [...(subscriptions || [])].sort((a, b) => Number(b.id) - Number(a.id));
    const subscription = candidates.find(x => ['trial', 'active'].includes(x.effective_status)) || candidates[0];
    if (!subscription) return {name: 'Plano não informado', details: 'Consulte a administração.', expired: true};
    const plan = subscription.plan || {};
    const date = value => new Date(value).toLocaleDateString('pt-BR');
    const status = subscription.effective_status || subscription.status;
    const expired = !['trial', 'active'].includes(status);
    const start = subscription.created_at ? 'Início: ' + date(subscription.created_at) + '. ' : '';
    const end = subscription.access_ends_at;
    const term = end ? 'Válido até ' + date(end) + '.' : plan.complimentary ? 'Cortesia sem vencimento, concedida pela administração.' : 'Prazo não informado.';
    const limits = `${plan.active_listing_limit ?? 0} anúncios ativos e ${plan.direct_deal_limit ?? 0} negociações diretas em andamento.`;
    return {name: plan.name || 'Seu plano', expired, details: start + term + ' ' + (expired ? 'Acesso para novas publicações e negociações encerrado. Consulte a administração. Seus negócios existentes continuam disponíveis.' : limits)};
}
function renderSubscriptionAccess() {
    const summary = fdbSubscriptionSummary(user?.subscriptions);
    for (const id of ['subscriptionCard', 'subscriptionListingBanner']) {
        const target = document.getElementById(id);
        if (!target) continue;
        target.replaceChildren();
        const title = document.createElement('b');
        title.textContent = summary.name + (summary.expired ? ' — encerrado' : '');
        const detail = document.createElement('div');
        detail.className = 'muted';
        detail.textContent = summary.details;
        if (id === 'subscriptionListingBanner') detail.style.color = '#ddd';
        target.append(title, detail);
    }
}
const loadMeBeforeSubscription = loadMe;
loadMe = async function () {
    const result = await loadMeBeforeSubscription();
    renderSubscriptionAccess();
    return result;
};
if (typeof user !== 'undefined' && user) renderSubscriptionAccess();

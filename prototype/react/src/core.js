export const DOMAIN_ACTIONS = [
  'renew',
  'toggle_auto_renew',
  'lock_domain',
  'unlock_domain',
  'get_auth_code',
  'transfer_out',
  'manage_privacy',
  'manage_child_hosts',
  'change_registrant',
  'manage_ssl',
  'delete_domain',
];

export function canShowDomainAction(c, action) {
  const map = {
    renew: c.canRenew,
    toggle_auto_renew: c.supportsAutoRenew,
    lock_domain: c.supportsTransferLock,
    unlock_domain: c.supportsTransferLock,
    get_auth_code: c.supportsAuthCode,
    transfer_out: c.supportsTransferOut,
    manage_privacy: c.supportsPrivacy,
    manage_child_hosts: c.supportsChildHosts,
    change_registrant: c.supportsChangeRegistrant,
    manage_ssl: c.supportsSsl,
    delete_domain: c.supportsDelete,
  };
  return Boolean(map[action]);
}

export function visibleDomainActions(c) {
  return DOMAIN_ACTIONS.filter((action) => canShowDomainAction(c, action));
}

const lifecycleLabels = {
  registration_pending: 'Registration pending',
  active: 'Active',
  expiring: 'Expiring',
  expired: 'Expired',
  renewal_pending: 'Renewal pending',
  transfer_pending: 'Transfer pending',
  suspended: 'Suspended',
  deletion_pending: 'Deletion pending',
  deleted: 'Deleted',
};

export function lifecycleLabel(status) {
  if (!lifecycleLabels[status]) throw new Error(`Unknown lifecycle status: ${status}`);
  return lifecycleLabels[status];
}

export function operationPresentation(operation) {
  if (operation.reconciliationRequired) {
    return { tone: 'info', title: 'Verifying registrar state', action: null };
  }
  const table = {
    pending: { tone: 'neutral', title: 'Queued', action: null },
    processing: { tone: 'info', title: 'Processing', action: null },
    action_required: { tone: 'warning', title: 'Action required', action: operation.nextAction ?? null },
    completed: { tone: 'success', title: 'Completed', action: null },
    failed: { tone: 'danger', title: 'Failed', action: operation.nextAction ?? null },
  };
  return table[operation.status] ?? { tone: 'neutral', title: 'Unknown', action: null };
}

export function formatMoney(amount, currency = 'USD') {
  return new Intl.NumberFormat('en-US', { style: 'currency', currency }).format(amount);
}

export function daysUntil(dateLike, now = new Date('2026-09-21T12:00:00Z')) {
  const target = new Date(dateLike);
  return Math.max(0, Math.ceil((target - now) / 86400000));
}

export function domainAttentionState(domain) {
  if (domain.paymentFailed) {
    return {
      kind: 'payment_failed',
      tone: 'danger',
      title: 'Auto-renew payment failed',
      message: 'Update the payment method to protect the upcoming renewal.',
      action: 'fix_payment',
    };
  }
  if (domain.nameserverStatus === 'action_required') {
    return {
      kind: 'nameserver_issue',
      tone: 'warning',
      title: 'Nameserver configuration needs attention',
      message: 'One or more configured nameservers are not responding as expected.',
      action: 'review_nameservers',
    };
  }
  if (domain.lifecycleStatus === 'expiring') {
    return {
      kind: 'expiring',
      tone: 'warning',
      title: 'Domain expires soon',
      message: 'Review renewal settings before the expiration date.',
      action: 'renew',
    };
  }
  if (domain.lifecycleStatus === 'transfer_pending' || domain.transfer) {
    return {
      kind: 'transfer_processing',
      tone: 'info',
      title: 'Transfer is processing',
      message: 'The registrar is processing this transfer. No action is currently required.',
      action: 'view_transfer',
    };
  }
  return {
    kind: 'healthy',
    tone: 'success',
    title: 'Domain is healthy',
    message: 'No registrar action is currently required.',
    action: null,
  };
}

export function portfolioHealthSummary(domains) {
  const states = domains.map(domainAttentionState);
  return {
    total: domains.length,
    healthy: states.filter((s) => s.kind === 'healthy').length,
    attention: states.filter((s) => s.tone === 'danger' || s.tone === 'warning').length,
    expiringSoon: domains.filter((d) => d.lifecycleStatus === 'expiring' || daysUntil(d.expiresAt) <= 30).length,
    transfersInProgress: domains.filter((d) => d.lifecycleStatus === 'transfer_pending' || Boolean(d.transfer)).length,
  };
}

export function attentionDomains(domains) {
  const priority = { danger: 0, warning: 1 };
  return domains
    .map((domain) => ({ domain, state: domainAttentionState(domain) }))
    .filter(({ state }) => state.tone === 'danger' || state.tone === 'warning')
    .sort((a, b) => priority[a.state.tone] - priority[b.state.tone] || daysUntil(a.domain.expiresAt) - daysUntil(b.domain.expiresAt));
}

export function upcomingRenewals(domains, limit = 3) {
  return [...domains]
    .sort((a, b) => new Date(a.expiresAt) - new Date(b.expiresAt))
    .slice(0, limit)
    .map((domain) => ({ domain, daysRemaining: daysUntil(domain.expiresAt) }));
}

export function filterDomains(domains, { query = '', status = '', workspace = '' } = {}) {
  const q = query.trim().toLowerCase();
  return domains.filter((domain) => {
    if (q && !domain.name.toLowerCase().includes(q)) return false;
    if (status && status !== 'all' && domain.lifecycleStatus !== status) return false;
    if (workspace && workspace !== 'all' && domain.workspace !== workspace) return false;
    return true;
  });
}

export function renewalQuote(domain, years = 1) {
  const allowed = domain?.caps?.renewalYears ?? [];
  if (!allowed.includes(years)) throw new Error(`Unsupported renewal period: ${years}`);
  const unitPrice = Number(domain.price);
  return {
    years,
    unitPrice,
    total: Math.round(unitPrice * years * 100) / 100,
  };
}

export function transferReadiness(domain) {
  if (domain.transfer) {
    return {
      status: 'processing',
      ready: false,
      blockers: [],
      nextAction: 'No action required',
    };
  }
  if (!domain?.caps?.supportsTransferOut) {
    return {
      status: 'unsupported',
      ready: false,
      blockers: ['transfer_out_unsupported'],
      nextAction: null,
    };
  }
  const blockers = [];
  if (domain.caps.supportsTransferLock && domain.transferLock === 'locked') blockers.push('transfer_lock');
  if (!domain.caps.supportsAuthCode) blockers.push('auth_code_unavailable');
  if (blockers.length) {
    return {
      status: 'blocked',
      ready: false,
      blockers,
      nextAction: blockers[0] === 'transfer_lock' ? 'Unlock domain' : 'Contact support',
    };
  }
  return {
    status: 'ready',
    ready: true,
    blockers: [],
    nextAction: 'Start transfer out',
  };
}

export function portfolioDomainSummary(domains) {
  const attention = domains.filter((domain) => {
    const state = domainAttentionState(domain);
    return state.tone === 'danger' || state.tone === 'warning';
  }).length;
  return {
    total: domains.length,
    active: domains.filter((d) => d.lifecycleStatus === 'active').length,
    expiring: domains.filter((d) => d.lifecycleStatus === 'expiring').length,
    transferPending: domains.filter((d) => d.lifecycleStatus === 'transfer_pending').length,
    autoRenewOn: domains.filter((d) => Boolean(d.autoRenew)).length,
    locked: domains.filter((d) => d.transferLock === 'locked').length,
    attention,
  };
}

export function renewedExpirationDate(expiresAt, years = 1) {
  const date = new Date(`${expiresAt}T00:00:00Z`);
  date.setUTCFullYear(date.getUTCFullYear() + years);
  return date.toISOString().slice(0, 10);
}

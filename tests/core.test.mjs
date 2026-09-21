import test from 'node:test';
import assert from 'node:assert/strict';
import {
  canShowDomainAction,
  visibleDomainActions,
  lifecycleLabel,
  operationPresentation,
} from '../src/core.js';

const caps = {
  canRenew: true,
  renewalYears: [1, 2],
  supportsAutoRenew: true,
  supportsTransferLock: false,
  supportsTransferOut: false,
  supportsAuthCode: false,
  supportsPrivacy: true,
  supportsChildHosts: false,
  supportsChangeRegistrant: false,
  supportsSsl: true,
  supportsDelete: false,
};

test('capability gate hides unsupported registrar actions', () => {
  assert.equal(canShowDomainAction(caps, 'unlock_domain'), false);
  assert.equal(canShowDomainAction(caps, 'get_auth_code'), false);
  assert.equal(canShowDomainAction(caps, 'delete_domain'), false);
  assert.equal(canShowDomainAction(caps, 'renew'), true);
  assert.equal(canShowDomainAction(caps, 'manage_privacy'), true);
});

test('visible actions are derived only from capability data', () => {
  assert.deepEqual(visibleDomainActions(caps), [
    'renew',
    'toggle_auto_renew',
    'manage_privacy',
    'manage_ssl',
  ]);
});

test('transfer lock is not treated as lifecycle', () => {
  assert.equal(lifecycleLabel('active'), 'Active');
  assert.equal(lifecycleLabel('transfer_pending'), 'Transfer pending');
  assert.throws(() => lifecycleLabel('locked'), /Unknown lifecycle status/);
});

test('ambiguous registrar writes present reconciliation instead of retry', () => {
  assert.deepEqual(operationPresentation({
    status: 'processing',
    reconciliationRequired: true,
    message: 'Registrar response is being verified.',
  }), {
    tone: 'info',
    title: 'Verifying registrar state',
    action: null,
  });
});

import {
  domainAttentionState,
  portfolioHealthSummary,
} from '../src/core.js';

test('domain attention prioritizes payment failure over other states', () => {
  const result = domainAttentionState({
    name: 'northstar.io',
    paymentFailed: true,
    lifecycleStatus: 'expiring',
    nameserverStatus: 'action_required',
    transfer: null,
    expiresAt: '2026-09-26',
  });
  assert.equal(result.tone, 'danger');
  assert.equal(result.kind, 'payment_failed');
  assert.equal(result.action, 'fix_payment');
});

test('portfolio health summarizes attention without counting healthy domains', () => {
  const domains = [
    { paymentFailed: true, lifecycleStatus: 'expiring', nameserverStatus: 'configured', transfer: null, expiresAt: '2026-09-26' },
    { paymentFailed: false, lifecycleStatus: 'transfer_pending', nameserverStatus: 'configured', transfer: 'processing', expiresAt: '2027-01-11' },
    { paymentFailed: false, lifecycleStatus: 'active', nameserverStatus: 'configured', transfer: null, expiresAt: '2027-09-21' },
  ];
  assert.deepEqual(portfolioHealthSummary(domains), {
    total: 3,
    healthy: 1,
    attention: 1,
    expiringSoon: 1,
    transfersInProgress: 1,
  });
});

import {
  attentionDomains,
  upcomingRenewals,
} from '../src/core.js';

test('attention domains returns only actionable warning or danger states in priority order', () => {
  const domains = [
    { id:'healthy', paymentFailed:false, lifecycleStatus:'active', nameserverStatus:'configured', transfer:null, expiresAt:'2027-09-21' },
    { id:'dns', paymentFailed:false, lifecycleStatus:'active', nameserverStatus:'action_required', transfer:null, expiresAt:'2027-05-02' },
    { id:'pay', paymentFailed:true, lifecycleStatus:'expiring', nameserverStatus:'configured', transfer:null, expiresAt:'2026-09-26' },
  ];
  assert.deepEqual(attentionDomains(domains).map(x=>x.domain.id), ['pay','dns']);
});

test('upcoming renewals sorts soonest first and includes days remaining', () => {
  const domains = [
    { id:'later', expiresAt:'2027-01-11', price:18 },
    { id:'soon', expiresAt:'2026-09-26', price:42 },
    { id:'middle', expiresAt:'2026-11-02', price:28 },
  ];
  assert.deepEqual(upcomingRenewals(domains, 2).map(x=>[x.domain.id,x.daysRemaining]), [
    ['soon',5],
    ['middle',42],
  ]);
});

import {
  filterDomains,
  renewalQuote,
  transferReadiness,
  portfolioDomainSummary,
} from '../src/core.js';

test('domain portfolio filters combine query, lifecycle status, and workspace', () => {
  const domains = [
    { id:'a', name:'example.com', lifecycleStatus:'active', workspace:'Acme Studio' },
    { id:'b', name:'example.dev', lifecycleStatus:'transfer_pending', workspace:'Labs' },
    { id:'c', name:'northstar.io', lifecycleStatus:'active', workspace:'Labs' },
  ];
  assert.deepEqual(filterDomains(domains, {
    query: 'example',
    status: 'active',
    workspace: 'Acme Studio',
  }).map(d=>d.id), ['a']);
});

test('renewal quote totals only supported renewal periods', () => {
  const domain = { price: 14.99, caps: { renewalYears: [1,2,3] } };
  assert.deepEqual(renewalQuote(domain, 2), {
    years: 2,
    unitPrice: 14.99,
    total: 29.98,
  });
  assert.throws(() => renewalQuote(domain, 5), /Unsupported renewal period/);
});

test('transfer readiness blocks transfer out while transfer lock is enabled', () => {
  const domain = {
    transfer: null,
    transferLock: 'locked',
    caps: { supportsTransferOut: true, supportsTransferLock: true, supportsAuthCode: true },
  };
  assert.deepEqual(transferReadiness(domain), {
    status: 'blocked',
    ready: false,
    blockers: ['transfer_lock'],
    nextAction: 'Unlock domain',
  });
});

test('transfer readiness reports processing separately from readiness checks', () => {
  const domain = {
    transfer: 'processing',
    transferLock: 'unlocked',
    caps: { supportsTransferOut: true, supportsTransferLock: true, supportsAuthCode: true },
  };
  assert.deepEqual(transferReadiness(domain), {
    status: 'processing',
    ready: false,
    blockers: [],
    nextAction: 'No action required',
  });
});

test('portfolio domain summary separates lifecycle and security state', () => {
  const domains = [
    { lifecycleStatus:'active', autoRenew:true, transferLock:'locked', paymentFailed:false, nameserverStatus:'configured', transfer:null, expiresAt:'2027-09-21' },
    { lifecycleStatus:'expiring', autoRenew:true, transferLock:'locked', paymentFailed:true, nameserverStatus:'configured', transfer:null, expiresAt:'2026-09-26' },
    { lifecycleStatus:'transfer_pending', autoRenew:false, transferLock:'unlocked', paymentFailed:false, nameserverStatus:'configured', transfer:'processing', expiresAt:'2027-01-11' },
  ];
  assert.deepEqual(portfolioDomainSummary(domains), {
    total: 3,
    active: 1,
    expiring: 1,
    transferPending: 1,
    autoRenewOn: 2,
    locked: 2,
    attention: 1,
  });
});

import { renewedExpirationDate } from '../src/core.js';

test('renewed expiration applies the selected renewal period', () => {
  assert.equal(renewedExpirationDate('2027-09-21', 3), '2030-09-21');
});

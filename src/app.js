import { canShowDomainAction, lifecycleLabel, formatMoney, daysUntil, domainAttentionState, portfolioHealthSummary, attentionDomains, upcomingRenewals, filterDomains, renewalQuote, transferReadiness, portfolioDomainSummary, renewedExpirationDate } from './core.js';

const icons = {
  overview: '<svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>',
  globe: '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a15 15 0 0 1 0 18M12 3a15 15 0 0 0 0 18"/></svg>',
  transfer: '<svg viewBox="0 0 24 24"><path d="M7 7h12l-3-3M17 17H5l3 3"/><path d="m19 7-3 3M5 17l3-3"/></svg>',
  lock: '<svg viewBox="0 0 24 24"><rect x="5" y="10" width="14" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>',
  card: '<svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18"/></svg>',
  users: '<svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="3"/><path d="M3 19c0-3 2.5-5 6-5s6 2 6 5"/><path d="M16 5a3 3 0 0 1 0 6M18 14c2 .6 3 2.2 3 5"/></svg>',
  settings: '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-2.8 2.8-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.6v.2h-4V21a1.7 1.7 0 0 0-1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1L4.2 17l.1-.1a1.7 1.7 0 0 0 .3-1.9A1.7 1.7 0 0 0 3 14H2.8v-4H3a1.7 1.7 0 0 0 1.6-1 1.7 1.7 0 0 0-.3-1.9L4.2 7 7 4.2l.1.1a1.7 1.7 0 0 0 1.9.3A1.7 1.7 0 0 0 10 3V2.8h4V3a1.7 1.7 0 0 0 1 1.6 1.7 1.7 0 0 0 1.9-.3l.1-.1L19.8 7l-.1.1a1.7 1.7 0 0 0-.3 1.9 1.7 1.7 0 0 0 1.6 1h.2v4H21a1.7 1.7 0 0 0-1.6 1Z"/></svg>',
  search: '<svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>',
  bell: '<svg viewBox="0 0 24 24"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/><path d="M10 21h4"/></svg>',
  plus: '<svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>',
  arrow: '<svg viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg>',
  dots: '<svg viewBox="0 0 24 24"><circle cx="5" cy="12" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/></svg>',
  check: '<svg viewBox="0 0 24 24"><path d="m5 12 4 4L19 6"/></svg>',
  warning: '<svg viewBox="0 0 24 24"><path d="M10.3 4.1 2.4 18a2 2 0 0 0 1.7 3h15.8a2 2 0 0 0 1.7-3L13.7 4.1a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4M12 17h.01"/></svg>',
  key: '<svg viewBox="0 0 24 24"><circle cx="8" cy="15" r="4"/><path d="m11 12 9-9M17 6l3 3M14 9l3 3"/></svg>',
  shield: '<svg viewBox="0 0 24 24"><path d="M12 3 5 6v5c0 5 3 8 7 10 4-2 7-5 7-10V6l-7-3Z"/><path d="m9 12 2 2 4-4"/></svg>',
  external: '<svg viewBox="0 0 24 24"><path d="M14 5h5v5M10 14 19 5"/><path d="M19 13v5a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1h5"/></svg>',
  menu: '<svg viewBox="0 0 24 24"><path d="M4 7h16M4 12h16M4 17h16"/></svg>',
  x: '<svg viewBox="0 0 24 24"><path d="m6 6 12 12M18 6 6 18"/></svg>',
  chevron: '<svg viewBox="0 0 24 24"><path d="m8 10 4 4 4-4"/></svg>',
};
const I = (name) => `<span class="icon">${icons[name] || icons.globe}</span>`;

const fullCaps = {
  canRenew: true, renewalYears: [1,2,3,5], supportsAutoRenew: true,
  supportsTransferLock: true, supportsTransferOut: true, supportsAuthCode: true,
  supportsPrivacy: true, supportsChildHosts: true, supportsChangeRegistrant: true,
  supportsSsl: true, supportsDelete: true,
};
const limitedCaps = {
  ...fullCaps, supportsPrivacy: false, supportsChildHosts: false,
  supportsChangeRegistrant: false, supportsDelete: false,
};

const state = {
  route: 'overview',
  domainId: 'd1',
  domainTab: 'overview',
  mobileNav: false,
  command: false,
  cart: [],
  cartOpen: false,
  moreOpen: false,
  operation: null,
  renewYears: 1,
  domainFilters: { query:'', status:'all', workspace:'all' },
  domains: [
    { id:'d1', name:'example.com', workspace:'Acme Studio', lifecycleStatus:'active', expiresAt:'2027-09-21', registeredAt:'2022-09-21', autoRenew:true, transferLock:'locked', privacyStatus:'active', sslStatus:'active', nameserverStatus:'configured', transfer:null, price:14.99, caps:fullCaps, nameservers:['ns1.example.net','ns2.example.net'] },
    { id:'d2', name:'northstar.io', workspace:'Acme Studio', lifecycleStatus:'expiring', expiresAt:'2026-09-26', registeredAt:'2024-09-26', autoRenew:true, transferLock:'locked', privacyStatus:'active', sslStatus:'active', nameserverStatus:'configured', transfer:null, paymentFailed:true, price:42.00, caps:fullCaps, nameservers:['ns1.platformdns.com','ns2.platformdns.com'] },
    { id:'d3', name:'orbitlabs.dev', workspace:'Labs', lifecycleStatus:'transfer_pending', expiresAt:'2027-01-11', registeredAt:'2025-01-11', autoRenew:false, transferLock:'unlocked', privacyStatus:'unsupported', sslStatus:'pending', nameserverStatus:'configured', transfer:'processing', price:18.00, caps:limitedCaps, nameservers:['ns1.vercel-dns.com','ns2.vercel-dns.com'] },
    { id:'d4', name:'madebyacme.co', workspace:'Acme Studio', lifecycleStatus:'active', expiresAt:'2027-05-02', registeredAt:'2023-05-02', autoRenew:true, transferLock:'locked', privacyStatus:'active', sslStatus:'inactive', nameserverStatus:'action_required', transfer:null, price:28.00, caps:fullCaps, nameservers:['ns1.oldhost.com','ns2.oldhost.com'] },
    { id:'d5', name:'framekit.app', workspace:'Labs', lifecycleStatus:'active', expiresAt:'2028-03-14', registeredAt:'2026-03-14', autoRenew:true, transferLock:'locked', privacyStatus:'active', sslStatus:'active', nameserverStatus:'configured', transfer:null, price:19.00, caps:fullCaps, nameservers:['ns1.platformdns.com','ns2.platformdns.com'] },
  ],
  activities: [
    ['Nameserver update','example.com · Completed','12 min ago'],
    ['Transfer started','orbitlabs.dev · Processing','2h ago'],
    ['Auto-renew payment failed','northstar.io · Action required','4h ago'],
    ['Domain registered','framekit.app · Completed','Yesterday'],
  ],
};

const app = document.querySelector('#app');
const modalRoot = document.querySelector('#modal-root');
const toastRoot = document.querySelector('#toast-root');

function domainById(id = state.domainId) { return state.domains.find(d => d.id === id) || state.domains[0]; }
function routeTo(route, extras={}) { state.route = route; Object.assign(state, extras); state.mobileNav=false; state.moreOpen=false; render(); window.scrollTo({top:0, behavior:'instant'}); }
function statusTone(status) {
  return ({ active:'success', expiring:'warning', transfer_pending:'info', expired:'danger', suspended:'danger' })[status] || '';
}
function badge(label, tone='') { return `<span class="badge ${tone}">${label}</span>`; }
function iconButton(icon, label, action='') { return `<button class="icon-btn" ${action} aria-label="${label}">${I(icon)}</button>`; }
function toast(title, text='') {
  toastRoot.innerHTML = `<div class="toast"><strong>${title}</strong>${text?`<span>${text}</span>`:''}</div>`;
  setTimeout(()=>toastRoot.innerHTML='', 2600);
}

function navItem(route, label, icon, count='') {
  const active = state.route === route || (route === 'domains' && state.route === 'domain');
  return `<a class="nav-item ${active?'active':''}" href="#" data-route="${route}">${I(icon)}<span>${label}</span>${count?`<span class="nav-count">${count}</span>`:''}</a>`;
}
function sidebar() {
  return `<aside class="sidebar ${state.mobileNav?'open':''}">
    <div class="brand"><span class="brand-mark">D</span><span>Domain Console</span></div>
    <button class="workspace-switcher" data-workspace-menu>
      <span class="left"><span class="workspace-icon">AC</span><span class="workspace-name">Acme Studio</span></span>${I('chevron')}
    </button>
    <div class="nav-label">Manage</div><nav class="nav">
      ${navItem('overview','Overview','overview')}
      ${navItem('domains','Domains','globe', state.domains.length)}
      ${navItem('transfers','Transfers','transfer','1')}
      ${navItem('ssl','SSL','shield')}
      ${navItem('billing','Billing','card')}
    </nav>
    <div class="nav-label">Workspace</div><nav class="nav">
      ${navItem('workspace','Members','users')}
      ${navItem('settings','Settings','settings')}
    </nav>
    <div class="sidebar-bottom">
      <a class="nav-item" href="#">${I('external')}<span>Documentation</span></a>
      <div class="user-block"><span class="avatar">IM</span><span class="user-meta"><strong>Ibrahim</strong><span>ibrahim@example.com</span></span></div>
    </div>
  </aside>`;
}
function topbar() {
  const crumb = state.route === 'domain' ? `Domains / ${domainById().name}` : ({overview:'Overview',domains:'Domains',transfers:'Transfers',ssl:'SSL',billing:'Billing',workspace:'Workspace',settings:'Settings',search:'Domain Search',checkout:'Checkout'})[state.route] || 'Overview';
  return `<header class="topbar">
    <button class="icon-btn mobile-menu" data-mobile-menu>${I('menu')}</button>
    <span class="breadcrumb">${crumb}</span>
    <div class="topbar-actions">
      <button class="search-trigger" data-command>${I('search')}<span class="search-copy">Search domains or actions…</span><kbd>⌘ K</kbd></button>
      ${iconButton('bell','Notifications','data-notifications')}
      <button class="btn primary" data-buy-domain>${I('plus')}<span class="mobile-hide">Buy domain</span></button>
    </div>
  </header>`;
}

function layout(content) {
  return `<div class="app">${sidebar()}<div class="mobile-backdrop ${state.mobileNav?'open':''}" data-mobile-close></div><div class="shell">${topbar()}<main class="main">${content}</main></div>${cartDrawer()}<div class="overlay ${state.cartOpen?'open':''}" data-cart-close></div></div>`;
}

function overviewPage() {
  const health = portfolioHealthSummary(state.domains);
  const attention = attentionDomains(state.domains);
  const renewals = upcomingRenewals(state.domains, 4);
  const autoRenewOn = state.domains.filter(d=>d.autoRenew).length;
  const processing = health.transfersInProgress;
  const healthPct = Math.round((health.healthy / Math.max(health.total,1)) * 100);
  return `<div class="page-header dashboard-header"><div class="page-title"><span class="eyebrow">Portfolio</span><h1>Overview</h1><p>Everything that needs attention across your domains, renewals, transfers, security, and billing.</p></div><div class="header-actions"><button class="btn" data-route="transfers">${I('transfer')}Transfer domain</button><button class="btn primary" data-buy-domain>${I('plus')}Buy domain</button></div></div>
  <section class="metrics dashboard-metrics">
    ${metric('Total domains', health.total, 'Across 2 workspaces', 'globe')}
    ${metric('Auto-renew', `${autoRenewOn}/${health.total}`, `${Math.round(autoRenewOn/health.total*100)}% coverage`, 'card')}
    ${metric('Expiring soon', health.expiringSoon, 'Within 30 days', 'warning')}
    ${metric('Active transfers', processing, processing ? 'Registrar processing' : 'Nothing in progress', 'transfer')}
  </section>
  <section class="portfolio-health card">
    <div class="health-main">
      <div class="health-copy"><span class="eyebrow">Portfolio health</span><div class="health-title-row"><h2>${health.healthy} of ${health.total} domains are clear</h2>${badge(`${healthPct}% healthy`, health.attention ? 'warning' : 'success')}</div><p>Healthy domains need no registrar action. Processing transfers are tracked separately from issues that need you.</p></div>
      <div class="health-score"><strong>${health.attention}</strong><span>need attention</span></div>
    </div>
    <div class="health-bar" aria-label="Portfolio health"><span class="health-segment healthy" style="width:${health.healthy/health.total*100}%"></span><span class="health-segment processing" style="width:${processing/health.total*100}%"></span><span class="health-segment attention" style="width:${health.attention/health.total*100}%"></span></div>
    <div class="health-legend"><span><i class="legend-dot healthy"></i>${health.healthy} healthy</span><span><i class="legend-dot processing"></i>${processing} processing</span><span><i class="legend-dot attention"></i>${health.attention} attention</span></div>
  </section>
  <div class="dashboard-grid">
    <div class="dashboard-main-column">
      <section class="card attention-card"><div class="card-header"><div><span class="eyebrow">Priority</span><h2>Needs attention</h2></div><span class="card-count">${attention.length}</span></div><div class="attention-list">${attention.map(attentionRow).join('')}</div></section>
      <section class="card"><div class="card-header"><div><span class="eyebrow">Portfolio</span><h2>Recent domains</h2></div><button class="btn ghost small card-action" data-route="domains">View all ${I('arrow')}</button></div>${domainTable(state.domains.slice(0,5), true)}</section>
    </div>
    <aside class="dashboard-side-column">
      <section class="card quick-card"><div class="card-header"><div><span class="eyebrow">Shortcuts</span><h2>Quick actions</h2></div></div><div class="quick-grid">${quickActionTile('Buy domain','Search and register a new domain','plus','data-buy-domain')}${quickActionTile('Transfer in','Move an existing domain','transfer','data-transfer-in')}${quickActionTile('Billing','Renewals and payment methods','card','data-route="billing"')}${quickActionTile('Invite member','Add someone to this workspace','users','data-route="workspace"')}</div></section>
      <section class="card renewal-card"><div class="card-header"><div><span class="eyebrow">Next up</span><h2>Upcoming renewals</h2></div><button class="btn ghost small card-action" data-route="billing">Billing ${I('arrow')}</button></div><div class="renewal-list">${renewals.map(renewalRow).join('')}</div></section>
      <section class="card"><div class="card-header"><div><span class="eyebrow">Workspace</span><h2>Recent activity</h2></div></div><div class="card-body compact-body"><div class="activity-list">${state.activities.slice(0,4).map(a=>activityItem(...a)).join('')}</div></div></section>
    </aside>
  </div>`;
}
function attentionRow({domain,state:attention}) {
  const action = attention.kind === 'payment_failed'
    ? `<button class="btn small" data-fix-payment>Fix payment</button>`
    : `<button class="btn small" data-open-domain-tab="${domain.id}:${attention.kind==='nameserver_issue'?'nameservers':'renewal'}">Review</button>`;
  return `<div class="attention-row"><div class="attention-symbol ${attention.tone}">${I(attention.kind==='payment_failed'?'card':'warning')}</div><div class="attention-copy"><div class="attention-title"><strong>${domain.name}</strong>${badge(attention.title,attention.tone)}</div><p>${attention.message}</p><span>${daysUntil(domain.expiresAt)} days until expiration · ${domain.workspace}</span></div><div class="attention-action">${action}</div></div>`;
}
function renewalRow({domain,daysRemaining}) {
  const tone = domain.paymentFailed ? 'danger' : daysRemaining <= 30 ? 'warning' : domain.autoRenew ? 'success' : '';
  const label = domain.paymentFailed ? 'Payment failed' : domain.autoRenew ? 'Auto-renew on' : 'Auto-renew off';
  return `<button class="renewal-row" data-open-domain-tab="${domain.id}:renewal"><div class="renewal-date"><strong>${new Date(domain.expiresAt).toLocaleDateString('en-US',{month:'short',day:'numeric'})}</strong><span>${daysRemaining}d</span></div><div class="renewal-copy"><strong>${domain.name}</strong><span>${formatMoney(domain.price)} / year</span></div><div class="renewal-state">${badge(label,tone)}</div></button>`;
}
function quickActionTile(title,desc,icon,attrs) { return `<button class="quick-tile" ${attrs}><span class="quick-icon">${I(icon)}</span><span class="quick-copy"><strong>${title}</strong><span>${desc}</span></span>${I('arrow')}</button>`; }

function metric(label, value, note, icon='arrow') { return `<div class="metric"><div class="metric-label"><span>${label}</span><span class="metric-icon">${I(icon)}</span></div><div class="metric-value">${value}</div><div class="metric-note">${note}</div></div>`; }
function activityItem(title, desc, time) { return `<div class="activity-item"><div class="activity-mark">${I(title.includes('Transfer')?'transfer':title.includes('payment')?'card':'globe')}</div><div class="activity-main"><strong>${title}</strong><span>${desc}</span></div><span class="activity-time">${time}</span></div>`; }

function domainTable(domains, compact=false) {
  if (compact) {
    return `<div class="table-wrap"><table class="portfolio-table compact"><thead><tr><th>Domain</th><th>Status</th><th>Expiry</th><th>Renewal</th><th></th></tr></thead><tbody>${domains.map(d=>{const attention=domainAttentionState(d);return `<tr class="clickable domain-row" data-domain="${d.id}"><td><div class="domain-identity-cell"><span class="domain-monogram">${d.name[0].toUpperCase()}</span><span><strong class="domain-cell">${d.name}</strong><span class="domain-sub">${d.workspace}</span></span></div></td><td>${badge(lifecycleLabel(d.lifecycleStatus),statusTone(d.lifecycleStatus))}</td><td><strong>${new Date(d.expiresAt).toLocaleDateString('en-US',{month:'short',day:'numeric'})}</strong><div class="domain-sub">${daysUntil(d.expiresAt)} days</div></td><td>${d.paymentFailed?badge('Payment failed','danger'):d.autoRenew?badge('Auto-renew','success'):badge('Manual')}</td><td class="row-arrow">${I('arrow')}</td></tr>`}).join('')}</tbody></table></div>`;
  }
  return `<div class="table-wrap portfolio-table-wrap"><table class="portfolio-table"><thead><tr><th>Domain</th><th>Lifecycle</th><th>Renewal</th><th>Security</th><th>Services</th><th>Workspace</th><th></th></tr></thead><tbody>
    ${domains.map(d=>{const attention=domainAttentionState(d);const rowTone=(attention.tone==='danger'||attention.tone==='warning')?'attention':'';return `<tr class="clickable domain-row ${rowTone}" data-domain="${d.id}"><td><div class="domain-identity-cell"><span class="domain-monogram">${d.name[0].toUpperCase()}</span><span><strong class="domain-cell">${d.name}</strong><span class="domain-sub">${attention.kind==='healthy'?'No action required':attention.title}</span></span></div></td><td>${badge(lifecycleLabel(d.lifecycleStatus),statusTone(d.lifecycleStatus))}<div class="domain-sub">${d.transfer?'Registrar processing':'Lifecycle state'}</div></td><td><div class="table-primary">${new Date(d.expiresAt).toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'})}</div><div class="domain-sub">${d.paymentFailed?'Payment failed':d.autoRenew?'Auto-renew enabled':'Manual renewal'}</div></td><td><div class="security-inline">${I('lock')}<span>${d.transferLock==='locked'?'Locked':'Unlocked'}</span></div><div class="domain-sub">${d.caps.supportsAuthCode?'Auth code protected':'Auth code unavailable'}</div></td><td><div class="service-pills"><span class="service-pill ${d.privacyStatus==='active'?'good':''}">Privacy ${d.privacyStatus==='unsupported'?'—':d.privacyStatus==='active'?'On':'Off'}</span><span class="service-pill ${d.sslStatus==='active'?'good':d.sslStatus==='pending'?'info':''}">SSL ${d.sslStatus==='unsupported'?'—':d.sslStatus}</span></div></td><td><div class="table-primary">${d.workspace}</div></td><td class="row-arrow">${I('arrow')}</td></tr>`}).join('')}
  </tbody></table></div>`;
}

function domainsPage() {
  const summary=portfolioDomainSummary(state.domains);
  const filtered=filterDomains(state.domains,state.domainFilters);
  const workspaces=[...new Set(state.domains.map(d=>d.workspace))];
  return `<div class="page-header domains-header"><div class="page-title"><span class="eyebrow">Portfolio</span><h1>Domains</h1><p>Search, review health, and manage every registrar capability from one portfolio.</p></div><div class="header-actions"><button class="btn" data-route="transfers">${I('transfer')}Transfer domain</button><button class="btn primary" data-buy-domain>${I('plus')}Buy domain</button></div></div>
  <section class="domains-summary card"><div class="portfolio-stat"><span>Total</span><strong>${summary.total}</strong><small>Across 2 workspaces</small></div><div class="portfolio-stat"><span>Active</span><strong>${summary.active}</strong><small>Lifecycle active</small></div><div class="portfolio-stat"><span>Auto-renew</span><strong>${summary.autoRenewOn}/${summary.total}</strong><small>${Math.round(summary.autoRenewOn/Math.max(summary.total,1)*100)}% coverage</small></div><div class="portfolio-stat"><span>Transfer lock</span><strong>${summary.locked}</strong><small>Domains protected</small></div><div class="portfolio-stat ${summary.attention?'attention':''}"><span>Needs attention</span><strong>${summary.attention}</strong><small>${summary.expiring} expiring · ${summary.transferPending} transferring</small></div></section>
  <section class="card domains-portfolio-card"><div class="domains-toolbar"><label class="input-shell domain-search-input">${I('search')}<input id="domain-filter" value="${state.domainFilters.query}" placeholder="Search domains…" /></label><select class="select" id="domain-status-filter"><option value="all">All lifecycle states</option><option value="active" ${state.domainFilters.status==='active'?'selected':''}>Active</option><option value="expiring" ${state.domainFilters.status==='expiring'?'selected':''}>Expiring</option><option value="transfer_pending" ${state.domainFilters.status==='transfer_pending'?'selected':''}>Transfer pending</option></select><select class="select" id="domain-workspace-filter"><option value="all">All workspaces</option>${workspaces.map(w=>`<option value="${w}" ${state.domainFilters.workspace===w?'selected':''}>${w}</option>`).join('')}</select><span class="toolbar-spacer"></span><span class="results-count" id="domain-result-count">${filtered.length} domain${filtered.length===1?'':'s'}</span><button class="btn small">Export</button></div><div id="domain-table-container">${domainTable(filtered)}</div></section>`;
}

function domainDetailPage() {
  const d = domainById();
  const tabs = ['overview','renewal','contacts','nameservers','privacy','security','transfers','ssl','billing','activity'];
  const attention=domainAttentionState(d);
  const expiryDays=daysUntil(d.expiresAt);
  return `<button class="back-link" data-route="domains">${I('arrow')}Back to domains</button>
  <section class="domain-control-hero"><div class="domain-control-main"><div class="domain-identity-head"><span class="domain-hero-icon">${d.name[0].toUpperCase()}</span><div><span class="eyebrow">Domain control center</span><div class="domain-title-row"><h1>${d.name}</h1>${badge(lifecycleLabel(d.lifecycleStatus),statusTone(d.lifecycleStatus))}</div><p>${d.workspace} · Registered ${new Date(d.registeredAt).toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'})}</p></div></div><div class="header-actions domain-hero-actions">${canShowDomainAction(d.caps,'renew')?`<button class="btn primary" data-tab="renewal">Renew domain</button>`:''}<div class="pill-menu"><button class="icon-btn" data-more>${I('dots')}</button>${state.moreOpen?moreMenu(d):''}</div></div></div><div class="domain-control-kpis"><div><span>Expires</span><strong>${new Date(d.expiresAt).toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'})}</strong><small>${expiryDays} days remaining</small></div><div><span>Auto-renew</span><strong>${d.autoRenew?'Enabled':'Disabled'}</strong><small>${d.paymentFailed?'Payment action required':d.autoRenew?'Visa •••• 4242':'Manual renewal'}</small></div><div><span>Transfer lock</span><strong>${d.transferLock==='locked'?'Locked':'Unlocked'}</strong><small>${d.transferLock==='locked'?'Protected from transfer':'Transfer-out possible'}</small></div><div class="domain-health-kpi ${attention.tone}"><span>Domain health</span><strong>${attention.kind==='healthy'?'Clear':attention.tone==='danger'?'Action required':attention.tone==='warning'?'Review':'Processing'}</strong><small>${attention.title}</small></div></div></section>
  ${domainAlert(d)}
  <div class="domain-tabs-wrap"><nav class="domain-tabs">${tabs.map(t=>`<button class="tab ${state.domainTab===t?'active':''}" data-tab="${t}">${t[0].toUpperCase()+t.slice(1)}</button>`).join('')}</nav></div>
  <section class="tab-panel">${renderDomainTab(d)}</section>`;
}

function moreMenu(d) {
  return `<div class="more-menu"><button data-advanced>${I('settings')}Advanced information</button>${canShowDomainAction(d.caps,'transfer_out')?`<button data-tab="transfers">${I('transfer')}Transfer domain away</button>`:''}${canShowDomainAction(d.caps,'manage_privacy')?`<button data-tab="privacy">${I('shield')}Manage privacy</button>`:''}${canShowDomainAction(d.caps,'delete_domain')?`<button class="danger" data-danger>${I('warning')}Danger Zone</button>`:''}</div>`;
}
function domainAlert(d) {
  if (d.paymentFailed) return `<div class="alert danger"><div class="alert-icon">${I('warning')}</div><div class="alert-copy"><div class="alert-title">Auto-renew failed</div><div class="alert-text">Update your payment method to avoid expiration in ${daysUntil(d.expiresAt)} days.</div></div><button class="btn small" data-fix-payment>Fix payment</button></div>`;
  if (d.lifecycleStatus==='transfer_pending') return `<div class="alert info"><div class="alert-icon">${I('transfer')}</div><div class="alert-copy"><div class="alert-title">Transfer is processing</div><div class="alert-text">The registrar is processing this transfer. No action is currently required.</div></div><button class="btn small" data-tab="transfers">View transfer</button></div>`;
  if (d.nameserverStatus==='action_required') return `<div class="alert warning"><div class="alert-icon">${I('warning')}</div><div class="alert-copy"><div class="alert-title">Nameserver configuration needs attention</div><div class="alert-text">One or more configured nameservers are not responding as expected.</div></div><button class="btn small" data-tab="nameservers">Review</button></div>`;
  return '';
}
function renderDomainTab(d) {
  const fn = {overview:domainOverview,renewal:renewalTab,contacts:contactsTab,nameservers:nameserversTab,privacy:privacyTab,security:securityTab,transfers:domainTransfersTab,ssl:domainSslTab,billing:domainBillingTab,activity:domainActivityTab}[state.domainTab] || domainOverview;
  return fn(d);
}
function domainOverview(d) {
  const transferLabel = d.transfer ? d.transfer[0].toUpperCase()+d.transfer.slice(1) : 'None';
  return `<div class="section-title compact-title"><div><span class="eyebrow">At a glance</span><h2>Domain status</h2></div></div>${statusGrid([
    ['Registration', lifecycleLabel(d.lifecycleStatus), 'Registrar lifecycle'],
    ['Expiration', new Date(d.expiresAt).toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'}), `${daysUntil(d.expiresAt)} days remaining`],
    ['Auto-renew', d.autoRenew?'Enabled':'Disabled', d.autoRenew?'Renews automatically':'Manual renewal'],
    ['Transfer Lock', d.transferLock==='locked'?'Locked':'Unlocked', 'Security state'],
    ['Privacy', d.privacyStatus==='unsupported'?'Unsupported':d.privacyStatus[0].toUpperCase()+d.privacyStatus.slice(1), 'ID Shield'],
    ['Nameservers', d.nameserverStatus==='configured'?'Configured':'Action required', d.nameservers.join(' · ')],
    ['Transfer', transferLabel, d.transfer?'Registrar processing':'No active transfer'],
    ['SSL', d.sslStatus==='unsupported'?'Unsupported':d.sslStatus[0].toUpperCase()+d.sslStatus.slice(1), 'Certificate product'],
  ])}
  <div class="section-title"><div><span class="eyebrow">Controls</span><h2>Quick actions</h2></div><p>Only capabilities supported for this TLD are shown.</p></div><div class="domain-action-grid">${quickActions(d)}</div>
  <div class="domain-overview-grid"><div><div class="section-title"><div><span class="eyebrow">Registrar</span><h2>Nameservers & DNS</h2></div></div><div class="card"><div class="setting-row"><div class="setting-copy"><strong>Nameservers</strong><span>${d.nameservers.join(' · ')}</span></div><button class="btn small" data-tab="nameservers">Manage</button></div><div class="setting-row"><div class="setting-copy"><strong>DNS Zone</strong><span>Managed externally. DNS record CRUD is intentionally not exposed in confirmed v1.</span></div>${badge('External','dotless')}</div></div></div><div><div class="section-title"><div><span class="eyebrow">Ownership</span><h2>Portfolio details</h2></div></div><div class="card"><dl class="card-body" style="margin:0">${def('Workspace',d.workspace)}${def('Registered',new Date(d.registeredAt).toLocaleDateString())}${def('Renewal price',formatMoney(d.price))}${def('Provider state','Synchronized')}</dl></div></div></div><div id="advanced-slot"></div>`;
}

function statusGrid(items) { return `<div class="status-grid">${items.map(([a,b,c])=>`<div class="status-cell"><div class="status-label">${a}</div><div class="status-value">${b}</div><div class="status-sub">${c}</div></div>`).join('')}</div>`; }
function quickActions(d) {
  const actions=[];
  const tile=(title,desc,icon,attrs,tone='')=>`<button class="domain-action-tile ${tone}" ${attrs}><span class="domain-action-icon">${I(icon)}</span><span><strong>${title}</strong><small>${desc}</small></span>${I('arrow')}</button>`;
  if(canShowDomainAction(d.caps,'renew')) actions.push(tile('Renew domain',`${formatMoney(d.price)} / year`,'card','data-tab="renewal"'));
  if(canShowDomainAction(d.caps,'toggle_auto_renew')) actions.push(tile(d.autoRenew?'Disable auto-renew':'Enable auto-renew',d.autoRenew?'Currently enabled':'Manual renewal','card','data-toggle-renew'));
  if(canShowDomainAction(d.caps,d.transferLock==='locked'?'unlock_domain':'lock_domain')) actions.push(tile(d.transferLock==='locked'?'Unlock domain':'Lock domain',d.transferLock==='locked'?'Required before transfer out':'Protect against transfer','lock','data-tab="security"'));
  actions.push(tile('Nameservers',d.nameservers.join(' · '),'globe','data-tab="nameservers"'));
  actions.push(tile('Edit contacts','Registrant, admin, technical, billing','users','data-tab="contacts"'));
  if(canShowDomainAction(d.caps,'get_auth_code')) actions.push(tile('Get auth code','Protected by re-authentication','key','data-auth-code'));
  return actions.join('');
}

function def(a,b) { return `<div class="definition"><dt>${a}</dt><dd>${b}</dd></div>`; }

function renewalTab(d) {
  const years=d.caps.renewalYears.includes(state.renewYears)?state.renewYears:d.caps.renewalYears[0];
  const quote=renewalQuote(d,years);
  return `<div class="renewal-layout"><div class="renewal-main-column"><section class="card renewal-control-card"><div class="card-header"><div><span class="eyebrow">Renewal</span><h2>Renew ${d.name}</h2></div>${badge(d.autoRenew?'Auto-renew enabled':'Manual renewal',d.autoRenew?'success':'')}</div><div class="renewal-expiry-block"><div><span>Current expiration</span><strong>${new Date(d.expiresAt).toLocaleDateString('en-US',{month:'long',day:'numeric',year:'numeric'})}</strong><small>${daysUntil(d.expiresAt)} days remaining</small></div><div class="renewal-expiry-meter"><span style="width:${Math.max(8,Math.min(100,daysUntil(d.expiresAt)/365*100))}%"></span></div></div>${d.paymentFailed?`<div class="inline-warning danger"><span>${I('warning')}</span><div><strong>Last auto-renew attempt failed</strong><small>Update your payment method before the next renewal attempt.</small></div><button class="btn small" data-fix-payment>Fix payment</button></div>`:''}<div class="renewal-builder"><div class="field"><label>Renewal period</label><select id="renew-years">${d.caps.renewalYears.map(y=>`<option value="${y}" ${y===years?'selected':''}>${y} year${y>1?'s':''}</option>`).join('')}</select><small>Available periods are controlled by this TLD.</small></div><div class="renewal-total"><span>Total today</span><strong>${formatMoney(quote.total)}</strong><small>${formatMoney(quote.unitPrice)} / year × ${quote.years}</small></div></div><div class="renewal-actions"><button class="btn primary" data-renew-now>Renew for ${quote.years} year${quote.years>1?'s':''}</button><button class="btn" data-toggle-renew>${d.autoRenew?'Disable':'Enable'} auto-renew</button></div>${state.operation?operationCard(state.operation):''}</section><section class="card"><div class="card-header"><div><span class="eyebrow">History</span><h2>Renewal activity</h2></div></div><div class="card-body"><div class="activity-list">${d.paymentFailed?activityItem('Auto-renew attempt','Failed · Payment method declined','Today'):''}${activityItem('Auto-renew setting',d.autoRenew?'Enabled':'Disabled','Sep 12')}${activityItem('Domain renewal','Completed · 1 year','Sep 21, 2025')}</div></div></section></div><aside class="renewal-side-column"><section class="card renewal-details-card"><div class="card-header"><h2>Renewal details</h2></div><div class="card-body"><dl style="margin:0">${def('Customer renewal price',`${formatMoney(d.price)} / year`)}${def('Payment method','Visa •••• 4242')}${def('Auto-renew',d.autoRenew?'Enabled':'Disabled')}${def('Last renewal attempt',d.paymentFailed?'Failed · Today':'Completed · Sep 21, 2025')}</dl></div></section><section class="renewal-help"><span class="domain-action-icon">${I('card')}</span><div><strong>Renewal protection</strong><p>Auto-renew attempts are tracked separately from registrar renewal processing. Ambiguous writes remain in processing until reconciled.</p></div></section></aside></div>`;
}

function contactsTab(d) {
  return `<div class="section-title"><h2>Domain contacts</h2><p>Change Registrant is distinct from routine contact editing.</p></div><div class="contact-grid">
    ${contactCard('Registrant','Ahmed Mohamed','ahmed@example.com',true,d.caps.supportsChangeRegistrant)}
    ${contactCard('Administrative','Ahmed Mohamed','ahmed@example.com',true,false)}
    ${contactCard('Technical','Company IT','it@acme.com',true,false)}
    ${contactCard('Billing','Finance Team','finance@acme.com',true,false)}
  </div>`;
}
function contactCard(type,name,email,editable,changeRegistrant) { return `<div class="contact-card"><div class="contact-top"><span class="contact-type">${type}</span><div>${editable?'<button class="btn ghost small" data-edit-contact>Edit</button>':''}${changeRegistrant?'<button class="btn ghost small" data-change-registrant>Change Registrant</button>':''}</div></div><h3>${name}</h3><p>${email}</p></div>`; }
function nameserversTab(d) {
  return `<div class="grid-2"><div><div class="card"><div class="card-header"><h2>Current Nameservers</h2><button class="btn small card-action" data-change-ns>Change Nameservers</button></div><div class="ns-list">${d.nameservers.map((ns,i)=>`<div class="ns-row"><span class="ns-index">${i+1}</span><span class="ns-name">${ns}</span><span class="ns-state">${badge('Configured','success')}</span></div>`).join('')}</div></div></div><div><div class="card"><div class="card-header"><h2>DNS Zone</h2></div><div class="card-body"><p style="margin:0;color:var(--text-2)">Managed externally. This product currently manages registrar nameservers, not DNS zone records.</p></div></div></div></div>
  ${d.caps.supportsChildHosts?`<div class="section-title"><h2>Registered Hosts</h2><p>Advanced · Child nameservers</p><button class="btn small" style="margin-left:auto" data-create-host>${I('plus')}Create Host</button></div><div class="card"><div class="host-row"><span class="ns-name">ns1.${d.name}</span><span>1.2.3.4</span><span style="margin-left:auto"><button class="btn ghost small" data-host-details>View details</button><button class="btn ghost small" data-host-edit>Change IP</button><button class="btn ghost small">Delete</button></span></div><div class="host-row"><span class="ns-name">ns2.${d.name}</span><span>1.2.3.5</span><span style="margin-left:auto"><button class="btn ghost small">View details</button><button class="btn ghost small">Change IP</button><button class="btn ghost small">Delete</button></span></div></div>`:''}`;
}
function privacyTab(d) {
  if(!d.caps.supportsPrivacy) return empty('Privacy is not supported for this TLD','The registrar does not expose an ID Shield/privacy product for this domain.');
  return `<div class="card"><div class="card-header"><h2>ID Shield / Domain Privacy</h2>${badge('Active','success')}</div><div class="setting-row"><div class="setting-copy"><strong>Privacy protection</strong><span>Registrant contact details are protected where registry policy allows.</span></div><button class="switch on" data-privacy-toggle aria-label="Toggle privacy"></button></div><div class="card-body"><dl style="margin:0">${def('Current price','$3.99 / year')}${def('Renewal price','$3.99 / year')}${def('Auto-renew','Enabled')}${def('Associated charge','INV-2026-0912')}</dl></div></div>`;
}
function securityTab(d) {
  const protectedCount=(d.transferLock==='locked'?1:0)+(d.caps.supportsAuthCode?1:0)+1;
  return `<section class="security-posture card"><div class="security-posture-copy"><span class="eyebrow">Security posture</span><h2>${protectedCount}/3 protections active</h2><p>Transfer controls and sensitive credentials are isolated from the domain lifecycle.</p></div><div class="security-ring"><strong>${protectedCount}</strong><span>of 3</span></div></section><div class="security-control-grid"><section class="security-control-card"><span class="security-control-icon">${I('lock')}</span><div><span class="eyebrow">Transfer lock</span><h3>${d.transferLock==='locked'?'Locked':'Unlocked'}</h3><p>${d.transferLock==='locked'?'Transfers away are blocked until you explicitly unlock this domain.':'This domain can currently be transferred away.'}</p></div>${d.caps.supportsTransferLock?`<button class="btn small" data-toggle-lock>${d.transferLock==='locked'?'Unlock domain':'Lock domain'}</button>`:badge('Unsupported','dotless')}</section><section class="security-control-card"><span class="security-control-icon">${I('key')}</span><div><span class="eyebrow">Authorization code</span><h3>${d.caps.supportsAuthCode?'Protected':'Unavailable'}</h3><p>${d.caps.supportsAuthCode?'Re-authentication is required before reveal or copy.':'This TLD/provider does not expose an authorization code.'}</p></div>${d.caps.supportsAuthCode?`<button class="btn small" data-auth-code>Get authorization code</button>`:badge('Unsupported','dotless')}</section><section class="security-control-card"><span class="security-control-icon">${I('shield')}</span><div><span class="eyebrow">Account protection</span><h3>2FA enabled</h3><p>Sensitive registrar actions require an authenticated account session.</p></div>${badge('Protected','success')}</section></div><div class="section-title"><div><span class="eyebrow">Sensitive actions</span><h2>Advanced security</h2></div></div><div class="card"><div class="setting-row"><div class="setting-copy"><strong>Domain Password</strong><span>Provider-specific advanced credential. Only use when required for a confirmed provider operation.</span></div><button class="btn small">Update Domain Password</button></div><div class="setting-row"><div class="setting-copy"><strong>Transfer domain away</strong><span>Review transfer readiness, unlock the domain, and retrieve the protected authorization code.</span></div><button class="btn small" data-tab="transfers">Review transfers</button></div></div>`;
}

function domainTransfersTab(d) {
  const readiness=transferReadiness(d);
  const tone=readiness.status==='ready'?'success':readiness.status==='processing'?'info':readiness.status==='blocked'?'warning':'';
  const checklist=[
    ['Transfer Lock',d.transferLock==='locked'?'Locked':'Unlocked',d.transferLock==='unlocked'],
    ['Authorization Code',d.caps.supportsAuthCode?'Available after re-auth':'Unavailable',d.caps.supportsAuthCode],
    ['Transfer capability',d.caps.supportsTransferOut?'Supported':'Unsupported',d.caps.supportsTransferOut],
  ];
  return `<section class="transfer-state-hero card ${tone}"><div><span class="eyebrow">Domain transfer</span><div class="transfer-state-title"><h2>${readiness.status==='processing'?'Transfer in progress':readiness.status==='ready'?'Ready to transfer out':readiness.status==='blocked'?'Action needed before transfer':'Transfer out unavailable'}</h2>${badge(readiness.status==='processing'?'Processing':readiness.status==='ready'?'Ready':readiness.status==='blocked'?'Blocked':'Unsupported',tone)}</div><p>${readiness.status==='processing'?'The registrar is processing this transfer. Status updates are reconciled automatically.':readiness.status==='ready'?'Security checks are clear. You can start an outgoing transfer when ready.':readiness.nextAction||'This registrar capability is not available for the domain.'}</p></div><div class="transfer-hero-actions">${d.caps.supportsTransferLock&&d.transferLock==='locked'?`<button class="btn" data-toggle-lock>Unlock domain</button>`:''}${d.caps.supportsAuthCode?`<button class="btn" data-auth-code>Get Auth Code</button>`:''}${readiness.ready?`<button class="btn primary" data-transfer-out>Transfer Out</button>`:''}${d.transfer?`<button class="btn" data-cancel-transfer>Cancel Transfer</button>`:''}</div></section><div class="transfer-layout"><section class="card"><div class="card-header"><div><span class="eyebrow">Readiness</span><h2>Transfer checklist</h2></div></div><div class="transfer-checklist">${checklist.map(([label,value,ok])=>`<div class="transfer-check"><span class="check-state ${ok?'ok':'blocked'}">${ok?I('check'):I('warning')}</span><div><strong>${label}</strong><span>${value}</span></div>${badge(ok?'Ready':'Review',ok?'success':'warning')}</div>`).join('')}</div></section><section class="card"><div class="card-header"><div><span class="eyebrow">${d.transfer?'Progress':'Current state'}</span><h2>${d.transfer?'Transfer timeline':'No active transfer'}</h2></div></div><div class="card-body">${d.transfer?transferTimeline():`<div class="empty-state transfer-empty"><div class="empty-icon">${I('transfer')}</div><h3>No active transfer</h3><p>When a transfer starts, the full registrar timeline and next action will appear here.</p></div>`}</div></section></div>`;
}

function transferTimeline() { return `<div class="timeline"><div class="timeline-item"><span class="timeline-dot done"></span><div class="timeline-copy"><strong>Started</strong><span>Transfer request created</span></div><span class="timeline-time">Sep 18</span></div><div class="timeline-item"><span class="timeline-dot done"></span><div class="timeline-copy"><strong>Authorization verified</strong><span>Registrar accepted authorization</span></div><span class="timeline-time">Sep 18</span></div><div class="timeline-item"><span class="timeline-dot"></span><div class="timeline-copy"><strong>Registrar processing</strong><span>No action required</span></div><span class="timeline-time">Now</span></div><div class="timeline-item"><span class="timeline-dot"></span><div class="timeline-copy"><strong>Completed</strong><span>Pending</span></div><span class="timeline-time">—</span></div></div>`; }
function domainSslTab(d) {
  if(!d.caps.supportsSsl) return empty('SSL products are not available for this domain','This provider/TLD combination does not currently expose SSL ordering.');
  return `<div class="card"><div class="card-header"><h2>SSL certificates for ${d.name}</h2><button class="btn small card-action" data-order-ssl>${I('plus')}Order certificate</button></div><div class="table-wrap"><table><thead><tr><th>Certificate</th><th>Status</th><th>Expires</th><th>Validation</th><th></th></tr></thead><tbody><tr><td><strong>Standard DV</strong><div class="domain-sub">${d.name}</div></td><td>${badge(d.sslStatus==='active'?'Active':'Pending',d.sslStatus==='active'?'success':'info')}</td><td>Aug 18, 2027</td><td>Email / DNS</td><td>${I('arrow')}</td></tr></tbody></table></div></div>`;
}
function domainBillingTab(d) {
  return `<div class="grid-2"><div><div class="card"><div class="card-header"><h2>Domain billing</h2></div><div class="table-wrap"><table><thead><tr><th>Date</th><th>Description</th><th>Amount</th><th>Status</th></tr></thead><tbody><tr><td>Sep 21, 2025</td><td>Domain renewal · 1 year</td><td>${formatMoney(d.price)}</td><td>${badge('Paid','success')}</td></tr><tr><td>Sep 21, 2025</td><td>ID Shield</td><td>$3.99</td><td>${badge('Paid','success')}</td></tr></tbody></table></div></div></div><div><div class="card"><div class="card-header"><h2>Payment method</h2></div><div class="card-body"><strong>Visa •••• 4242</strong><p style="color:var(--text-2);margin:4px 0 13px">Expires 08/29</p><button class="btn small" data-fix-payment>Manage payment method</button></div></div></div></div>`;
}
function domainActivityTab(d) {
  return `<div class="card"><div class="card-header"><h2>Domain Activity</h2><p>Customer-safe registrar operation history</p></div><div class="card-body"><div class="activity-list">${activityItem('Renewal','Completed','Sep 21, 14:32')}${activityItem('Nameserver Update','Completed','Sep 18, 12:14')}${activityItem('Transfer Lock','Locked','Sep 17, 09:22')}${activityItem('Contact update','Completed','Sep 14, 16:10')}</div></div></div>`;
}
function operationCard(op) { return `<div class="operation-card ${op.status==='completed'?'success':op.status==='failed'?'failed':''}"><div>${op.status==='completed'?I('check'):I('transfer')}</div><div class="operation-copy"><strong>${op.title}</strong><span>${op.message}</span></div>${badge(op.status==='completed'?'Completed':'Processing',op.status==='completed'?'success':'info')}</div>`; }
function empty(title,text) { return `<div class="card"><div class="empty-state"><div class="empty-icon">${I('globe')}</div><h3>${title}</h3><p>${text}</p></div></div>`; }

function transfersPage() {
  const active=state.domains.filter(d=>d.transfer);
  return `<div class="page-header"><div class="page-title"><span class="eyebrow">Portfolio</span><h1>Transfers</h1><p>Track incoming and outgoing registrar transfers across every workspace.</p></div><button class="btn primary" data-transfer-in>${I('plus')}Transfer domain in</button></div><section class="metrics transfer-metrics">${metric('In progress',active.length,'Registrar processing','transfer')}${metric('Action required',0,'Nothing waiting on you','warning')}${metric('Ready to transfer',state.domains.filter(d=>transferReadiness(d).ready).length,'Unlocked and supported','check')}${metric('Completed',8,'Last 12 months','globe')}</section><section class="card transfer-portfolio-card"><div class="card-header"><div><span class="eyebrow">Active</span><h2>Current transfers</h2></div><span class="card-count">${active.length}</span></div>${active.length?`<div class="table-wrap"><table><thead><tr><th>Domain</th><th>Direction</th><th>Status</th><th>Started</th><th>Latest update</th><th>Next action</th><th></th></tr></thead><tbody>${active.map(d=>`<tr data-domain="${d.id}" class="clickable"><td><div class="domain-identity-cell"><span class="domain-monogram">${d.name[0].toUpperCase()}</span><span><strong>${d.name}</strong><span class="domain-sub">${d.workspace}</span></span></div></td><td>Transfer in</td><td>${badge('Processing','info')}</td><td>Sep 18, 2026</td><td>Registrar processing</td><td>No action required</td><td class="row-arrow">${I('arrow')}</td></tr>`).join('')}</tbody></table></div>`:emptyInline('No active transfers','Transfer activity will appear here as soon as it starts.')}</section><section class="transfer-guide"><span class="domain-action-icon">${I('transfer')}</span><div><strong>Transfers stay asynchronous</strong><p>We show provider processing, action-required, completed, and failed states without pretending registrar operations are instant.</p></div></section>`;
}

function sslPage() {
  return `<div class="page-header"><div class="page-title"><h1>SSL</h1><p>Certificate products across all domains. This is certificate lifecycle management, not website HTTPS monitoring.</p></div><button class="btn primary" data-order-ssl>${I('plus')}Order certificate</button></div><section class="metrics">${metric('Active',4,'Certificates')}${metric('Pending',1,'Awaiting validation')}${metric('Expiring',0,'Next 30 days')}${metric('Action required',0,'Nothing needs you')}</section><div class="card"><div class="table-wrap"><table><thead><tr><th>Domain</th><th>Product</th><th>Status</th><th>Expires</th><th>Validation</th></tr></thead><tbody>${['example.com','framekit.app','madebyacme.co'].map((d,i)=>`<tr><td><strong>${d}</strong></td><td>Standard DV</td><td>${badge(i===2?'Pending':'Active',i===2?'info':'success')}</td><td>Aug ${18+i}, 2027</td><td>${i===2?'Approval required':'Completed'}</td></tr>`).join('')}</tbody></table></div></div>`;
}
function billingPage() {
  return `<div class="page-header"><div class="page-title"><h1>Billing</h1><p>Payment methods, domain charges, invoices, and upcoming renewals.</p></div><button class="btn">Billing settings</button></div><div class="grid-2"><div><div class="card"><div class="card-header"><h2>Upcoming renewals</h2></div><div class="table-wrap"><table><thead><tr><th>Domain</th><th>Date</th><th>Amount</th><th>Auto-renew</th></tr></thead><tbody><tr><td><strong>northstar.io</strong></td><td>Sep 26</td><td>$42.00</td><td>${badge('Payment failed','danger')}</td></tr><tr><td><strong>orbitlabs.dev</strong></td><td>Jan 11</td><td>$18.00</td><td>${badge('Off')}</td></tr></tbody></table></div></div><div class="card"><div class="card-header"><h2>Invoices</h2></div><div class="table-wrap"><table><tbody><tr><td>INV-2026-0912</td><td>Sep 12, 2026</td><td>$18.99</td><td>${badge('Paid','success')}</td></tr><tr><td>INV-2026-0821</td><td>Aug 21, 2026</td><td>$14.99</td><td>${badge('Paid','success')}</td></tr></tbody></table></div></div></div><div><div class="card"><div class="card-header"><h2>Payment method</h2></div><div class="card-body"><strong>Visa •••• 4242</strong><p style="color:var(--text-2);margin:5px 0 14px">Default · Expires 08/29</p><button class="btn small" data-fix-payment>Update payment method</button></div></div></div></div>`;
}
function workspacePage() {
  return `<div class="page-header"><div class="page-title"><h1>Workspace</h1><p>Manage members and lightweight roles for Acme Studio.</p></div><button class="btn primary" data-invite>${I('plus')}Invite member</button></div><div class="card"><div class="table-wrap"><table><thead><tr><th>Member</th><th>Role</th><th>Status</th><th>Joined</th></tr></thead><tbody><tr><td><strong>Ibrahim Maher</strong><div class="domain-sub">ibrahim@example.com</div></td><td>Owner</td><td>${badge('Active','success')}</td><td>Jan 4, 2026</td></tr><tr><td><strong>Sarah Chen</strong><div class="domain-sub">sarah@acme.com</div></td><td>Admin</td><td>${badge('Active','success')}</td><td>Feb 11, 2026</td></tr><tr><td><strong>Omar Ali</strong><div class="domain-sub">omar@acme.com</div></td><td>Member</td><td>${badge('Active','success')}</td><td>May 20, 2026</td></tr></tbody></table></div></div>`;
}
function settingsPage() {
  return `<div class="page-header"><div class="page-title"><h1>Settings</h1><p>Personal preferences and notification controls.</p></div></div><div class="card"><div class="card-header"><h2>Notifications</h2></div>${setting('Domain expiration emails','Receive reminders before domains expire.',true)}${setting('Transfer updates','Email when a transfer requires action or completes.',true)}${setting('SSL updates','Certificate approval and expiration notices.',true)}${setting('Product announcements','Occasional product updates.',false)}</div><div class="section-title"><h2>Security</h2></div><div class="card">${setting('Two-factor authentication','Protect sensitive domain actions with a second factor.',true)}<div class="setting-row"><div class="setting-copy"><strong>Active sessions</strong><span>Review browsers and devices signed into your account.</span></div><button class="btn small">Review sessions</button></div></div>`;
}
function setting(title,desc,on) { return `<div class="setting-row"><div class="setting-copy"><strong>${title}</strong><span>${desc}</span></div><button class="switch ${on?'on':''}" data-generic-switch aria-label="Toggle ${title}"></button></div>`; }

function searchPage() {
  return `<div class="search-page"><div class="search-hero"><h1>Find your next domain</h1><p>Search availability, compare extensions, and register through your workspace.</p></div><div class="domain-search-box">${I('search')}<input id="domain-search" value="acme" placeholder="Search a domain"/><button class="btn primary" data-search-submit>Search</button></div><div class="search-results" id="search-results">${searchResults('acme')}</div></div>`;
}
function searchResults(q) {
  const clean=(q||'acme').replace(/\..*$/,'').replace(/[^a-z0-9-]/gi,'').toLowerCase() || 'acme';
  const options=[[`${clean}.com`,14.99],[`${clean}.io`,42],[`${clean}.dev`,18],[`get${clean}.com`,14.99],[`${clean}hq.co`,28]];
  return options.map(([d,p],i)=>`<div class="search-result"><div><div class="domain">${d}</div><div class="availability">${i===0?'Available':'Available · Recommended alternative'}</div></div><div class="price"><strong>${formatMoney(p)}</strong><span>/ first year</span></div><button class="btn small ${i===0?'primary':''}" data-add-cart="${d}" data-price="${p}">Add</button></div>`).join('');
}
function cartDrawer() {
  const total=state.cart.reduce((s,i)=>s+i.price,0);
  return `<aside class="cart-drawer ${state.cartOpen?'open':''}"><div class="drawer-head"><h2>Cart · ${state.cart.length} item${state.cart.length===1?'':'s'}</h2>${iconButton('x','Close cart','data-cart-close')}</div><div class="drawer-body">${state.cart.length?state.cart.map(i=>`<div class="cart-item"><div class="cart-row"><strong>${i.domain}</strong><strong>${formatMoney(i.price)}</strong></div><div class="domain-sub">1 year · Auto-renew on</div></div>`).join(''):emptyInline('Your cart is empty','Add an available domain to continue.')}</div><div class="drawer-foot"><div class="cart-total"><span>Total today</span><span>${formatMoney(total)}</span></div><button class="btn primary" style="width:100%" data-checkout ${state.cart.length?'':'disabled'}>Continue to checkout</button></div></aside>`;
}
function emptyInline(title,text){return `<div class="empty-state"><div class="empty-icon">${I('globe')}</div><h3>${title}</h3><p>${text}</p></div>`;}
function checkoutPage() {
  const total=state.cart.reduce((s,i)=>s+i.price,0);
  if(!state.cart.length) return `<div class="page-header"><div class="page-title"><h1>Checkout</h1><p>Your cart is empty.</p></div></div>${empty('No domains selected','Search for a domain to continue.')} `;
  return `<div class="page-header"><div class="page-title"><h1>Checkout</h1><p>Review registrant details and payment before completing registration.</p></div></div><div class="checkout-grid"><div><div class="checkout-section"><div class="head">Registrant contact</div><div class="body"><div class="form-grid"><div class="field"><label>First name</label><input value="Ibrahim"/></div><div class="field"><label>Last name</label><input value="Maher"/></div><div class="field full"><label>Email</label><input type="email" value="ibrahim@example.com"/></div><div class="field"><label>Country</label><select><option>Egypt</option></select></div><div class="field"><label>Phone</label><input value="+20 10 0000 0000"/></div></div></div></div><div class="checkout-section"><div class="head">Payment method</div><div class="body"><div class="setting-row" style="padding:0;border:0"><div class="setting-copy"><strong>Visa •••• 4242</strong><span>Default payment method</span></div>${badge('Selected','success')}</div></div></div></div><aside class="card order-summary"><div class="card-header"><h2>Order summary</h2></div><div class="card-body">${state.cart.map(i=>`<div class="summary-line"><span>${i.domain} · 1 year</span><strong>${formatMoney(i.price)}</strong></div>`).join('')}<div class="summary-line"><span>WHOIS privacy</span><span>Included where supported</span></div><div class="summary-line total"><span>Total</span><span>${formatMoney(total)}</span></div><button class="btn primary" style="width:100%;margin-top:14px" data-complete-purchase>Complete purchase</button><p style="font-size:11px;color:var(--text-3);margin:10px 0 0">Registrar provisioning may continue asynchronously after payment.</p></div></aside></div>`;
}

function renderRoute() {
  return ({overview:overviewPage,domains:domainsPage,domain:domainDetailPage,transfers:transfersPage,ssl:sslPage,billing:billingPage,workspace:workspacePage,settings:settingsPage,search:searchPage,checkout:checkoutPage}[state.route] || overviewPage)();
}
function render() {
  app.innerHTML = layout(renderRoute());
  wire();
}

function wire() {
  document.querySelectorAll('[data-route]').forEach(el=>el.addEventListener('click',e=>{e.preventDefault();routeTo(el.dataset.route)}));
  document.querySelectorAll('[data-domain]').forEach(el=>el.addEventListener('click',e=>{ if(e.target.closest('button')) return; state.domainId=el.dataset.domain; state.domainTab='overview'; state.renewYears=1; routeTo('domain'); }));
  document.querySelectorAll('[data-tab]').forEach(el=>el.addEventListener('click',()=>{ state.domainTab=el.dataset.tab; state.moreOpen=false; render(); }));
  document.querySelectorAll('[data-open-domain-tab]').forEach(el=>el.addEventListener('click',()=>{const [id,tab]=el.dataset.openDomainTab.split(':');state.domainId=id;state.domainTab=tab||'overview';state.operation=null;state.renewYears=1;routeTo('domain');}));
  document.querySelectorAll('[data-buy-domain]').forEach(el=>el.addEventListener('click',()=>routeTo('search')));
  document.querySelectorAll('[data-command]').forEach(el=>el.addEventListener('click',openCommand));
  document.querySelectorAll('[data-mobile-menu]').forEach(el=>el.addEventListener('click',()=>{state.mobileNav=true;render()}));
  document.querySelectorAll('[data-mobile-close]').forEach(el=>el.addEventListener('click',()=>{state.mobileNav=false;render()}));
  document.querySelectorAll('[data-cart-close]').forEach(el=>el.addEventListener('click',()=>{state.cartOpen=false;render()}));
  document.querySelectorAll('[data-more]').forEach(el=>el.addEventListener('click',()=>{state.moreOpen=!state.moreOpen;render()}));
  document.querySelectorAll('[data-auth-code]').forEach(el=>el.addEventListener('click',reauthModal));
  document.querySelectorAll('[data-toggle-renew]').forEach(el=>el.addEventListener('click',()=>{const d=domainById();d.autoRenew=!d.autoRenew;toast(`Auto-renew ${d.autoRenew?'enabled':'disabled'}`,d.name);render()}));
  document.querySelectorAll('[data-toggle-lock]').forEach(el=>el.addEventListener('click',()=>{const d=domainById();d.transferLock=d.transferLock==='locked'?'unlocked':'locked';toast(`Domain ${d.transferLock}`,d.name);render()}));
  document.querySelectorAll('[data-renew-now]').forEach(el=>el.addEventListener('click',renewDomain));
  document.querySelectorAll('[data-fix-payment]').forEach(el=>el.addEventListener('click',()=>routeTo('billing')));
  document.querySelectorAll('[data-change-ns]').forEach(el=>el.addEventListener('click',nameserverModal));
  document.querySelectorAll('[data-edit-contact]').forEach(el=>el.addEventListener('click',contactModal));
  document.querySelectorAll('[data-change-registrant]').forEach(el=>el.addEventListener('click',changeRegistrantModal));
  document.querySelectorAll('[data-danger]').forEach(el=>el.addEventListener('click',dangerModal));
  document.querySelectorAll('[data-advanced]').forEach(el=>el.addEventListener('click',showAdvanced));
  document.querySelectorAll('[data-order-ssl]').forEach(el=>el.addEventListener('click',()=>toast('SSL order flow','Certificate product configuration would open here.')));
  document.querySelectorAll('[data-transfer-out]').forEach(el=>el.addEventListener('click',transferOutModal));
  document.querySelectorAll('[data-transfer-in]').forEach(el=>el.addEventListener('click',()=>modal('Transfer a domain in','Enter a domain to validate transfer eligibility.',`<div class="field"><label>Domain</label><input placeholder="example.com" autofocus></div>`, 'Continue', ()=>toast('Eligibility check started','This is a prototype flow.'))));
  document.querySelectorAll('[data-invite]').forEach(el=>el.addEventListener('click',()=>modal('Invite workspace member','Invite a teammate and assign a lightweight v1 role.',`<div class="field"><label>Email</label><input type="email" placeholder="name@company.com"></div><div class="field" style="margin-top:12px"><label>Role</label><select><option>Member</option><option>Admin</option></select></div>`,'Send invite',()=>toast('Invitation sent'))));
  document.querySelectorAll('[data-generic-switch],[data-privacy-toggle]').forEach(el=>el.addEventListener('click',()=>el.classList.toggle('on')));
  document.querySelectorAll('[data-notifications]').forEach(el=>el.addEventListener('click',()=>toast('Notifications','2 domain events are available in this prototype.')));
  const filter=document.querySelector('#domain-filter'); if(filter) filter.addEventListener('input',()=>{state.domainFilters.query=filter.value;refreshDomainTable();});
  const statusFilter=document.querySelector('#domain-status-filter'); if(statusFilter) statusFilter.addEventListener('change',()=>{state.domainFilters.status=statusFilter.value;refreshDomainTable();});
  const workspaceFilter=document.querySelector('#domain-workspace-filter'); if(workspaceFilter) workspaceFilter.addEventListener('change',()=>{state.domainFilters.workspace=workspaceFilter.value;refreshDomainTable();});
  const renewYears=document.querySelector('#renew-years'); if(renewYears) renewYears.addEventListener('change',()=>{state.renewYears=Number(renewYears.value);render();});
  document.querySelectorAll('[data-cancel-transfer]').forEach(el=>el.addEventListener('click',()=>{const d=domainById();d.transfer=null;d.lifecycleStatus='active';toast('Transfer cancelled',d.name);render();}));
  const search=document.querySelector('#domain-search'); const submit=document.querySelector('[data-search-submit]'); if(search && submit) submit.addEventListener('click',()=>{document.querySelector('#search-results').innerHTML=searchResults(search.value); wireCartButtons();});
  wireCartButtons();
  const checkout=document.querySelector('[data-checkout]'); if(checkout) checkout.addEventListener('click',()=>{state.cartOpen=false;routeTo('checkout')});
  const purchase=document.querySelector('[data-complete-purchase]'); if(purchase) purchase.addEventListener('click',completePurchase);
}
function refreshDomainTable(){
  const filtered=filterDomains(state.domains,state.domainFilters);
  const container=document.querySelector('#domain-table-container');
  if(container) container.innerHTML=domainTable(filtered);
  const count=document.querySelector('#domain-result-count');
  if(count) count.textContent=`${filtered.length} domain${filtered.length===1?'':'s'}`;
  wireTableOnly();
}
function wireTableOnly(){ document.querySelectorAll('[data-domain]').forEach(el=>el.addEventListener('click',()=>{state.domainId=el.dataset.domain;state.domainTab='overview';state.renewYears=1;routeTo('domain')})); }
function wireCartButtons(){ document.querySelectorAll('[data-add-cart]').forEach(el=>el.addEventListener('click',()=>{const domain=el.dataset.addCart;if(!state.cart.some(i=>i.domain===domain)) state.cart.push({domain,price:Number(el.dataset.price)}); state.cartOpen=true;render();})); }

function renewDomain(){
  state.operation={status:'processing',title:'Renewal processing',message:'The registrar request is being processed. Do not retry while state is being reconciled.'}; render();
  setTimeout(()=>{ const d=domainById(); d.expiresAt=renewedExpirationDate(d.expiresAt,state.renewYears); d.paymentFailed=false; state.operation={status:'completed',title:'Renewal completed',message:`${d.name} was renewed successfully for ${state.renewYears} year${state.renewYears>1?'s':''}.`}; render(); toast('Domain renewed',d.name); },1300);
}
function completePurchase(){
  const items=[...state.cart]; state.cart=[];
  modalRoot.innerHTML=`<div class="modal-backdrop"><div class="modal"><div class="modal-head"><h2>Registration submitted</h2><p>Payment succeeded. Registrar provisioning is now processing.</p></div><div class="modal-body">${items.map(i=>`<div class="operation-card"><div>${I('globe')}</div><div class="operation-copy"><strong>${i.domain}</strong><span>Registration pending · Provider state will be reconciled automatically.</span></div>${badge('Processing','info')}</div>`).join('')}</div><div class="modal-actions"><button class="btn primary" data-modal-close>Go to domains</button></div></div></div>`;
  modalRoot.querySelector('[data-modal-close]').addEventListener('click',()=>{modalRoot.innerHTML='';routeTo('domains')});
}
function reauthModal(){
  modal('Re-authenticate to reveal auth code','Authorization codes are sensitive credentials and are never shown before re-authentication.',`<div class="field"><label>Account password</label><input type="password" placeholder="Enter your password" autofocus></div>`,'Continue',()=>{
    modalRoot.innerHTML=`<div class="modal-backdrop"><div class="modal"><div class="modal-head"><h2>Authorization Code</h2><p>Copy this code only to the receiving registrar. It is intentionally not stored in normal client state.</p></div><div class="modal-body"><div class="field"><label>Auth code</label><div style="display:flex;gap:8px"><input class="code" value="EPP-7H2K-9Q4M-X8P3" readonly><button class="btn" data-copy-code>Copy</button></div></div></div><div class="modal-actions"><button class="btn primary" data-modal-close>Done</button></div></div></div>`;
    modalRoot.querySelector('[data-copy-code]').addEventListener('click',()=>toast('Copied','Authorization code copied for this prototype session.'));
    modalRoot.querySelector('[data-modal-close]').addEventListener('click',()=>modalRoot.innerHTML='');
  });
}
function nameserverModal(){ const d=domainById(); modal('Change Nameservers',`Updates to ${d.name} are high-impact registrar changes.`,`<div class="field"><label>Nameserver 1</label><input value="${d.nameservers[0]}"></div><div class="field" style="margin-top:12px"><label>Nameserver 2</label><input value="${d.nameservers[1]}"></div>`,'Save nameservers',()=>{state.operation={status:'processing',title:'Nameserver update processing',message:'Registrar state is being verified before the UI marks this completed.'};toast('Nameserver update submitted','No blind retry will be offered during reconciliation.');render();}); }
function contactModal(){ modal('Edit contact','Update routine contact fields for this role.',`<div class="form-grid"><div class="field"><label>Name</label><input value="Ahmed Mohamed"></div><div class="field"><label>Email</label><input value="ahmed@example.com"></div></div>`,'Save changes',()=>toast('Contact updated')); }
function changeRegistrantModal(){ modal('Change Registrant','This is a distinct high-impact registrar operation and may trigger registry verification.',`<div class="alert warning" style="margin-top:0"><div class="alert-icon">${I('warning')}</div><div class="alert-copy"><div class="alert-title">Ownership-sensitive change</div><div class="alert-text">The registry may apply additional restrictions after a registrant change.</div></div></div><div class="field"><label>New registrant name</label><input placeholder="Full legal name"></div>`,'Continue',()=>toast('Registrant change submitted','Registrar processing started.')); }
function dangerModal(){ const d=domainById(); modal('Danger Zone',`Destructive operations for ${d.name}.`,`<div class="danger-zone"><div class="setting-row"><div class="setting-copy"><strong>Transfer domain away</strong><span>Move the domain to another registrar.</span></div><button class="btn danger small">Transfer</button></div><div class="setting-row"><div class="setting-copy"><strong>Disable privacy</strong><span>Registrant data may become publicly visible.</span></div><button class="btn danger small">Disable</button></div><div class="setting-row"><div class="setting-copy"><strong>Delete domain</strong><span>Destructive and potentially irreversible.</span></div><button class="btn danger small">Delete</button></div></div>`,'Close',()=>{}); }
function transferOutModal(){ const d=domainById(); modal('Transfer domain out',`Prepare ${d.name} for transfer to another registrar.`,`<div class="alert warning" style="margin-top:0"><div class="alert-icon">${I('warning')}</div><div class="alert-copy"><div class="alert-title">Transfer Lock must be off</div><div class="alert-text">You will be asked to confirm this transfer and may need the authorization code.</div></div></div>`,'Continue',()=>toast('Transfer preparation started')); }
function showAdvanced(){ state.moreOpen=false; render(); const slot=document.querySelector('#advanced-slot'); if(slot){const d=domainById(); slot.innerHTML=`<div class="section-title"><h2>Advanced Information</h2><p>Technical registrar details</p></div><div class="card"><div class="card-body"><dl style="margin:0">${def('Domain',d.name)}${def('Registration date',new Date(d.registeredAt).toLocaleString())}${def('Expiration date',new Date(d.expiresAt).toLocaleString())}${def('Registrar status','clientTransferProhibited')}${def('IDN / Punycode','—')}${def('Registered nameservers',d.nameservers.join(', '))}${def('Provider operation status','Synchronized')}</dl></div></div>`; slot.scrollIntoView({behavior:'smooth',block:'start'});} }
function openCommand(){
  state.command=true;
  modalRoot.innerHTML=`<div class="modal-backdrop" data-command-close><div class="modal command" data-command-box><div class="command-input">${I('search')}<input id="command-query" placeholder="Search domains or actions…" autofocus><kbd>ESC</kbd></div><div class="command-list" id="command-list">${commandItems('')}</div></div></div>`;
  const input=document.querySelector('#command-query'); input.focus(); input.addEventListener('input',()=>document.querySelector('#command-list').innerHTML=commandItems(input.value));
  modalRoot.querySelector('[data-command-close]').addEventListener('click',e=>{if(!e.target.closest('[data-command-box]'))closeModal()});
  wireCommand();
}
function commandItems(q){ const lower=q.toLowerCase(); const domains=state.domains.filter(d=>d.name.includes(lower)); return `<div class="command-group-label">Domains</div>${domains.slice(0,5).map(d=>`<div class="command-item" data-command-domain="${d.id}">${I('globe')}<strong>${d.name}</strong><span>${d.workspace}</span></div>`).join('')}<div class="command-group-label">Actions</div>${[['Buy a domain','search'],['View transfers','transfers'],['Open billing','billing']].filter(a=>a[0].toLowerCase().includes(lower)).map(([l,r])=>`<div class="command-item" data-command-route="${r}">${I('arrow')}<strong>${l}</strong><span>Navigate</span></div>`).join('')}`; }
function wireCommand(){ document.querySelectorAll('[data-command-domain]').forEach(el=>el.addEventListener('click',()=>{closeModal();state.domainId=el.dataset.commandDomain;state.domainTab='overview';routeTo('domain')}));document.querySelectorAll('[data-command-route]').forEach(el=>el.addEventListener('click',()=>{const r=el.dataset.commandRoute;closeModal();routeTo(r)})); }
function modal(title,subtitle,body,primary='Continue',onPrimary=()=>{}){
  modalRoot.innerHTML=`<div class="modal-backdrop"><div class="modal"><div class="modal-head"><h2>${title}</h2><p>${subtitle}</p></div><div class="modal-body">${body}</div><div class="modal-actions"><button class="btn" data-modal-close>Cancel</button><button class="btn primary" data-modal-primary>${primary}</button></div></div></div>`;
  modalRoot.querySelector('[data-modal-close]').addEventListener('click',closeModal);
  modalRoot.querySelector('[data-modal-primary]').addEventListener('click',()=>{closeModal();onPrimary();});
}
function closeModal(){modalRoot.innerHTML='';state.command=false;}

document.addEventListener('keydown',e=>{
  if((e.metaKey||e.ctrlKey) && e.key.toLowerCase()==='k'){e.preventDefault();openCommand();}
  if(e.key==='Escape') closeModal();
});

render();

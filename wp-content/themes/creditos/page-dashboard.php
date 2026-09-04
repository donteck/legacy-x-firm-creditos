<?php
/**
 * Template Name: CreditOS Dashboard
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( ! is_user_logged_in() ) { auth_redirect(); }
$user = wp_get_current_user();
$display_name = $user->display_name ? $user->display_name : $user->user_login;
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<?php wp_head(); ?>
</head>
<body <?php body_class( 'creditos-dashboard' ); ?>>
<?php wp_body_open(); ?>
<div class="app">
<aside class="sidebar">
  <a class="brand" href="<?php echo esc_url( home_url( '/' ) ); ?>"><span class="brand-mark">C</span><span>CreditOS<small>BY LEGACY X FIRM</small></span></a>
  <div class="nav-list">
    <a class="nav-item active" href="#overview"><span class="nav-icon">⌂</span>Dashboard</a>
    <a class="nav-item" href="#roadmaps"><span class="nav-icon">◎</span>Roadmaps</a>
    <a class="nav-item" href="#health"><span class="nav-icon">◫</span>Credit Reports</a>
    <a class="nav-item" href="#disputes"><span class="nav-icon">▤</span>Disputes</a>
    <a class="nav-item" href="#tasks"><span class="nav-icon">✓</span>Tasks</a>
    <a class="nav-item" href="#documents"><span class="nav-icon">□</span>Documents</a>
    <a class="nav-item" href="#ai"><span class="nav-icon">✦</span>AI Center</a>
    <a class="nav-item" href="#funding"><span class="nav-icon">↗</span>Funding Readiness</a>
    <a class="nav-item" href="#business"><span class="nav-icon">▦</span>Business Credit</a>
    <a class="nav-item" href="#reports"><span class="nav-icon">◩</span>Reports</a>
  </div>
  <div class="sidebar-spacer"></div>
  <div class="sidebar-card"><strong>CreditOS Pro</strong><p>Personal + Business Credit Intelligence, Management &amp; Automation.</p><a class="btn btn-primary" href="#">Manage Plan</a></div>
</aside>
<div class="main">
<header class="topbar">
  <div class="search"><input type="search" placeholder="Search clients, tasks, disputes, documents…" aria-label="Search CreditOS"></div>
  <div class="top-actions"><button class="icon-btn" type="button" aria-label="Help">?</button><button class="notif-btn" type="button" aria-label="Notifications">🔔<span class="notif-dot"></span></button><div class="user"><span class="avatar"><?php echo esc_html( strtoupper( mb_substr( $display_name, 0, 1 ) ) ); ?></span><div><strong><?php echo esc_html( $display_name ); ?></strong><span>CreditOS Account</span></div></div></div>
</header>
<main class="content" id="overview">
  <div class="head-row"><div><h1>Credit Intelligence Dashboard</h1><p>Know what changed, what needs attention, and what to do next.</p><div class="role-switch" aria-label="Dashboard role preview"><button class="active" type="button" data-role="client">Client View</button><button type="button" data-role="staff">Staff View</button></div></div><div class="mode-switch"><button class="active" type="button">Combined</button><button type="button">Personal</button><button type="button">Business</button></div></div>

  <div id="creditos-live-status" class="next-action" style="display:none" aria-live="polite"><div class="next-icon">✓</div><div><strong>CreditOS data connected</strong><span id="creditos-live-summary">Loading your live workspace…</span></div></div>
  <div class="next-action" data-role-only="client"><div class="next-icon">→</div><div><strong>Your next best action: Review utilization priorities</strong><span>CreditOS will replace this guidance with live account-based recommendations as authorized data is connected.</span></div><button class="btn btn-primary" type="button">Review Now</button></div>
  <div class="next-action" data-role-only="staff"><div class="next-icon">12</div><div><strong>Staff attention queue</strong><span>Review client responses, due tasks, onboarding items, and pending approvals from one workspace.</span></div><button class="btn btn-primary" type="button">Open Queue</button></div>

  <div class="attention-strip"><div class="attention"><small>NEEDS ATTENTION</small><strong>2 follow-ups due soon</strong></div><div class="attention"><small>RECENT CHANGE</small><strong>Business roadmap advanced</strong></div><div class="attention"><small>NEXT MILESTONE</small><strong>Funding readiness review</strong></div></div>

  <div class="kpis"><div class="kpi"><small>PERSONAL CREDIT HEALTH</small><strong>752</strong><span>Illustrative preview</span></div><div class="kpi"><small>BUSINESS SCORE</small><strong>68</strong><span>Progressing</span></div><div class="kpi"><small>ACTIVE DISPUTES</small><strong>9</strong><span>Workflow preview</span></div><div class="kpi"><small>TASKS COMPLETED</small><strong>24</strong><span>This cycle</span></div><div class="kpi"><small>FUNDING READINESS</small><strong>82%</strong><span>High</span></div></div>

  <div class="section-label" id="roadmaps"><h3>Your Guided Credit Roadmaps</h3><a href="#">View All Roadmaps →</a></div>
  <div class="roadmap-grid">
    <section class="roadmap-card"><div class="roadmap-head"><div><strong>Personal CreditOS</strong><span>7-step personal credit journey</span></div><span>Step 5 of 7</span></div><div class="steps"><div class="step"><i>1</i><strong>Credit Foundation</strong><span>Complete</span></div><div class="step"><i>2</i><strong>3-Bureau Credit Analysis</strong><span>Complete</span></div><div class="step"><i>3</i><strong>Credit Accuracy &amp; Health Review</strong><span>Complete</span></div><div class="step"><i>4</i><strong>Dispute &amp; Correction</strong><span>Active</span></div><div class="step"><i>5</i><strong>Credit Optimization</strong><span>Next</span></div><div class="step"><i>6</i><strong>Credit Building &amp; Strengthening</strong><span>Locked</span></div><div class="step"><i>7</i><strong>Personal Funding Readiness</strong><span>Locked</span></div></div></section>
    <section class="roadmap-card"><div class="roadmap-head"><div><strong>Business CreditOS</strong><span>7-step business credit journey</span></div><span>Step 5 of 7</span></div><div class="steps"><div class="step"><i>1</i><strong>Business Foundation</strong><span>Complete</span></div><div class="step"><i>2</i><strong>Business Fundability</strong><span>Complete</span></div><div class="step"><i>3</i><strong>Business Credit Bureau Setup</strong><span>Complete</span></div><div class="step"><i>4</i><strong>Vendor Credit / Net Terms</strong><span>Active</span></div><div class="step"><i>5</i><strong>Revolving Business Credit</strong><span>Next</span></div><div class="step"><i>6</i><strong>Business Credit Strengthening</strong><span>Locked</span></div><div class="step"><i>7</i><strong>Business Funding Readiness</strong><span>Locked</span></div></div></section>
  </div>

  <div class="section-label"><h3>Goal Journeys</h3><a href="#">Manage Goals →</a></div>
  <div class="goal-journeys"><div class="goal-journey"><small>HOME READINESS</small><strong>Preparation Plan</strong><div class="bar"><i style="width:64%"></i></div><p>64% complete · priority tasks remain.</p></div><div class="goal-journey"><small>BUSINESS FUNDING</small><strong>Funding Readiness</strong><div class="bar"><i style="width:82%"></i></div><p>82% ready · verify remaining business items next.</p></div><div class="goal-journey"><small>CREDIT HEALTH</small><strong>Optimization Journey</strong><div class="bar"><i style="width:71%"></i></div><p>71% complete · utilization remains a priority factor.</p></div></div>

  <div class="section-label" id="health"><h3>Credit Health &amp; Goals</h3><a href="#">View Full Analysis →</a></div>
  <div class="health-grid"><section class="health-card"><h4>Credit Factor Analysis</h4><div class="factor"><span>Payment History</span><div class="track"><div class="fill" style="width:86%"></div></div><b>Strong</b></div><div class="factor"><span>Utilization</span><div class="track"><div class="fill" style="width:61%"></div></div><b>Watch</b></div><div class="factor"><span>Account Age</span><div class="track"><div class="fill" style="width:74%"></div></div><b>Good</b></div><div class="factor"><span>Inquiries</span><div class="track"><div class="fill" style="width:68%"></div></div><b>Good</b></div><div class="factor"><span>Credit Mix</span><div class="track"><div class="fill" style="width:79%"></div></div><b>Good</b></div></section><section class="health-card"><h4>Financial Goals</h4><div class="goal-list"><div class="goal"><strong>🏠 Home Readiness</strong><p>Track priority actions before the next readiness review.</p></div><div class="goal"><strong>🚗 Auto Financing</strong><p>Understand which factors may need attention before financing.</p></div><div class="goal"><strong>🏢 Business Funding</strong><p>Complete Business CreditOS milestones to improve readiness.</p></div></div></section></div>

  <div class="section-label" id="disputes"><h3>Dispute Command Center</h3><a href="#">Open Full Dispute Center →</a></div>
  <div class="dispute-kpis"><div class="dispute-kpi"><small>ACTIVE ITEMS</small><strong>9</strong></div><div class="dispute-kpi"><small>AWAITING REVIEW</small><strong>3</strong></div><div class="dispute-kpi"><small>FOLLOW-UPS DUE</small><strong>2</strong></div><div class="dispute-kpi"><small>RESOLVED THIS CYCLE</small><strong>4</strong></div></div>
  <div class="table-wrap"><table class="data-table"><thead><tr><th>Item</th><th>Bureau / Furnisher</th><th>Round</th><th>Status</th></tr></thead><tbody><tr><td>Collection Account</td><td>Experian</td><td>Round 1</td><td><span class="status">Review</span></td></tr><tr><td>Late Payment</td><td>Equifax</td><td>Round 2</td><td><span class="status">Sent</span></td></tr><tr><td>Credit Inquiry</td><td>TransUnion</td><td>Response</td><td><span class="status">Response</span></td></tr></tbody></table></div>

  <div class="section-label" id="documents"><h3>Documents &amp; Account</h3><a href="#">Open Secure Vault →</a></div>
  <div class="vault-billing"><section class="vb-card"><h4>Secure Document Vault</h4><div class="vb-row"><span>Credit reports</span><b>Connected to records</b></div><div class="vb-row"><span>Bureau responses</span><b>Protected</b></div><div class="vb-row"><span>Signed agreements</span><b>Protected</b></div><div class="vb-row"><span>Identity / address documents</span><b>Protected</b></div></section><section class="vb-card"><h4>Plan &amp; Billing</h4><div class="vb-row"><span>Provider</span><b>Stripe-ready</b></div><div class="vb-row"><span>Plan</span><b>CreditOS Pro</b></div><div class="vb-row"><span>Status</span><b>Database-backed</b></div><div class="vb-row"><span>Billing</span><b>Integration pending</b></div></section></div>

  <div class="section-label" id="business"><h3>Business Operations</h3><a href="#">Open CRM Workspace →</a></div>
  <div class="ops-grid"><article class="ops-card"><h4>Lead Pipeline</h4><p>Track leads from consultation through onboarding and active service.</p></article><article class="ops-card"><h4>Credit Audits</h4><p>Organize report review, priorities, notes, and client recommendations.</p></article><article class="ops-card"><h4>Agreements</h4><p>Connect authorizations and future e-sign workflows to client records.</p></article><article class="ops-card"><h4>Team &amp; Automation</h4><p>Assign staff work, standardize processes, and prepare recurring automation.</p></article></div>

  <div class="section-label" id="ai"><h3>CreditOS AI &amp; Quick Access</h3><a href="#">Open AI Center →</a></div>
  <div class="quick-grid"><article class="quick"><strong>Ask CreditOS AI</strong><p>Summarize changes, priorities, and next-step options from authorized account data.</p></article><article class="quick"><strong>Create a Task</strong><p>Add a client or staff action and connect it to a roadmap step.</p></article><article class="quick"><strong>Start a Dispute Review</strong><p>Organize inaccurate or unverifiable information for human review.</p></article><article class="quick"><strong>Funding Readiness</strong><p>Review personal and business readiness milestones together.</p></article></div>
</main>
</div>
</div>
<div class="notif-panel" aria-hidden="true"><h4>Notifications</h4><div class="notif-item"><strong>CreditOS connected</strong><p>Live notifications from the database will appear here.</p></div></div>
<nav class="mobile-bottom-nav" aria-label="Mobile CreditOS navigation"><a href="#overview">Home</a><a href="#roadmaps">Roadmap</a><a href="#ai">AI</a><a href="#disputes">Disputes</a><a href="#documents">Account</a></nav>
<?php wp_footer(); ?>
</body>
</html>

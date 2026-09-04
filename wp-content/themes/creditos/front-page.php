<?php
/** CreditOS production marketing / onboarding front page. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<?php wp_head(); ?>
</head>
<body <?php body_class( 'creditos-front' ); ?>>
<?php wp_body_open(); ?>
<a class="skip-link" href="#main-content"><?php esc_html_e( 'Skip to content', 'creditos' ); ?></a>
<div class="topline"></div>
<nav>
  <div class="container nav-inner">
    <a class="brand" href="<?php echo esc_url( home_url( '/' ) ); ?>"><span class="brand-mark">C</span><span>Credit<span class="rainbow-text">OS</span><small>BY LEGACY X FIRM</small></span></a>
    <div class="nav-links"><a href="#features">Product</a><a href="#who">Who It’s For</a><a href="#how">How It Works</a><a href="#roadmaps">7 + 7 Method</a><a href="#resources">Resources</a></div>
    <div class="nav-actions"><a class="btn" href="<?php echo esc_url( is_user_logged_in() ? home_url( '/dashboard/' ) : wp_login_url( home_url( '/dashboard/' ) ) ); ?>">Client Login</a><a class="btn btn-primary" href="#start">Get Started</a></div>
    <button class="mobile-menu-btn" aria-label="Open menu" type="button">☰</button>
  </div>
</nav>
<main id="main-content">
<section class="hero">
  <div class="container hero-grid">
    <div>
      <span class="eyebrow"><i class="spark-dot"></i> AI-POWERED CREDIT OPERATING SOLUTION</span>
      <h1>Credit clarity.<br>Business power.<br><span class="rainbow-text">One premium system.</span></h1>
      <p>CreditOS brings personal credit, business credit, guided roadmaps, dispute organization, AI-powered intelligence, monitoring, CRM workflows, secure documents, and funding readiness into one elegant operating environment.</p>
      <div class="hero-actions"><a class="btn btn-primary" href="#start">Start Your CreditOS Journey →</a><a class="btn" href="#features">Explore the Platform</a></div>
      <div class="hero-meta"><span>Personal + Business Credit</span><span>7 + 7 Guided Roadmaps</span><span>AI-Powered Insights</span><span>Secure Client Portal</span></div>
    </div>
    <div class="visual-wrap">
      <div class="device">
        <div class="device-top"><strong>Credit Intelligence Overview</strong><span class="window-dots"><i></i><i></i><i></i></span></div>
        <div class="device-grid">
          <div class="glass-card score-panel"><div class="score-title"><span>Personal Credit Health</span><span class="positive">Improving</span></div><div class="score-value">752</div><div class="score-scale">Illustrative dashboard preview</div><div class="mini-chart"></div></div>
          <div class="side-stack"><div class="glass-card stat"><small>Business Score</small><strong>68</strong><span class="positive">▲ Progress</span></div><div class="glass-card stat"><small>Funding Readiness</small><strong>82%</strong><span class="positive">High</span></div><div class="glass-card stat"><small>Active Disputes</small><strong>9</strong><small>Workflow preview</small></div></div>
        </div>
      </div>
      <div class="float-card"><small>BUSINESS ROADMAP</small><strong>Step 5 of 7</strong><span>72% complete</span><div class="progress"><i></i></div></div>
    </div>
  </div>
</section>
<section class="trust"><div class="container"><p>BUILT FOR MODERN CREDIT WORKFLOWS ACROSS LEADING CONSUMER AND BUSINESS CREDIT ECOSYSTEMS</p><div class="logos"><span>Experian</span><span>Equifax</span><span>TransUnion</span><span>Dun &amp; Bradstreet</span><span>Stripe Ready</span></div></div></section>

<section class="section" id="who"><div class="container"><div class="section-head"><h2>One platform. <span class="rainbow-text">Different paths to growth.</span></h2><p>CreditOS serves consumers, credit professionals, and adjacent financial-service businesses that need a clearer way to manage credit improvement and funding readiness.</p></div><div class="audience-grid">
<article class="audience-card"><div class="aud-icon">👤</div><h3>Improve Your Own Credit</h3><p>Understand reports, identify priorities, follow a guided plan, organize appropriate disputes, and strengthen your profile step by step.</p><a href="#start">Explore Personal CreditOS →</a></article>
<article class="audience-card"><div class="aud-icon">🏢</div><h3>Run a Credit Business</h3><p>Manage leads, onboarding, client workflows, agreements, tasks, disputes, payments, documents, and team activity from one workspace.</p><a href="#start">Explore CreditOS Pro →</a></article>
<article class="audience-card"><div class="aud-icon">📈</div><h3>Scale an Existing Operation</h3><p>Use automation, reporting, team assignments, AI guidance, pipelines, and standardized workflows to reduce operational friction.</p><a href="#start">Explore Agency Tools →</a></article>
<article class="audience-card"><div class="aud-icon">🤝</div><h3>Mortgage, Real Estate &amp; Tax Pros</h3><p>Add a structured credit-readiness workflow to help clients become better prepared for financing and major transactions.</p><a href="#start">Explore Partner Solutions →</a></article>
</div></div></section>

<section class="section" id="features"><div class="container"><div class="section-head"><h2>A premium operating environment for <span class="rainbow-text">credit transformation.</span></h2><p>Every module is designed to give clients and specialists greater clarity, visibility, consistency, and control across the full credit journey.</p></div><div class="features">
<article class="feature"><div class="icon">✨</div><h3>CreditOS AI Intelligence</h3><p>Surface patterns, summarize credit data, prioritize next actions, and support personal and business workflows with human review.</p></article>
<article class="feature"><div class="icon">◎</div><h3>7 + 7 Guided Roadmaps</h3><p>Move from foundation to funding readiness with progress-based personal and business credit operating paths.</p></article>
<article class="feature"><div class="icon">▤</div><h3>Dispute Command Center</h3><p>Organize items, documentation, status changes, correspondence, follow-up tasks, evidence, and case history in one place.</p></article>
<article class="feature"><div class="icon">↗</div><h3>Funding Readiness Engine</h3><p>See what is strengthening or weakening readiness and which actions should come next before pursuing funding.</p></article>
<article class="feature"><div class="icon">▣</div><h3>CRM + Automation</h3><p>Manage leads, consultations, onboarding, client stages, tasks, communications, documents, and recurring workflows.</p></article>
<article class="feature"><div class="icon">✓</div><h3>Secure Client Experience</h3><p>Deliver a polished portal with role-based access, private documents, structured data, auditability, and roadmap visibility.</p></article>
</div></div></section>

<section class="section" id="how"><div class="container"><div class="section-head"><h2>From first lead to stronger credit. <span class="rainbow-text">All connected.</span></h2><p>CreditOS connects customer acquisition, onboarding, credit operations, and business management so the client journey does not break between tools.</p></div><div class="flow">
<article class="flow-card"><span class="num">01 · ATTRACT</span><h3>Capture &amp; Qualify Leads</h3><p>Lead forms, referrals, consultations, and CRM stages organize prospects in one pipeline.</p></article>
<article class="flow-card"><span class="num">02 · CONVERT</span><h3>Audit, Explain &amp; Onboard</h3><p>Structure intake, goals, authorizations, agreements, documents, and the first client roadmap.</p></article>
<article class="flow-card"><span class="num">03 · IMPROVE</span><h3>Analyze &amp; Execute</h3><p>Credit analysis, appropriate dispute workflows, optimization, education, and positive-building actions work together.</p></article>
<article class="flow-card"><span class="num">04 · OPERATE</span><h3>Automate &amp; Scale</h3><p>Tasks, notifications, staff queues, reporting, billing, and integrations reduce operational friction.</p></article>
</div></div></section>

<section class="section" id="credit-intelligence-suite"><div class="container"><div class="section-head"><h2>Credit repair is only the beginning. <span class="rainbow-text">CreditOS manages the whole journey.</span></h2><p>CreditOS combines the strongest operational patterns of modern credit services with broader personal credit, business credit, AI, automation, and funding-readiness architecture.</p></div><div class="journey-tabs"><span>ANALYZE</span><span>REPAIR</span><span>BUILD</span><span>PROTECT</span><span>OPTIMIZE</span><span>FUND</span></div><div class="market-suite">
<article class="market-card"><small>3-BUREAU INTELLIGENCE</small><h3>Reports, Scores &amp; Monitoring</h3><p>Organize three-bureau information, score movement, account changes, inquiries, utilization, and progress inside one workspace.</p></article>
<article class="market-card"><small>PERSONALIZED GUIDANCE</small><h3>Credit Action Plan</h3><p>Turn report findings into prioritized actions with explanations, specialist notes, AI guidance, and the Personal CreditOS roadmap.</p></article>
<article class="market-card"><small>CREDITOR OPERATIONS</small><h3>Interventions &amp; Letter Center</h3><p>Manage appropriate bureau disputes, creditor interventions, validation requests, goodwill correspondence, and case history.</p></article>
<article class="market-card"><small>BUILD</small><h3>Positive Credit Strategy</h3><p>Track utilization, payment habits, account mix, credit-building opportunities, reminders, and positive reporting strategies.</p></article>
<article class="market-card"><small>PROTECT</small><h3>Identity &amp; Fraud Watch</h3><p>Centralize fraud alerts, identity-protection education, monitoring notifications, and future authorized protection integrations.</p></article>
<article class="market-card"><small>MONEY MANAGEMENT</small><h3>Debt &amp; Budget Workspace</h3><p>Help clients understand balances, utilization, payoff priorities, obligations, and their relationship to credit health.</p></article>
<article class="market-card"><small>BUSINESS CREDIT</small><h3>Business CreditOS</h3><p>Build business foundation, fundability, bureau setup, vendor accounts, revolving credit, strengthening, and funding readiness.</p></article>
<article class="market-card"><small>AI + HUMAN</small><h3>CreditOS Intelligence</h3><p>Surface risks, priorities, letter suggestions, next actions, and progress explanations while preserving human review.</p></article>
</div></div></section>

<section class="section" id="roadmaps"><div class="container"><div class="section-head"><h2>The CreditOS <span class="rainbow-text">7 + 7 Method.</span></h2><p>Two connected roadmaps give personal and business credit equal importance.</p></div><div class="roadmaps">
<div class="roadmap"><h3>Personal CreditOS</h3><ol><li>Credit Foundation</li><li>3-Bureau Credit Analysis</li><li>Credit Accuracy &amp; Health Review</li><li>Dispute &amp; Correction</li><li>Credit Optimization</li><li>Credit Building &amp; Strengthening</li><li>Personal Funding Readiness</li></ol></div>
<div class="roadmap"><h3>Business CreditOS</h3><ol><li>Business Foundation</li><li>Business Fundability</li><li>Business Credit Bureau Setup</li><li>Vendor Credit / Net Terms</li><li>Revolving Business Credit</li><li>Business Credit Strengthening</li><li>Business Funding Readiness</li></ol></div>
</div></div></section>

<section class="section"><div class="container"><div class="section-head"><h2>Designed around the outcomes <span class="rainbow-text">clients actually care about.</span></h2><p>CreditOS connects credit improvement to understandable financial goals without promising a particular score increase or approval outcome.</p></div><div class="outcome-grid"><article class="outcome-card"><h3>Home Readiness</h3><p>Track utilization, payment history, derogatory items, debt priorities, and preparation tasks relevant to a future mortgage conversation.</p></article><article class="outcome-card"><h3>Auto &amp; Personal Financing</h3><p>See which credit factors need attention before seeking financing and understand readiness more clearly.</p></article><article class="outcome-card"><h3>Business Funding Readiness</h3><p>Combine personal and business signals, business fundability, reporting tradelines, and documentation readiness in one view.</p></article></div></div></section>

<section class="section" id="resources"><div class="container"><div class="section-head"><h2>Learn, build, and operate with the <span class="rainbow-text">CreditOS Resource Center.</span></h2><p>CreditOS can become the learning hub around the platform, not just the software itself.</p></div><div class="resource-grid"><article class="resource-card"><small>ACADEMY</small><h3>CreditOS Learning Center</h3><p>Guided lessons for personal credit, business credit, dispute fundamentals, funding readiness, and client operations.</p><a href="#">Browse Learning →</a></article><article class="resource-card"><small>TOOLS</small><h3>Templates &amp; Downloads</h3><p>Checklists, onboarding resources, worksheets, consultation tools, and client education assets.</p><a href="#">Browse Resources →</a></article><article class="resource-card"><small>INSIGHTS</small><h3>Articles, Video &amp; Updates</h3><p>Educational content, platform updates, case studies, implementation guides, and credit-business strategy.</p><a href="#">Explore Insights →</a></article></div></div></section>

<section class="section" id="start"><div class="container"><div class="cta-box"><span class="eyebrow"><i class="spark-dot"></i> START WITH CLARITY</span><h2>Build your next credit chapter with <span class="rainbow-text">CreditOS.</span></h2><p>Choose Personal Credit, Business Credit, or both. CreditOS will organize the journey around your goals.</p><a class="btn btn-primary" href="#start">Get Started with CreditOS →</a></div></div></section>
<section class="section"><div class="container"><div class="brand-statement"><strong>Legacy X Firm Credit Operating Solutions</strong> · Personal &amp; Business Credit Intelligence, Management &amp; Automation</div></div></section>
</main>
<footer><div class="container"><div class="footer-grid"><div><a class="brand" href="<?php echo esc_url( home_url( '/' ) ); ?>"><span class="brand-mark">C</span><span>Credit<span class="rainbow-text">OS</span><small>BY LEGACY X FIRM</small></span></a><p>The Operating Solution for Personal &amp; Business Credit.</p></div><div><h5>Solutions</h5><a href="#roadmaps">Personal CreditOS</a><br><a href="#roadmaps">Business CreditOS</a><br><a href="#features">Funding Readiness</a></div><div><h5>Platform</h5><a href="#features">CreditOS AI</a><br><a href="#features">Dispute Center</a><br><a href="#features">CRM</a></div><div><h5>Company</h5><a href="#">About Legacy X Firm</a><br><a href="#">Pricing</a><br><a href="#">Contact</a></div><div><h5>Legal</h5><a href="#">Privacy</a><br><a href="#">Terms</a><br><a href="#">Security</a></div></div><div class="copy">© <?php echo esc_html( gmdate( 'Y' ) ); ?> Legacy X Firm Credit Operating Solutions.</div></div></footer>

<div class="onboard-modal" id="creditosOnboarding" aria-hidden="true"><div class="onboard-panel" role="dialog" aria-modal="true" aria-labelledby="onboardTitle"><button class="onboard-close" aria-label="Close onboarding" type="button">×</button><span class="eyebrow">CREDITOS SECURE ONBOARDING</span><div class="onboard-progress"><i class="active"></i><i></i><i></i><i></i></div>
<section class="onboard-step active" data-step="0"><h3 id="onboardTitle">Choose your CreditOS journey.</h3><p>Your dashboard and roadmap will adapt to the type of credit journey you select.</p><div class="choice-grid"><button class="choice" data-journey="Personal" type="button"><div>👤</div><strong>Personal Credit</strong><span>Analyze, improve, build, optimize, and prepare your personal credit profile.</span></button><button class="choice" data-journey="Business" type="button"><div>🏢</div><strong>Business Credit</strong><span>Build business fundability, bureau presence, vendor credit, and funding readiness.</span></button><button class="choice" data-journey="Combined" type="button"><div>✨</div><strong>Personal + Business</strong><span>Coordinate both profiles in one combined CreditOS command center.</span></button></div></section>
<section class="onboard-step" data-step="1"><h3>What are you working toward?</h3><p>Select one or more goals. CreditOS will use these to prioritize your roadmap.</p><div class="goal-options"><label class="goal-option"><input type="checkbox" value="Improve credit health"><span><strong>Improve credit health</strong><br>Strengthen the fundamentals of your profile.</span></label><label class="goal-option"><input type="checkbox" value="Prepare for a mortgage"><span><strong>Prepare for a mortgage</strong><br>Track readiness factors before speaking with a lender.</span></label><label class="goal-option"><input type="checkbox" value="Prepare for auto financing"><span><strong>Prepare for auto financing</strong><br>Focus on the factors that may affect financing readiness.</span></label><label class="goal-option"><input type="checkbox" value="Build business credit"><span><strong>Build business credit</strong><br>Follow the Business CreditOS 7-step roadmap.</span></label><label class="goal-option"><input type="checkbox" value="Become funding ready"><span><strong>Become funding ready</strong><br>Organize personal and business readiness milestones.</span></label><label class="goal-option"><input type="checkbox" value="Resolve inaccurate information"><span><strong>Resolve inaccurate information</strong><br>Use the Dispute Command Center when appropriate.</span></label></div></section>
<section class="onboard-step" data-step="2"><h3>Secure setup &amp; authorization.</h3><p>CreditOS records authorization and consent before saving an authenticated user’s onboarding profile.</p><div class="consent-box"><label><input id="consentCheck" type="checkbox"> I authorize CreditOS to store the information I choose to submit for my account and understand that consequential actions should be reviewed before submission.</label></div><div class="consent-box">CreditOS uses WordPress authentication, role-based permissions, audit logs, and structured application records. Sensitive bureau integrations require authorized providers and are not enabled by this onboarding form.</div></section>
<section class="onboard-step" data-step="3"><h3>Your CreditOS workspace is ready.</h3><p>Here is the profile used to personalize your first dashboard experience.</p><div class="onboard-summary"><div class="summary-row"><span>Journey</span><strong id="summaryJourney">—</strong></div><div class="summary-row"><span>Goals selected</span><strong id="summaryGoals">0</strong></div><div class="summary-row"><span>First action</span><strong>Complete your Credit Health Audit</strong></div></div><a class="btn btn-primary" style="width:100%" href="<?php echo esc_url( home_url( '/dashboard/' ) ); ?>">Enter Your CreditOS Dashboard →</a></section>
<div class="onboard-actions"><button class="btn" id="onboardBack" type="button">Back</button><button class="btn btn-primary" id="onboardNext" type="button">Continue →</button></div></div></div>
<?php wp_footer(); ?>
</body>
</html>

<?php
/**
 * Template Name: CreditOS Credit Reports
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
<body <?php body_class( 'creditos-reports-page' ); ?>>
<?php wp_body_open(); ?>
<div class="reports-shell">
  <aside class="reports-sidebar">
    <a class="brand" href="<?php echo esc_url( home_url( '/' ) ); ?>"><span class="brand-mark">C</span><span>CreditOS<small>BY LEGACY X FIRM</small></span></a>
    <nav class="reports-nav" aria-label="CreditOS report navigation">
      <a href="<?php echo esc_url( home_url( '/dashboard/' ) ); ?>">← Dashboard</a>
      <a class="active" href="#import">Import Reports</a>
      <a href="#history">Report History</a>
      <a href="#review">Import Review</a>
      <a href="#data">Normalized Data</a>
    </nav>
    <div class="reports-side-note"><strong>Phase 1</strong><p>Secure report ingestion, history, normalized records, and review workflow.</p></div>
  </aside>
  <div class="reports-main">
    <header class="reports-topbar"><div><strong>Credit Report Import Center</strong><span>Phase 1 · Personal CreditOS</span></div><div class="reports-user"><span><?php echo esc_html( strtoupper( substr( $display_name, 0, 1 ) ) ); ?></span><b><?php echo esc_html( $display_name ); ?></b></div></header>
    <main class="reports-content">
      <section class="reports-hero" id="import">
        <div><span class="phase-pill">PHASE 1 · LIVE FOUNDATION</span><h1>Bring your credit report into <span>CreditOS.</span></h1><p>Securely import a report for organization and review. PDF and CSV files are stored for structured review; CreditOS-formatted JSON can be normalized immediately into tradelines, collections, inquiries, and personal information.</p></div>
        <div class="security-card"><strong>Designed for secure ingestion</strong><ul><li>Authenticated client access</li><li>Consent required before import</li><li>25 MB upload limit</li><li>Private report classification</li><li>Import audit trail</li></ul></div>
      </section>

      <section class="import-grid">
        <article class="import-card">
          <div class="card-head"><div><small>01</small><h2>Import a Credit Report</h2></div><span class="status-chip">Secure upload</span></div>
          <form id="creditos-report-form">
            <label>Credit bureau / source<select name="bureau"><option value="multi">3-Bureau / Combined Report</option><option value="experian">Experian</option><option value="equifax">Equifax</option><option value="transunion">TransUnion</option></select></label>
            <label>Report date<input type="date" name="report_date"></label>
            <label class="file-drop" for="creditos-report-file"><strong>Choose your report</strong><span>PDF, JSON, or CSV · maximum 25 MB</span><input id="creditos-report-file" name="report" type="file" accept=".pdf,.json,.csv,application/pdf,application/json,text/csv" required></label>
            <div id="creditos-file-name" class="file-name">No file selected</div>
            <button class="reports-btn primary" type="submit">Import Report →</button>
          </form>
          <div id="creditos-import-message" class="import-message" aria-live="polite"></div>
        </article>
        <article class="import-card guidance-card">
          <div class="card-head"><div><small>02</small><h2>What happens next</h2></div></div>
          <div class="guidance-step"><i>1</i><div><strong>Secure ingestion</strong><p>Your file is registered to your CreditOS client profile and logged.</p></div></div>
          <div class="guidance-step"><i>2</i><div><strong>Normalization</strong><p>Structured data is transformed into CreditOS report records. PDF/CSV parsing will use approved parser adapters as we expand this phase.</p></div></div>
          <div class="guidance-step"><i>3</i><div><strong>Review</strong><p>You or an authorized specialist review imported accounts before Phase 2 analysis begins.</p></div></div>
          <div class="guidance-step"><i>4</i><div><strong>3-bureau intelligence</strong><p>Phase 2 will compare bureau records and generate review flags—not automatic disputes.</p></div></div>
        </article>
      </section>

      <section class="reports-section" id="history"><div class="section-head"><div><small>REPORT HISTORY</small><h2>Your imported reports</h2></div><button id="creditos-refresh-reports" class="reports-btn" type="button">Refresh</button></div><div id="creditos-report-list" class="report-list"><div class="empty-state">Loading report history…</div></div></section>

      <section class="reports-section" id="review"><div class="section-head"><div><small>IMPORT REVIEW</small><h2>Selected report</h2></div></div><div id="creditos-report-review" class="review-panel"><div class="empty-state">Select a report from your history to review imported records.</div></div></section>

      <section class="reports-section" id="data"><div class="section-head"><div><small>NORMALIZED CREDIT DATA</small><h2>Phase 1 data domains</h2></div></div><div class="domain-grid"><article><strong>Tradelines</strong><p>Creditor, masked account, type, balance, limit, dates, status, bureau, remarks.</p></article><article><strong>Collections</strong><p>Collector, original creditor, balance, status, assigned date, bureau.</p></article><article><strong>Inquiries</strong><p>Creditor, inquiry date, inquiry type, bureau.</p></article><article><strong>Personal Information</strong><p>Reported names, addresses, employers, and other report identity variants.</p></article></div></section>
    </main>
    <footer class="reports-footer"><div><strong>CreditOS</strong><span>Legacy X Firm Credit Operating Solutions</span></div><nav><a href="<?php echo esc_url( home_url( '/dashboard/' ) ); ?>">Dashboard</a><a href="#import">Import</a><a href="#history">History</a></nav><small>© <?php echo esc_html( gmdate( 'Y' ) ); ?> Legacy X Firm.</small></footer>
  </div>
</div>
<?php wp_footer(); ?>
</body>
</html>

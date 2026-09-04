<?php
/**
 * Template Name: CreditOS Client Login
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$dashboard_url = home_url( '/dashboard/' );
$redirect_to   = isset( $_REQUEST['redirect_to'] ) ? wp_validate_redirect( esc_url_raw( wp_unslash( $_REQUEST['redirect_to'] ) ), $dashboard_url ) : $dashboard_url;

if ( is_user_logged_in() ) {
    wp_safe_redirect( $redirect_to );
    exit;
}

$error_message = '';
if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['creditos_login_action'] ) ) {
    $nonce = isset( $_POST['creditos_login_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['creditos_login_nonce'] ) ) : '';
    if ( ! wp_verify_nonce( $nonce, 'creditos_client_login' ) ) {
        $error_message = __( 'Your secure session expired. Please try again.', 'creditos' );
    } else {
        $credentials = array(
            'user_login'    => isset( $_POST['log'] ) ? sanitize_text_field( wp_unslash( $_POST['log'] ) ) : '',
            'user_password' => isset( $_POST['pwd'] ) ? (string) wp_unslash( $_POST['pwd'] ) : '',
            'remember'      => ! empty( $_POST['rememberme'] ),
        );
        $user = wp_signon( $credentials, is_ssl() );
        if ( is_wp_error( $user ) ) {
            $error_message = __( 'We could not sign you in with those credentials. Please check your information and try again.', 'creditos' );
        } else {
            wp_safe_redirect( $redirect_to );
            exit;
        }
    }
}
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<?php wp_head(); ?>
</head>
<body <?php body_class( 'creditos-login-page' ); ?>>
<?php wp_body_open(); ?>
<main class="login-shell">
  <section class="login-story" aria-label="CreditOS client portal introduction">
    <a class="login-brand" href="<?php echo esc_url( home_url( '/' ) ); ?>"><span class="login-brand-mark">C</span><span>Credit<strong>OS</strong><small>BY LEGACY X FIRM</small></span></a>
    <div class="login-story-copy">
      <span class="login-kicker">SECURE CLIENT PORTAL</span>
      <h1>Your credit journey, organized in one intelligent workspace.</h1>
      <p>Access your personal and business credit roadmaps, reports, disputes, tasks, documents, funding readiness, and CreditOS intelligence from one premium dashboard.</p>
      <div class="login-benefits">
        <div><span>✓</span><p><strong>One secure workspace</strong><small>Credit reports, tasks, documents, and progress stay connected.</small></p></div>
        <div><span>✓</span><p><strong>Personal + Business CreditOS</strong><small>Move through both sides of your 7 + 7 credit roadmap.</small></p></div>
        <div><span>✓</span><p><strong>Clear next actions</strong><small>See what matters now and what should happen next.</small></p></div>
      </div>
    </div>
    <div class="login-trust"><span class="trust-dot"></span><strong>Protected CreditOS Access</strong><small>Your sign-in is handled by WordPress authentication behind the CreditOS client experience.</small></div>
  </section>

  <section class="login-panel-wrap">
    <div class="login-panel">
      <div class="login-panel-head">
        <span class="login-mobile-brand"><i>C</i> CreditOS</span>
        <span class="login-badge">CLIENT ACCESS</span>
        <h2>Welcome back.</h2>
        <p>Sign in to continue to your CreditOS dashboard.</p>
      </div>

      <?php if ( $error_message ) : ?>
        <div class="login-alert" role="alert"><?php echo esc_html( $error_message ); ?></div>
      <?php endif; ?>

      <form class="creditos-login-form" method="post" action="<?php echo esc_url( get_permalink() ); ?>">
        <?php wp_nonce_field( 'creditos_client_login', 'creditos_login_nonce' ); ?>
        <input type="hidden" name="creditos_login_action" value="1">
        <input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redirect_to ); ?>">

        <label for="creditos-login-user">Email or username</label>
        <div class="login-field"><span aria-hidden="true">@</span><input id="creditos-login-user" name="log" type="text" autocomplete="username" required autofocus placeholder="you@example.com" value="<?php echo isset( $_POST['log'] ) ? esc_attr( sanitize_text_field( wp_unslash( $_POST['log'] ) ) ) : ''; ?>"></div>

        <div class="login-label-row"><label for="creditos-login-password">Password</label><a href="<?php echo esc_url( wp_lostpassword_url( get_permalink() ) ); ?>">Forgot password?</a></div>
        <div class="login-field"><span aria-hidden="true">⌁</span><input id="creditos-login-password" name="pwd" type="password" autocomplete="current-password" required placeholder="Enter your password"><button class="password-toggle" type="button" aria-label="Show password" data-password-toggle>Show</button></div>

        <label class="remember-row"><input type="checkbox" name="rememberme" value="forever"><span>Keep me signed in on this device</span></label>

        <button class="login-submit" type="submit">Sign in to CreditOS <span>→</span></button>
      </form>

      <div class="login-help"><span>Need help accessing your account?</span><a href="<?php echo esc_url( home_url( '/#start' ) ); ?>">Contact Legacy X Firm</a></div>
      <div class="login-security-note"><span>🔒</span><p><strong>Security reminder</strong><small>Never share your password or one-time security codes with anyone.</small></p></div>
    </div>
    <footer class="login-footer"><a href="<?php echo esc_url( home_url( '/' ) ); ?>">← Back to CreditOS</a><span>© <?php echo esc_html( gmdate( 'Y' ) ); ?> Legacy X Firm Credit Operating Solutions</span></footer>
  </section>
</main>
<script>
(()=>{const b=document.querySelector('[data-password-toggle]'),i=document.getElementById('creditos-login-password');if(!b||!i)return;b.addEventListener('click',()=>{const show=i.type==='password';i.type=show?'text':'password';b.textContent=show?'Hide':'Show';b.setAttribute('aria-label',show?'Hide password':'Show password');});})();
</script>
<?php wp_footer(); ?>
</body>
</html>

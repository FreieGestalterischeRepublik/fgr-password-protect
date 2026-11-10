<?php
/**
 * Schutzlogik: fängt Frontend-Aufrufe ab, prüft Zugangs-Cookie, IP-Freigabe und Passwort.
 */

defined( 'ABSPATH' ) || exit;

class FGR_PWP_Protector {

    /** Fehlversuche pro IP, bevor die Eingabe für LOCK_MINUTES gesperrt wird */
    const MAX_ATTEMPTS = 10;
    const LOCK_MINUTES = 15;

    /** Gültigkeit des Zugangs ohne "Angemeldet bleiben" (Browser-Sitzung) */
    const SESSION_SECONDS = DAY_IN_SECONDS;

    public static function init(): void {
        add_action( 'template_redirect', [ __CLASS__, 'intercept' ], 0 );
        add_filter( 'rest_authentication_errors', [ __CLASS__, 'protect_rest' ], 99 );
        add_action( 'admin_bar_menu', [ __CLASS__, 'admin_bar' ], 100 );
    }

    public static function defaults(): array {
        return [
            'active'          => false,
            'password_hash'   => '',
            'allow_logged_in' => true,
            'remember_days'   => 14,
            'ip_whitelist'    => '',
            'protect_rest'    => false,
            'design'          => 'fgr',
            'headline'        => 'Geschützter Bereich',
            'text'            => 'Diese Webseite ist passwortgeschützt. Bitte geben Sie das Passwort ein, um fortzufahren.',
            'button_label'    => 'Entsperren',
            'logo_id'         => 0,
            'logo_width'      => 220,
            'bg_color'        => '#ffffff',
            'bg_image_id'     => 0,
            'text_color'      => '#1d2327',
            'button_bg'       => '#1d2327',
            'button_text'     => '#ffffff',
            'font_family'     => 'system-ui,-apple-system,BlinkMacSystemFont,sans-serif',
            'show_fgr_badge'  => true,
        ];
    }

    public static function options(): array {
        $opts = get_option( FGR_PWP_OPTION, [] );
        return array_merge( self::defaults(), is_array( $opts ) ? $opts : [] );
    }

    /** Ist der Schutz tatsächlich wirksam (aktiv UND Passwort gesetzt)? */
    public static function is_enforced( ?array $opts = null ): bool {
        $opts = $opts ?? self::options();
        return ! empty( $opts['active'] ) && '' !== (string) $opts['password_hash'];
    }

    // ── Zugangsprüfung ───────────────────────────────────────────────────────

    /** Darf der aktuelle Besucher ohne Passworteingabe auf die Seite? */
    public static function has_access( array $opts ): bool {
        if ( is_user_logged_in() ) {
            if ( current_user_can( 'manage_options' ) || ! empty( $opts['allow_logged_in'] ) ) {
                return true;
            }
        }

        $ip = self::client_ip();
        if ( '' !== $ip && in_array( $ip, self::ip_list( $opts ), true ) ) {
            return true;
        }

        return self::cookie_is_valid( $opts );
    }

    public static function ip_list( array $opts ): array {
        return array_values( array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', (string) $opts['ip_whitelist'] ) ) ) );
    }

    /**
     * Cookie-Wert "ablaufzeit|hmac". Der Passwort-Hash fließt in die Signatur ein:
     * Wird das Passwort geändert, verlieren alle bisherigen Zugänge sofort ihre Gültigkeit.
     */
    private static function sign( int $expires, string $password_hash ): string {
        return hash_hmac( 'sha256', $expires . '|' . $password_hash, wp_salt( 'auth' ) );
    }

    private static function cookie_is_valid( array $opts ): bool {
        if ( empty( $_COOKIE[ FGR_PWP_COOKIE ] ) ) {
            return false;
        }
        $raw   = sanitize_text_field( wp_unslash( $_COOKIE[ FGR_PWP_COOKIE ] ) );
        $parts = explode( '|', $raw, 2 );
        if ( 2 !== count( $parts ) || ! ctype_digit( $parts[0] ) ) {
            return false;
        }
        $expires = (int) $parts[0];
        if ( $expires < time() ) {
            return false;
        }
        return hash_equals( self::sign( $expires, (string) $opts['password_hash'] ), $parts[1] );
    }

    private static function set_cookie( array $opts, bool $remember ): void {
        $days = absint( $opts['remember_days'] );
        if ( $remember && $days > 0 ) {
            $expires        = time() + $days * DAY_IN_SECONDS;
            $cookie_expires = $expires;
        } else {
            $expires        = time() + self::SESSION_SECONDS;
            $cookie_expires = 0; // Sitzungs-Cookie: endet mit dem Schließen des Browsers
        }
        $value = $expires . '|' . self::sign( $expires, (string) $opts['password_hash'] );

        setcookie( FGR_PWP_COOKIE, $value, [
            'expires'  => $cookie_expires,
            'path'     => COOKIEPATH ?: '/',
            'domain'   => COOKIE_DOMAIN ?: '',
            'secure'   => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax',
        ] );
        $_COOKIE[ FGR_PWP_COOKIE ] = $value;
    }

    // ── Frontend-Intercept ───────────────────────────────────────────────────

    public static function intercept(): void {
        if ( defined( 'WP_CLI' ) && WP_CLI ) { return; }
        if ( defined( 'DOING_CRON' ) && DOING_CRON ) { return; }
        if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) { return; }

        $opts = self::options();

        // Vorschau für Administratoren (auch wenn der Schutz aus ist)
        if ( isset( $_GET['fgr_pwp_preview'] ) && current_user_can( 'manage_options' ) ) {
            $error = isset( $_GET['fgr_pwp_error'] ) ? 'Das Passwort ist leider nicht korrekt.' : '';
            FGR_PWP_Page::render( $opts, $error, true );
            exit;
        }

        if ( ! self::is_enforced( $opts ) ) { return; }
        if ( self::is_login_request() ) { return; }
        if ( self::has_access( $opts ) ) { return; }

        $error = '';
        if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) && isset( $_POST['fgr_pwp_password'] ) ) {
            $error = self::handle_submit( $opts );
        }

        FGR_PWP_Page::render( $opts, $error, false );
        exit;
    }

    /** Verarbeitet das Formular. Leitet bei Erfolg weiter, gibt sonst die Fehlermeldung zurück. */
    private static function handle_submit( array $opts ): string {
        $ip       = self::client_ip();
        $lock_key = 'fgr_pwp_fail_' . md5( $ip );
        $fails    = (int) get_transient( $lock_key );

        if ( $fails >= self::MAX_ATTEMPTS ) {
            return 'Zu viele Fehlversuche. Bitte versuchen Sie es in ' . self::LOCK_MINUTES . ' Minuten erneut.';
        }

        $password = (string) wp_unslash( $_POST['fgr_pwp_password'] ); // phpcs:ignore -- wird nur gehasht verglichen
        if ( '' !== $password && wp_check_password( $password, (string) $opts['password_hash'] ) ) {
            delete_transient( $lock_key );
            self::set_cookie( $opts, ! empty( $_POST['fgr_pwp_remember'] ) );
            nocache_headers();
            wp_safe_redirect( self::current_url() );
            exit;
        }

        set_transient( $lock_key, $fails + 1, self::LOCK_MINUTES * MINUTE_IN_SECONDS );
        return 'Das Passwort ist leider nicht korrekt.';
    }

    /** wp-login.php und die Login-Adresse von FGR Hide Login immer erreichbar lassen */
    private static function is_login_request(): bool {
        $path = self::request_path();
        if ( basename( $path ) === 'wp-login.php' ) {
            return true;
        }
        $hide_slug = trim( (string) get_option( 'fgr_hide_login_slug', '' ), '/' );
        if ( '' !== $hide_slug ) {
            $home_path  = (string) parse_url( home_url( '/' ), PHP_URL_PATH );
            $login_path = untrailingslashit( $home_path ) . '/' . $hide_slug;
            if ( untrailingslashit( $path ) === untrailingslashit( $login_path ) ) {
                return true;
            }
        }
        return false;
    }

    private static function request_path(): string {
        $uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/'; // phpcs:ignore
        return (string) parse_url( $uri, PHP_URL_PATH );
    }

    /** Aktuelle URL zum Zurückleiten nach erfolgreicher Eingabe (wp_safe_redirect prüft den Host) */
    private static function current_url(): string {
        $host = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
        $uri  = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
        if ( '' === $host ) {
            return home_url( '/' );
        }
        return ( is_ssl() ? 'https://' : 'http://' ) . $host . $uri;
    }

    // ── REST-API ─────────────────────────────────────────────────────────────

    public static function protect_rest( $result ) {
        if ( null !== $result && true !== $result ) {
            return $result; // anderer Fehler bereits gesetzt
        }
        $opts = self::options();
        if ( empty( $opts['protect_rest'] ) || ! self::is_enforced( $opts ) ) {
            return $result;
        }
        if ( self::has_access( $opts ) ) {
            return $result;
        }
        // Rückruf-Verifikation von FGR Matomo Stats muss erreichbar bleiben
        $route = (string) ( $GLOBALS['wp']->query_vars['rest_route'] ?? '' );
        if ( 0 === strpos( ltrim( $route, '/' ), 'fgr-matomo-stats/' ) ) {
            return $result;
        }
        return new WP_Error( 'fgr_pwp_protected', 'Diese Website ist passwortgeschützt.', [ 'status' => 401 ] );
    }

    // ── Admin-Bar-Hinweis ────────────────────────────────────────────────────

    public static function admin_bar( WP_Admin_Bar $bar ): void {
        if ( ! current_user_can( 'manage_options' ) || ! self::is_enforced() ) {
            return;
        }
        $bar->add_node( [
            'id'    => 'fgr-pwp',
            'title' => '<span class="ab-icon dashicons dashicons-lock" style="top:2px"></span>Passwortschutz aktiv',
            'href'  => admin_url( 'admin.php?page=fgr-password-protect' ),
        ] );
    }

    // ── IP ───────────────────────────────────────────────────────────────────

    /**
     * Client-IP. Proxy-Header (X-Forwarded-For usw.) sind vom Besucher fälschbar und
     * zählen nur, wenn per Filter ein vertrauenswürdiger Reverse-Proxy bestätigt ist.
     */
    public static function client_ip(): string {
        $candidates = [];
        if ( apply_filters( 'fgr_pwp_trust_proxy_headers', false ) ) {
            if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
                $xff          = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
                $candidates[] = trim( (string) strtok( $xff, ',' ) );
            }
            if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
                $candidates[] = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) );
            }
        }
        if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
            $candidates[] = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
        }
        foreach ( $candidates as $ip ) {
            if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
                return $ip;
            }
        }
        return '';
    }
}

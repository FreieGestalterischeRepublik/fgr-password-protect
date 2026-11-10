<?php
/**
 * Plugin Name:  FGR Password Protect
 * Description:  Ein Plugin der Freien Gestalterischen Republik. Schützt die gesamte Website mit einem Passwort. Gestaltung im FGR-Design oder individuell (Logo, Farben, Schrift).
 * Version:      1.0.0
 * Author:       Freie Gestalterische Republik
 * Author URI:   https://fgr.design
 * License:      GPL-2.0-or-later
 * Requires PHP: 7.4
 * Requires at least: 6.0
 * Text Domain:  fgr-password-protect
 */

defined( 'ABSPATH' ) || exit;

define( 'FGR_PWP_VERSION', '1.0.0' );
define( 'FGR_PWP_OPTION', 'fgr_password_protect' );
define( 'FGR_PWP_COOKIE', 'fgr_pwp_access' );

// Update-Checker: fragt die zentrale FGR-Update-API ab (nicht direkt GitHub)
require_once plugin_dir_path( __FILE__ ) . 'lib/plugin-update-checker/plugin-update-checker.php';
$fgr_pwp_updater = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
    'https://fgr-plugins-api.fgr.design/fgr-password-protect.json',
    __FILE__,
    'fgr-password-protect'
);

// Auto-Update: WordPress' täglicher Update-Cron installiert neue Versionen
// dieses Plugins automatisch, kein manueller Klick auf jeder Seite nötig.
add_filter( 'auto_update_plugin', function ( $update, $item ) {
    if ( isset( $item->slug ) && $item->slug === 'fgr-password-protect' ) {
        return true;
    }
    return $update;
}, 10, 2 );

// "Details anzeigen" und "Nach Update suchen" erscheinen in der Pluginliste auch wenn ein Update verfügbar ist.
add_filter( 'plugin_row_meta', function ( array $links, string $plugin_file ): array {
    if ( plugin_basename( __FILE__ ) !== $plugin_file || ! current_user_can( 'update_plugins' ) ) {
        return $links;
    }
    $has_details = false;
    $has_check   = false;
    foreach ( $links as $link ) {
        if ( strpos( $link, 'open-plugin-details-modal' ) !== false ) $has_details = true;
        if ( strpos( $link, 'puc_check_for_updates' )     !== false ) $has_check   = true;
    }
    if ( ! $has_details ) {
        $url     = network_admin_url( 'plugin-install.php?tab=plugin-information&plugin=fgr-password-protect&TB_iframe=true&width=600&height=550' );
        $links[] = '<a href="' . esc_url( $url ) . '" class="thickbox open-plugin-details-modal">Details anzeigen</a>';
    }
    if ( ! $has_check ) {
        $url     = wp_nonce_url(
            add_query_arg( [ 'puc_check_for_updates' => 1, 'puc_slug' => 'fgr-password-protect' ], self_admin_url( 'plugins.php' ) ),
            'puc_check_for_updates'
        );
        $links[] = '<a href="' . esc_url( $url ) . '">Nach Update suchen</a>';
    }
    return $links;
}, 20, 2 );

// Link "Einstellungen" in der Pluginliste
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), function ( array $links ): array {
    array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=fgr-password-protect' ) ) . '">Einstellungen</a>' );
    return $links;
} );

// ── MU-Plugin-Sync ────────────────────────────────────────────────────────────
// Installiert/aktualisiert das MU-Plugin "FGR Plugin-Übersicht" von GitHub
// (function_exists-Guard: MU-Plugin und andere FGR-Plugins definieren dieselbe Funktion)

if ( ! function_exists( 'fgr_mu_sync' ) ) {
    function fgr_mu_sync(): void {
        // An den jeweils aktuellen GitHub-Release gepinnt statt an den beweglichen main-Branch.
        $release = wp_remote_get(
            'https://api.github.com/repos/FreieGestalterischeRepublik/fgr-plugin-overview/releases/latest',
            [
                'timeout'    => 10,
                'user-agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url(),
            ]
        );
        if ( is_wp_error( $release ) || 200 !== wp_remote_retrieve_response_code( $release ) ) return;
        $release_data = json_decode( wp_remote_retrieve_body( $release ), true );
        $tag          = (string) ( $release_data['tag_name'] ?? '' );
        if ( '' === $tag || ! preg_match( '/^v?[\d.]+$/', $tag ) ) return;

        $url      = 'https://raw.githubusercontent.com/FreieGestalterischeRepublik/fgr-plugin-overview/' . rawurlencode( $tag ) . '/fgr-plugin-overview.php';
        $dest_dir = WPMU_PLUGIN_DIR;
        $dest     = $dest_dir . '/fgr-plugin-overview.php';

        $response = wp_remote_get( $url, [
            'timeout'    => 15,
            'user-agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url(),
        ] );

        if ( is_wp_error( $response ) ) return;
        if ( 200 !== wp_remote_retrieve_response_code( $response ) ) return;

        $remote_content = wp_remote_retrieve_body( $response );
        if ( empty( $remote_content ) ) return;

        preg_match( '/\*\s+Version:\s+([\d.]+)/i', $remote_content, $matches );
        $remote_version = $matches[1] ?? '0';

        $installed_version = '0';
        if ( file_exists( $dest ) ) {
            $contents = file_get_contents( $dest );
            preg_match( '/\*\s+Version:\s+([\d.]+)/i', $contents, $m );
            $installed_version = $m[1] ?? '0';
        }

        if ( ! file_exists( $dest ) || version_compare( $remote_version, $installed_version, '>' ) ) {
            if ( ! is_dir( $dest_dir ) ) {
                wp_mkdir_p( $dest_dir );
            }
            file_put_contents( $dest, $remote_content );
            delete_transient( 'fgr_mu_update_info' );
        }
    }
}

// MU-Plugin bei Plugin-Aktivierung installieren/aktualisieren
register_activation_hook( __FILE__, 'fgr_mu_sync' );

// MU-Plugin nach Update dieses Plugins aktualisieren
add_action( 'upgrader_process_complete', function ( $upgrader, array $hook_extra ): void {
    if ( ( $hook_extra['type'] ?? '' ) !== 'plugin' ) return;
    if ( ( $hook_extra['action'] ?? '' ) !== 'update' ) return;

    $updated = array_merge(
        isset( $hook_extra['plugin'] )  ? (array) $hook_extra['plugin']  : [],
        isset( $hook_extra['plugins'] ) ? (array) $hook_extra['plugins'] : []
    );
    if ( in_array( plugin_basename( __FILE__ ), $updated, true ) ) {
        fgr_mu_sync();
    }
}, 10, 2 );

// Warnung wenn Plugin im falschen Ordner installiert ist (GitHub-Archiv "…-main")
if ( is_admin() && substr( untrailingslashit( plugin_dir_path( __FILE__ ) ), -5 ) === '-main' ) {
    add_action( 'admin_notices', function () {
        echo '<div class="notice notice-error"><p>'
            . '<strong>FGR Password Protect:</strong> Das Plugin ist im falschen Ordner installiert '
            . '(<code>' . esc_html( basename( plugin_dir_path( __FILE__ ) ) ) . '</code>). '
            . 'Bitte das Plugin <strong>deaktivieren → löschen → neu installieren</strong>.'
            . '</p></div>';
    } );
}

require_once plugin_dir_path( __FILE__ ) . 'includes/class-fgr-pwp-protector.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-fgr-pwp-page.php';
FGR_PWP_Protector::init();

add_action( 'plugins_loaded', function () {
    if ( is_admin() ) {
        require_once plugin_dir_path( __FILE__ ) . 'includes/class-fgr-pwp-settings.php';
        new FGR_PWP_Settings();
    }
} );

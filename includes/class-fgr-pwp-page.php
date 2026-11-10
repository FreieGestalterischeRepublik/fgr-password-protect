<?php
/**
 * Die Passwortseite, die Besucher ohne Zugang sehen (FGR-Design oder individuell).
 */

defined( 'ABSPATH' ) || exit;

class FGR_PWP_Page {

    /** Erlaubte Schriftfamilien (Whitelist, verhindert CSS-Injection) */
    public static function fonts(): array {
        return [
            'system-ui,-apple-system,BlinkMacSystemFont,sans-serif' => 'System-Standard',
            'Helvetica,Arial,sans-serif'                             => 'Helvetica',
            'Arial,Helvetica,sans-serif'                             => 'Arial',
            'Verdana,Geneva,sans-serif'                              => 'Verdana',
            'Tahoma,Geneva,sans-serif'                               => 'Tahoma',
            '"Trebuchet MS",Helvetica,sans-serif'                    => 'Trebuchet MS',
            '"Gill Sans","Gill Sans MT",Calibri,sans-serif'          => 'Gill Sans',
            'Georgia,"Times New Roman",serif'                        => 'Georgia',
            '"Times New Roman",Times,serif'                          => 'Times New Roman',
            '"Palatino Linotype",Palatino,"Book Antiqua",serif'      => 'Palatino',
        ];
    }

    public static function render( array $opts, string $error, bool $preview ): void {
        if ( ! defined( 'DONOTCACHEPAGE' ) ) {
            define( 'DONOTCACHEPAGE', true ); // Caching-Plugins: diese Seite nie zwischenspeichern
        }
        status_header( 200 );
        nocache_headers();
        header( 'Content-Type: text/html; charset=' . get_option( 'blog_charset', 'UTF-8' ) );
        header( 'X-Robots-Tag: noindex, nofollow' );

        $remember_days = absint( $opts['remember_days'] );
        $headline      = (string) $opts['headline'];
        $text          = (string) $opts['text'];
        $button        = '' !== (string) $opts['button_label'] ? (string) $opts['button_label'] : 'Entsperren';
        $is_fgr        = 'custom' !== $opts['design'];

        ?><!doctype html>
<html lang="<?php echo esc_attr( get_bloginfo( 'language' ) ); ?>">
<head>
<meta charset="<?php echo esc_attr( get_option( 'blog_charset', 'UTF-8' ) ); ?>">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?php echo esc_html( '' !== $headline ? $headline : get_bloginfo( 'name' ) ); ?></title>
<?php if ( function_exists( 'get_site_icon_url' ) && get_site_icon_url() ) : ?>
<link rel="icon" href="<?php echo esc_url( get_site_icon_url( 32 ) ); ?>">
<?php endif; ?>
<style>
<?php echo $is_fgr ? self::css_fgr() : self::css_custom( $opts ); // phpcs:ignore -- CSS aus validierten Werten ?>
</style>
</head>
<body class="<?php echo $is_fgr ? 'fgr-design' : 'custom-design'; ?>">
<?php if ( $preview ) : ?>
    <div class="preview-bar">Vorschau – so sehen Besucher die Passwortseite. <a href="<?php echo esc_url( admin_url( 'admin.php?page=fgr-password-protect' ) ); ?>">Zurück zu den Einstellungen</a></div>
<?php endif; ?>
    <main class="box">
        <?php if ( ! $is_fgr ) : self::logo( $opts ); endif; ?>
        <?php if ( '' !== $headline ) : ?><h1><?php echo esc_html( $headline ); ?></h1><?php endif; ?>
        <?php if ( '' !== $text ) : ?><p class="text"><?php echo nl2br( esc_html( $text ) ); ?></p><?php endif; ?>

        <form method="post" action=""<?php echo $preview ? ' onsubmit="return false"' : ''; ?>>
            <?php if ( '' !== $error ) : ?>
                <p class="error" role="alert"><?php echo esc_html( $error ); ?></p>
            <?php endif; ?>
            <label class="sr-only" for="fgr_pwp_password">Passwort</label>
            <div class="row">
                <input type="password" id="fgr_pwp_password" name="fgr_pwp_password" placeholder="Passwort"
                       autocomplete="current-password" required autofocus>
                <button type="submit"><?php echo esc_html( $button ); ?></button>
            </div>
            <?php if ( $remember_days > 0 ) : ?>
            <label class="remember">
                <input type="checkbox" name="fgr_pwp_remember" value="1" checked>
                Angemeldet bleiben (<?php echo esc_html( 1 === $remember_days ? '1 Tag' : $remember_days . ' Tage' ); ?>)
            </label>
            <?php endif; ?>
        </form>
    </main>
    <?php if ( $is_fgr || ! empty( $opts['show_fgr_badge'] ) ) : self::badge(); endif; ?>
</body>
</html><?php
    }

    private static function logo( array $opts ): void {
        $logo_id = absint( $opts['logo_id'] );
        $url     = $logo_id ? wp_get_attachment_image_url( $logo_id, 'full' ) : '';
        if ( ! $url ) {
            return;
        }
        echo '<img class="logo" src="' . esc_url( $url ) . '" alt="' . esc_attr( get_bloginfo( 'name' ) ) . '">';
    }

    private static function badge(): void {
        ?>
    <div id="powered_by">
        <small>Eine Webseite von:</small>
        <a href="https://fgr.design" target="_blank" rel="noopener" aria-label="FGR.DESIGN">
            <svg xmlns="http://www.w3.org/2000/svg" width="283" height="143.393" viewBox="0 0 283 143.393">
                <path d="M0,102V0H283V103H40.394L0,143.393Z" fill="#ffff6b"/>
                <path d="M9.18-9.36V0h-6.8V-25.7h17.6v5.76H9.18v4.824H19.368v5.76Zm31.392.252h-4.1v-5.58H46.944v11.7a15.869,15.869,0,0,1-5.058,2.7,19.045,19.045,0,0,1-5.958.936Q29.88.648,26.6-2.826t-3.276-9.882a16.636,16.636,0,0,1,1.512-7.4,10.516,10.516,0,0,1,4.32-4.662,13.339,13.339,0,0,1,6.66-1.584,11.658,11.658,0,0,1,7.362,2.2A9.376,9.376,0,0,1,46.656-18l-6.912.684a4.3,4.3,0,0,0-1.35-2.43,4.089,4.089,0,0,0-2.646-.774,4.473,4.473,0,0,0-3.96,1.89,10.239,10.239,0,0,0-1.3,5.742q0,4.032,1.44,5.868A5.5,5.5,0,0,0,36.54-5.184a8.471,8.471,0,0,0,4.032-.936ZM66.384,0,62.172-8.856H58.428V0H51.84V-25.7H61.992q5.04,0,7.65,2.07a7.279,7.279,0,0,1,2.61,6.066,9.324,9.324,0,0,1-.972,4.356,7.164,7.164,0,0,1-2.844,2.952L73.692,0ZM61.812-14.22a4.449,4.449,0,0,0,2.88-.756,2.932,2.932,0,0,0,.9-2.376,2.665,2.665,0,0,0-.99-2.286,5.02,5.02,0,0,0-3.006-.738H58.428v6.156ZM76.428,0V-6.876h6.264V0ZM87.48,0V-25.7H97.6q6.372,0,9.684,3.2t3.312,9.36q0,6.336-3.474,9.738T97.2,0Zm9.828-5.832q6.12,0,6.12-7.092a8.077,8.077,0,0,0-1.422-5.238,5.32,5.32,0,0,0-4.338-1.71H94.392v14.04Zm18,5.832V-25.7h18.4v5.688H122.04v4.1h10.944v5.688H122.04v4.536h12.42L133.884,0Zm30.636-10.44q-4.392-.828-6.39-2.7a6.711,6.711,0,0,1-2-5.148,7.126,7.126,0,0,1,2.682-5.976q2.682-2.088,7.65-2.088a12.684,12.684,0,0,1,7.6,2.016,7.834,7.834,0,0,1,3.168,5.832l-6.7.684a3.847,3.847,0,0,0-1.314-2.34,4.58,4.58,0,0,0-2.79-.72,4.867,4.867,0,0,0-2.556.558,1.809,1.809,0,0,0-.9,1.638,1.742,1.742,0,0,0,.684,1.44,5.174,5.174,0,0,0,2.2.828l3.492.684a12.535,12.535,0,0,1,6.318,2.754,6.823,6.823,0,0,1,2.034,5.2,7.435,7.435,0,0,1-2.808,6.246Q153.5.648,148.284.648q-5.256,0-8.1-2.286a9.012,9.012,0,0,1-3.132-6.822h6.876a3.766,3.766,0,0,0,1.35,2.7,5.294,5.294,0,0,0,3.258.864,4.907,4.907,0,0,0,2.736-.63,2.092,2.092,0,0,0,.936-1.854,1.938,1.938,0,0,0-.7-1.584,4.641,4.641,0,0,0-2.142-.828ZM163.3,0V-25.7h6.912V0Zm28.872-9.108h-4.1v-5.58H198.54v11.7a15.869,15.869,0,0,1-5.058,2.7,19.045,19.045,0,0,1-5.958.936q-6.048,0-9.324-3.474t-3.276-9.882a16.635,16.635,0,0,1,1.512-7.4,10.516,10.516,0,0,1,4.32-4.662,13.339,13.339,0,0,1,6.66-1.584,11.658,11.658,0,0,1,7.362,2.2A9.376,9.376,0,0,1,198.252-18l-6.912.684a4.3,4.3,0,0,0-1.35-2.43,4.089,4.089,0,0,0-2.646-.774,4.473,4.473,0,0,0-3.96,1.89,10.239,10.239,0,0,0-1.3,5.742q0,4.032,1.44,5.868a5.5,5.5,0,0,0,4.608,1.836,8.471,8.471,0,0,0,4.032-.936ZM219.24,0l-9.252-14.724V0h-6.552V-25.7h6.048l9.252,14.724V-25.7h6.552V0Z" transform="translate(28 64)"/>
            </svg>
        </a>
    </div>
        <?php
    }

    /** Gemeinsame Grundregeln für beide Designs */
    private static function css_base(): string {
        return '
*,*::before,*::after{box-sizing:border-box}
html,body{margin:0;padding:0}
body{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:30px}
.box{width:100%}
h1{margin:0 0 10px}
.text{margin:0 0 28px;line-height:1.5}
.row{display:flex;gap:10px;flex-wrap:wrap}
.row input{flex:1 1 220px;min-width:0;font:inherit;font-size:1.0625rem;padding:13px 16px;border-radius:0;outline:none}
.row button{flex:0 0 auto;font:inherit;font-size:1.0625rem;font-weight:700;padding:13px 26px;border:0;border-radius:0;cursor:pointer}
.row button:hover{opacity:.9}
.remember{display:flex;align-items:center;gap:8px;margin-top:14px;font-size:.9rem;cursor:pointer}
.error{margin:0 0 14px;padding:10px 14px;font-weight:600}
.sr-only{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap}
#powered_by{position:absolute;right:50px;bottom:40px;width:130px}
#powered_by svg{width:100%;height:auto;display:block}
#powered_by small{font-size:.8325rem;display:block;margin-bottom:6.5px;white-space:nowrap}
.preview-bar{position:fixed;top:0;left:0;right:0;z-index:10;background:#1d2327;color:#fff;font:14px/1.4 system-ui,sans-serif;padding:8px 16px;text-align:center}
.preview-bar a{color:#72aee6}
@media (max-width:767px){
  body{align-items:flex-start;padding-top:18vh;padding-bottom:150px}
  #powered_by{right:30px;bottom:30px;width:100px}
  .row button{flex:1 1 100%}
}';
    }

    /** FGR-Design: Lila Hintergrund, weiße Schrift, gelber Button (wie FGR Maintenance) */
    private static function css_fgr(): string {
        return self::css_base() . '
body{background:#5c1bdb;color:#fff;font-family:Helvetica,Arial,sans-serif}
.box{max-width:675px;padding-bottom:75px}
h1{font-size:3.25rem;font-weight:900;line-height:1.1}
.text{font-size:1.25rem;font-weight:300}
.row input{border:2px solid #fff;background:transparent;color:#fff}
.row input::placeholder{color:rgba(255,255,255,.7)}
.row input:focus{border-color:#ffff6b}
.row button{background:#ffff6b;color:#000}
.remember input{accent-color:#ffff6b}
.error{background:#ffff6b;color:#000}
#powered_by,#powered_by a{color:#fff}
@media (max-width:767px){.box{max-width:375px}h1{font-size:2.5rem}.text{font-size:1.125rem}}';
    }

    /** Individuelles Design aus den Einstellungen */
    private static function css_custom( array $opts ): string {
        $fonts = self::fonts();
        $font  = array_key_exists( $opts['font_family'], $fonts ) ? $opts['font_family'] : key( $fonts );
        $hex   = function ( $v, $fallback ) {
            return sanitize_hex_color( (string) $v ) ?: $fallback;
        };
        $bg        = $hex( $opts['bg_color'], '#ffffff' );
        $color     = $hex( $opts['text_color'], '#1d2327' );
        $btn_bg    = $hex( $opts['button_bg'], '#1d2327' );
        $btn_color = $hex( $opts['button_text'], '#ffffff' );
        $logo_w    = absint( $opts['logo_width'] ) ?: 220;

        $bg_image = '';
        $bg_id    = absint( $opts['bg_image_id'] );
        if ( $bg_id ) {
            $url = wp_get_attachment_image_url( $bg_id, 'full' );
            if ( $url ) {
                $bg_image = 'background-image:url("' . esc_url( $url ) . '");background-size:cover;background-position:center;';
            }
        }

        return self::css_base() . '
body{background-color:' . $bg . ';' . $bg_image . 'color:' . $color . ';font-family:' . $font . ';text-align:center}
.box{max-width:480px}
.logo{display:block;max-width:' . $logo_w . 'px;width:100%;height:auto;margin:0 auto 32px}
h1{font-size:2rem;font-weight:700;line-height:1.2}
.text{font-size:1.0625rem}
.row input{border:1px solid ' . $color . ';background:#fff;color:#1d2327}
.row input:focus{border-color:' . $btn_bg . ';box-shadow:0 0 0 1px ' . $btn_bg . '}
.row button{background:' . $btn_bg . ';color:' . $btn_color . '}
.remember{justify-content:center}
.remember input{accent-color:' . $btn_bg . '}
.error{border:1px solid currentColor}
#powered_by,#powered_by a{color:' . $color . ';text-align:left}
@media (max-width:767px){body{padding-top:12vh}}';
    }
}

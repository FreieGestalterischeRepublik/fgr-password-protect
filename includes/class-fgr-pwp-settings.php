<?php
/**
 * Einstellungsseite unter "FGR Plugins → Password Protect".
 */

defined( 'ABSPATH' ) || exit;

class FGR_PWP_Settings {

    /** Hash, der im laufenden Request gerade erzeugt wurde (siehe sanitize_options) */
    private static string $fresh_hash = '';

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'register_submenu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
    }

    public function register_submenu(): void {
        add_submenu_page(
            'fgr-plugins',
            'FGR Password Protect',
            'Password Protect',
            'manage_options',
            'fgr-password-protect',
            [ $this, 'render_page' ]
        );
    }

    public function register_settings(): void {
        register_setting(
            'fgr_pwp_group',
            FGR_PWP_OPTION,
            [
                'sanitize_callback' => [ $this, 'sanitize_options' ],
                'default'           => FGR_PWP_Protector::defaults(),
            ]
        );
    }

    public function enqueue( string $hook ): void {
        if ( 'fgr-plugins_page_fgr-password-protect' === $hook ) {
            wp_enqueue_media();
        }
    }

    public function sanitize_options( $input ): array {
        $input = is_array( $input ) ? $input : [];
        $old   = FGR_PWP_Protector::options();
        $d     = FGR_PWP_Protector::defaults();
        $clean = [];

        // Passwort: leeres Feld = bisheriges behalten. Gespeichert wird nur der Hash.
        // WordPress ruft den Sanitize-Callback beim allerersten Speichern zweimal auf
        // (update_option → add_option); im zweiten Durchlauf kommt das bereits bereinigte
        // Array mit password_hash an. Den akzeptieren wir nur, wenn wir ihn selbst gerade
        // erzeugt haben – sonst ließe sich per POST ein beliebiger Hash einschleusen.
        $new_password = isset( $input['new_password'] ) ? (string) $input['new_password'] : '';
        if ( '' !== $new_password ) {
            self::$fresh_hash       = wp_hash_password( $new_password );
            $clean['password_hash'] = self::$fresh_hash;
        } elseif ( '' !== self::$fresh_hash && ( $input['password_hash'] ?? '' ) === self::$fresh_hash ) {
            $clean['password_hash'] = self::$fresh_hash;
        } else {
            $clean['password_hash'] = (string) $old['password_hash'];
        }

        $clean['active']          = ! empty( $input['active'] );
        $clean['allow_logged_in'] = ! empty( $input['allow_logged_in'] );
        $clean['protect_rest']    = ! empty( $input['protect_rest'] );
        $clean['remember_days']   = min( 365, absint( $input['remember_days'] ?? $d['remember_days'] ) );

        $lines = preg_split( '/\r\n|\r|\n/', (string) ( $input['ip_whitelist'] ?? '' ) );
        $ips   = [];
        foreach ( $lines as $line ) {
            $line = trim( $line );
            if ( '' !== $line && filter_var( $line, FILTER_VALIDATE_IP ) ) {
                $ips[] = $line;
            }
        }
        $clean['ip_whitelist'] = implode( "\n", array_unique( $ips ) );

        $clean['design']       = ( $input['design'] ?? '' ) === 'custom' ? 'custom' : 'fgr';
        $clean['headline']     = sanitize_text_field( $input['headline'] ?? '' );
        $clean['text']         = sanitize_textarea_field( $input['text'] ?? '' );
        $clean['button_label'] = sanitize_text_field( $input['button_label'] ?? '' ) ?: $d['button_label'];

        $clean['logo_id']        = absint( $input['logo_id'] ?? 0 );
        $clean['bg_image_id']    = absint( $input['bg_image_id'] ?? 0 );
        $clean['logo_width']     = min( 2000, absint( $input['logo_width'] ?? $d['logo_width'] ) ) ?: $d['logo_width'];
        foreach ( [ 'bg_color', 'text_color', 'button_bg', 'button_text' ] as $key ) {
            $clean[ $key ] = sanitize_hex_color( (string) ( $input[ $key ] ?? '' ) ) ?: $d[ $key ];
        }
        $fonts                  = FGR_PWP_Page::fonts();
        $clean['font_family']   = array_key_exists( $input['font_family'] ?? '', $fonts ) ? $input['font_family'] : $d['font_family'];
        $clean['show_fgr_badge'] = ! empty( $input['show_fgr_badge'] );

        return $clean;
    }

    public function render_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Keine Berechtigung.' );
        }

        $o          = FGR_PWP_Protector::options();
        $n          = FGR_PWP_OPTION;
        $has_pw     = '' !== (string) $o['password_hash'];
        $enforced   = FGR_PWP_Protector::is_enforced( $o );
        $custom     = 'custom' === $o['design'];
        $current_ip = FGR_PWP_Protector::client_ip();
        $preview    = add_query_arg( 'fgr_pwp_preview', '1', home_url( '/' ) );
        $fonts      = FGR_PWP_Page::fonts();
        $logo_url   = $o['logo_id'] ? wp_get_attachment_image_url( $o['logo_id'], 'medium' ) : '';
        $bg_url     = $o['bg_image_id'] ? wp_get_attachment_image_url( $o['bg_image_id'], 'medium' ) : '';
        $hidden     = $custom ? '' : ' style="display:none"';
        ?>
        <div class="wrap">
            <h1>
                <span class="dashicons dashicons-lock" style="font-size:28px;height:28px;margin-right:6px;vertical-align:middle;color:#2271b1;"></span>
                FGR Password Protect
            </h1>
            <p style="color:#646970;">Schützt die gesamte Website mit einem Passwort. Besucher sehen eine Passwortseite, bis sie das richtige Passwort eingegeben haben.</p>

            <?php if ( $enforced ) : ?>
            <div class="notice notice-warning" style="border-left-color:#d63638;">
                <p><strong>Passwortschutz ist aktiv</strong> – Besucher müssen das Passwort eingeben, um die Website zu sehen.</p>
            </div>
            <?php elseif ( $o['active'] && ! $has_pw ) : ?>
            <div class="notice notice-error">
                <p><strong>Noch kein Passwort gesetzt</strong> – der Schutz ist eingeschaltet, greift aber erst, wenn ein Passwort gespeichert ist.</p>
            </div>
            <?php endif; ?>

            <form method="post" action="options.php">
                <?php settings_fields( 'fgr_pwp_group' ); ?>

                <h2 class="title">Zugang</h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">Status</th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr( $n ); ?>[active]" value="1" <?php checked( $o['active'] ); ?>>
                                <strong>Passwortschutz aktivieren</strong>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="fgr_pwp_new_password">Passwort</label></th>
                        <td>
                            <input type="text" id="fgr_pwp_new_password" name="<?php echo esc_attr( $n ); ?>[new_password]"
                                   value="" autocomplete="new-password" style="width:100%;max-width:320px;"
                                   placeholder="<?php echo $has_pw ? 'Leer lassen = unverändert' : 'Neues Passwort'; ?>">
                            <p class="description">
                                <?php if ( $has_pw ) : ?>
                                    <span style="color:#00a32a;">✓ Ein Passwort ist gesetzt.</span>
                                    Es wird nur verschlüsselt gespeichert und kann deshalb nicht angezeigt werden.
                                    Ein neues Passwort meldet alle Besucher ab, die bisher Zugang hatten.
                                <?php else : ?>
                                    Dieses Passwort geben Sie an alle weiter, die die Website sehen sollen.
                                <?php endif; ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Eingeloggte Benutzer</th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr( $n ); ?>[allow_logged_in]" value="1" <?php checked( $o['allow_logged_in'] ); ?>>
                                Eingeloggte Benutzer sehen die Website ohne Passwort
                            </label>
                            <p class="description">Administratoren haben immer Zugang. Ohne Haken müssen alle anderen Rollen (z.&nbsp;B. Kunden) das Passwort ebenfalls eingeben.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="fgr_pwp_remember_days">Angemeldet bleiben</label></th>
                        <td>
                            <input type="number" id="fgr_pwp_remember_days" name="<?php echo esc_attr( $n ); ?>[remember_days]"
                                   value="<?php echo esc_attr( $o['remember_days'] ); ?>" min="0" max="365" style="width:80px;"> Tage
                            <p class="description">So lange merkt sich der Browser das Passwort, wenn „Angemeldet bleiben“ angehakt ist. <code>0</code> = Passwort bei jedem neuen Browser-Start erneut eingeben.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="fgr_pwp_ip_whitelist">IP-Freigabe</label></th>
                        <td>
                            <textarea id="fgr_pwp_ip_whitelist" name="<?php echo esc_attr( $n ); ?>[ip_whitelist]" rows="4"
                                      style="width:100%;max-width:320px;font-family:monospace;font-size:13px;"><?php echo esc_textarea( $o['ip_whitelist'] ); ?></textarea>
                            <p class="description">
                                Eine IP-Adresse pro Zeile. Diese IPs sehen die Website ohne Passwort.
                                <?php if ( $current_ip ) : ?>
                                    <br>Ihre aktuelle IP: <code id="fgr-pwp-current-ip"><?php echo esc_html( $current_ip ); ?></code>
                                    <button type="button" class="button button-small" id="fgr-pwp-add-ip" style="margin-left:6px;">Hinzufügen</button>
                                <?php endif; ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">REST-API</th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr( $n ); ?>[protect_rest]" value="1" <?php checked( $o['protect_rest'] ); ?>>
                                REST-API ebenfalls schützen
                            </label>
                            <p class="description">Sperrt <code>/wp-json/</code> für Besucher ohne Zugang (Inhalte lassen sich sonst über die API auslesen). Kann Plugins stören, die die REST-API von außen brauchen. Die Rückruf-Prüfung von FGR Matomo Stats bleibt immer erreichbar.</p>
                        </td>
                    </tr>
                </table>

                <h2 class="title">Gestaltung</h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">Design</th>
                        <td>
                            <fieldset>
                                <label style="display:block;margin-bottom:12px;">
                                    <input type="radio" name="<?php echo esc_attr( $n ); ?>[design]" value="fgr" <?php checked( ! $custom ); ?>>
                                    <strong>FGR-Design</strong>
                                    <br><span style="color:#646970;margin-left:24px;">Lila Seite mit FGR-Logo, wie die Under-Construction-Seite von FGR Maintenance</span>
                                </label>
                                <label style="display:block;">
                                    <input type="radio" name="<?php echo esc_attr( $n ); ?>[design]" value="custom" <?php checked( $custom ); ?>>
                                    <strong>Individuell</strong>
                                    <br><span style="color:#646970;margin-left:24px;">Eigenes Logo, Hintergrund, Farben und Schrift</span>
                                </label>
                            </fieldset>
                            <p style="margin-top:14px;">
                                <a href="<?php echo esc_url( $preview ); ?>" target="_blank" class="button">Vorschau öffnen</a>
                                <span class="description" style="margin-left:6px;">zeigt den zuletzt gespeicherten Stand</span>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="fgr_pwp_headline">Überschrift</label></th>
                        <td><input type="text" id="fgr_pwp_headline" name="<?php echo esc_attr( $n ); ?>[headline]" value="<?php echo esc_attr( $o['headline'] ); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="fgr_pwp_text">Text</label></th>
                        <td><textarea id="fgr_pwp_text" name="<?php echo esc_attr( $n ); ?>[text]" rows="3" class="large-text" style="max-width:500px;"><?php echo esc_textarea( $o['text'] ); ?></textarea></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="fgr_pwp_button">Button-Text</label></th>
                        <td><input type="text" id="fgr_pwp_button" name="<?php echo esc_attr( $n ); ?>[button_label]" value="<?php echo esc_attr( $o['button_label'] ); ?>" style="width:200px;"></td>
                    </tr>

                    <tr class="fgr-pwp-custom"<?php echo $hidden; // phpcs:ignore ?>>
                        <th scope="row">Logo</th>
                        <td>
                            <?php $this->media_field( 'logo_id', (int) $o['logo_id'], $logo_url, 'Logo auswählen' ); ?>
                            <p style="margin-top:10px;">
                                <label>Breite max.
                                    <input type="number" name="<?php echo esc_attr( $n ); ?>[logo_width]" value="<?php echo esc_attr( $o['logo_width'] ); ?>" min="20" max="2000" style="width:80px;"> px
                                </label>
                            </p>
                        </td>
                    </tr>
                    <tr class="fgr-pwp-custom"<?php echo $hidden; // phpcs:ignore ?>>
                        <th scope="row">Hintergrundbild</th>
                        <td>
                            <?php $this->media_field( 'bg_image_id', (int) $o['bg_image_id'], $bg_url, 'Bild auswählen' ); ?>
                            <p class="description">Optional. Füllt die ganze Seite, sonst gilt die Hintergrundfarbe.</p>
                        </td>
                    </tr>
                    <tr class="fgr-pwp-custom"<?php echo $hidden; // phpcs:ignore ?>>
                        <th scope="row">Farben</th>
                        <td>
                            <div style="display:flex;gap:28px;flex-wrap:wrap;">
                                <?php
                                $colors = [
                                    'bg_color'    => 'Hintergrund',
                                    'text_color'  => 'Text',
                                    'button_bg'   => 'Button',
                                    'button_text' => 'Button-Text',
                                ];
                                foreach ( $colors as $key => $label ) : ?>
                                <label><?php echo esc_html( $label ); ?><br>
                                    <input type="color" name="<?php echo esc_attr( $n . '[' . $key . ']' ); ?>" value="<?php echo esc_attr( $o[ $key ] ); ?>">
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </td>
                    </tr>
                    <tr class="fgr-pwp-custom"<?php echo $hidden; // phpcs:ignore ?>>
                        <th scope="row"><label for="fgr_pwp_font">Schrift</label></th>
                        <td>
                            <select id="fgr_pwp_font" name="<?php echo esc_attr( $n ); ?>[font_family]">
                                <?php foreach ( $fonts as $value => $label ) : ?>
                                <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $o['font_family'], $value ); ?> style="font-family:<?php echo esc_attr( $value ); ?>;"><?php echo esc_html( $label ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr class="fgr-pwp-custom"<?php echo $hidden; // phpcs:ignore ?>>
                        <th scope="row">FGR-Logo</th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr( $n ); ?>[show_fgr_badge]" value="1" <?php checked( $o['show_fgr_badge'] ); ?>>
                                „Eine Webseite von: FGR.DESIGN“ unten rechts anzeigen
                            </label>
                        </td>
                    </tr>
                </table>

                <?php submit_button( 'Einstellungen speichern' ); ?>
            </form>
        </div>

        <script>
        ( function () {
            // Individuelle Felder nur bei Design "Individuell" zeigen
            var rows = document.querySelectorAll( '.fgr-pwp-custom' );
            document.querySelectorAll( 'input[name="<?php echo esc_js( $n ); ?>[design]"]' ).forEach( function ( r ) {
                r.addEventListener( 'change', function () {
                    rows.forEach( function ( row ) { row.style.display = ( r.value === 'custom' ) ? '' : 'none'; } );
                } );
            } );

            // Mediathek für Logo und Hintergrundbild
            document.querySelectorAll( '.fgr-pwp-media' ).forEach( function ( wrap ) {
                var input   = wrap.querySelector( 'input[type=hidden]' );
                var preview = wrap.querySelector( 'img' );
                var remove  = wrap.querySelector( '.fgr-pwp-remove' );
                var frame;
                wrap.querySelector( '.fgr-pwp-select' ).addEventListener( 'click', function ( e ) {
                    e.preventDefault();
                    if ( ! frame ) {
                        frame = wp.media( { title: this.textContent, button: { text: 'Verwenden' }, multiple: false, library: { type: 'image' } } );
                        frame.on( 'select', function () {
                            var a = frame.state().get( 'selection' ).first().toJSON();
                            input.value = a.id;
                            preview.src = a.sizes && a.sizes.medium ? a.sizes.medium.url : a.url;
                            preview.style.display = 'block';
                            remove.style.display = '';
                        } );
                    }
                    frame.open();
                } );
                remove.addEventListener( 'click', function ( e ) {
                    e.preventDefault();
                    input.value = '';
                    preview.style.display = 'none';
                    remove.style.display = 'none';
                } );
            } );

            // Eigene IP in die Freigabe eintragen
            var btn = document.getElementById( 'fgr-pwp-add-ip' );
            if ( btn ) {
                btn.addEventListener( 'click', function () {
                    var ta    = document.getElementById( 'fgr_pwp_ip_whitelist' );
                    var ip    = document.getElementById( 'fgr-pwp-current-ip' ).textContent.trim();
                    var lines = ta.value.trim() ? ta.value.trim().split( '\n' ) : [];
                    if ( lines.indexOf( ip ) === -1 ) { lines.push( ip ); }
                    ta.value = lines.join( '\n' );
                    btn.textContent = 'Hinzugefügt ✓';
                    btn.disabled = true;
                } );
            }
        } )();
        </script>
        <?php
    }

    private function media_field( string $key, int $id, $url, string $label ): void {
        ?>
        <div class="fgr-pwp-media">
            <input type="hidden" name="<?php echo esc_attr( FGR_PWP_OPTION . '[' . $key . ']' ); ?>" value="<?php echo esc_attr( $id ?: '' ); ?>">
            <button type="button" class="button fgr-pwp-select"><?php echo esc_html( $label ); ?></button>
            <button type="button" class="button fgr-pwp-remove"<?php echo $url ? '' : ' style="display:none"'; ?>>Entfernen</button>
            <img src="<?php echo esc_url( (string) $url ); ?>" alt=""
                 style="margin-top:10px;max-width:200px;max-height:120px;border:1px solid #ddd;padding:4px;background:#f6f7f7;display:<?php echo $url ? 'block' : 'none'; ?>;">
        </div>
        <?php
    }
}

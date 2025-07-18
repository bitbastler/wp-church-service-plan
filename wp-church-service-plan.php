<?php
/**
 * Plugin Name: WP Church Service Plan
 * Description: A planning tool for church services with list view, input form, team management, and tabbed navigation.
 * Version: 1.0
 * Author: Uwe & Copilot
 */


defined('ABSPATH') || exit;

/* -------------------------------------------------
 * 🧱 Database Setup on Activation
 * ------------------------------------------------- */
register_activation_hook(__FILE__, 'church_service_create_table');
function church_service_create_table()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'church_service_plan';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        date datetime NOT NULL,
        welcome text,
        moderation text,
        sermon text,
        kids1topic text,
        kids1resp text,
        kids2topic text,
        kids2resp text,
        music_keys text,
        music_resp text,
        tech_sound text,
        tech_presentation text,
        info text,
        comment text,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

/* -------------------------------------------------
 * 📁 Upload Table Setup on Activation
 * ------------------------------------------------- */
register_activation_hook(__FILE__, 'church_service_create_upload_table');
function church_service_create_upload_table()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'church_service_uploads';
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE $table_name (
        id BIGINT(20) NOT NULL AUTO_INCREMENT,
        service_id MEDIUMINT(9) NOT NULL,
        file_id BIGINT(20) NOT NULL,
        file_name TEXT,
        team VARCHAR(64),
        uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY service_id (service_id)
    ) $charset_collate;";
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

/* -------------------------------------------------
 * ⏱️ Get Next Sunday After Latest Entry
 * ------------------------------------------------- */
function church_service_next_sunday()
{
    global $wpdb;
    $table = $wpdb->prefix . 'church_service_plan';

    $latest = $wpdb->get_var("SELECT MAX(date) FROM $table");
    $start = $latest ? strtotime($latest) : time();

    do {
        $start = strtotime('+1 day', $start);
    } while (date('w', $start) != 0); // Sunday

    return date('Y-m-d\T10:00', $start);
}

/* -------------------------------------------------
 * 🏷️ Labels and Field Grouping
 * ------------------------------------------------- */
function church_service_get_labels($lang = 'de')
{
    $labels = [
        'de' => [
            'date' => '📅 Datum',
            'welcome' => '👋 Begrüßung',
            'moderation' => '🎤 Moderation',
            'sermon' => '📖 Predigt',
            'kids1topic' => '🧒 Kids 1',
            'kids1resp' => '👨‍🏫 Kids 1 Mitarbeiter',
            'kids2topic' => '🧑 Kids 2',
            'kids2resp' => '👩‍🏫 Kids 2 Mitarbeiter',
            'music_keys' => '🎹 Klavier',
            'music_resp' => '🎵 Musik',
            'tech_sound' => '🔊 Sound',
            'tech_presentation' => '📽️ Präsentation',
            'info' => 'ℹ️ Info',
            'comment' => '💬 Kommentare'
        ],
        'en' => [
            'date' => '📅 Date',
            'welcome' => '👋 Welcome',
            'moderation' => '🎤 Moderation',
            'sermon' => '📖 Sermon',
            'kids1topic' => '🧒 Kids Group 1 Topic',
            'kids1resp' => '👨‍🏫 Kids Group 1 Leader',
            'kids2topic' => '🧑 Kids Group 2 Topic',
            'kids2resp' => '👩‍🏫 Kids Group 2 Leader',
            'music_keys' => '🎹 Keyboard',
            'music_resp' => '🎵 Music Leader',
            'tech_sound' => '🔊 Sound',
            'tech_presentation' => '📽️ Presentation',
            'info' => 'ℹ️ Info',
            'comment' => '💬 Comment'
        ]
    ];
    return $labels[$lang] ?? $labels['de'];
}

function church_service_get_form_groups($lang = 'de')
{
    $groups = [
        'de' => [
            '📅 Allgemein' => ['date', 'welcome', 'moderation', 'sermon'],
            '🎈 Kinder' => ['kids1topic', 'kids1resp', 'kids2topic', 'kids2resp'],
            '🎵 Musik & Technik' => ['music_keys', 'music_resp', 'tech_sound', 'tech_presentation'],
            '💬 Sonstiges' => ['info', 'comment']
        ],
        'en' => [
            '📅 General' => ['date', 'welcome', 'moderation', 'sermon'],
            '🎈 Kids Ministry' => ['kids1topic', 'kids1resp', 'kids2topic', 'kids2resp'],
            '🎵 Music & Tech' => ['music_keys', 'music_resp', 'tech_sound', 'tech_presentation'],
            '💬 Other' => ['info', 'comment']
        ]
    ];
    return $groups[$lang] ?? $groups['de'];
}

/* -------------------------------------------------
 * 👥 Team Management Helpers
 * ------------------------------------------------- */
function church_service_get_teams()
{
    return get_option('church_service_teams', []);
}

function church_service_get_team($field)
{
    $teams = church_service_get_teams();

    if (!isset($teams[$field])) {
        return [];
    }

    $raw = $teams[$field];

    if (is_array($raw)) {
        return array_filter(array_map('trim', $raw));
    }

    return array_filter(array_map('trim', explode(',', $raw)));
}

/* -------------------------------------------------
 * 📋 Frontend Table View Shortcode
 * ------------------------------------------------- */
function church_service_list_shortcode($atts)
{
    global $wpdb;

    // Shortcode-Attribut for detail page
    $atts = shortcode_atts([
        'detail_page' => 'default'
    ], $atts, 'church_service_list_shortcode');

    $detail_page = ($atts['detail_page'] === 'default' ? get_permalink() : $atts['detail_page']);

    ob_start();

    $table = $wpdb->prefix . 'church_service_plan';
    // Standard: Nur zukünftige Einträge anzeigen, außer Checkbox ist explizit aktiviert
    $show_all = isset($_GET['show_all']) && $_GET['show_all'] === '1';
    $from_today = $show_all ? "" : "WHERE date >= NOW()";
    // $results = $wpdb->get_results("SELECT * FROM $table $from_today ORDER BY date");
    $results = $wpdb->get_results("SELECT id,date,moderation,sermon,music_resp,music_keys FROM $table $from_today ORDER BY date");

    if ($results === null) {
        echo "<p style='color:red;'>Fehler bei der Datenbankabfrage. Tabelle: $table</p>";
        return ob_get_clean();
    }
    if (!$results) {
        echo "<p>No entries found.</p>";
        return ob_get_clean();
    }

    $columns = array_keys((array) $results[0]);
    $columns = array_filter($columns, fn($col) => $col !== 'id');
    $labels = church_service_get_labels();

    echo "<div id='church_service_wrapper' >";

    echo "<form method='get' style='margin:1em 0;'>
        <label><input type='checkbox' name='show_all' value='1' onchange='this.form.submit()'" . ($show_all ? " checked" : "") . ">
        Show all</label>
    </form>";

    echo "<table id='church_service_table' class='cell-border display compact'>
          <thead><tr>";

    foreach ($columns as $col) {
        $label = $labels[$col] ?? ucfirst($col);
        $class = '';
        echo "<th $class>" . esc_html($label) . "</th>";
    }

    echo "</tr></thead><tbody>";

    foreach ($results as $row) {
        echo "<tr data-id='" . esc_attr($row->id) . "'>";
        foreach ($columns as $col) {
            $val = ($col === 'date') ? substr($row->$col, 0, 10) : $row->$col;
            echo "<td $class>$val</td>";
        }
        echo "</tr>";
    }

    echo "</tbody></table></div>
    
    <script>
    jQuery(document).ready(function($){
        var table = $('#church_service_table').DataTable({
            autoWidth: false,
            scrollX: true,
            ordering: false,
            columnDefs: [{ targets: '_all', className: 'dt-nowrap' }],
            language: {
                url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/en-GB.json',
                search: 'Suche:'
            }
        });
        $('#church_service_table').on('click', 'tr', function () {
            var id = $(this).data('id');
            window.location.href = '$detail_page?edit_id=' + encodeURIComponent(id);
            //alert('Du hast die Zeile mit folgendem Inhalt geklickt:'+id);
        });
    });
    </script>";

    return ob_get_clean();
}
add_shortcode('church_service_list', 'church_service_list_shortcode');

/* -------------------------------------------------
 * 📝 Frontend Form Shortcode
 * ------------------------------------------------- */
function church_service_form_shortcode($atts)
{
    ob_start();
    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    global $wpdb;

    // Shortcode-Attribute für Berechtigungen
    $atts = shortcode_atts([
        'edit' => 'false',
        'new' => 'false',
        'upload' => 'false',
    ], $atts, 'church_service_form');
    $allow_edit = ($atts['edit'] === 'true');
    $allow_new = ($atts['new'] === 'true');
    $allow_upload = ($atts['upload'] === 'true');

    // Modus bestimmen
    $id = isset($_GET['edit_id']) ? intval($_GET['edit_id']) : (isset($_POST['edit_id']) ? intval($_POST['edit_id']) : 0);
    $edit_mode = $id > 0;
    $table = $wpdb->prefix . 'church_service_plan';

    $labels = church_service_get_labels();
    $groups = church_service_get_form_groups();
    $fields = array_merge(...array_values($groups));
    $data = array_fill_keys($fields, '');

    if ($edit_mode) {
        $entry = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id), 'ARRAY_A');
        if ($entry) {
            $data = array_merge($data, $entry);
        }
    } else {
        $data['date'] = church_service_next_sunday();
    }

    // Felder readonly, wenn nicht erlaubt
    $readonly = !($edit_mode ? $allow_edit : $allow_new);

    $notice = '';
    // Speichern/Update
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['church_service_form_save']) && !$readonly) {
        foreach ($fields as $field) {
            $data[$field] = sanitize_text_field($_POST[$field] ?? '');
        }
        unset($data['id']);
        if ($edit_mode) {
            $wpdb->update($table, $data, ['id' => $id]);
            $notice = '<div class="updated"><p><strong>Entry updated successfully.</strong></p></div>';
        } else {
            $wpdb->insert($table, $data);
            $id = $wpdb->insert_id;
            $edit_mode = true;
            $notice = '<div class="updated"><p><strong>Entry created successfully.</strong></p></div>';
        }
        // Nach dem Speichern: Redirect auf edit_id, damit Uploads etc. angezeigt werden
        $redirect_url = add_query_arg(['edit_id' => $id], strtok($_SERVER['REQUEST_URI'], '?'));
        if (!headers_sent()) {
            wp_redirect($redirect_url);
            exit;
        } else {
            echo "<script>window.location='" . esc_url_raw($redirect_url) . "';</script>";
            exit;
        }
    }
    // Upload-Formular im Upload-Tab (separat vom Hauptformular)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['church_service_uploads_submit']) && $edit_mode) {
        if (!empty($_FILES['church_service_uploads']['name'][0])) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            $upload_count = count($_FILES['church_service_uploads']['name']);
            $team = sanitize_text_field($_POST['upload_team'] ?? '');
            // Hole das Gottesdienst-Datum
            $service_date = '';
            $service_row = $wpdb->get_row($wpdb->prepare("SELECT date FROM {$wpdb->prefix}church_service_plan WHERE id = %d", $id));
            if ($service_row && !empty($service_row->date)) {
                $service_date = substr($service_row->date, 0, 10); // yyyy-mm-tt
            }
            // Gruppenname für Dateiname aufbereiten (ohne Emoji, Umlaute ersetzen, Leerzeichen zu _)
            $team_label = $team;
            $team_label = preg_replace('/^[^\w]+/u', '', $team_label); // Emoji entfernen
            $team_label = preg_replace('/[\x{1F300}-\x{1FAFF}]/u', '', $team_label); // weitere Emojis entfernen
            $team_label = trim($team_label);
            $team_label = str_replace(
                ['ä', 'ö', 'ü', 'Ä', 'Ö', 'Ü', 'ß', ' '],
                ['ae', 'oe', 'ue', 'Ae', 'Oe', 'Ue', 'ss', '_'],
                $team_label
            );
            for ($i = 0; $i < $upload_count; $i++) {
                if ($_FILES['church_service_uploads']['error'][$i] === UPLOAD_ERR_OK) {
                    $orig_name = $_FILES['church_service_uploads']['name'][$i];
                    $ext = pathinfo($orig_name, PATHINFO_EXTENSION);
                    $base = pathinfo($orig_name, PATHINFO_FILENAME);
                    $new_name = 'csp_' . $service_date . '_' . $team_label . '_' . $base;
                    // Nur erlaubte Zeichen im Dateinamen
                    $new_name = preg_replace('/[^A-Za-z0-9_\-\.]/', '', $new_name);
                    if ($ext)
                        $new_name .= '.' . $ext;
                    $file_array = [
                        'name' => $new_name,
                        'type' => $_FILES['church_service_uploads']['type'][$i],
                        'tmp_name' => $_FILES['church_service_uploads']['tmp_name'][$i],
                        'error' => $_FILES['church_service_uploads']['error'][$i],
                        'size' => $_FILES['church_service_uploads']['size'][$i],
                    ];
                    $attachment_id = media_handle_sideload($file_array, 0);
                    if (!is_wp_error($attachment_id)) {
                        $wpdb->insert(
                            $wpdb->prefix . 'church_service_uploads',
                            [
                                'service_id' => $id,
                                'file_id' => $attachment_id,
                                'file_name' => $new_name,
                                'team' => $team,
                            ]
                        );
                    }
                }
            }
        }
        // Nach Upload: Redirect, damit Upload-Liste aktualisiert wird
        $redirect_url = add_query_arg(['edit_id' => $id, 'mode' => 'edit'], get_permalink());
        if (!headers_sent()) {
            wp_redirect($redirect_url);
            exit;
        } else {
            echo "<script>window.location='" . esc_url_raw($redirect_url) . "';</script>";
            exit;
        }
    }
    // Bereits hochgeladene Dateien nach Team gruppieren
    $uploads_by_team = [];
    if ($edit_mode) {
        $all_uploads = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}church_service_uploads WHERE service_id = %d",
            $id
        ));
        foreach ($all_uploads as $upload) {
            $uploads_by_team[$upload->team][] = $upload;
        }
    }
    ?>
    <div id="church_service_wrapper" class="wrap">
        <?php if (!empty($notice))
            echo $notice; ?>
        <h2><?php echo $edit_mode ? ($readonly ? 'Show Entry' : 'Gottesdienst') : 'Gottesdienst'; ?></h2>
        <div>
            <form method="post" id="church_service_form" enctype="multipart/form-data">
                <div class="cs-tab-nav">
                    <?php
                    $i = 0;
                    foreach (array_keys($groups) as $group_label) {
                        $active = $i === 0 ? 'active' : '';
                        $tab_id = 'tab-' . $i;
                        $icon = mb_substr($group_label, 0, 2, 'UTF-8');
                        $label_text = trim(mb_substr($group_label, 2, null, 'UTF-8'));
                        echo "<button type='button' class='cs-tab-btn $active' data-tab='$tab_id'>"
                            . "<span class='tab-label-icon'>$icon</span>"
                            . "<span class='tab-label-text'>$label_text</span>"
                            . "</button>";
                        $i++;
                    }
                    // Upload-Tab hinzufügen
                    $upload_tab_id = 'tab-uploads';
                    if ($edit_mode) {
                        echo "<button type='button' class='cs-tab-btn' data-tab='$upload_tab_id'>"
                            . "<span class='tab-label-icon'>📁</span>"
                            . "<span class='tab-label-text'>Uploads</span>"
                            . "</button>";
                    }
                    ?>
                </div>
                <?php
                $i = 0;
                foreach ($groups as $group_label => $group_fields) {
                    $tab_id = 'tab-' . $i;
                    $visible = ($i === 0) ? 'style="display:block;"' : 'style="display:none;"';
                    echo "<div class='cs-tab-content' id='$tab_id' $visible>";
                    // echo "$group_label";
                    foreach ($group_fields as $field) {
                        $label = $labels[$field] ?? ucfirst($field);
                        $value = esc_attr($data[$field] ?? '');
                        $readonly_attr = $readonly ? 'readonly disabled' : '';
                        echo "<p><label for='$field'><strong>$label</strong><br>";
                        $team = church_service_get_team($field);
                        if (!empty($team)) {
                            $datalist_id = "list_$field";
                            echo "<input list='$datalist_id' id='$field' name='$field' value='$value' $readonly_attr>";
                            echo "<datalist id='$datalist_id'>";
                            foreach ($team as $member) {
                                echo "<option value='" . esc_attr($member) . "'>";
                            }
                            echo "</datalist>";
                        } else {
                            if ($field === 'info') {
                                echo "<textarea rows='5' id='$field' name='$field' $readonly_attr>$value</textarea>";
                            } else {
                                echo "<input type='text' id='$field' name='$field' value='$value' $readonly_attr>";
                            }
                        }
                        echo "</label></p>";
                    }
                    echo "</div>";
                    $i++;
                }
                // Upload-Tab-Inhalt
                $visible = ($i === 0) ? 'style="display:block;"' : 'style="display:none;"';
                echo "<div class='cs-tab-content' id='$upload_tab_id' $visible>";
                echo "<h3>Alle Uploads</h3>";
                if (!empty($uploads_by_team)) {
                    echo "<table style='width:100%;border-collapse:collapse;margin-bottom:1em;'><thead><tr><th>Team</th><th>Datei</th><th>Download</th></tr></thead><tbody>";
                    foreach ($uploads_by_team as $team => $uploads) {
                        foreach ($uploads as $upload) {
                            $url = wp_get_attachment_url($upload->file_id);
                            $name = esc_html($upload->file_name);
                            $team_disp = esc_html($team);
                            echo "<tr><td>$team_disp</td><td>$name</td><td><a href='" . esc_url($url) . "' target='_blank'>Download</a></td></tr>";
                        }
                    }
                    echo "</tbody></table>";
                } else {
                    echo "<p>Noch keine Dateien hochgeladen.</p>";
                }
                // Upload-Formular im Upload-Tab
                if ($allow_upload) {
                    echo '<div class="cs-upload" style="margin-top:1em;">';
                    if ($edit_mode) {
                        echo '<input type="hidden" name="upload_service_id" value="' . intval($id) . '">';
                    }
                    echo '<label"><strong>Dateien hochladen (Team zuordnen):</strong></label>';
                    echo '<select name="upload_team" required>';
                    foreach ($groups as $group_label => $group_fields) {
                        echo '<option value="' . esc_attr($group_label) . '">' . esc_html($group_label) . '</option>';
                    }
                    echo '</select>';
                    echo '<input type="file" name="church_service_uploads[]" multiple >';
                    echo '<button type="submit" class="cs-tab-btn" name="church_service_uploads_submit">Upload</button>';
                    echo '</div>';
                }
                echo "</div>"; // End Upload-Tab
            
                ?>
                <?php if ($edit_mode): ?>
                    <input type="hidden" name="edit_id" value="<?php echo intval($id); ?>">
                <?php endif; ?>
                <p>
                    <?php
                    // Button-Logik
                    // Always show Save button, label depends on mode
                    if (!$edit_mode && $allow_new) {
                        echo '<button type="submit" class="cs-tab-btn" name="church_service_form_save" value="Save">Save</button>';
                    } elseif ($edit_mode && $allow_edit) {
                        echo '<button type="submit" class="cs-tab-btn" name="church_service_form_save" value="Update">Save</button>';
                        if ($allow_new) {
                            // New-Button im Update-Modus: Felder leeren und Datum vorbelegen
                            $new_url = add_query_arg(['edit_id' => '', 'new_entry' => 'true'], strtok($_SERVER['REQUEST_URI'], '?'));
                            echo ' <button type="button" class="cs-tab-btn" onclick="window.location=\'' . esc_url($new_url) . '\'">New</button>';
                        }
                    }
                    // Upload-Button ist im Upload-Tab-Formular
                    ?>
                </p>
            </form>
        </div>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const buttons = document.querySelectorAll('.cs-tab-btn');
                const tabs = document.querySelectorAll('.cs-tab-content');
                if (!buttons.length || !tabs.length) return;
                buttons.forEach(btn => {
                    btn.addEventListener('click', function () {
                        buttons.forEach(b => b.classList.remove('active'));
                        this.classList.add('active');
                        const target = this.getAttribute('data-tab');
                        tabs.forEach(t => t.style.display = (t.id === target ? 'block' : 'none'));
                    });
                });
            });
        </script>
    </div>
    <?php
    // Felder leeren und Datum vorbelegen, wenn new_entry=1
    if (isset($_GET['new_entry']) && $_GET['new_entry'] === 'true') {
        $edit_mode = false;
        $id = 0;
        $data = array_fill_keys($fields, '');
        $data['date'] = church_service_next_sunday();
        // Keine Manipulation von $allow_new mehr nötig!
    }

    return ob_get_clean();
}
add_shortcode('church_service_form', 'church_service_form_shortcode');

/* -------------------------------------------------
 * 🛠️ Admin Menu for Team Settings
 * ------------------------------------------------- */
add_action('admin_menu', function () {
    add_menu_page(
        'Church Service Teams',
        'Church Service Teams',
        'manage_options',
        'church_service_teams',
        'church_service_teams_page',
        'dashicons-groups',
        20
    );
});

function church_service_teams_page()
{
    if (!current_user_can('manage_options'))
        return;

    $labels = church_service_get_labels();
    $teams = church_service_get_teams();
    array_shift($labels);
    array_shift($teams);
    array_pop($labels);
    array_pop($teams);
    array_pop($labels);
    array_pop($teams);

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['church_service_teams_save'])) {
        foreach ($labels as $key => $_) {
            $input = sanitize_text_field($_POST[$key] ?? '');
            $teams[$key] = array_filter(array_map('trim', explode(',', $input)));
        }
        church_service_save_teams($teams);
        echo '<div class="updated"><p><strong>Teams saved successfully.</strong></p></div>';
    }

    echo '<div class="wrap"><h1>Manage Church Service Teams</h1>';
    echo '<form method="post"><table class="form-table"><tbody>';

    foreach ($labels as $role => $label) {
        $names = implode(', ', $teams[$role] ?? []);
        echo "<tr>
            <th scope='row'><label for='$role'>$label</label></th>
            <td><input type='text' id='$role' name='$role' value='" . esc_attr($names) . "' style='width:100%; max-width:600px;'></td>
        </tr>";
    }

    echo '</tbody></table>';
    submit_button('Save Teams', 'primary', 'church_service_teams_save');
    echo '</form></div>';
}

function church_service_save_teams($teams)
{
    update_option('church_service_teams', $teams);
}

/* -------------------------------------------------
 * 📦 Enqueue Assets (Frontend)
 * ------------------------------------------------- */
add_action('wp_enqueue_scripts', 'church_service_enqueue_assets');
function church_service_enqueue_assets()
{
    if (!is_admin()) {
        wp_enqueue_script('jquery');
        wp_enqueue_style('wp-church-service-plan-style', plugin_dir_url(__FILE__) . 'wp-church-service-plan.css');

        wp_enqueue_style('datatables', 'https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css');
        wp_enqueue_script('datatables', 'https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js', ['jquery'], null, true);
    }

}




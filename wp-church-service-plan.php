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
function church_service_get_labels($lang = 'en')
{
    return [
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
    ];
}

function church_service_get_form_groups()
{
    return [
        '📅 General' => ['date', 'welcome', 'moderation', 'sermon'],
        '🎈 Kids Ministry' => ['kids1topic', 'kids1resp', 'kids2topic', 'kids2resp'],
        '🎵 Music & Tech' => ['music_keys', 'music_resp', 'tech_sound', 'tech_presentation'],
        '💬 Other' => ['info', 'comment']
    ];
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
    ob_start();

    $atts = shortcode_atts(['edit' => 'false'], $atts);
    $edit_mode = $atts['edit'] === 'true';

    $table = $wpdb->prefix . 'church_service_plan';
    $from_today = isset($_GET['from_today']) ? "WHERE date >= NOW()" : "";
    $results = $wpdb->get_results("SELECT * FROM $table $from_today ORDER BY date");

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

    echo "<form method='get' style='margin:1em 0;'>
        <label><input type='checkbox' name='from_today' onchange='this.form.submit()'" . (isset($_GET['from_today']) ? " checked" : "") . ">
        Show future entries only</label>
    </form>";

    echo "<div id='church_service_table_wrapper' style='overflow-x:auto;'>
        <table id='church_service_table' class='display' style='width:100%;'>
        <thead><tr>";

    foreach ($columns as $col) {
        $label = $labels[$col] ?? ucfirst($col);
        $class = ($col === 'date') ? 'class="sticky-date"' : '';
        echo "<th $class>" . esc_html($label) . "</th>";
    }

    echo "</tr></thead><tbody>";

    foreach ($results as $row) {
        echo "<tr>";
        foreach ($columns as $col) {
            $val = ($col === 'date') ? substr($row->$col, 0, 10) : $row->$col;

            if ($col === 'date' && $edit_mode) {
                $edit_url = add_query_arg(['edit_id' => $row->id, 'mode' => 'edit'], get_permalink());
                $show_url = add_query_arg(['edit_id' => $row->id, 'mode' => 'show'], get_permalink());
                $val = "<a href='" . esc_url($edit_url) . "' class='cs-date-link'>Bearbeiten</a> | <a href='" . esc_url($show_url) . "' class='cs-date-link'>Anzeigen</a> | " . esc_html(substr($row->date, 0, 10));
            }

            $class = $col === 'date' ? 'class="sticky-date"' : '';
            echo "<td $class>$val</td>";
        }
        echo "</tr>";
    }

    echo "</tbody></table></div>";
    echo "
    <style>
    /* 🌑 Dark Mode Table Styles */
    #church_service_table_wrapper {
        background-color: #1e1e1e;
        color: #ddd;
        padding: 10px;
        border-radius: 6px;
        overflow-x: auto;
    }

    #church_service_table {
        min-width: 1000px;
        border-collapse: separate;
        background-color: #1e1e1e;
        color: #ccc;
    }

    #church_service_table th,
    #church_service_table td {
        white-space: nowrap;
        padding: 6px 10px;
        text-align: left;
        border-bottom: 1px solid #333;
    }

    #church_service_table th {
        background-color: #2a2a2a;
        color: #eee;
    }

    #church_service_table tbody tr:hover {
        background-color: #2c2c2c;
    }

    /* Sticky Date Column */
    #church_service_table td.sticky-date,
    #church_service_table th.sticky-date {
        background-color: #252525 !important;
        color: #eee;
        position: sticky !important;
        left: 0;
        z-index: 3;
        box-shadow: 2px 0 4px rgba(0,0,0,0.5);
        font-weight: bold;
    }

    /* Link Style in Date Cell */
    .cs-date-link {
        color: #80b3ff;
        text-decoration: none;
    }

    .cs-date-link:hover {
        text-decoration: underline;
    }

    /* 🌑 Dark Form Design */
    .cs-tab-nav {
        margin-bottom: 10px;
        display: flex;
        flex-wrap: wrap;
    }
    .cs-tab-btn {
        padding: 6px 12px;
        border: none;
        background: #444;
        color: #ddd;
        margin-right: 5px;
        cursor: pointer;
        border-radius: 4px 4px 0 0;
        transition: background 0.2s ease;
        display: flex;
        align-items: center;
        font-size: 1em;
    }
    .cs-tab-btn:hover {
        background: #555;
    }
    .cs-tab-btn.active {
        background: #222;
        color: #fff;
        font-weight: bold;
    }
    .cs-tab-btn .tab-label-text {
        margin-left: 6px;
    }
    @media (max-width: 600px) {
        .cs-tab-btn .tab-label-text {
            display: none;
        }
        .cs-tab-btn {
            min-width: 40px;
            padding: 6px 8px;
            font-size: 1.3em;
        }
    }
    .cs-tab-content {
        padding: 12px;
        border: 1px solid #333;
        border-top: none;
        background: #1e1e1e;
        color: #ddd;
    }
    </style>

    <script>
    jQuery(document).ready(function($){
        $('#church_service_table').DataTable({
            autoWidth: false,
            scrollX: true,
            language: {
                url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/en-GB.json'
            }
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

    // Robust: edit_id aus GET oder POST holen
    $id = isset($_GET['edit_id']) ? intval($_GET['edit_id']) : (isset($_POST['edit_id']) ? intval($_POST['edit_id']) : 0);
    $edit_mode = $id > 0;
    $mode = isset($_GET['mode']) ? $_GET['mode'] : ($edit_mode ? 'edit' : 'new');
    $readonly = ($mode === 'show');
    $table = $wpdb->prefix . 'church_service_plan';

    $labels = church_service_get_labels();
    $groups = church_service_get_form_groups();
    $fields = array_merge(...array_values($groups));
    $data = array_fill_keys($fields, '');

    if (!$edit_mode) {
        $data['date'] = church_service_next_sunday();
    }

    if ($edit_mode) {
        $entry = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id), 'ARRAY_A');
        if ($entry) {
            $data = array_merge($data, $entry);
        }
    }
    $notice = '';
    // Nach dem Speichern/Upload: Redirect mit edit_id, damit Uploads immer angezeigt werden
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['church_service_form_save']) && !$readonly) {
        foreach ($fields as $field) {
            $data[$field] = sanitize_text_field($_POST[$field] ?? '');
        }
        unset($data['id']);
        if ($edit_mode) {
            $wpdb->update($table, $data, ['id' => $id]);
            $service_id = $id;
            $notice = '<div class="updated"><p><strong>Entry updated successfully.</strong></p></div>';
        } else {
            $wpdb->insert($table, $data);
            $service_id = $wpdb->insert_id;
            // Nach Neuanlage: Redirect auf Bearbeiten-Ansicht
            $redirect_url = add_query_arg(['edit_id' => $service_id, 'mode' => 'edit'], get_permalink());
            if (!headers_sent()) {
                wp_redirect($redirect_url);
                exit;
            } else {
                echo "<script>window.location='" . esc_url_raw($redirect_url) . "';</script>";
                exit;
            }
        }
        // Nach Update: Redirect, damit Uploads korrekt angezeigt werden
        $redirect_url = add_query_arg(['edit_id' => $id, 'mode' => 'edit'], get_permalink());
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
            for ($i = 0; $i < $upload_count; $i++) {
                if ($_FILES['church_service_uploads']['error'][$i] === UPLOAD_ERR_OK) {
                    $file_array = [
                        'name' => $_FILES['church_service_uploads']['name'][$i],
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
                                'file_name' => $_FILES['church_service_uploads']['name'][$i],
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
    <div class="wrap">
        <?php if (!empty($notice))
            echo $notice; ?>
        <h2><?php echo $edit_mode ? ($readonly ? 'Show Entry' : 'Edit Entry') : 'New Entry'; ?></h2>
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
                echo "<button type='button' class='cs-tab-btn' data-tab='$upload_tab_id'>"
                    . "<span class='tab-label-icon'>📁</span>"
                    . "<span class='tab-label-text'>Uploads</span>"
                    . "</button>";
                ?>
            </div>
            <?php
            $i = 0;
            foreach ($groups as $group_label => $group_fields) {
                $tab_id = 'tab-' . $i;
                $visible = ($i === 0) ? 'style="display:block;"' : 'style="display:none;"';
                echo "<div class='cs-tab-content' id='$tab_id' $visible>";
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
                        echo "<input type='text' id='$field' name='$field' value='$value' $readonly_attr>";
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
            echo "<form method='post' enctype='multipart/form-data' style='margin-top:1em;'>";
            if ($edit_mode) {
                echo "<input type='hidden' name='upload_service_id' value='" . intval($id) . "'>";
            }
            echo "<label><strong>Dateien hochladen (Team zuordnen):</strong><br>";
            echo "<select name='upload_team' required style='margin-bottom:8px;'>";
            foreach ($groups as $group_label => $group_fields) {
                $team_key = !empty($group_fields[0]) ? $group_fields[0] : '';
                if ($team_key) {
                    echo "<option value='" . esc_attr($team_key) . "'>" . esc_html($group_label) . "</option>";
                }
            }
            echo "</select> ";
            echo "<input type='file' name='church_service_uploads[]' multiple required></label> ";
            echo "<button type='submit' class='button button-primary' name='church_service_uploads_submit'>Dateien hochladen</button>";
            echo "</form>";
            echo "</div>";
            ?>
            <?php if ($edit_mode): ?>
                <input type="hidden" name="edit_id" value="<?php echo intval($id); ?>">
            <?php endif; ?>
            <p>
                <input type="submit" class="button button-primary" name="church_service_form_save"
                    value="<?php echo $readonly ? 'Dateien hochladen' : ($edit_mode ? 'Update' : 'Save'); ?>">
            </p>
        </form>
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

        wp_enqueue_style('datatables', 'https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css');
        wp_enqueue_script('datatables', 'https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js', ['jquery'], null, true);
    }
}

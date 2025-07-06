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

    $atts = shortcode_atts(['edit' => 'false'], $atts);
    $edit_mode = $atts['edit'] === 'true';

    $table = $wpdb->prefix . 'church_service_plan';
    $from_today = isset($_GET['from_today']) ? "WHERE date >= NOW()" : "";
    $results = $wpdb->get_results("SELECT * FROM $table $from_today ORDER BY date");

    if (!$results)
        return "<p>No entries found.</p>";

    $columns = array_keys((array) $results[0]);
    $columns = array_filter($columns, fn($col) => $col !== 'id');
    $labels = church_service_get_labels();

    $html = "<form method='get' style='margin:1em 0;'>
        <label><input type='checkbox' name='from_today' onchange='this.form.submit()'" . (isset($_GET['from_today']) ? " checked" : "") . ">
        Show future entries only</label>
    </form>";

    $html .= "<div id='church_service_table_wrapper' style='overflow-x:auto;'>
        <table id='church_service_table' class='display' style='width:100%;'>
        <thead><tr>";

    foreach ($columns as $col) {
        $label = $labels[$col] ?? ucfirst($col);
        $class = ($col === 'date') ? 'class="sticky-date"' : '';
        $html .= "<th $class>" . esc_html($label) . "</th>";
    }

    $html .= "</tr></thead><tbody>";

    foreach ($results as $row) {
        $html .= "<tr>";
        foreach ($columns as $col) {
            $val = ($col === 'date') ? substr($row->$col, 0, 10) : $row->$col;

            if ($col === 'date' && $edit_mode) {
                $edit_url = add_query_arg('edit_id', $row->id, get_permalink());
                $val = "<a href='" . esc_url($edit_url) . "' class='cs-date-link'>" . esc_html($val) . "</a>";
            }

            $class = $col === 'date' ? 'class="sticky-date"' : '';
            $html .= "<td $class>$val</td>";
        }
        $html .= "</tr>";
    }

    $html .= "</tbody></table></div>";
    $html .= "
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

    return $html;
}
add_shortcode('church_service_list', 'church_service_list_shortcode');

/* -------------------------------------------------
 * 📝 Frontend Form Shortcode
 * ------------------------------------------------- */
function church_service_form_shortcode($atts)
{
    global $wpdb;

    $id = isset($_GET['edit_id']) ? intval($_GET['edit_id']) : 0;
    $edit_mode = $id > 0;
    $table = $wpdb->prefix . 'church_service_plan';

    $labels = church_service_get_labels();
    $groups = church_service_get_form_groups();
    $fields = array_merge(...array_values($groups));
    $data = array_fill_keys($fields, '');

    if (!$edit_mode) {
        $data['date'] = church_service_next_sunday();
    }

    if ($edit_mode) {
        $entry = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id), ARRAY_A);
        if ($entry) {
            $data = array_merge($data, $entry);
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['church_service_form_save'])) {
        foreach ($fields as $field) {
            $data[$field] = sanitize_text_field($_POST[$field] ?? '');
        }

        unset($data['id']);

        if ($edit_mode) {
            $wpdb->update($table, $data, ['id' => $id]);
            echo '<div class="updated"><p><strong>Entry updated successfully.</strong></p></div>';
        } else {
            $wpdb->insert($table, $data);
            echo '<div class="updated"><p><strong>New entry saved successfully.</strong></p></div>';
        }
    }

    ob_start();
    ?>
    <div class="wrap">
        <h2><?php echo $edit_mode ? 'Edit Entry' : 'New Entry'; ?></h2>
        <form method="post" id="church_service_form">
            <div class="cs-tab-nav">
                <?php
                $i = 0;
                foreach (array_keys($groups) as $group_label) {
                    $active = $i === 0 ? 'active' : '';
                    echo "<button type='button' class='cs-tab-btn $active' data-tab='tab-$i'>" . esc_html($group_label) . "</button>";
                    $i++;
                }
                ?>
            </div>
            <?php
            $i = 0;
            foreach ($groups as $group_label => $group_fields) {
                $visible = ($i === 0) ? 'style="display:block;"' : 'style="display:none;"';
                echo "<div class='cs-tab-content' id='tab-$i' $visible>";
                foreach ($group_fields as $field) {
                    $label = $labels[$field] ?? ucfirst($field);
                    $value = esc_attr($data[$field] ?? '');
                    echo "<p><label for='$field'><strong>$label</strong><br>";

                    $team = church_service_get_team($field);
                    if (!empty($team)) {
                        $datalist_id = "list_$field";
                        echo "<input list='$datalist_id' id='$field' name='$field' value='$value'>";
                        echo "<datalist id='$datalist_id'>";
                        foreach ($team as $member) {
                            echo "<option value='" . esc_attr($member) . "'>";
                        }
                        echo "</datalist>";
                    } else {
                        echo "<input type='text' id='$field' name='$field' value='$value'>";
                    }

                    echo "</label></p>";
                }
                echo "</div>";
                $i++;
            }
            ?>

            <?php if ($edit_mode): ?>
                <input type="hidden" name="edit_id" value="<?php echo intval($id); ?>">
            <?php endif; ?>
            <p>
                <input type="submit" class="button button-primary" name="church_service_form_save"
                    value="<?php echo $edit_mode ? 'Update' : 'Save'; ?>">
            </p>
        </form>
    </div>
    <style>
        /* 🌑 Dark Form Design */
        .cs-tab-nav {
            margin-bottom: 10px;
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
        }

        .cs-tab-btn:hover {
            background: #555;
        }

        .cs-tab-btn.active {
            background: #222;
            color: #fff;
            font-weight: bold;
        }

        .cs-tab-content {
            padding: 12px;
            border: 1px solid #333;
            border-top: none;
            background: #1e1e1e;
            color: #ddd;
        }

        #church_service_form input[type="text"],
        #church_service_form input[list] {
            background-color: #2a2a2a;
            color: #f0f0f0;
            border: 1px solid #555;
            padding: 6px 10px;
            border-radius: 4px;
            width: 100%;
            max-width: 600px;
        }

        #church_service_form input[type="text"]:focus,
        #church_service_form input[list]:focus {
            border-color: #6699ff;
            outline: none;
            box-shadow: 0 0 3px rgba(102, 153, 255, 0.5);
        }

        #church_service_form input[type="submit"] {
            background-color: #2e7fd1;
            border: none;
            color: #fff;
            padding: 8px 16px;
            font-weight: bold;
            border-radius: 4px;
            cursor: pointer;
            margin-top: 10px;
        }

        #church_service_form input[type="submit"]:hover {
            background-color: #1c5ca9;
        }
    </style>

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

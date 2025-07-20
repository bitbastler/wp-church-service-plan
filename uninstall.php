<?php
// Sicherheitscheck – Plugin darf nur durch WordPress deinstalliert werden
if ( !defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Beispiel: Lösche eine eigene Tabelle (ersetze `wp_deine_tabelle` durch deinen tatsächlichen Tabellennamen)
global $wpdb;
$table_name = $wpdb->prefix . 'church_service_plan';
$wpdb->query( "DROP TABLE IF EXISTS $table_name" );

$table_name = $wpdb->prefix . 'church_service_uploads';
$wpdb->query( "DROP TABLE IF EXISTS $table_name" );

// Beispiel: Lösche Plugin-spezifische Optionen
// delete_option( 'mein_plugin_option_name' );
// delete_site_option( 'mein_plugin_option_name' ); // Für Multisite

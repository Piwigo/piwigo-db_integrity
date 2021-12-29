<?php /*
Plugin Name: RV DB Integrity
Version: 2.7.a
Description: Checks database integrity. After install go to Administration /Tools/Maintenance: "Check database integrity".
Plugin URI: http://piwigo.org/ext/extension_view.php?eid=179
Author: rvelices
Author URI: http://www.modusoptimus.com
Has Settings: false
*/

define('RVDI_DIR' , basename(dirname(__FILE__)));
define('RVDI_PATH' , PHPWG_PLUGINS_PATH . RVDI_DIR . '/');


add_event_handler('get_admin_advanced_features_links', 'rvint_get_admin_advanced_features_links');

function rvint_get_admin_advanced_features_links($advanced_features)
{
  load_language('plugin.lang', RVDI_PATH);
  $advanced_features[] = array(
      'CAPTION' => l10n('Check database integrity'),
      'URL' => get_admin_plugin_menu_link(dirname(__FILE__).'/check_db.php'),
      'ICON' => 'icon-database',
    );
  return $advanced_features;
}

?>
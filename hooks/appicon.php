//<?php

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	exit;
}

class vcgaming_hook_appicon extends _HOOK_CLASS_
{

/* !Hook Data - DO NOT REMOVE */
public static function hookData() {
 return array_merge_recursive( array (
  'appmenu' => 
  array (
    0 => 
    array (
      'selector' => '#acpAppList',
      'type' => 'add_before',
      'content' => '<style>
  #acpAppList [data-tab="tab_vcgaming"] i {
  }
  #acpAppList [data-tab="tab_vcgaming"] .acpAppList_icon {
    content: url({resource="vcgamingicon.png" app="vcgaming" location="admin"});
    width: 35px !important;
    height: 35px !important;
  }
</style>',
    ),
  ),
), parent::hookData() );
}
/* End Hook Data */


}

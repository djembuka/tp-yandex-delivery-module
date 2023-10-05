<?
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php");

$module_id = "twinpx.yadelivery";
if(IsModuleInstalled($module_id))
{
	//update js
	if (is_dir(dirname(__FILE__).'/install/js'))
		$updater->CopyFiles("install/js", "js/{$module_id}/");
	
	//update css	
	if (is_dir(dirname(__FILE__).'/install/css'))
		$updater->CopyFiles("install/css", "css/{$module_id}/");
	
}


if($updater->CanUpdateDatabase())
{
	if($updater->TableExists("b_twpx_yadelivery_offer_temp")) {
		$updater->Query(
			array("MySQL" => "ALTER TABLE `b_twpx_yadelivery_offer_temp` CHANGE `LOCATION` `LOCATION` VARCHAR(256) NULL DEFAULT NULL")		
		);
	}
	
}
?>
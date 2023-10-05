<?
use \Bitrix\Main\Application,
	\Bitrix\Main\EventManager,
	\Bitrix\Main\Loader,
	\Bitrix\Main\Context;

$module_id = 'twinpx.yadelivery';

$request = Context::getCurrent()->getRequest();
$session = Application::getInstance()->getSession();

IncludeModuleLangFile(__FILE__);

//D7
Loader::registerAutoLoadClasses(
    $module_id,
    array(
        "TwinpxDelivery"=> "classes/general/TwinpxDelivery.php", //���������� ��������
        "TwinpxEvent"   => "classes/general/TwinpxEvent.php" //�������
    )
);

//������������ ����� � ������� ��� ������
$arJsConfig = array(
    'twinpx_lib'	=> array(
        'js'  		=> '/bitrix/js/'.$module_id.'/script.js',
        'css' 		=> '/bitrix/css/'.$module_id.'/style.css',
        'lang'		=> '/bitrix/modules/'.$module_id.'/lang/'.LANGUAGE_ID.'/js/js_script.php',
        'rel'   	=> array('jquery2'),
    ),
    'twinpx_admin_lib'=> array(
        'js'  		=> '/bitrix/js/'.$module_id.'/admin/script.js',
        'css' 		=> '/bitrix/css/'.$module_id.'/admin/style.css',
        'lang'		=> '/bitrix/modules/'.$module_id.'/lang/'.LANGUAGE_ID.'/js/admin/js_script.php',
        'rel'   	=> array('jquery2')
    ),
    'twinpx_schedule_chunk' => array(
        'js'		=> '/bitrix/js/'.$module_id.'/admin/chunk-vendors.93f85daa.js'
    ),
    'twinpx_schedule_app' => array(
        'js'		=> '/bitrix/js/'.$module_id.'/admin/app.ef1751b5.js',
        'css'      	=> '/bitrix/css/'.$module_id.'/admin/app.e8ca5091.css',
        'rel'      	=> array('twinpx_schedule_chunk'),
        'skip_core'	=> TRUE
    )
);
foreach ($arJsConfig as $ext => $arExt) {
    CJSCore::RegisterExt($ext, $arExt); //��������� �����������
}

//�������
EventManager::getInstance()->addEventHandler("sale", "OnSaleOrderBeforeSaved",  array("TwinpxEvent","OnSaleOrderBeforeSaved")); //����� ���������� ������
EventManager::getInstance()->addEventHandler("main", "OnAdminContextMenuShow", array("TwinpxEvent","OnAdminContextMenuShow")); //������ � ���� ������
//EventManager::getInstance()->addEventHandler('sale', 'OnSaleDeliveryOrder', array("TwinpxEvent", "OnSaleDeliveryOrder"));  //����� ���������� ��������
EventManager::getInstance()->addEventHandler('sale', 'OnSalePayOrder', array("TwinpxEvent", "OnSaleOnSalePayOrder"));  //����� ������


Class CTwinpxYadelivery
{
    function __construct()
    {
		//
    }

    function __destruct()
    {
    	//
    }
}
?>

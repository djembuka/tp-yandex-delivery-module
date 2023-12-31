<?
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php");
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php");

//require_once $_SERVER['DOCUMENT_ROOT'].' / bitrix / modules / main / include / prolog_admin.php';
//require_once $_SERVER['DOCUMENT_ROOT'].' / bitrix / modules / twpx.yadelivery / include.php';

use Twinpx\Yadelivery\TwinpxOfferTable;

use Bitrix\Main\Context, 
Bitrix\Main\Request,
Bitrix\Main\UI\PageNavigation,
Bitrix\Iblock\PropertyEnumerationTable,
Bitrix\Main\Grid\Options as GridOptions;

global $APPLICATION, $USER;

$module_id = 'twinpx.yadelivery';

$module_id = 'twinpx.yadelivery';
$MODULE_RIGHT = $APPLICATION->GetGroupRight($module_id);
if ($MODULE_RIGHT < "R") {
	$optionsNotSetMessage = new CAdminMessage([
        'MESSAGE' => GetMessage("TWINPX_ACCESS_DENIED"), 
        'TYPE' => 'ERROR', 
        'DETAILS' => GetMessage("TWINPX_ACCESS_DENIED"), 
        'HTML' => true
    ]);
    echo $optionsNotSetMessage->Show();
    
    return;
}

CUtil::InitJSCore( array('jquery2', 'ajax' ,'popup', 'twinpx_admin_lib'));

$APPLICATION->SetTitle(GetMessage("TWINPX_ARHIVNYE_ZAAVKI"));
$APPLICATION->SetAdditionalCSS('/bitrix/css/main/grid/webform-button.css');

IncludeModuleLangFile(__FILE__);

$isRequiredOptionsSet = false;
if (!$isRequiredOptionsSet)
{
    $optionsNotSetMessage = new CAdminMessage([
        'MESSAGE' => GetMessage("TWINPX_SOOBSENIE"), 
        'TYPE' => 'ERROR', 
        'DETAILS' => GetMessage("TWINPX_PODROBNOSTQ_PROBLEMY"), 
        'HTML' => true
    ]);
    //echo $optionsNotSetMessage->Show();
}	

$request = Context::getCurrent()->getRequest();
if($request["op"] == 'delete' AND $request["id"] > 0) {
    //������� ������ �� ������� � ������ ��������
    TwinpxOfferTable::delete($request["id"]);
}

//����� �������
$list_id   = 'offers_list_archive';
$grid_options = new Bitrix\Main\Grid\Options($list_id);
$sort         = $grid_options->GetSorting(['sort' => ['ID' => 'DESC'], 'vars' => ['by' => 'by', 'order' => 'order']]);
$nav_params   = $grid_options->GetNavParams();

$nav          = new PageNavigation($list_id);
$nav->allowAllRecords(true)->setPageSize($nav_params['nPageSize'])->initFromUri();

//$filterOption = new Bitrix\Main\UI\Filter\Options($list_id);
//$filterData   = $filterOption->getFilter([]);
$filter       = ['DIVIDE' => 2];

//�������� ������ �� ��
$res = TwinpxOfferTable::getList([
    'filter'     => $filter,
    'select'     => ["*"],
    'offset'    => $nav->getOffset(),
    'limit'     => $nav->getLimit(),
    'order'     => $sort['sort']
]);
foreach ($res->fetchAll() as $row) {
    $actions = array();
	if($MODULE_RIGHT > "R") {
		$actions = array(
            array(
                'text'    => GetMessage("TWINPX_UDALITQ"),
                'default' => false,
                'class'    => 'icon remove',
                'onclick' => 'if(confirm("'.GetMessage("TWINPX_TOCNO").'")){document.location.href="?lang='.SITE_ID.'&op=delete&id='.$row['ID'].'"}'
            )
        );
	}
    $list[] = array(
        'data' => array(
            "ID" => $row['ID'],
            "ORDER_ID" => '<a href="/bitrix/admin/sale_order_view.php?lang=ru&ID='.$row['ORDER_ID'].'">'.$row['ORDER_ID'].'</a>',
            "REQUEST_ID" => $row['REQUEST_ID'],
            "ORDER_DATE" => $row['ORDER_DATE'],
            "ADDRESS" => $row['ADDRESS'],
            "DELIVERY_INTERVAL" => $row['DELIVERY_INTERVAL'],
            "STATUS_DESCRIPTION" => $row['STATUS_DESCRIPTION'],
            //"DIVIDE" => $row['DIVIDE']
        ),
        'actions' => $actions
    );
}

$ui_filter = array(
    array('id' => 'ID', 'name' => 'ID', 'type'=>'number', 'default' => true),
    array('id' => 'ORDER_ID', 'name' => GetMessage("TWINPX_NOMER_ZAKAZA"), 'type'=>'number', 'default' => true),
    array('id' => 'DIVIDE', 'name' => GetMessage("TWINPX_RAZDEL"), 'type'=>'list', 'items' => array('' => GetMessage("TWINPXY_LUBOY"), '1' => '1', '2' => '2'), 'params' => array('multiple' => 'N'))
);
?>
<div>
    <?/*$APPLICATION->IncludeComponent('bitrix:main.ui.filter', '', [
        'FILTER_ID' => $list_id,
        'GRID_ID' => $list_id,
        'FILTER' => $ui_filter,
        'ENABLE_LIVE_SEARCH' => true,
        'ENABLE_LABEL' => true
    ]);*/?>
</div>
<div style="clear: both;"></div>
<?
$APPLICATION->IncludeComponent('bitrix:main.ui.grid', '.default', array(
    'GRID_ID'                  	=> $list_id,
    'ROWS'                     	=> $list,
    'COLUMNS'                  	=> array(
        array('id' => 'ID', 'name' => 'ID', 'sort' => 'ID', 'default' => true),
        array('id' => 'ORDER_ID', 'name' => GetMessage("TWINPX_NOMER_ZAKAZA"), 'sort' => 'ORDER_ID', 'default' => true),
        array('id' => 'REQUEST_ID', 'name' => GetMessage("TWINPX_NOMER_ZAAVKI"), 'sort' => 'REQUEST_ID', 'default' => true),
        array('id' => 'ORDER_DATE', 'name' => GetMessage("TWINPX_DATA_ZAAVKI"), 'sort' => 'ORDER_DATE', 'type' => 'custom', 'default' => true),
        array('id' => 'ADDRESS', 'name' => GetMessage("TWINPX_ADRES_DOSTAVKI"),'default' => true),
//        array('id' => 'DELIVERY_INTERVAL', 'name' => GetMessage("TWINPX_INTERVAL_DOSTAVKI"), 'default' => true),
        array('id' => 'STATUS_DESCRIPTION', 'name' => GetMessage("TWINPX_STATUS"), 'default' => true),
        //array('id' => 'DIVIDE', 'name' => '������', 'sort' => 'DIVIDE', 'default' => true)
    ),
    /*'HEADERS_SECTIONS' => array (
    array ('id' => 'ALL', 'name' => '���', 'default' => true, 'selected' => true),
    array ('id' => 'ARHIVE', 'name' => '�����', 'selected' => true),
    ),*/
    'PAGE_SIZES'               	=>  array(
        array('NAME' => '20', 'VALUE' => '20'),
        array('NAME' => '50', 'VALUE' => '50'),
        array('NAME' => '100', 'VALUE' => '100'),
        array('NAME' => '200', 'VALUE' => '200')
    ),
    'AJAX_MODE'                	=> 'Y',
    'AJAX_ID'                  	=> \CAjax::getComponentID('bitrix:main.ui.grid', '.default', ''),
    'AJAX_OPTION_JUMP'         	=> 'N',
    'AJAX_OPTION_HISTORY'      	=> 'N',
    'SHOW_CHECK_ALL_CHECKBOXES'	=> false,
    'SHOW_ROW_ACTIONS_MENU'    	=> true,
    'SHOW_GRID_SETTINGS_MENU'  	=> true,
    'SHOW_NAVIGATION_PANEL'    	=> true,
    'SHOW_PAGINATION'         	=> true,
    'SHOW_SELECTED_COUNTER'    	=> true,
    'SHOW_TOTAL_COUNTER'       	=> true,
    'SHOW_PAGESIZE'            	=> true,
    'SHOW_ACTION_PANEL'        	=> true,
    'ALLOW_COLUMNS_SORT'       	=> true,
    'ALLOW_COLUMNS_RESIZE'     	=> true,
    'ALLOW_HORIZONTAL_SCROLL'  	=> true,
    'ALLOW_SORT'               	=> true,
    'ALLOW_PIN_HEADER'         	=> true,
    'ENABLE_COLLAPSIBLE_ROWS'	=> true,
    'NAV_OBJECT'               	=> $nav,
    'TOTAL_ROWS_COUNT'         	=> $nav->getRecordCount(),
    'SHOW_ROW_CHECKBOXES'		=> false, //�������� ��������, ���������, ������ �� ACTION_PANEL
    /*'ACTION_PANEL'              => [
    'GROUPS' => [
    'TYPE' => [
    'ITEMS' => [
    [
    'ID' => 'actions', 
    'TYPE'  => 'DROPDOWN', 
    'ITEMS' => [
    ['VALUE' => '', 'NAME' => '- ������� -'],
    ['VALUE' => 'plus', 'NAME' => '� �����'],
    ['VALUE' => 'minus', 'NAME' => '�������']
    ]
    ],
    [
    'ID'       => 'delete',
    'TYPE'     => 'BUTTON',
    'TEXT'     => '�������',
    'CLASS'    => 'icon remove',
    'ONCHANGE' => 'if(confirm("�����?")){document.location.href="?op=delete"}'
    ],
    ],
    ],
    ],
    ], */
));

?>
<script>
    var ajaxURL = '/bitrix/admin/twinpx_delivery_ajax.php';
</script>
<?

require $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_admin.php';

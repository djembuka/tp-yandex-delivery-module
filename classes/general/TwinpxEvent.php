<?php
use	\Bitrix\Main,
	\Bitrix\Main\Entity,
	\Bitrix\Main\Loader,
	\Bitrix\Main\Context,
	\Bitrix\Main\Application,
	\Bitrix\Main\Localization\Loc,
	\Bitrix\Sale,
	\Bitrix\Sale\Delivery,
	\Bitrix\Sale\Delivery\Services\Table;
	
use \Twinpx\Yadelivery\TwinpxApi,
	\Twinpx\Yadelivery\TwinpxOfferTable,
	\Twinpx\Yadelivery\TwinpxOfferTempTable;

Loader::includeModule("sale");

IncludeModuleLangFile(__FILE__);

class TwinpxEvent
{
	static $module_id = 'twinpx.yadelivery';
    
    public static function OnSaleOrderBeforeSaved(\Bitrix\Main\Event $event)
    {
        $thisData           = time();
        $error 				= array();
        $deliveryInfo 		= array();
        $request            = Context::getCurrent()->getRequest();
        $session            = Application::getInstance()->getSession();

        $delivery_id     	= $request->getPost("DELIVERY_ID"); //выбранная доставка
//        $order              = $event->getParameter("ENTITY");
//        $propertyCollection = $order->getPropertyCollection();
        
        //проверяем если наша доставка
        if($delivery_id){			
	        $arDelivery = TwinpxDelivery::ChechDelivery($delivery_id);
	        if(!empty($arDelivery)) {
				$deliveryInfo = $arDelivery[$delivery_id];
			}
		}
				
        //если есть наша доставка
        if(!empty($deliveryInfo)) 
        {
        	if($deliveryInfo['CODE'] == 'twpx_yadelivery:curier_simple')
        	{
        		if($session->get('CURIER_SIMPLE_NOTCALCULATE') == 1) {
					$error[] = Loc::GetMessage('Price-Nocalculate');
				} 
				else {
        			return;
				}
			}
        	elseif($deliveryInfo['CODE'] == 'twpx_yadelivery:pickup_simple') 
        	{
				if($session->get('PVZ_ID_SIMPLE') == '' || !$session->has('PVZ_ID_SIMPLE')){
					$error[] = Loc::GetMessage('Select-Pickup');
				}
				elseif($session->get('PICKUP_SIMPLE_NOTCALCULATE') == 1){
					$error[] = Loc::GetMessage('Price-Nocalculate');
				}
				else {
					return;
				}
			}
        	//если цена не задана
			elseif( $session->get('CURIERPRICE') == '' && $session->get('PVZPRICE') == '') 
			{
				$error[] = Loc::GetMessage('Select-Interval');
			}
			//проверка если данные не менялсь
			elseif(!$session->has('OFFER_ID') || $session->get('OFFER_ID') == '')
			{
				$error[] = ($deliveryInfo['CODE'] == 'twpx_yadelivery:curier') ? Loc::GetMessage('Changes-Property-Curier') : Loc::GetMessage('Changes-Property-Pickup');
			}
        	//проверяем время жизни оффера
        	elseif($session->has('OFFER_EXPIRE')) 
        	{
				$expire = strtotime($session->get('OFFER_EXPIRE'));
				if($expire < $thisData) {
					$error[] = Loc::GetMessage('Offer-Expire');
					$session->remove('OFFER_EXPIRE');
					$session->remove('OFFER_ID');
				} 
			} 
			//если нет сессий тоже выдем ошибку
			else {
				$error[] = Loc::GetMessage('Offer-Expire');
			}
    
			// выполняем код перед сохранением, но после проверки полей
			if (!empty($error)) {
				return new \Bitrix\Main\EventResult(Main\EventResult::ERROR, Sale\ResultError::create(new Main\Error($error, "GRAIN_IMFAST")));
			} 
			else {
				return;
			}
		} 
		else {
			return;	
		}
    }  
	
	//remove on next
	public static function OnSaleDeliveryOrder($id, $value)
	{	
		if($value == 'Y') {
			$res = TwinpxOfferTable::getList(array(
	            'select' => array('ID'),
	            'filter' => array('ORDER_ID'=> $id)
	        ));
	        if ($ar = $res->Fetch()) {
				TwinpxOfferTable::update($ar['ID'], array('PAYCONFIRM' => 1)); //подверждаем заказ
	        }
		}	
	}
	
	//изменение оплаты
	public static function OnSaleOnSalePayOrder($id, $value)
	{	
		if($value == 'Y') {
			$res = TwinpxOfferTempTable::getList(array(
	            'select' => array('ID'),
	            'filter' => array('ORDER_ID'=>$id)
	        ));
	        if ($ar = $res->Fetch()) {
				TwinpxOfferTempTable::update($ar['ID'], array('PAYCONFIRM' => 1)); //подверждаем заказ
	        }
		}	
	}

	public static function OnAdminContextMenuShow(&$items)
	{
	    global $module_id;
	    global $APPLICATION;
	    CUtil::InitJSCore( array('twinpx_admin_lib')); //подключаем стили и скрипты
	    //add custom button to the index page toolbar
	    if($GLOBALS["APPLICATION"]->GetCurPage(true) == "/bitrix/admin/sale_order_view.php" && intval($_REQUEST['ID']) > 0) {
	        $items[] = array(
	        	"TEXT" => GetMessage('TWINPX_YADELIVERY'), 
	        	"ICON" => "twinpx_icon",
	        	"MENU" => array(
	        		array(
	        			"TEXT" => GetMessage('TWINPX_CURIER'), 
	        			"LINK" => "javascript:void(0)",
	        			"ONCLICK" => "newDelivery({$_REQUEST['ID']})",
	        		),
	        		array(
	        			"TEXT" => GetMessage('TWINPX_PICKUP'), 
	        			"LINK" => "javascript:void(0)",
	        			"ONCLICK" => "newDeliveryPvz({$_REQUEST['ID']})",
	        		),
	        	),
	        );
			//\Bitrix\Main\UI\Extension::load("ui.buttons"); 
			$request     = Main\Application::getInstance()->getContext()->getRequest();
	        $scheme = $request->isHttps() ? 'https' : 'http';
			$ya_key = htmlspecialcharsbx(\Bitrix\Main\Config\Option::get('fileman', 'yandex_map_api_key', ''));
			$APPLICATION->AddHeadString('<script src="'.$scheme.'://api-maps.yandex.ru/2.1.50/?apikey='.$ya_key.'&load=package.full&lang=ru-RU"></script>', true); //подключаем карту
	        $session = Application::getInstance()->getSession();
	        $session->remove('CURIER_PRICE');
	        $session->remove('PICKUP_PRICE');
	        ?>
			<script>
			    window.twinpxYadeliveryFetchURL = '/bitrix/tools/<?=self::$module_id?>/admin/ajax.php';
			</script>
			<?
		}
		
	}
}
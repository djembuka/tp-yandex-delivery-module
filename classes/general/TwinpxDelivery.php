<?php
use \Bitrix\Main, 
	\Bitrix\Main\Context, 
	\Bitrix\Main\Entity, 
	\Bitrix\Main\Application,
	\Bitrix\Main\Localization\Loc,
	\Bitrix\Sale, 
	\Bitrix\Sale\Delivery,
	\Bitrix\Sale\Delivery\Services\Table;
use \Twinpx\Yadelivery\TwinpxApi, 
	\Twinpx\Yadelivery\TwinpxConfigTable, 
	\Twinpx\Yadelivery\TwinpxOfferTable,
	\Twinpx\Yadelivery\TwinpxTimezoneTable,
	\Twinpx\Yadelivery\TwinpxOfferTempTable;
	
IncludeModuleLangFile(__FILE__);

class TwinpxDelivery
{
	static $module_id = 'twinpx.yadelivery';
	
	function Init() 
	{
		return array(
			"SID" => 'twpx_yadelivery', // unique string identifier
			"NAME" => Loc::getMessage('Name'), // services public title
			"DESCRIPTION" => Loc::getMessage('Description'), // services public dedcription
			"DESCRIPTION_INNER" => '', // services private description for admin panel
			"BASE_CURRENCY" => COption::GetOptionString("sale", "default_currency", "RUB"), // services base currency
			"HANDLER" => __FILE__, // services path
			"COMPABILITY" => array("TwinpxDelivery", "Compability"),
			"CALCULATOR" => array("TwinpxDelivery", "Calculate"),
			"DBGETSETTINGS" => array("TwinpxDelivery", "GetSettings"),
			"DBSETSETTINGS" => array("TwinpxDelivery", "SetSettings"),
			"GETCONFIG" => array("TwinpxDelivery", "GetConfig"),
			"PROFILES" => array(
				"curier" => array(
					"TITLE" => Loc::getMessage('Curier-Title'),
					"DESCRIPTION" => Loc::getMessage('Curier-Description'),
					"RESTRICTIONS_WEIGHT" => array(0),
					"RESTRICTIONS_SUM" => array(0),
				),
				"pickup" => array(
					"TITLE" => Loc::getMessage('Pickup-Title'),
					"DESCRIPTION" => Loc::getMessage('Pickup-Description'),
					"RESTRICTIONS_WEIGHT" => array(0),
					"RESTRICTIONS_SUM" => array(0),
				),
				"curier_simple" => array(
					"TITLE" => Loc::getMessage('Curier-Simple-Title'),
					"DESCRIPTION" => Loc::getMessage('Curier-Simple-Description'),
					"RESTRICTIONS_WEIGHT" => array(0),
					"RESTRICTIONS_SUM" => array(0),
				),
				"pickup_simple" => array(
					"TITLE" => Loc::getMessage('Pickup-Simple-Title'),
					"DESCRIPTION" => Loc::getMessage('Pickup-Simple-Description'),
					"RESTRICTIONS_WEIGHT" => array(0),
					"RESTRICTIONS_SUM" => array(0),
				)
			)
		);
	}
	
	function SetSettings($arSettings)
	{
		// �������� ������ �������� ���������. ������ �������� ������ �� ������.
		foreach ($arSettings as $key => $value)
		{
			if (strlen($value) > 0)
			{
				$arSettings[$key] = doubleval($value);
			}
			else
			{
				unset($arSettings[$key]);
			}
		}
		
		// ������ �������� � ���� ���������������� �������.
		// � ������ ����� �������� ������ �������� ����� ��������� ����� ������� ������ ������������.
		return serialize($arSettings);
	}
	
	function GetSettings($strSettings)
	{
		return unserialize($strSettings);
	}
	
	// ��������� �����������
	function GetConfig()
	{
		//��������� � ����������� �������
        $rsDelivery = \Bitrix\Sale\Delivery\Services\Table::getList(array('filter' => array('ACTIVE' => 'Y', '=CODE' => 'twpx_yadelivery', 'LOGOTIP' => false),'select' => array('LOGOTIP', 'ID')));
        while ($delivery = $rsDelivery->fetch()) {
            if ($delivery['LOGOTIP'] == false) {
                $path   = $_SERVER["DOCUMENT_ROOT"]."/bitrix/images/".static::$module_id."/yandex_delivery_logo.png";
                $arFile = CFile::MakeFileArray($path);
                $fid    = CFile::SaveFile($arFile, "sale");
                \Bitrix\Sale\Delivery\Services\Table::update($delivery['ID'], array("LOGOTIP"=> $fid)); //���������� ����
            }
        }
				
		$arConfig = array(
			'CONFIG_GROUPS' => array(
				'twpx_yadelivery' => Loc::getMessage('Setting'),
				'curier' => Loc::getMessage('Setting'),
				'pickup' => Loc::getMessage('Setting'),
				'curier_simple' => Loc::getMessage('Setting'),
				'pickup_simple' => Loc::getMessage('Setting')
			),
			'CONFIG' => array(
				//����� ���������
				'HEADER_API_SETTINGS' => array(
					'TYPE' => 'SECTION',
					'TITLE' => '<a href="/bitrix/admin/settings.php?mid=twinpx.yadelivery&mid_menu=1" target="_blank">'.Loc::getMessage('API-Setting').'</a>',
					'GROUP' => 'twpx_yadelivery'
				),
				'DELIVERY_PRICE' => array(
					"TYPE" => "STRING",
					"DEFAULT" => "",
					"TITLE" => Loc::getMessage('Delivery-Price'),
					"GROUP" => "twpx_yadelivery",
				),
				//��������� ��� �������
				'CURIER_PRICE' => array(
					"TYPE" => "STRING",
					"DEFAULT" => "",
					"TITLE" => Loc::getMessage('Delivery-Price'),
					"GROUP" => "curier",
				),
				//��������� ��� ���
				'PICKUP_PRICE' => array(
					"TYPE" => "STRING",
					"DEFAULT" => "",
					"TITLE" => Loc::getMessage('Delivery-Price'),
					"GROUP" => "pickup",
				),
				//��������� ��� ������� (����������)
				'CURIER_SIMPLE_PRICE' => array(
					"TYPE" => "STRING",
					"DEFAULT" => "",
					"TITLE" => Loc::getMessage('Delivery-Price'),
					"GROUP" => "curier_simple",
				),
				//��������� ��� ��� (����������)
				'PICKUP_SIMPLE_PRICE' => array(
					"TYPE" => "STRING",
					"DEFAULT" => "",
					"TITLE" => Loc::getMessage('Delivery-Price'),
					"GROUP" => "pickup_simple",
				),
			)
		);
		return $arConfig;
	}
	
	// ����� �������� ������������� � ������ ������ ����������� ���������� �������� ���������
	function Compability($arOrder, $arConfig)
	{
		// �������� ������� ��������� ��������
		$result = self::__CheckCompatible($arOrder, $arConfig);
		$profile = array();
		if ($result === FALSE)
		{
			//return array(); // ���� ��������� �� �������, ������ ������ ������ - �� �������� �� ���� �������
		}
		else
		{
//			if( (strlen($arConfig['CURIER_PRICE']['VALUE']) > 0) || (strlen($arConfig['DELIVERY_PRICE']['VALUE']) > 0) ){
				$profile[] = 'curier';
//			}
//			if( (strlen($arConfig['PICKUP_PRICE']['VALUE']) > 0) || (strlen($arConfig['DELIVERY_PRICE']['VALUE']) > 0) ){
				$profile[] = 'pickup';
//			}
	
			//������ ���� ������ ���. ����
//			if( (strlen($arConfig['CURIER_SIMPLE_PRICE']['VALUE']) > 0) || (strlen($arConfig['DELIVERY_PRICE']['VALUE']) > 0) ){
				$profile[] = 'curier_simple';
//			}
			
//			if( (strlen($arConfig['PICKUP_SIMPLE_PRICE']['VALUE']) > 0) || (strlen($arConfig['DELIVERY_PRICE']['VALUE']) > 0) ){
				$profile[] = 'pickup_simple';
//			}
			
//			return array('curier', 'pickup'); // � ��������� ������ ������ ������, ���������� ������������� ������������� ������� ��������
		}
		
		return $profile;
	}
	
	// ����������, ������� ���������
    function Calculate($profile, $arConfig, $arOrder, $STEP, $TEMP = false)
    {
        $request = Context::getCurrent()->getRequest();
        $session = Application::getInstance()->getSession();

		//��������� ���� �� ��������� ����� 
        if ($session->get('LOCATION') != $arOrder["LOCATION_TO"]) {
        	//���� �������� ��������������� ���������� ��� ����
            $session->remove('SETPRICE');
            $session->remove('TIMEZONE');
            $session->remove('CURIERPRICE');
            $session->remove('PVZPRICE');
        }
        $session->set('LOCATION', $arOrder["LOCATION_TO"]); //���������� ������ �������
		$session->remove('CURIER_SIMPLE_NOTCALCULATE');
		$session->remove('PICKUP_SIMPLE_NOTCALCULATE');
		    
        switch($profile){
			case 'curier':
				$session->remove('CURIER_PRICE');
		        //���� ��� �������
		        // ��������� ����� �������� �������� ����, ��� ���������� �������������� �� ����� ������������ �� ��������.
		        if(
		        	(strlen($arConfig['CURIER_PRICE']['VALUE']) > 0) ||
		        	(strlen($arConfig['DELIVERY_PRICE']['VALUE']) > 0)
		        ) 
		        {
		        	if( floatval($arConfig['CURIER_PRICE']['VALUE']) >= 0 && strlen($arConfig['CURIER_PRICE']['VALUE']) > 0){
						$price = floatval($arConfig['CURIER_PRICE']['VALUE']);
					}
					elseif(floatval($arConfig['DELIVERY_PRICE']['VALUE']) >= 0 && strlen($arConfig['DELIVERY_PRICE']['VALUE']) > 0) {
						$price = floatval($arConfig['DELIVERY_PRICE']['VALUE']);
					} 
					else {
						$price = 0;
					}
		        	
					$session->set('CURIER_PRICE', $price);
					return array(
		                "RESULT" => "OK",
		                "VALUE"  => $price,
		                "TRANSIT"=> ''
		            );
				}
		        elseif ($session->has('CURIERPRICE') && $session->get('CURIERPRICE') != '') {
		            return array(
		                "RESULT" => "OK",
		                "VALUE"  => $session->get('CURIERPRICE'),
		                "TRANSIT"=> ''
		            );
		        }
		        else {
		            return array(
		                "RESULT"=> "NEXT_STEP",
		                "TEXT"  => Loc::getMessage('Curier-Error-Message'),
		                "TEMP"  => $TEMP,
		            );
		        }
		        
				break;
				
			case 'pickup':
				$session->remove('PICKUP_PRICE');
		        //���� ��� �������
		        // ��������� ����� �������� �������� ����, ��� ���������� �������������� �� ����� ������������ �� ��������.
		        if(
		        	(strlen($arConfig['PICKUP_PRICE']['VALUE']) > 0) ||
		        	(strlen($arConfig['DELIVERY_PRICE']['VALUE']) > 0)
		        ) 
		        {
		        	if( floatval($arConfig['PICKUP_PRICE']['VALUE']) >= 0 && strlen($arConfig['PICKUP_PRICE']['VALUE']) > 0){
						$price = floatval($arConfig['PICKUP_PRICE']['VALUE']);
					}
					elseif(floatval($arConfig['DELIVERY_PRICE']['VALUE']) >= 0 && strlen($arConfig['DELIVERY_PRICE']['VALUE']) > 0) {
						$price = floatval($arConfig['DELIVERY_PRICE']['VALUE']);
					} 
					else {
						$price = 0;
					}
					
		        	$session->set('PICKUP_PRICE', $price);
					return array(
		                "RESULT" => "OK",
		                "VALUE"  => $price,
		                "TRANSIT"=> ''
		            );
				}
		        elseif ($session->has('PVZPRICE') && $session->get('PVZPRICE') != '') {
		            return array(
		                "RESULT" => "OK",
		                "VALUE"  => $session->get('PVZPRICE'),
		                "TRANSIT"=> ''
		            );
		        }
		        else {
		            return array(
		                "RESULT"=> "NEXT_STEP",
		                "TEXT"  => Loc::getMessage('Pickup-Error-Message'),
		                "TEMP"  => $TEMP
		            );
		        }
		        
				break;
				
			case 'curier_simple':
				$session->remove('CURIER_SIMPLE_PRICE');
				$price = self::__GetLocationPrice($arOrder, $type = 'time_interval');
				
				if(
		        	(strlen($arConfig['CURIER_SIMPLE_PRICE']['VALUE']) > 0) ||
		        	(strlen($arConfig['DELIVERY_PRICE']['VALUE']) > 0)
		        )
		        {
					if( floatval($arConfig['CURIER_SIMPLE_PRICE']['VALUE']) >= 0 && strlen($arConfig['CURIER_SIMPLE_PRICE']['VALUE']) > 0){
						$price = floatval($arConfig['CURIER_SIMPLE_PRICE']['VALUE']);
					}
					elseif(floatval($arConfig['DELIVERY_PRICE']['VALUE']) >= 0 && strlen($arConfig['DELIVERY_PRICE']['VALUE']) > 0) {
						$price = floatval($arConfig['DELIVERY_PRICE']['VALUE']);
					} 
					else {
						$price = 0;
					}
		        	
					$session->set('CURIER_SIMPLE_PRICE', $price);
					return array(
		                "RESULT" => "OK",
		                "VALUE"  => $price,
		                "TRANSIT"=> ''
		            );
				}
				elseif($price){
					return array(
		                "RESULT" => "OK",
		                "VALUE"  => $price,
		                "TRANSIT"=> ''
		            );
				}
				else {
					$session->set('CURIER_SIMPLE_NOTCALCULATE', 1);
					return array(
		                "RESULT"=> "NEXT_STEP",
		                "TEXT"  => Loc::getMessage('Curier-Error-Message'),
		                "TEMP"  => $TEMP,
		            );
				}
			
				break;
				
			case 'pickup_simple':
				$session->remove('PICKUP_SIMPLE_PRICE');
				$price = self::__GetLocationPrice($arOrder, $type = 'self_pickup');
				if(
		        	(strlen($arConfig['PICKUP_SIMPLE_PRICE']['VALUE']) > 0) ||
		        	(strlen($arConfig['DELIVERY_PRICE']['VALUE']) > 0)
		        )
		        {
					if( floatval($arConfig['PICKUP_SIMPLE_PRICE']['VALUE']) >= 0 && strlen($arConfig['PICKUP_SIMPLE_PRICE']['VALUE']) > 0){
						$price = floatval($arConfig['PICKUP_SIMPLE_PRICE']['VALUE']);
					}
					elseif(floatval($arConfig['DELIVERY_PRICE']['VALUE']) >= 0 && strlen($arConfig['DELIVERY_PRICE']['VALUE']) > 0) {
						$price = floatval($arConfig['DELIVERY_PRICE']['VALUE']);
					} 
					else {
						$price = 0;
					}
		        	
					$session->set('PICKUP_SIMPLE_PRICE', $price);
					return array(
		                "RESULT" => "OK",
		                "VALUE"  => $price,
		                "TRANSIT"=> ''
		            );
				} 
				elseif($price){
					return array(
		                "RESULT" => "OK",
		                "VALUE"  => $price,
		                "TRANSIT"=> ''
		            );
				}
				else {
					$session->set('PICKUP_SIMPLE_NOTCALCULATE', 1);
					return array(
		                "RESULT"=> "NEXT_STEP",
		                "TEXT"  => Loc::getMessage('Pickup-Error-Message'),
		                "TEMP"  => $TEMP,
		            );
				}
			
				break;
				
			default:
				break;
		}
        
    }
	
	// ������ ��������� �����, ������������ ������ �������������� � ������������ ��������� ��� ���� ������.
	function __GetLocationPrice($arOrder, $type)
	{
		// ������� ������ ����� ��� ����������� ��������������
		$result = FALSE;
		$dbLocationGroups = CSaleLocation::GetByID(array("ID" => $arOrder["LOCATION_TO"]));
		if($dbLocationGroups){
			$city = ($dbLocationGroups['CITY_NAME']) ? $dbLocationGroups['CITY_NAME'] : $dbLocationGroups['CITY_NAME_ORIG'];
			$payment = 'card_on_receipt';
			if($city) {
				
				$arData = array('CITY' => $city, 'PAYMENT_METHOD' => $payment, 'TARIFF' => $type);
				$cacheTime = 300;
				$cacheId = md5(serialize($arData));
				$cachePath = 'modules/'.self::$module_id.'/'.__FUNCTION__;
				$obCache = new CPHPCache();
				if ($obCache->InitCache($cacheTime, $cacheId, $cachePath))
				{
					$getPrice = $obCache->GetVars();
				}	
				else {
					$obCache->StartDataCache();					
	        		$prepareCalculator = TwinpxApi::PrepareDataCalculate($arData);
					$getPrice = TwinpxApi::requestPost('/api/b2b/platform/pricing-calculator', $prepareCalculator);
					$obCache->EndDataCache($getPrice);
				}
				
				//��������� �����		
				if($getPrice['CODE'] == '200') {
					$arPrice = explode(" ", $getPrice['DATA']['pricing_total']);
				}
				
				$result = ($arPrice[0] >=0) ? $arPrice[0] : FALSE; //���� �������� ���� ������ 0
			}
		}
		return $result;
	}
	
	function __CheckCompatible($arOrder, $arConfig)
	{
		//��������� ���������
		$compatible = TwinpxConfigTable::CheckSettings();
		$session = Application::getInstance()->getSession();
		
		//�������� ������� ���� �� ��
		$obResult = TwinpxTimezoneTable::getList(array("select" => array("UTC"), "filter" => array("BX_CODE" => $arOrder['LOCATION_TO'])));
		if($out = $obResult->Fetch()) {
			$utc = ($out['UTC']) ? $out['UTC'] : date('O');
			$session->set('TIMEZONE', $utc);
		}	
				
		//�������� ������ �����, ��� �������������
		if ($arOrder['CURRENCY'] == 'RUB' && $compatible['RESULT'] == 'OK' )
		{
			return TRUE;
		}
		
		return FALSE;
	}
	
	//����� ������ ������ ������ � �������� ��������
	public function OrderDeliveryBuildList(&$arResult, &$arUserResult, $arParams)
	{
		global $APPLICATION;
		\CUtil::InitJSCore(array('twinpx_lib'));  //���������� ���� JS ����������
		
		$request     = Application::getInstance()->getContext()->getRequest();
		$requestData = $request->getPost("order");
		$deliveryIds  = array_keys($arResult['DELIVERY']);
		$personIds  = array_keys($arResult['PERSON_TYPE']);
		$flag = FALSE;
		$showMaps = FALSE;
		
		$arDelivery = self::ChechDelivery($deliveryIds);
		
		//���� ������� ���� �������� ��������, ������ ���. ������ � ��������
		if(!empty($arDelivery))
		{
			foreach($arDelivery as $profile) {
				$decription  = ''; //
				$code = $profile['CODE'];
				switch($code){
					case 'twpx_yadelivery:curier':
							$decription .= '<p id="twpx-showoffer" class="twpx_btn_wrapper"><a class="btn btn-primary" onclick="twinpxYadeliveryCourierPopupOpen(this)" style="line-height: 1.2; padding: 8px 0;">'.GetMessage("Curier-Open").'</a></p>';
							$arResult['DELIVERY'][$profile['ID']]['DESCRIPTION'] .= $decription;
							$flag = TRUE;
						break;
					case 'twpx_yadelivery:pickup':
							$decription .= '<p id="twpx-showpvz" class="twpx_btn_wrapper"><a class="btn btn-primary" onclick="showPvz(this)" style="line-height: 1.2; padding: 8px 0;">'.GetMessage("Pickup-Open").'</a></p>';
							$arResult['DELIVERY'][$profile['ID']]['DESCRIPTION'] .= $decription;
							$flag = TRUE;
							$showMaps = TRUE;
						break;
					/*case 'twpx_yadelivery:curier':
							$decription .= '<p id="twpx-showpvz" class="twpx_btn_wrapper"><a class="btn btn-primary" onclick="showPvz(this)" style="line-height: 1.2; padding: 8px 0;">'.GetMessage("Pickup-Open").'</a></p>';
							$arResult['DELIVERY'][$profile['ID']]['DESCRIPTION'] .= $decription;
						break;*/
					case 'twpx_yadelivery:pickup_simple':
							$decription .= '<p id="twpx-showpvz-simple" class="twpx_btn_wrapper"><a class="btn btn-primary" onclick="showPvz(this, \'simple\')" style="line-height: 1.2; padding: 8px 0;">'.GetMessage("Pickup-Simple-Open").'</a></p>';
							$arResult['DELIVERY'][$profile['ID']]['DESCRIPTION'] .= $decription;
							$showMaps = TRUE;
						break;
					default:
						break;
				}
				
			}
		}

		//�� ����� ���� �������
		if($flag) 
		{
			$options = TwinpxConfigTable::GetAllOptions(); //���������
			$require = array();
			foreach($personIds as $i){
				if($options['PropFio_'.$i] > 0){
					$require[] = "ORDER_PROP_".$options['PropFio_'.$i];
				}
				if($options['PropPhone_'.$i] > 0){
					$require[] = "ORDER_PROP_".$options['PropPhone_'.$i];
				}
				if($options['PropCity_'.$i] > 0){
					$require[] = "ORDER_PROP_".$options['PropCity_'.$i];
				}
				if($options['PropCorp_'.$i] > 0){
					$require[] = "ORDER_PROP_".$options['PropCorp_'.$i];
				}
				if($options['PropHome_'.$i] > 0){
					$require[] = "ORDER_PROP_".$options['PropHome_'.$i];
				}
				if($options['PropStreet_'.$i] > 0){
					$require[] = "ORDER_PROP_".$options['PropStreet_'.$i];
				}
				$dbProps = CSaleOrderProps::GetList(array("SORT"=> "ASC"), array("IS_ADDRESS" => "Y", "ACTIVE" => "Y", "PERSON_TYPE_ID" => $i), false, false, array('ID'));
	            if($prop = $dbProps->Fetch()) {
	                $require[] = "ORDER_PROP_".$prop['ID'];
	            }
	            //����������
				if($options['PropComment_'.$i] == 'ORDER_DESCRIPTION') {
					$require[] = "ORDER_DESCRIPTION";
				}
				elseif($options['PropComment_'.$i] > 0){
					$require[] = "ORDER_PROP_".$options['PropComment_'.$i];
				}
				//������������ ����
			}
			$APPLICATION->AddHeadString('<script>window.twinpxYadeliveryRequired = '.\Bitrix\Main\Web\Json::encode($require).';</script>', true); //���� ������� ������ �� �����������.
		}
		
		//���������� ����� ���� ���� ���
		if($showMaps)
		{
			$scheme = $request->isHttps() ? 'https' : 'http';
			$ya_key = htmlspecialcharsbx(\Bitrix\Main\Config\Option::get('fileman', 'yandex_map_api_key', ''));
			$APPLICATION->AddHeadString('<script src="'.$scheme.'://api-maps.yandex.ru/2.1.50/?apikey='.$ya_key.'&load=package.full&lang=ru-RU"></script>', true); //���������� �����
		}
	}
	
	//���������� ������
    public function OrderSave($event)
    {        
        $request        = Context::getCurrent()->getRequest();
        $session        = Application::getInstance()->getSession();

        $deliveryID 	= $request->getPost("DELIVERY_ID");
        $thisPayID 		= $request->getPost("PAY_SYSTEM_ID"); 
        $offerID    	= ($session->has('OFFER_ID')) ? $session->get('OFFER_ID') : FALSE;
        $session_answer = ($session->has('JSON_ANSWER')) ? $session->get('JSON_ANSWER') : array();
        $flag       	= FALSE;
        $addressId		= 0;
        $pvzId 			= NULL;
    	
        //����� ����� ��������
        if($deliveryID){
	        $arDelivery = self::ChechDelivery($deliveryID);
	        if(!empty($arDelivery)) {
				$deliveryInfo = $arDelivery[$deliveryID];
				$flag = true;
				//���� ��� ���� ��������
				$options        = TwinpxConfigTable::GetAllOptions(); //���������
				$order          = $event->getParameter("ENTITY");
	            $props          = $order->getPropertyCollection();
	            $allprops       = $props->getArray();
	            $userProps      = $allprops['properties'];
	            $pTypeId 		= $userProps[0]['PERSON_TYPE_ID']; //��� �����������
	            $deliveryPrice 	= floatval($order->getDeliveryPrice());
	            //������� ���������� ������ � ������ �� �����
	            foreach ($userProps as $prop) {
	            	$propsFormat[$prop['ID']] = $prop;
	            }
	            
	            $location = CSaleLocation::GetByID($propsFormat[$options['PropCity_'.$pTypeId]]['VALUE'][0], LANGUAGE_ID); //��������������
	            if($location === false){ //���� �� ������� �������� �� �������, �������� ��������� ��������
					$location = array('CITY_NAME' => $propsFormat[$options['PropCity_'.$pTypeId]]['VALUE'][0], 'CODE' => '-');
				}
			}
		}
		
        //���� ���� ���� ������� ��������
        if(
        	$deliveryInfo['CODE'] == 'twpx_yadelivery:curier_simple' ||
        	$deliveryInfo['CODE'] == 'twpx_yadelivery:pickup_simple'
        ) 
        {            
            //���� ������ id ���
            if(strlen($session->get('PVZ_ID_SIMPLE')) > 1 && $deliveryInfo['CODE'] == 'twpx_yadelivery:pickup_simple'){
				$pvzId = $session->get('PVZ_ID_SIMPLE');
			}
			
            $data = array(
            	'ORDER_ID'      	=> $order->getId(),
                'ORDER_DATE'    	=> new \Bitrix\Main\Type\DateTime(),
                'PVZ_ID'			=> $pvzId,
                'PAYCONFIRM'		=> 0,
                'LOCATION'			=> $session->get('PVZ_ADDRESS'),
                'DELIVERY_INTERVAL'	=> '',
                'DELIVERYDATE'		=> ''
            );
            $res = TwinpxOfferTempTable::add($data);
		}
		//���� ���� ���� �������� � ������� �����
        elseif ($flag AND $offerID) {
            $order          = $event->getParameter("ENTITY");
            $props          = $order->getPropertyCollection();
            $allprops       = $props->getArray();
            $userProps      = $allprops['properties'];
            $deliveryPrice 	= floatval($order->getDeliveryPrice());
            
//            $options         = TwinpxConfigTable::GetAllOptions(); //���������
            $deliveryInterval = '';
            $pickupInterval = '';
            $full_address = '';
            $pvzId = NULL;
            $price = NULL;
            
            //�������� ���������� �� ��������� �������
			if($thisPayID > 0) {
				if(strlen($options['Pay_'.$thisPayID]) > 0){
					$payment = $options['Pay_'.$thisPayID];
				} 
				else {
					$error[] = 	GetMessage('PaymentError');
				}
			}
            
            //���� ���� ���� ���� �����
	        $dbProps = CSaleOrderProps::GetList(array("SORT" => "ASC"), array("IS_ADDRESS" => "Y", "ACTIVE" => "Y", "PERSON_TYPE_ID" => $pTypeId), false, false, array());
			if($prop = $dbProps->Fetch())
			{
				$addressId = $prop['ID'];
			}
            
            //��������� ����������
			foreach ($session_answer as $json_answer) {
                foreach ($json_answer as $answer) {
                    if ($answer['offer_id'] == $offerID) {
                        $start           = TwinpxApi::PrepareDataTime($answer['offer_details']['delivery_interval']['min']);
                        $end             = TwinpxApi::PrepareDataTime($answer['offer_details']['delivery_interval']['max']);
                        $deliveryInterval= $start . ' - ' . $end;
                        
                        $dstart           = TwinpxApi::PrepareDataTime($answer['offer_details']['pickup_interval']['min']);
                        $dend             = TwinpxApi::PrepareDataTime($answer['offer_details']['pickup_interval']['max']);
                        $pickupInterval	  = $dstart . ' - ' . $dend;
                        $pickupDate 	  = new \Bitrix\Main\Type\DateTime($dstart);
                    }
                }
            }
            
            if($session->get('PVZ_ID') !== FALSE){
				$full_address = $session->get('FULL_ADDRESS');
				$pvzId = $session->get('PVZ_ID');
	            $price = $session->get('PVZPRICE');
			}
			elseif($addressId > 0){
				if ($location['CITY_NAME']) {
	                $full_address .=  Loc::getMessage('Address-Prefix-City') . $location['CITY_NAME'];
	            }
				if ($propsFormat[$addressId]['VALUE'][0]) {
	                $full_address .= ", ".$propsFormat[$addressId]['VALUE'][0];
	            }
	            $price = $session->get('CURIERPRICE');
			}
			else {
	            if ($location['CITY_NAME']) {
	                $full_address .=  Loc::getMessage('Address-Prefix-City') . $location['CITY_NAME'];
	            }
	            if ($propsFormat[$options['PropStreet_'.$pTypeId]]['VALUE'][0]) {
	                $full_address .= Loc::getMessage('Address-Prefix-Street') . $propsFormat[$options['PropStreet_'.$pTypeId]]['VALUE'][0];
	            }
	            if ($propsFormat[$options['PropHome_'.$pTypeId]]['VALUE'][0]) {
	                $full_address .= Loc::getMessage('Address-Prefix-Home') . $propsFormat[$options['PropHome_'.$pTypeId]]['VALUE'][0];
	            }
	            if ($propsFormat[$options['PropCorp_'.$pTypeId]]['VALUE'][0]) {
	                $full_address .= Loc::getMessage('Address-Prefix-Corp') . $propsFormat[$options['PropCorp_'.$pTypeId]]['VALUE'][0];
	            }
	            if ($propsFormat[$options['PropApartament_'.$pTypeId]]['VALUE'][0]) {
	                $full_address .= Loc::getMessage('Address-Prefix-Ap') . $propsFormat[$options['PropApartament_'.$pTypeId]]['VALUE'][0];
	            }
	            
	            //������� ������
	            $price = $session->get('CURIERPRICE');
			}
			
			
			if($session->has('DELIVERY_COST') && $session->get('DELIVERY_COST') >= 0){
				$deliveryCost = floatval($session->get('DELIVERY_COST'));
			}
			
           
            //���������� ������ ��� ������ � �������
            $data = array(
                'OFFER_ID'         	=> $offerID,
                'PVZ_ID'           	=> $pvzId,
                'ORDER_ID'         	=> $order->getId(),
                'ORDER_DATE'       	=> new \Bitrix\Main\Type\DateTime(),
                'ADDRESS'          	=> $full_address,
                'LOCATION'         	=> $location['CODE'],
                'DELIVERY_INTERVAL'	=> $deliveryInterval,
                'JSON_REQUEST'     	=> ($session->has('JSON_REQUEST')) ? serialize($session->get('JSON_REQUEST')) : NULL,
                'JSON_RESPONS'     	=> ($session->has('JSON_ANSWER')) ? serialize($session->get('JSON_ANSWER')) : NULL,
                'PAYMENT'		   	=> $payment,
                'PICKUP'			=> $pickupInterval,
                'PICKUPDATE'		=> $pickupDate,
                'PRICE'				=> $price,
                'PRICE_FIX'			=> $deliveryPrice,
                'PRICE_DELIVERY'	=> $deliveryCost,
                'BARCODE'			=> $session->get('YDELIVERY_BARCODE')
            );
            
			$offer_id = array("offer_id" => $offerID);
            $create = TwinpxApi::requestPost('/api/b2b/platform/offers/confirm', $offer_id);
			if ($create['SUCCESS'] AND $create['DATA']['request_id']) {
            	$data += array('REQUEST_ID' => $create['DATA']['request_id'], 'PAYCONFIRM' => 0);
            	$r = TwinpxOfferTable::add($data);
            	
            	//���������� ������
            	$state = TwinpxApi::GetOfferState($create['DATA']['request_id']);
                if ($state['STATUS']) {
                    $stats = array('STATUS' => $state['STATUS'], 'STATUS_DESCRIPTION'=> $state['DESCRIPTION']);
                    TwinpxOfferTable::update($r->GetID(), $stats); //���������� ������
                }
                
                //���� ��������� �������������� ������ ������.
                //��������� ����� ������ ���� ������
                $timeDelivery = TwinpxConfigTable::getByCode('CancelDelivery'); //����� ������
                $timePaid = TwinpxConfigTable::getByCode('CancelPaid'); //����� ������
				
				if($timePaid > 0 && $payment == 'already_paid'){
	                //���������� �����������
	                $arEventFields = array(
	                    "ID"                => $r->GetID(),
	                    "ORDER_ID"          => $data['ORDER_ID'],
	                    "OFFER_ID"          => $data['OFFER_ID'],
	                    "ORDER_DATE"        => FormatDateFromDB($data['ORDER_DATE'], "FULL"),
	                    "STATUS_DESCRIPTION"=> $data['STATUS_DESCRIPTION'],
	                    "SALE_EMAIL" => \Bitrix\Main\Config\Option::get("sale", "order_email", "order@".$SERVER_NAME)
	                );
					CEvent::Send("TWPX_YANDEX_ORDER_CREATE_PAID", SITE_ID, $arEventFields);
				}
				elseif($timeDelivery > 0){
	                //���������� �����������
	                $arEventFields = array(
	                    "ID"                => $r->GetID(),
	                    "ORDER_ID"          => $data['ORDER_ID'],
	                    "OFFER_ID"          => $data['OFFER_ID'],
	                    "ORDER_DATE"        => FormatDateFromDB($data['ORDER_DATE'], "FULL"),
	                    "STATUS_DESCRIPTION"=> $data['STATUS_DESCRIPTION'],
	                    "SALE_EMAIL" => \Bitrix\Main\Config\Option::get("sale", "order_email", "order@".$SERVER_NAME)
	                );
					CEvent::Send("TWPX_YANDEX_ORDER_CREATE", SITE_ID, $arEventFields);
				}
				else {
					//
				}
			}
        }
        
        //������� ��� ������������ ������ � ����������
        $session->remove('PVZ_ID');
        $session->remove('PVZ_ID_SIMPLE');
        $session->remove('PVZ_ADDRESS');
        $session->remove('PVZPRICE');
        $session->remove('CURIERPRICE');
        $session->remove('OFFER_ID');
        $session->remove('FULL_ADDRESS');
        $session->remove('JSON_REQUEST');
        $session->remove('JSON_ANSWER');
        $session->remove('YDELIVERY_BARCODE');
    }
	
	//remove on next (action removed)
    public function SalePayOrder($id, $val)
    {
        return;//�� ���������
        if ($val == 'Y' && FALSE) {
        	$rsSites = CSite::GetList($by="sort", $order="desc", array("DOMAIN" => $_SERVER['SERVER_NAME']));
			if($arSite = $rsSites->Fetch())
			{
				$sId = $arSite['ID'];
			}
        	
            //���� �������� ������
            $res = TwinpxOfferTempTable::getList(array(
                'select' => array('*'),
                'filter' => array('ORDER_ID'=> $id)
            ));
            if ($ar = $res->Fetch()) {
                $data = $ar;
                unset($data['ID']);
            }
            
            $offer_id = array("offer_id" => $data['OFFER_ID']);
            
            $create = TwinpxApi::requestPost('/api/b2b/platform/offers/confirm', $offer_id);
            if ($create['SUCCESS'] AND $create['DATA']['request_id']) {
                $data += array(
                    'REQUEST_ID'=> $create['DATA']['request_id']
                );
                $r = TwinpxOfferTable::add($data); //���������� ��������
	            
                //������ ������� ��������
                $state = TwinpxApi::GetOfferState($create['DATA']['request_id']);
                if ($state['STATUS']) {
                    $stats = array(
                        'STATUS'            => $state['STATUS'],
                        'STATUS_DESCRIPTION'=> $state['DESCRIPTION']
                    );
                    TwinpxOfferTable::update($r->GetID(), $stats); //���������� ������
                }
                
                //��������� ������� ����� ������ � ������������
                if (!CSaleOrder::StatusOrder($id, "P"))
   					//echo "������ ��������� ������ ������� ������";
				
                CSaleOrder::Update($id, array('ALLOW_DELIVERY' => 'Y'));
            }
            else {
                $data['STATUS'] = 'CREATED_ERROR';
                $data['STATUS_DESCRIPTION'] = Loc::getMessage('Order-Error');
                
                //�������� ����� ��� ����� ������.
                CSaleOrder::Update($id, array('MARKED' => 'Y', 'REASON_MARKED' => $data['STATUS_DESCRIPTION']));
				                
                $r = TwinpxOfferTable::add($data);
                //���������� �����������
                $arEventFields = array(
                    "ID"                => $r->GetID(),
                    "OFFER_ID"          => $data['OFFER_ID'],
                    "ORDER_DATE"        => FormatDateFromDB($data['ORDER_DATE'], "FULL"),
                    "STATUS_DESCRIPTION"=> $data['STATUS_DESCRIPTION'],
                    "SALE_EMAIL" => \Bitrix\Main\Config\Option::get("sale", "order_email", "order@".$SERVER_NAME)
                );
                CEvent::Send("TWPX_YANDEX_ORDER", $sId, $arEventFields);
            }
        }
    }

	//��������� ���� ���� ���� ��������
	public function ChechDelivery($ids = array())
	{
		$return = array();
		$rsDelivery  = Table::getList(array('filter' => array('ACTIVE' => 'Y', '=CODE' => 'twpx_yadelivery'), 'select' => array('ID')));
		while($delivery = $rsDelivery->fetch())
		{
			$rsProfile = Table::getList(array('filter' => array('ACTIVE' => 'Y', 'PARENT_ID' => $delivery['ID'], 'ID' => $ids), 'select' => array('ID', 'CODE', 'DESCRIPTION')));
			while($profile = $rsProfile->fetch())
			{
				$return[$profile['ID']] = $profile;
			}
		}
		
		return $return;
	}
	
	//����� ��� �������� ������� ��������
	public function Agent()
	{
		if (CModule::IncludeModule("twinpx.yadelivery") && CModule::IncludeModule("sale")) {
			$GLOBALS['result_html'] = '';
			$options = TwinpxConfigTable::GetAllOptions(); //���������
			$res = TwinpxOfferTable::getList(array('filter'	=> array('DIVIDE' => 1, '!REQUEST_ID' => FALSE, 'CANCEL' => 0), 'select' => array("ID", "REQUEST_ID", "ORDER_ID", "STATUS", "STATUSCONFIRM"), 'order' => array('ID' => 'DESC')))->fetchAll();
			foreach($res as $row) {
				$id = $row['ID'];
				$requestID = $row['REQUEST_ID'];
				$order_id = $row['ORDER_ID'];
				$thisStatus = $row['STATUS'];
				$statusConfirm = intval($row['STATUSCONFIRM']);
				
				$state = TwinpxApi::GetOfferState($requestID);
				
				//������ ������ �� ������
				if($state['STATUS']) { //���� ��������� �������
			        $data = array('STATUS' => $state['STATUS'], 'STATUS_DESCRIPTION'=> $state['DESCRIPTION']);
					
					//���� ���� ��������� ��� ������
					if(strlen($options[$state['STATUS']]) > 1) {
						$opState = unserialize($options[$state['STATUS']]);
						
						if($statusConfirm === 1 && $thisStatus == $state['STATUS']){
							continue;
						}
						else {
							//������ ������ ������
							if(strlen($opState['SALE'])>1){
								CSaleOrder::StatusOrder($order_id, $opState['SALE']); //������ ������
							}
							
							//������ ������� ��� ��������
							if(strlen($opState['DELIVERY']) > 0 || strlen($opState['SHIPPED']) > 0){
								//�������� �����
								$order = \Bitrix\Sale\Order::load($order_id);
								$shipmentCollection = $order->getShipmentCollection(); //�������� ��������
								foreach ($shipmentCollection as $shipment) {
									if($shipment->isSystem())
										continue;
									
									//������ ��������
									if(strlen($opState['DELIVERY']) > 1){
										$shipment->setFields(array('STATUS_ID' => $opState['DELIVERY']));
										$data['STATUSCONFIRM'] = 1;
									}
									
									//�������� ���������
									if($opState['SHIPPED'] == 'Y'){
										$shipment->setFields(array('DEDUCTED' => 'Y'));
									}
								}
								$order->save(); //��������� ������
								
							}
						}
						
						//��������� ������
						if($opState['ARHIVED'] == 'Y'){
							$data['DIVIDE'] = 2;
						}
						
					}
					
				    TwinpxOfferTable::update($id, $data);
				    $GLOBALS['result_html'] .= '<p>'.GetMessage("TWINPX_YADELIVERY_OBNOVLEN_ZAKAZ").$row['ID'].'</p>';
				}
			}
			
			//��������� ���� ���� �������� � ��������� ����� ��������
	        $code = TwinpxConfigTable::getByCode('AgentUpdate');
	        if ($code == NULL) {
	            TwinpxConfigTable::add(array('CODE' => 'AgentUpdate','VALUE' => time()));
	        } else {
	            TwinpxConfigTable::update('AgentUpdate', array('VALUE' => time()));
	        }
		}
		return "TwinpxDelivery::Agent();";
	}
	
	//����� ��� �������� ������� ��������
	public function AgentShipment()
	{
		if (CModule::IncludeModule("twinpx.yadelivery") && CModule::IncludeModule('sale')) 
		{
			$thisDate = time();
			$chechCancel = TwinpxConfigTable::getByCode('Cancel_Offer'); //������� ������
			$timeDelivery = TwinpxConfigTable::getByCode('CancelDelivery'); //����� ������
            $timePaid = TwinpxConfigTable::getByCode('CancelPaid'); //����� ������
			
			$rsSites = CSite::GetList($by="sort", $order="desc", array("DOMAIN" => $_SERVER['SERVER_NAME'], "ACTIVE" => "Y"));
			if($arSite = $rsSites->Fetch())
			{
				$sId = $arSite['ID'];
			}
			//����� ������, ������ ������������� ������
			$res = TwinpxOfferTable::getList([
				'filter'	=> array('DIVIDE' => 1, 'CHECK_AGENT' => 0),
				'select'    => array("ID", "ORDER_DATE", "REQUEST_ID", "PAYMENT", "ORDER_ID", "CHECK_AGENT", "CANCEL"),
			    'order'     => array('ID' => 'DESC')
			])->fetchAll();
			
			foreach($res as $row) {
				//���� ��� ����������� �������� ��� �����������.
				if($timeDelivery < 1 && $timePaid < 1 || $chechCancel == 'N') {
					TwinpxOfferTable::update($row['ID'], array('CHECK_AGENT' => 1));
					continue;
				}
				
				//���� ������ �������� �������� ��� �������
				if($row['CANCEL'] == 1) {
					TwinpxOfferTable::update($row['ID'], array('CHECK_AGENT' => 1));
					continue;
				}
				
				$diff = $thisDate - MakeTimeStamp($row['ORDER_DATE']); //������� ������� ������� ����� ��� ������ �����
				$arOrder = CSaleOrder::GetByID($row['ORDER_ID']); //���������� � ������
				
				//�������� ��� ������ �� �����
				$payed = $arOrder['PAYED'];
				if($row['PAYMENT'] == 'already_paid' && $chechCancel == 'Y') {
					if($payed === 'Y' || $timePaid < 1) {
						TwinpxOfferTable::update($row['ID'], array('CHECK_AGENT' => 1));
					}
					elseif($diff >= ($timePaid*60) && $row['REQUEST_ID']) {
						$state = TwinpxApi::CancelOffer($row['REQUEST_ID']);	
						if ($state['STATUS']) {
					        $data = array(
					            'STATUS'            => $state['STATUS'],
					            'STATUS_DESCRIPTION'=> $state['DESCRIPTION'],
					            'CHECK_AGENT'		=> 1,
					            'CANCEL'			=> 1
					        );
					        TwinpxOfferTable::update($row['ID'], $data);
					    }
					    $arEventFields = array(
		                    "ID"          		=> $row['ID'],
		                    "ORDER_ID"        	=> $row['ORDER_ID'],
		                    "ORDER_DATE"        => FormatDateFromDB($row['ORDER_DATE'], "FULL"),
		                    "STATUS_DESCRIPTION"=> $data['STATUS_DESCRIPTION'],
		                    "SALE_EMAIL" 		=> \Bitrix\Main\Config\Option::get("sale", "order_email", "order@".$SERVER_NAME)
		                );
		                $send = CEvent::Send("TWPX_YANDEX_ORDER_CANCEL_PAID", $sId, $arEventFields);
					}
				}
				
				//�������� �� ������������ ��������
				$delivery = $arOrder['ALLOW_DELIVERY'];
				if($row['PAYMENT'] != 'already_paid' && $chechCancel == 'Y') {
					if($delivery === 'Y' || $timeDelivery < 1) {
						TwinpxOfferTable::update($row['ID'], array('CHECK_AGENT' => 1));
					}
					elseif($diff >= ($timeDelivery*60) && $row['REQUEST_ID']) {
						$state = TwinpxApi::CancelOffer($row['REQUEST_ID']);	
						if ($state['STATUS']) {
					        $data = array(
					            'STATUS'            => $state['STATUS'],
					            'STATUS_DESCRIPTION'=> $state['DESCRIPTION'],
					            'CHECK_AGENT'		=> 1,
					            'CANCEL'			=> 1
					        );
					        TwinpxOfferTable::update($row['ID'], $data);
					    }
					    $arEventFields = array(
		                    "ID"          		=> $row['ID'],
		                    "ORDER_ID"        	=> $row['ORDER_ID'],
		                    "ORDER_DATE"        => FormatDateFromDB($row['ORDER_DATE'], "FULL"),
		                    "STATUS_DESCRIPTION"=> $data['STATUS_DESCRIPTION'],
		                    "SALE_EMAIL" 		=> \Bitrix\Main\Config\Option::get("sale", "order_email", "order@".$SERVER_NAME)
		                );
		                $send = CEvent::Send("TWPX_YANDEX_ORDER_CANCEL", $sId, $arEventFields);
					}
				}
				
				
			}
		}
		return "TwinpxDelivery::AgentShipment();";
	}
}
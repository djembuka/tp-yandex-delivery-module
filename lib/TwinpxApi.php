<?php
namespace Twinpx\Yadelivery;

use Bitrix\Main\Localization\Loc,
	Bitrix\Sale\Fuser,
	Bitrix\Main\Loader,
	Bitrix\Main\Web\Json,
	Bitrix\Main\Text\Encoding;

use Twinpx\Yadelivery\TwinpxConfigTable;

Loader::includeModule('sale');
Loc::loadMessages(__FILE__);

//����� ��� ������ � api ��������
class TwinpxApi
{
    public $default;
    static $url = 'https://b2b-authproxy.taxi.yandex.net'; //API ������ ������
    static $demourl = 'https://b2b.taxi.tst.yandex.net'; //API ������ ��������
    static $module_id = 'twinpx.yadelivery';
    
    public function __construct()
    {
		//return TRUE;
    }
    
    public static function SetLogs($data = array(), $type = 'DEBUG', $object = false)
    {
    	global $USER;
    	$check = TwinpxConfigTable::getByCode('Enable_Logs'); //�������� ���� �����������
    	if($check === "Y"){
			\CEventLog::Add(
	        	array(
	                'SEVERITY'		=> $type,
	                'AUDIT_TYPE_ID'	=> 'TWINPX_LOGS',
	                'MODULE_ID'    	=> self::$module_id,
	                'ITEM_ID'		=> $object,
	                'USER_ID'		=> $USER->GetID(),
	                'DESCRIPTION'  	=> print_r($data, true)
	            )
	        );
		}
	}
    
    //�������� API ����
    public static function GetApiOAuth()
    {
        $auth = FALSE;
        $result = self::GetValue('OAuth');

        if ($result) {
            $auth = $result;
        }

        return $auth;
    }
	
	//�������� Platform_id
    public static function GetPlatformId()
    {
        $platform = FALSE;
        $result = self::GetValue('PlatformId');
        if ($result) {
            $platform = $result;
        }
        return $platform;
    }

	//�������� �������� �� ������� �� �����
    static function GetValue($code)
    {
        if (!$code)
            return;
        $obConfig = TwinpxConfigTable::getList(array('select' => array('VALUE'), 'filter' => array('CODE'=> $code)));
        if ($arResult = $obConfig->fetch()) {
            $return = $arResult['VALUE'];
        }

        return $return;
    }

	//���������� ������� ��� ��������� ����
    public static function PrepareDataCalculate($arData)
    {
        if (empty($arData))
            return; //��� ������
            
        $default      = TwinpxConfigTable::GetAllOptions(); //�������� �� ���������
        $items        = self::GetItems();
        
        $data = array(
			"client_price" => $items['price'],
			"destination" => ["address" => $arData['CITY']],
			"total_assessed_price" => $items['price'],
			"payment_method" => $arData['PAYMENT_METHOD'],
			"source" => ["platform_station_id" => $default['PlatformId']],
			"tariff" => $arData['TARIFF'],
			"total_weight" => $items['weight']
		);
        
        return $data; 
	}
	
	//���������� ������� ��� �������� � ������
    public static function PrepareData($arData, $order_id = FALSE, $isAdmin = FALSE)
    {
        if (empty($arData))
            return; //��� ������
            
        $session = \Bitrix\Main\Application::getInstance()->getSession();       
        $default      = TwinpxConfigTable::GetAllOptions(); //�������� �� ���������
        $items        = self::GetItems($order_id);
        $operator_request_id = ($order_id > 0) ? uniqid($order_id . "_") : uniqid();
        $full_address = '';
        $custom_location = '';
        $platform_station = '';
        $delivery_cost = 0;
        
        if($arData['PVZ_ID']) 
        {
			$type = 1;
            $last_mile_policy = 'self_pickup';
            
			$platform_station = array("platform_id" => $arData['PVZ_ID']);
            $full_address = $arData['FULL_ADDRESS'];
        }
		else 
		{
			$type = 2;
			$last_mile_policy = 'time_interval';
			
	        if ($arData['CITY']){
	            $full_address .= $arData['CITY'];
			}
			
			//���� ���� ������ �����
			if($arData['FULL_ADDRESS']){
				$full_address .= ', '.$arData['FULL_ADDRESS'];
			} 
			else {
								
		        if ($arData['STREET'])
		            $full_address .= ', '.GetMessage("TWINPX_PREFIX_STREET").$arData['STREET'];

		        if ($arData['HOME'])
		            $full_address .= ', '.GetMessage("TWINPX_PREFIX_HOME").$arData['HOME'];

		        if ($arData['CORPS'])
		            $full_address .= ', '.GetMessage("TWINPX_PREFIX_CORPS").$arData['CORPS'];

		        if ($arData['APARTAMENT'])
		            $full_address .= ', '.GetMessage("TWINPX_PREFIX_AP").$arData['APARTAMENT'];
			}
	        
	        $custom_location = array("details" => array("full_address"=> $full_address));
	        
	        //���� ���� �����������
        	if($arData['COMMENT']) {
				$custom_location["details"] += array("comment"=>$arData['COMMENT']);
			}
		}
        
		//���� ���� ���. ����
        if($arData['FIX_PRICE'] >= 0 && $arData['FIX_PRICE'] !== FALSE)
        {
            if ($isAdmin) {
                $delivery_cost = floatval($arData['FIX_PRICE']);
            }
            elseif ($arData['PAYMENT'] == 'already_paid') {
                $delivery_cost = 0;
            }
            else {
                $delivery_cost = floatval($arData['FIX_PRICE']);
            }
		}
		
		//���������� �������
        $data = array(
        	"last_mile_policy" => $last_mile_policy,
            "info" => array(
                "operator_request_id"	=> mb_substr($operator_request_id, 0, 20), //�������� �� 20 ��������, ����������� � ������
            	"referral_source" 		=> "1Cbitrix_2px_ndd",
                //"comment"           	=> $arData['COMMENT'],
            ),
            "source" => array(
                "type" => 1,
                "platform_station" 		=> array(
                    "platform_id" 		=> self::GetPlatformId()
                )
            ),
            "destination" => array(
                "type" => $type,
            ),
            "items" => $items['items'],
            "places" => array(
                array(
                    "physical_dims" => array(
                        "predefined_volume" => intval($items['volume']),
                        "weight_gross" 	=> intval($items['weight']),
                    ),
                    "description"=> '',//$default['Description'],
                    "barcode"    => self::GetPlaceBarcode($order_id)
                )
            ),
            "billing_info" => array(
                "payment_method"=> $arData['PAYMENT'],
                "delivery_cost" => ceil($delivery_cost*100)
            ),
            "recipient_info" 	=> array(
                "first_name"	=> $arData['FIO'][0],
                "last_name" 	=> $arData['FIO'][1],
                "phone"     	=> $arData['PHONE'],
                "email"			=> $arData['EMAIL']
            ),
        );
        
        //���� ���
        if($platform_station) {
			$data['destination']['platform_station'] = $platform_station;
		}
        
        //���� ��������
        if($custom_location) {
			$data['destination']['custom_location'] = $custom_location;
		}
		        
		//�������� �� �������
		if($isAdmin){
			//
		}
				
		//��������� ������
		$session->set('FULL_ADDRESS', $full_address);//�������� ������ ����� � ������, ��� ������ � ��
		$session->set('DELIVERY_COST', $delivery_cost); //�������� delivery_cost � ���������� ������

        return $data;
    }

    public static function GetItems($order_id = FALSE)
    {
        $items = array();
        $default = TwinpxConfigTable::GetAllOptions();
        $basket_storage = \Bitrix\Sale\Basket\Storage::getInstance(Fuser::getId(), SITE_ID);
        $basket         = $basket_storage->getBasket();
        
        if($order_id > 0) { //���� ������� ID ������, �������� ������ �������
        	$obBasket = \Bitrix\Sale\Basket::getList(array('filter' => array('ORDER_ID' => $order_id)));
			while($arItem = $obBasket->Fetch()){
			     $arData[] = $arItem;
			}
		}

        foreach ($basket as $basket_item) {
            $product = $basket_item->getFieldValues();
            if($product['CAN_BUY'] == 'Y') {  //������ ��������� ������ � �������
            	$arData[] = $product;
            	$ids[] = $product['PRODUCT_ID'];
			}
        }
       
        //�������� ������ �������
        $arrSelect = array();
        foreach($default as $key => $val){
			//�������� ��� ��������
			$posA = strpos($key, 'ArticleProduct');
			if($posA === false) {
			} else {
				$arrSelect[] = $val;
			}
			//�������� ��� �������
			$posB = strpos($key, 'BarcodeProduct');
			if($posB === false) {
			}else {
				$arrSelect[] = $val;
			}
		}
				
		if(!empty($ids)){
			$arSelect = Array("ID", "IBLOCK_ID", "NAME", "PROPERTY_CML2_LINK");
			$res = \CIBlockElement::GetList(array(), array("ID" => $ids), false, false, array_merge($arSelect, $arrSelect));
			while($ob = $res->Fetch()) {
				$properyProduct[$ob['ID']] = $ob;
			}
		}
		
        $totalVolume = 0;
        $totalPrice = 0;
        $totalWeight = 0;
        if(!empty($arData)){
	        foreach ($arData as $k => $data) {
	            //��������� ����� ��� ��
	            $item = $properyProduct[$data['PRODUCT_ID']];
	            
	            $keyArtikle = $default['ArticleProduct_'.$item['IBLOCK_ID']]; //�������� �������� ��� ��������
	            $keyBarcode = $default['BarcodeProduct_'.$item['IBLOCK_ID']]; //�������� ������� ��� �������
	            
	          	$article = ($item[$keyArtikle.'_VALUE']) ? $item[$keyArtikle.'_VALUE'] : '_'.$data['PRODUCT_ID']; //���� ��� �������� ID ������
				$barcode = ($item[$keyBarcode.'_VALUE']) ? $item[$keyBarcode.'_VALUE'] : '_'.$data['PRODUCT_ID']; //���� ��� �������� ID ������
				$quantity = intval($data['QUANTITY']);
	            $volume = $default['Volume'];
	            $weight = $default['Weight'];
	            $price = $data['PRICE'];
	//            $dx		= $default['Length']; //mm
	//            $dy		= $default['Height']; //mm
	//            $dz		= $default['Width']; //mm
	            
	            //���� ������
	            $arSizes = unserialize($data['DIMENSIONS']); //� ��
	            if ($arSizes['WIDTH'] AND $arSizes['HEIGHT'] AND $arSizes['LENGTH']) {
	                $volume = ($arSizes['WIDTH'] * 0.1) * ($arSizes['HEIGHT'] * 0.1) * ($arSizes['LENGTH'] * 0.1);
	                $dx		= intval($arSizes['LENGTH']);
		            $dy		= intval($arSizes['HEIGHT']);
		            $dz		= intval($arSizes['WIDTH']);
	            }

	            if ($data['WEIGHT'] > 0) {
	                $weight = $data['WEIGHT']; //� ��
	            }

	            $items[] = array(
	                "count"          	=> $quantity,
	                "name"           	=> ($data['NAME']) ? $data['NAME'] : GetMessage("TWINPX_TOVAR").$data['PRODUCT_ID'],
	                "article"        	=> $article,
	                "barcode"        	=> $barcode,
	                "billing_details" 	=> array(
	                    "unit_price"         => ($price * 100),
	                    "assessed_unit_price"=> ($price * 100)
	                ),
	                "physical_dims"     => array(
	                    "predefined_volume"=> intval($volume),
	                    //"weight_gross"     => intval($weight),
	                    //"dx"     => intval($dx/10),
	                    //"dy"     => intval($dy/10),
	                    //"dz"     => intval($dz/10)
	                ),
	                "place_barcode"  => self::GetPlaceBarcode($order_id)
	            );
	            
	            $totalPrice += ($price * 100) * $quantity; //
	            $totalVolume += $volume * $quantity;
	            $totalWeight += $weight * $quantity;
	        }
		} 
		
        return array('items' => $items, 'price' => $totalPrice, 'volume'=> $totalVolume, 'weight' => $totalWeight);
    }

    //����� �� ����� 30 �������� 
    function GetPlaceBarcode($order_id = false, $len = 30)
    {
		$session = \Bitrix\Main\Application::getInstance()->getSession();
        $default = TwinpxConfigTable::GetAllOptions();
        
        $strBarcode = $default['Barcode'];
        $order = ($order_id) ? intval($order_id) : '';
        $date = date('Ymd');
        $fUser = Fuser::getId();
        
        if(strlen($strBarcode) > ($len - strlen($order) + 1)) { //���� ������� ����� 29 ��������
        	if($order > 0) { //���� ����� ����� ������
				$strOrder = intval(strlen($order) + 1);
				$cutBarcode = mb_substr($strBarcode, 0, ($len - $strOrder)); //�������� �������
				$barcode = $cutBarcode . '_' .$order; //��������� � ������
			}
			else {
				$barcode = $strBarcode; //���� ��� � ����� ������ ���������� ��������� �������
			}
		}
		else { //�������� ��� ������ � ������ � �������� �� ������ �����
			if($order > 0) {
				$arBarcode[] = $strBarcode;
				$arBarcode[] = $order;
				$arBarcode[] = $date;
				$arBarcode[] = $fUser;
			}
			else {
				$arBarcode[] = $strBarcode;
				$arBarcode[] = $date;
				$arBarcode[] = $fUser;
			}
			$barcode = implode("_", $arBarcode);
		}
		
		$return = mb_substr($barcode, 0, $len); //�������� �� 30 ��������
		
        $session->set("YDELIVERY_BARCODE", $return); //���������� � ������
        
        return $return; //���������� ������
    }

    //����� �������  �������
    public static function ShowOfferAdmin($arResult, $order_id = false)
    {
		if (empty($arResult))
            return;
    
        foreach ($arResult as $interval => $offers) {
            if (count($offers) > 1) {
                foreach ($offers as $offer) {
                    $arr[] = array(
                        'offer_id'	=> $offer['offer_id'],
                        'expire'  	=> $offer['expires_at'],
                        'price'   	=> $offer['offer_details']['pricing'],
                        'delivery'  => array('start'=> $offer['offer_details']['delivery_interval']['min'],'end'  => $offer['offer_details']['delivery_interval']['max']),
                        'pickup'    => array('start'=> $offer['offer_details']['pickup_interval']['min'],'end'  => $offer['offer_details']['pickup_interval']['max']),
                        'interval'	=> $interval
                    );
                }
            }
            else {
                $offer = $offers[0];
                $arr[] = array(
                    'offer_id'	=> $offer['offer_id'],
                    'expire' 	=> $offer['expires_at'],
                    'price'   	=> $offer['offer_details']['pricing'],
                    'delivery'  => array('start'=> $offer['offer_details']['delivery_interval']['min'],'end'  => $offer['offer_details']['delivery_interval']['max']),
                    'pickup'    => array('start'=> $offer['offer_details']['pickup_interval']['min'],'end'  => $offer['offer_details']['pickup_interval']['max']),
                    'interval'	=> $interval
                );
            }
        }

        //�������� ���������� ����������
        foreach($arr as $o) {
            $out[md5(serialize($o['delivery']))] = $o;
        }

        $html = '';
        if (!empty($out)) {
            $html = '<div class="yd-popup-offers__wrapper">';
            foreach ($out as $offer) {
                if( strlen($offer['price']) < 1 ) continue;

                $startDate = MakeTimeStamp(self::PrepareDataTime($offer['delivery']['start']), "DD.MM.YYYY HH:MI:SS");
                $endDate = MakeTimeStamp(self::PrepareDataTime($offer['delivery']['end']), "DD.MM.YYYY HH:MI:SS");

                $formatDate = FormatDate("j F Y", $startDate);

                $formatTimeStart = FormatDate("H:i", $startDate); //�������������� ������ 
                $formatTimeEnd = FormatDate("H:i", $endDate); //�������������� �����
				
				$dataJson = array(
					'order_id' => $order_id,
					'offer_id' => $offer['offer_id'],
					'offer_expire' => $offer['expire'],
					'interval' => trim($offer['interval']),
					'price' => trim($offer['price'])
				);
				                
                $html .= '
                	<div class="yd-popup-offers__item" data-object=\''.\Bitrix\Main\Web\Json::encode($dataJson).'\'>
                  		<b class="yd-popup-offers__date"><i style="background-image: url(\'/bitrix/images/twinpx.yadelivery/calendar.svg\')"></i>'.$formatDate.'</b>
				    	<span class="yd-popup-offers__time"><i style="background-image: url(\'/bitrix/images/twinpx.yadelivery/clock.svg\')"></i>c '.$formatTimeStart.' '.GetMessage("TWINPX_DO").$formatTimeEnd.'</span>
				    	<b class="yd-popup-offers__price">'.$offer['price'].'</b>
				    	<a href="" class="ui-btn ui-btn-sm ui-btn-primary">'.GetMessage("TWINPX_VYBRATQ").'</a>
				  	</div>
                ';
            }
            $html .= "</div>";
        }
        else {
    		$html .= '<div class="yd-popup-error__message"><i style="background-image: url(/bitrix/images/twinpx.yadelivery/danger.svg)"></i>'.GetMessage("TWINPX_NET_DOSTUPNYH_INTERV").'</div>';
        }

        return $html;
	}
	
	//����� ������� � �������
    public static function ShowOffer($arResult)
    {
        if (empty($arResult))
            return;
    
        foreach ($arResult as $interval => $offers) {
            if (count($offers) > 1) {
                foreach ($offers as $offer) {
                    $arr[] = array(
                        'offer_id'	=> $offer['offer_id'],
                        'expire'  	=> $offer['expires_at'],
                        'price'   	=> $offer['offer_details']['pricing'],
                        'delivery'  => array('start'=> $offer['offer_details']['delivery_interval']['min'],'end'  => $offer['offer_details']['delivery_interval']['max']),
                        'pickup'    => array('start'=> $offer['offer_details']['pickup_interval']['min'],'end'  => $offer['offer_details']['pickup_interval']['max']),
                        'interval'	=> $interval
                    );
                }
            }
            else {
                $offer = $offers[0];
                $arr[] = array(
                    'offer_id'	=> $offer['offer_id'],
                    'expire' 	=> $offer['expires_at'],
                    'price'   	=> $offer['offer_details']['pricing'],
                    'delivery'  => array('start'=> $offer['offer_details']['delivery_interval']['min'],'end'  => $offer['offer_details']['delivery_interval']['max']),
                    'pickup'    => array('start'=> $offer['offer_details']['pickup_interval']['min'],'end'  => $offer['offer_details']['pickup_interval']['max']),
                    'interval'	=> $interval
                );
            }
        }

        //�������� ���������� ����������
        foreach($arr as $o) {
            $out[md5(serialize($o['delivery']))] = $o;
        }

        /**
        * 
        * @var todo
        * ��������� ��� ����������� ������������
        * 
        */
        $html = '';
        if (!empty($out)) {
            $html = '<div class="yd-popup-offers__wrapper">';
            foreach ($out as $offer) {
                if( strlen($offer['price']) < 1 ) continue;

                $startDate = MakeTimeStamp(self::PrepareDataTime($offer['delivery']['start']), "DD.MM.YYYY HH:MI:SS");
                $endDate = MakeTimeStamp(self::PrepareDataTime($offer['delivery']['end']), "DD.MM.YYYY HH:MI:SS");

                $formatDate = FormatDate("j F Y", $startDate);

                $formatTimeStart = FormatDate("H:i", $startDate); //�������������� ������ 
                $formatTimeEnd = FormatDate("H:i", $endDate); //�������������� �����

                $data = array(
                	'offer_id' 		=> $offer['offer_id'],
                	'offer_price'	=> $offer['price'],
                	'offer_expire'	=> $offer['expire']
                );
                $jsonData = \Bitrix\Main\Web\Json::encode($data);//�������� ������ �

                $html .= '
                	<div class="yd-popup-offers__item" data-json=\''.$jsonData.'\'>
                		<div class="yd-popup-offers__info">
	                    	<span class="yd-popup-offers__date"><i style="background-image: url(/bitrix/images/'.self::$module_id.'/pvz-calendar.svg)"></i>'.$formatDate.'</span>
	                    	<span class="yd-popup-offers__time"><i style="background-image: url(/bitrix/images/'.self::$module_id.'/pvz-clock.svg)"></i>c '.$formatTimeStart.' '.GetMessage("TWINPX_DO").$formatTimeEnd.'</span>
	                  	</div>
	                	<b class="yd-popup-offers__price">'.$offer['price'].'</b>
	                	<a href="#" class="ui-btn ui-btn-sm ui-btn-primary">'.GetMessage("TWINPX_VYBRATQ1").'</a>
                	</div>
                ';
            }
            $html .= "</div>";
        }
        else {
            $html .= '<div class="yd-popup-offers__wrapper">'.GetMessage("TWINPX_NET_DOSTUPNYH_INTERV").'</div>';
        }

        return $html;
    }
    
    public static function ShowOfferJson($arResult, $pvzId = FALSE, $orderId = FALSE)
    {
        if (empty($arResult))
            return;
            
        $session = \Bitrix\Main\Application::getInstance()->getSession();
        $data = array();

        if($pvzId) {
        	$cost = ($session->has('PICKUP_PRICE')) ? $session->get('PICKUP_PRICE') : FALSE;
			foreach ($arResult as $offers) {
	        	$arr[] = array(
	                'offer_id'	=> $offers['offer_id'],
	                'expire' 	=> $offers['expires_at'],
	                'price'   	=> $offers['offer_details']['pricing_total'],
	                'delivery'  => array('start'=> $offers['offer_details']['delivery_interval']['min'], 'end'  => $offers['offer_details']['delivery_interval']['max']),
	                'pickup'    => array('start'=> $offers['offer_details']['pickup_interval']['min'], 'end'  => $offers['offer_details']['pickup_interval']['max']),
	            );
	        }
		} 
		else {
			$cost = ($session->has('CURIER_PRICE')) ? $session->get('CURIER_PRICE') : FALSE;
			foreach ($arResult as $interval => $offers) {
	            if (count($offers) > 1) {
	                foreach ($offers as $offer) {
	                    $arr[] = array(
	                        'offer_id'	=> $offer['offer_id'],
	                        'expire'  	=> $offer['expires_at'],
	                        'price'   	=> $offer['offer_details']['pricing_total'],
	                        'delivery'  => array('start'=> $offer['offer_details']['delivery_interval']['min'],'end'  => $offer['offer_details']['delivery_interval']['max']),
	                        'pickup'    => array('start'=> $offer['offer_details']['pickup_interval']['min'],'end'  => $offer['offer_details']['pickup_interval']['max']),
	                        'interval'	=> $interval,
	                    );
	                }
	            }
	            else {
	                $offer = $offers[0];
	                $arr[] = array(
	                    'offer_id'	=> $offer['offer_id'],
	                    'expire' 	=> $offer['expires_at'],
	                    'price'   	=> $offer['offer_details']['pricing_total'],
	                    'delivery'  => array('start'=> $offer['offer_details']['delivery_interval']['min'],'end'  => $offer['offer_details']['delivery_interval']['max']),
	                    'pickup'    => array('start'=> $offer['offer_details']['pickup_interval']['min'],'end'  => $offer['offer_details']['pickup_interval']['max']),
	                    'interval'	=> $interval,
	                );
	            }
	        }
		}
		
		//���� ���� ��������� �������
		if($session->has('MARGIN_VALUE') && $session->get('MARGIN_VALUE') >= 0){
			$cost = $session->get('MARGIN_VALUE');
		}
    	
        //�������� ���������� ����������
        foreach($arr as $o) {
            $out[md5(serialize($o['delivery']))] = $o;
        }
        
        if (!empty($out)) {
            foreach ($out as $offer) {
                if( strlen($offer['price']) < 1 ) continue;

                $startDate = MakeTimeStamp(self::PrepareDataTime($offer['delivery']['start']), "DD.MM.YYYY HH:MI:SS");
                $endDate = MakeTimeStamp(self::PrepareDataTime($offer['delivery']['end']), "DD.MM.YYYY HH:MI:SS");

                $formatDate = FormatDate("j F Y", $startDate);

                $formatTimeStart = FormatDate("H:i", $startDate); //�������������� ������ 
                $formatTimeEnd = FormatDate("H:i", $endDate); //�������������� �����
                
                $data = array(
                	'offer_id' 		=> $offer['offer_id'],
                	'offer_price'	=> $offer['price'],
                	'offer_expire'	=> $offer['expire'],
                	'offer_pvz'		=> $pvzId,
                	'type' 			=> ($pvzId) ? "pickup" : "curier",
                	'cost'			=> round($cost, 2),
                	'email'			=> $session->get('BAYER_EMAIL')
                );
                //���� ������� ID ������
                if($orderId) {
                	$data['order_id'] .= $orderId; 
				}
                
                $jsonData = \Bitrix\Main\Web\Json::encode($data);//�������� ������
                                
                $output[] = array(
                	"timestamp" => $startDate,
                	"json" => $jsonData,
				    "date" => $formatDate,
				    "time" => ($pvzId) ? $formatTimeStart : GetMessage('TWINPX_FROM') . $formatTimeStart . GetMessage('TWINPX_TO') .$formatTimeEnd,
				    "price" => ($cost !== FALSE && $orderId === FALSE) ? \CCurrencyLang::CurrencyFormat($cost, "RUB") : \CCurrencyLang::CurrencyFormat($offer['price'], "RUB")
                );
            }
            //���������� �� ����
            usort($output, function($a,$b){
			    return ($a['timestamp']-$b['timestamp']);
			});
        }
        else {
            $output = GetMessage("TWINPX_NET_DOSTUPNYH_INTERV");
        }
		$session->remove('BAYER_EMAIL');
        return $output;
    }
    
    public static function GenerateSchedule($arParams)
    {
		if(empty($arParams)) return;
		
		$arResult = array();
		$week = array(
			1=>GetMessage("TWINPX_MONDAY"), 
			2=>GetMessage("TWINPX_TUESDAY"), 
			3=>GetMessage("TWINPX_WEDNESDAY"), 
			4=>GetMessage("TWINPX_THURSDAY"), 
			5=>GetMessage("TWINPX_FRIDAY"), 
			6=>GetMessage("TWINPX_SATURDAY"), 
			7=>GetMessage("TWINPX_SUNDAY")
		);
		
		foreach($arParams as $day){
			$days = array();
			foreach($day['days'] as $d){
				$days[] = $week[$d];
			}
			
			$from = sprintf("%02d", $day['time_from']['hours']).':'.sprintf("%02d", $day['time_from']['minutes']);
			$to = sprintf("%02d", $day['time_to']['hours']).':'.sprintf("%02d", $day['time_to']['minutes']);
			
			$arResult[] = implode(", ", $days).': '.$from.'-'.$to;
		}
		
		return "<div>".GetMessage("TWINPX_WORK_TIME") . implode(". ", $arResult)."</div>";
	}

    public static function GenerateInterval($location = FALSE)
    {
        //Local Format
        //$days[] = strtotime(" + 3 day");
        if($location['CODE'] == '0000073738') { //���� ��� ������
            $days[] = strtotime("+1 day");
            $days[] = strtotime("+2 day");
            $days[] = strtotime("+3 day");
            foreach ($days as $day) {
                $start1 = date('d.m.Y 09:00', $day);
                $end1 = date('d.m.Y 18:00', $day);
                
                $start2 = date('d.m.Y 14:00', $day);
                $end2  = date('d.m.Y 22:00', $day);
                
                $start3 = date('d.m.Y 19:00', $day);
                $end3  = date('d.m.Y 23:59', $day);

                $interval[] = array(
                    'from'=> strtotime($start1),
                    'to'  => strtotime($end1),
                    'fformat' => $start1,
                    'tformat' => $end1
                );
                $interval[] = array(
                    'from'=> strtotime($start2),
                    'to'  => strtotime($end2),
                    'fformat' => $start2,
                    'tformat' => $end2
                );
                $interval[] = array(
                    'from'=> strtotime($start3),
                    'to'  => strtotime($end3),
                    'fformat' => $start3,
                    'tformat' => $end3
                );
            }    
        } 
        else {
           $days[] = strtotime("+1 day");
           $days[] = strtotime("+2 day");
           $days[] = strtotime("+3 day");
           foreach ($days as $day) {
               $start1 = date('d.m.Y 09:00', $day);
               $end1 = date('d.m.Y 22:00', $day);

               $interval[] = array(
                   'from'=> strtotime($start1),
                   'to'  => strtotime($end1),
                   'fformat' => $start1,
                   'tformat' => $end1
               );
           } 
        }

        return $interval;
    }
    
    public static function GetInterval($address = FALSE)
    {
		if(!$address) return;
		$interval = array();
		$uri = "/api/b2b/platform/offers/info/";
				
		$query = array("station_id" => self::GetPlatformId(), "send_unix" => true, "full_address" => $address);
		$result = self::requestGet($uri, $query);
		
		if($result['SUCCESS']) {
			$interval = array_slice($result['DATA'], 0, 12);//�������� �� 12 ����������
		}
		return $interval;
	}
    
    public static function PrepareDataTime($utc)
    {        
        $session = \Bitrix\Main\Application::getInstance()->getSession(); 
        $utcTZ = new \DateTimeZone("UTC");
        if($session->has('TIMEZONE')) {
            $timeZone = $session->get('TIMEZONE');
        } else {
            $timeZone = date('O'); //�� ��������� ������� ���� �������
        }

        $dt = new \DateTime($utc, $utcTZ); //��������� ��� ����� UTC
//        $tz = new \DateTimeZone('Europe/Moscow'); // or whatever zone you're after
        $tz = new \DateTimeZone($timeZone); //������������ �� ����
        
        $dt->setTimezone($tz);
        
        return $dt->format('d.m.Y H:i:s'); //P ��� �������
    }

	//����������� ����� �� �������
	public static function PrepareAddress($data = FALSE) 
	{
		if(!$data) return;
		
		$full_address = '';
        if ($data['CITY'])
            $full_address .= GetMessage("TWINPX_PREFIX_CITY").$data['CITY'];

        if ($data['STREET'])
            $full_address .= ', '.GetMessage("TWINPX_PREFIX_STREET").$data['STREET'];

        if ($data['HOME'])
            $full_address .= ', '.GetMessage("TWINPX_PREFIX_HOME").$data['HOME'];

        if ($data['CORPS'])
            $full_address .= ', '.GetMessage("TWINPX_PREFIX_CORPS").$data['CORPS'];

        if ($data['APARTAMENT'])
            $full_address .= ', '.GetMessage("TWINPX_PREFIX_AP").$data['APARTAMENT'];
            
        return $full_address;
	}
	
	public static function GetOfferState($data)
    {
        $getQuery = array('request_id'=> $data);
        $getState = TwinpxApi::requestGet('/api/b2b/platform/request/info', $getQuery);

        if ($getState['SUCCESS']) {
            $result = array(
                'STATUS'     => $getState['DATA']['state']['status'],
                'DESCRIPTION'=> $getState['DATA']['state']['description']
            );
        }

        return $result;
    }

    public static function CancelOffer($data)
    {
        $getQuery = array('request_id'=> $data);
        $getState = TwinpxApi::requestGet('/api/b2b/platform/request/cancel', $getQuery);

        if ($getState['SUCCESS']) {
            $result = array(
                'STATUS'     => $getState['DATA']['status'],
                'DESCRIPTION'=> $getState['DATA']['description']
            );
        }

        return $result;
    }

    public static function GetBarcode($data)
    {
        $getQuery = array('request_ids' => array($data));
        $getState = TwinpxApi::requestPdf('/api/b2b/platform/request/generate-labels', $getQuery);

        if ($getState['SUCCESS']) {
            $result['SUCCESS'] = true;

            if($getState['DATA']['PDF'] AND file_exists($_SERVER["DOCUMENT_ROOT"].$getState['DATA']['PDF'])) {
                $result['DATA'] .= '<a class="btn btn-default ui-btn" target="_blank" href="'.$getState['DATA']['PDF'].'"> '.GetMessage("TWINPX_SKACATQ").'</a>';
            }
        }


        return $result;
    }    

	/**
	* 
	������� � ������
	*/
    public static function requestPost($uri, $data = array(), $oAuth = false)
    {
        $check = self::GetValue('Checkbox_Demo');
		$url = ($check === 'Y') ? self::$demourl : self::$url;
		$oAuth = ($oAuth) ? $oAuth : TwinpxApi::GetApiOAuth(); //���� ������� $oAuth �������
		
        $result = array('SUCCESS'=> false, 'DATA' => GetMessage("TWINPX_ERROR_POST"));
        $dataJson = \Bitrix\Main\Web\Json::encode($data);
        
        TwinpxApi::SetLogs($dataJson, '', 'requestPost.request'); //Log
        
        try {
            $path    = $url . $uri;
            $headers = array(
                "Authorization: Bearer ".$oAuth."",
                "Content-Type: application/json"
            );
            
            $curl = curl_init();
            curl_setopt($curl, CURLOPT_URL, $path);
            curl_setopt($curl, CURLOPT_ENCODING, 'gzip');
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $dataJson);

            //for debug only!
            //curl_setopt($curl, CURLOPT_HEADER, true);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

            $resp     = curl_exec($curl);
            if (!curl_errno($curl)) {
                $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
                $result['CODE'] = $http_code; 
            }
            curl_close($curl);
            
            $response = json_decode($resp, TRUE);
            
			//���� ������������ ������� ������
            if ($response['request_id']) {
            	TwinpxApi::SetLogs($resp, '', 'requestPost.response'); //Log
            	
                $result['SUCCESS'] = TRUE;
                if(LANG_CHARSET != 'UTF-8'){
			        $response = \Bitrix\Main\Text\Encoding::convertEncoding($response, "UTF-8", LANG_CHARSET); //��� ��������� windows-1251
				}
                $result['DATA'] = $response;
            }
            else {
            	//TwinpxApi::SetLogs($resp, '', 'requestPost.response'); //Log
                $result['SUCCESS'] = TRUE;
                if(LANG_CHARSET != 'UTF-8'){
			        $response = \Bitrix\Main\Text\Encoding::convertEncoding($response, "UTF-8", LANG_CHARSET); //��� ��������� windows-1251
				}
                $result['DATA'] = $response;
            }
            
            //���� �������� PVZ
            if($response['points']){
				$result['SUCCESS'] = TRUE;

                $result['DATA'] = $response['points'];
			}
			
			//���� �������� ������
			if($response['offers']){
				
				TwinpxApi::SetLogs($resp, '', 'requestPost.response'); //Log
				
				unset($_SESSION['JSON_ANSWER']);
				unset($_SESSION['JSON_REQUEST']);
				
				$result['SUCCESS'] = TRUE;
				if(LANG_CHARSET != 'UTF-8'){
			        $response = \Bitrix\Main\Text\Encoding::convertEncoding($response, "UTF-8", LANG_CHARSET); //��� ��������� windows-1251
				}
                $result['DATA'] = array($response['offers']);
                
            	$_SESSION['JSON_REQUEST'][] = $dataJson;
                $_SESSION['JSON_ANSWER'][] = $response['offers'];
			}

        } catch (Exception $e) {
            $result['DATA'] = GetMessage("REQUEST_SEND_QUERY");
        }

        return $result;
    }

    public static function requestGet($uri, $data = array())
    {
        $check = self::GetValue('Checkbox_Demo');
		$url = ($check === 'Y') ? self::$demourl : self::$url;
		
        $result = array('SUCCESS'=> false, 'DATA' => GetMessage('TWINPX_ERROR_POST'));
        
        TwinpxApi::SetLogs(\Bitrix\Main\Web\Json::encode($data), '', 'requestGet.request'); //Log
        
        try {
            $getQuery = http_build_query($data);
            $path     = $url . $uri . '?'.$getQuery;
            
            $headers  = array(
                "Authorization: Bearer ".TwinpxApi::GetApiOAuth()."",
                "Content-Type: application/json"
            );
            $curl = curl_init();
            curl_setopt($curl, CURLOPT_URL, $path);
            curl_setopt($curl, CURLOPT_ENCODING, 'gzip');
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

            //for debug only!
            //curl_setopt($curl, CURLOPT_HEADER, true);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

            $resp     = curl_exec($curl);
            curl_close($curl);
            
            $response = json_decode($resp, TRUE);
            
            TwinpxApi::SetLogs($resp, '', 'requestGet.response'); //Log
			
			//���������� �������
            if ($response['state']) {
                $result['SUCCESS'] = TRUE;
                if(LANG_CHARSET != 'UTF-8'){
			        $response = \Bitrix\Main\Text\Encoding::convertEncoding($response, "UTF-8", LANG_CHARSET); //��� ��������� windows-1251
				}
                $result['DATA'] = $response;
            }
			
			//
            if($response['status']) {
                $result['SUCCESS'] = TRUE;
                if(LANG_CHARSET != 'UTF-8'){
			        $response = \Bitrix\Main\Text\Encoding::convertEncoding($response, "UTF-8", LANG_CHARSET); //��� ��������� windows-1251
				}
                $result['DATA'] = $response;
            }
            
            //���� ������ ���� ��� ����������
            if(!empty($response['offers'])) {
                $result['SUCCESS'] = TRUE;
                if(LANG_CHARSET != 'UTF-8'){
			        $response = \Bitrix\Main\Text\Encoding::convertEncoding($response, "UTF-8", LANG_CHARSET); //��� ��������� windows-1251
				}
                $result['DATA'] = $response['offers'];
            }


        } catch (Exception $e) {
            $result['DATA'] = GetMessage('REQUEST_SEND_QUERY');
        }

        return $result;
    }

    public static function requestPdf($uri, $data = array())
    {
        $check = self::GetValue('Checkbox_Demo');
		$url = ($check === 'Y') ? self::$demourl : self::$url;
		
        $result = array('SUCCESS' => false, 'DATA' => GetMessage("TWINPX_ERROR_POST"));
        
        $dataJson = \Bitrix\Main\Web\Json::encode($data);
        
        TwinpxApi::SetLogs($dataJson, '', 'requestPdf.request'); //Log

        try {
            $path    = $url . $uri;
            $headers = array(
                "Authorization: Bearer ".TwinpxApi::GetApiOAuth()."",
                "Content-Type: application/json"
            );
            $curl = curl_init();
            curl_setopt($curl, CURLOPT_URL, $path);
            curl_setopt($curl, CURLOPT_ENCODING, 'gzip');
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $dataJson);

            //for debug only!
            //curl_setopt($curl, CURLOPT_HEADER, true);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

            $resp     = curl_exec($curl);
            curl_close($curl);
            $response = json_decode($resp, TRUE);
            
            TwinpxApi::SetLogs($response, '', 'requestPdf.response'); //Log
            
            if($response['error_details']) {
                $result['SUCCESS'] = TRUE; 
            } 
            else {

                $result['SUCCESS'] = TRUE; 

                if(\Bitrix\Main\IO\Directory::createDirectory(\Bitrix\Main\Application::getDocumentRoot().'/upload/barcode/')){
                    //��������� ���������� ����� PDF
                    $savePath = "/upload/barcode/" . $data['request_ids'][0] . ".pdf";
                    $imagePath = "/upload/barcode/" . $data['request_ids'][0] . ".jpg";

                    $result['DATA'] = array('PDF' => $savePath, 'IMG' => $imagePath);

                    $filePdf = file_put_contents($_SERVER["DOCUMENT_ROOT"] . $savePath, $resp);
                }
            }

        } catch (Exception $e) {
            $result['DATA'] = GetMessage("REQUEST_SEND_QUERY");
        }

        return $result;
    }    

    public static function multiRequest($uri, $data, $interval)
    {
        $check = self::GetValue('Checkbox_Demo');
		$url = ($check === 'Y') ? self::$demourl : self::$url;
		
        $result = array('SUCCESS'=> false, 'DATA' => GetMessage("TWINPX_ERROR_POST"));
        $error = TRUE;
        unset($_SESSION['JSON_ANSWER']);
		unset($_SESSION['JSON_REQUEST']);
		
        try {
            $multiple = curl_multi_init();
            $channels = array();
            
            TwinpxApi::SetLogs(\Bitrix\Main\Web\Json::encode($data), '', 'multiRequest.request'); //Log

            foreach ($interval as $i) {
                //����������� ���������
                $data['destination']['interval']['from'] = $i['from'];
                $data['destination']['interval']['to'] = $i['to'];
                $dataJson = \Bitrix\Main\Web\Json::encode($data); //������� json
                
                $path     = $url . $uri;
                $headers  = array(
                    "Authorization: Bearer ".TwinpxApi::GetApiOAuth()."",
                    "Content-Type: application/json"
                );
                $curl = curl_init();
                curl_setopt($curl, CURLOPT_URL, $path);
                curl_setopt($curl, CURLOPT_ENCODING, 'gzip');
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
                curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($curl, CURLOPT_POST, true);
                curl_setopt($curl, CURLOPT_POSTFIELDS, $dataJson);

                //for debug only!
                curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
                curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
                
                curl_multi_add_handle($multiple, $curl);

                $channels[ $data['destination']['interval']['from'] ] = $curl;
                $_SESSION['JSON_REQUEST'][ $data['destination']['interval']['from'] ] = $dataJson;

            }
            
            $active = null;
            do {
                $mrc = curl_multi_exec($multiple, $active);
            } while ($mrc == CURLM_CALL_MULTI_PERFORM);

            while ($active && $mrc == CURLM_OK) {
                if (curl_multi_select($multiple) == -1) {
                    continue;
                }
                do {
                    $mrc = curl_multi_exec($multiple, $active);
                } while ($mrc == CURLM_CALL_MULTI_PERFORM);
            }

            $result = array();
            
            foreach ($channels as $int => $channel) {
                $res = json_decode( curl_multi_getcontent($channel), TRUE );
				
                //���� ���� ������
                if ($res['offers']) {
                	TwinpxApi::SetLogs(curl_multi_getcontent($channel), '', 'multiRequest.response'); //Log
                	
                    $result['SUCCESS'] = TRUE;
                    if(LANG_CHARSET != 'UTF-8'){
				        $res = \Bitrix\Main\Text\Encoding::convertEncoding($res, "UTF-8", LANG_CHARSET); //��� ��������� windows-1251
					}
                    $result['DATA'][$int] = $res['offers'];

                    $_SESSION['JSON_ANSWER'][$int] = $res['offers'];
                    
                    $error = FALSE; //���� ���� ���� ���� �����
                } 

                //���� ���� ������ ��� ���������
                if($res['error_details']) {
                    TwinpxApi::SetLogs(curl_multi_getcontent($channel), '', 'multiRequest.response'); //Log
                    
                    $err_message[] = $res['error_details'];
                }

                //��� ������ 500
                if($res['message']) {
                    TwinpxApi::SetLogs(curl_multi_getcontent($channel), '', 'multiRequest.response'); //Log
                    
                    $err_message[] = $res['message'];
                }

                curl_multi_remove_handle($multiple, $channel);
            }

            curl_multi_close($multiple);//��������� curl
            
        } catch (Exception $e) {
            $result['DATA'] = GetMessage("REQUEST_SEND_QUERY");
        }

        if($error) {
            $result = array(
                'SUCCESS' => TRUE,
                'ERROR' => $err_message
            );
        }

        return $result;
    }

   }
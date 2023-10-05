<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

use Bitrix\Main\Context,
	Bitrix\Main\Request,
	Bitrix\Main\Web\Json,
	Bitrix\Main\Application,
	Bitrix\Main\Localization\Loc,
	Bitrix\Sale\Delivery\Services\Table;
use Twinpx\Yadelivery\TwinpxApi,
    Twinpx\Yadelivery\TwinpxConfigTable,
    Twinpx\Yadelivery\TwinpxOfferTable;

//init js lib
CJSCore::Init(array('twinpx_lib')); 
//init module
CModule::IncludeModule("twinpx.yadelivery");
CModule::IncludeModule("sale");

Loc::loadMessages(__FILE__);

$siteId = Context::getCurrent()->getSite();
$request = Context::getCurrent()->getRequest();
$session = Application::getInstance()->getSession();
$module_id = "twinpx.yadelivery";

if ($request->isPost()) {
	//сбрасываем оффер
    if ($request["action"] == 'reset') 
    {
        if($session->has('OFFER_ID')) {
        	$session->remove('OFFER_ID');
		}
    }

    //получаем список офферов
    if ($request["action"] == 'getOffer')
    {
        parse_str($request["fields"], $fields);
        if(LANG_CHARSET != 'UTF-8') {
        	$fields = \Bitrix\Main\Text\Encoding::convertEncoding($fields, "UTF-8", LANG_CHARSET); //для кодировки windows-1251
        }
        
        TwinpxApi::SetLogs($fields, '', 'getOffer.fields'); //
        
        $options = TwinpxConfigTable::GetAllOptions();
        $pTypeId = $fields['PERSON_TYPE']; //тип плательщика
        $thisPayID = $fields['PAY_SYSTEM_ID']; //
        $deliveryID = $fields['DELIVERY_ID']; //id доставки
        $payment = FALSE;
        $emptyFields = array();
        $errors = "";
        $status = "N";
        $output = array("STATUS" => $status);
        
        //ищем есть если поле адрес
        if ($pTypeId) {
            $dbProps = CSaleOrderProps::GetList(array("SORT"=> "ASC"), array("IS_ADDRESS" => "Y", "ACTIVE" => "Y", "PERSON_TYPE_ID" => $pTypeId), false, false, array());
            if ($prop = $dbProps->Fetch()) {
                $addressId = $prop['ID'];
            }
        }

        //получаем информацию по платежной системе
        if ($thisPayID > 0) {
            //если есть тип платежа
            if (strlen($options['Pay_'.$thisPayID]) > 0) {
                $payment = $options['Pay_'.$thisPayID];
            }
            else {
                $error[] = GetMessage('PaymentError');
            }
        }
        
        $location = ($options['PropCity_'.$pTypeId]) ? CSaleLocation::GetByID($fields['ORDER_PROP_'.$options['PropCity_'.$pTypeId]], LANGUAGE_ID) : FALSE;
        //получаем свойствы заказа
        //личные данные
        $fio      = ($options['PropFio_'.$pTypeId]) ? $fields["ORDER_PROP_".$options['PropFio_'.$pTypeId]] : false;
        $phone    = ($options['PropPhone_'.$pTypeId]) ? $fields["ORDER_PROP_".$options['PropPhone_'.$pTypeId]] : false;
        $email    = ($options['PropEmail_'.$pTypeId]) ? $fields["ORDER_PROP_".$options['PropEmail_'.$pTypeId]] : false;
        
        //данные доставки
        $city     = ($location) ? $location['CITY_NAME'] : false;
        $street   = ($options['PropStreet_'.$pTypeId]) ? $fields['ORDER_PROP_'.$options['PropStreet_'.$pTypeId]] : false;
        $home     = ($options['PropHome_'.$pTypeId]) ? $fields['ORDER_PROP_'.$options['PropHome_'.$pTypeId]] : false;
        $corps    = ($options['PropCorp_'.$pTypeId]) ? $fields['ORDER_PROP_'.$options['PropCorp_'.$pTypeId]] : false;
        $ap       = ($options['PropApartament_'.$pTypeId]) ? $fields['ORDER_PROP_'.$options['PropApartament_'.$pTypeId]] : false;
        
        $address  = ($addressId > 0) ? $fields['ORDER_PROP_'.$addressId] : false;
        
        //комменатрий
        if($options['PropComment_'.$pTypeId] == ''){ //если нет привязки комментарий не будет передаватся
			$comment = false;
		}
        elseif( $options['PropComment_'.$pTypeId] > 0 ) {
        	$comment  = $fields["ORDER_PROP_".$options['PropComment_'.$pTypeId]];
//        	$emptyFields['PropComment'] = "ORDER_PROP_".$options['PropComment_'.$pTypeId];
		}
		else {
        	$comment  = $fields["ORDER_DESCRIPTION"];
//        	$emptyFields['PropComment'] = "ORDER_DESCRIPTION";
		}
                
        if(strlen($city) < 1) {
			$error[] = GetMessage('RequireCity');
		}
		
		//фио
        if (strlen($fio) < 1) {
            $error[] = GetMessage('Require');
            $emptyFields['PropFio'] = "ORDER_PROP_".$options['PropFio_'.$pTypeId];
		}
		
		//телефон
		$phone = preg_replace('![^0-9+]+!', '', $phone); //чистим от лишних символов
        if (strlen($phone) < 1) {
            $error[] = GetMessage('Require');
		} 
		elseif(strlen($phone) > 12){
			$error[] = GetMessage('LengthPhone');
		}
        
		$emptyFields['PropFio'] = "ORDER_PROP_".$options['PropFio_'.$pTypeId];
        $emptyFields['PropPhone'] = "ORDER_PROP_".$options['PropPhone_'.$pTypeId];
        $emptyFields['PropEmail'] = "ORDER_PROP_".$options['PropEmail_'.$pTypeId];
        
        //адрес
        if ($addressId && isset($address)) {
            if (strlen($fields['ORDER_PROP_'.$addressId]) < 1) {
                $error[] = GetMessage('Require');
            }
            
            $emptyFields['PropAddress'] = "ORDER_PROP_".$addressId;
        }
        elseif ($street && $home && $ap) {
            if (strlen($street) < 1) {
                $error[] = GetMessage('Require');
            }
            if (strlen($home) < 1) {
                $error[] = GetMessage('Require');
            }
            if (strlen($ap) < 1) {
                $error[] = GetMessage('Require');
            }
            
            $emptyFields['PropStreet'] = "ORDER_PROP_".$options['PropStreet_'.$pTypeId];
            $emptyFields['PropHome'] = "ORDER_PROP_".$options['PropHome_'.$pTypeId];
            $emptyFields['PropCorp'] = "ORDER_PROP_".$options['PropCorp_'.$pTypeId];
            $emptyFields['PropAp'] = "ORDER_PROP_".$options['PropApartament_'.$pTypeId];
        }
        else {
            if (strlen($city) < 1 && $city) {
                $error[] = GetMessage('Require');
            }
            if (strlen($address) < 1 && $address) {
                $error[] = GetMessage('Require');
            }
            if (strlen($street) < 1 && $street) {
                $error[] = GetMessage('Require');
            }
            if (strlen($home) < 1 && $home) {
                $error[] = GetMessage('Require');
            }
            if (strlen($email) < 1 && $email) {
                $error[] = GetMessage('Require');
            }
            
            $emptyFields['PropStreet'] = "ORDER_PROP_".$options['PropStreet_'.$pTypeId];
            $emptyFields['PropHome'] = "ORDER_PROP_".$options['PropHome_'.$pTypeId];
            $emptyFields['PropCorp'] = "ORDER_PROP_".$options['PropCorp_'.$pTypeId];
            $emptyFields['PropAp'] = "ORDER_PROP_".$options['PropApartament_'.$pTypeId];
        }

		$cost = ($session->has('CURIER_PRICE')) ? intval($session->get('CURIER_PRICE')) : FALSE;
		$calculateError = FALSE;
        //если нет фик. считаем динамично
        if($cost === FALSE && strlen($city) > 1){
        	$arData = array(
        		'CITY'      => $city,
	            'PAYMENT_METHOD' => $payment,
	            'TARIFF'	=> 'time_interval'
        	);
			$prepareCalculator = TwinpxApi::PrepareDataCalculate($arData);			
			$price = TwinpxApi::requestPost('/api/b2b/platform/pricing-calculator', $prepareCalculator);
			if(strlen($price['DATA']['pricing_total']) > 1 && $price['SUCCESS']){
				$priceValue = explode(" ", $price['DATA']['pricing_total']);
				$cost = floatval($priceValue[0]);
				$session->set("CALCULATE_PRICE", $cost);
			}
			else {
				$calculateError = TRUE;
			    TwinpxApi::SetLogs($price, '', 'getOffer.calculate'); //            	
			}
		}
		
        if (!empty($error)) {
        	$status = "Y";
        	TwinpxApi::SetLogs($output, 'ERROR', 'getOffer.errors'); //
        	$error = array_unique($error);
        	$errors = implode('<br/>', $error);
        	//output
        	$output = array("STATUS" => $status, "FIELDS" => $emptyFields, "ERRORS" => $errors);
        }
        elseif($calculateError){
			$status = "Y";
            $errors = GetMessage('No-intervals');
            $output = array("STATUS" => $status, "OFFERS" => array(), "ERRORS" => $errors);
		}
        else {
        	//считаем наценку если она заданао
        	if($deliveryID){
				$rsProfile = Table::getList(array('filter' => array('ACTIVE' => 'Y', 'ID' => $deliveryID), 'select' => array('ID', 'PARENT_ID', 'CONFIG')));
				if($profile = $rsProfile->fetch())
				{
					$profileMargin = array('MARGIN_VALUE' => $profile['CONFIG']['MAIN']['MARGIN_VALUE'], 'MARGIN_TYPE' => $profile['CONFIG']['MAIN']['MARGIN_TYPE']);
					$rsDelivery = Table::getList(array('filter' => array('ACTIVE' => 'Y', 'ID' => $profile['PARENT_ID']), 'select' => array('ID', 'PARENT_ID', 'CONFIG')));
					if($delivery = $rsDelivery->fetch())
					{
						$deliveryMargin = array('MARGIN_VALUE' => $delivery['CONFIG']['MAIN']['MARGIN_VALUE'], 'MARGIN_TYPE' => $delivery['CONFIG']['MAIN']['MARGIN_TYPE']);
						
					}
				}
				
				//наценка доставки
				if($deliveryMargin['MARGIN_TYPE'] == 'CURRENCY'){
					$cost = $cost + intval($deliveryMargin['MARGIN_VALUE']);
				}
				else {
					$cost = $cost + ( $cost * (intval($deliveryMargin['MARGIN_VALUE']) / 100 ));
				}
				
				//наценка профиля
				if($profileMargin['MARGIN_TYPE'] == 'CURRENCY'){
					$cost = $cost + intval($profileMargin['MARGIN_VALUE']);
				}
				else {
					$cost = $cost + ( $cost * (intval($profileMargin['MARGIN_VALUE']) / 100 ));
				}				
				
				$cost = ($cost >=0 ) ? $cost : 0;
				$session->set('MARGIN_VALUE', $cost); //сохраняем наценку
			}
        	
        	//массив с данные
        	$arFields = array(
	            'FIO'       => explode(" ", $fio),
	            'PHONE'     => $phone,
	            'EMAIL'     => $email,

	            'CITY'      => $city,
	            'STREET'    => $street,
	            'HOME'      => $home,
	            'CORPS'     => $corps,
	            'APARTAMENT'=> $ap,
	            
	            'FULL_ADDRESS' => $address,

	            'COMMENT'   => $comment,
	            'PAYMENT'	=> $payment,
	            
	            'FIX_PRICE'	=> $cost,
	        );
	        
            $prepare = TwinpxApi::PrepareData($arFields);
            $full_address = ($addressId > 0) ? $city.', '.$address : TwinpxApi::PrepareAddress($arFields);
            
            $getInterval = TwinpxApi::GetInterval($full_address); //получаем доступные интервалы           
            $generateInterval = TwinpxApi::GenerateInterval($location); //создаем наши интервалы
			$intervals = (!empty($getInterval)) ? $getInterval : $generateInterval; //проверяем если если получаем интервалы проверяем по ними, если нет наши интервалы генерируем
           
            $offer   = TwinpxApi::multiRequest('/api/b2b/platform/offers/create', $prepare, $intervals);
            if ($offer['SUCCESS'] AND !empty($offer['DATA'])) {
                $status = "Y";
                $result = TwinpxApi::ShowOfferJson($offer['DATA']);
                //output
                $output = array("STATUS" => $status, "OFFERS" => $result, "ERRORS" => $errors);
            } 
            elseif($offer['SUCCESS'] AND !empty($offer['ERROR'])) {
                $adr = FALSE;
                foreach($offer['ERROR'] as $value){
                    if ( is_array($value) && in_array ( "cannot parse destination info" , $value ) ) {
                        $adr = TRUE;
                    }
                }
                //если нашли ошибку адреса
                if($adr) {
                    TwinpxApi::SetLogs($offer, '', 'getOffer.address'); //
                	$status = "Y";
                    $errors = GetMessage('Wrong-Address');
                    //output
                    $output = array("STATUS" => $status, "FIELDS" => $emptyFields, "ERRORS" => $errors);
                } 
                else {
                	TwinpxApi::SetLogs($offer, '', 'getOffer.nooffer'); //
                	$status = "Y";
                    $errors = GetMessage('No-intervals');
                    $output = array("STATUS" => $status, "OFFERS" => array(), "ERRORS" => $errors);
                }
            }
            else {
            	TwinpxApi::SetLogs($offer, '', 'getOffer.nooffer'); //
            	$status = "Y";
                $errors = GetMessage('No-intervals');
                $output = array("STATUS" => $status, "OFFERS" => array(), "ERRORS" => $errors);
            }
        }
        
		echo \Bitrix\Main\Web\Json::encode($output);
    }

    //записываем цену в доставки
    if ($request["action"] == 'price') 
    {
        $price   = floatval($request['price']);
        $offerID = $request['offer'];
        $offerExpire = $request['expire'];
		
		$session->set('SETPRICE', $price);
		$session->set('OFFER_ID', $offerID);
		$session->set('OFFER_EXPIRE', $offerExpire);
    }
    
    //возврат адреса региона из битрикс
    if ($request["action"] == 'getRegion') 
    {
        parse_str($request["fields"], $fields);
        if(LANG_CHARSET != 'UTF-8') {
        	$fields = \Bitrix\Main\Text\Encoding::convertEncoding($fields, "UTF-8", LANG_CHARSET); //для кодировки windows-1251
        }
        TwinpxApi::SetLogs($fields, '', 'getRegion.fields'); //
        
        $options      = TwinpxConfigTable::GetAllOptions();
        $pTypeId      = $fields['PERSON_TYPE']; //тип плательщика
        if($pTypeId > 0) {
        	$session->set('PERSON_TYPE', $pTypeId);
		}
        $status       = "N";
        $full_address = '';
        $error = array();

        $location     = ($options['PropCity_'.$pTypeId] > 0) ? CSaleLocation::GetByID($fields['ORDER_PROP_'.$options['PropCity_'.$pTypeId]], LANGUAGE_ID) : CSaleLocation::GetByID("0000073738", LANGUAGE_ID); //если не указан регион передаем москва
        
        if ($location) {
            $status = "Y";
            if ($location['CITY_NAME_LANG']) $arAddress[] = $location['CITY_NAME_LANG'];
            if ($location['REGION_NAME_LANG']) $arAddress[] = $location['REGION_NAME_LANG'];
            if ($location['COUNTRY_NAME_LANG']) $arAddress[] = $location['COUNTRY_NAME_LANG'];

            $full_address = implode(", ", $arAddress);            
        }
        elseif($options['PropCity_'.$pTypeId] > 0 && $location === FALSE) {
			$status = "Y";
			if(strlen($fields['ORDER_PROP_'.$options['PropCity_'.$pTypeId]])>1){
				$full_address = $fields['ORDER_PROP_'.$options['PropCity_'.$pTypeId]];
			} else {
				$error[] = GetMessage('RequireCity');
				TwinpxApi::SetLogs($location, '', 'getRegion.location'); //
			}
		}
        else {
        	$status = "Y";
			$error[] = GetMessage('RequireCity');
			TwinpxApi::SetLogs($location, '', 'getRegion.location'); //
		}
        
        $thisPayID = intval($fields['PAY_SYSTEM_ID']); //
    	$payment = false;
    	if($thisPayID > 0) {
			//если есть тип платежа
			if(strlen($options['Pay_'.$thisPayID]) > 0){
				$payment = $options['Pay_'.$thisPayID];
			} 
			else {
				$status = "Y";
				$error[] = GetMessage('PaymentError');
				TwinpxApi::SetLogs(array(GetMessage('PaymentError')), '', 'getRegion.payment');
			}
		}

        echo \Bitrix\Main\Web\Json::encode(array("STATUS"=>$status, "REGION"=>$full_address, "PAYMENT"=>$payment, "ERRORS" => implode("<br/>", $error)));
    }
    
	//получение список ПВЗ для региона
    if ($request["action"] == 'getPoints') 
    {
    	parse_str($request["fields"], $fields);
    	if(LANG_CHARSET != 'UTF-8') {
        	$fields = \Bitrix\Main\Text\Encoding::convertEncoding($fields, "UTF-8", LANG_CHARSET); //для кодировки windows-1251
        }
        TwinpxApi::SetLogs($fields, '', 'getPoints.fields');
        
    	$status = "N";
    	$errors = false;
    	$points = false;
    	$error = array();
    	$allPoints = array();
    	$type = array(
    		"pickup_point" => GetMessage('Pickup-point'),
			"terminal" => GetMessage('Terminal'),
			"post_office" => GetMessage('Post-office'),
			"sorting_center" => GetMessage('Sorting-center'),
			"warehouse" => GetMessage('Warehouse'),
    	);
    	
    	if($fields['payment'] != 'false'){
		    $queryPvz = array(
		        "available_for_dropoff"=> FALSE,
		        "payment_method"       => $fields['payment'],
		        "latitude"             => ["from" => floatval($fields['lat-from']), "to" => floatval($fields['lat-to'])],
		        "longitude"            => ["from" => floatval($fields['lon-from']), "to" => floatval($fields['lon-to'])],
		    );
		    $response = TwinpxApi::requestPost("/api/b2b/platform/pickup-points/list", $queryPvz);
		} 
		else {
			$error[] = GetMessage('PaymentError');
			TwinpxApi::SetLogs(array(GetMessage('PaymentError')), '', 'getPoints.payment');
		}
		
    	//обработка результата
    	if($response['SUCCESS'] && isset($response['DATA']['error'])){
			$status = "Y";
			$error[] = $response['DATA']['error'];
			TwinpxApi::SetLogs($response['DATA']['error'], '', 'getPoints.error');
		}
    	elseif($response['SUCCESS'] && !isset($response['DATA']['message'])){ // [message] => object_not_found
			$status = "Y";
			foreach($response['DATA'] as $point){
				if($point['type'] == 'sorting_center') continue;
				$data = array(
                	'id'		=> $point['id'],
                	'title' 		=> $point['name'],
                	'type'			=> ($type[$point['type']] != NULL) ? $type[$point['type']] : '',
                	'address'		=> ($point['address']['full_address']) ? $point['address']['full_address'] : $point['address']['locality'].', '.$point['address']['street'].', '.$point['address']['house'],
				);
				$jsonData = \Bitrix\Main\Web\Json::encode($data);//передаем данные
				$hash = md5($point['name'].$point['type'].$point['position']['latitude'].$point['position']['longitude']); //хэш значение для исключение одинаковых точек
				$allPoints[$hash] = array(
					"id" => $point['id'],
				    "title" => $point['name'],
				    "type" => ($type[$point['type']] != NULL) ? $type[$point['type']] : '',
				    "schedule" => TwinpxApi::GenerateSchedule($point['schedule']['restrictions']),
				    "address" => ($point['address']['full_address']) ? $point['address']['full_address'] : $point['address']['locality'].', '.$point['address']['street'].', '.$point['address']['house'],
				    "coords" => array($point['position']['latitude'], $point['position']['longitude']),
				    "json" => $jsonData
				);
			}
		} 
		else {
			$status = "Y";
			$error[] = GetMessage($response['DATA']['message']);
			TwinpxApi::SetLogs($response['DATA']['message'], '', 'getPoints.error');
		}
		
		if(!empty($allPoints)) $points = array_values($allPoints);
		if(!empty($error)) $errors = implode('<br/>', $error);
    	
    	echo \Bitrix\Main\Web\Json::encode(array("STATUS"=> $status, "POINTS"=> $points, "ERRORS" => $errors));
    }
    
    //получение список офферов для ПВЗ
    if ($request["action"] == 'pvzOffer') 
    {
    	parse_str($request["fields"], $fields);
		if(LANG_CHARSET != 'UTF-8') {
        	$fields = \Bitrix\Main\Text\Encoding::convertEncoding($fields, "UTF-8", LANG_CHARSET); //для кодировки windows-1251
        }
        TwinpxApi::SetLogs($fields, '', 'pvzOffer.fields');
        
    	$options = TwinpxConfigTable::GetAllOptions();
        $pTypeId = $fields['PERSON_TYPE']; //тип плательщика
        $thisPayID = $fields['PAY_SYSTEM_ID']; //
        $deliveryID = $fields['DELIVERY_ID']; //id доставки
        $pvzId = $fields['id'];
        $pvzAddress = $fields['address'];
    	$status = "N";
    	$points = array();
    	$error = $log = array();
    	$emptyFields = array();
    	
    	$log[] = 'PERSON_TYPE:'.$pTypeId;
    	$log[] = 'PAY_SYSTEM_ID:'.$thisPayID;
    	if ($pTypeId) {
            $dbProps = CSaleOrderProps::GetList(array("SORT"=> "ASC"), array("IS_ADDRESS" => "Y", "ACTIVE" => "Y", "PERSON_TYPE_ID" => $pTypeId), false, false, array());
            if ($prop = $dbProps->Fetch()) {
                $addressId = $prop['ID'];
                $log[] = 'ADDRESS_ID:'.$addressId;
            }
        }
        
        //получаем свойствы заказа
        //личные данные
        $fio      = ($options['PropFio_'.$pTypeId]) ? $fields["ORDER_PROP_".$options['PropFio_'.$pTypeId]] : false;
        $phone    = ($options['PropPhone_'.$pTypeId]) ? $fields["ORDER_PROP_".$options['PropPhone_'.$pTypeId]] : false;
        $email    = ($options['PropEmail_'.$pTypeId]) ? $fields["ORDER_PROP_".$options['PropEmail_'.$pTypeId]] : false;
        //$comment  = ($options['PropEmail_'.$pTypeId]) ? $fields["ORDER_PROP_".$options['PropEmail_'.$pTypeId]] : false;
        
        $emptyFields['PropFio'] = "ORDER_PROP_".$options['PropFio_'.$pTypeId];
        $emptyFields['PropPhone'] = "ORDER_PROP_".$options['PropPhone_'.$pTypeId];
        $emptyFields['PropEmail'] = "ORDER_PROP_".$options['PropEmail_'.$pTypeId];
        $emptyFields['PropAddress'] = ($addressId > 0 ) ? "ORDER_PROP_".$addressId : '';
        
        //комменатрий
        $comment  = $fields["ORDER_DESCRIPTION"];
        
        
        //получаем информацию по платежной системе
		if($thisPayID > 0) {
			//если есть тип платежа
			if(strlen($options['Pay_'.$thisPayID]) > 0){
				$payment = $options['Pay_'.$thisPayID];
			} 
			else {
				$error[] = 	GetMessage('PaymentError');
				$log[] = 'PAYMENT:NOT_SUPPORT';
			}
		}

        //фио
		if (strlen($fio) < 1) {
            $error[] = GetMessage('Require');
            $log[] = 'FIO_REQUIRE:'.$fio;
		}
		
		//телефон
		$phone = preg_replace('![^0-9+]+!', '', $phone); //чистим от лишних символов
        if (strlen($phone) < 1) {
            $error[] = GetMessage('Require');
            $log[] = 'PHONE_REQUIRE:'.$phone;
		} 
		elseif(strlen($phone) > 12){
			$error[] = GetMessage('LengthPhone');
			$log[] = 'PHONE_LENGTH:'.$phone;
		}

		$cost = ($session->has('PICKUP_PRICE')) ? intval($session->get('PICKUP_PRICE')) : FALSE;
		$calculateError = FALSE;
        //если нет фик. считаем динамично
        if($cost === FALSE && strlen($pvzAddress) > 1){
        	$arData = array(
        		'CITY'      => $pvzAddress,
	            'PAYMENT_METHOD' => $payment,
	            'TARIFF'	=> 'self_pickup'
        	);
			$prepareCalculator = TwinpxApi::PrepareDataCalculate($arData);			
			$price = TwinpxApi::requestPost('/api/b2b/platform/pricing-calculator', $prepareCalculator);			
			if(strlen($price['DATA']['pricing_total']) > 1 && $price['SUCCESS']){
				$priceValue = explode(" ", $price['DATA']['pricing_total']);
				$cost = floatval($priceValue[0]);
				$session->set("CALCULATE_PRICE", $cost);
			}
			else {
				$calculateError = TRUE;
			    TwinpxApi::SetLogs($price, '', 'pvzOffer.calculate'); //            	
			}
		}
        
        
    	if (!empty($error)) {
        	TwinpxApi::SetLogs($error, '', 'pvzOffer.error');
        	//TwinpxApi::SetLogs($log, '', 'pvzOffer.log');
    		$status = "Y";
        	$error = array_unique($error);
        	$errors = implode('<br/>', $error);
        	//output
        	$output = array("STATUS" => $status, "FIELDS" => $emptyFields, "ERRORS" => $errors);
        } 
        elseif($calculateError){
			$status = "Y";
            $errors = GetMessage('No-intervals');
            $output = array("STATUS" => $status, "OFFERS" => array(), "ERRORS" => $errors);
		}
        else {
        	//считаем наценку если она заданао
        	if($deliveryID){
				$rsProfile = Table::getList(array('filter' => array('ACTIVE' => 'Y', 'ID' => $deliveryID), 'select' => array('ID', 'PARENT_ID', 'CONFIG')));
				if($profile = $rsProfile->fetch())
				{
					$profileMargin = array('MARGIN_VALUE' => $profile['CONFIG']['MAIN']['MARGIN_VALUE'], 'MARGIN_TYPE' => $profile['CONFIG']['MAIN']['MARGIN_TYPE']);
					$rsDelivery = Table::getList(array('filter' => array('ACTIVE' => 'Y', 'ID' => $profile['PARENT_ID']), 'select' => array('ID', 'PARENT_ID', 'CONFIG')));
					if($delivery = $rsDelivery->fetch())
					{
						$deliveryMargin = array('MARGIN_VALUE' => $delivery['CONFIG']['MAIN']['MARGIN_VALUE'], 'MARGIN_TYPE' => $delivery['CONFIG']['MAIN']['MARGIN_TYPE']);
						
					}
				}
				
				//наценка доставки
				if($deliveryMargin['MARGIN_TYPE'] == 'CURRENCY'){
					$cost = $cost + floatval($deliveryMargin['MARGIN_VALUE']);
				}
				else {
					$cost = $cost + ( $cost * (floatval($deliveryMargin['MARGIN_VALUE']) / 100 ));
				}
				
				//наценка профиля
				if($profileMargin['MARGIN_TYPE'] == 'CURRENCY'){
					$cost = $cost + floatval($profileMargin['MARGIN_VALUE']);
				}
				else {
					$cost = $cost + ( $cost * (floatval($profileMargin['MARGIN_VALUE']) / 100 ));
				}
				
				$cost = ($cost >=0 ) ? $cost : 0;
				$session->set('MARGIN_VALUE', $cost); //сохраняем наценку
			}
			
			$arFields = array(
	            'FIO'       	=> explode(" ", $fio),
	            'PHONE'     	=> $phone,
	            'EMAIL'			=> $email,
	            
	            'COMMENT'   	=> $comment,
	            
	            'PVZ_ID'		=> $pvzId,
	            'FULL_ADDRESS'	=> $pvzAddress,
	            
	            'PAYMENT'		=> $payment,
	            
	            'FIX_PRICE'		=> $cost
	        );
        	
        	$prepare = TwinpxApi::PrepareData($arFields);	
        	$offer   = TwinpxApi::requestPost('/api/b2b/platform/offers/create', $prepare); //запрос
        	
        	if ($offer['SUCCESS'] && !empty($offer['DATA']) && empty($offer['DATA']['error'])) {
                $status = "Y";
                $result = TwinpxApi::ShowOfferJson($offer['DATA'][0], $pvzId);
                //output
                $output = array("STATUS" => $status, "OFFERS" => $result, "ERRORS" => $errors);
            } 
            elseif($offer['SUCCESS'] AND !empty($offer['ERROR'])) {
                $adr = FALSE;
                foreach($offer['ERROR'] as $value){
                    if ( in_array ( "incorrect delivery address or house number not stated, please check" , $value ) ) {
                        $adr = TRUE;
                    }
                }
                //если нашли ошибку адреса
                if($adr) {
                	$status = "Y";
                    $errors = GetMessage('Wrong-Address');
                    //output
                    $output = array("STATUS" => $status, "OFFERS" => array(), "ERRORS" => $errors);
                } 
                else {
                	$status = "Y";
                    $errors = GetMessage('No-intervals');
                    //output
                    $output = array("STATUS" => $status, "OFFERS" => array(), "ERRORS" => $errors);
                }
            }
            else {
            	$status = "Y";
                $errors = GetMessage('No-intervals');
                //output
                $output = array("STATUS" => $status, "OFFERS" => array(), "ERRORS" => $errors);
            }
        }
        
    	echo \Bitrix\Main\Web\Json::encode($output);
    }
    
	//передаем цену для доставки
	if($request["action"] == 'setOfferPrice') 
	{
		$fields = \Bitrix\Main\Web\Json::decode($request["fields"]); //парсим JSON
		$status = "Y";
		
		$price   = floatval($fields['offer_price']);
		$cost   = floatval($fields['cost']);
        $offerID = ($fields['offer_id']) ? $fields['offer_id'] : FALSE;
        $pvzID = ($fields['offer_pvz']) ? $fields['offer_pvz'] : FALSE;
        $offerExpire = ($fields['offer_expire']) ? $fields['offer_expire'] : FALSE;
        
        $price = ($session->has('CALCULATE_PRICE')) ? $session->get('CALCULATE_PRICE') : $price;
        
        $emptyFields = array();
        
        if ($session->has('PERSON_TYPE') && $session->get('PERSON_TYPE') > 0) {
            $options = TwinpxConfigTable::GetAllOptions(); //настройки
            $pTypeId = $session->get('PERSON_TYPE');
            $dbProps = CSaleOrderProps::GetList(array("SORT"=> "ASC"), array("IS_ADDRESS" => "Y", "ACTIVE" => "Y", "PERSON_TYPE_ID" => $pTypeId), false, false, array());
            if ($prop = $dbProps->Fetch()) {
                $addressId = $prop['ID'];
            }
            
            $emptyFields['PropFio'] = "ORDER_PROP_".$options['PropFio_'.$pTypeId];
	        $emptyFields['PropPhone'] = "ORDER_PROP_".$options['PropPhone_'.$pTypeId];
	        $emptyFields['PropEmail'] = "ORDER_PROP_".$options['PropEmail_'.$pTypeId];
	        $emptyFields['PropAddress'] = ($addressId > 0 ) ? "ORDER_PROP_".$addressId : null;
        }
		
		//задаем цену в зависимомть от типа
		if($pvzID){
			$session->set('PVZPRICE', $price);
		} else {
        	$session->set('CURIERPRICE', $price);
		}
        
        $session->set('PVZ_ID', $pvzID);
        $session->set('OFFER_ID', $offerID);
        $session->set('OFFER_EXPIRE', $offerExpire);
        
        $session->remove('PERSON_TYPE');
		
		echo \Bitrix\Main\Web\Json::encode(array("STATUS" => $status, "FIELDS" => $emptyFields));
	}
	
	//записываем оффер
	if($request["action"] == 'setDelivery') 
	{
		$params = \Bitrix\Main\Web\Json::decode($request->get("data"));
		$order_id = $params['order_id'];
		$offer_id = $params['offer_id'];
		$location = ($session->has('LOCATION_CODE')) ? $session->get('LOCATION_CODE') : '_';
		$full_address = ($session->has('FULL_ADDRESS')) ? $session->get('FULL_ADDRESS') : '';
		
		$deliveryInterval = '';
	    foreach ($session->get('JSON_ANSWER') as $json_answer) {
	        foreach ($json_answer as $answer) {
	            if ($answer['offer_id'] == $offer_id) {
	                $start           = TwinpxApi::PrepareDataTime($answer['offer_details']['delivery_interval']['min']);
	                $end             = TwinpxApi::PrepareDataTime($answer['offer_details']['delivery_interval']['max']);
	                $deliveryInterval= $start . ' - ' . $end;
	            }
	        }
	    }
	     
	    //приготовим данные для записи в таблицу
	    $data = array(
	        'ORDER_ID'         	=> $order_id,
	        'ORDER_DATE'       	=> new \Bitrix\Main\Type\DateTime(),
	        'OFFER_ID'         	=> $offer_id,
	        'ADDRESS'          	=> $full_address,
	        'LOCATION'         	=> $location,
	        'JSON_REQUEST'     	=> ($session->has('JSON_REQUEST')) ? serialize($session->get('JSON_REQUEST')) : null,
	        'JSON_RESPONS'     	=> ($session->has('JSON_ANSWER')) ? serialize($session->get('JSON_ANSWER')) : null,
	        'DELIVERY_INTERVAL'	=> $deliveryInterval,
	        'BARCODE'			=> $session->get('YDELIVERY_BARCODE')
	    );
	    $r = TwinpxOfferTable::add($data); //создаем записи
	    
	   	
		$offerRequest = array("offer_id"=> $offer_id);
	    $create = TwinpxApi::requestPost('/api/b2b/platform/offers/confirm', $offerRequest); //бронируем оффер
	    
	    //если получили ответ
	    if ($create['SUCCESS'] AND $create['DATA']['request_id']) {
	    	$requestID = $create['DATA']['request_id'];
	        $data = array('REQUEST_ID'=> $requestID);
	        TwinpxOfferTable::update($r->GetID(), $data);
	        
	        //записи статуса доставки
	        $state = TwinpxApi::GetOfferState($requestID);
	        if ($state['STATUS']) {
	            $data = array(
	                'STATUS'            => $state['STATUS'],
	                'STATUS_DESCRIPTION'=> $state['DESCRIPTION']
	            );
	            TwinpxOfferTable::update($r->GetID(), $data);
	        }
	        
	        //получаем ID и название наще доставки
	        $rsDelivery = \Bitrix\Sale\Delivery\Services\Table::getList(array('filter' => array('ACTIVE'=>'Y', '=CODE' => 'twpx_yadelivery'), 'select' => array('ID', 'NAME')));
			if ($delivery = $rsDelivery->fetch()){
				$rsProfile = \Bitrix\Sale\Delivery\Services\Table::getList(array(
					'filter' => array('ACTIVE' => 'Y', 'PARENT_ID'=> $delivery['ID'], '=CODE' => 'twpx_yadelivery:pickup'),
					'select' => array('*')
				));
				$profile = $rsProfile->fetch();//информация о доставке
			}
	               
	        //если нашли достаку
	        if($delivery) {
				//получаем корзину
		        $order = \Bitrix\Sale\Order::load($order_id);
				$shipmentCollection = $order->getShipmentCollection();
				foreach ($shipmentCollection as $shipment) {
					if($shipment->isSystem())
						continue;
					$shipment->setFields(array(
						'DELIVERY_ID'  => $profile['ID'],
						'DELIVERY_NAME' => $delivery['NAME'].' ('.$profile['NAME'].')'
					));
					$shipment->allowDelivery();
				}
				$res = $order->save();
				if (!$res->isSuccess()) {
					TwinpxApi::SetLogs($res->getErrors(), '', 'setDelivery.ordersave');
				}
			}
			
	        $result = array('SUCCESS' => 'Y'); //успешно
	    }
	    else {    	
	        $data = array(
	            'STATUS'            => 'CREATED_ERROR',
	            'STATUS_DESCRIPTION'=> GetMessage("TWINPX_YADELIVERY_OSIBKA_SOZDANIA_ZAAV")
	        );
	        TwinpxOfferTable::update($r->GetID(), $data);
	        
	        $result = array('SUCCESS' => 'N', 'ERROR' => GetMessage("TWINPX_YADELIVERY_PROIZOSLA_OSIBKA_BRO")); //ошибка
	    }
	    
	    echo \Bitrix\Main\Web\Json::encode($result);
	}

	//запоминаем ID ПВЗ
    if ($request["action"] == 'setPvzId') 
    {
        $params = \Bitrix\Main\Web\Json::decode($request->get("json"));        
        $pvzId = $params['id'];
        $address = $params['address'];
        
        $session->set('PVZ_ID_SIMPLE', $pvzId);
        $session->set('PVZ_ADDRESS', $address);
        
        $emptyFields = array();
        
        if ($session->has('PERSON_TYPE') && $session->get('PERSON_TYPE') > 0) {
            //$options = TwinpxConfigTable::GetAllOptions(); //настройки
            $pTypeId = $session->get('PERSON_TYPE');
            $dbProps = CSaleOrderProps::GetList(array("SORT"=> "ASC"), array("IS_ADDRESS" => "Y", "ACTIVE" => "Y", "PERSON_TYPE_ID" => $pTypeId), false, false, array());
            if ($prop = $dbProps->Fetch()) {
                $addressId = $prop['ID'];
            }
            
	        $emptyFields['PropAddress'] = ($addressId > 0 ) ? "ORDER_PROP_".$addressId : null;
        }
        
        $session->remove('PERSON_TYPE');
        
        echo \Bitrix\Main\Web\Json::encode(array("STATUS" => 'Y', "FIELDS" => $emptyFields));
    }
} 
else {
    echo GetMessage('Error');
}


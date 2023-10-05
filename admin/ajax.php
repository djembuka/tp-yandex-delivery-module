<?php
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php");

use	Bitrix\Main\Loader,
	Bitrix\Main\Server,
	Bitrix\Main\Request,
	Bitrix\Main\Context,
	Bitrix\Main\Web\Json,
	Bitrix\Main\Application,
	Bitrix\Main\Localization\Loc,
	Bitrix\Sale;

use Twinpx\Yadelivery\TwinpxApi,
	Twinpx\Yadelivery\TwinpxOfferTable,
	Twinpx\Yadelivery\TwinpxOfferTempTable,
	Twinpx\Yadelivery\TwinpxConfigTable;

//init module
Loader::IncludeModule("sale");
Loader::IncludeModule("twinpx.yadelivery");
CJSCore::Init(array("twinpx_admin_lib")); //init js lib

Loc::loadMessages(__FILE__);

$siteId = Context::getCurrent()->getSite();
$request = Context::getCurrent()->getRequest();
$session = Application::getInstance()->getSession();

//новый офферы
if ($request->isPost()) {
		
	if ($request->get("action") == 'new') 
	{
		$itemId = $request->get("itemID");
		$detail = TwinpxOfferTable::getList(array('select' => array('*'),'filter' => array('ID'=> $itemId)))->fetch();
		
		$pvz = ($detail['PVZ_ID']) ? $detail['PVZ_ID'] : FALSE;
		$order_id = intval($detail['ORDER_ID']);
		
		$operator_request_id = ($order_id > 0) ? uniqid($order_id . "_") : uniqid();
        $barcode = TwinpxApi::GetPlaceBarcode($order_id);
        $cost = $detail['PRICE_DELIVERY'];
                
		$json_request = unserialize($detail['JSON_REQUEST']);
		$first_request = json_decode(current($json_request), true);
		if(LANG_CHARSET != 'UTF-8') {
        	$first_request = \Bitrix\Main\Text\Encoding::convertEncoding($first_request, "UTF-8", LANG_CHARSET); //для кодировки windows-1251
        }
        
        $first_request['info']['operator_request_id'] = $operator_request_id; //задаем уникальный ключ
		$first_request['places'][0]['barcode'] = $barcode;
               
        //получаем товары и записываем для них новый баркод
        $items = $first_request['items'];
        $newItem = array();
        foreach($items as $i => $value){
        	$value['place_barcode'] = $barcode;
			$newItem[$i] = $value;
		}		
		$first_request['items'] = $newItem;
		
		$session->remove('PICKUP_PRICE');
		$session->remove('CURIER_PRICE');
		if($pvz) {
			$session->set('PICKUP_PRICE', $cost);
			$offer   = TwinpxApi::requestPost('/api/b2b/platform/offers/create', $first_request);
			$offer['DATA'] = $offer['DATA'][0];
		}
		else {
			$session->set('CURIER_PRICE', $cost);
			$location['CODE'] = $detail['LOCATION'];
			$generateInterval= TwinpxApi::GenerateInterval($location); //новые интервалы
			$full_address = $detail['ADDRESS'];
			$getInterval = TwinpxApi::GetInterval($full_address);
			$intervals = (!empty($getInterval)) ? $getInterval : $generateInterval;
			
			$offer   = TwinpxApi::multiRequest('/api/b2b/platform/offers/create', $first_request, $intervals);	
		}
		
	 	if ($offer['SUCCESS'] AND !empty($offer['DATA'])) {
            $status = "Y";
            $result = TwinpxApi::ShowOfferJson($offer['DATA'], $pvz, $order_id);
        } 
        elseif($offer['SUCCESS'] AND !empty($offer['ERROR'])) {
            $adr = FALSE;
            $auth = FALSE;
            foreach($offer['ERROR'] as $value){
                if ( is_array($value) && in_array("cannot parse destination info", $value) ) {
                    $adr = TRUE;
                }
                elseif("Not authorized request" == $value){
					$auth = TRUE;
				}
            }
            //если нашли ошибку адреса
            if($adr) {
            	$status = "Y";
                $errors = GetMessage('TWINPX_YADELIVERY_NE_UDALOSQ_POLUCITQ');
            }
            elseif($auth){
				$status = "Y";
                $errors = GetMessage('TWINPX_YADELIVERY_AUTH');
			}
            else {
            	$status = "Y";
                $errors = GetMessage('TWINPX_YADELIVERY_NET_DOSTUPNYH_INTERV');
            }
        }
        else {
        	$status = "Y";
            $errors = GetMessage('TWINPX_YADELIVERY_NET_DOSTUPNYH_INTERV');
        }
	    		
		echo \Bitrix\Main\Web\Json::encode(array("STATUS" => $status, "OFFERS" => $result, "ERRORS" => $errors));	
	}

	//обновление статуса
	if ($request->get("action") == 'update') 
	{
		$itemId = $request->get("itemID");
		$detail = TwinpxOfferTable::getList(array('select' => array('*'),'filter' => array('ID'=> $itemId)))->fetch();
		$requestID = $detail['REQUEST_ID'];
		
	    $state = TwinpxApi::GetOfferState($requestID);
	    
	    if ($state['STATUS']) {
	        $data = array(
	            'STATUS'            => $state['STATUS'],
	            'STATUS_DESCRIPTION'=> $state['DESCRIPTION']
	        );
	        TwinpxOfferTable::update($detail['ID'], $data);
	        $html = '<p>'.$state['DESCRIPTION'].'</p>';
	    } 
	    else {
	        $html = '<p>'.GetMessage('ERROR').'</p>';
		}
	    
	    echo $html;
	}

	//обновление все офферы
	if ($request->get("action") == 'updateAll') 
	{
		$html = '<p>'.GetMessage("TWINPX_YADELIVERY_ZAPROS_VYPOLNEN").'</p>';
		
		TwinpxDelivery::Agent(); //запускаем метод проверки статусов.
		
		$html .= "<br/>" . $GLOBALS['result_html'];
		
	    echo $html;
	}

	//
	if ($request->get("action") == 'offer') 
	{
		$ID = $request->get('id');
	    $fields = \Bitrix\Main\Web\Json::decode($request->get("fields"));
	    
	    $offerID = $fields['offer_id'];
	    $price   = floatval($fields['offer_price']);
	    
	    $deliveryInterval = '';
	    foreach ($_SESSION['JSON_ANSWER'] as $json_answer) {
	        foreach ($json_answer as $answer) {
	            if ($answer['offer_id'] == $offerID) {
	                $start           = TwinpxApi::PrepareDataTime($answer['offer_details']['delivery_interval']['min']);
	                $end             = TwinpxApi::PrepareDataTime($answer['offer_details']['delivery_interval']['max']);
	                $deliveryInterval = $start. ' - ' . $end;
	                
	                $dstart           = TwinpxApi::PrepareDataTime($answer['offer_details']['pickup_interval']['min']);
	                $dend             = TwinpxApi::PrepareDataTime($answer['offer_details']['pickup_interval']['max']);
	                $pickupInterval	  = $dstart . ' - ' . $dend;
	                $pickupDate 	  = new \Bitrix\Main\Type\DateTime($dstart);
	            }
	        }
	    }
	    
	    $offer_id = array("offer_id" => $offerID);
	    $create = TwinpxApi::requestPost('/api/b2b/platform/offers/confirm', $offer_id);
	            
	    if ($create['SUCCESS'] AND $create['DATA']['request_id']) {
	        $data = array(
	            'REQUEST_ID'		=> $create['DATA']['request_id'],
	            'OFFER_ID'         	=> $offerID,
	            'DELIVERY_INTERVAL'	=> $deliveryInterval,
		        'PICKUP'			=> $pickupInterval,
	            'PICKUPDATE'		=> $pickupDate,
	            'PRICE'				=> $price,
	            'CANCEL'			=> 0,
	            'CHECK_AGENT'		=> 1,
            	'BARCODE'			=> $session->get('YDELIVERY_BARCODE')
	        );
	    }
	    else {
	        if ($state['STATUS']) {
	            $data = array(
	            	'REQUEST_ID' => NULL,
	                'STATUS'            => 'CREATED_ERROR',
	                'STATUS_DESCRIPTION'=> GetMessage("TWINPX_YADELIVERY_OSIBKA_SOZDANIE_ZAAV"),
	                'OFFER_ID'         => NULL,
	            	'DELIVERY_INTERVAL'=> NULL
	            );
	        }

	    }
	    TwinpxOfferTable::update($ID, $data);
	    
	}

	//отмена оффера
	if ($request->get("action") == 'cancel') 
	{
		$itemId = $request->get("itemID");
		$detail = TwinpxOfferTable::getList(array('select' => array('*'),'filter' => array('ID'=> $itemId)))->fetch();
		$requestID = $detail['REQUEST_ID'];
		
	    $state = TwinpxApi::CancelOffer($requestID);	
	    if ($state['STATUS']) {
	        $data = array(
	            'STATUS'            => $state['STATUS'],
	            'STATUS_DESCRIPTION'=> $state['DESCRIPTION'],
	            'CANCEL'			=> 1
	        );
	        TwinpxOfferTable::update($detail['ID'], $data);
	        $html = '<p>'.$state['DESCRIPTION'].'</p>';
	    } 
	    else {
	        $html = '<p>'.GetMessage("ERROR").'</p>';
		}
	    
	    echo $html;
	}

	//перевод в архив
	if ($request->get("action") == 'archive') 
	{
		$itemId = $request->get("itemID");
		$data = array("DIVIDE" => 2);
		
		TwinpxOfferTable::update($itemId, $data);
			
		$html = "<p>".GetMessage("TWINPX_YADELIVERY_ZAAVKA_PERENESENA_V")."</p>";
	    		
		echo $html;	
	}

	//печать баркода
	if ($request->get("action") == 'barcode') 
	{
	    $itemId = $request->get("itemID");
	    $detail = TwinpxOfferTable::getList(array('select' => array('*'),'filter' => array('ID'=> $itemId)))->fetch();
	    $requestID = $detail['REQUEST_ID'];
	    
	    $state = TwinpxApi::GetBarcode($requestID);
	    
	    if ($state['SUCCESS']) {
	        $html = '<p>';
	        
	        $html .= $state['DATA'];
	        
	        $html .= '</p>';
	    } 
	    else {
	        $html = '<p>'.GetMessage("ERROR").'</p>';
	    }
	    
	    echo $html;
	}
	
	//оформление доставки
	if ($request->get("action") == 'newDelivery') 
	{
		$session->remove('MARGIN_VALUE');
		$session->remove('CURIER_PRICE');
		$session->remove('PICKUP_PRICE');
		$session->remove('ORDER_DELIVERY_COST');
		
		$pID = 1;
		$orderId = ($request->get('id') > 0) ? intval($request->get('id')) : "";
		$readonly = '';
		$fields_address = '';
		$pay_select = '';
		//если получили $orderId можем заполнить форму
		if($orderId > 0 && $orderId != '')
		{
			$readonly = "readonly";
			$options = TwinpxConfigTable::GetAllOptions(); //получаем настройки
			
			//данные заказа
			$resSaleOrder = CSaleOrder::GetList(array(), array("ID" => $orderId), false, array(), array("PERSON_TYPE_ID", "PAY_SYSTEM_ID", "DELIVERY_ID", "PAYED", "ALLOW_DELIVERY", "PRICE_DELIVERY", "PRICE", "CURRENCY")); // ID заказа из переменной
			$arOrder = $resSaleOrder->Fetch(); 
			
			//свойства заказа
            $resSaleOrderProps = CSaleOrderPropsValue::GetList(array(), array("ORDER_ID" => $orderId), false, array(), array('ORDER_PROPS_ID', 'VALUE_ORIG'));
            while ($arOrderProps = $resSaleOrderProps->Fetch()) {
                $props[$arOrderProps['ORDER_PROPS_ID']] = $arOrderProps['VALUE_ORIG'];
            }
            
            $pID = $arOrder['PERSON_TYPE_ID'];
			$payID = $arOrder['PAY_SYSTEM_ID'];
			$paySelect = $options['Pay_'.$payID];
			$price = ($paySelect == 'already_paid') ? 0 : $arOrder['PRICE_DELIVERY'];
			
			$session->set('ORDER_DELIVERY_COST', floatval($arOrder['PRICE_DELIVERY']));
		}
		
		
        //ищем есть если поле адрес
        if ($pID) {
            $dbProps = CSaleOrderProps::GetList(array("SORT"=> "ASC"), array("IS_ADDRESS" => "Y", "ACTIVE" => "Y", "PERSON_TYPE_ID" => $pID), false, false, array());
            if ($propAddress = $dbProps->Fetch()) {
                $addressId = $propAddress['ID'];
            }
        }
        
    	$fio = ($props[$options['PropFio_'.$pID]]) ? $props[$options['PropFio_'.$pID]] : "";
		$email = ($props[$options['PropEmail_'.$pID]]) ? $props[$options['PropEmail_'.$pID]] : "";
		$phone = ($props[$options['PropPhone_'.$pID]]) ? $props[$options['PropPhone_'.$pID]] : "";
		
		$street = ($props[$options['PropStreet_'.$pID]]) ? $props[$options['PropStreet_'.$pID]] : "";
		$home = ($props[$options['PropHome_'.$pID]]) ? $props[$options['PropHome_'.$pID]] : "";
		$corp = ($props[$options['PropCorp_'.$pID]]) ? $props[$options['PropCorp_'.$pID]] : "";
		$ap = ($props[$options['PropApartament_'.$pID]]) ? $props[$options['PropApartament_'.$pID]] : "";
		$city = ($props[$options['PropCity_'.$pID]]) ? (CSaleLocation::GetByID($props[$options['PropCity_'.$pID]], LANGUAGE_ID)['CITY_NAME']) ? CSaleLocation::GetByID($props[$options['PropCity_'.$pID]], LANGUAGE_ID)['CITY_NAME'] : CSaleLocation::GetByID($props[$options['PropCity_'.$pID]], LANGUAGE_ID)['REGION_NAME'] : $props[$options['PropCity_'.$pID]];
		if($city == NULL) //если получаем null тогда ставим строку из свойствы
			$city = $props[$options['PropCity_'.$pID]];
        
        $fields_address = '';
        if($addressId > 0) {
        	$address = ($props[$addressId]) ? $props[$addressId] : "";
        	$fields_address .='
				<div class="b-float-label">
                	<textarea name="PropAddress" id="ydFormAddress" required rows="10" cows="10">'.$address.'</textarea>
                    <label for="ydFormAddress">'.GetMessage('TWINPX_YADELIVERY_ADDRESS').'*</label>
                </div>
            ';
		} 
		else {
			$fields_address .='
				<div class="b-float-label">
                	<input name="PropStreet" id="ydFormStreet" type="text" value="'.$street.'" required/>
                    <label for="ydFormStreet">'.GetMessage('TWINPX_YADELIVERY_STREET').'*</label>
              	</div>

              	<div class="b-float-label">
                	<input name="PropHome" id="ydFormHouse" type="text" value="'.$home.'" required/>
                    <label for="ydFormHouse">'.GetMessage('TWINPX_YADELIVERY_HOME').'*</label>
            	</div>

              	<div class="b-float-label">
                	<input name="PropCorp" id="ydFormBuilding" type="text" value="'.$corp.'" />
                	<label for="ydFormBuilding">'.GetMessage('TWINPX_YADELIVERY_KORP').'</label>
            	</div>

            	<div class="b-float-label">
                	<input name="PropApartament" id="ydFormOffise" type="text" value="'.$ap.'" required/>
                	<label for="ydFormOffise">'.GetMessage('TWINPX_YADELIVERY_AP').'*</label>
              	</div>
			';
		}
        
        //тип оплаты
		$arPayType = array(
			"" => GetMessage('TWINPX_YADELIVERY_SELECT'),
			"already_paid" => GetMessage('TWINPX_YADELIVERY_PAID'),
			"cash_on_receipt" => GetMessage('TWINPX_YADELIVERY_CASH'),
			"card_on_receipt" => GetMessage('TWINPX_YADELIVERY_CARD'),
		);
		foreach($arPayType as $key => $value) {
			$pay_select .= ($key == $paySelect && $paySelect) ? '<option value="'.$key.'" selected>'.$value.'</option>' : '<option value="'.$key.'">'.$value.'</option>';
		}
	        
		$result = '
			<div class="yd-popup-error"></div>
			<div class="yd-popup-body">
        <div class="yd-popup-tabs">
          <div class="yd-popup-tabs__nav">
            <div class="yd-popup-tabs__nav__item yd-popup-tabs__nav__item--active" data-tab="general">Общие данные</div>
            <div class="yd-popup-tabs__nav__item" data-tab="props">Характеристики</div>
          </div>
          <div class="yd-popup-tabs__tabs">
            <form action="" novalidate>
              <div class="yd-popup-tabs__tabs__item yd-popup-tabs__tabs__item--active" data-tab="general">
                <div class="yd-popup-form">
                    <div class="yd-popup-form__col">

                        <div class="b-float-label">
                            <input name="ORDER_ID" id="ydFormOrder" type="number" min="1" value="'.$orderId.'" '.$readonly.' required/>
                            <label for="ydFormOrder">'.GetMessage('TWINPX_YADELIVERY_ORDER').'*</label>
                            <div class="yd-popup-form-fillbutton">'.GetMessage('TWINPX_YADELIVERY_GETDATA').'</div>
                        </div>
                        
                        <div class="b-float-label">
                            <input name="PropFio" id="ydFormFio" type="text" value="'.$fio.'" required/>
                            <label for="ydFormFio">'.GetMessage('TWINPX_YADELIVERY_FIO').'*</label>
                        </div>

                        <div class="b-float-label">
                            <input name="PropEmail" id="ydFormEmail" type="email" value="'.$email.'" />
                            <label for="ydFormEmail">'.GetMessage('TWINPX_YADELIVERY_EMAIL').'</label>
                        </div>

                        <div class="b-float-label">
                            <input name="PropPhone" id="ydFormPhone" type="tel" value="'.$phone.'" required/>
                            <label for="ydFormPhone">'.GetMessage('TWINPX_YADELIVERY_PHONE').'*</label>
                        </div>
                        
                        <div class="b-form-control b-float-label">
                          <select name="PAY_TYPE" id="ydFormPay" required>
                                '.$pay_select.'
                            </select>
                        </div>
                        
                        <div class="b-float-label">
                            <input name="PropPrice" id="ydFormPrice" type="number" min="0" value="'.$price.'" required/>
                            <label for="ydFormPrice">'.GetMessage('TWINPX_YADELIVERY_COST').'</label>
                        </div>
                
                    </div>

                    <div class="yd-popup-form__col">
                        <div class="b-float-label">
                            <input name="PropCity" id="ydFormCity" type="text" value="'.$city.'" required/>
                            <label for="ydFormCity">'.GetMessage('TWINPX_YADELIVERY_CITY').'*</label>
                        </div>
                          '.$fields_address.'
                        <div class="b-float-label">
                          <textarea name="PropComment" id="ydFormComment" type="text" value="'.$city.'"></textarea>
                          <label for="ydFormComment">'.GetMessage('TWINPX_YADELIVERY_COMMENT').'</label>
                        </div>                        
                    </div>
                </div>
              </div>
              
              <div class="yd-popup-tabs__tabs__item" data-tab="props">
                <div class="yd-popup-form">
                    <div class="yd-popup-form__col">
                        
                        <div class="b-float-label">
                            <input name="PropLength" id="ydFormLength" type="text" value="'.$length.'"/>
                            <label for="ydFormLength">'.GetMessage('TWINPX_YADELIVERY_LENGTH').'</label>
                        </div>

                        <div class="b-float-label">
                            <input name="PropWidth" id="ydFormWidth" type="text" value="'.$width.'" />
                            <label for="ydFormWidth">'.GetMessage('TWINPX_YADELIVERY_WIDTH').'</label>
                        </div>

                        <div class="b-float-label">
                            <input name="PropHeight" id="ydFormHeight" type="text" value="'.$height.'"/>
                            <label for="ydFormHeight">'.GetMessage('TWINPX_YADELIVERY_HEIGHT').'</label>
                        </div>
                
                    </div>

                    <div class="yd-popup-form__col">                        
                    </div>
                </div>
              </div>

              <div class="yd-popup-form__submit">
                  <button class="twpx-ui-btn" type="submit">'.GetMessage('TWINPX_YADELIVERY_SUBMIT').'</button>
              </div>
            </form>
            
          </div>
        </div>
        
        <div class="yd-popup-offers load-circle"></div>
    </div>
		';
	    		
		echo $result;	
	}

	//оформление ПВЗ
	if ($request->get("action") == 'newDeliveryPvz')
	{
		$session->remove('MARGIN_VALUE');
		$session->remove('CURIER_PRICE');
		$session->remove('PICKUP_PRICE');
		$pID = 1;
		$orderId = ($request->get('id') > 0) ? intval($request->get('id')) : "";
		$readonly = '';
		$fields_address = '';
		$pay_select = '';
		//если получили $orderId можем заполнить форму
		if($orderId > 0 && $orderId != '')
		{
			$readonly = "readonly";
			$options = TwinpxConfigTable::GetAllOptions(); //получаем настройки
			
			//данные заказа
			$resSaleOrder = CSaleOrder::GetList(array(), array("ID" => $orderId), false, array(), array("PERSON_TYPE_ID", "PAY_SYSTEM_ID", "DELIVERY_ID", "PAYED", "ALLOW_DELIVERY", "PRICE_DELIVERY", "PRICE", "CURRENCY")); // ID заказа из переменной
			$arOrder = $resSaleOrder->Fetch(); 
			
			//свойства заказа
            $resSaleOrderProps = CSaleOrderPropsValue::GetList(array(), array("ORDER_ID" => $orderId), false, array(), array('ORDER_PROPS_ID', 'VALUE_ORIG'));
            while ($arOrderProps = $resSaleOrderProps->Fetch()) {
                $props[$arOrderProps['ORDER_PROPS_ID']] = $arOrderProps['VALUE_ORIG'];
            }
            
            $pID = $arOrder['PERSON_TYPE_ID'];
			$payID = $arOrder['PAY_SYSTEM_ID'];
			$paySelect = $options['Pay_'.$payID];
			
			$price = ($paySelect == 'already_paid') ? 0 : $arOrder['PRICE_DELIVERY'];
			$session->set('ORDER_DELIVERY_COST', floatval($arOrder['PRICE_DELIVERY']));
		}
		
        //ищем есть если поле адрес
        if ($pID) {
            $dbProps = CSaleOrderProps::GetList(array("SORT"=> "ASC"), array("IS_ADDRESS" => "Y", "ACTIVE" => "Y", "PERSON_TYPE_ID" => $pID), false, false, array());
            if ($propAddress = $dbProps->Fetch()) {
                $addressId = $propAddress['ID'];
            }
        }
        
    	$fio = ($props[$options['PropFio_'.$pID]]) ? $props[$options['PropFio_'.$pID]] : "";
		$email = ($props[$options['PropEmail_'.$pID]]) ? $props[$options['PropEmail_'.$pID]] : "";
		$phone = ($props[$options['PropPhone_'.$pID]]) ? $props[$options['PropPhone_'.$pID]] : "";
		
		$city = ($props[$options['PropCity_'.$pID]]) ? (CSaleLocation::GetByID($props[$options['PropCity_'.$pID]], LANGUAGE_ID)['CITY_NAME']) ? CSaleLocation::GetByID($props[$options['PropCity_'.$pID]], LANGUAGE_ID)['CITY_NAME'] : CSaleLocation::GetByID($props[$options['PropCity_'.$pID]], LANGUAGE_ID)['REGION_NAME'] : $props[$options['PropCity_'.$pID]];
		if($city == NULL) //если получаем null тогда ставим строку из свойствы
			$city = $props[$options['PropCity_'.$pID]];
		
		
		//тип оплаты
		$arPayType = array(
			"" => GetMessage('TWINPX_YADELIVERY_SELECT'),
			"already_paid" => GetMessage('TWINPX_YADELIVERY_PAID'),
			"cash_on_receipt" => GetMessage('TWINPX_YADELIVERY_CASH'),
			"card_on_receipt" => GetMessage('TWINPX_YADELIVERY_CARD'),
		);
		foreach($arPayType as $key => $value) {
			$pay_select .= ($key == $paySelect && $paySelect) ? '<option value="'.$key.'" selected>'.$value.'</option>' : '<option value="'.$key.'">'.$value.'</option>';
		}
		
		$result = '
			<div class="yd-popup-error"></div>
			<div class="yd-popup-body">
        <div class="yd-popup-tabs">
          <div class="yd-popup-tabs__nav">
            <div class="yd-popup-tabs__nav__item yd-popup-tabs__nav__item--active" data-tab="general">Общие данные</div>
            <div class="yd-popup-tabs__nav__item" data-tab="props">Характеристики</div>
          </div>
          <div class="yd-popup-tabs__tabs">
	            <form action="" novalidate>
                <div class="yd-popup-tabs__tabs__item yd-popup-tabs__tabs__item--active" data-tab="general">
	                <div class="yd-popup-form">
	                    <div class="yd-popup-form__col">

	                        <div class="b-float-label">
	                            <input name="ORDER_ID" id="ydFormPvzOrder" type="number" value="'.$orderId.'" '.$readonly.' required/>
	                            <label for="ydFormPvzOrder">'.GetMessage('TWINPX_YADELIVERY_ORDER').'*</label>
                              <div class="yd-popup-form-fillbutton">'.GetMessage('TWINPX_YADELIVERY_GETDATA').'</div>
	                        </div>
                          
	                        <div class="b-float-label">
	                            <input name="PropFio" id="ydFormPvzFio" type="text" value="'.$fio.'" required/>
	                            <label for="ydFormPvzFio">'.GetMessage('TWINPX_YADELIVERY_FIO').'*</label>
	                        </div>

	                        <div class="b-float-label">
	                            <input name="PropEmail" id="ydFormPvzEmail" type="email" value="'.$email.'" />
	                            <label for="ydFormPvzEmail">'.GetMessage('TWINPX_YADELIVERY_EMAIL').'</label>
	                        </div>

	                        <div class="b-float-label">
	                            <input name="PropPhone" id="ydFormPvzPhone" type="tel" value="'.$phone.'" required/>
	                            <label for="ydFormPvzPhone">'.GetMessage('TWINPX_YADELIVERY_PHONE').'*</label>
	                        </div>
	                        
	                        <div class="b-form-control b-float-label">
	                        	<select name="PAY_TYPE" id="ydFormPay" required>
                                	'.$pay_select.'
                            	</select>
	                        </div>
	                        
	                        <div class="b-float-label">
	                            <input name="PropPrice" id="ydFormPrice" type="number" min="0" value="'.$price.'" required/>
	                            <label for="ydFormPrice">'.GetMessage('TWINPX_YADELIVERY_COST').'</label>
	                        </div>
	                        
	                    </div>

	                    <div class="yd-popup-form__col">
	                        <div class="b-float-label">
	                            <input name="PropCity" id="ydFormPvzCity" type="text" value="'.$city.'" required/>
	                            <label for="ydFormPvzCity">'.GetMessage('TWINPX_YADELIVERY_CITY').'*</label>
	                        </div>
	                    </div>
                    </div>
                  </div>
              
                  <div class="yd-popup-tabs__tabs__item" data-tab="props">
                    <div class="yd-popup-form">
                        <div class="yd-popup-form__col">
                            
                            <div class="b-float-label">
                                <input name="PropLength" id="ydFormLength" type="text" value="'.$length.'"/>
                                <label for="ydFormLength">'.GetMessage('TWINPX_YADELIVERY_LENGTH').'</label>
                            </div>

                            <div class="b-float-label">
                                <input name="PropWidth" id="ydFormWidth" type="text" value="'.$width.'" />
                                <label for="ydFormWidth">'.GetMessage('TWINPX_YADELIVERY_WIDTH').'</label>
                            </div>

                            <div class="b-float-label">
                                <input name="PropHeight" id="ydFormHeight" type="text" value="'.$height.'"/>
                                <label for="ydFormHeight">'.GetMessage('TWINPX_YADELIVERY_HEIGHT').'</label>
                            </div>
                    
                        </div>

                        <div class="yd-popup-form__col">                        
                        </div>
                    </div>
                  </div>

	                <div class="yd-popup-form__submit">
	                    <button class="twpx-ui-btn" type="submit">'.GetMessage('TWINPX_YADELIVERY_SUBMIT').'</button>
	                </div>
	            </form>
	            <div class="yd-popup-map-container"></div>
	        </div>
		';
	    		
		echo $result;	
	}

	//получение список ПВЗ для региона
    if ($request->get("action") == 'getPoints') 
    {
    	parse_str($request["fields"], $fields);
    	if(LANG_CHARSET != 'UTF-8') {
        	$fields = \Bitrix\Main\Text\Encoding::convertEncoding($fields, "UTF-8", LANG_CHARSET); //для кодировки windows-1251
        }
        TwinpxApi::SetLogs($fields, '', 'adm_getPoints.fields'); //
    	$status = "N";
    	$errors = false;
    	$error = array();
    	$points = array();
    	$type = array(
    		"pickup_point" => GetMessage('Pickup-point'),
			"terminal" => GetMessage('Terminal'),
			"post_office" => GetMessage('Post-office'),
			"sorting_center" => GetMessage('Sorting-center'),
			"warehouse" => GetMessage('Warehouse'),
    	);
    	
    	if(isset($fields['payment'])){
		    $queryPvz = array(
		        //"type" => "pickup_point",
		        "available_for_dropoff"=> FALSE,
		        "payment_method"       => $fields['payment'],
		        "latitude"             => ["from" => floatval($fields['lat-from']), "to" => floatval($fields['lat-to'])],
		        "longitude"            => ["from" => floatval($fields['lon-from']), "to" => floatval($fields['lon-to'])],
		    );
		    $response = TwinpxApi::requestPost("/api/b2b/platform/pickup-points/list", $queryPvz);
		} 
		else {
			$status = "Y";
			$error[] = GetMessage('PaymentError');
			TwinpxApi::SetLogs(array(GetMessage('PaymentError')), '', 'adm_getPoints.payment'); //
		}
		
    	//обработка результата
    	if($response['SUCCESS'] && (isset($response['DATA']['error']) || isset($response['DATA']['message'])) ){
			$status = "Y";
			$error[] = ($response['DATA']['error']) ? $response['DATA']['error'] : GetMessage($response['DATA']['message']);
		}
    	elseif($response['SUCCESS'] && !isset($response['DATA']['message'])){ // [message] => object_not_found
			$status = "Y";
			foreach($response['DATA'] as $point){
				if($point['type'] == 'sorting_center') continue;
				
				$hash = md5($point['name'].$point['type'].$point['position']['latitude'].$point['position']['longitude']); //хэш значение для исключение одинаковых точек
				$points[$hash] = array(
					"id" => $point['id'],
				    "title" => $point['name'],
				    "type" => $type[$point['type']],
				    "schedule" => TwinpxApi::GenerateSchedule($point['schedule']['restrictions']),
				    "address" => ($point['address']['full_address']) ? $point['address']['full_address'] : $point['address']['locality'].', '.$point['address']['street'].', '.$point['address']['house'],
				    "coords" => array($point['position']['latitude'], $point['position']['longitude'])
				);
			}
		} 
		else {
			$error[] = GetMessage($response['DATA']['message']);
		}
		//обработка ошибок
		if(!empty($error)) $errors = implode('<br/>', $error);
    	
    	echo \Bitrix\Main\Web\Json::encode(array("STATUS" => $status, "POINTS"=> (!empty($points)) ? array_values($points) : NULL, "ERRORS" => $errors));
    }
    
    //получение список ПВЗ для региона
    if ($request->get("action") == 'getReception') 
    {
    	/*parse_str($request["fields"], $fields);
    	if(LANG_CHARSET != 'UTF-8') {
        	$fields = \Bitrix\Main\Text\Encoding::convertEncoding($fields, "UTF-8", LANG_CHARSET); //для кодировки windows-1251
        }*/
    	$status = "N";
    	$errors = false;
    	$points = array();
      
	    $queryPvz = array("available_for_dropoff" => true); //выбираем только пункты самопривоза
	    $response = TwinpxApi::requestPost("/api/b2b/platform/pickup-points/list", $queryPvz);
		
    	//обработка результата
    	if($response['SUCCESS'] && (isset($response['DATA']['error']) || isset($response['DATA']['message'])) ){
			$status = "Y";
			$error[] = ($response['DATA']['error']) ? $response['DATA']['error'] : GetMessage($response['DATA']['message']);
		}
    	elseif($response['SUCCESS'] && !isset($response['DATA']['message'])){ // [message] => object_not_found
			$status = "Y";
			foreach($response['DATA'] as $point){
				$hash = md5($point['name'].$point['type'].$point['position']['latitude'].$point['position']['longitude']); //хэш значение для исключение одинаковых точек
				$points[$hash] = array(
					"id" => $point['id'],
				    "title" => $point['name'],
				    "schedule" => TwinpxApi::GenerateSchedule($point['schedule']['restrictions']),
				    "address" => ($point['address']['full_address']) ? $point['address']['full_address'] : $point['address']['locality'].', '.$point['address']['street'].', '.$point['address']['house'],
				    "coords" => array($point['position']['latitude'], $point['position']['longitude'])
				);
			}
		} 
		else {
			$error[] = GetMessage($response['DATA']['message']);
		}
		//обработка ошибок
		if(!empty($error)) $errors = implode('<br/>', $error);
    	
    	echo \Bitrix\Main\Web\Json::encode(array("STATUS" => $status, "POINTS"=> (!empty($points)) ? array_values($points) : NULL, "ERRORS" => $errors));
    }
    
	//получаем спсиок офферов
	if ($request->get("action") == 'newGetOffer')
	{
		parse_str($request["form"], $fields);
		if(LANG_CHARSET != 'UTF-8') {
	    	$fields = \Bitrix\Main\Text\Encoding::convertEncoding($fields, "UTF-8", LANG_CHARSET); //для кодировки windows-1251
	    }
	    TwinpxApi::SetLogs($fields, '', 'adm_newGetOffer.fields'); //
	    $status = 'N';
	    $result = FALSE;
	    $deliveryType = ($fields['Type']) ? $fields['Type'] : FALSE;
	    $session->remove('PAYMENT');
	    $session->remove('CURIER_PRICE');
	    
		//получаем свойствы заказа
	    //личные данные
	    $comment = '';
	    
	    $fio = ($fields['PropFio']) ? $fields['PropFio'] : FALSE;
	    $phone = ($fields['PropPhone']) ? $fields['PropPhone'] : FALSE;
	    $email = ($fields['PropEmail']) ? $fields['PropEmail'] : FALSE;
	    $price_fix = ($fields['PropPrice'] != '') ? $fields['PropPrice'] : 0;
	    
	    //данные доставки
	    $city    = ($fields['PropCity']) ? $fields['PropCity'] : FALSE;
	    $street = ($fields['PropStreet']) ? $fields['PropStreet'] : FALSE;
	    $home = ($fields['PropHome']) ? $fields['PropHome'] : FALSE;
	    $corps = ($fields['PropCorp']) ? $fields['PropCorp'] : FALSE;
	    $ap = ($fields['PropApartament']) ? $fields['PropApartament'] : FALSE;
	    
	    $order_id = ($fields['ORDER_ID']>0) ? $fields['ORDER_ID'] : FALSE;
	    $payment = ($fields['PAY_TYPE']) ? $fields['PAY_TYPE'] : FALSE;
	    $address = ($fields['PropAddress']) ? $fields['PropAddress'] : FALSE;
	    
	    if (strlen($order_id) < 1){
	    	$error[] = GetMessage('TWINPX_YADELIVERY_ORDER');
		}
		if (strlen($payment) < 1){
	    	$error[] = GetMessage('TWINPX_YADELIVERY_PAY');
		}
		
	    if (strlen($fio) < 1){
	    	$error[] = GetMessage('TWINPX_YADELIVERY_FIO');
		}
		if (strlen($phone) < 1){
	    	$error[] = GetMessage('TWINPX_YADELIVERY_PHONE');
		}
		
		if($address){
			if (strlen($address) < 1) {
		    	$error[] = GetMessage('TWINPX_YADELIVERY_ADRESS');
			}
		} 
		else {
		    if (strlen($city) < 1) {
		    	$error[] = GetMessage('TWINPX_YADELIVERY_CITY');
			}
		    if (strlen($street) < 1){
		    	$error[] = GetMessage('TWINPX_YADELIVERY_STREET');
			}
		    if (strlen($home) < 1) {
		    	$error[] = GetMessage('TWINPX_YADELIVERY_HOME');
			}
		}
		
		$arFields = array(
	        'FIO'       => explode(" ", $fio),
	        'PHONE'     => $phone,
	        'EMAIL'		=> $email,

	        'CITY'      => $city,
	        'FULL_ADDRESS'=>$address,
	        'STREET'    => $street,
	        'HOME'      => $home,
	        'CORPS'     => $corps,
	        'APARTAMENT'=> $ap,
	        'PAYMENT'	=> $payment,
	        
	        'FIX_PRICE'	=> $price_fix,

	        'COMMENT'   => $comment
	    );
	    
	    if (!empty($error)) {
	    	TwinpxApi::SetLogs($error, '', 'adm_newGetOffer.error'); //
	        $errors = implode(', ', $error);
	    } 
	    else {
	        $session->set('PAYMENT', $payment); //запоминаем метод оплаты
	        $session->set('CURIER_PRICE', $price_fix); //передаем фикс. цену
	        $session->set('BAYER_EMAIL', $email); 
	        
	        $prepare = TwinpxApi::PrepareData($arFields, $order_id, true); //подготовка массива с данные
	        $full_address = ($address) ? $city.', '.$address : TwinpxApi::PrepareAddress($arFields);
	        
	        //получаем код города по название
	        $db_vars = CSaleLocation::GetList(array(), array("LID" => LANGUAGE_ID, "CITY_NAME"=> $city), false, false, array("CODE"));
	        if ($vars = $db_vars->Fetch()){
				$location = $vars;
				$session->set('LOCATION_CODE', $vars['CODE']); //запоминаем код местоположение
			}
			
		    $getInterval = TwinpxApi::GetInterval($full_address); //получаем доступные интервалы           
	        $generateInterval = TwinpxApi::GenerateInterval($location); //создаем наши интервалы
	        
			$intervals = (!empty($getInterval)) ? $getInterval : $generateInterval; //проверяем если если получаем интервалы проверяем по ними, если нет наши интервалы генерируем
			
			$offer  = TwinpxApi::multiRequest('/api/b2b/platform/offers/create', $prepare, $intervals);
					
	        if ($offer['SUCCESS'] AND !empty($offer['DATA'])) {
	            $status = "Y";
	            $result = TwinpxApi::ShowOfferJson($offer['DATA'], false, $order_id);
	        } 
	        elseif($offer['SUCCESS'] AND !empty($offer['ERROR'])) {
	            $adr = FALSE;
	            $auth = FALSE;
	            foreach($offer['ERROR'] as $value){
	                if ( is_array($value) && in_array("cannot parse destination info", $value) ) {
	                    $adr = TRUE;
	                }
	                elseif("Not authorized request" == $value){
						$auth = TRUE;
					}
	            }
	            //если нашли ошибку адреса
	            if($adr) {
	            	$status = "Y";
	                $errors = GetMessage('TWINPX_YADELIVERY_NE_UDALOSQ_POLUCITQ');
	            }
	            elseif($auth){
					$status = "Y";
	                $errors = GetMessage('TWINPX_YADELIVERY_AUTH');
				}
	            else {
	            	$status = "Y";
	                $errors = GetMessage('TWINPX_YADELIVERY_NET_DOSTUPNYH_INTERV');
	            }
	        }
	        else {
	        	$status = "Y";
	            $errors = GetMessage('TWINPX_YADELIVERY_NET_DOSTUPNYH_INTERV');
	        }
	    }
		
		echo \Bitrix\Main\Web\Json::encode(array("STATUS" => $status, "OFFERS" => $result, "ERRORS" => $errors));
	}

	//проверяем если есть такой заказ
	if ($request->get("action") == 'checkOrder')
	{
		Loader::includeModule('sale');
		$orderId = $request->get("orderId");
		$result = array('STATUS' => 'N');
		if ($orderId > 0) {
			$dbRes    = \Bitrix\Sale\Order::getList(array('filter' => array("ID" => $orderId), 'select' => array('ID')));
			if($order = $dbRes->fetch()) {
				$result = array( 'STATUS' => 'Y'); //заказ найден
			} 
			else {
				TwinpxApi::SetLogs(array($orderId => 'false'), '', 'adm_checkOrder.error'); //	
			}
		}
		
		echo \Bitrix\Main\Web\Json::encode($result);
	}
	
	//проверяем если есть такой заказ
	if ($request->get("action") == 'getOrderData')
	{
		Loader::includeModule('sale');
		$options = TwinpxConfigTable::GetAllOptions();
		$order_id = $request->get("id");
		$result = array('STATUS' => 'N');
		
		if (intval($order_id)  > 0) {
			$resSaleOrder = CSaleOrder::GetList(array(), array("ID" => $order_id), false, array(), array("PERSON_TYPE_ID", "PAY_SYSTEM_ID", "DELIVERY_ID", "PAYED", "ALLOW_DELIVERY", "PRICE_DELIVERY", "PRICE", "CURRENCY")); // ID заказа из переменной
			if($arOrder = $resSaleOrder->Fetch()) {
				//свойства заказа
	            $resSaleOrderProps = CSaleOrderPropsValue::GetList(array(), array("ORDER_ID" => $order_id), false, array(), array('ORDER_PROPS_ID', 'VALUE_ORIG'));
	            while ($arOrderProps = $resSaleOrderProps->Fetch()) {
	                $props[$arOrderProps['ORDER_PROPS_ID']] = $arOrderProps['VALUE_ORIG'];
	            }
	            
				$payID = $arOrder['PAY_SYSTEM_ID'];
				$fields['PAY_TYPE'] = ($options['Pay_'.$payID]) ? $options['Pay_'.$payID] : "";
				
	            //ищем есть если поле адрес
				$pID = $arOrder['PERSON_TYPE_ID'];
		        if ($pID) {
		            $dbProps = CSaleOrderProps::GetList(array("SORT"=> "ASC"), array("IS_ADDRESS" => "Y", "PERSON_TYPE_ID" => $pID), false, false, array());
		            if ($propAddress = $dbProps->Fetch()) {
		                $addressId = $propAddress['ID'];
		            }
		        }
		        
		        $fields['PropFio'] = ($props[$options['PropFio_'.$pID]]) ? $props[$options['PropFio_'.$pID]] : "";
				$fields['PropEmail'] = ($props[$options['PropEmail_'.$pID]]) ? $props[$options['PropEmail_'.$pID]] : "";
				$fields['PropPhone'] = ($props[$options['PropPhone_'.$pID]]) ? $props[$options['PropPhone_'.$pID]] : "";
				$fields['PropCity'] = ($props[$options['PropCity_'.$pID]]) ? (CSaleLocation::GetByID($props[$options['PropCity_'.$pID]], LANGUAGE_ID)['CITY_NAME']) ? CSaleLocation::GetByID($props[$options['PropCity_'.$pID]], LANGUAGE_ID)['CITY_NAME'] : CSaleLocation::GetByID($props[$options['PropCity_'.$pID]], LANGUAGE_ID)['REGION_NAME'] : "";
				$fields['PropPrice'] = ($fields['PAY_TYPE'] == 'already_paid') ? "0" : $arOrder['PRICE_DELIVERY'];
				
				if($addressId > 0) {
					$fields['PropAddress'] = ($props[$addressId]) ? $props[$addressId] : "";
				}
				else {
					$fields['PropStreet'] = ($props[$options['PropStreet_'.$pID]]) ? $props[$options['PropStreet_'.$pID]] : "";
					$fields['PropHome'] = ($props[$options['PropHome_'.$pID]]) ? $props[$options['PropHome_'.$pID]] : "";
					$fields['PropCorp'] = ($props[$options['PropCorp_'.$pID]]) ? $props[$options['PropCorp_'.$pID]] : "";
					$fields['PropApartament'] = ($props[$options['PropApartament_'.$pID]]) ? $props[$options['PropApartament_'.$pID]] : "";	
				}
				
				$result = array( 'STATUS' => 'Y'); //заказ найден
				$result += array( 'FIELDS' => $fields);
				
				$session->set('ORDER_DELIVERY_COST', floatval($arOrder['PRICE_DELIVERY']));
			}
		}
		
		echo \Bitrix\Main\Web\Json::encode($result);
	}

	//бронируем оффер
	if($request->get("action") == 'setDelivery')
	{
		$params = \Bitrix\Main\Web\Json::decode($request->get("data"));
		$full_address = ($session->has('FULL_ADDRESS')) ? $session->get('FULL_ADDRESS') : '';
		$payment = ($session->has('PAYMENT')) ? $session->get('PAYMENT') : false;
		$location = ($session->has('LOCATION_CODE')) ? $session->get('LOCATION_CODE') : '_';
		$deliveryPrice = ($session->has('ORDER_DELIVERY_COST') && $session->get('ORDER_DELIVERY_COST') >= 0) ? floatval($session->get('ORDER_DELIVERY_COST')) : 0;
		$order_id = $params['order_id'];
		$offer_id = $params['offer_id'];
		$price	= floatval($params['offer_price']);
		$deliveryCost = ($params['cost'] >= 0) ? floatval($params['cost']) : 0;
		$email = $params['email'];
		
		//извлекаем интервалы
		$deliveryInterval = '';
	    foreach ($session->get('JSON_ANSWER') as $json_answer) {
	        foreach ($json_answer as $answer) {
	            if ($answer['offer_id'] == $offer_id) {
	                $start           	= TwinpxApi::PrepareDataTime($answer['offer_details']['delivery_interval']['min']);
	                $end             	= TwinpxApi::PrepareDataTime($answer['offer_details']['delivery_interval']['max']);
	                $deliveryInterval	= $start . ' - ' . $end;
	                
	                $dstart         	= TwinpxApi::PrepareDataTime($answer['offer_details']['pickup_interval']['min']);
                    $dend           	= TwinpxApi::PrepareDataTime($answer['offer_details']['pickup_interval']['max']);
                    $pickupInterval		= $dstart . ' - ' . $dend;
                    $pickupDate 		= new \Bitrix\Main\Type\DateTime($dstart);
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
	        'PAYMENT'		   	=> $payment,
            'PICKUP'			=> $pickupInterval,
            'PICKUPDATE'		=> $pickupDate,	
            'PRICE'        		=> $price,
            'PRICE_FIX'			=> $deliveryPrice,
            'PRICE_DELIVERY'	=> $deliveryCost,
            'CHECK_AGENT'		=> 1,
            'BARCODE'			=> $session->get('YDELIVERY_BARCODE')
	    );
	    $r = TwinpxOfferTable::add($data); //создаем записи
	    
		$offerRequest = array("offer_id"=> $offer_id);
	    $create = TwinpxApi::requestPost('/api/b2b/platform/offers/confirm', $offerRequest); //бронируем оффер
	    
	    //если получили ответ
	    if ($create['SUCCESS'] AND $create['DATA']['request_id']) {
	    	$requestID = $create['DATA']['request_id'];
	        TwinpxOfferTable::update($r->GetID(), array('REQUEST_ID'=> $requestID)); //записываем id брони
	        
	        //записи статуса доставки
	        $state = TwinpxApi::GetOfferState($requestID);
	        if ($state['STATUS']) {
	            $data = array(
	                'STATUS'            => $state['STATUS'],
	                'STATUS_DESCRIPTION'=> $state['DESCRIPTION']
	            );
	            TwinpxOfferTable::update($r->GetID(), $data);
	        }
	        
	        //получаем ID и название наше доставки
			$rsDelivery = \Bitrix\Sale\Delivery\Services\Table::getList(array('filter' => array('ACTIVE'=>'Y', '=CODE'=>'twpx_yadelivery'), 'select' => array('ID', 'NAME')));
			if ($delivery = $rsDelivery->fetch()){
				$rsProfile = \Bitrix\Sale\Delivery\Services\Table::getList(array('filter' => array('ACTIVE'=>'Y', 'PARENT_ID' => $delivery['ID'], 'CODE'=>'twpx_yadelivery:'.$params['type']."%"), 'select' => array('ID', 'NAME')));
				$profile = $rsProfile->fetch();//информация о доставке
			}
	        
	        //если нашли достаку
	        if($delivery && $profile) {
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
					$err = $res->getErrors();
					TwinpxApi::SetLogs($err, '', 'adm_setDelivery.ordersave'); //
				}
			}
	        $result = array('SUCCESS' => 'Y'); //успешно
	        
	        //удалем записи из времменой таблицы
			$arTemp = TwinpxOfferTempTable::getList(array('select' => array('ID'), 'filter' => array('ORDER_ID'=> $order_id)));
			if($row = $arTemp->fetch()){
				TwinpxOfferTempTable::delete($row['ID']);//удаляем
				
				$result += array('RELOAD' => '/bitrix/admin/twinpx_delivery_offers.php?lang=' . SITE_ID);
				
				//получаем ID сайта
				$rsSites = CSite::GetList($by="sort", $order="desc", array("DOMAIN" => $_SERVER['SERVER_NAME'], "ACTIVE" => "Y"));
				if($arSite = $rsSites->Fetch())
				{
					$sId = $arSite['ID'];
				}
				//отправляем уведомление
				$arEventFields = array(
                    "ORDER_ID"        	=> $order_id,
                    "ORDER_DATE"        => new \Bitrix\Main\Type\DateTime(),
                    "DELIVERY_NAME"		=> $delivery['NAME'].' ('.$profile['NAME'].')',
                    "EMAIL"				=> $email,
                    "TIME_INTERVAL"		=> $deliveryInterval,
                    "SALE_EMAIL" 		=> \Bitrix\Main\Config\Option::get("sale", "order_email", "order@".$SERVER_NAME)
                );
                $send = CEvent::Send("TWPX_YANDEX_CREATE_OFFER", $sId, $arEventFields);
			}
	        
	    }
	    else {    	
	        $data = array(
	            'STATUS'            => 'CREATED_ERROR',
	            'STATUS_DESCRIPTION'=> GetMessage("TWINPX_YADELIVERY_OSIBKA_SOZDANIA_ZAAV")
	        );
	        TwinpxOfferTable::update($r->GetID(), $data);
	        $result = array('SUCCESS' => 'N', 'ERROR' => GetMessage("TWINPX_YADELIVERY_PROIZOSLA_OSIBKA_BRO")); //ошибка
	    }
	    
	    $session->remove('PAYMENT');
	    $session->remove('CURIER_PRICE');
	    $session->remove('JSON_REQUEST');
	    $session->remove('JSON_ANSWER');
	    $session->remove('ORDER_DELIVERY_COST');
	    $session->remove('YDELIVERY_BARCODE');
	    
	    echo \Bitrix\Main\Web\Json::encode($result);
	}

	//получение список офферов для ПВЗ
    if ($request->get("action") == 'pvzOfferAdmin') 
    {
    	parse_str($request["fields"], $fields);
		if(LANG_CHARSET != 'UTF-8') {
        	$fields = \Bitrix\Main\Text\Encoding::convertEncoding($fields, "UTF-8", LANG_CHARSET); //для кодировки windows-1251
        }
        TwinpxApi::SetLogs($fields, '', 'adm_pvzOfferAdmin.fields'); //
    	$options = TwinpxConfigTable::GetAllOptions();
        $pTypeId = $fields['PERSON_TYPE']; //тип плательщика
        $pvzId = $fields['id'];
        $pvzAddress = $fields['address'];
    	$status = "N";
    	$points = array();
    	$session->remove('PAYMENT');
	    $session->remove('PICKUP_PRICE');
        
        //получаем свойствы заказа
        //личные данные
        $fio    = ($fields["PropFio"]) 	? $fields["PropFio"]	: FALSE;
        $phone  = ($fields["PropPhone"])? $fields["PropPhone"] 	: FALSE;
        $email  = ($fields["PropEmail"])? $fields["PropEmail"] 	: FALSE;
        $city 	= ($fields["PropCity"]) ? $fields["PropCity"] 	: FALSE;
        $payment= ($fields['PAY_TYPE']) ? $fields['PAY_TYPE'] 	: FALSE;
        $price_fix= ($fields['PropPrice']!='') ? $fields['PropPrice'] 	: FALSE;
        
        $order_id = ($fields['ORDER_ID']) ? $fields['ORDER_ID'] : FALSE;    
	    if (strlen($order_id) < 1){
	    	$error[] = GetMessage('PropOrderId');
		}
		if (strlen($payment) < 1){
	    	$error[] = GetMessage('TWINPX_YADELIVERY_PAY');
		}
	    if (strlen($fio) < 1){
	    	$error[] = GetMessage('PropFio');
		}
		if (strlen($phone) < 1){
	    	$error[] = GetMessage('PropPhone');
		}
	    if (strlen($pvzAddress) < 1) {
	    	$error[] = GetMessage('PropHome');
		}
        
        $arFields = array(
            'FIO'       => explode(" ", $fio),
            'PHONE'     => $phone,
            'EMAIL'		=> $email,
            
            'PVZ_ID'	=> $pvzId,
            'FULL_ADDRESS'=> $pvzAddress,
            
            'PAYMENT'	=> $payment,
            'FIX_PRICE'	=> $price_fix,
            
            'COMMENT'   => '',
        );
    	
    	if (!empty($error)) {
    		//$status = "Y";
            $result = implode('<br/>', $error);
            TwinpxApi::SetLogs($error, '', 'adm_pvzOfferAdmin.error'); //
        } 
        else {
			$session->set('PAYMENT', $payment); //запоминаем метод оплаты
			$session->set('PICKUP_PRICE', $price_fix); //запоминаем цену
			$session->set('BAYER_EMAIL', $email);
			
        	$prepare = TwinpxApi::PrepareData($arFields, $order_id, true);	
        	$offer   = TwinpxApi::requestPost('/api/b2b/platform/offers/create', $prepare); //запрос
        	
        	if ($offer['SUCCESS'] AND !empty($offer['DATA'])) {
                $status = "Y";
                if(!empty($offer['DATA']['error'])){ //если получаем ошибку 
					$errors = GetMessage('No-Pvz-intervals');
				} 
				else {
                	$result = TwinpxApi::ShowOfferJson($offer['DATA'][0], $pvzId, $order_id);
				}
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
                } 
                else {
                	$status = "Y";
                    $errors = GetMessage('No-Pvz-intervals');
                }
            }
            else {
            	$status = "Y";
                $errors = GetMessage('No-intervals');
            }
        }
    	    	
    	echo \Bitrix\Main\Web\Json::encode(array("STATUS" => $status, "OFFERS" => $result, "ERRORS" => $errors));
    }
    
	//передаем цену для доставки
	if($request->get("action") == 'setOfferPriceAdmin') 
	{
		$fields = \Bitrix\Main\Web\Json::decode($request["fields"]); //парсим JSON
		$result = array('STATUS' => 'N');
		$payconfirm = 0;
        $order_id = ($fields['order_id']) ? $fields['order_id'] : FALSE;
        $offer_id = $fields['offer_id'];
        $offerExpire = $fields['offer_expire'];
        $email = $fields['email'];
        $pvzID = ($fields['offer_pvz']) ? $fields['offer_pvz'] : FALSE;
		$price   = floatval($fields['offer_price']);
		$deliveryCost = ($fields['cost'] >= 0) ? floatval($fields['cost']) : 0;
        
        $payment = ($session->has('PAYMENT')) ? $session->get('PAYMENT') : false;
        $full_address = ($session->has('FULL_ADDRESS')) ? $session->get('FULL_ADDRESS') : '';
        $deliveryPrice = ($session->has('ORDER_DELIVERY_COST') && $session->get('ORDER_DELIVERY_COST') >= 0) ? floatval($session->get('ORDER_DELIVERY_COST')) : 0;
        
        $deliveryInterval = '';
        foreach ($session->get('JSON_ANSWER') as $json_answer) {
	        foreach ($json_answer as $answer) {
	            if ($answer['offer_id'] == $offer_id) {
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
	    
		//приготовим данные для записи в таблицу
	    $data = array(
	        'ORDER_ID'         	=> $order_id,
	        'ORDER_DATE'       	=> new \Bitrix\Main\Type\DateTime(),
	        'OFFER_ID'         	=> $offer_id,
	    	'PVZ_ID' 		   	=> $pvzID,
	        'ADDRESS'          	=> $full_address,
	        'LOCATION'         	=> '_',
	        'JSON_REQUEST'     	=> ($session->has('JSON_REQUEST')) ? serialize($session->get('JSON_REQUEST')) : null,
	        'JSON_RESPONS'     	=> ($session->has('JSON_ANSWER')) ? serialize($session->get('JSON_ANSWER')) : null,
	        'DELIVERY_INTERVAL'	=> $deliveryInterval,
	        'PAYMENT'		   	=> $payment,
//	        'PAYCONFIRM'	    => $payconfirm,
	        'PICKUP'			=> $pickupInterval,
            'PICKUPDATE'		=> $pickupDate,
            'PRICE'        		=> $price,
            'PRICE_FIX'			=> $deliveryPrice,
            'PRICE_DELIVERY'	=> $deliveryCost,
            'CHECK_AGENT'		=> 1,
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
	            $data = array('STATUS' => $state['STATUS'], 'STATUS_DESCRIPTION'=> $state['DESCRIPTION']);
	            TwinpxOfferTable::update($r->GetID(), $data);
	        }
	        
	        //получаем ID и название наше доставки
			$rsDelivery = \Bitrix\Sale\Delivery\Services\Table::getList(array('filter' => array('ACTIVE'=>'Y', '=CODE'=>'twpx_yadelivery'), 'select'=>array('ID', 'NAME')));
			if ($delivery = $rsDelivery->fetch()){
				$rsProfile = \Bitrix\Sale\Delivery\Services\Table::getList(array('filter' => array('ACTIVE'=>'Y', 'PARENT_ID'=>$delivery['ID'], 'CODE'=>'twpx_yadelivery:'.$fields['type']."%"), 'select'=>array('ID', 'NAME')));
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
					TwinpxApi::SetLogs($res->getErrors(), '', 'adm_setOfferPriceAdmin.ordersave'); //
				}
			}
			
	        $result = array('STATUS' => 'Y'); //успешно
	        
	        //удалем записи из времменой таблицы
			$arTemp = TwinpxOfferTempTable::getList(array('select' => array('ID'), 'filter' => array('ORDER_ID'=> $order_id)));
			if($row = $arTemp->fetch()){
				TwinpxOfferTempTable::delete($row['ID']);//удаляем
				
				$result += array('RELOAD' => '/bitrix/admin/twinpx_delivery_offers.php?lang=' . SITE_ID);
				
				//получаем ID сайта
				$rsSites = CSite::GetList($by="sort", $order="desc", array("DOMAIN" => $_SERVER['SERVER_NAME'], "ACTIVE" => "Y"));
				if($arSite = $rsSites->Fetch())
				{
					$sId = $arSite['ID'];
				}
				$dateInterval = explode("-", $deliveryInterval);
				//отправляем уведомление
				$arEventFields = array(
                    "ORDER_ID"        	=> $order_id,
                    "ORDER_DATE"        => new \Bitrix\Main\Type\DateTime(),
                    "EMAIL"				=> $email,
                    "DELIVERY_NAME"		=> $delivery['NAME'].' ('.$profile['NAME'].')',
                    "TIME_INTERVAL"		=> $dateInterval[0],
                    "SALE_EMAIL" 		=> \Bitrix\Main\Config\Option::get("sale", "order_email", "order@".$SERVER_NAME)
                );
                $send = CEvent::Send("TWPX_YANDEX_CREATE_OFFER", $sId, $arEventFields);
			}
	    }
	    else {    	
	        $data = array(
	            'STATUS'            => 'CREATED_ERROR',
	            'STATUS_DESCRIPTION'=> GetMessage("TWINPX_YADELIVERY_OSIBKA_SOZDANIA_ZAAV")
	        );
	        TwinpxOfferTable::update($r->GetID(), $data);
	        
	        //$err = implode(", ", $create['DATA']['details']['details']['error']); //список ошибок
	        $result = array('STATUS' => 'Y', 'ERROR' => GetMessage("TWINPX_YADELIVERY_PROIZOSLA_OSIBKA_BRO")); //ошибка
	    }
	    
	    $session->remove('PAYMENT');
	    $session->remove('PICKUP_PRICE');
	    $session->remove('JSON_REQUEST');
	    $session->remove('JSON_ANSWER');
	    $session->remove('ORDER_DELIVERY_COST');
	    $session->remove('YDELIVERY_BARCODE');
	    
		echo \Bitrix\Main\Web\Json::encode($result);		
	}
	//проверка токена и platform id
	if ($request->get("action") == 'auth') 
	{
        parse_str($request->get('fields'), $fields);
        $Oauth      = $fields['PROPERTY']['OAuth'];
        $PlatformId = $fields['PROPERTY']['PlatformId'];

        $query      = array(
            "client_price"        => 10000,
            "destination"         => ["address" => "Moscow"],
            "payment_method"      => "already_paid",
            "source"              => ["platform_station_id" => $PlatformId],
            "tariff"              => "time_interval",
            "total_assessed_price"=> 10000,
            "total_weight"        => 200
        );
        $check = TwinpxApi::requestPost('/api/b2b/platform/pricing-calculator', $query, $Oauth);
        $code  = $check['CODE'];
        
        switch ($code) {
            case 200:
            	$html = GetMessage("TWINPX_YADELIVERY_CHECK_SUCCES");
            break;
            case 400:
	            $result = json_encode($check['DATA']);

	            $platform   = FALSE;
	            $keys = ['station', 'platform', 'id'];
	            foreach ($keys as $k) {
	                $f    = mb_stripos($result, $k);
	                $platform = ($f === FALSE) ? FALSE : TRUE; //если есть вхождение, значить ошибка Platform ID
	            }
	            if ($platform === TRUE) {
	                $html = GetMessage("TWINPX_YADELIVERY_PLATFORM_ERROR");
	            }
	            else {
	                $html = GetMessage("TWINPX_YADELIVERY_OTHER_ERROR");
	            }
            break;
            case 401:
            	$html = GetMessage("TWINPX_YADELIVERY_TOKEN_ERROR");
            break;
            case 404:
	            $result = json_encode($check['DATA']);

	            $token = FALSE;
	            $keysToken = ['restore employer info', 'employer'];
	            foreach ($keysToken as $k) {
	                $f    = mb_stripos($result, $k);
	                $token = ($f === FALSE) ? FALSE : TRUE; //если есть вхождение, значить ошибка Platform ID
	            }
	            
	            if($token === TRUE){
	                $html = GetMessage("TWINPX_YADELIVERY_TOKEN_ERROR");
				}
	            else {
	                $html = GetMessage("TWINPX_YADELIVERY_OTHER_ERROR");
	            }
	            
            break;
            default:
            	$html = GetMessage("TWINPX_YADELIVERY_CHECK_ERROR");
            break;
        }
        echo $html;
	}

} 
else {
    echo GetMessage('ERROR');
}

/**
* 
* @param  $prepare
* @param  $location
* @param  $order_id
* 
* @return
*/
function getOffer($prepare, $location, $order_id = FALSE)
{
	global $success;
	global $getSecond;
	
	if($getSecond) {
		$interval= Twinpx\Yadelivery\TwinpxApi::GenerateInterval($location, 4);
		$getSecond = FALSE;
	}
	else {
		$interval= Twinpx\Yadelivery\TwinpxApi::GenerateInterval($location);
		$getSecond = TRUE;
		
	}
	
	$offer = Twinpx\Yadelivery\TwinpxApi::multiRequest('/api/b2b/platform/offers/create', $prepare, $interval);
	
	if ($offer['SUCCESS'] AND !empty($offer['DATA'])) {
//        $result = Twinpx\Yadelivery\TwinpxApi::ShowOfferAdmin($offer['DATA'], $order_id);
        $result = Twinpx\Yadelivery\TwinpxApi::ShowOfferJson($offer['DATA'], FALSE, $order_id);
        $success = TRUE;
    }
    elseif ($offer['SUCCESS'] AND !empty($offer['ERROR'])) {
        $adr = FALSE;
		
        foreach ($offer['ERROR'] as $value) {
            if ( in_array ( "incorrect delivery address or house number not stated, please check" , $value ) ) {
                $adr = TRUE;
            }
        }
        //если нашли ошибку адреса
        if ($adr) {
            $result = GetMessage('Wrong-Address');
            
            $result = '<strong>'.GetMessage("TWINPX_YADELIVERY_NE_UDALOSQ_POLUCITQ").'</strong>';
        }
        else {
            $result = '<strong>'.GetMessage("TWINPX_YADELIVERY_NET_DOSTUPNYH_INTERV").'</strong>';
        }
    }
    elseif ($offer['SUCCESS'] AND empty($offer['ERROR'])) { //если нечего не получено отправим повторно
    	//повторный запрос
    	if($getSecond){
    		$result = getOffer($prepare, $location, $order_id);
    		$getSecond = FALSE;
		} else {
        	$success = FALSE;
        	$result = '<strong>'.GetMessage("TWINPX_YADELIVERY_NET_DOSTUPNYH_INTERV").'</strong>';
		}
    }
    else {
        $result = '<strong>'.GetMessage("TWINPX_YADELIVERY_NET_DOSTUPNYH_INTERV").'</strong>';
    }
    
    return $result;
}
<?php
use \Twinpx\Yadelivery\TwinpxConfigTable;
use \Bitrix\Main\Context;
use \Bitrix\Sale;
use \Bitrix\Catalog;
use \Bitrix\Iblock;
use \Bitrix\Main\Page\Asset;

$module_id = 'twinpx.yadelivery';

IncludeModuleLangFile($_SERVER["DOCUMENT_ROOT"].BX_ROOT."/modules/main/options.php");
IncludeModuleLangFile(__FILE__);

$MODULE_RIGHT = $APPLICATION->GetGroupRight($module_id);
if ($MODULE_RIGHT >= "R") :

CModule::IncludeModule($module_id);
CModule::IncludeModule("sale");
CJSCore::Init(array('twinpx_schedule_app', 'twinpx_admin_lib', 'jquery2'));
$scheme = $request->isHttps() ? 'https' : 'http';
$ya_key = htmlspecialcharsbx(\Bitrix\Main\Config\Option::get('fileman', 'yandex_map_api_key', ''));
$APPLICATION->AddHeadString('<script src="'.$scheme.'://api-maps.yandex.ru/2.1.50/?apikey='.$ya_key.'&load=package.full&lang=ru-RU"></script>', true, false, 'BODY_END'); //подключаем карту

$request = Context::getCurrent()->getRequest();

$stateList = array(
	'DRAFT' => GetMessage('DRAFT'),
	'VALIDATING' => GetMessage('VALIDATING'),
	'VALIDATING_ERROR' => GetMessage('VALIDATING_ERROR'),
	'CREATED' => GetMessage('CREATED'),
	'DELIVERY_PROCESSING_STARTED' => GetMessage('DELIVERY_PROCESSING_STARTED'),
	'DELIVERY_TRACK_RECEIVED' => GetMessage('DELIVERY_TRACK_RECEIVED'),
	'DELIVERY_LOADED' => GetMessage('DELIVERY_LOADED'),
	'CANCELED_IN_PLATFORM' => GetMessage('CANCELED_IN_PLATFORM'),
	'SENDER_WAIT_FULFILLMENT' => GetMessage('SENDER_WAIT_FULFILLMENT'),
	'SENDER_WAIT_DELIVERY' => GetMessage('SENDER_WAIT_DELIVERY'),
	'DELIVERY_AT_START' => GetMessage('DELIVERY_AT_START'),
	'FULFILLMENT_LOADED' => GetMessage('FULFILLMENT_LOADED'),
	'FULFILLMENT_ARRIVED' => GetMessage('FULFILLMENT_ARRIVED'),
	'FULFILLMENT_PREPARED' => GetMessage('FULFILLMENT_PREPARED'),
	'FULFILLMENT_TRANSMITTED' => GetMessage('FULFILLMENT_TRANSMITTED'),
	'DELIVERY_AT_START_SORT' => GetMessage('DELIVERY_AT_START_SORT'),
	'DELIVERY_TRANSPORTATION' => GetMessage('DELIVERY_TRANSPORTATION'),
	'DELIVERY_ARRIVED' => GetMessage('DELIVERY_ARRIVED'),
	'DELIVERY_TRANSPORTATION_RECIPIENT' => GetMessage('DELIVERY_TRANSPORTATION_RECIPIENT'),
	'DELIVERY_CUSTOMS_ARRIVED' => GetMessage('DELIVERY_CUSTOMS_ARRIVED'),
	'DELIVERY_CUSTOMS_CLEARED' => GetMessage('DELIVERY_CUSTOMS_CLEARED'),
	'DELIVERY_STORAGE_PERIOD_EXTENDED' => GetMessage('DELIVERY_STORAGE_PERIOD_EXTENDED'),
	'DELIVERY_STORAGE_PERIOD_EXPIRED' => GetMessage('DELIVERY_STORAGE_PERIOD_EXPIRED'),
	'DELIVERY_UPDATED_BY_SHOP' => GetMessage('DELIVERY_UPDATED_BY_SHOP'),
	'DELIVERY_UPDATED_BY_RECIPIENT' => GetMessage('DELIVERY_UPDATED_BY_RECIPIENT'),
	'DELIVERY_UPDATED_BY_DELIVERY' => GetMessage('DELIVERY_UPDATED_BY_DELIVERY'),
	'DELIVERY_ARRIVED_PICKUP_POINT' => GetMessage('DELIVERY_ARRIVED_PICKUP_POINT'),
	'DELIVERY_TRANSMITTED_TO_RECIPIENT' => GetMessage('DELIVERY_TRANSMITTED_TO_RECIPIENT'),
	'DELIVERY_DELIVERED' => GetMessage('DELIVERY_DELIVERED'),	
	'DELIVERY_ATTEMPT_FAILED' => GetMessage('DELIVERY_ATTEMPT_FAILED'),	
	'DELIVERY_CAN_NOT_BE_COMPLETED' => GetMessage('DELIVERY_CAN_NOT_BE_COMPLETED'),	
	'DELIVERED_FINISH' => GetMessage('DELIVERED_FINISH'),	
	'RETURN_PREPARING' => GetMessage('RETURN_PREPARING'),
	'RETURN_ARRIVED_DELIVERY' => GetMessage('RETURN_ARRIVED_DELIVERY'),	
	'RETURN_ARRIVED_FULFILLMENT' => GetMessage('RETURN_ARRIVED_FULFILLMENT'),	
	'RETURN_PREPARING_SENDER' => GetMessage('RETURN_PREPARING_SENDER'),	
	'RETURN_RETURNED' => GetMessage('RETURN_RETURNED'),	
	'RETURN_TRANSMITTED_FULFILLMENT' => GetMessage('RETURN_TRANSMITTED_FULFILLMENT'),	
	'SORTING_CENTER_CREATED' => GetMessage('SORTING_CENTER_CREATED'),	
	'SORTING_CENTER_PROCESSING_STARTED' => GetMessage('SORTING_CENTER_PROCESSING_STARTED'),	
	'SORTING_CENTER_TRACK_RECEIVED' => GetMessage('SORTING_CENTER_TRACK_RECEIVED'),
	'SORTING_CENTER_LOADED' => GetMessage('SORTING_CENTER_LOADED'),
	'SORTING_CENTER_AT_START' => GetMessage('SORTING_CENTER_AT_START'),
	'SORTING_CENTER_PREPARED' => GetMessage('SORTING_CENTER_PREPARED'),
	'SORTING_CENTER_TRANSMITTED' => GetMessage('SORTING_CENTER_TRANSMITTED'),
	'SORTING_CENTER_OUT_OF_STOCK' => GetMessage('SORTING_CENTER_OUT_OF_STOCK'),	
	'SORTING_CENTER_AWAITING_CLARIFICATION' => GetMessage('SORTING_CENTER_AWAITING_CLARIFICATION'),	
	'SORTING_CENTER_RETURN_PREPARING' => GetMessage('SORTING_CENTER_RETURN_PREPARING'),
	'SORTING_CENTER_RETURN_RFF_PREPARING_FULFILLMENT' => GetMessage('SORTING_CENTER_RETURN_RFF_PREPARING_FULFILLMENT'),
	'SORTING_CENTER_RETURN_RFF_TRANSMITTED_FULFILLMENT' => GetMessage('SORTING_CENTER_RETURN_RFF_TRANSMITTED_FULFILLMENT'),
	'SORTING_CENTER_RETURN_RFF_ARRIVED_FULFILLMENT' => GetMessage('SORTING_CENTER_RETURN_RFF_ARRIVED_FULFILLMENT'),
	'SORTING_CENTER_RETURN_ARRIVED' => GetMessage('SORTING_CENTER_RETURN_ARRIVED'),
	'SORTING_CENTER_RETURN_PREPARING_SENDER' => GetMessage('SORTING_CENTER_RETURN_PREPARING_SENDER'),
	'SORTING_CENTER_RETURN_TRANSFERRED' => GetMessage('SORTING_CENTER_RETURN_TRANSFERRED'),
	'SORTING_CENTER_RETURN_RETURNED' => GetMessage('SORTING_CENTER_RETURN_RETURNED'),
	'SORTING_CENTER_CANCELED' => GetMessage('SORTING_CENTER_CANCELED'),
	'SORTING_CENTER_ERROR' => GetMessage('SORTING_CENTER_ERROR'),
	'LOST' => GetMessage('LOST'),
	'UNEXPECTED' => GetMessage('UNEXPECTED'),
	'CANCELLED' => GetMessage('CANCELLED'),
	'CANCELLED_USER' => GetMessage('CANCELLED_USER'),
	'FINISHED' => GetMessage('FINISHED'),
	'ERROR' => GetMessage('ERROR'),
	'GENERIC_ERROR' => GetMessage('GENERIC_ERROR'),
	'NOT_FOUND' => GetMessage('NOT_FOUND'),
	'OUT_OF_STOCK' => GetMessage('OUT_OF_STOCK'),
	'CREATED_IN_PLATFORM' => GetMessage('CREATED_IN_PLATFORM')
);

//статусы заказа
$resSaleState = CSaleStatus::GetList(array('SORT' => 'ASC'), array('LID' => SITE_ID), false, false, array('ID', 'TYPE', 'NAME'));
$saleState[''] = GetMessage('Select');
$deliveryState[''] = GetMessage('Select');
while($ss = $resSaleState->fetch()){
	if($ss['TYPE'] == 'O') {
		$saleState[$ss['ID']] = $ss['NAME'];
	}
	if($ss['TYPE'] == 'D') {
		$deliveryState[$ss['ID']] = $ss['NAME'];
	}
}

//Получаем настроики из БД
$obConfig = TwinpxConfigTable::getList(array('select' => array('*')));
while($arConfig = $obConfig->Fetch()){
	$configs[$arConfig['CODE']] = $arConfig['VALUE'];
}

//список типов плательщиков, группа по id сайта
$obPersonTyle = CSalePersonType::GetList(Array("SORT" => "ASC"), Array("ACTIVE" => "Y"));
while ($arType = $obPersonTyle->Fetch())
{
	$listType[$arType['LID']][$arType['ID']] = $arType;		
}

//получить список свойств, группа по типу плательщика
$dbProps = CSaleOrderProps::GetList(array("SORT" => "ASC"),array("ACTIVE" => "Y"),false,false,array());
while($arVals = $dbProps->Fetch())
{
	$propsList[$arVals['PERSON_TYPE_ID']][$arVals['ID']] = "[".$arVals['ID']."] " . $arVals['NAME'];
}

//получаем название инфоблоков
$arNameCatalog = [];
$ers_iblock = Iblock\IblockTable::getList(['select' => ['ID', 'NAME']]);
while ($arRes = $ers_iblock->fetch())
{
	$arNameCatalog[$arRes['ID']] = $arRes['NAME'] . ' (ID=' . $arRes['ID'] . ')';
}

//получить список свойств
//получаем количество каталогов
$arIdCatalog = [];
$res_catalog = Catalog\CatalogIblockTable::getList();
while ($arRes = $res_catalog->fetch())
{
	$arIdCatalog[$arRes['IBLOCK_ID']] = $arRes['IBLOCK_ID'];
}

//если есть каталоги получаем свойства из них
if (count($arIdCatalog) > 0)
{
	//свойства которые есть всегда у товара
	$arStandardProps = array(
		'' => GetMessage('Select'),
		'ID' => "[ID] " . GetMessage('Id-element'),
		'XML_ID' => "[XML_ID] " . GetMessage('Xml-element'),
	);
	
	$arProps = [];
	$properties = Iblock\PropertyTable::getList(['filter' => ['ACTIVE' => 'Y', 'IBLOCK_ID' => $arIdCatalog, 'MULTIPLE' => 'N', 'PROPERTY_TYPE' => 'S']]);
	while ($prop_fields = $properties->fetch())
	{
		$arProps[$prop_fields['IBLOCK_ID']]["PROPERTY_".$prop_fields['CODE']] = "[".$prop_fields['CODE']."] ".$prop_fields['NAME'];
	}
	foreach ($arProps as $id => $props)
	{
		$listProps[$id] = array_merge($arStandardProps, $props); //формируем список свойст по группам
	}
}

//получение платежных систем
$cash = array("Y" => GetMessage('CashPayment'), "A" => GetMessage('EcquiringPayment'), "N" => GetMessage('CashlessPayments'));
$dbPay = CSalePaySystem::GetList(array(), array("LID"=>SITE_ID, "ACTIVE" => "Y"), false, false, array("ID", "NAME", "IS_CASH"));
while($pay = $dbPay->Fetch())
{
	$arPay[$pay['ID']] = $pay['NAME']." (".$cash[$pay['IS_CASH']].")";
}
$arPayType = array(
	"" => GetMessage('pay_no_select'),
	"already_paid" => GetMessage('already_paid'),
	"cash_on_receipt" => GetMessage('cash_on_receipt'),
	"card_on_receipt" => GetMessage('card_on_receipt'),
);

//TODO:: REQUEST FROM POST
//Сохраняем данные в Бд
if($request->isPost() && check_bitrix_sessid() && $MODULE_RIGHT == 'W') {
	//оработка пост данные
	if(!empty($request->getPost("PROPERTY"))) {
		foreach($request->getPost("PROPERTY") as $code => $property) {
			if(is_array($property)){
				$serialize = serialize($property); //сериализуем значение
				if(!isset($configs[$code]))
				{
					TwinpxConfigTable::add(array('CODE' => $code, 'VALUE' => $serialize));
				} 
				elseif($configs[$code] == NULL)
				{
					TwinpxConfigTable::update($code, array('VALUE' => $serialize));
				}
				else 
				{
					TwinpxConfigTable::update($code, array('VALUE' => $serialize));
				}
			}
			else {
				if(!isset($configs[$code]))
				{
					TwinpxConfigTable::add(array('CODE' => $code, 'VALUE' => $property));
				} 
				elseif($configs[$code] == NULL)
				{
					TwinpxConfigTable::update($code, array('VALUE' => $property));
				}
				else 
				{
					TwinpxConfigTable::update($code, array('VALUE' => $property));
				}
			}
		}
	}
	
	//обработка чекбоксы
	//Демо
	$checkbox_demo = isset($request->getPost("PROPERTY")["Checkbox_Demo"]) ? 'Y' : 'N';
	if(isset($checkbox_demo))
	{
		TwinpxConfigTable::update("Checkbox_Demo", array('VALUE' => $checkbox_demo));
	}	
	//логирование
	$enable_logs = isset($request->getPost("PROPERTY")["Enable_Logs"]) ? 'Y' : 'N';
	if(isset($enable_logs))
	{
		TwinpxConfigTable::update("Enable_Logs", array('VALUE' => $enable_logs));
	}
	//автоматическое отмена
	$cancel_offer = isset($request->getPost("PROPERTY")['Cancel_Offer']) ? 'Y' : 'N';
	if(isset($cancel_offer))
	{
		TwinpxConfigTable::update("Cancel_Offer", array('VALUE' => $cancel_offer));
	}

	//делаем редирекст чтобы сбросить POST и выводились новый значени
	LocalRedirect($request->getRequestUri());
}

$aTabs = array(
    array("DIV" => "edit1", "TAB" => GetMessage('tab_1_tab'), "TITLE" => GetMessage('tab_1_title')),
    array("DIV" => "edit2", "TAB" => GetMessage('tab_2_tab'), "TITLE" => GetMessage('tab_2_title')),
    array("DIV" => "edit3", "TAB" => GetMessage('tab_3_tab'), "TITLE" => GetMessage('tab_3_title')),
    array("DIV" => "edit4", "TAB" => GetMessage('tab_4_tab'), "TITLE" => GetMessage('tab_4_title')),
    array("DIV" => "edit5", "TAB" => GetMessage('tab_5_tab'), "TITLE" => GetMessage('tab_5_title')),
);
$tabControl = new CAdminTabControl("tabControl", $aTabs);
$tabControl->Begin();
?>
<form method="POST" action="<?echo $APPLICATION->GetCurPage()?>?mid=<?=htmlspecialcharsbx($mid)?>&amp;lang=<?echo LANG?>" name="<?=$module_id?>">
    <?=bitrix_sessid_post();?>
    
    <?
    $tabControl->BeginNextTab();
    ?>
    <tr>
        <td width="30%" valign="top">
            <label for="Checkbox_Demo"><?=GetMessage('Checkbox_Demo')?>:</label>
        </td>
        <td width="70%">
            <input type="checkbox" id="Checkbox_Demo" name="PROPERTY[Checkbox_Demo]" value="Y" <?=($configs['Checkbox_Demo'] == 'Y') ? 'checked' : ''?>/>
        </td>
    </tr>
    <tr>
        <td width="30%" valign="top">
            <label for="OAuth"><?=GetMessage('OAuth')?>:</label>
        </td>
        <td width="70%">
            <input type="text" id="OAuth" name="PROPERTY[OAuth]" value="<?=$configs['OAuth']?>"/>
        </td>
    </tr>
    <tr>
        <td width="30%" valign="top">
            <label for="PlatformId"><?=GetMessage('PlatformId')?>:</label>
        </td>
        <td width="70%">
            <input type="text" id="PlatformId" name="PROPERTY[PlatformId]" value="<?=$configs['PlatformId']?>"/>
            &nbsp;<a href="javascript:void(0);" onclick="checkAuth(this)"><?=GetMessage('Chech_Auth')?></a>
        </td>
    </tr>
    <tr>
        <td width="30%" valign="top">
            <label for="Barcode"><?=GetMessage('PVZ_PlatformId')?>:</label>
        </td>
        <td width="70%">
            <a href="javascript:void(0);" class="adm-btn" onclick="setPlatformId('PlatformId')"><?=GetMessage('SetPlatformId')?></a>
        </td>
    </tr>
    <tr>
        <td width="30%" valign="top">
            <label for="Barcode"><?=GetMessage('Barcode')?>:</label>
        </td>
        <td width="70%">
            <input type="text" id="Barcode" name="PROPERTY[Barcode]" value="<?=$configs['Barcode']?>"/>
        </td>
    </tr>
    <!--<tr>
        <td width="30%" valign="top">
            <label for="Description"><?=GetMessage('Description')?>:</label>
        </td>
        <td width="70%">
            <input type="text" id="Description" name="PROPERTY[Description]" value="<?=$configs['Description']?>"/>
        </td>
    </tr>-->
    <tr>
        <td width="30%" valign="top">
            <label for="Weight"><?=GetMessage('Weight')?>:</label>
        </td>
        <td width="70%">
            <input type="text" id="Weight" name="PROPERTY[Weight]" value="<?=$configs['Weight']?>" />
        </td>
    </tr>
    <tr>
        <td width="30%" valign="top">
            <label for="Volume"><?=GetMessage('Volume')?>:</label>
        </td>
        <td width="70%">
            <input type="text" id="Volume" name="PROPERTY[Volume]" value="<?=$configs['Volume']?>" />
        </td>
    </tr>
    <?/*<tr>
        <td width="30%" valign="top">
            <label for="Length"><?=GetMessage('Length')?>:</label>
        </td>
        <td width="70%">
            <input type="text" id="Length" name="PROPERTY[Length]" value="<?=$configs['Length']?>" />
        </td>
    </tr>
    <tr>
        <td width="30%" valign="top">
            <label for="Height"><?=GetMessage('Height')?>:</label>
        </td>
        <td width="70%">
            <input type="text" id="Height" name="PROPERTY[Height]" value="<?=$configs['Height']?>" />
        </td>
    </tr>
    <tr>
        <td width="30%" valign="top">
            <label for="Width"><?=GetMessage('Width')?>:</label>
        </td>
        <td width="70%">
            <input type="text" id="Width" name="PROPERTY[Width]" value="<?=$configs['Width']?>" />
        </td>
    </tr>
    */?>
    <tr>
        <td width="30%" valign="top">
            <label for="Volume"><?=GetMessage('Schedule')?>:</label>
        </td>
        <td width="70%">
        	<script data-skip-moving="true">
                window.ydTimetableData = {
                    importData: <?=($configs['Schedule'] != NULL ) ? $configs['Schedule'] : '[{"day":1,"start":0,"end":86400},{"day":2,"start":0,"end":86400},{"day":3,"start":0,"end":86400},{"day":4,"start":0,"end":86400},{"day":5,"start":0,"end":86400},{"day":6,"start":0,"end":86400},{"day":7,"start":0,"end":86400}]';?>,
                	inputName: 'PROPERTY[Schedule]',
                	days: [<?=GetMessage('WeekDays')?>]
                };
        	</script>
            <div id="yd-timetable"></div>
        </td>
    </tr>
    <tr>
		<td colspan="2" align="center">
			<?=BeginNote('align="left" name=""');?>
			<?=GetMessage('YandexKey')?>
			<?=EndNote();?>
		</td>
	</tr>
	<tr>
        <td width="30%" valign="top">
            <label for="Enable_Logs"><?=GetMessage('Enable_Logs')?>:</label>
        </td>
        <td width="70%">
            <input type="checkbox" id="Enable_Logs" name="PROPERTY[Enable_Logs]" value="Y" <?=($configs['Enable_Logs'] == 'Y') ? 'checked' : ''?>/>
        </td>
    </tr>
	<?
	$tabControl->BeginNextTab();
	?>
	<?foreach($arIdCatalog as $ibId):?>
		
		<tr class="heading">
			<td colspan="2"><?=GetMessage("PRODUCT_PROPERTY");?> (<?=$arNameCatalog[$ibId]?>)</td>
		</tr>
		<tr>
			<td width="30%" valign="top">
				<label for="ArticleProduct_<?=$ibId?>"><?=GetMessage('ArticleProduct') ?>:</label>
			</td>
			<td width="70%">
				<select name="PROPERTY[ArticleProduct_<?=$ibId?>]" size="1" id="ArticleProduct_<?=$ibId?>">
					<? foreach ($listProps[$ibId] as $k => $p) { ?>
					<option value="<?=$k ?>"
						<? if ($k == $configs['ArticleProduct_'.$ibId]) echo 'selected'; ?> ><?=$p ?>
					</option>
					<?}; ?>
				</select>
			</td>
		</tr>	
		<tr>
			<td width="30%" valign="top">
				<label for="BarcodeProduct_<?=$ibId?>"><?=GetMessage('BarcodeProduct') ?>:</label>
			</td>
			<td width="70%">
				<select name="PROPERTY[BarcodeProduct_<?=$ibId?>]" size="1" id="BarcodeProduct_<?=$ibId?>">
					<? foreach ($listProps[$ibId] as $k => $p) { ?>
					<option value="<?=$k ?>"
						<? if ($k == $configs['BarcodeProduct_'.$ibId]) echo 'selected'; ?> ><?=$p ?>
					</option>
					<?}; ?>
				</select>
			</td>
		</tr>
	<?endforeach?>
		
	<tr class="heading">
		<td colspan="2"><?=GetMessage('ProfileType')?></td>		
	</tr>
	
	<?foreach($listType as $sid => $stype){?>
		<?foreach($stype as $pid => $ptype){?>
			<?
			$propsType = $propsList[$pid];
			$propsType += array("ORDER_DESCRIPTION" =>  GetMessage('PropOrderComment'));			
			if(empty($propsType)) continue;
			?>
			<tr class="heading">
				<td colspan="2"><?=$ptype['NAME']?> (<?=$sid?>)<br/><?=GetMessage('ProfileTitle')?></td>		
			</tr>
						
			<tr>
				<td width="30%" valign="top">
					<label for="PROPERTY-<?=$pid?>-PropFio"><?=GetMessage('PropFio') ?>:</label>
				</td>
				<td width="70%">
					<select name="PROPERTY[PropFio_<?=$pid?>]" size="1" id="PROPERTY-<?=$pid?>-PropFio">
						<option value=""><?=GetMessage('Select')?></option>
						<? foreach ($propsType as $k => $p) { ?>
						<option value="<?=$k ?>"
							<? if ($k == $configs['PropFio_'.$pid]) echo 'selected'; ?> ><?=$p?>
						</option>
						<?}; ?>
					</select>
				</td>
			</tr>
			<tr>
				<td width="30%" valign="top">
					<label for="PropEmail"><?=GetMessage('PropEmail') ?>:</label>
				</td>
				<td width="70%">
					<select name="PROPERTY[PropEmail_<?=$pid?>]" size="1" id="PROPERTY-<?=$pid?>-PropEmail">
						<option value=""><?=GetMessage('Select')?></option>
						<? foreach ($propsType as $k => $p) { ?>
						<option value="<?=$k ?>"
							<?if ($k == $configs['PropEmail_'.$pid]) echo 'selected'; ?> ><?=$p ?>
						</option>
						<?}; ?>
					</select>
				</td>
			</tr>
			<tr>
				<td width="30%" valign="top">
					<label for="PropPhone"><?=GetMessage('PropPhone') ?>:</label>
				</td>
				<td width="70%">
					<select name="PROPERTY[PropPhone_<?=$pid?>]" size="1" id="PROPERTY-<?=$pid?>-PropPhone">
						<option value=""><?=GetMessage('Select')?></option>
						<? foreach ($propsType as $k => $p) { ?>
						<option value="<?=$k ?>"
							<? if ($k == $configs['PropPhone_'.$pid]) echo 'selected'; ?> ><?=$p ?>
						</option>
						<?}; ?>
					</select>
				</td>
			</tr>
			<tr>
				<td width="30%" valign="top">
					<label for="PropComment"><?=GetMessage('PropComment') ?>:</label>
				</td>
				<td width="70%">
					<select name="PROPERTY[PropComment_<?=$pid?>]" size="1" id="PROPERTY-<?=$pid?>-PropComment">
						<option value=""><?=GetMessage('Select')?></option>
						<? foreach ($propsType as $k => $p) { ?>
						<option value="<?=$k ?>"
							<? if ($k == $configs['PropComment_'.$pid]) echo 'selected'; ?> ><?=$p ?>
						</option>
						<?}; ?>
					</select>
				</td>
			</tr>
			
			<tr class="heading">
				<td colspan="2"><?=$ptype['NAME']?> (<?=$sid?>)<br/> <?=GetMessage("DeliveryTitle");?></td>
			</tr>
			<tr>
				<td width="30%" valign="top">
					<label for="PropCity"><?=GetMessage('PropCity') ?>:</label>
				</td>
				<td width="70%">
					<select name="PROPERTY[PropCity_<?=$pid?>]" size="1" id="PROPERTY-<?=$pid?>-PropCity">
						<option value=""><?=GetMessage('Select')?></option>
						<? foreach ($propsType as $k => $p) { ?>
						<option value="<?=$k ?>"
							<? if ($k == $configs['PropCity_'.$pid]) echo 'selected'; ?> ><?=$p ?>
						</option>
						<?}; ?>
					</select>
				</td>
			</tr>	
			<tr>
				<td colspan="2" align="center">
					<?=BeginNote('align="center" name=""');?>
					<?=GetMessage('Address_Info')?>
					<?=EndNote();?>
				</td>
			</tr>
			<tr>
				<td width="30%" valign="top">
					<label for="PropStreet"><?=GetMessage('PropStreet') ?>:</label>
				</td>
				<td width="70%">
					<select name="PROPERTY[PropStreet_<?=$pid?>]" size="1" id="PROPERTY-<?=$pid?>-PropStreet">
						<option value=""><?=GetMessage('Select')?></option>
						<? foreach ($propsType as $k => $p) { ?>
						<option value="<?=$k ?>"
							<? if ($k == $configs['PropStreet_'.$pid]) echo 'selected'; ?> ><?=$p ?>
						</option>
						<?}; ?>
					</select>
				</td>
			</tr>	
			<tr>
				<td width="30%" valign="top">
					<label for="PropHome"><?=GetMessage('PropHome') ?>:</label>
				</td>
				<td width="70%">
					<select name="PROPERTY[PropHome_<?=$pid?>]" size="1" id="PROPERTY-<?=$pid?>-PropHome">
						<option value=""><?=GetMessage('Select')?></option>
						<? foreach ($propsType as $k => $p) { ?>
						<option value="<?=$k ?>"
							<? if ($k == $configs['PropHome_'.$pid]) echo 'selected'; ?> ><?=$p ?>
						</option>
						<?}; ?>
					</select>
				</td>
			</tr>
			<tr>
				<td width="30%" valign="top">
					<label for="PropCorp"><?=GetMessage('PropCorp') ?>:</label>
				</td>
				<td width="70%">
					<select name="PROPERTY[PropCorp_<?=$pid?>]" size="1" id="PROPERTY-<?=$pid?>-PropCorp">
						<option value=""><?=GetMessage('Select')?></option>
						<? foreach ($propsType as $k => $p) { ?>
						<option value="<?=$k ?>"
							<? if ($k == $configs['PropCorp_'.$pid]) echo 'selected'; ?> ><?=$p ?>
						</option>
						<?}; ?>
					</select>
				</td>
			</tr>
			<tr>
				<td width="30%" valign="top">
					<label for="PropApartament"><?=GetMessage('PropApartament') ?>:</label>
				</td>
				<td width="70%">
					<select name="PROPERTY[PropApartament_<?=$pid?>]" size="1" id="PROPERTY-<?=$pid?>-PropApartament">
						<option value=""><?=GetMessage('Select')?></option>
						<? foreach ($propsType as $k => $p) { ?>
						<option value="<?=$k ?>"
							<? if ($k == $configs['PropApartament_'.$pid]) echo 'selected'; ?> ><?=$p ?>
						</option>
						<?}; ?>
					</select>
				</td>
			</tr>
		<?}?>
	<?}?>
	
	<?
	$tabControl->BeginNextTab();
	?>
	
	<? foreach ($arPay as $k => $p) { ?>
	<tr>
		<td width="30%" valign="top">
			<label for="Pay_<?=$k?>"><?=$p?></label>
		</td>
		<td width="70%">
			<select name="PROPERTY[Pay_<?=$k?>]" id="Pay_<?=$k?>">
				<? foreach ($arPayType as $c => $t) { ?>
				<option value="<?=$c?>"
					<? if ($c == $configs['Pay_'.$k]) echo 'selected'; ?> ><?=$t?>
				</option>
				<?}; ?>
			</select>
		</td>
	</tr>
	<?};?>
	
	<?
	$tabControl->BeginNextTab();
	?>
	<tr>
		<td width="30%" valign="top">			
			<label for="Cancel_Offer"><?=GetMessage("Cancel_Offer")?>:</label>
		</td>
		<td width="70%">
			<input type="checkbox" id="Cancel_Offer" name="PROPERTY[Cancel_Offer]" value="Y" <?=($configs['Cancel_Offer'] == 'Y') ? 'checked' : ''?> onclick="toogleCancelOptions(this)"/>
		</td>
	</tr>
	<tr id="cancel_group_1" <?if (!$configs['Cancel_Offer'] || $configs['Cancel_Offer'] == 'N'):?>style="display: none"<?endif;?>>
		<td width="30%" valign="top">
			<span id="hint_CancelDelivery"></span>
			<script type="text/javascript">BX.hint_replace(BX('hint_CancelDelivery'), '<?=\CUtil::JSEscape(GetMessage("Hint_Cancel_Delivery")); ?>');</script>
			<label for="CancelDelivery"><?=GetMessage("Cancel_Delivery")?>:</label>
		</td>
		<td width="70%">
			<input type="number" min="0" id="CancelDelivery" name="PROPERTY[CancelDelivery]" value="<?=$configs['CancelDelivery']?>" />
		</td>
	</tr>
	<tr id="cancel_group_2" <?if (!$configs['Cancel_Offer'] || $configs['Cancel_Offer'] == 'N'):?>style="display: none"<?endif;?>>
		<td width="30%" valign="top">
			<span id="hint_CancelPaid"></span>
			<script type="text/javascript">BX.hint_replace(BX('hint_CancelPaid'), '<?=\CUtil::JSEscape(GetMessage("Hint_Cancel_Paid")); ?>');</script>
			<label for="CancelPaid"><?=GetMessage('Cancel_Paid')?>:</label>
		</td>
		<td width="70%">
			<input type="number" min="0" id="CancelPaid" name="PROPERTY[CancelPaid]" value="<?=$configs['CancelPaid']?>" />
		</td>
	</tr>
	<?
	$tabControl->BeginNextTab();
	?>
	<tr class="heading">
		<td width="20%" valign="center" align="center">
			<?=GetMessage('StateYandex') ?>
		</td>
		<td width="30%" valign="center" align="center">
			<?=GetMessage('StateOrderSale') ?>
		</td>
		<td width="30%" valign="center" align="center">
			<?=GetMessage('StateDeliverySale') ?>
		</td>
		<td width="10%" valign="center" align="center">
			<?=GetMessage('StateShipped') ?>
		</td>
		<td width="10%" valign="center" align="center">
			<?=GetMessage('StateArhived') ?>
		</td>
	</tr>
	<? foreach ($stateList as $kod => $value) : ?>
		<?$values = unserialize($configs[$kod]); //массив с значение из БД?>
		<tr class="list_state">
			<td width="20%" valign="center" align="center"><?=$value?></td> 
			<td width="30%" valign="center" align="center">
				<select name="PROPERTY[<?=$kod?>][SALE]">
					<? foreach ($saleState as $c => $t) { ?>
					<option value="<?=$c?>"
						<?=($c == $values['SALE']) ? 'selected' : ''?>><?=$t?> <?=($c) ? '['.$c.']' : ''?>
					</option>
					<?}; ?>
				</select>
			</td>
			<td width="30%" valign="center" align="center">
				<select name="PROPERTY[<?=$kod?>][DELIVERY]">
					<? foreach ($deliveryState as $c => $t) { ?>
					<option value="<?=$c?>"
						<? if ($c == $values['DELIVERY']) echo 'selected'; ?> ><?=$t?> <?=($c) ? '['.$c.']' : ''?>
					</option>
					<?}; ?>
				</select>
			</td>
			<td width="10%" valign="center" align="center">
				<input type="checkbox" name="PROPERTY[<?=$kod?>][SHIPPED]" value="Y" <?=($values['SHIPPED'] == 'Y') ? 'checked' : ''?>/>
			</td>
			<td width="10%" valign="center" align="center">
				<input type="checkbox" name="PROPERTY[<?=$kod?>][ARHIVED]" value="Y" <?=($values['ARHIVED'] == 'Y') ? 'checked' : ''?>/>
			</td>
		</tr>
	<? endforeach; ?>
	
	<?/*
	$tabControl->BeginNextTab();
	?>
	<?require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/admin/group_rights.php");*/?>
	<?//конец табов?>
    <?$tabControl->Buttons();?>
    <input type="submit" class="adm-btn-save" name="SAVE" value="<?=GetMessage('Save')?>" <?=($MODULE_RIGHT != 'W') ? 'disabled' : ''?>/>
    <!--<input type="reset" name="reset" value="<?=GetMessage('Reset')?>" />-->
    <input type="button" value="<?=GetMessage('Cancel')?>" name="cancel" onclick="top.window.location='settings.php?lang=<?=SITE_ID?>'">
    <?$tabControl->End();?>
</form>

<!--локальный стили-->
<style>
	.list_state select{max-width:350px;min-width:200px}
	#modal_chech_key{padding:20px}
	.adm-workarea input[type="number"]{font-size:13px;height:25px;padding:0 5px;margin:0;background:#fff;border:1px solid;border-color:#87919c #959ea9 #9ea7b1;border-radius:4px;color:#000;-webkit-box-shadow:0 1px 0 0 rgba(255,255,255,0.3),inset 0 2px 2px -1px rgba(180,188,191,0.7);box-shadow:0 1px 0 0 rgba(255,255,255,0.3),inset 0 2px 2px -1px rgba(180,188,191,0.7);display:inline-block;outline:none;vertical-align:middle;-webkit-font-smoothing:antialiased}
</style>
<!--скрипты надо подключить в конце страницы-->
<script type="text/javascript">
	function toogleCancelOptions(el)
	{
		BX.style(BX('cancel_group_1'), 'display', el.checked? 'table-row': 'none');
		BX.style(BX('cancel_group_2'), 'display', el.checked? 'table-row': 'none');
	}

    function checkAuth(e)
    {
        let ModuleForm = e.closest('form'),
        fields = $(ModuleForm).serialize()
        var Auth = new BX.PopupWindow(`modal_chech_key`, null, {
                content: `<div id="modal_chech_key_context"><div class="loading"><?=GetMessage('Load_Checking')?></div></div>`,
                //titleBar: {content: ''},
                width: 'auto',
                height: 'auto',
                min_width: 300,
                min_height: 300,
                zIndex: 100,
                autoHide : true,
                offsetTop : 1,
                offsetLeft : 0,
                lightShadow : true,
                closeIcon : true,
                closeByEsc : true,
                draggable: {
                    restrict: false
                },
                overlay: {
                    backgroundColor: 'black', opacity: '80'
                },
                events: {
                    onPopupShow: function() {
                        BX.ajax.post('/bitrix/tools/twinpx.yadelivery/admin/ajax.php', {
                                fields: fields, action: 'auth'
                            }, function(data) {
                                node = document.getElementById(`modal_chech_key_context`);
                                node.innerHTML = data;
                                node.classList.remove('loading');

                                Auth.adjustPosition();
                            });
                    },
                    onPopupClose: function (Auth) {
                        Auth.destroy();
                    }
                },
                buttons: [
                    /*new BX.PopupWindowButton({
                    text: BX.message('TWINPX_JS_CLOSE'),
                    className: "link-cancel",
                    events: {
                    click: function() {
                    this.popupWindow.close(); // закрытие окна
                    //                            document.location.reload();
                    }
                    }
                    })*/
                ]
            });
        Auth.show(); //открываем модальку
    }
    window.twinpxYadeliveryFetchURL = '/bitrix/tools/<?=$module_id?>/admin/ajax.php';
</script>
<script src="/bitrix/js/<?=$module_id?>/admin/chunk-vendors.93f85daa.js"></script>
<script src="/bitrix/js/<?=$module_id?>/admin/app.ef1751b5.js"></script>
<?endif?>
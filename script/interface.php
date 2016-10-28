<?php

define('INC_FROM_CRON_SCRIPT', true);
set_time_limit(0);

require('../config.php');

//dol_include_once('/asset/config.php');
dol_include_once('/asset/lib/asset.lib.php');
dol_include_once('/asset/class/asset.class.php');

//Interface qui renvoie les emprunts de ressources d'un utilisateur
$PDOdb=new TPDOdb;
global $langs;

$get = GETPOST('get');
_get($PDOdb, $get);

function _get(&$PDOdb, $get) {

	switch ($get) {
		case 'serial_number':

			__out(_serial_number($PDOdb, GETPOST('term')),'json');

			break;
        case 'autocomplete_asset':
            __out(_autocomplete_asset($PDOdb,GETPOST('lot_number'),GETPOST('productid')),'json');
            break;
		case 'autocomplete_lot_number':
            __out(_autocomplete_lot_number($PDOdb,GETPOST('productid')),'json');
            break;
	}

}

function _serial_number(&$PDOdb, $sn) {

	$sql = "SELECT DISTINCT(rowid) as id, serial_number
			FROM ".MAIN_DB_PREFIX."asset
			WHERE serial_number LIKE '".$sn."%'";
	$PDOdb->Execute($sql);
	$Tab=array();

	while($obj=$PDOdb->Get_line()) {
		/*
		$Tab[]=array(
			'value'=>$obj->id
			,'label'=>$obj->serial_number
		);
		*/

		$Tab[]=$obj->serial_number;
	}

	return $Tab;
}

function _autocomplete_asset(&$PDOdb, $lot_number, $productid) {
	global $db, $conf, $langs;
	$langs->load('other');
	dol_include_once('/core/lib/product.lib.php');

	$sql = "SELECT DISTINCT(rowid)
			FROM ".MAIN_DB_PREFIX."asset
			WHERE lot_number = '".$lot_number."'
			AND fk_product = ".$productid;
	$PDOdb->Execute($sql);
	$TAssetIds = $PDOdb->Get_All();

	$Tres = array();
	foreach ($TAssetIds as $res) {

		$asset = new TAsset;
		$asset->load($PDOdb, $res->rowid);
		$asset->load_asset_type($PDOdb);

		//pre($asset,true);

		$Tres[$PDOdb->Get_field('serial_number')]['serial_number'] = $PDOdb->Get_field('serial_number');
		$Tres[$PDOdb->Get_field('serial_number')]['qty'] = $PDOdb->Get_field('contenancereel_value');
		$Tres[$PDOdb->Get_field('serial_number')]['unite_string'] = ($asset->assetType->measuring_units == 'unit') ? 'unité(s)' : measuring_units_string($PDOdb->Get_field('contenancereel_units'),$asset->assetType->measuring_units);
		$Tres[$PDOdb->Get_field('serial_number')]['unite'] = ($asset->assetType->measuring_units == 'unit') ? 'unité(s)' : $PDOdb->Get_field('contenancereel_units');
	}
	return $Tres;
}

function _autocomplete_lot_number(&$PDOdb, $productid) {
	global $db, $conf, $langs;
	$langs->load('other');
	dol_include_once('/core/lib/product.lib.php');

	$sql = "SELECT DISTINCT(lot_number),rowid, SUM(contenancereel_value) as qty, contenancereel_units as unit
			FROM ".MAIN_DB_PREFIX."asset
			WHERE fk_product = ".$productid." GROUP BY lot_number,contenancereel_units,rowid";
	$PDOdb->Execute($sql);

	$TLotNumber = array('');
	$PDOdb->Execute($sql);
	$Tres = $PDOdb->Get_All();
	foreach($Tres as $res){

		$asset = new TAsset;
		$asset->load($PDOdb, $res->rowid);
		$asset->load_asset_type($PDOdb);
		//pre($asset,true);exit;
		$TLotNumber[$res->lot_number]['lot_number'] = $res->lot_number;
		$TLotNumber[$res->lot_number]['label'] = $res->lot_number." / ".$res->qty." ".(($asset->assetType->measuring_units == 'unit') ? 'unité(s)' : measuring_units_string($res->unit,$asset->assetType->measuring_units));
	}
	return $TLotNumber;
}

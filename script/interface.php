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
$type = GETPOST('type');

actions($PDOdb, $type);

function actions(&$PDOdb, $type) {
	
	if($type == 'get'){
	
		switch (GETPOST('get')) {
	        case 'autocomplete_asset':
	            __out(_autocomplete_asset($PDOdb,GETPOST('lot_number')),'json');
	            break;
		}
	}
}

function _autocomplete_asset(&$PDOdb, $lot_number) {
	global $db, $conf, $langs;
	$langs->load('other');
	dol_include_once('/core/lib/product.lib.php');
	
	$sql = "SELECT DISTINCT(serial_number),contenancereel_value,contenancereel_units 
			FROM ".MAIN_DB_PREFIX."asset 
			WHERE lot_number = '".$lot_number."'";
	$PDOdb->Execute($sql);
	
	$Tres = array();
	while ($PDOdb->Get_line()) {
		$Tres[$PDOdb->Get_field('serial_number')]['serial_number'] = $PDOdb->Get_field('serial_number');
		$Tres[$PDOdb->Get_field('serial_number')]['qty'] = $PDOdb->Get_field('contenancereel_value')." ".measuring_units_string($PDOdb->Get_field('contenancereel_units'),'weight');
	}
	return $Tres;
}

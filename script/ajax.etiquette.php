<?php
require("../config.php");
require(DOL_DOCUMENT_ROOT."/custom/asset/class/asset.class.php");

if(isset($_REQUEST['modele'])){
	
	//Récupération des parametres transmis
	$nbVides = (!empty($_REQUEST['startpos'])) ? $_REQUEST['startpos']-1 : 0;
	$nbCopies = (!empty($_REQUEST['copie'])) ? $_REQUEST['copie'] : 1;
	$modele = $_REQUEST['modele'];
	
	$TetiquettesVides = array();
	$Tetiquettes = array();
	
	//création des div vides
	for($i=0; $i< $nbVides; $i++){
		$TetiquettesVides[$i] = array($i);
	}
	
	$TPDOdb = new TPDOdb;
	
	$sql = "SELECT p.label as nom, p.note as descritpion, eda.tare as tare, a.serial_number as code, a.lot_number as lot, eda.weight_reel as poids, eda.weight_reel_unit as poids_unit, eda.tare_unit as tare_unit
			FROM ".MAIN_DB_PREFIX."expedtiondet_asset as eda
				LEFT JOIN ".MAIN_DB_PREFIX."asset as a ON (a.rowid = eda.fk_asset)
				LEFT JOIN ".MAIN_DB_PREFIX."product as p ON (p.rowid = a.fk_product)
			WHERE eda.rowid = ".$idLigneDetail;
	
	$TPDOdb->Execute($sql);
	
	$Tetiquettes[] = array(
						"nom" => $TPDOdb->Get_field('nom'),
						"description" => $TPDOdb->Get_field('description'),
						"tare" => $TPDOdb->Get_field('tare'),
						"tare_unit" => $TPDOdb->Get_field('tare_unit'),
						"code" => $TPDOdb->Get_field('code'),
						"lot" => $TPDOdb->Get_field('lot'),
						"poids" => $TPDOdb->Get_field('poids'),
						"poids_unit" => $TPDOdb->Get_field('poids_unit'),
					);
	
	$TBS = new TTemplateTBS();
	$rendu = $TBS->Render("../modele/".$modele,
					array(),
					array('etiquette_vide'=>$TetiquettesVides,
						  'etiquette'=>$Tetiquettes)
				);
	
	return $rendu;
}
else{
	return 0;
}

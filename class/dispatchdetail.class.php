<?php

class TDispatchDetail extends TObjetStd {
	function __construct() {
		global $langs;
		
		parent::set_table(MAIN_DB_PREFIX.'expeditiondet_asset');
		parent::add_champs('fk_expeditiondet,fk_asset','type=entier;index;');
		parent::add_champs('rang','type=entier;');
		parent::add_champs('weight, weight_reel, tare','type=float;');
		parent::add_champs('weight_unit, weight_reel_unit, tare_unit','type=entier;');
		
		parent::_init_vars();
		parent::start();
		
		$this->lines = array();
		$this->nbLines = 0;
	}
	
	//Charges les lignes de flacon associé à la ligne d'expédition passé en paramètre
	function loadLines(&$PDOdb, $id_expeditionLine){
		
		$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."expeditiondet_asset WHERE fk_expeditiondet = ".$id_expeditionLine." ORDER BY rang";
		
		$TIdExpedet = TRequeteCore::_get_id_by_sql($PDOdb, $sql);
		
		foreach($TIdExpedet as $idexpedet){
			$dispatchdetail_temp = new TDispatchDetail;
			$dispatchdetail_temp->load($PDOdb, $idexpedet);
			$this->lines[] = $dispatchdetail_temp;
			$this->nbLines = $this->nbLines + 1;
		}
	}
	
	function getPoidsExpedie(&$PDOdb,$id_expeditionLine){
		$sql = "SELECT SUM(weight) as Total FROM ".MAIN_DB_PREFIX."expeditiondet_asset WHERE fk_expeditiondet = ".$id_expeditionLine;
		$PDOdb->Execute($sql);
		return ($PDOdb->Get_line()) ? $PDOdb->Get_field('Total') : 0 ;
	}
}
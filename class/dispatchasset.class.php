<?php

class TDispatch extends TObjetStd {
	function __construct() {
		global $langs;
		
		parent::set_table(MAIN_DB_PREFIX.'dispatch');
		parent::add_champs('fk_object',array('type'=>'int', 'index'=>true));
		parent::add_champs('type_object',array('length'=>30, 'index'=>true));
		parent::_init_vars();
		parent::start();
		
		$this->setChild('TDispatchAsset', 'fk_dispatch');
	}
	
	function loadByObject(&$PDOdb, $id, $type_object) {
		
		$this->fk_object = $id;
		$this->type_object = $type_object;
		
		$PDOdb->Execute("SELECT rowid FROM ".$this->get_table()." WHERE fk_object=".$id." AND type_object='".$type_object."' ");
		if($obj = $PDOdb->Get_line()) {
			return $this->load($PDOdb, $obj->rowid);
			
		}
		else {
			return false;
		}
		
	}
}

class TDispatchAsset extends TObjetStd {
	
	function __construct() {
		global $langs;
		
		parent::set_table(MAIN_DB_PREFIX.'dispatch_asset');
		parent::add_champs('fk_object,fk_dispatch,fk_asset',array('type'=>'int', 'index'=>true));
		parent::add_champs('type_object',array('length'=>30, 'index'=>true));
		parent::_init_vars();
		parent::start();
		
		$this->asset=new TAsset;
	}
	function load(&$PDOdb, $id, $withChildren = true) {
		
		parent::load($PDOdb, $id, $withChildren);
		
		if($this->fk_asset>0) $this->asset->load($PDOdb, $this->fk_asset,false);
		
		
	}
	
}

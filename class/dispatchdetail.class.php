<?php

class TDispatchDetail extends TObjetStd {
	function __construct() {
		global $langs;
		
		parent::set_table(MAIN_DB_PREFIX.'dispatchdet');
		parent::add_champs('fk_expeditiondet,fk_asset','type=entier;index;');
		parent::add_champs('rang','type=entier;');
		parent::add_champs('weight, weight_reel, tare','type=float;');
		parent::add_champs('weight_unit, weight_reel_unit, tare_unit','type=entier;');
		
		parent::_init_vars();
		parent::start();
	}
}
<?php

class TDispatch extends TObjetStd {
	function __construct() { /* declaration */
		global $langs;
		
		parent::set_table(MAIN_DB_PREFIX.'dispatch');
		parent::add_champs('ref','type=chaine;index;');
		parent::add_champs('note_private, note_public, model_pdf','type=chaine;');
		parent::add_champs('height,width,entity, size_units, size, weight_units, weight','type=entier;');
		parent::add_champs('fk_soc,fk_user_author,entity, fk_expedition_method, fk_commande','type=entier;index;');
		parent::add_champs('fk_statut','type=entier;');
		parent::add_champs('date_valid,date_expedition,date_livraison','type=date;');
		
		parent::_init_vars();
		parent::start();
	}
}

class TDispatchdet extends TObjetStd {
	function __construct() { /* declaration */
		global $langs;
		
		parent::set_table(MAIN_DB_PREFIX.'dispatchdet');
		parent::add_champs('fk_dispatch','type=entier;index;');
		parent::add_champs('fk_entrepot, fk_product, fk_commandedet, rang','type=entier;');
				
		parent::_init_vars();
		parent::start();
	}
}

class TDispatchdet_asset extends TObjetStd {
	function __construct() { /* declaration */
		global $langs;
		
		parent::set_table(MAIN_DB_PREFIX.'dispatchdet_asset');
		parent::add_champs('fk_dispatchdet','type=entier;index;');
		parent::add_champs('fk_product, fk_asset, rang','type=entier;');
		parent::add_champs('weight, weight_reel, tare','type=float;');
		parent::add_champs('weight_unit, weight_reel_unit, tare_unit','type=entier;');
				
		parent::_init_vars();
		parent::start();
	}
}
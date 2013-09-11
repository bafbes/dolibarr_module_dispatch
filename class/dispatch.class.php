<?php

class TDispatch extends TObjetStd {
	function __construct() { /* declaration */
		global $langs;
		
		parent::set_table(MAIN_DB_PREFIX.'dispatch');
		parent::add_champs('ref','type=chaine;index;');
		parent::add_champs('num_transporteur, note_private, note_public, model_pdf','type=chaine;');
		parent::add_champs('entity, weight_units, weight','type=entier;');
		parent::add_champs('fk_soc,fk_user_author,entity, fk_expedition_method, fk_commande','type=entier;index;');
		parent::add_champs('type_expedition,statut, etat','type=entier;');
		parent::add_champs('date_valid,date_expedition,date_livraison','type=date;');
		parent::add_champs('height, width','type=float;');
		
		parent::_init_vars();
		parent::start();
		
		$this->prefix = "SH";
		
		$lines = array(); //Tableau d'objet Idspatchdet_asset
	}
	
	//Parse les varibales passé par le formulaire de création d'une expédition
	// retour : Tab[id_ligne_commandedet][compteur_ligne_equipement][element_formulaire] = valeur_element_formulaire
	function FormParser($Tvar){
		
		$TLigneToDispatch = array();
		$ligne = "";
		$change = false;
		foreach($Tvar as $cle=>$val){
			
			$Tcle = explode("_",$cle);
			
			if(is_numeric($Tcle[1])){
				if($Tvar["poids_".$Tcle[1]."_".$Tcle[2]] != "" && $Tvar["poidsreel_".$Tcle[1]."_".$Tcle[2]] != "" && $Tvar["tare_".$Tcle[1]."_".$Tcle[2]] != "")
					$TLigneToDispatch[$Tcle[1]][$Tcle[2]][$Tcle[0]] = $val;
			}
			else
				$TLigneToDispatch[$cle] = $val; 
		}
		return $TLigneToDispatch;
	}
	
	function loadLines($ATMdb,$id_commandedet){
		$this->lines = array();
		$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."dispatchdet WHERE fk_dispatch = ".$this->rowid." AND fk_commandedet = ".$id_commandedet." ORDER BY rowid ASC";
		$ATMdb->Execute($sql);
		
		while($ATMdb->Get_line()){
			$ATMdb2 = new Tdb;
			$sql2 = "SELECT rowid FROM ".MAIN_DB_PREFIX."dispatchdet_asset WHERE fk_dispatchdet = ".$ATMdb->Get_field('rowid')." ORDER BY fk_dispatchdet, rang ASC";
			$ATMdb2->Execute($sql2);
			
			while($ATMdb2->Get_line()){
				$ATMdb3 = new Tdb;
				$disatchdet_asset = new TDispatchdet_asset;
				$disatchdet_asset->load($ATMdb3,$ATMdb2->Get_field('rowid'));
				$this->lines[] = $disatchdet_asset;
				$ATMdb3->close();
			}
			$ATMdb2->close();
		}
		
		if(count($this->lines) > 0)
			return true;
		else
			return false;
	}
	
	function addLines($TLigneToDispatch,&$commande,&$ATMdb,$action = ""){
		foreach($TLigneToDispatch as $cle=>$val){
			
			if(is_numeric($cle)){
				//Création de l'association Dispatch => Dispatchdet
				$TDispatchdet = new TDispatchdet;
				
				if($action == "update_expedition")
					$TDispatchdet->loadBy(&$ATMdb,$cle,"fk_commandedet");
				
				$TDispatchdet->fk_dispatch = $this->rowid;
				$TDispatchdet->fk_commandedet = $cle;
				$TDispatchdet->save($ATMdb);
				
				//Création des associations Dispatchdet => Asset
				foreach($TLigneToDispatch[$cle] as $name=>$value){
					$Tdispatchdet_asset =  new TDispatchdet_asset;
					
					if($action == "update_expedition")
						$Tdispatchdet_asset->load(&$ATMdb,$value['idDispatchdetAsset']);
					
					$Tdispatchdet_asset->fk_dispatchdet = $TDispatchdet->rowid; 
					$Tdispatchdet_asset->fk_asset = $value['equipement'];
					$Tdispatchdet_asset->rang = $name;
					$Tdispatchdet_asset->weight = floatval(str_replace(",", ".", $value['poids']));
					$Tdispatchdet_asset->weight_reel = floatval(str_replace(",", ".", $value['poidsreel']));
					$Tdispatchdet_asset->tare = floatval(str_replace(",", ".", $value['tare']));
					$Tdispatchdet_asset->weight_unit = $value['unitepoids'];
					$Tdispatchdet_asset->weight_reel_unit = $value['unitereel'];
					$Tdispatchdet_asset->tare_unit = $value['unitetare'];
					
					/*echo '<pre>';
					print_r($Tdispatchdet_asset);
					echo '</pre>';exit;*/
					$Tdispatchdet_asset->save($ATMdb);
				}
			}
		}
	}
	
	function enregistrer(&$ATMdb,$commande,$_REQUEST){
		$TLigneToDispatch = $this->FormParser($_REQUEST);
			
		if($_REQUEST['action'] == "update_expedition")
			$this->load(&$ATMdb, $_REQUEST['fk_dispatch']);
		
		$this->statut = 0; //Brouillon				
		$this->ref = ($TLigneToDispatch['ref_expe'] != "") ? $TLigneToDispatch['ref_expe'] : $this->getNextValue();
		$this->date_livraison = $TLigneToDispatch['date_livraison'];
		$this->type_expedition = $TLigneToDispatch['methode_dispatch'];
		$this->height = $TLigneToDispatch['hauteur'];
		$this->width = $TLigneToDispatch['largeur'];
		$this->weight = $TLigneToDispatch['poid_general'];
		$this->num_transporteur = $TLigneToDispatch['num_transporteur'];
		$this->fk_commande = $commande->id;
		$this->save($ATMdb);
		$this->addLines($TLigneToDispatch,$commande,&$ATMdb,$_REQUEST['action']);
	}
	
	function valider(&$ATMdb,$commande){
		require_once DOL_DOCUMENT_ROOT."/custom/asset/class/asset.class.php";
		require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
		
		$this->statut = 1; //Validé
		$this->ref = $this->getNextValue();
		$this->save($ATMdb);
		
		foreach($commande->lines as $line){
			//Cahrge les équipements lié à la ligne de commande
			$this->loadLines($ATMdb, $line->rowid);
			
			//Ajoute une sortie de stock pour chaque equipement lié à l'expédition de la commande
			foreach($this->lines as $lineAsset){
				$asset = new TAsset;
				$asset->load(&$ATMdb,$lineAsset->fk_asset);
				$asset->contenancereel_value = $asset->contenancereel_value - $lineAsset->weight_reel;
				$asset->save(&$ATMdb,"Validation Expedition");
			}
		}
	}
	
	function reouvrir(&$ATMdb,$commande){
		require_once DOL_DOCUMENT_ROOT."/custom/asset/class/asset.class.php";
		require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
		
		$this->statut = 0; //Validé
		$this->save($ATMdb);
		
		foreach($commande->lines as $line){
			//Cahrge les équipements lié à la ligne de commande
			$this->loadLines($ATMdb, $line->rowid);
			
			//Retirer une sortie de stock pour chaque equipement lié à l'expédition de la commande
			foreach($this->lines as $lineAsset){
				$asset = new TAsset;
				$asset->load(&$ATMdb,$lineAsset->fk_asset);
				$asset->contenancereel_value = $asset->contenancereel_value + $lineAsset->weight_reel;
				$asset->save(&$ATMdb,"Annulation Expedition");
			}
		}
	}
	
	
	function delete($ATMdb){
		$ATMdb->Execute("SELECT rowid FROM ".MAIN_DB_PREFIX."dispatchdet WHERE fk_dispatch = ".$this->rowid);
		$ATMdb2 = new Tdb;
		//Suppression des assiociations dispatch => asset
		while($ATMdb->Get_line()){
			$ATMdb2->Execute("DELETE FROM ".MAIN_DB_PREFIX."dispatchdet_asset WHERE fk_dispatchdet = ".$ATMdb->Get_field('rowid'));
		}
		$ATMdb2->close();
		$ATMdb->Execute("DELETE FROM ".MAIN_DB_PREFIX."dispatchdet WHERE fk_dispatch = ".$this->rowid);
		
		parent::delete($ATMdb);
	}
	
	function get_qte_expedie($ATMdb,$fk_commandedet){
		$sql = "SELECT SUM(dda.weight) as total
				FROM ".MAIN_DB_PREFIX."dispatchdet_asset as dda
					LEFT JOIN ".MAIN_DB_PREFIX."dispatchdet as dd ON dd.rowid = dda.fk_dispatchdet
				WHERE dd.fk_commandedet = ".$fk_commandedet;
		
		$ATMdb->Execute($sql);
		$ATMdb->Get_line();
		
		return $ATMdb->Get_field('total');
	}
	
	/**
	 *	Return next value
	 *
	 *	@param	Societe		$objsoc     Third party object
	 *	@param	Object		$shipment	Shipment object
	 *	@return string      			Value if OK, 0 if KO
	 */
	function getNextValue()
	{
		global $db,$conf;

		$posindice=8;
		$sql = "SELECT MAX(SUBSTRING(ref FROM ".$posindice.")) as max";
		$sql.= " FROM ".MAIN_DB_PREFIX."dispatch";
		$sql.= " WHERE ref like '".$this->prefix."____-%'";

		$resql=$db->query($sql);
		if ($resql)
		{
			$obj = $db->fetch_object($resql);
			if ($obj) $max = intval($obj->max);
			else $max=0;
		}

		$date=time();
		$yymm = strftime("%y%m",$date);
		$num = sprintf("%04s",$max+1);

		dol_syslog("mod_expedition_safor::getNextValue return ".$this->prefix.$yymm."-".$num);
		return $this->prefix.$yymm."-".$num;
	}
}

class TDispatchdet extends TObjetStd {
	function __construct() { /* declaration */
		global $langs;
		
		parent::set_table(MAIN_DB_PREFIX.'dispatchdet');
		parent::add_champs('fk_dispatch','type=entier;index;');
		parent::add_champs('fk_commandedet','type=entier;');
				
		parent::_init_vars();
		parent::start();
	}
}

class TDispatchdet_asset extends TObjetStd {
	function __construct() { /* declaration */
		global $langs;
		
		parent::set_table(MAIN_DB_PREFIX.'dispatchdet_asset');
		parent::add_champs('fk_dispatchdet','type=entier;index;');
		parent::add_champs('fk_asset, rang','type=entier;');
		parent::add_champs('weight, weight_reel, tare','type=float;');
		parent::add_champs('weight_unit, weight_reel_unit, tare_unit','type=entier;');
				
		parent::_init_vars();
		parent::start();
	}
}
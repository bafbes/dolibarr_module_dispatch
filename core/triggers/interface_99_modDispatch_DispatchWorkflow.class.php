<?php
/* Copyright (C) 2005-2011 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2011 Regis Houssin        <regis.houssin@capnetworks.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 *  \file       htdocs/core/triggers/interface_90_all_Demo.class.php
 *  \ingroup    core
 *  \brief      Fichier de demo de personalisation des actions du workflow
 *  \remarks    Son propre fichier d'actions peut etre cree par recopie de celui-ci:
 *              - Le nom du fichier doit etre: interface_99_modMymodule_Mytrigger.class.php
 *				                           ou: interface_99_all_Mytrigger.class.php
 *              - Le fichier doit rester stocke dans core/triggers
 *              - Le nom de la classe doit etre InterfaceMytrigger
 *              - Le nom de la methode constructeur doit etre InterfaceMytrigger
 *              - Le nom de la propriete name doit etre Mytrigger
 */


/**
 *  Class of triggers for Mantis module
 */
 
class InterfaceDispatchWorkflow
{
    var $db;
    
    /**
     *   Constructor
     *
     *   @param		DoliDB		$db      Database handler
     */
    function __construct($db)
    {
        $this->db = $db;
    
        $this->name = preg_replace('/^Interface/i','',get_class($this));
        $this->family = "ATM";
        $this->description = "Trigger du module expédtion spécifique Latoxan";
        $this->version = 'dolibarr';            // 'development', 'experimental', 'dolibarr' or version
        $this->picto = 'technic';
    }
    
    
    /**
     *   Return name of trigger file
     *
     *   @return     string      Name of trigger file
     */
    function getName()
    {
        return $this->name;
    }
    
    /**
     *   Return description of trigger file
     *
     *   @return     string      Description of trigger file
     */
    function getDesc()
    {
        return $this->description;
    }

    /**
     *   Return version of trigger file
     *
     *   @return     string      Version of trigger file
     */
    function getVersion()
    {
        global $langs;
        $langs->load("admin");

        if ($this->version == 'development') return $langs->trans("Development");
        elseif ($this->version == 'experimental') return $langs->trans("Experimental");
        elseif ($this->version == 'dolibarr') return DOL_VERSION;
        elseif ($this->version) return $this->version;
        else return $langs->trans("Unknown");
    }

	
    /**
     *      Function called when a Dolibarrr business event is done.
     *      All functions "run_trigger" are triggered if file is inside directory htdocs/core/triggers
     *
     *      @param	string		$action		Event action code
     *      @param  Object		$object     Object
     *      @param  User		$user       Object user
     *      @param  Translate	$langs      Object langs
     *      @param  conf		$conf       Object conf
     *      @return int         			<0 if KO, 0 if no triggered ran, >0 if OK
     */
	function run_trigger($action,$object,$user,$langs,$conf)
	{
		if(!defined('INC_FROM_DOLIBARR')) define('INC_FROM_DOLIBARR',true);
		
		if ($action == 'SHIPPING_VALIDATE') {
			dol_include_once('/dispatch/config.php');
			dol_include_once('/dispatch/class/dispatchdetail.class.php');
			
			$PDOdb = new TPDOdb();
			
			// Pour chaque ligne de l'expédition
			foreach($object->lines as $line) {
				// Chargement de l'objet detail dispatch relié à la ligne d'expédition
				$dd = new TDispatchDetail();
				
				$TIdExpeditionDet = TRequeteCore::get_id_from_what_you_want($PDOdb, MAIN_DB_PREFIX.'expeditiondet', array('fk_expedition' => $object->id, 'fk_origin_line' => $line->fk_origin_line));
				$idExpeditionDet = $TIdExpeditionDet[0];
				
				//if(!empty($idExpeditionDet) && $dd->loadBy($PDOdb, $idExpeditionDet, 'fk_expeditiondet')) {
				if(!empty($idExpeditionDet)) {
					$dd->loadLines($PDOdb, $idExpeditionDet);
					
					// Création des mouvements de stock de flacon
					foreach($dd->lines as $detail) {
						// Création du mouvement de stock standard
						$this->create_flacon_stock_mouvement($PDOdb, $detail, $object->ref);
						$this->create_standard_stock_mouvement($line, $detail->weight_reel, $object->ref);
					}
				} else {
					$this->create_standard_stock_mouvement($line, $line->qty, $object->ref);
				}
			}
			
			dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__.". id=".$object->id);
		}

		return 0;
	}
	
	private function create_flacon_stock_mouvement(&$PDOdb, &$linedetail, $numref) {
		global $user, $langs;
		dol_include_once('/asset/class/asset.class.php');
		
		$asset = new TAsset;
		$asset->load($PDOdb,$linedetail->fk_asset);
		$asset->contenancereel_value = $asset->contenancereel_value - $linedetail->weight_reel;
		$asset->save($PDOdb, $user, $langs->trans("ShipmentValidatedInDolibarr",$numref));
	}
	
	private function create_standard_stock_mouvement(&$line, $qty, $numref) {
		global $user, $langs;
		require_once DOL_DOCUMENT_ROOT.'/product/stock/class/mouvementstock.class.php';

		$mouvS = new MouvementStock($this->db);
		// We decrement stock of product (and sub-products)
		// We use warehouse selected for each line
		$result=$mouvS->livraison($user, $line->fk_product, $line->entrepot_id, $qty, $line->subprice, $langs->trans("ShipmentValidatedInDolibarr",$numref));
		return $result;
	}
}

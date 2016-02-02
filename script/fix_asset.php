<?php
/*
 * Script créant et vérifiant que les champs requis s'ajoutent bien
 * 
 */
	set_time_limit(0);
	require('../config.php');
	dol_include_once('/dispatch/class/dispatchdetail.class.php');
	dol_include_once('/asset/class/asset.class.php');
	dol_include_once('/expedition/class/expedition.class.php');

	global $db, $user, $conf;
	
	$PDOdb=new TPDOdb;

	$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."expedition";
	
	$TIds = TRequeteCore::_get_id_by_sql($PDOdb, $sql);
	
	foreach($TIds as $expeditionid){
		
		$expedition = new Expedition($db);
		$expedition->fetch($expeditionid);
		
		echo $expedition->ref.'<br>';
		
		$expedition->fetch_lines();
		$expedition->fetchObjectLinked();
		
		// Pour chaque ligne de l'expédition
		foreach($expedition->lines as &$line) 
		{
			// Chargement de l'objet detail dispatch relié à la ligne d'expédition
			$dd = new TDispatchDetail();

			$TIdExpeditionDet = TRequeteCore::get_id_from_what_you_want($PDOdb, MAIN_DB_PREFIX.'expeditiondet', array('fk_expedition' => $expedition->id, 'fk_origin_line' => $line->fk_origin_line));
			$idExpeditionDet = $TIdExpeditionDet[0];
			
			if(!empty($idExpeditionDet)) 
			{
				$dd->loadLines($PDOdb, $idExpeditionDet);
				
				if($conf->asset->enabled)
				{
					// Création des mouvements de stock de flacon
					foreach($dd->lines as &$detail) 
					{
						// Création du mouvement de stock standard
						$poids_destocke = create_flacon_stock_mouvement($PDOdb, $detail, $expedition->ref, $expedition);
						//$this->create_standard_stock_mouvement($line, $poids_destocke, $object->ref);
						
						
					}
				}
//					exit;
			}
			/* else { // Pas de détail, on déstocke la quantité comme Dolibarr standard
				$this->create_standard_stock_mouvement($line, $line->qty, $object->ref);
			}*/
		}

        // Appel des triggers
        $interface=new Interfaces($db);
        $result=$interface->run_triggers('SHIPPING_VALIDATE',$expedition,$user,$langs,$conf);
        if ($result < 0) { $error++; echo($interface->errors); }
        // Fin appel triggers
	}

	function create_flacon_stock_mouvement(&$PDOdb, &$linedetail, $numref, &$object) {
		global $user, $langs, $conf;
		dol_include_once('/asset/class/asset.class.php');
		dol_include_once('/product/class/product.class.php');
		dol_include_once('/expedition/class/expedition.class.php');
		dol_include_once('/core/class/interfaces.class.php');
		
		$asset = new TAsset;
		$asset->load($PDOdb,$linedetail->fk_asset,false);
		
		$poids_destocke = calcule_poids_destocke($PDOdb,$linedetail);
		$poids_destocke = $poids_destocke * pow(10,$asset->contenancereel_units);
		
		$asset->contenancereel_value = $asset->contenancereel_value - $poids_destocke;
		
		
		if($conf->clinomadic->enabled)
		{
			update_asset($PDOdb, $asset, $object);
		}
		
		$asset->save($PDOdb, $user, $langs->trans("ShipmentValidatedInDolibarr",$numref),0,false,0,true);
		
		return $poids_destocke;
	}
	
	function update_asset(&$PDOdb, &$asset, &$object)
	{
		global $db, $user, $langs, $conf;
		
		$nb_year_garantie = 0;
		//$asset = new TAsset;
		//$asset->load($PDOdb, $detail->fk_asset, false);

		$prod = new Product($db);
		$prod->fetch($asset->fk_product);
		
		//Affectation du type d'équipement pour avoir accès aux extrafields équipement
		$asset->fk_asset_type = $asset->get_asset_type($PDOdb, $prod->id);
		$asset->load_asset_type($PDOdb);
		
		//Localisation client
		$asset->fk_societe_localisation = $object->socid;
		if(!empty($object->linkedObjects['commande'][0]->array_options['options_duree_pret']))
		{
			$asset->etat = 2; //Prêté
		}
		else
		{
			$asset->etat = 1; //Vendu
		}
		
		foreach($object->linkedObjects['commande'][0]->lines as &$linecommande)
		{
			if($linecommande->fk_product == $asset->fk_product)
			{
				$linecommande->fetch_optionals($linecommande->rowid);

				$fk_service = $linecommande->array_options['options_extension_garantie'];
				$extension_garantie = null;
				if ($fk_service > 0)
				{
					$extension = new Product($db);
					$extension->fetch($fk_service);
					$extension_garantie = $extension->array_options['options_duree_garantie_client'];	
				}
			}
		}
		
		$nb_year_garantie+=$prod->array_options['options_duree_garantie_client'];

		$date_valid=dol_now();
		$asset->date_fin_garantie_cli = strtotime('+'.$nb_year_garantie.'year', $date_valid);
		
		if ($extension_garantie !== null) $asset->date_fin_garantie_cli = strtotime('+'.$extension_garantie.'year', $asset->date_fin_garantie_cli);
		
		//pre($asset,true);exit;
		echo " ====> ASSET ".$asset->etat." : ".$asset->serial_number.'<br>';
		flush();
		//$asset->save($PDOdb);
	}
	
	/*private function create_standard_stock_mouvement(&$line, $qty, $numref) {
		global $user, $langs;
		require_once DOL_DOCUMENT_ROOT.'/product/stock/class/mouvementstock.class.php';

		$mouvS = new MouvementStock($this->db);
		// We decrement stock of product (and sub-products)
		// We use warehouse selected for each line
		$result=$mouvS->livraison($user, $line->fk_product, $line->entrepot_id, $qty, $line->subprice, $langs->trans("ShipmentValidatedInDolibarr",$numref));
		return $result;
	}*/
	
	function calcule_poids_destocke(&$PDOdb,&$linedetail){
			
		$sql = "SELECT p.weight, p.weight_units
				FROM ".MAIN_DB_PREFIX."product as p
					LEFT JOIN ".MAIN_DB_PREFIX."asset as a ON (a.fk_product = p.rowid)
					LEFT JOIN ".MAIN_DB_PREFIX."expeditiondet_asset as eda ON (eda.fk_asset = a.rowid)
					LEFT JOIN ".MAIN_DB_PREFIX."expeditiondet as ed ON (ed.rowid = eda.fk_expeditiondet)
				WHERE ed.rowid = ".$linedetail->fk_expeditiondet;
		
		$PDOdb->Execute($sql);
		$PDOdb->Get_line();
		$weight = $PDOdb->Get_field('weight');
		$poids = (!empty($weight)) ? $weight : 1 ;
		$weight_units = $PDOdb->Get_field('weight_units');
		$poids_unite = (!empty($weight_units)) ? $weight_units : $linedetail->weight_reel_unit ;
		$poids = $poids * pow(10,$poids_unite);
		$weight_reel = $linedetail->weight_reel * pow(10,$linedetail->weight_reel_unit );
		
		return $weight_reel / $poids;
	} 
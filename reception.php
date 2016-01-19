<?php

	require('config.php');

	dol_include_once('/fourn/class/fournisseur.commande.class.php' );
	dol_include_once('/dispatch/class/dispatchdetail.class.php' );
	dol_include_once('/product/class/html.formproduct.class.php' );
	dol_include_once('/product/stock/class/entrepot.class.php' );
	dol_include_once('/core/lib/product.lib.php' );
	dol_include_once('/core/lib/fourn.lib.php' );
	dol_include_once('/asset/class/asset.class.php');
	
	global $langs, $user, $conf;
	
	$PDOdb = new TPDOdb;
	
	$langs->load('companies');
	$langs->load('suppliers');
	$langs->load('products');
	$langs->load('bills');
	$langs->load('orders');
	$langs->load('commercial');
	
	$id = GETPOST('id');

	$commandefourn = new CommandeFournisseur($db);
	$commandefourn->fetch($id);
	
	$action = GETPOST('action');
	$TImport = _loadDetail($PDOdb,$commandefourn);
	
	function _loadDetail(&$PDOdb,&$commandefourn){
		
		$TImport = array();

		foreach($commandefourn->lines as $line){
		
			$sql = "SELECT ca.rowid as idline,ca.serial_number,p.ref,p.rowid, ca.fk_commandedet, ca.imei, ca.firmware,ca.lot_number,ca.weight_reel,ca.weight_reel_unit, ca.dluo
					FROM ".MAIN_DB_PREFIX."commande_fournisseurdet_asset as ca
						LEFT JOIN ".MAIN_DB_PREFIX."product as p ON (p.rowid = ca.fk_product)
					WHERE ca.fk_commandedet = ".$line->id."
						ORDER BY ca.rang ASC";

			$PDOdb->Execute($sql);
			
			while ($PDOdb->Get_line()) {
				$TImport[] =array(
					'ref'=>$PDOdb->Get_field('ref')
					,'numserie'=>$PDOdb->Get_field('serial_number')
					,'lot_number'=>$PDOdb->Get_field('lot_number')
					,'quantity'=>$PDOdb->Get_field('weight_reel')
					,'quantity_unit'=>$PDOdb->Get_field('weight_reel_unit')
					,'imei'=>$PDOdb->Get_field('imei')
					,'firmware'=>$PDOdb->Get_field('firmware')
					,'fk_product'=>$PDOdb->Get_field('rowid')
					,'dluo'=>$PDOdb->Get_field('dluo')
					,'commande_fournisseurdet_asset'=>$PDOdb->Get_field('idline')
				);
			}
		}
		
		return $TImport;
	}
	
	function _addCommandedetLine(&$PDOdb,&$TImport,&$commandefourn,$refproduit,$numserie,$imei,$firmware,$lot_number,$quantity,$quantity_unit,$dluo,$k=null){
		global $db, $conf;
		
		//Charge le produit associé à l'équipement
		$prodAsset = new Product($db);
		$prodAsset->fetch('',$refproduit);
		
		//Récupération de l'indentifiant de la ligne d'expédition concerné par le produit
		foreach($commandefourn->lines as $commandeline){
			if($commandeline->fk_product == $prodAsset->id){
				$fk_line = $commandeline->id;
			}
		}
		
		if (empty($_POST['TLine'][$k]) === false) {
			if ($numserie != $_POST['TLine'][$k]['numserie']) {
				$line_update = true;
			}
		}
		
		//Sauvegarde (ajout/MAJ) des lignes de détail d'expédition
		$recepdetail = new TRecepDetail;
		
		//pre($TImport,true);
		
		//Si déjà existant => MAj
		$PDOdb->Execute("SELECT rowid FROM ".MAIN_DB_PREFIX."commande_fournisseurdet_asset 
						WHERE fk_product = ".$prodAsset->id." AND serial_number = ".$PDOdb->quote($numserie)." AND fk_commandedet = ".$fk_line." AND rowid = ".$_POST['TLine'][$k]['commande_fournisseurdet_asset']);
		
		$lineFound = false;
		if($PDOdb->Get_line() || $line_update){
			$rowid = ($line_exists ? $_POST['TLine'][$k]['commande_fournisseurdet_asset'] : $PDOdb->Get_field('rowid'));
			$recepdetail->load($PDOdb, $rowid);
			
			$lineFound = true;
		}
		
		$keys = array_keys($TImport);
		$rang = $keys[count($keys)-1];
		
		$recepdetail->fk_commandedet = $fk_line;
		$recepdetail->fk_product = $prodAsset->id;
		$recepdetail->rang = $rang;
		$recepdetail->set_date('dluo', ($dluo) ? $dluo : date('Y-m-d H:i:s'));
		$recepdetail->lot_number = $lot_number;
		$recepdetail->weight_reel = $quantity;
		$recepdetail->weight = $quantity;
		$recepdetail->weight_unit = $quantity_unit;
		$recepdetail->weight_reel_unit = $quantity_unit;
		$recepdetail->serial_number = $numserie;
		$recepdetail->imei = $imei;
		$recepdetail->firmware = $firmware;
		/*$recepdetail->weight = 1;
		$recepdetail->weight_reel = 1;
		$recepdetail->weight_unit = 0;
		$recepdetail->weight_reel_unit = 0;*/

		$recepdetail->save($PDOdb);
		
		//Rempli le tableau utilisé pour l'affichage des lignes		
		if ($lineFound)
		{
			$TImport[$k] =array(
				'ref'=>$prodAsset->ref
				,'numserie'=>$numserie
				,'lot_number'=>$lot_number
				,'quantity'=>$quantity
				,'quantity_unit'=>$quantity_unit
				,'fk_product'=>$prodAsset->id
				,'imei'=>$imei
				,'firmware'=>$firmware
				,'dluo'=>$recepdetail->get_date('dluo','Y-m-d H:i:s')
				,'commande_fournisseurdet_asset'=>$recepdetail->getId()
			);
		}
		else
		{
			$TImport[] =array(
				'ref'=>$prodAsset->ref
				,'numserie'=>$numserie
				,'lot_number'=>$lot_number
				,'quantity'=>$quantity
				,'quantity_unit'=>$quantity_unit
				,'fk_product'=>$prodAsset->id
				,'imei'=>$imei
				,'firmware'=>$firmware
				,'dluo'=>$recepdetail->get_date('dluo','Y-m-d H:i:s')
				,'commande_fournisseurdet_asset'=>$recepdetail->getId()
			);
		}
		
		
		return $TImport;

	}
	
	if(isset($_FILES['file1']) && $_FILES['file1']['name']!='') {
		$f1  =file($_FILES['file1']['tmp_name']);
		
		foreach($f1 as $line) {
			if(!(ctype_space($line))) {
				list($ref, $numserie, $imei, $firmware, $lot_number)=str_getcsv($line,';','"');
				$TImport = _addCommandedetLine($PDOdb,$TImport,$commandefourn,$ref,$numserie,$imei,$firmware,$lot_number,$quantity,$quantity_unit,$dluo);
			}
		}
		
	}
	else if($action=='DELETE_LINE') {
		$k = (int)GETPOST('k');
		unset($TImport[$k]);

		$rowid = GETPOST('rowid');
		
		$recepdetail = new TRecepDetail;
		$recepdetail->load($PDOdb, $rowid);
		$recepdetail->delete($PDOdb);
		
		$TImport = _loadDetail($PDOdb,$commandefourn);
		
		setEventMessage('Ligne supprimée');

	}
	elseif(isset($_POST['bt_save'])) {
		
		foreach($_POST['TLine']  as $k=>$line) {
			unset($TImport[(int)$k]);

			// Modification
			if (empty($line['fk_product']) === false) {
				$fk_product = $line['fk_product'];
			} else if (empty($_POST['new_line_fk_product']) === false) { // Ajout
				$fk_product = $_POST['new_line_fk_product'];
			} 

			// Si aucun produit renseigné mais numéro de série renseigné
			if ($k == -1 && $fk_product == -1 && empty($line['numserie']) === false) {
				$error = true;
				setEventMessage('Veuillez saisir un produit.', 'errors');
			}

			// Si un produit est renseigné, on sauvegarde
			if (!$error && $fk_product > 0) {
				$product = new Product($db);
				$product->fetch($fk_product);
				
				//On vérifie que le produit est bien présent dans la commande
				$find = false;
				$quantityOrdered = 0;
				foreach ($commandefourn->lines as $key => $l) {
					if($l->fk_product == $product->id){
						$find = true; 
						$quantityOrdered += $l->qty;
					}
				}
				
				if (!$find) {
					$error = true;
					setEventMessage('Référence produit non présente dans la commande', 'errors');
				}
				
				if (empty($product->id)) {
					$error = true;
					setEventMessage('Référence produit introuvable', 'errors');
				}
				
				//pre($commandefourn,true);exit;
				if (!$error) {
					$TImport = _addCommandedetLine($PDOdb,$TImport,$commandefourn,$product->ref,$line['numserie'],$line['imei'],$line['firmware'],$line['lot_number'],($line['quantity']) ? $line['quantity'] : $quantityOrdered,$line['quantity_unit'],$line['dluo'], $k);
				}
			}
			
			$fk_product = -1; // Reset de la variable contenant la référence produit

/*
			$asset = new TAsset;
			if($asset->loadBy($PDOdb, $line['numserie'], 'serial_number')){
					
				$TImport = _addCommandedetLine($PDOdb,$TImport,$commandefourn,$line['ref'],$line['numserie'],$line['imei'],$line['firmware']);
			}
 */
		}
		
		if (!$error) {
			setEventMessage('Modifications enregistrées');
		}
	}
	elseif(isset($_POST['bt_create'])) {
		
		$PDOdb=new TPDOdb;

		$time_date_recep = Tools::get_time($_POST['date_recep']);
			
		//Tableau provisoir qui permettra la ventilation standard Dolibarr après la création des équipements
		$TProdVentil = array();

		foreach($TImport  as $k=>$line) {
			
			$asset =new TAsset;
			
			//pre($line,true);
			
			if(!$asset->loadReference($PDOdb, $line['numserie'])) {
				// si inexistant
				//Seulement si nouvelle ligne
				if($k == -1){
					_addCommandedetLine($PDOdb,$TImport,$commandefourn,$line['ref'],$line['numserie'],$line['$imei'],$line['$firmware'],$line['lot_number'],$line['quantity'],$line['quantity_unit']);
				}
				
				$prod = new Product($db);
				$prod->fetch($line['fk_product']);
				
				//Affectation du type d'équipement pour avoir accès aux extrafields équipement
				$asset->fk_asset_type = $asset->get_asset_type($PDOdb, $prod->id);
				$asset->load_asset_type($PDOdb);

				//echo $asset->getNextValue($PDOdb);
				$asset->fk_product = $line['fk_product'];
				$asset->serial_number = ($line['numserie']) ? $line['numserie'] : $asset->getNextValue($PDOdb);
				$asset->lot_number =$line['lot_number'];
				$asset->contenance_value =($line['quantity']) ? $line['quantity'] : 1;
				$asset->contenancereel_value =($line['quantity']) ? $line['quantity'] : 1 ;
				$asset->contenancereel_units =($line['quantity_unit']) ? $line['quantity_unit'] : 0;
				$asset->contenance_units =($line['quantity_unit']) ? $line['quantity_unit'] : 0;
				$asset->lot_number =$line['lot_number'];
				$asset->firmware = $line['firmware'];
				$asset->imei= $line['imei'];
				$asset->set_date('dluo', $line['dluo']);
				$asset->entity = $conf->entity;
				
				//$asset->contenancereel_value = 1;
				
				$nb_year_garantie = 0;
				
				//Renseignement des extrafields
				$asset->set_date('date_reception', $_REQUEST['date_recep']);
				
				foreach($commandefourn->lines as $l){
					if($l->fk_product == $asset->fk_product){
						$asset->prix_achat  = number_format($l->subprice,2);
						
						$extension_garantie = 0;
						$PDOdb->Execute('SELECT extension_garantie FROM '.MAIN_DB_PREFIX.'commande_fournisseurdet WHERE rowid = '.$l->id);
						if($PDOdb->Get_line()){
							$extension_garantie = $PDOdb->Get_field('extension_garantie');
						}
						
					}
				}
				
				$nb_year_garantie+=$prod->array_options['options_duree_garantie_fournisseur'];
				
				$asset->date_fin_garantie_fourn = strtotime('+'.$nb_year_garantie.'year', $time_date_recep);
				$asset->date_fin_garantie_fourn = strtotime('+'.$extension_garantie.'year', $asset->date_fin_garantie_fourn);
				$asset->fk_soc = $commandefourn->socid;
				$asset->fk_entrepot = GETPOST('id_entrepot');
				
				$societe = new Societe($db);
				$societe->fetch('', $conf->global->MAIN_INFO_SOCIETE_NOM);

				$asset->fk_societe_localisation = $societe->id;
				$asset->etat = 0; //En stock
				//pre($asset,true);exit;
				// Le destockage dans Dolibarr est fait par la fonction de ventilation plus loin, donc désactivation du mouvement créé par l'équipement.
				$asset->save($PDOdb, $user, '', 0, false, 0, true,GETPOST('id_entrepot'));
				
				$TImport[$k]['numserie'] = $asset->serial_number;
				
				$stock = new TAssetStock;
				$stock->mouvement_stock($PDOdb, $user, $asset->getId(), $asset->contenancereel_value, $langs->trans("DispatchSupplierOrder",$commandefourn->ref), $commandefourn->id);
				
				if($asset->serial_number != $line['numserie']){
					$receptDetailLine = new TRecepDetail;
					$receptDetailLine->load($PDOdb, $line['commande_fournisseurdet_asset']);
					$receptDetailLine->numserie = $receptDetailLine->serial_number = $asset->serial_number;
					$receptDetailLine->save($PDOdb);
				}
				
				//Compteur pour chaque produit : 1 équipement = 1 quantité de produit ventilé
				$TProdVentil[$asset->fk_product] += ($line['quantity']) ? $line['quantity'] : 1;
			}
			
		}

		//pre($TProdVentil,true);
		
		$status = $commandefourn->fk_statut;
		
		if(count($TProdVentil)>0) {
			$status = $commandefourn->fk_statut;
			
			$totalementventile = true;

			foreach($TProdVentil as $id_prod => $qte){
				//Fonction standard ventilation commande fournisseur
				$commandefourn->DispatchProduct($user, $id_prod, $qte, GETPOST('id_entrepot'),'',$langs->trans("DispatchSupplierOrder",$commandefourn->ref));
				
				foreach($commandefourn->lines as $line){
					if($line->fk_product == $id_prod){
						if($qte < $line->qty && $totalementventile){
							$totalementventile = false;
							$status = 4;
						}
					}
				}
			}

			if($commandefourn->statut == 0){
				$commandefourn->valid($user);
			}
			
			if($totalementventile){
				$status = 5;
			}
			
			$commandefourn->setStatus($user, $status);
			$commandefourn->statut = $status;
	
			setEventMessage('Equipements créés');
			
		}
		

	}

	//if(is_array($TImport)) usort($TImport,'_by_ref');

	fiche($commandefourn, $TImport);


function _by_ref(&$a, &$b) {
	
	if($a['ref']<$b['ref']) return -1;
	else if($a['ref']>$b['ref']) return 1;
	return 0;
	
}
function fiche(&$commande, &$TImport) {
global $langs, $db, $conf;

	llxHeader();

	$head = ordersupplier_prepare_head($commande);

	$title=$langs->trans("SupplierOrder");
	dol_fiche_head($head, 'recepasset', $title, 0, 'order');
	
	entetecmd($commande);
	
	$form=new TFormCore('auto','formrecept','post', true);
	echo $form->hidden('action', 'SAVE');
	echo $form->hidden('id', $commande->id);
	
	if($commande->statut < 5 && $conf->global->DISPATCH_USE_IMPORT_FILE){
		echo $form->fichier('Fichier à importer','file1','',80);
		echo $form->btsubmit('Envoyer', 'btsend');
	}
	
	tabImport($TImport,$commande);
	
	$form->end();
	
	llxFooter();
}

function tabImport(&$TImport,&$commande) {
global $langs, $db, $conf;		
	
	$PDOdb=new TPDOdb;
	
	$form=new TFormCore;
	$formDoli =	new Form($db);
	$formproduct=new FormProduct($db);
	
	print count($TImport).' équipement(s) dans votre réception';
	
	?>
	<table width="100%" class="border">
		<tr class="liste_titre">
			<td>Produit</td>
			<td>Numéro de Série</td>
			<td>Numéro de Lot</td>
			<td>DLUO</td>
			<td>Quantité</td>
			<td>Unité</td>
			<?php
			if($conf->global->clinomadic->enabled){
				?>
				<td>IMEI</td>
				<td>Firmware</td>
				<?php
			}
			?>
			<td>&nbsp;</td>
		</tr>
		
	<?php
		if($commande->statut >= 5) $form->type_aff = "view";
		$prod = new Product($db);

		$warning_asset = false;

		if(is_array($TImport)){
			foreach ($TImport as $k=>$line) {
							
				if($prod->id==0 || $line['ref']!= $prod->ref) {
					if(empty($line['fk_product']) === false) {
						$prod->fetch($line['fk_product']);
					} else if (empty($line['ref']) === false) {
						$prod->fetch('', $line['ref']);	
					} else {
						continue;
					}
				} 		
					
				?><tr>
					<td><?php echo $prod->getNomUrl(1).$form->hidden('TLine['.$k.'][fk_product]', $prod->id).$form->hidden('TLine['.$k.'][ref]', $prod->ref) ?></td>
					<td><?php
						echo $form->texte('','TLine['.$k.'][numserie]', $line['numserie'], 30) ;
						$asset=new TAsset;
						
						if($asset->loadReference($PDOdb, $line['numserie'])) {
							echo '<a href="'.dol_buildpath('/asset/fiche.php?id='.$asset->getId(),1).'">' .img_picto('Equipement lié à cet import', 'info.png'). '</a>';
						}
						else {
							echo img_picto('Aucun équipement créé en Base', 'warning.png');
							$warning_asset = true;
						}
						echo $form->hidden('TLine['.$k.'][commande_fournisseurdet_asset]', $line['commande_fournisseurdet_asset'], 30)   
					?>
					</td>
					<td><?php echo $form->texte('','TLine['.$k.'][lot_number]', $line['lot_number'], 30);   ?></td>
					<td><?php echo $form->calendrier('','TLine['.$k.'][dluo]', date('d/m/Y',strtotime($line['dluo'])));   ?></td>
					<td><?php echo $form->texte('','TLine['.$k.'][quantity]', $line['quantity'], 10);   ?></td>
					<td><?php echo ($commande->statut < 5) ? $formproduct->select_measuring_units('TLine['.$k.'][quantity_unit]','weight',$line['quantity_unit']) : measuring_units_string($line['quantity_unit'],'weight');  ?></td>					<?php
					if($conf->global->clinomadic->enabled){
						?>
						<td><?php echo $form->texte('','TLine['.$k.'][imei]', $line['imei'], 30)   ?></td>
						<td><?php echo $form->texte('','TLine['.$k.'][firmware]', $line['firmware'], 30)   ?></td>
						<?php
					}
					?>
					<td>
						<?php 
						if($commande->statut < 5){
							echo '<a href="?action=DELETE_LINE&k='.$k.'&id='.$commande->id.'&rowid='.$line['commande_fournisseurdet_asset'].'">'.img_delete().'</a>';
						}
						?>
					</td>
				</tr>				
				<?php
				
			}
		}
		
		if($commande->statut < 5){
			
			$pListe[0] = "Sélectionnez un produit";
			foreach($commande->lines as $line){
				$pListe[$line->fk_product] = $line->product_label;
			}
			
			$defaultDLUO = '';
			if($conf->global->DISPATCH_DLUO_BY_DEFAULT){
				$defaultDLUO = date('d/m/Y',strtotime(date('Y-m-d')." ".$conf->global->DISPATCH_DLUO_BY_DEFAULT));
			}
			
			echo $defaultDLUO;
			
			?><tr style="background-color: lightblue;">
					<td><?php print $form->combo('', 'new_line_fk_product', $pListe, ''); ?></td>
					<td><?php echo $form->texte('','TLine[-1][numserie]', '', 30); ?></td>
					<td><?php echo $form->texte('','TLine[-1][lot_number]', '', 30);   ?></td>
					<td><?php echo $form->calendrier('','TLine[-1][dluo]',$defaultDLUO);  ?></td>
					<td><?php echo $form->texte('','TLine[-1][quantity]', '', 10);   ?></td>
					<td><?php echo $formproduct->select_measuring_units('TLine[-1][quantity_unit]','weight');   ?></td>
					<?php
					if($conf->global->clinomadic->enabled){
						?>
						<td><?php echo $form->texte('','TLine[-1][imei]', '', 30);   ?></td>
						<td><?php echo $form->texte('','TLine[-1][firmware]', '', 30);   ?></td>
						<?php
					}
					?>
					<td>Nouveau
					</td>
				</tr>
			<?php
		}	
		?>
			
		
	</table>
	<?php
	if($commande->statut < 5 || $warning_asset){
			
		if($commande->statut < 5 ) echo $form->btsubmit('Enregistrer', 'bt_save');
			
			
		$form->type_aff = 'edit';	
		?>
		<hr />
		<?php
		echo $form->calendrier('Date de réception', 'date_recep', time());
		
		$entrepot = new Entrepot($db);
		$entrepot->fetch('','Neuf');
		
		print " <b>Entrepôt</b> ".$formproduct->selectWarehouses($entrepot->id,'id_entrepot','',1);
		
		echo $form->btsubmit('Créer les équipements', 'bt_create');
	}
	
}

function entetecmd(&$commande) {
global $langs, $db;

			
		$form =	new Form($db);
		
		$soc = new Societe($db);
		$soc->fetch($commande->socid);

		$author = new User($db);
		$author->fetch($commande->user_author_id);
		
		/*
		 *	Commande
		 */
		print '<table class="border" width="100%">';

		// Ref
		print '<tr><td width="20%">'.$langs->trans("Ref").'</td>';
		print '<td colspan="2">';
		print $form->showrefnav($commande,'ref','',1,'ref','ref');
		print '</td>';
		print '</tr>';

		// Fournisseur
		print '<tr><td>'.$langs->trans("Supplier")."</td>";
		print '<td colspan="2">'.$soc->getNomUrl(1,'supplier').'</td>';
		print '</tr>';

		// Statut
		print '<tr>';
		print '<td>'.$langs->trans("Status").'</td>';
		print '<td colspan="2">';
		print $commande->getLibStatut(4);
		print "</td></tr>";

		// Date
		if ($commande->methode_commande_id > 0)
		{
			print '<tr><td>'.$langs->trans("Date").'</td><td colspan="2">';
			if ($commande->date_commande)
			{
				print dol_print_date($commande->date_commande,"dayhourtext")."\n";
			}
			print "</td></tr>";

			if ($commande->methode_commande)
			{
				print '<tr><td>'.$langs->trans("Method").'</td><td colspan="2">'.$commande->methode_commande.'</td></tr>';
			}
		}

		// Auteur
		print '<tr><td>'.$langs->trans("AuthorRequest").'</td>';
		print '<td colspan="2">'.$author->getNomUrl(1).'</td>';
		print '</tr>';

		print "</table>";

		//if ($mesg) print $mesg;
		print '<br>';
	
	
}

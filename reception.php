<?php

	require('config.php');

	dol_include_once('/fourn/class/fournisseur.commande.class.php' );
	dol_include_once('/dispatch/class/dispatchdetail.class.php' );
	dol_include_once('/product/class/html.formproduct.class.php' );
	dol_include_once('/product/stock/class/entrepot.class.php' );
	dol_include_once('/core/lib/fourn.lib.php' );
	dol_include_once('/asset/class/asset.class.php');
	
	global $langs, $user;
	$langs->load('orders');
	
	$PDOdb = new TPDOdb;
	
	$id = GETPOST('id');

	$commandefourn = new CommandeFournisseur($db);
	$commandefourn->fetch($id);
	
	$action = GETPOST('action');
	$TImport = _loadDetail($PDOdb,$commandefourn);
	
	function _loadDetail(&$PDOdb,&$commandefourn){
		
		$TImport = array();
		
		foreach($commandefourn->lines as $line){
		
			$sql = "SELECT ca.rowid as idline,ca.serial_number,p.ref,p.rowid, ca.fk_commandedet, ca.imei, ca.firmware
					FROM ".MAIN_DB_PREFIX."commande_fournisseurdet_asset as ca
						LEFT JOIN ".MAIN_DB_PREFIX."product as p ON (p.rowid = ca.fk_product)
					WHERE ca.fk_commandedet = ".$line->id."
						ORDER BY ca.rang ASC";
			
			$PDOdb->Execute($sql);

			while ($PDOdb->Get_line()) {
				$TImport[] =array(
					'ref'=>$PDOdb->Get_field('ref')
					,'numserie'=>$PDOdb->Get_field('serial_number')
					,'imei'=>$PDOdb->Get_field('imei')
					,'firmware'=>$PDOdb->Get_field('firmware')
					,'fk_product'=>$PDOdb->Get_field('rowid')
					,'commande_fournisseurdet_asset'=>$PDOdb->Get_field('idline')
				);
			}
		}
		
		return $TImport;
	}
	
	function _addCommandedetLine(&$PDOdb,&$TImport,&$commandefourn,$refproduit,$numserie,$imei,$firmware){
		global $db;
			
		//Charge le produit associé à l'équipement
		$prodAsset = new Product($db);
		$prodAsset->fetch('',$refproduit);
		
		//Rempli le tableau utilisé pour l'affichage des lignes
		$TImport[] =array(
			'ref'=>$prodAsset->ref
			,'numserie'=>$numserie
			,'fk_product'=>$prodAsset->id
			,'imei'=>$imei
			,'firmware'=>$firmware
		);
		
		//Récupération de l'indentifiant de la ligne d'expédition concerné par le produit
		foreach($commandefourn->lines as $commandeline){
			if($commandeline->fk_product == $prodAsset->id){
				$fk_line = $commandeline->id;
			}
		}
		
		//Sauvegarde (ajout/MAJ) des lignes de détail d'expédition
		$recepdetail = new TRecepDetail;
		
		//Si déjà existant => MAj
		$PDOdb->Execute("SELECT rowid FROM ".MAIN_DB_PREFIX."commande_fournisseurdet_asset 
						WHERE fk_product = ".$prodAsset->id." AND serial_number = ".$numserie." AND fk_commandedet = ".$fk_line);
		if($PDOdb->Get_line()){
			$recepdetail->load($PDOdb,$PDOdb->Get_field('rowid'));
		}

		$keys = array_keys($TImport);
		$rang = $keys[count($keys)-1];

		$recepdetail->fk_commandedet = $fk_line;
		$recepdetail->fk_product = $prodAsset->id;
		$recepdetail->rang = $rang;
		$recepdetail->serial_number = $numserie;
		$recepdetail->imei = $imei;
		$recepdetail->firmware = $firmware;
		$recepdetail->weight = 1;
		$recepdetail->weight_reel = 1;
		$recepdetail->weight_unit = 0;
		$recepdetail->weight_reel_unit = 0;

		$recepdetail->save($PDOdb);
		
		return $TImport;

	}
	
	if(isset($_FILES['file1']) && $_FILES['file1']['name']!='') {
		$f1  =file($_FILES['file1']['tmp_name']);
		
		$TImport = array();
		
		foreach($f1 as $line) {
			if(!(ctype_space($line))) {
				list($ref, $numserie, $imei, $firmware)=str_getcsv($line,';','"');
				$TImport = _addCommandedetLine($PDOdb,$TImport,$commandefourn,$ref,$numserie,$imei,$firmware);
			}
		}
		
	}
	else if($action=='DELETE_LINE') {
		unset($TImport[(int)GETPOST('k')]);

		$rowid = GETPOST('rowid');

		$recepdetail = new TRecepDetail;
		$recepdetail->load($PDOdb, $rowid);
		$recepdetail->delete($PDOdb);
		
		setEventMessage('Ligne supprimée');

	}
	elseif(isset($_POST['bt_save'])) {
		
		foreach($_POST['TLine']  as $k=>$line) {
			unset($TImport[(int)$k]);
			$asset = new TAsset;
			if($asset->loadBy($PDOdb, $line['numserie'], 'serial_number')){
					
				$TImport = _addCommandedetLine($PDOdb,$TImport,$commandefourn,$line['ref'],$line['numserie'],$line['imei'],$line['firmware']);
			}

		}

		setEventMessage('Modifications enregistrées');
	}
	elseif(isset($_POST['bt_create'])) {
		
		$PDOdb=new TPDOdb;

		$time_date_recep = Tools::get_time($_POST['date_recep']);
			
		//Tableau provisoir qui permettra la ventilation standard Dolibarr après la création des équipements
		$TProdVentil = array();

		foreach($TImport  as $k=>$line) {
			
			$asset =new TAsset;
			
			if(!$asset->loadReference($PDOdb, $line['numserie'])) {
				// si inexistant
				//Seulement si nouvelle ligne
				if($k == -1){
					_addCommandedetLine($PDOdb,$TImport,$commandefourn,$line['ref'],$line['numserie'],$line['$imei'],$line['$firmware']);
				}
				
				$asset->fk_product = $line['fk_product'];
				$asset->serial_number =$line['numserie'];
				$asset->firmware = $line['firmware'];
				$asset->imei= $line['imei'];
				
				$asset->contenancereel_value = 1;
				
				$nb_year_garantie = 0;
				
				$prod = new Product($db);
				$prod->fetch($asset->fk_product);

				//Affectation du type d'équipement pour avoir accès aux extrafields équipement
				$asset->fk_asset_type = $asset->get_asset_type($PDOdb, $prod->id);
				$asset->load_asset_type($PDOdb);
				
				//Renseignement des extrafields
				$asset->set_date('date_reception', $_REQUEST['date_recep']);
				
				foreach($commandefourn->lines as $line){
					if($line->fk_product == $asset->fk_product){
						$asset->prix_achat  = number_format($line->subprice,2);
						
						$extension_garantie = 0;
						$PDOdb->Execute('SELECT extension_garantie FROM '.MAIN_DB_PREFIX.'commande_fournisseurdet WHERE rowid = '.$line->id);
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
				$societe->fetch('','NOMADIC SOLUTIONS');
				
				$asset->fk_societe_localisation = $societe->id;
				$asset->etat = 0; //En stock
				
				$asset->save($PDOdb);
				
				//Compteur pour chaque produit : 1 équipement = 1 quantité de produit ventilé
				$TProdVentil[$asset->fk_product] += 1;
			}
			
		}

		//pre($commandefourn,true);exit;
		
		$status = $commandefourn->fk_statut;
		
		if(count($TProdVentil)>0) {
			$status = $commandefourn->fk_statut;
			
			$totalementventile = true;
			
			foreach($TProdVentil as $id_prod => $qte){
				//Fonction standard ventilation commande fournisseur
				
				foreach($commandefourn->lines as $line){
					if($line->fk_product = $id_prod){
						if($qte < $line->qty && $totalementventile){
							$totalementventile = false;
							$status = 4;
						}
					}
				}
				
				$commandefourn->DispatchProduct($user, $id_prod, $qte, GETPOST('id_entrepot'),'',$langs->trans("DispatchSupplierOrder",$commandefourn->ref));
			}
			
			if($commandefourn->fk_statut == 0)
				$commandefourn->valid($user);
			
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
global $langs, $db;

	llxHeader();

	$head = ordersupplier_prepare_head($commande);

	$title=$langs->trans("SupplierOrder");
	dol_fiche_head($head, 'recepasset', $title, 0, 'order');
	
	entetecmd($commande);
	
	$form=new TFormCore('auto','formrecept','post', true);
	echo $form->hidden('action', 'SAVE');
	echo $form->hidden('id', $commande->id);
	
	if($commande->statut < 5){
		echo $form->fichier('Fichier à importer','file1','',80);
		echo $form->btsubmit('Envoyer', 'btsend');
	}
	
	tabImport($TImport,$commande);
	
	$form->end();
	
	llxFooter();
}

function tabImport(&$TImport,&$commande) {
global $langs, $db;		
	
	$PDOdb=new TPDOdb;
	
	$form=new TFormCore;
	$formDoli =	new Form($db);
	$formproduct=new FormProduct($db);
	
	print count($TImport).' équipement(s) dans votre réception';
	
	?>
	<table width="100%" class="border">
		<tr class="liste_titre">
			<td>Produit</td>
			<td>Numéro de série</td>
			<td>IMEI</td>
			<td>Firmware</td>
			<td>&nbsp;</td>
		</tr>
		
	<?
		if($commande->statut >= 5) $form->type_aff = "view";
		$prod = new Product($db);

		$warning_asset = false;

		if(is_array($TImport)){
			foreach ($TImport as $k=>$line) {
							
				if($prod->id==0 || $line['ref']!= $prod->ref) {
					if(!empty( $line['fk_product']))$prod->fetch($line['fk_product']);
					else $prod->fetch('', $line['ref']);
				} 		
					
				?><tr>
					<td><?php echo $prod->getNomUrl(1).$form->hidden('TLine['.$k.'][fk_product]', $prod->id).$form->hidden('TLine['.$k.'][ref]', $prod->ref) ?></td>
					<td><?php 
						echo $form->texte('','TLine['.$k.'][numserie]', $line['numserie'], 30) ;
						$asset=new TAsset;
						if($commande->statut >= 5 && $asset->loadReference($PDOdb, $line['numserie'])) {
							echo '<a href="'.dol_buildpath('/asset/fiche.php?id='.$asset->getId(),1).'">' .img_picto('Equipement lié à cet import', 'info.png'). '</a>';
						}
						else{
							echo img_picto('Aucun équipement créé en Base', 'warning.png');
							$warning_asset = true;
						}
						echo $form->hidden('TLine['.$k.'][commande_fournisseurdet_asset]', $line['commande_fournisseurdet_asset'], 30)   
					?>
					</td>
					<td><?php echo $form->texte('','TLine['.$k.'][imei]', $line['imei'], 30)   ?></td>
					<td><?php echo $form->texte('','TLine['.$k.'][firmware]', $line['firmware'], 30)   ?></td>
					<td>
						<?php 
						if($commande->statut < 5){
							echo '<a href="?action=DELETE_LINE&k='.$k.'&id='.$commande->id.'&rowid='.$line['commande_fournisseurdet_asset'].'">'.img_delete().'</a>';
						}
						?>
					</td>
				</tr>
				
				<?
				
			}
		}
		
		if($commande->statut < 5){
			?><tr style="background-color: lightblue;">
					<td><?php $formDoli->select_produits(-1, 'TLine[-1][fk_product]') ?></td>
					<td><?php echo $form->texte('','TLine[-1][numserie]', '', 30)   ?></td>
					<td><?php echo $form->texte('','TLine[-1][imei]', '', 30)   ?></td>
					<td><?php echo $form->texte('','TLine[-1][firmware]', '', 30)   ?></td>
					<td>Nouveau
					</td>
				</tr>
			<?php
		}	
		?>
			
		
	</table>
	<?
	if($commande->statut < 5 || $warning_asset){
			
		if($commande->statut < 5 ) echo $form->btsubmit('Enregistrer', 'bt_save');
			
			
		$form->type_aff = 'edit';	
		?>
		<hr />
		<?
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

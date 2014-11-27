<?php

	require('config.php');

	dol_include_once('/fourn/class/fournisseur.commande.class.php' );
	dol_include_once('/core/lib/fourn.lib.php' );
	
	global $langs;
	$langs->load('orders');
	
	$id = GETPOST('id');

	$commandefourn = new CommandeFournisseur($db);
	$commandefourn->fetch($id);

	$action = GETPOST('action');
	$TImport = &$_SESSION['import_recept'];
	if(isset($_FILES['file1']) && $_FILES['file1']['name']!='') {
		$f1  =file($_FILES['file1']['tmp_name']);
		
		$TImport = array();
		
		foreach($f1 as $line) {

			list($ref, $numserie, $imei, $firmware)=str_getcsv($line,';','"');
			if($numserie!='') {
				$TImport[] =array(
					'ref'=>$ref
					,'numserie'=>$numserie
					,'imei'=>$imei
					,'firmware'=>$firmware
					,'fk_product'=>0
				);
				
			}
		}
		
	}
	else if($action=='DELETE_LINE') {
		unset($TImport[(int)GETPOST('k')]);
	
		setEventMessage('Ligne supprimée');
		
	}
	elseif(isset($_POST['bt_save'])) {
		
		//var_dump($_POST['TLine']);
		foreach($_POST['TLine']  as $k=>$line) {
			
			if($k==-1) {
				if($line['numserie']!='' && $line['fk_product']>0) $TImport[] = $line;
				elseif($line['fk_product']>0) setEventMessage('Nouvelle ligne incomplète', 'errors');
			} 
			else  $TImport[(int)$k] = $line;
			
		}
		setEventMessage('Modifications enregistrées');
	}
	elseif(isset($_POST['bt_create'])) {
		
		dol_include_once('/asset/class/asset.class.php');
		
		$PDOdb=new TPDOdb;
		
		$time_date_recep = Tools::get_time($_POST['date_recep']);

		foreach($TImport  as $k=>$line) {
				
			$asset =new TAsset;
			if(!$asset->loadReference($PDOdb, $line['numserie'])) {
				// si inexistant
				
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
				
				$societe = new Societe($db);
				$societe->fetch('','NOMADIC SOLUTIONS');
				
				$asset->fk_societe_localisation = $societe->id;
				$asset->etat = 0; //En stock
				
				$asset->save($PDOdb);
			}
			
		}
		
		$commandefourn->setStatus($user, 5);
		$commandefourn->statut = 5;
		
		setEventMessage('Equipements créés');

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
	
	$form=new TFormCore;
	$formDoli =	new Form($db);
	
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
		
		if(is_array($TImport)){
			foreach ($TImport as $k=>$line) {
							
				if($prod->id==0 || $line['ref']!= $prod->ref) {
					if(!empty( $line['fk_product']))$prod->fetch($line['fk_product']);
					else $prod->fetch('', $line['ref']);
				} 		
					
				?><tr>
					<td><?php echo $prod->getNomUrl(1).$form->hidden('TLine['.$k.'][fk_product]', $prod->id).$form->hidden('TLine['.$k.'][ref]', $prod->ref) ?></td>
					<td><?php echo $form->texte('','TLine['.$k.'][numserie]', $line['numserie'], 30)   ?></td>
					<td><?php echo $form->texte('','TLine['.$k.'][imei]', $line['imei'], 30)   ?></td>
					<td><?php echo $form->texte('','TLine['.$k.'][firmware]', $line['firmware'], 30)   ?></td>
					<td>
						<?php 
						if($commande->statut < 5){
							echo '<a href="?action=DELETE_LINE&k='.$k.'&id='.$commande->id.'">'.img_delete().'</a>';
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
	if($commande->statut < 5){
		echo $form->btsubmit('Enregistrer', 'bt_save');
			
		?>
		<hr />
		<?
		echo $form->calendrier('Date de réception', 'date_recep', time());
		
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

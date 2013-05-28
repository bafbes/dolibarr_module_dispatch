<?php
	require('config.php');
	require('class/dispatch.class.php');
	require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
	require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
	require_once DOL_DOCUMENT_ROOT.'/custom/asset/class/asset.class.php';
	require_once(DOL_DOCUMENT_ROOT."/core/lib/order.lib.php");
	require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
	require_once DOL_DOCUMENT_ROOT.'/product/class/html.formproduct.class.php';
	
	$langs->load('orders');
	$langs->load("companies");
	$langs->load("bills");
	$langs->load('propal');
	$langs->load('deliveries');
	$langs->load('stocks');
	
	llxHeader('','Epédition de la commande','','');
	
	$socid=0;
	if (! empty($user->societe_id)) $socid=$user->societe_id;
	
	$ATMdb = new Tdb;	
	$commande = new Commande($db);
	if(isset($_GET['fk_commande']) && !empty($_GET['fk_commande']))
		$commande->fetch($_GET['fk_commande']);
	else{
		echo "BadParametreCommandeId"; exit;
	}
	
	/*
	 * RECAPITULATIF COMMANDE ET ETAT DES PRODUITS A EXPEDIER
	 * 
	 */
	
	$form = new Form($db);
	$formproduct = new FormProduct($db);
	$soc = new Societe($db);
	$soc->fetch($commande->socid);
	$product_static=new Product($db);
		
	$head=commande_prepare_head($commande, $user);
	$titre='Commande client';
	$picto='order';
	dol_fiche_head($head, 'tabExpedition1', $titre, 0, $picto);
	
	print '<table class="border" width="100%">';
	
	// Ref
	print '<tr><td width="18%">'.$langs->trans('Ref').'</td>';
	print '<td colspan="3">';
	print $form->showrefnav($commande,'ref','',1,'ref','ref');
	print '</td>';
	print '</tr>';

	// Ref commande client
	print '<tr><td>';
	print '<table class="nobordernopadding" width="100%"><tr><td nowrap>';
	print $langs->trans('RefCustomer').'</td><td align="left">';
	print '</td>';
	if ($action != 'RefCustomerOrder' && $commande->brouillon) print '<td align="right"><a href="'.$_SERVER['PHP_SELF'].'?action=RefCustomerOrder&amp;id='.$commande->id.'">'.img_edit($langs->trans('Modify')).'</a></td>';
	print '</tr></table>';
	print '</td><td colspan="3">';
	if ($user->rights->commande->creer && $action == 'RefCustomerOrder')
	{
		print '<form action="'.$_SERVER['PHP_SELF'].'?id='.$id.'" method="POST">';
		print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
		print '<input type="hidden" name="action" value="setrefcustomer">';
		print '<input type="text" class="flat" size="20" name="ref_customer" value="'.$commande->ref_client.'">';
		print ' <input type="submit" class="button" value="'.$langs->trans('Modify').'">';
		print '</form>';
	}
	else
	{
		print $commande->ref_client;
	}
	print '</td>';
	print '</tr>';

	// Third party
	print '<tr><td>'.$langs->trans('Company').'</td>';
	print '<td colspan="3">'.$soc->getNomUrl(1).'</td>';
	print '</tr>';

	// Discounts for third party
	print '<tr><td>'.$langs->trans('Discounts').'</td><td colspan="3">';
	if ($soc->remise_client) print $langs->trans("CompanyHasRelativeDiscount",$soc->remise_client);
	else print $langs->trans("CompanyHasNoRelativeDiscount");
	print '. ';
	$absolute_discount=$soc->getAvailableDiscounts('','fk_facture_source IS NULL');
	$absolute_creditnote=$soc->getAvailableDiscounts('','fk_facture_source IS NOT NULL');
	$absolute_discount=price2num($absolute_discount,'MT');
	$absolute_creditnote=price2num($absolute_creditnote,'MT');
	if ($absolute_discount)
	{
		if ($commande->statut > 0)
		{
			print $langs->trans("CompanyHasAbsoluteDiscount",price($absolute_discount),$langs->transnoentities("Currency".$conf->currency));
		}
		else
		{
			// Remise dispo de type non avoir
			$filter='fk_facture_source IS NULL';
			print '<br>';
			$form->form_remise_dispo($_SERVER["PHP_SELF"].'?id='.$commande->id,0,'remise_id',$soc->id,$absolute_discount,$filter);
		}
	}
	if ($absolute_creditnote)
	{
		print $langs->trans("CompanyHasCreditNote",price($absolute_creditnote),$langs->transnoentities("Currency".$conf->currency)).'. ';
	}
	if (! $absolute_discount && ! $absolute_creditnote) print $langs->trans("CompanyHasNoAbsoluteDiscount").'.';
	print '</td></tr>';

	// Date
	print '<tr><td>'.$langs->trans('Date').'</td>';
	print '<td colspan="2">'.dol_print_date($commande->date,'daytext').'</td>';
	print '<td width="50%">'.$langs->trans('Source').' : '.$commande->getLabelSource().'</td>';
	print '</tr>';

	// Delivery date planned
	print '<tr><td height="10">';
	print '<table class="nobordernopadding" width="100%"><tr><td>';
	print $langs->trans('DateDeliveryPlanned');
	print '</td>';

	if ($action != 'editdate_livraison') print '<td align="right"><a href="'.$_SERVER["PHP_SELF"].'?action=editdate_livraison&amp;id='.$commande->id.'">'.img_edit($langs->trans('SetDeliveryDate'),1).'</a></td>';
	print '</tr></table>';
	print '</td><td colspan="2">';
	if ($action == 'editdate_livraison')
	{
		print '<form name="setdate_livraison" action="'.$_SERVER["PHP_SELF"].'?id='.$commande->id.'" method="post">';
		print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
		print '<input type="hidden" name="action" value="setdatedelivery">';
		$form->select_date($commande->date_livraison>0?$commande->date_livraison:-1,'liv_','','','',"setdatedelivery");
		print '<input type="submit" class="button" value="'.$langs->trans('Modify').'">';
		print '</form>';
	}
	else
	{
		print dol_print_date($commande->date_livraison,'daytext');
	}
	print '</td>';
	print '<td rowspan="'.$nbrow.'" valign="top">'.$langs->trans('NotePublic').' :<br>';
	print nl2br($commande->note_public);
	print '</td>';
	print '</tr>';

	// Terms of payment
	print '<tr><td height="10">';
	print '<table class="nobordernopadding" width="100%"><tr><td>';
	print $langs->trans('PaymentConditionsShort');
	print '</td>';

	if ($action != 'editconditions' && ! empty($commande->brouillon)) print '<td align="right"><a href="'.$_SERVER["PHP_SELF"].'?action=editconditions&amp;id='.$commande->id.'">'.img_edit($langs->trans('SetConditions'),1).'</a></td>';
	print '</tr></table>';
	print '</td><td colspan="2">';
	if ($action == 'editconditions')
	{
		$form->form_conditions_reglement($_SERVER['PHP_SELF'].'?id='.$commande->id,$commande->cond_reglement_id,'cond_reglement_id');
	}
	else
	{
		$form->form_conditions_reglement($_SERVER['PHP_SELF'].'?id='.$commande->id,$commande->cond_reglement_id,'none');
	}
	print '</td></tr>';

	// Mode of payment
	print '<tr><td height="10">';
	print '<table class="nobordernopadding" width="100%"><tr><td>';
	print $langs->trans('PaymentMode');
	print '</td>';
	if ($action != 'editmode' && ! empty($commande->brouillon)) print '<td align="right"><a href="'.$_SERVER["PHP_SELF"].'?action=editmode&amp;id='.$commande->id.'">'.img_edit($langs->trans('SetMode'),1).'</a></td>';
	print '</tr></table>';
	print '</td><td colspan="2">';
	if ($action == 'editmode')
	{
		$form->form_modes_reglement($_SERVER['PHP_SELF'].'?id='.$commande->id,$commande->mode_reglement_id,'mode_reglement_id');
	}
	else
	{
		$form->form_modes_reglement($_SERVER['PHP_SELF'].'?id='.$commande->id,$commande->mode_reglement_id,'none');
	}
	print '</td></tr>';

	// Project
	if (! empty($conf->projet->enabled))
	{
		$langs->load('projects');
		print '<tr><td height="10">';
		print '<table class="nobordernopadding" width="100%"><tr><td>';
		print $langs->trans('Project');
		print '</td>';
		if ($action != 'classify') print '<td align="right"><a href="'.$_SERVER['PHP_SELF'].'?action=classify&amp;id='.$commande->id.'">'.img_edit($langs->trans('SetProject')).'</a></td>';
		print '</tr></table>';
		print '</td><td colspan="2">';
		if ($action == 'classify')
		{
			$form->form_project($_SERVER['PHP_SELF'].'?id='.$commande->id, $commande->socid, $commande->fk_project, 'projectid');
		}
		else
		{
			$form->form_project($_SERVER['PHP_SELF'].'?id='.$commande->id, $commande->socid, $commande->fk_project, 'none');
		}
		print '</td></tr>';
	}

	// Lignes de 3 colonnes

	// Total HT
	print '<tr><td>'.$langs->trans('AmountHT').'</td>';
	print '<td align="right"><b>'.price($commande->total_ht).'</b></td>';
	print '<td>'.$langs->trans('Currency'.$conf->currency).'</td></tr>';

	// Total TVA
	print '<tr><td>'.$langs->trans('AmountVAT').'</td><td align="right">'.price($commande->total_tva).'</td>';
	print '<td>'.$langs->trans('Currency'.$conf->currency).'</td></tr>';

	// Total TTC
	print '<tr><td>'.$langs->trans('AmountTTC').'</td><td align="right">'.price($commande->total_ttc).'</td>';
	print '<td>'.$langs->trans('Currency'.$conf->currency).'</td></tr>';

	// Statut
	print '<tr><td>'.$langs->trans('Status').'</td>';
	print '<td colspan="2">'.$commande->getLibStatut(4).'</td>';
	print '</tr>';

	print '</table><br>';
	print '</div>';
	
	
	/*
	 * LISTE DES EXPEDITIONS ET ACTIONS EXPEDITIONS
	 * 
	 */
	
	
	// Formulaire de création
	if(isset($_REQUEST['action']) && !empty($_REQUEST['action']) &&  $_REQUEST['action'] == "add"){
		
		/* JS permettant de cloner les lignes équipements */
		?>
		<script type="text/javascript">
			function add_line(id_line){
				i = Number($('.equipement_'+id_line+':last').attr('id').substring($('.equipement_'+id_line+':last').attr('id').length - 1));
				$('.ligne_'+id_line+':first').clone(true).insertAfter($('.ligne_'+id_line+':last'));
				$('.ligne_'+id_line+' a:last').remove();
				j = i + 1;
				$('.equipement_'+id_line+":last").attr('id','equipement_'+id_line+"_"+String(j));
				$('.equipement_'+id_line+":last").attr('name','equipement_'+id_line+"_"+String(j));
				$('.poids_'+id_line+":last").attr('id','poids_'+id_line+"_"+String(j));
				$('.poids_'+id_line+":last").attr('name','poids_'+id_line+"_"+String(j));
				$('.unitepoids_'+id_line+":last").attr('id','unitepoids_'+id_line+"_"+String(j));
				$('.unitepoids_'+id_line+":last").attr('name','unitepoids_'+id_line+"_"+String(j));
				$('.poidsreel_'+id_line+":last").attr('id','poidsreel_'+id_line+"_"+String(j));
				$('.poidsreel_'+id_line+":last").attr('name','poidsreel_'+id_line+"_"+String(j));
				$('.unitereel_'+id_line+":last").attr('id','unitereel_'+id_line+"_"+String(j));
				$('.unitereel_'+id_line+":last").attr('name','unitereel_'+id_line+"_"+String(j));
				$('.tare_'+id_line+":last").attr('id','tare_'+id_line+"_"+String(j));
				$('.tare_'+id_line+":last").attr('name','tare_'+id_line+"_"+String(j));
				$('.unitetare_'+id_line+":last").attr('id','unitetare_'+id_line+"_"+String(j));
				$('.unitetare_'+id_line+":last").attr('name','unitetare_'+id_line+"_"+String(j));
				$('#equipement_'+id_line+'_'+String(j)).after('&nbsp;<a alt="Supprimer l\'équipement" title="Supprimer l\'équipement" style="cursor:pointer;" onclick="$(this).parent().parent().remove();"><img src="img/supprimer.png" style="cursor:pointer;" /></a>');
				i = i+1;
			}
		</script>
		
		<form action="" method="POST">
			<table class="notopnoleftnoright" width="100%" border="0" style="margin-bottom: 2px;" summary="">
			<tbody><tr>
			<td class="nobordernopadding" valign="middle"><div class="titre">Nouvelle expédition</div></td>
			</tr></tbody>
			</table>
			<br>
			<table class="border" width="100%">
				<tr><td align="left" width="300px;">Référence de l'expédition</td><td><input type="text" name="ref_expe"></td></tr>
				<tr><td align="left">Date de livraison prévue</td><td><?=$form->select_date(date('Y-m-d'),'date_livraison',0,0);?></td></tr>
				<tr><td align="left">Méthode d'expédition</td><td><?=$form->selectarray("methode_dispatch",array('Enlèvement par le client','Transporteur'));?></td></tr>
				<tr><td align="left">Hauteur</td><td><input type="text" name="hauteur"> cm</td></tr>
				<tr><td align="left">Largeur</td><td><input type="text" name="largeur"> cm</td></tr>
				<tr><td align="left">Poids du colis</td><td><input type="text" name="poid_general"><select id="unitepoid_general" name="unitepoid_general"><option value="-6">mg</option><option value="-3">g</option><option value="0">kg</option></select></td></tr>
				<tr><td align="left">Entrepôt</td><td><?=$formproduct->selectWarehouses($tmpentrepot_id,'entrepot'.$indiceAsked,'',1,0,$line->fk_product);?></td></tr>
			</table>
			<br>
			<input type="hidden" name="action" value="add_expedition">
			<input type="hidden" name="id" value="<?=$commande->id; ?>">
			<table class="liste" width="100%">
				<tr class="liste_titre">
					<td>Produit</td>
					<td align="center">Lot</td>
					<td align="center">Poids</td>
					<td align="center">Qté commandée</td>
					<td align="center">Qté expédiée</td>
					<td align="center">Qté à expédier</td>
					<td align="center">Qté stock/entrepôt</td>
				</tr>
				<?php
				foreach($commande->lines as $line){
					$product = new Product($db);
					$product->fetch($line->fk_product);
					
					$ATMdb->Execute('SELECT asset_lot, poids, tarif_poids FROM '.MAIN_DB_PREFIX.'commandedet WHERE rowid = '.$line->rowid);
					$ATMdb->Get_line();
					
					//Unite de poids
					switch($ATMdb->Get_field('poids')){
						case -6:
							$unite = 'mg';
							break;
						case -3:
							$unite = 'g';
							break;
						case 0:
							$unite = 'kg';
							break;
					}
				
				/*
				 * LIGNE RECAP PRODUIT
				 */
				print '<tr class="impair" style="height:50px;">';
				print '<td>'.$product->ref." - ".$product->label.'</td>';
				print '<td align="center" >'.$ATMdb->Get_field('asset_lot').'</td>';
				print '<td align="center">'.$ATMdb->Get_field('tarif_poids')." ".$unite.'</td>';
				print '<td align="center">'.$line->qty.'</td>';
				print '<td align="center">'.(! empty($commande->expeditions[$line->rowid])?$commande->expeditions[$line->rowid]:0).'</td>';
				print '<td align="center">'.(! empty($commande->expeditions[$line->rowid])?$line->qty - $commande->expeditions[$line->rowid]:$line->qty).'</td>';
				print '<td align="center">'.$product->stock_reel.'</td>';
				print '</tr>';
				
				/*
				 * LIGNE RECAP PRODUIT
				 */
				
				?>
				<tr class="ligne_<?=$line->rowid;?>">
					<td colspan="2" align="left">
						<span style="padding-left: 25px;">Equipement lié :</span>
						<select id="equipement_<?=$line->rowid;?>_1" name="equipement_<?=$line->rowid;?>_1" class="equipement_<?=$line->rowid;?>">
						<?php
						//Chargement des équipement lié au produit
						$sql = "SELECT rowid, serial_number, lot_number, contenance_value, contenance_units
						 		 FROM ".MAIN_DB_PREFIX."asset
						 		 WHERE fk_product = ".$line->fk_product."
						 		 ORDER BY contenance_value DESC";
						$ATMdb->Execute($sql);
						
						while($ATMdb->Get_line()){
							switch($ATMdb->Get_field('contenance_units')){
								case -6:
									$unite = 'mg';
									break;
								case -3:
									$unite = 'g';
									break;
								case 0:
									$unite = 'kg';
									break;
							}
							?>
							<option value="<?=$ATMdb->Get_field('rowid'); ?>"><?=$ATMdb->Get_field('serial_number')." - Lot n° ".$ATMdb->Get_field('lot_number')." - ".$ATMdb->Get_field('contenance_value')." ".$unite; ?></option>	
							<?php	
						}
						?>
						</select>
						<a alt="Lié un équipement suplémentaire" title="Lié un équipement suplémentaire" style="cursor:pointer;" onclick="add_line(<?=$line->rowid;?>);"><img src="img/ajouter.png" style="cursor:pointer;" /></a>
					</td>
					<td colspan="2">poids : <input type="text" id="poids_<?=$line->rowid;?>_1" name="poids_<?=$line->rowid;?>_1" class="poids_<?=$line->rowid;?>" style="width: 35px;"/><select id="unitepoids_<?=$line->rowid;?>_1" name="unitepoids_<?=$line->rowid;?>_1" class="unitepoids_<?=$line->rowid;?>"><option value="-6">mg</option><option value="-3">g</option><option value="0">kg</option></select></td>
					<td colspan="2">poids réel : <input type="text" id="poidsreel_<?=$line->rowid;?>_1" name="poidsreel_<?=$line->rowid;?>_1" class="poidsreel_<?=$line->rowid;?>" style="width: 35px;"/><select id="unitereel_<?=$line->rowid;?>_1" name="unitereel_<?=$line->rowid;?>_1" class="unitereel_<?=$line->rowid;?>"><option value="-6">mg</option><option value="-3">g</option><option value="0">kg</option></select></td>
					<td colspan="2">tare : <input type="text" id="tare_<?=$line->rowid;?>_1" name="tare_<?=$line->rowid;?>_1" class="tare_<?=$line->rowid;?>" style="width: 35px;"/><select id="unitetare_<?=$line->rowid;?>_1" name="unitetare_<?=$line->rowid;?>_1" class="unitetare_<?=$line->rowid;?>"><option value="-6">mg</option><option value="-3">g</option><option value="0">kg</option></select></td>
				</tr>
				<?php
			}
			?>
			</table>
			<center><br><input type="submit" class="button" value="Enregistrer" name="save">&nbsp;
			<input type="submit" class="button" value="Annuler" name="back"></center>
		<br></form>
		<?php		
	}

	//Traitement Création et Modification
	elseif(isset($_REQUEST['action']) && !empty($_REQUEST['action']) &&  ($_REQUEST['action'] == "add_expedition" || $_REQUEST['action'] == "update_expedition")){
		
		$dispatch = new TDispatch;
		$TLigneToDispatch = $dispatch->FormParser($_POST);
		
		$dispatch->ref = $TLigneToDispatch['ref_expe'];
		$dispatch->date_livraison = $TLigneToDispatch['date_livraison'];
		$dispatch->type_expedition = $TLigneToDispatch['methode_dispatch'];
		$dispatch->height = $TLigneToDispatch['hauteur'];
		$dispatch->width = $TLigneToDispatch['largeur'];
		$dispatch->weight = $TLigneToDispatch['poid_general'];
		$dispatch->fk_entrepot = $TLigneToDispatch['entrepot'];
		$dispatch->fk_commande = $commande->id;
		$dispatch->save($ATMdb);
		$dispatch->addLines($TLigneToDispatch,$commande,&$ATMdb);
		
		/*echo '<pre>';
		print_r($TLigneToDispatch);
		echo '</pre>';*/
	}
	
	//Liste des expéditions
	else{
		
		//Traitement Suppression
		if(isset($_REQUEST['action']) && !empty($_REQUEST['action']) &&  $_REQUEST['action'] == "delete"){
			$dispatch = new TDispatch;
			$dispatch->load(&$ATMdb,$_REQUEST['fk_dispatch']);
			$dispatch->delete(&$ATMdb);
		}	
		
		print '<div class="tabsAction">
				<a class="butAction" href="?action=add&fk_commande='.$commande->id.'">Ajouter une expédition</a>
			</div><br>';
		
		?>
		<div class="titre">Liste des expéditions</div>
		<?php
		$TDispatch = array();
		
		$sql = "SELECT rowid AS 'id', ref AS 'ref', date_expedition AS 'date_expedition', date_livraison AS 'date_livraison', '' AS 'Supprimer'
				FROM ".MAIN_DB_PREFIX."dispatch
				ORDER BY date_expedition ASC";
		
		$r = new TSSRenderControler(new TDispatch);
			
		print $r->liste($ATMdb, $sql, array(
			'limit'=>array('nbLine'=>1000)
			,'title'=>array(
				'ref'=>'Référence expédition'
				,'date_expedition'=>'Date expédition'
				,'date_livraison'=>'Date livraison'
				,'Supprimer' => 'Supprimer'
			)
			,'type'=>array('date_expedition'=>'date','date_livraison'=>'date')
			,'hide'=>array(
				'id'
			)
			,'link'=>array(
				'Supprimer'=>'<a href="?fk_commande='.$commande->id.'&action=delete&fk_dispatch=@id@"><img src="img/delete.png"></a>'
			)
		));
		
		llxFooter();
	}

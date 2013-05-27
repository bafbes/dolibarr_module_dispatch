<?php
	require('config.php');
	require('class/dispatch.class.php');
	require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
	require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
	require_once DOL_DOCUMENT_ROOT.'/custom/asset/class/asset.class.php';
	require_once(DOL_DOCUMENT_ROOT."/core/lib/order.lib.php");
	require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
	
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
	
	/**
	 *  Lignes de commandes avec quantite livrees et reste a livrer
	 *  Les quantites livrees sont stockees dans $commande->expeditions[fk_product]
	 */
	print '<table class="liste" width="100%">';

	$sql = "SELECT cd.rowid, cd.fk_product, cd.product_type, cd.label, cd.description,";
	$sql.= " cd.price, cd.tva_tx, cd.subprice,";
	$sql.= " cd.qty,";
	$sql.= ' cd.date_start,';
	$sql.= ' cd.date_end,';
	$sql.= ' p.label as product_label, p.ref, p.fk_product_type, p.rowid as prodid,';
	$sql.= ' p.description as product_desc, p.fk_product_type as product_type';
	$sql.= " FROM ".MAIN_DB_PREFIX."commandedet as cd";
	$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."product as p ON cd.fk_product = p.rowid";
	$sql.= " WHERE cd.fk_commande = ".$commande->id;
	$sql.= " ORDER BY cd.rang, cd.rowid";

	//print $sql;
	dol_syslog("shipment.php sql=".$sql, LOG_DEBUG);
	$resql = $db->query($sql);
	if ($resql)
	{
		$num = $db->num_rows($resql);
		$i = 0;

		print '<tr class="liste_titre">';
		print '<td>'.$langs->trans("Description").'</td>';
		print '<td align="center">'.$langs->trans("QtyOrdered").'</td>';
		print '<td align="center">Quantité expédié</td>';
		print '<td align="center">Quantité à expédié</td>';
		if (! empty($conf->stock->enabled))
		{
			print '<td align="center">Stock entrepôt</td>';
		}
		else
		{
			print '<td>&nbsp;</td>';
		}
		print "</tr>\n";

		$var=true;
		$toBeShipped=array();
		$toBeShippedTotal=0;
		while ($i < $num)
		{
			$objp = $db->fetch_object($resql);
			$var=!$var;

			// Show product and description
			$type=$objp->product_type?$objp->product_type:$objp->fk_product_type;
			// Try to enhance type detection using date_start and date_end for free lines where type
			// was not saved.
			if (! empty($objp->date_start)) $type=1;
			if (! empty($objp->date_end)) $type=1;

			print "<tr ".$bc[$var].">";

			// Product label
			if ($objp->fk_product > 0)
			{
				// Define output language
				if (! empty($conf->global->MAIN_MULTILANGS) && ! empty($conf->global->PRODUIT_TEXTS_IN_THIRDPARTY_LANGUAGE))
				{
					$commande->fetch_thirdparty();
					$prod = new Product($db, $objp->fk_product);
					$outputlangs = $langs;
					$newlang='';
					if (empty($newlang) && ! empty($_REQUEST['lang_id'])) $newlang=$_REQUEST['lang_id'];
					if (empty($newlang)) $newlang=$commande->client->default_lang;
					if (! empty($newlang))
					{
						$outputlangs = new Translate("",$conf);
						$outputlangs->setDefaultLang($newlang);
					}

					$label = (! empty($prod->multilangs[$outputlangs->defaultlang]["label"])) ? $prod->multilangs[$outputlangs->defaultlang]["label"] : $objp->product_label;
				}
				else
					$label = (! empty($objp->label)?$objp->label:$objp->product_label);

				print '<td>';
				print '<a name="'.$objp->rowid.'"></a>'; // ancre pour retourner sur la ligne

				// Show product and description
				$product_static->type=$objp->fk_product_type;
				$product_static->id=$objp->fk_product;
				$product_static->ref=$objp->ref;
				$text=$product_static->getNomUrl(1);
				$text.= ' - '.$label;
				$description=($conf->global->PRODUIT_DESC_IN_FORM?'':dol_htmlentitiesbr($objp->description));
				print $form->textwithtooltip($text,$description,3,'','',$i);

				// Show range
				print_date_range($db->jdate($objp->date_start),$db->jdate($objp->date_end));

				// Add description in form
				if (! empty($conf->global->PRODUIT_DESC_IN_FORM))
				{
					print ($objp->description && $objp->description!=$objp->product_label)?'<br>'.dol_htmlentitiesbr($objp->description):'';
				}

				print '</td>';
			}
			else
			{
				print "<td>";
				if ($type==1) $text = img_object($langs->trans('Service'),'service');
				else $text = img_object($langs->trans('Product'),'product');

				if (! empty($objp->label)) {
					$text.= ' <strong>'.$objp->label.'</strong>';
					print $form->textwithtooltip($text,$objp->description,3,'','',$i);
				} else {
					print $text.' '.nl2br($objp->description);
				}

				// Show range
				print_date_range($db->jdate($objp->date_start),$db->jdate($objp->date_end));
				print "</td>\n";
			}

			// Qty ordered
			print '<td align="center">'.$objp->qty.'</td>';

			// Qty already shipped
			$qtyProdCom=$objp->qty;
			print '<td align="center">';
			// Nb of sending products for this line of order
			$qtyAlreadyShipped = (! empty($commande->expeditions[$objp->rowid])?$commande->expeditions[$objp->rowid]:0);
			print $qtyAlreadyShipped;
			print '</td>';

			// Qty remains to ship
			print '<td align="center">';
			if ($type == 0 || ! empty($conf->global->STOCK_SUPPORTS_SERVICES))
			{
				$toBeShipped[$objp->fk_product] = $objp->qty - $qtyAlreadyShipped;
				$toBeShippedTotal += $toBeShipped[$objp->fk_product];
				print $toBeShipped[$objp->fk_product];
			}
			else
			{
				print '0 ('.$langs->trans("Service").')';
			}
			print '</td>';

			if ($objp->fk_product > 0)
			{
				$product = new Product($db);
				$product->fetch($objp->fk_product);
			}

			if ($objp->fk_product > 0 && $type == 0 && ! empty($conf->stock->enabled))
			{
				print '<td align="center">';
				print $product->stock_reel;
				if ($product->stock_reel < $toBeShipped[$objp->fk_product])
				{
					print ' '.img_warning($langs->trans("StockTooLow"));
				}
				print '</td>';
			}
			else
			{
				print '<td>&nbsp;</td>';
			}
			print "</tr>\n";

			// Show subproducts details
			if ($objp->fk_product > 0 && ! empty($conf->global->PRODUIT_SOUSPRODUITS))
			{
				// Set tree of subproducts in product->sousprods
				$product->get_sousproduits_arbo();
				//var_dump($product->sousprods);exit;

				// Define a new tree with quantiies recalculated
				$prods_arbo = $product->get_arbo_each_prod($qtyProdCom);
				//var_dump($prods_arbo);
				if (count($prods_arbo) > 0)
				{
					foreach($prods_arbo as $key => $value)
					{
						print '<tr><td colspan="4">';

						$img='';
						if ($value['stock'] < $value['stock_alert'])
						{
							$img=img_warning($langs->trans("StockTooLow"));
						}
						print '<tr><td>&nbsp; &nbsp; &nbsp; -> <a href="'.DOL_URL_ROOT."/product/fiche.php?id=".$value['id'].'">'.$value['fullpath'].'</a> ('.$value['nb'].')</td>';
						print '<td align="center"> '.$value['nb_total'].'</td>';
						print '<td>&nbsp</td>';
						print '<td>&nbsp</td>';
						print '<td align="center">'.$value['stock'].' '.$img.'</td></tr>'."\n";

						print '</td></tr>'."\n";
					}
				}
			}

			$i++;
		}
		$db->free($resql);

		if (! $num)
		{
			print '<tr '.$bc[false].'><td colspan="5">'.$langs->trans("NoArticleOfTypeProduct").'<br>';
		}

		print "</table>";
	}
	else
	{
		dol_print_error($db);
	}

	print '</div>';
	
	
	/*
	 * LISTE DES EXPEDITIONS ET ACTIONS EXPEDITIONS
	 * 
	 */
	
	if(isset($_REQUEST['action']) && !empty($_REQUEST['action']) &&  $_REQUEST['action'] == "add"){
		?>
		<script type="text/javascript">
			function add_line(id_line){
				i = Number($('.equipement_'+id_line+':last').attr('id').substring($('.equipement_'+id_line+':last').attr('id').length - 1));
				$('.ligne_'+id_line+':first').clone(true).insertAfter($('.ligne_'+id_line+':last'));
				$('.ligne_'+id_line+' a:last').remove();
				j = i + 1;
				$('.equipement_'+id_line+":last").attr('id','equipement_'+id_line+"_"+String(j));
				$('.poids_'+id_line+":last").attr('id','poids_'+id_line+"_"+String(j));
				$('.unitepoids_'+id_line+":last").attr('id','unitepoids_'+id_line+"_"+String(j));
				$('.poidsreel_'+id_line+":last").attr('id','poidsreel_'+id_line+"_"+String(j));
				$('.unitereel_'+id_line+":last").attr('id','unitereel_'+id_line+"_"+String(j));
				$('.tare_'+id_line+":last").attr('id','tare_'+id_line+"_"+String(j));
				$('.unitetare_'+id_line+":last").attr('id','unitetare_'+id_line+"_"+String(j));
				$('#equipement_'+id_line+'_'+String(j)).after('&nbsp;<a alt="Lié un équipement suplémentaire" title="Lié un équipement suplémentaire" style="cursor:pointer;" onclick="$(this).parent().parent().remove();"><img src="img/supprimer.png" style="cursor:pointer;" /></a>');
				i = i+1;
			}
		</script>
		
		<table class="notopnoleftnoright" width="100%" border="0" style="margin-bottom: 2px;" summary="">
		<tbody><tr>
		<td class="nobordernopadding" valign="middle"><div class="titre">Nouvelle expédition</div></td>
		</tr></tbody>
		</table>
		
		<form action="" method="POST">
			<input type="hidden" name="action" value="add_conditionnement">
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
						<select id="equipement_<?=$line->rowid;?>_1" class="equipement_<?=$line->rowid;?>">
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
					<td colspan="2">poids : <input type="text" id="poids_<?=$line->rowid;?>_1" class="poids_<?=$line->rowid;?>" style="width: 35px;"/><select id="unitepoids_<?=$line->rowid;?>_1" class="unitepoids_<?=$line->rowid;?>"><option value="-6">mg</option><option value="-3">g</option><option value="0">kg</option></select></td>
					<td colspan="2">poids réel : <input type="text" id="poidsreel_<?=$line->rowid;?>_1" class="poidsreel_<?=$line->rowid;?>" style="width: 35px;"/><select id="unitereel_<?=$line->rowid;?>_1" class="unitereel_<?=$line->rowid;?>"><option value="-6">mg</option><option value="-3">g</option><option value="0">kg</option></select></td>
					<td colspan="2">tare : <input type="text" id="tare_<?=$line->rowid;?>_1" class="tare_<?=$line->rowid;?>" style="width: 35px;"/><select id="unitetare_<?=$line->rowid;?>_1" class="unitetare_<?=$line->rowid;?>"><option value="-6">mg</option><option value="-3">g</option><option value="0">kg</option></select></td>
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
	elseif(isset($_REQUEST['action']) && !empty($_REQUEST['action']) &&  $_REQUEST['action'] == "add_conditionnement"){
		
		echo '<pre>';
		print_r($_POST);
		echo '</pre>';
	}
	else{
		
		print '<div class="tabsAction">
				<a class="butAction" href="?action=add&fk_commande='.$commande->id.'">Ajouter une expédition</a>
			</div><br>';
		
		?>
		<div class="titre">Liste des expéditions</div>
		<?php
		$TDispatch = array();
		
		$sql = "SELECT rowid AS 'id', ref AS 'ref', fk_statut AS 'statut', date_expedition AS 'date_expedition', date_livraison AS 'date_livraison', '' AS 'Supprimer'
				FROM ".MAIN_DB_PREFIX."dispatch
				ORDER BY date_expedition ASC";
		
		$r = new TSSRenderControler(new TDispatch);
			
		print $r->liste($ATMdb, $sql, array(
			'limit'=>array('nbLine'=>1000)
			,'title'=>array(
				'ref'=>'Référence expédition'
				,'statut' => 'Statut'
				,'date_expedition'=>'Date expédition;'
				,'date_livraison'=>'Date livraison'
				,'Supprimer' => 'Supprimer'
			)
			,'type'=>array('date_expedition'=>'date','date_livraison'=>'date')
			,'hide'=>array(
				'id'
			)
			,'link'=>array(
				'Supprimer'=>'<a href="?fk_commande=@id@&action=delete&fk_dispatch='.$object->id.'"><img src="img/delete.png"></a>'
			)
		));
		
		llxFooter();
	}

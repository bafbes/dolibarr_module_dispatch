<?php
	require('config.php');
	require('class/dispatch.class.php');
	require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
	require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
	require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
	require_once DOL_DOCUMENT_ROOT.'/custom/dispatch/class/dispatch.class.php';
	require_once DOL_DOCUMENT_ROOT.'/custom/asset/class/asset.class.php';
	require_once(DOL_DOCUMENT_ROOT."/core/lib/order.lib.php");
	require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
	require_once DOL_DOCUMENT_ROOT.'/product/class/html.formproduct.class.php';
	require_once DOL_DOCUMENT_ROOT.'/custom/dispatch/lib/dispatch.lib.php';
	
	$langs->load('orders');
	$langs->load("companies");
	$langs->load("bills");
	$langs->load('propal');
	$langs->load('deliveries');
	$langs->load('stocks');
	
	global $db,$user,$conf;
	
	llxHeader('','Expédition de la commande','','');
	
	$socid=0;
	if (! empty($user->societe_id)) $socid=$user->societe_id;
	
	$ATMdb = new Tdb;	
	$commande = new Commande($db);
	
	if(isset($_GET['fk_commande']) && !empty($_GET['fk_commande'])){
		$commande->fetch($_GET['fk_commande']);
	}
	else{
		echo "BadParametreCommandeId"; exit;
	}
	$dispatch = new TDispatch;
	$dispatch->load($ATMdb,$_REQUEST["fk_dispatch"]);
	
	$form = new Form($db);
	$formproduct = new FormProduct($db);
	$soc = new Societe($db);
	$soc->fetch($commande->socid);
	$product_static=new Product($db);
	
	if(isset($_GET['action']) && !empty($_GET['action']) &&  ($_GET['action'] == "view" || $_GET['action'] == "delete" || $_REQUEST['action'] == "update_expedition")){
		/*
		 * RECAPITULATIF EXPEDITION
		 * 
		 */
			
		$head=dispatch_prepare_head($commande,$dispatch);
	    dol_fiche_head($head, 'shipping', $langs->trans("Sending"), 0, 'sending');
		
		print '<table class="border" width="100%">';
		
		//Réf expédition
	    print '<tr><td>Réf. expédition</td>';
	    print '<td colspan="2">';
	    print $dispatch->ref;
	    print "</td>\n";
	    print '</tr>';
		
		//Statut expédition
	    print '<tr><td>Statut</td>';
	    print '<td colspan="2">';
	    print ($dispatch->statut) ? '<b>Validé</b>' : "<b>Brouillon</b>";
	    print "</td>\n";
	    print '</tr>';
		
		//Réf commande
	    print '<tr><td>';
	    print $langs->trans("RefOrder").'</td>';
	    print '<td colspan="2">';
	    print $commande->getNomUrl(1,'commande');
	    print "</td>\n";
	    print '</tr>';
	
		// Third party
		print '<tr><td>'.$langs->trans('Company').'</td>';
		print '<td colspan="2">'.$soc->getNomUrl(1).'</td>';
		print '</tr>';
		
		// Date Expédition
		print '<tr><td>Date expédition</td>';
		print '<td colspan="3">'.dol_print_date($commande->date,'daytext').'</td>';
		print '</tr>';
		
		// Delivery date planned
		print '<tr><td>Date de livraison</td>';
		print '<td colspan="2">'.dol_print_date($dispatch->date_livraison,'daytext').'</td>';
		print '</tr>';
		
		// Méthod dispatch
		print '<tr><td>Méthode d\'expédition</td>';
		print '<td colspan="2">';
		print ($dispatch->fk_expedition_method == 0) ? "Enlèvement par le client" : "Transporteur" ;
		print '</td>';
		print '</tr>';
		
		// Hauteur
		print '<tr><td>Hauteur</td>';
		print '<td colspan="2">';
		print $dispatch->height." cm";
		print '</td>';
		print '</tr>';
		
		// Largeur
		print '<tr><td>Hauteur</td>';
		print '<td colspan="2">';
		print $dispatch->width." cm";
		print '</td>';
		print '</tr>';
		
		// Poids
		print '<tr><td>Poids du colis</td>';
		print '<td colspan="2">';
		print $dispatch->weight." ";
		switch($dispatch->weight_units){
			case 0:
				print 'kg';
				break;
			case -3:
				print 'g';
				break;
			case -6;
				print 'mg';
				break;
		}
		print '</td>';
		print '</tr>';
		
		// Num transporteur
		print '<tr><td>N° suivis transporteur</td>';
		print '<td colspan="2">';
		print $dispatch->num_transporteur;
		print '</td>';
		print '</tr>';
		
		
		print '</table><br>';
		print '</div>';
		
		?>
		<table class="liste" width="100%">
		<tr class="liste_titre">
			<td>Produit</td>
			<td align="center">Lot</td>
			<td align="center">Poids commandé</td>
			<td align="center">Qté commandée</td>
			<td align="center">Qté expédiée</td>
			<td align="center">Qté à expédier</td>
			<td align="center">Flacon</td>
			<td align="center">Poids</td>
			<td align="center">Poids Réel</td>
			<td align="center">Tare</td>
		</tr>
		<?php
		foreach($commande->lines as $line){
			$product = new Product($db);
			if($line->fk_product > 0) //Ligne de commande lié à un produit
			{
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
			
				//Récupération des quantitées déjà expédiées
				$ATMdb2 = new Tdb;
				$qte_expedie = $dispatch->get_qte_expedie(&$ATMdb2,$line->rowid);
				($qte_expedie == 0) ? $qte_expedie = 0.00 : "";
				$ATMdb2->close();
				
				$res = $dispatch->loadLines(new Tdb,$line->rowid);
				
				//Il existe au moin une ligne d'équipement associé à la ligne de commande
				if($res){
					//Si il existe au moin un équipement lié à la ligne d'expédition	
					if(count($dispatch->lines)){
						foreach($dispatch->lines as $dispatchline){
							?>
							<tr class="ligne_<?=$line->rowid;?>">
								<?php
								print '<td style="padding-left:5px; height: 30px;">'.$product->ref." - ".$product->label.'</td>';
								print '<td align="center" >'.$ATMdb->Get_field('asset_lot').'</td>';
								print '<td align="center">'.number_format($ATMdb->Get_field('tarif_poids'),2,",",'')." ".$unite.'</td>';
								print '<td align="center">'.$line->qty.'</td>';
								print '<td align="center">'.number_format($qte_expedie,2,",","").' '.$unite.'</td>';
								print '<td align="center">'.number_format((($ATMdb->Get_field('tarif_poids') * $line->qty) - $qte_expedie),2,",","")." ".$unite.'</td>';
								?>
								<input type="hidden" name="idDispatchdetAsset_<?=$line->rowid;?>_<?=$dispatchline->rang;?>" value="<?=$dispatchline->rowid;?>" />
								<td align="left">
									<span style="padding-left: 25px;">Flacon lié :</span>
									<?php
									//Chargement des équipement lié au produit
									$sql = "SELECT rowid, serial_number, lot_number, contenancereel_value, contenancereel_units
									 		 FROM ".MAIN_DB_PREFIX."asset
									 		 WHERE rowid = ".$dispatchline->fk_asset."
									 		 ORDER BY contenance_value DESC";
									$ATMdb->Execute($sql);
									
									$cpt = 0;
									while($ATMdb->Get_line()){
										switch($ATMdb->Get_field('contenancereel_units')){
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
										
										
										if($ATMdb->Get_field('contenancereel_value') > 0){
											$cpt++;
											?>
											<?=$ATMdb->Get_field('serial_number')." - Lot n° ".$ATMdb->Get_field('lot_number')." - ".number_format($ATMdb->Get_field('contenancereel_value'),2,",","")." ".$unite."<br>"; ?>
											<?php
										}	
									}
									?>
								</td>
								<td>
									poids : <?=number_format($dispatchline->weight,2,",",''); ?>
									<?php
									switch($dispatchline->weight_unit){
											case -6:
												echo ' mg';
												break;
											case -3:
												echo ' g';
												break;
											case 0:
												echo ' kg';
												break;
										}
									?>
								</td>
								<td>
									poids réel : <?=number_format($dispatchline->weight_reel,2,",",''); ?>
									<?php
									switch($dispatchline->weight_reel_unit){
											case -6:
												echo ' mg';
												break;
											case -3:
												echo ' g';
												break;
											case 0:
												echo ' kg';
												break;
										}
									?>
								</td>
								<td>
									tare : <?=number_format($dispatchline->tare,2,",",''); ?>
									<?php
									switch($dispatchline->tare_unit){
											case -6:
												echo ' mg';
												break;
											case -3:
												echo ' g';
												break;
											case 0:
												echo ' kg';
												break;
										}
									?>
								</td>
							</tr>
							<?php
						}
					}
					//Aucun équipement lié à la ligne d'expédition	
					else{
						/*
						 * LIGNE RECAP PRODUIT
						 */
						print '<tr class="impair" style="height:50px;">';
						print '<td style="padding-left:5px;">'.$product->ref." - ".$product->label.'</td>';
						print '<td align="center" >'.$ATMdb->Get_field('asset_lot').'</td>';
						print '<td align="center">'.number_format($ATMdb->Get_field('tarif_poids'),2,",",'')." ".$unite.'</td>';
						print '<td align="center">'.$line->qty.'</td>';
						print '<td align="center">'.number_format($qte_expedie,2,",","").' '.$unite.'</td>';
						print '<td align="center">'.number_format((($ATMdb->Get_field('tarif_poids') * $line->qty) - $qte_expedie),2,",","")." ".$unite.'</td>';
						print '<td align="center" colspan="4"> </td>';
						print '</tr>';
					}
				}
				else{ //il n'existe aucune ligne d'équipement associé => création d'une ligne caché
					?>
					<tr class="ligne_<?=$line->rowid;?>" style="display: none;">
						<td colspan="2" align="left">
							<span style="padding-left: 25px;">Flacon lié :</span>
							<select id="equipement_<?=$line->rowid;?>_1" name="equipement_<?=$line->rowid;?>_1" class="equipement_<?=$line->rowid;?>">
							<?php
							//Chargement des équipement lié au produit
							$sql = "SELECT rowid, serial_number, lot_number, contenancereel_value, contenancereel_units
							 		 FROM ".MAIN_DB_PREFIX."asset
							 		 WHERE fk_product = ".$line->fk_product."
							 		 ORDER BY contenance_value DESC";
							$ATMdb->Execute($sql);
							
							$cpt = 0;
							while($ATMdb->Get_line()){
								switch($ATMdb->Get_field('contenancereel_value')){
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

								if($ATMdb->Get_field('contenancereel_value') > 0){
									$cpt++;
									?>
									<option value="<?=$ATMdb->Get_field('rowid'); ?>"><?=$ATMdb->Get_field('serial_number')." - Lot n° ".$ATMdb->Get_field('lot_number')." - ".number_format($ATMdb->Get_field('contenancereel_value'),2,",","")." ".$unite; ?></option>	
									<?php
								}	
							}
							
							if($cpt == 0){
								?>
								<option value="null">Aucun flacon utilisable pour ce produit</option>
								<?php
							}
							?>
							</select>
						</td>
						<td colspan="2">
							poids : <input type="text" id="poids_<?=$line->rowid;?>_1" name="poids_<?=$line->rowid;?>_1" class="poids_<?=$line->rowid;?>" style="width: 35px;"/>
							<select id="unitepoids_<?=$line->rowid;?>_1" name="unitepoids_<?=$line->rowid;?>_1" class="unitepoids_<?=$line->rowid;?>">
									<option value="-6">mg</option>
									<option value="-3">g</option>
									<option value="0">kg</option>
							</select>
						</td>
						<td>
							poids réel : <input type="text" id="poidsreel_<?=$line->rowid;?>_1" name="poidsreel_<?=$line->rowid;?>_1" class="poidsreel_<?=$line->rowid;?>" style="width: 35px;"/>
							<select id="unitereel_<?=$line->rowid;?>_1" name="unitereel_<?=$line->rowid;?>_1" class="unitereel_<?=$line->rowid;?>">
								<option value="-6">mg</option>
								<option value="-3">g</option>
								<option value="0">kg</option>
							</select>
						</td>
						<td>
							tare : <input type="text" id="tare_<?=$line->rowid;?>_1" name="tare_<?=$line->rowid;?>_1" class="tare_<?=$line->rowid;?>" style="width: 35px;"/>
							<select id="unitetare_<?=$line->rowid;?>_1" name="unitetare_<?=$line->rowid;?>_1" class="unitetare_<?=$line->rowid;?>">
								<option value="-6">mg</option>
								<option value="-3" selected="selected">g</option>
								<option value="0">kg</option>
							</select>
						</td>
					</tr>
				<?php
				}
			}

			//Ligne de commande libre
			elseif($line->product_type != 9){
				$ATMdb->Execute("SELECT tarif_poids, poids FROM ".MAIN_DB_PREFIX."commandedet WHERE rowid = ".$line->rowid);
				$ATMdb->Get_line();
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
				print '<tr class="impair" style="height:50px;">';
				print '<td style="padding-left:5px;">'.$product->ref." - ".$product->label.'</td>';
				print '<td align="center" >'.$ATMdb->Get_field('asset_lot').'</td>';
				print '<td align="center">'.number_format($ATMdb->Get_field('tarif_poids'),2,",","")." ".$unite.'</td>';
				print '<td align="center">'.$line->qty.'</td>';
				print '<td align="center"></td>';
				print '<td align="center"></td>';
				print '</tr>';
			}
		}
		?>
		</table>
	<?php }

	/* JS permettant de cloner les lignes équipements */
	?>
	<script type="text/javascript">
		function add_line(id_line){
			nb_line = $('tr[class=ligne_'+id_line+']:hidden').length;
			$('tr[class=ligne_'+id_line+']:hidden').prev().hide();
			if(nb_line == 1){
				$('tr[class=ligne_'+id_line+']').show();
			}
			else{
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
				$('.ligne_'+id_line+":last input:text").val('');
				$('#equipement_'+id_line+'_'+String(j)).after('&nbsp;<a alt="Supprimer la liaison" title="Supprimer la liaison" style="cursor:pointer;" onclick="delete_line(this,'+id_line+');"><img src="img/supprimer.png" style="cursor:pointer;" /></a>');
				i = i+1;
			}
		}
		
		function delete_line(ligne, id_line, id_dispatchdet_asset){
			nb_line = $('tr[class=ligne_'+id_line+']').length;
			if(nb_line>1)
				$(ligne).parent().parent().remove();
			else{
			
				$(ligne).parent().parent().hide();
				$(ligne).parent().parent().prev().show();
			}
			
			if(id_dispatchdet_asset != ""){
				$.ajax({
					type: "POST"
					,url:'script/ajax.delete_line.php'
					,data:{
						id_dispatchdet_asset : id_dispatchdet_asset
					}
				});
			}
		}
		
		function delete_input_hide(){
			$('tr:hidden').remove();
		}
	</script>
	<?php
		
	/*
	 * FORMULAIRE DE CREATION
	 */
	if(isset($_REQUEST['action']) && !empty($_REQUEST['action']) &&  $_REQUEST['action'] == "add"){
		?>
		<form action="?action=view&fk_commande=<?php echo $commande->id; ?>" method="POST" onsubmit="delete_input_hide();">
			<table class="notopnoleftnoright" width="100%" border="0" style="margin-bottom: 2px;" summary="">
			<tbody><tr>
			<td class="nobordernopadding" valign="middle"><div class="titre">Nouvelle expédition</div></td>
			</tr></tbody>
			</table>
			<br>
			<table class="border" width="100%">
				<!-- <tr><td align="left" width="300px;">Référence de l'expédition</td><td><input type="text" name="ref_expe"></td></tr> -->
				<tr><td align="left">Date de livraison prévue</td><td><?=$form->select_date($commande->date_livraison,'date_livraison',0,0);?></td></tr>
				<tr><td align="left">Méthode d'expédition</td><td><?=$form->selectarray("methode_dispatch",array('Enlèvement par le client','Transporteur'));?></td></tr>
				<tr><td align="left">Hauteur</td><td><input type="text" name="hauteur"> cm</td></tr>
				<tr><td align="left">Largeur</td><td><input type="text" name="largeur"> cm</td></tr>
				<tr><td align="left">Poids du colis</td><td><input type="text" name="poid_general"><select id="unitepoid_general" name="unitepoid_general"><option value="-6">mg</option><option value="-3">g</option><option value="0">kg</option></select></td></tr>
				<tr><td align="left">N° suivis transporteur</td><td><input type="text" name="num_transporteur"></td></tr>
			</table>
			<br>
			<input type="hidden" name="action" value="add_expedition">
			<input type="hidden" name="id" value="<?=$commande->id; ?>">
			<table class="liste" width="100%">
				<tr class="liste_titre">
					<td>Produit</td>
					<td align="center">Lot</td>
					<td align="center">Poids commandé</td>
					<td align="center">Qté commandée</td>
					<td align="center">Qté expédiée</td>
					<td align="center">Qté à expédier</td>
					<td align="center">Flacon</td>
					<td align="center">Poids</td>
					<td align="center">Poids Réel</td>
					<td align="center">Tare</td>
				</tr>
				<?php
				foreach($commande->lines as $line){
					$product = new Product($db);
					if($line->fk_product > 0) //Ligne de commande lié à un produit
					{
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
						
						//Récupération des quantitées déjà expédiées
						$ATMdb2 = new Tdb;
						$dispatch = new TDispatch;
						$qte_expedie = $dispatch->get_qte_expedie(&$ATMdb2,$line->rowid);
						($qte_expedie == 0) ? $qte_expedie = 0.00 : "";
						$ATMdb2->close();
						
						/*
						 * LIGNE RECAP PRODUIT
						 */
						print '<tr class="impair" style="height:50px; display:none;">';
						print '<td style="padding-left:5px;">'.$product->ref." - ".$product->label.'</td>';
						print '<td align="center" >'.$ATMdb->Get_field('asset_lot').'</td>';
						print '<td align="center">'.number_format($ATMdb->Get_field('tarif_poids'),2,",",'')." ".$unite.'</td>';
						print '<td align="center">'.$line->qty.'</td>';
						print '<td align="center">'.number_format($qte_expedie,2,",","").' '.$unite.'</td>';
						print '<td align="center">'.number_format((($ATMdb->Get_field('tarif_poids') * $line->qty) - $qte_expedie),2,",","")." ".$unite.'</td>';
						print '<td align="center" colspan="4"> </td>';
						print '</tr>';
						
						?>
						<tr class="ligne_<?=$line->rowid;?>">
							<?php
							print '<td style="padding-left:5px;">'.$product->ref." - ".$product->label.'</td>';
							print '<td align="center" >'.$ATMdb->Get_field('asset_lot').'</td>';
							print '<td align="center">'.number_format($ATMdb->Get_field('tarif_poids'),2,",",'')." ".$unite.'</td>';
							print '<td align="center">'.$line->qty.'</td>';
							print '<td align="center">'.number_format($qte_expedie,2,",","").' '.$unite.'</td>';
							print '<td align="center">'.number_format((($ATMdb->Get_field('tarif_poids') * $line->qty) - $qte_expedie),2,",","")." ".$unite.'</td>';
							?>
							<td align="left">
								<span style="padding-left: 25px;">Flacon lié :</span>
								<select id="equipement_<?=$line->rowid;?>_1" name="equipement_<?=$line->rowid;?>_1" class="equipement_<?=$line->rowid;?>">
								<?php
								//Chargement des équipement lié au produit
								$sql = "SELECT rowid, serial_number, lot_number, contenancereel_value, contenancereel_units
								 		 FROM ".MAIN_DB_PREFIX."asset
								 		 WHERE fk_product = ".$line->fk_product;
								
								($ATMdb->Get_field('asset_lot') != NULL) ? $sql.= " AND lot_number = ".$ATMdb->Get_field('asset_lot') : "";
								
								$sql.=" ORDER BY contenance_value DESC";
								
								$ATMdb->Execute($sql);
								
								$cpt = 0;
								while($ATMdb->Get_line()){
									switch($ATMdb->Get_field('contenancereel_units')){
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
									
									if($ATMdb->Get_field('contenancereel_value') > 0){
										$cpt++;
										?>
										<option value="<?=$ATMdb->Get_field('rowid'); ?>"><?=$ATMdb->Get_field('serial_number')." - Lot n° ".$ATMdb->Get_field('lot_number')." - ".number_format($ATMdb->Get_field('contenancereel_value'),2,",","")." ".$unite; ?></option>	
										<?php
									}	
								}
								
								if($cpt == 0){
									?>
									<option value="null">Aucun flacon utilisable pour ce produit</option>
									<?php
								}
								?>
								</select>
								<a alt="Supprimer la liaison" title="Supprimer la liaison" style="cursor:pointer;" onclick="delete_line(this,<?=$line->rowid;?>);"><img src="img/supprimer.png" style="cursor:pointer;" /></a>
							</td>
							<td>
								poids : <input type="text" id="poids_<?=$line->rowid;?>_1" name="poids_<?=$line->rowid;?>_1" class="poids_<?=$line->rowid;?>" style="width: 35px;"/>
								<select id="unitepoids_<?=$line->rowid;?>_1" name="unitepoids_<?=$line->rowid;?>_1" class="unitepoids_<?=$line->rowid;?>">
										<option value="-6">mg</option>
										<option value="-3">g</option>
										<option value="0">kg</option>
								</select>
							</td>
							<td>
								poids réel : <input type="text" id="poidsreel_<?=$line->rowid;?>_1" name="poidsreel_<?=$line->rowid;?>_1" class="poidsreel_<?=$line->rowid;?>" style="width: 35px;"/>
								<select id="unitereel_<?=$line->rowid;?>_1" name="unitereel_<?=$line->rowid;?>_1" class="unitereel_<?=$line->rowid;?>">
									<option value="-6">mg</option>
									<option value="-3">g</option>
									<option value="0">kg</option>
								</select>
							</td>
							<td>
								tare : <input type="text" id="tare_<?=$line->rowid;?>_1" name="tare_<?=$line->rowid;?>_1" class="tare_<?=$line->rowid;?>" style="width: 35px;"/>
								<select id="unitetare_<?=$line->rowid;?>_1" name="unitetare_<?=$line->rowid;?>_1" class="unitetare_<?=$line->rowid;?>">
									<option value="-6">mg</option>
									<option value="-3" selected="selected">g</option>
									<option value="0">kg</option>
								</select>
							</td>
						</tr>
						<tr><td colspan="8" align="left"><span style="padding-left: 25px;">Ajouter un flacon d'expédition:</span><a alt="Lié un flacon suplémentaire" title="Lié un flacon suplémentaire" style="cursor:pointer;" onclick="add_line(<?=$line->rowid;?>);"><img src="img/ajouter.png" style="cursor:pointer;" /></a></td></tr>
						<?php
					}

					//Ligne de commande libre
					elseif($line->product_type != 9){
						$ATMdb->Execute("SELECT tarif_poids, poids FROM ".MAIN_DB_PREFIX."commandedet WHERE rowid = ".$line->rowid);
						$ATMdb->Get_line();
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
						print '<tr class="impair" style="height:50px;">';
						print '<td style="padding-left:5px;">'.$product->ref." - ".$product->label.'</td>';
						print '<td align="center" >'.$ATMdb->Get_field('asset_lot').'</td>';
						print '<td align="center">'.number_format($ATMdb->Get_field('tarif_poids'),2,",","")." ".$unite.'</td>';
						print '<td align="center">'.$line->qty.'</td>';
						print '<td align="center"></td>';
						print '<td align="center"></td>';
						print '</tr>';
					}
				}
			?>
			</table>
			<center><br><input type="submit" class="button" value="Sauvegarder" name="save">&nbsp;
			<input type="button" class="button" value="Annuler" name="back" onclick="window.location = 'liste.php?fk_commande=<?php echo $commande->id;?>';"></center>
		<br></form>
		<?php		
	}
	
	/*
	 * FORMULAIRE DE MODIFICATION
	 */
	elseif(isset($_REQUEST['action']) && !empty($_REQUEST['action']) &&  ($_REQUEST['action'] == "update")){
		$dispatch = new TDispatch;
		$dispatch->load(&$ATMdb,$_REQUEST['fk_dispatch']);
		?>
		<form action="" method="POST" onsubmit="delete_input_hide();">
			<table class="notopnoleftnoright" width="100%" border="0" style="margin-bottom: 2px;" summary="">
			<tbody><tr>
			<td class="nobordernopadding" valign="middle"><div class="titre">Modification expédition</div></td>
			</tr></tbody>
			</table>
			<br>
			<table class="border" width="100%">
				<!--<tr><td align="left" width="300px;">Référence de l'expédition</td><td><input type="text" name="ref_expe" value="<?=$dispatch->ref; ?>"></td></tr>-->
				<tr><td align="left">Date de livraison prévue</td><td><?=$form->select_date($dispatch->date_livraison,'date_livraison',0,0);?></td></tr>
				<tr><td align="left">Méthode d'expédition</td><td><?=$form->selectarray("methode_dispatch",array('Enlèvement par le client','Transporteur'),$dispatch->type_expedition);?></td></tr>
				<tr><td align="left">Hauteur</td><td><input type="text" name="hauteur" value="<?=$dispatch->height; ?>"> cm</td></tr>
				<tr><td align="left">Largeur</td><td><input type="text" name="largeur" value="<?=$dispatch->width; ?>"> cm</td></tr>
				<tr><td align="left">Poids du colis</td><td><input type="text" name="poid_general" value="<?=$dispatch->weight; ?>">
															<select id="unitepoid_general" name="unitepoid_general">
																<option value="-6" <?php echo ($dispatch->weight_units == "-6")? 'selected="selected"' : ""; ?>>mg</option>
																<option value="-3" <?php echo ($dispatch->weight_units == "-3")? 'selected="selected"' : ""; ?>>g</option>
																<option value="0" <?php echo ($dispatch->weight_units == "0")? 'selected="selected"' : ""; ?>>kg</option>
															</select>
														</td></tr>
				<tr><td align="left">N° suivis transporteur</td><td><input type="text" name="num_transporteur" value="<?=$dispatch->num_transporteur; ?>"></td></tr>
			</table>
			<br>
			<input type="hidden" name="action" value="update_expedition">
			<input type="hidden" name="id" value="<?=$commande->id; ?>">
			<input type="hidden" name="fk_dispatch" value="<?=$dispatch->rowid; ?>">
			<table class="liste" width="100%">
				<tr class="liste_titre">
					<td>Produit</td>
					<td align="center">Lot</td>
					<td align="center">Poids commandé</td>
					<td align="center">Qté commandée</td>
					<td align="center">Qté expédiée</td>
					<td align="center">Qté à expédier</td>
					<td align="center">Flacon</td>
					<td align="center">Poids</td>
					<td align="center">Poids Réel</td>
					<td align="center">Tare</td>
				</tr>
				<?php
				foreach($commande->lines as $line){
					$product = new Product($db);
					if($line->fk_product > 0) //Ligne de commande lié à un produit
					{
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
					
						//Récupération des quantitées déjà expédiées
						$ATMdb2 = new Tdb;
						$qte_expedie = $dispatch->get_qte_expedie(&$ATMdb2,$line->rowid);
						($qte_expedie == 0) ? $qte_expedie = 0.00 : "";
						$ATMdb2->close();
						
						/*
						 * LIGNE RECAP PRODUIT
						 */
						print '<tr class="impair" style="height:50px;display:none;">';
						print '<td style="padding-left:5px;">'.$product->ref." - ".$product->label.'</td>';
						print '<td align="center" >'.$ATMdb->Get_field('asset_lot').'</td>';
						print '<td align="center">'.number_format($ATMdb->Get_field('tarif_poids'),2,",",'')." ".$unite.'</td>';
						print '<td align="center">'.$line->qty.'</td>';
						print '<td align="center">'.number_format($qte_expedie,2,",","").' '.$unite.'</td>';
						print '<td align="center">'.number_format((($ATMdb->Get_field('tarif_poids') * $line->qty) - $qte_expedie),2,",","")." ".$unite.'</td>';
						print '<td align="center" colspan="4"> </td>';
						print '</tr>';
						
						$res = $dispatch->loadLines(new Tdb,$line->rowid);
						
						//Il existe au moin une ligne d'équipement associé à la ligne de commande
						if($res){
							foreach($dispatch->lines as $dispatchline){
								?>
								<tr class="ligne_<?=$line->rowid;?>">
									<input type="hidden" name="idDispatchdetAsset_<?=$line->rowid;?>_<?=$dispatchline->rang;?>" value="<?=$dispatchline->rowid;?>" />
									<?php
									//echo $product->ref."<br><br>";
									print '<td style="padding-left:5px;">'.$product->ref." - ".$product->label.'</td>';
									print '<td align="center" >'.$ATMdb->Get_field('asset_lot').'</td>';
									print '<td align="center">'.number_format($ATMdb->Get_field('tarif_poids'),2,",",'')." ".$unite.'</td>';
									print '<td align="center">'.$line->qty.'</td>';
									print '<td align="center">'.number_format($qte_expedie,2,",","").' '.$unite.'</td>';
									print '<td align="center">'.number_format((($ATMdb->Get_field('tarif_poids') * $line->qty) - $qte_expedie),2,",","")." ".$unite.'</td>';
									?>
									<td align="left">
										<span style="padding-left: 25px;">Flacon lié :</span>
										<select id="equipement_<?=$line->rowid;?>_<?=$dispatchline->rang;?>" name="equipement_<?=$line->rowid;?>_<?=$dispatchline->rang;?>" class="equipement_<?=$line->rowid;?>">
										<?php
										//Chargement des équipement lié au produit
										$sql = "SELECT rowid, serial_number, lot_number, contenancereel_value, contenancereel_units
										 		 FROM ".MAIN_DB_PREFIX."asset
										 		 WHERE fk_product = ".$line->fk_product."
										 		 ORDER BY contenance_value DESC";
										$ATMdb->Execute($sql);
										
										$cpt = 0;
										while($ATMdb->Get_line()){
											switch($ATMdb->Get_field('contenancereel_units')){
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
											
											
											if($ATMdb->Get_field('contenancereel_value') > 0){
												$cpt++;
												?>
												<option value="<?=$ATMdb->Get_field('rowid'); ?>" <?php echo ($dispatchline->fk_asset == $ATMdb->Get_field('rowid')) ? 'selected="selected"' : ""; ?>><?=$ATMdb->Get_field('serial_number')." - Lot n° ".$ATMdb->Get_field('lot_number')." - ".number_format($ATMdb->Get_field('contenancereel_value'),2,",","")." ".$unite; ?></option>	
												<?php
											}	
										}

										if($cpt == 0){
											?>
											<option value="null">Aucun flacon utilisable pour ce produit</option>
											<?php
										}
										?>
										</select>
										<a alt="Supprimer la liaison" title="Supprimer la liaison" style="cursor:pointer;" onclick="delete_line(this,<?=$line->rowid;?>,<?=$dispatchline->rowid;?>);"><img src="img/supprimer.png" style="cursor:pointer;" /></a>
									</td>
									<td>
										poids : <input type="text" id="poids_<?=$line->rowid;?>_<?=$dispatchline->rang;?>" name="poids_<?=$line->rowid;?>_<?=$dispatchline->rang;?>" class="poids_<?=$line->rowid;?>" style="width: 35px;" value="<?=number_format($dispatchline->weight,2,",",''); ?>"/>
										<select id="unitepoids_<?=$line->rowid;?>_<?=$dispatchline->rang;?>" name="unitepoids_<?=$line->rowid;?>_<?=$dispatchline->rang;?>" class="unitepoids_<?=$line->rowid;?>">
												<option value="-6" <?php echo ($dispatchline->weight_unit == "-6") ? 'selected="selected"' : ""; ?>>mg</option>
												<option value="-3" <?php echo ($dispatchline->weight_unit == "-3") ? 'selected="selected"' : ""; ?>>g</option>
												<option value="0" <?php echo ($dispatchline->weight_unit == "0") ? 'selected="selected"' : ""; ?>>kg</option>
										</select>
									</td>
									<td>
										poids réel : <input type="text" id="poidsreel_<?=$line->rowid;?>_<?=$dispatchline->rang;?>" name="poidsreel_<?=$line->rowid;?>_<?=$dispatchline->rang;?>" class="poidsreel_<?=$line->rowid;?>" style="width: 35px;" value="<?=number_format($dispatchline->weight_reel,2,",",''); ?>"/>
										<select id="unitereel_<?=$line->rowid;?>_<?=$dispatchline->rang;?>" name="unitereel_<?=$line->rowid;?>_<?=$dispatchline->rang;?>" class="unitereel_<?=$line->rowid;?>">
											<option value="-6" <?php echo ($dispatchline->weight_reel_unit == "-6") ? 'selected="selected"' : ""; ?>>mg</option>
											<option value="-3" <?php echo ($dispatchline->weight_reel_unit == "-3") ? 'selected="selected"' : ""; ?>>g</option>
											<option value="0" <?php echo ($dispatchline->weight_reel_unit == "0") ? 'selected="selected"' : ""; ?>>kg</option>
										</select>
									</td>
									<td>
										tare : <input type="text" id="tare_<?=$line->rowid;?>_<?=$dispatchline->rang;?>" name="tare_<?=$line->rowid;?>_<?=$dispatchline->rang;?>" class="tare_<?=$line->rowid;?>" style="width: 35px;" value="<?=number_format($dispatchline->tare,2,",",''); ?>"/>
										<select id="unitetare_<?=$line->rowid;?>_<?=$dispatchline->rang;?>" name="unitetare_<?=$line->rowid;?>_<?=$dispatchline->rang;?>" class="unitetare_<?=$line->rowid;?>">
											<option value="-6" <?php echo ($dispatchline->tare_unit == "-6") ? 'selected="selected"' : ""; ?>>mg</option>
											<option value="-3" <?php echo ($dispatchline->tare_unit == "-3") ? 'selected="selected"' : ""; ?>>g</option>
											<option value="0" <?php echo ($dispatchline->tare_unit == "0") ? 'selected="selected"' : ""; ?>>kg</option>
										</select>
									</td>
								</tr>
								<?php
							}
						}
						else{ //il n'existe aucune ligne d'équipement associé => création d'une ligne caché
						
							print '<tr class="impair" style="height:50px;">';
							print '<td style="padding-left:5px;">'.$product->ref." - ".$product->label.'</td>';
							print '<td align="center" >'.$ATMdb->Get_field('asset_lot').'</td>';
							print '<td align="center">'.number_format($ATMdb->Get_field('tarif_poids'),2,",",'')." ".$unite.'</td>';
							print '<td align="center">'.$line->qty.'</td>';
							print '<td align="center">'.number_format($qte_expedie,2,",","").' '.$unite.'</td>';
							print '<td align="center">'.number_format((($ATMdb->Get_field('tarif_poids') * $line->qty) - $qte_expedie),2,",","")." ".$unite.'</td>';
							print '<td align="center" colspan="4"> </td>';
							print '</tr>';
							
							?>
							<tr class="ligne_<?=$line->rowid;?>" style="display: none;">
								<?php
								print '<td style="padding-left:5px;">'.$product->ref." - ".$product->label.'</td>';
								print '<td align="center" >'.$ATMdb->Get_field('asset_lot').'</td>';
								print '<td align="center">'.number_format($ATMdb->Get_field('tarif_poids'),2,",","")." ".$unite.'</td>';
								print '<td align="center">'.$line->qty.'</td>';
								print '<td align="center">'.number_format($qte_expedie,2,",","").' '.$unite.'</td>';
								print '<td align="center">'.number_format((($ATMdb->Get_field('tarif_poids') * $line->qty) - $qte_expedie),2,",","")." ".$unite.'</td>';
								?>
								<td align="left">
									<span style="padding-left: 25px;">Flacon lié :</span>
									<select id="equipement_<?=$line->rowid;?>_1" name="equipement_<?=$line->rowid;?>_1" class="equipement_<?=$line->rowid;?>">
									<?php
									//Chargement des équipement lié au produit
									$sql = "SELECT rowid, serial_number, lot_number, contenancereel_value, contenancereel_units
									 		 FROM ".MAIN_DB_PREFIX."asset
									 		 WHERE fk_product = ".$line->fk_product."
									 		 ORDER BY contenance_value DESC";
									$ATMdb->Execute($sql);
									
									$cpt = 0;
									while($ATMdb->Get_line()){
										switch($ATMdb->Get_field('contenancereel_value')){
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

										if($ATMdb->Get_field('contenancereel_value') > 0){
											$cpt++;
											?>
											<option value="<?=$ATMdb->Get_field('rowid'); ?>"><?=$ATMdb->Get_field('serial_number')." - Lot n° ".$ATMdb->Get_field('lot_number')." - ".number_format($ATMdb->Get_field('contenancereel_value'),2,",","")." ".$unite; ?></option>	
											<?php
										}	
									}
									
									if($cpt == 0){
										?>
										<option value="null">Aucun Flacon utilisable pour ce produit</option>
										<?php
									}
									?>
									</select>
									<a alt="Supprimer la liaison" title="Supprimer la liaison" style="cursor:pointer;" onclick="delete_line(this,<?=$line->rowid;?>);"><img src="img/supprimer.png" style="cursor:pointer;" /></a>
								</td>
								<td>
									poids : <input type="text" id="poids_<?=$line->rowid;?>_1" name="poids_<?=$line->rowid;?>_1" class="poids_<?=$line->rowid;?>" style="width: 35px;"/>
									<select id="unitepoids_<?=$line->rowid;?>_1" name="unitepoids_<?=$line->rowid;?>_1" class="unitepoids_<?=$line->rowid;?>">
											<option value="-6">mg</option>
											<option value="-3">g</option>
											<option value="0">kg</option>
									</select>
								</td>
								<td>
									poids réel : <input type="text" id="poidsreel_<?=$line->rowid;?>_1" name="poidsreel_<?=$line->rowid;?>_1" class="poidsreel_<?=$line->rowid;?>" style="width: 35px;"/>
									<select id="unitereel_<?=$line->rowid;?>_1" name="unitereel_<?=$line->rowid;?>_1" class="unitereel_<?=$line->rowid;?>">
										<option value="-6">mg</option>
										<option value="-3">g</option>
										<option value="0">kg</option>
									</select>
								</td>
								<td>
									tare : <input type="text" id="tare_<?=$line->rowid;?>_1" name="tare_<?=$line->rowid;?>_1" class="tare_<?=$line->rowid;?>" style="width: 35px;"/>
									<select id="unitetare_<?=$line->rowid;?>_1" name="unitetare_<?=$line->rowid;?>_1" class="unitetare_<?=$line->rowid;?>">
										<option value="-6">mg</option>
										<option value="-3" selected="selected">g</option>
										<option value="0">kg</option>
									</select>
								</td>
							</tr>
						<?php
						}
						?>
						<tr><td colspan="8" align="left"><span style="padding-left: 25px;">Ajouter un flacon d'expédition :</span><a alt="Lié un flacon suplémentaire" title="Lié un flacon suplémentaire" style="cursor:pointer;" onclick="add_line(<?=$line->rowid;?>);"><img src="img/ajouter.png" style="cursor:pointer;" /></a></td></tr>
						<?php
					}

					//Ligne de commande libre
					elseif($line->product_type != 9){
						$ATMdb->Execute("SELECT tarif_poids, poids FROM ".MAIN_DB_PREFIX."commandedet WHERE rowid = ".$line->rowid);
						$ATMdb->Get_line();
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
						print '<tr class="impair" style="height:50px;">';
						print '<td style="padding-left:5px;">'.$product->ref." - ".$product->label.'</td>';
						print '<td align="center" >'.$ATMdb->Get_field('asset_lot').'</td>';
						print '<td align="center">'.number_format($ATMdb->Get_field('tarif_poids'),2,",",'')." ".$unite.'</td>';
						print '<td align="center">'.$line->qty.'</td>';
						print '<td align="center"></td>';
						print '<td align="center"></td>';
						print '</tr>';
					}
				}
				?>
			</table>
			<center><br>
				<?php
				if($dispatch->statut == 0){
					?>
					<input type="submit" class="button" value="Valider" name="valider" onclick="confirm('Êtes-vous sûr de vouloir valider cette expédition sous la référence <?=$dispatch->ref; ?>?');">&nbsp;
					<input type="submit" class="button" value="Sauvegarder" name="save">&nbsp;
					<input type="submit" class="button" value="Annuler" name="back">
					<?php
				}
				elseif($dispatch->statut == 1){
					?>
					<input type="submit" class="button" value="Réouvrir" name="reouvrir">
					<input type="submit" class="button" value="Annuler" name="back">
					<?php
				}
				?></center>
		<br></form>
		<?php
	}

	//Liste des expéditions
	else{
		/*
		 * TRAITEMENT DES ACTIONS 
		 */
		 		 
		//Traitement création et modification 
		if(isset($_REQUEST['action']) && !empty($_REQUEST['action']) && isset($_REQUEST['save']) &&  ($_REQUEST['action'] == "add_expedition" || $_REQUEST['action'] == "update_expedition")){
			//echo "ok"; exit;
			/*echo '<pre>';
			print_r($_REQUEST);
			echo '</pre>'; exit;*/
			$dispatch = new TDispatch;
			$dispatch->enregistrer(&$ATMdb, $commande, $_REQUEST);
			?>
			<script type="text/javascript">
				window.location = 'fiche.php?action=view&fk_commande=<?php echo $commande->id;?>&fk_dispatch=<?php echo $dispatch->rowid; ?>';
			</script>
			<?php
		}
		
		//Traitement Suppression
		if(isset($_REQUEST['action']) && !empty($_REQUEST['action']) &&  $_REQUEST['action'] == "delete"){
			$dispatch = new TDispatch;
			$dispatch->load(&$ATMdb,$_REQUEST['fk_dispatch']);
			$dispatch->delete(&$ATMdb);
			?>
			<script type="text/javascript">
				window.location = 'liste.php?fk_commande=<?php echo $commande->id;?>';
			</script>
			<?php
		}
		
		//Traitement Validation	
		if(isset($_REQUEST['action']) && !empty($_REQUEST['action']) && isset($_REQUEST['valider']) && $_REQUEST['action'] == "update_expedition"){
			$dispatch = new TDispatch;
			$dispatch->load(&$ATMdb,$_REQUEST['fk_dispatch']);
			$dispatch->enregistrer(&$ATMdb, $commande, $_REQUEST);
			$dispatch->valider(&$ATMdb, $commande);
		}
		
		//Traitement Réouverture	
		if(isset($_REQUEST['action']) && !empty($_REQUEST['action']) && isset($_REQUEST['reouvrir']) && $_REQUEST['action'] == "update_expedition"){
			$dispatch = new TDispatch;
			$dispatch->load(&$ATMdb,$_REQUEST['fk_dispatch']);
			$dispatch->reouvrir(&$ATMdb, $commande);
		}
		
		//Traitement Facturer
		if(isset($_REQUEST['action']) && !empty($_REQUEST['action']) && isset($_REQUEST['fk_dispatch']) && $_REQUEST['action'] == "facturer"){
			
			$facture = new Facture($db);
			
			$facture->entity = $conf->entity;
			$facture->type = 0;
			$facture->date = date('Y-m-d H:i:s');
			$facture->socid = $_REQUEST['socid'];
			$facture->remise_absolue = $commande->remise_absolue;
			$facture->remise_percent = $commande->remise_percent;
			$facture->cond_reglement_id = $commande->cond_reglement_id;
			$facture->mode_reglement_id = $commande->mode_reglement_id;
			
			$facture->create($user);
			
			$sql = "SELECT dd.fk_commandedet, dda.rang, dda.weight, dda.weight_unit
					FROM ".MAIN_DB_PREFIX."dispatchdet_asset AS dda
						LEFT JOIN ".MAIN_DB_PREFIX."dispatchdet AS dd ON (dd.rowid = dda.fk_dispatchdet) 
					WHERE dd.fk_dispatch = ".$dispatch->rowid."
					ORDER BY rang ASC";
			
			$resql = $db->query($sql);
			while($res = $db->fetch_object($resql)){
				$commandedet = new OrderLine($db);
				$commandedet->fetch($res->fk_commandedet);
				
				/*echo '<pre>';
				print_r($commandedet);
				echo '</pre>';exit;*/
				$id_factureline = $facture->addline($facture->id, $commandedet->desc, $commandedet->subprice, 1, $commandedet->tva_tx,0,0,($commandedet->fk_product)?$commandedet->fk_product:0,$commandedet->remise_percent);
				
				$ATMdb->Execute("UPDATE ".MAIN_DB_PREFIX."facturedet SET poids = ".$res->weight_unit.", tarif_poids = ".$res->weight." WHERE rowid = ".$id_factureline);
				
				$factureLine = new FactureLigne($db);
				$factureLine->fetch($id_factureline);
				$factureLine->total_ht = $commandedet->subprice;
				$factureLine->total_tva = ($commandedet->subprice * (1 + ($factureLine->tva_tx/100))) - $factureLine->total_ht;
				$factureLine->total_ttc = $factureLine->total_ht + $factureLine->total_tva;
				$factureLine->update_total();
			}
			$facture->update_price();
			?>
			<script type="text/javascript">
				window.location = '<?php echo DOL_URL_ROOT; ?>/compta/facture.php?facid=<?php echo $facture->id; ?>';
			</script>
			<?php
			
		}
		
		print '<div class="tabsAction">';
		if($dispatch->statut == 1){
			print '<a class="butAction" href="?action=facturer&fk_dispatch='.$dispatch->rowid.'&socid='.$commande->socid.'&fk_commande='.$commande->id.'&notrigger=1">Facturer</a>';
		}
		print		'<a class="butAction" href="?action=update&fk_commande='.$commande->id.'&fk_dispatch='.$dispatch->rowid.'">Modifier</a><a class="butAction" href="?fk_commande='.$commande->id.'&action=delete&fk_dispatch='.$dispatch->rowid.'" onclick="return confirm(\'Voulez-vous vraiment supprimer cette expédition?\');">Supprimer</a>';
		print '</div><br>';
		
		?>
		<div class="titre">Liste des expéditions pour la commande : <?php  print $commande->getNomUrl(1,'commande'); ?></div>
		<br>
		<?php
		$TDispatch = array();
		
		/*echo '<pre>';
		print_r($commande);
		echo '</pre>';*/
		
		$sql = "SELECT rowid AS 'id', ref AS 'ref', statut AS statut, date_expedition AS 'date_expedition', date_livraison AS 'date_livraison', '' AS 'Supprimer'
				FROM ".MAIN_DB_PREFIX."dispatch
				WHERE fk_commande = ".$commande->id."
				ORDER BY date_expedition ASC";
		
		$r = new TSSRenderControler(new TDispatch);
			
		print $r->liste($ATMdb, $sql, array(
			'limit'=>array('nbLine'=>1000)
			,'title'=>array(
				'ref'=>'Référence expédition'
				,'statut'=>'Statut'
				,'date_expedition'=>'Date expédition'
				,'date_livraison'=>'Date livraison'
				,'Supprimer' => 'Supprimer'
			)
			,'translate'=>array(
				'statut'=>array(1=>'Validé',0=>'Brouillon')
				,'etat'=>array(1=>'Expédition complète',0=>'Expédition partielle')
			)
			,'type'=>array('date_expedition'=>'date','date_livraison'=>'date')
			,'hide'=>array(
				'id'
			)
			,'link'=>array(
				'ref'=>'<a href="?fk_commande='.$commande->id.'&action=view&fk_dispatch=@id@">@val@</a>'
				,'Supprimer'=>'<a href="?fk_commande='.$commande->id.'&action=delete&fk_dispatch=@id@" onclick="return confirm(\'Voulez-vous vraiment supprimer cette expédition?\');"><img src="img/delete.png"></a>'
			)
		));
		
		llxFooter();
	}

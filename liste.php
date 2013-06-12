<?php
	require('config.php');
	require('class/dispatch.class.php');
	require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
	require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
	require_once DOL_DOCUMENT_ROOT.'/custom/dispatch/class/dispatch.class.php';
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
	
	/*
	 * RECAPITULATIF COMMANDE
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
	
	if($commande->statut < 1){
		echo "Votre commande doit être validé avant de pouvoir l'expédier."; exit;
		print '</table>';
	}
	
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
		
	print '<div class="tabsAction">
			<a class="butAction" href="fiche.php?action=add&fk_commande='.$commande->id.'">Ajouter une expédition</a>
		</div><br>';
	
	//Traitement Suppression
	if(isset($_REQUEST['action']) && !empty($_REQUEST['action']) &&  $_REQUEST['action'] == "delete"){
		$dispatch = new TDispatch;
		$dispatch->load(&$ATMdb,$_REQUEST['fk_dispatch']);
		$dispatch->delete(&$ATMdb);
	}
	
	?>
	<div class="titre">Liste des expéditions</div>
	<?php
	$TDispatch = array();
	
	/*echo '<pre>';
	print_r($commande);
	echo '</pre>';*/
	
	$sql = "SELECT rowid AS 'id', ref AS 'ref', statut AS statut, etat as etat, date_expedition AS 'date_expedition', date_livraison AS 'date_livraison', '' AS 'Supprimer'
			FROM ".MAIN_DB_PREFIX."dispatch
			WHERE fk_commande = ".$commande->id."
			ORDER BY date_expedition ASC";
	
	$r = new TSSRenderControler(new TDispatch);
		
	print $r->liste($ATMdb, $sql, array(
		'limit'=>array('nbLine'=>1000)
		,'title'=>array(
			'ref'=>'Référence expédition'
			,'statut'=>'Statut'
			,'etat' => 'Etat commande'
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
			'ref'=>'<a href="fiche.php?fk_commande='.$commande->id.'&action=update&fk_dispatch=@id@">@val@</a>'
			,'Supprimer'=>'<a href="?fk_commande='.$commande->id.'&action=delete&fk_dispatch=@id@" onclick="return confirm(\'Voulez-vous vraiment supprimer cette expédition?\');"><img src="img/delete.png"></a>'
		)
	));
	
	llxFooter();

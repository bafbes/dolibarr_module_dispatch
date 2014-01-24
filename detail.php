<?php
require('config.php');
require('class/dispatchdetail.class.php');
include_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';

//Mise en page du tableau récap de l'expédition
include('detail_head.php');

$commande = $objectsrc; //récupération de l'onjet commande
$expedition = $object; //récupération de l'objet expedition
$expedition->fetch_lines();

/*echo '<pre>';
print_r($_POST);
echo '</pre>';exit;*/
global $db;

?>
<script type="text/javascript">
	function add_line(id_ligne,rang){
		newrang = rang + 1;
		ligne = $('.line_'+id_ligne+'_'+rang);
		
		//implémentation du compteur générale pour le traitement
		$('input[name=cptLigne]').val(parseInt($('input[name=cptLigne]').val()) + 1);
		
		//MAJ du rowspan pour la partie gauche de la ligne
		cpt = 0;
		cpt_max = 3;
		$('tr.line_'+id_ligne+'_1 > td').each(function(){
			if(cpt <= cpt_max)
				$(this).attr('rowspan',newrang);
			cpt = cpt + 1;
		});
		
		//clonage de la ligne et suppression des td en trop
		newligne = $(ligne).clone(true).insertAfter($(ligne));
		cpt = 0;
		if(rang == 1){
			$(newligne).find('> td').each(function(){
				if(cpt <= 3)
					$(this).remove();
				cpt = cpt + 1;
			});
		}
		
		//MAJ des libelle de class, name, id des différents champs de la nouvelle ligne
		$(newligne).attr('class','line_'+id_ligne+'_'+newrang);
		$('#add_'+id_ligne).attr('onclick','add_line('+id_ligne+','+newrang+')');
		if(rang == 1) $(newligne).children().eq(0).prepend('<a alt="Supprimer la liaison" title="Supprimer la liaison" style="cursor:pointer;" onclick="delete_line('+id_ligne+',this,false);"><img src="img/supprimer.png" style="cursor:pointer;" /></a>');
		<?php if($conf->global->MAIN_MODULE_ASSET) { ?> $(newligne).find('#equipement_'+id_ligne+'_'+rang).attr('id','equipement_'+id_ligne+'_'+newrang).attr('name','equipement_'+id_ligne+'_'+newrang); <?php } ?>
		$(newligne).find('#lot_'+id_ligne+'_'+rang).attr('id','lot_'+id_ligne+'_'+newrang).attr('name','lot_'+id_ligne+'_'+newrang);
		$(newligne).find('#weight_'+id_ligne+'_'+rang).attr('id','weight_'+id_ligne+'_'+newrang).attr('name','weight_'+id_ligne+'_'+newrang);
		$(newligne).find('select[name=weightunit_'+id_ligne+'_'+rang+']').attr('name','weightunit_'+id_ligne+'_'+newrang);
		$(newligne).find('#weightreel_'+id_ligne+'_'+rang).attr('id','weightreel_'+id_ligne+'_'+newrang).attr('name','weightreel_'+id_ligne+'_'+newrang);
		$(newligne).find('select[name=weightreelunit_'+id_ligne+'_'+rang+']').attr('name','weightreelunit_'+id_ligne+'_'+newrang);
		$(newligne).find('#tare_'+id_ligne+'_'+rang).attr('id','tare_'+id_ligne+'_'+newrang).attr('name','tare_'+id_ligne+'_'+newrang);
		$(newligne).find('select[name=tareunit_'+id_ligne+'_'+rang+']').attr('name','tareunit_'+id_ligne+'_'+newrang);
		
		$(newligne).find('>input').val('');
	}
	
	function delete_line(id_ligne,ligne,id_detail){
		
		$(ligne).parent().parent().remove();
		
		cpt = 0;
		$('tr.line_'+id_ligne+'_1 > td').each(function(){
			if(cpt <= 4){
				nb = $(this).attr('rowspan');
				$(this).attr('rowspan',nb - 1);
				$('#add_'+id_ligne).attr('onclick','add_line('+id_ligne+','+(nb-1)+')')
			}
			cpt = cpt + 1;
		});
		
		if(id_detail != false){
			$.ajax({
				type: "POST"
				,url:'script/ajax.delete_line.php'
				,data:{
					id_detail : id_detail
				}
			});
		}
	}
	
	$(function() {
		$( "#dialog" ).dialog({
			autoOpen: false,
			height: 700,
			width: 900,
			show: {
				effect: "blind",
				duration: 1000
			},
			buttons: {
				"Annuler": function() {
					$('#etiquettes').empty();
					$( this ).dialog( "close" );
				},				
				"Imprimer": function(){
					window.frames.etiquettes.focus();
					window.frames.etiquettes.print();
				}
			}
		});
		$( "#btnimpression" ).click(function() {
			$( "#dialog" ).dialog( "open" );
		});
	});
	
	function generer_etiquettes(){
		
		$('#etiquettes').attr('src','imp_etiquette.php?startpos='+$('#startpos').val()+'&copie='+$('#copie').val()+'&modele='+$('#modele').val()+'&expedition='+<?php echo $expedition->id; ?>+'&margetop='+$('#margetop').val()+'&margeleft='+$('#margeleft').val());
	}
</script>
<?php
/*echo '<pre>';
print_r($expedition);
echo '</pre>'; exit;*/

//Boutons d'action
print '<div class="tabsAction">';
if($expedition->statut == 0 && $_REQUEST['action'] != 'edit'){
	print '<a class="butAction" href="?id='.$expedition->id.'&action=edit">Modifier le détail</a>';
}
elseif($_REQUEST['action'] != 'edit') {
	print '<a class="butAction" id="btnimpression">Imprimer les étiquettes</a>';
}
print '</div><br>';

print "<div class=\"titre\">Détail de l'expédition</div>";

// Get parameters
_action($expedition,$commande);

function _action(&$expedition,&$commande) {
	global $user, $conf;	
	$PDOdb=new TPDOdb;

	if(isset($_REQUEST['action'])) {
		switch($_REQUEST['action']) {
			
			case 'edit'	: //Ajout ou suppression de flacons associé à une ligne d'expédition
				_fiche($PDOdb,$expedition,$commande,'edit');
				break;
				
			case 'save':
				_save_expedition_lines($PDOdb,$expedition,$commande,$_REQUEST);
				_fiche($PDOdb,$expedition,$commande,'view');
				break;
			
			default :
				_fiche($PDOdb,$expedition,$commande,'view');
		}
		
	}
	elseif(isset($_REQUEST['id'])) {
		_fiche($PDOdb,$expedition,$commande,'view');
	}
}

//Affiche le détail des lignes d'expédition avec leur flacons
function _fiche(&$PDOdb,&$expedition, &$commande, $mode){
	
	if($mode == 'edit'){
		print '<form action="?id='.$expedition->id.'" method="POST" onsubmit="delete_input_hide();">';
		print '<input type="hidden" name="action" value="save">';
		print '<input type="hidden" name="cptLigne" value="'.count($expedition->lines).'">';
	}
	
	_print_entete_tableau();
	
	if(count($expedition->lines) > 0){
		
		//Parcours des lignes d'expédition
		foreach($expedition->lines as $line){
			
			//Récupération du rowid de la ligne d'expédition car non stocké dans l'objet dolibarr....
			$sql = "SELECT rowid 
					FROM ".MAIN_DB_PREFIX."expeditiondet 
					WHERE fk_origin_line = ".$line->fk_origin_line."
					AND fk_expedition = ".$expedition->id;
					
			$PDOdb->Execute($sql);
			$PDOdb->Get_line();
			$fk_expeditiondet = $PDOdb->Get_field('rowid');
			
			//Chargement des détails associé à la ligne
			$TDispatchDetail = new TDispatchDetail;
			$TDispatchDetail->loadLines($PDOdb, $fk_expeditiondet);
			
			_print_expedition_line($PDOdb,$expedition,$line,$TDispatchDetail,$fk_expeditiondet,$mode);
		}
	}
	else{
		print '<tr><td colspan="9">Aucunes lignes d\'expéditions à afficher<td></tr>';
	}
	
	print '</table>';
	if($mode == 'edit'){
		print '<center><br><input type="submit" class="button" value="Sauvegarder" name="save">&nbsp;';
		print '<input type="button" class="button" value="Annuler" name="back" onclick="window.location = \'?id='.$expedition->id.'\';"></center>';
		print '</form>';
	}
}

//Parse le formulaire puis le traite
function _save_expedition_lines(&$PDOdb,&$expedition, &$commande, $request){
	
	//Parsing des données passé en $_POST
	foreach($request as $cle=>$val){
		$Tcle = explode('_', $cle);
		if(!empty($Tcle)){
			$Tval[$Tcle[2]][$Tcle[1]][$Tcle[0]] = $val;
		}
	}
	
	//Création des associations expeditiondet => expeditiondet_asset
	foreach($Tval as $cle=>$val){
		if(is_numeric($cle)){
			foreach($val as $cle2=>$val2){
				if(!empty($val2['weightreel']) && !empty($val2['weight'])){
					$dispatchdetail = new TDispatchDetail();
					
					if(isset($val2['idexpeditiondetasset'])) $dispatchdetail->load($PDOdb, $val2['idexpeditiondetasset']);
					
					$dispatchdetail->fk_expeditiondet = $cle2;
					$dispatchdetail->fk_asset = $val2['equipement'];
					$dispatchdetail->rang = $cle;
					$dispatchdetail->lot = $val2['lot'];;
					$dispatchdetail->weight = price2num($val2['weight'],2);
					$dispatchdetail->weight_reel = price2num($val2['weightreel'],2); 
					$dispatchdetail->tare = price2num($val2['tare'],2);
					$dispatchdetail->weight_unit = $val2['weightunit'];
					$dispatchdetail->weight_reel_unit = $val2['weightreelunit'];
					$dispatchdetail->tare_unit = $val2['tareunit'];
					
					$dispatchdetail->save($PDOdb);
				}
			}
		}
	}
}

//Affiche l'entete du tableau
function _print_entete_tableau(){
	
	global $conf;
	
	if($conf->global->MAIN_MODULE_ASSET){
	
		print '<table class="liste" width="100%">';
		print '	<tr class="liste_titre">';
		print '		<td style="width: 300px;">Produit</td>';
		print '		<td align="center" style="width: 100px;">Flacon prévu</td>';
		print '		<td align="center" style="width: 150px;">Poids commandé</td>';
		print '		<td align="center" style="width: 150px;">Poids expédié</td>';
		print '		<td align="center" style="width: 150px;">Poids à expédier</td>';
		print '		<td align="center">Flacon réel</td>';
		print '		<td align="center">Poids</td>';
		print '		<td align="center">Poids réel</td>';
		print '		<td align="center">Tare</td>';
		print '	</tr>';
	}
	else{
		print '<table class="liste" width="100%">';
		print '	<tr class="liste_titre">';
		print '		<td style="width: 300px;">Produit</td>';
		print '		<td align="center" style="width: 150px;">Unités commandées</td>';
		print '		<td align="center" style="width: 150px;">Unités expédiées</td>';
		print '		<td align="center" style="width: 150px;">Unités à expédier</td>';
		print '		<td align="center" style="width: 150px;">Lot</td>';
		print '		<td align="center">Unités</td>';
		print '		<td align="center">Unités réelles</td>';
		print '		<td align="center">Tare</td>';
		print '	</tr>';
	}
}

//Affiche la ligne d'expédition
function _print_expedition_line(&$PDOdb,&$expedition,&$line,&$TDispatchDetail,$fk_expeditiondet,$mode){
	global $db,$conf;
	
	if($TDispatchDetail->nbLines > 0)
		$nbLines = $TDispatchDetail->nbLines;
	elseif($conf->asset->enabled)
		$nbLines = $line->qty_shipped;
	else
		$nbLines = 1;
	
	$PDOdb->Execute("SELECT fk_product, tarif_poids, poids, qty FROM ".MAIN_DB_PREFIX."commandedet WHERE rowid = ".$line->fk_origin_line);
	$PDOdb->Get_line();
	
	$product = new Product($db);
	if($PDOdb->Get_field('fk_product')) $product->fetch($PDOdb->Get_field('fk_product'));
	
	if($mode == 'edit')
		_form_expedition_line($PDOdb,$product,$line,$TDispatchDetail,$nbLines,$fk_expeditiondet);
	else if ($mode == 'view')
		_view_expedition_line($PDOdb,$product,$line,$TDispatchDetail,$nbLines);
}

//Affichage en type edition
function _form_expedition_line(&$PDOdb,&$product,&$line,&$TDispatchDetail,$nbLines,$fk_expeditiondet){
	global $db, $conf;
	
	if((int)$product->id == 0)
		$libelle = $line->description;
	else
		$libelle = $product->ref.' - '.$product->label;
	
	$form = new FormProduct($db);
	
	$poidsCommande = floatval($PDOdb->Get_field('tarif_poids') * $PDOdb->Get_field('qty'));
	$poids = $PDOdb->Get_field('poids');
	$asset_lot = $PDOdb->Get_field('asset_lot');
	$poidsExpedie = floatval($TDispatchDetail->getPoidsExpedie($PDOdb,$line->rowid,$product));
	$poidsAExpedier = floatval($poidsCommande - $poidsExpedie);
	$poidsAExpedierParFlacon = floatval($PDOdb->Get_field('tarif_poids'));
	
	if($conf->global->MAIN_MODULE_ASSET){
		dol_include_once('/asset/class/asset.class.php');
		$ATMdb = new Tdb;
		$asset = new TAsset();
		$asset->load($ATMdb, $asset_lot);
	}
	
	print '<tr class="line_'.$fk_expeditiondet.'_'.(($line->rang)? $line->rang : 1 ).'">';
	print '<td rowspan="'.$nbLines.'">'.$libelle.'</td>';
	if($conf->global->MAIN_MODULE_ASSET) print '<td rowspan="'.$nbLines.'" align="center">'.$asset->serial_number.'</td>';
	print '<td rowspan="'.$nbLines.'" align="center">'.$poidsCommande.' '.measuring_units_string($poids,"weight").'</td>';
	print '<td rowspan="'.$nbLines.'" align="center">'.$poidsExpedie.' '.measuring_units_string($poids,"weight").'</td>';
	print '<td rowspan="'.$nbLines.'" align="center">'.$poidsAExpedier.' '.measuring_units_string($poids,"weight").'</td>';
	if($TDispatchDetail->nbLines > 0){
		$cpt = 1;
		foreach($TDispatchDetail->lines as $detailline){
			if($cpt > 1){
				print '<tr style="height:30px;">';
				print '		<td align="right">';
				print '		<a alt="Supprimer la liaison" title="Supprimer la liaison" style="cursor:pointer;" onclick="delete_line('.$fk_expeditiondet.',this,'.$detailline->rowid.');"><img src="img/supprimer.png" style="cursor:pointer;" /></a>';
			}
			else
				print '		<td align="right">';
			if($conf->global->MAIN_MODULE_ASSET){
				_select_equipement($PDOdb,$product,$detailline,$fk_expeditiondet,$asset_lot);
			}
			else{
				print '<input type="text" style="width:75px;" id="lot_'.$fk_expeditiondet.'_'.(($detailline->rang)? $detailline->rang : 1 ).'" name="lot_'.$fk_expeditiondet.'_'.(($detailline->rang)? $detailline->rang : 1 ).'" value="'.$detailline->lot.'">';
			}
			
			print '		<input type="hidden" name="idexpeditiondetasset_'.$fk_expeditiondet.'_'.(($detailline->rang)? $detailline->rang : 1 ).'" value="'.$detailline->rowid.'">';
			print '</td>';
			print '<td align="center">';
			print		'<input style="width:50px;" type="text" id="weight_'.$fk_expeditiondet.'_'.(($detailline->rang)? $detailline->rang : 1 ).'" name="weight_'.$fk_expeditiondet.'_'.(($detailline->rang)? $detailline->rang : 1 ).'" value="'.$detailline->weight.'">';
			print 		$form->select_measuring_units("weightunit_".$fk_expeditiondet.'_'.(($detailline->rang)? $detailline->rang : 1 ),"weight",$detailline->weight_unit);
			print '</td>';
			print '<td align="center">';
			print		'<input style="width:50px;" type="text" id="weightreel_'.$fk_expeditiondet.'_'.(($detailline->rang)? $detailline->rang : 1 ).'" name="weightreel_'.$fk_expeditiondet.'_'.(($detailline->rang)? $detailline->rang : 1 ).'" value="'.$detailline->weight_reel.'">';
			print 		$form->select_measuring_units("weightreelunit_".$fk_expeditiondet.'_'.(($detailline->rang)? $detailline->rang : 1 ),"weight",$detailline->weight_reel_unit);
			print '</td>';
			print '<td align="center">';
			print		'<input style="width:50px;" type="text" id="tare_'.$fk_expeditiondet.'_'.(($detailline->rang)? $detailline->rang : 1 ).'" name="tare_'.$fk_expeditiondet.'_'.(($detailline->rang)? $detailline->rang : 1 ).'" value="'.$detailline->tare.'">';
			print 		$form->select_measuring_units("tareunit_".$fk_expeditiondet.'_'.(($detailline->rang)? $detailline->rang : 1 ),"weight",$detailline->tare_unit);
			print '</td>';
			if($cpt > 1)
				print '</tr>';
			$cpt++;
		}
	}
	else{
		if($conf->global->MAIN_MODULE_ASSET){
			$cpt = $line->qty_shipped;
		}
		else {
			$cpt = 1;
		}
		for($i=0;$i<$cpt;$i++){
			if($i > 0){
				print '<tr class="line_'.$fk_expeditiondet.'_'.($i+1).'">';
				print '<td align="right">';
				print 		'<a alt="Supprimer la liaison" title="Supprimer la liaison" style="cursor:pointer;" onclick="delete_line('.$fk_expeditiondet.',this,false);"><img src="img/supprimer.png" style="cursor:pointer;" /></a>';
			}
			else if($conf->global->MAIN_MODULE_ASSET){
				print '<td align="right">';
				_select_equipement($PDOdb,$product,$detailline,$fk_expeditiondet,$asset_lot,$i);
				print '</td>';
			}
			else{
				print '<td align="right">';
				print '<input type="text" style="width: 100px;" id="lot_'.$fk_expeditiondet.'_'.(($detailline->rang)? $detailline->rang : 1 ).'" name="lot_'.$fk_expeditiondet.'_'.(($detailline->rang)? $detailline->rang : 1 ).'" value="'.$detailline->lot.'">';
				print '</td>';
			}
			
			$PDOdb->Execute("SELECT poids FROM ".MAIN_DB_PREFIX."commandedet WHERE rowid = ".$line->fk_origin_line);
			$PDOdb->Get_line();
			
			print '<td align="center">';
			print		'<input style="width:50px;" type="text" id="weight_'.$fk_expeditiondet.'_'.($i+1).'" name="weight_'.$fk_expeditiondet.'_'.($i+1).'" value="'.(!empty($detailline->weight) ? $detailline->weight:$poidsAExpedierParFlacon).'">';
			print 		$form->select_measuring_units("weightunit_".$fk_expeditiondet.'_'.($i+1),"weight",$PDOdb->Get_field('poids'));
			print '</td>';
			print '<td align="center">';
			print		'<input style="width:50px;" type="text" id="weightreel_'.$fk_expeditiondet.'_'.($i+1).'" name="weightreel_'.$fk_expeditiondet.'_'.($i+1).'" value="'.(!empty($detailline->weight_reel) ? $detailline->weight_reel : $poidsAExpedierParFlacon).'">';
			print 		$form->select_measuring_units("weightreelunit_".$fk_expeditiondet.'_'.($i+1),"weight",$PDOdb->Get_field('poids'));
			print '</td>';
			print '<td align="center">';
			print		'<input style="width:50px;" type="text" id="tare_'.$fk_expeditiondet.'_'.($i+1).'" name="tare_'.$fk_expeditiondet.'_'.($i+1).'" value="'.$detailline->tare.'">';
			print 		$form->select_measuring_units("tareunit_".$fk_expeditiondet.'_'.($i+1),"weight",-3);
			print '</td>';
			if($i > 0)
				print '</tr>';
		}
	}
	print '</tr>';
	//actions
	print '<tr>';
	if($conf->global->MAIN_MODULE_ASSET)
		print '<td colspan="8" align="left"><span style="padding-left: 25px;">Ajouter un flacon d\'expédition:</span><a id="add_'.$fk_expeditiondet.'" alt="Lié un flacon suplémentaire" title="Lié un flacon suplémentaire" style="cursor:pointer;" onclick="add_line('.$fk_expeditiondet.','.$nbLines.');"><img src="img/ajouter.png" style="cursor:pointer;" /></a></td>';
	else
		print '<td colspan="8" align="left"><span style="padding-left: 25px;">Ajouter une ligne de détail:</span><a id="add_'.$fk_expeditiondet.'" alt="Lié un flacon suplémentaire" title="Lié un flacon suplémentaire" style="cursor:pointer;" onclick="add_line('.$fk_expeditiondet.','.$nbLines.');"><img src="img/ajouter.png" style="cursor:pointer;" /></a></td>';
	print '</tr>';
}

//Affichage en type view
function _view_expedition_line(&$PDOdb,&$product,&$line,&$TDispatchDetail,$nbLines){
	global $conf;
		
	
	if((int)$product->id == 0)
		$libelle = $line->description;
	else
		$libelle = $product->ref.' - '.$product->label;
	
	$poidsCommande = round(floatval($PDOdb->Get_field('tarif_poids') * $PDOdb->Get_field('qty')), 6);
	$poids = $PDOdb->Get_field('poids');
	if($conf->global->MAIN_MODULE_ASSET) $asset_lot = $PDOdb->Get_field('asset_lot');
	$poidsExpedie = round(floatval($TDispatchDetail->getPoidsExpedie($PDOdb,$line->rowid,$product)), 6);
	$poidsAExpedier = floatval($poidsCommande - $poidsExpedie);
	
	if($conf->global->MAIN_MODULE_ASSET){
		dol_include_once('/asset/class/asset.class.php');
		$ATMdb = new Tdb;
		$asset = new TAsset();
		$asset->load($ATMdb, $asset_lot);
	}
	
	print '<tr style="height:30px;">';
	print '<td rowspan="'.(($TDispatchDetail->nbLines > 0) ? $nbLines : 1).'">'.$libelle.' </td>';
	if($conf->asset->enabled) 
		print '<td rowspan="'.(($TDispatchDetail->nbLines > 0) ? $nbLines : 1).'" align="center">'.$asset->serial_number.'</td>';
	print '<td rowspan="'.(($TDispatchDetail->nbLines > 0) ? $nbLines : 1).'" align="center">'.$poidsCommande.' '.measuring_units_string($poids,"weight").'</td>';
	print '<td rowspan="'.(($TDispatchDetail->nbLines > 0) ? $nbLines : 1).'" align="center">'.$poidsExpedie.' '.measuring_units_string($poids,"weight").'</td>';
	print '<td rowspan="'.(($TDispatchDetail->nbLines > 0) ? $nbLines : 1).'" align="center">'.$poidsAExpedier.' '.measuring_units_string($poids,"weight").'</td>';
	
	if($TDispatchDetail->nbLines > 0){
		$cpt = 1;
		foreach($TDispatchDetail->lines as $detailline){
			if($cpt > 1)
				print '<tr style="height:30px;">';
			//chargement de l'équipement associé à la ligne
			$sql = "SELECT rowid, serial_number, lot_number, contenancereel_value, contenancereel_units
	 		    FROM ".MAIN_DB_PREFIX."asset
	 		    WHERE rowid = ".$detailline->fk_asset;
			$PDOdb->Execute($sql);
			$PDOdb->Get_line();
			
			if($conf->global->MAIN_MODULE_ASSET) 
				print '<td align="center">'.$PDOdb->Get_field('serial_number').' - Lot n° '.$PDOdb->Get_field('lot_number').' - '.floatval($PDOdb->Get_field('contenancereel_value')).' '.measuring_units_string($PDOdb->Get_field('contenancereel_units'),"weight").'</td>';
			else
				print '<td align="center">'.$detailline->lot.'</td>';
			print '<td align="center">'.floatval($detailline->weight).' '.measuring_units_string($detailline->weight_unit,"weight").'</td>';
			print '<td align="center">'.floatval($detailline->weight_reel).' '.measuring_units_string($detailline->weight_reel_unit,"weight").'</td>';
			print '<td align="center">'.floatval($detailline->tare).' '.measuring_units_string($detailline->tare_unit,"weight").'</td>';
			if($cpt > 1)
				print '</tr>';
			$cpt++;
		}
	}
	else{
		print '<td align="center"> - </td>';
		print '<td align="center"> - </td>';
		print '<td align="center"> - </td>';
		print '<td align="center"> - </td>';
	}
	print '</tr><tr class="impair"><td colspan="9">&nbsp</td></tr>';
}

function _select_equipement(&$PDOdb,&$product,&$line,$fk_expeditiondet,$asset_lot='',$i=0){
	
	print '<select id="equipement_'.$fk_expeditiondet.'_'.(($line->rang)? $line->rang : ($i+1) ).'" name="equipement_'.$fk_expeditiondet.'_'.(($line->rang)? $line->rang : ($i+1) ).'" class="equipement_'.$line->rowid.'">';
	
	//Chargement des équipement lié au produit
	$sql = "SELECT rowid, serial_number, lot_number, contenancereel_value, contenancereel_units, emplacement
	 		 FROM ".MAIN_DB_PREFIX."asset
	 		 WHERE fk_product = ".$product->id;
	// 13.10.28 - MKO : On autorise la sélection d'un flacon dans un autre lot lors de l'expé
	//if($asset_lot != '')
	//	$sql .= " AND lot_number = '".$asset_lot."'";
	$sql .= " ORDER BY contenance_value DESC";
	$PDOdb->Execute($sql);
	$defaultFlacon = !empty($line) ? $line->fk_asset : $asset_lot;
	
	$cpt = 0;
	while($PDOdb->Get_line()){
		if($PDOdb->Get_field('contenancereel_value') > 0){
			$cpt++;
			print '<option value="'.$PDOdb->Get_field('rowid').'" '.(($defaultFlacon == $PDOdb->Get_field('rowid')) ? 'selected="selected"' : "").'>';
			print $PDOdb->Get_field('serial_number');
			print " / Batch ".$PDOdb->Get_field('lot_number')." / Stock ".$PDOdb->Get_field('emplacement');
			print " / ".number_format($PDOdb->Get_field('contenancereel_value'),2,",","")." ".measuring_units_string($PDOdb->Get_field('contenancereel_units'),"weight");
			print '</option>';	
		}	
	}

	if($cpt == 0){
		print '<option value="null">Aucun flacon utilisable pour ce produit</option>';
	}
	print '</select>';
}

function combo($nom='modele',$defaut='', $entity=1) {
	/* Code combo pour sélection modèle */
	$TDocs = getListe($type, $entity);
	?>
	<select name="<?=$nom?>" id="<?=$nom?>" class="flat"><?
		
	foreach($TDocs as $fichier) {
		
		?><option value="<?=$fichier ?>" <?=($defaut==$fichier)?'selected="selected"':''?> extension="<?=_ext($fichier)?>"><?=$fichier ?></option><?
		
	}

	?></select><?
	
}

function getListe($entity=1) {
/* Liste des modèles valides */
	$Tab=array();
	
	if(is_dir(DOL_DOCUMENT_ROOT_ALT.'/dispatch/modele/'.$entity.'/')){
		if ($handle = opendir(DOL_DOCUMENT_ROOT_ALT.'/dispatch/modele/'.$entity.'/')) {
		    while (false !== ($entry = readdir($handle))) {
		    	if($entry[0]!='.' && validFile($entry))  $Tab[] = $entry;
		    }
		
		    closedir($handle);
		}
	}
	
	sort($Tab);
	
	return $Tab;
}

function _ext($file) {
/* extension d'un fichier */
	$ext = substr ($file, strrpos($file,'.'));
	return $ext;
}

function validFile($name) {
/* Fichier valid pour le traitement ? */
	$ext = _ext($name);
	
	if($ext=='.html') return TRUE;
	else { print "Type de fichier ($ext) non supporté ($name)."; return false; }
	
}

llxFooter();

?>
<div id="dialog" title="Impression Etiquette">
	<script type="text/javascript">
		$('#modele').change(function(){
			$.ajax({
				type: "POST"
				,url:'script/get_const.php'
				,dataType: "json"
				,data:{
					modele : $(this).val()
				}
			}).done(function(TConstantes){
				$('#margetop').val(TConstantes.margetop);
				$('#margeleft').val(TConstantes.margeleft);
			});
		});
	</script>
	<table>
		<tr>
			<td align="left">Position d&eacute;part : <input type="text" name="startpos" id="startpos" style="width:25px;" value="1"></td>
			<td align="left">
				Mod&egrave;le :
				<?php
				combo('modele',GETPOST('modele'), $conf->entity);
				?>
			</td>
			<td align="left">Nombre de copie : <input type="text" name="copie" id="copie" style="width:25px;" value="1"></td>
			<td align="left">Marge haute (mm) : <input type="text" name="margetop" id="margetop" style="width:25px;" value="<?php echo (dolibarr_get_const($db, 'ETIQUETTE_MARGE_TOP')) ? dolibarr_get_const($db, 'ETIQUETTE_MARGE_TOP') : 35;?>"></td>
			<td align="left">Marge gauche (mm) : <input type="text" name="margeleft" id="margeleft" style="width:25px;" value="<?php echo  (dolibarr_get_const($db, 'ETIQUETTE_MARGE_LEFT')) ? dolibarr_get_const($db, 'ETIQUETTE_MARGE_LEFT') : 34;?>"></td>
			<td align="center"><input type="button" value="G&eacute;n&eacute;rer" onclick="generer_etiquettes();" /></td>
		</tr>
		<tr>
			<td colspan="6">
				<iframe id="etiquettes" name="etiquettes" style="width:230mm;height: 500px;" src="">
		
				</iframe>
			</td>
		</tr>
	</table>
</div>
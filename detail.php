<?php
require('config.php');
require('class/dispatchdetail.class.php');

//Mise en page du tableau récap de l'expédition
include('detail_head.php');

$commande = $objectsrc; //récupération de l'onjet commande
$expedition = $object; //récupération de l'objet expedition
$expedition->fetch_lines();

?>
<script type="text/javascript">
	function add_line(id_ligne,rang){
		newrang = rang + 1;
		ligne = $('.line_'+id_ligne+'_1');
		
		//implémentation du conteur générale pour le traitement
		$('input[name=cptLigne]').val(parseInt($('input[name=cptLigne]').val()) + 1);
		
		//MAJ du rowspan pour la partie gauche de la ligne
		cpt = 0;
		$('tr.line_'+id_ligne+'_1 > td').each(function(){
			if(cpt <= 4)
				$(this).attr('rowspan',newrang);
			cpt = cpt + 1;
		});
		
		//clonage de la ligne et suppression des td en trop
		newligne = $(ligne).clone(true).insertAfter($(ligne));
		cpt = 0;
		$(newligne).find('> td').each(function(){
			if(cpt <= 4)
				$(this).remove();
			cpt = cpt + 1;
		});
		
		//MAJ des libelle de class, name, id des différents champs de la nouvelle ligne
		$(newligne).attr('class','line_'+id_ligne+'_'+newrang);
		$('#add_'+id_ligne).attr('onclick','add_line('+id_ligne+','+newrang+')');
		$(newligne).children().eq(0).prepend('<a alt="Supprimer la liaison" title="Supprimer la liaison" style="cursor:pointer;" onclick="delete_line('+id_ligne+',this);"><img src="img/supprimer.png" style="cursor:pointer;" /></a>');
		$(newligne).find('#equipement_'+id_ligne+'_'+rang).attr('id','equipement_'+id_ligne+'_'+newrang).attr('name','equipement_'+id_ligne+'_'+newrang);
		$(newligne).find('#weight_'+id_ligne+'_'+rang).attr('id','weight_'+id_ligne+'_'+newrang).attr('name','weight_'+id_ligne+'_'+newrang);
		$(newligne).find('select[name=weightunit_'+id_ligne+'_'+rang+']').attr('name','weightunit_'+id_ligne+'_'+newrang);
		$(newligne).find('#weightreel_'+id_ligne+'_'+rang).attr('id','weightreel_'+id_ligne+'_'+newrang).attr('name','weightreel_'+id_ligne+'_'+newrang);
		$(newligne).find('select[name=weightreelunit_'+id_ligne+'_'+rang+']').attr('name','weightreelunit_'+id_ligne+'_'+newrang);
		$(newligne).find('#tare_'+id_ligne+'_'+rang).attr('id','tare_'+id_ligne+'_'+newrang).attr('name','tare_'+id_ligne+'_'+newrang);
		$(newligne).find('select[name=tareunit_'+id_ligne+'_'+rang+']').attr('name','tareunit_'+id_ligne+'_'+newrang);
		
		$(newligne).find('>input').val('');
	}
	
	function delete_line(id_ligne,ligne,id_detail=0){
		
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
		
		if(id_detail != 0){
			$.ajax({
				type: "POST"
				,url:'script/ajax.delete_line.php'
				,data:{
					id_detail : id_detail
				}
			});
		}
	}
</script>
<?php
/*echo '<pre>';
print_r($line);
echo '</pre>'; exit;*/

//Boutons d'action
print '<div class="tabsAction">';
if($expedition->fk_statut != 1 && $_REQUEST['action'] != 'edit'){
	print '<a class="butAction" href="?id='.$expedition->id.'&action=edit">Modifier le détail</a>';
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
				if(!empty($val2['weightreel']) && !empty($val2['weight']) && !empty($val2['tare'])){
					$dispatchdetail = new TDispatchDetail();
					
					if(isset($val2['idexpeditiondetasset'])) $dispatchdetail->load($PDOdb, $val2['idexpeditiondetasset']);
					
					$dispatchdetail->fk_expeditiondet = $cle2;
					$dispatchdetail->fk_asset = $val2['equipement'];
					$dispatchdetail->rang = $cle;
					$dispatchdetail->weight = $val2['weight'];
					$dispatchdetail->weight_reel = $val2['weightreel']; 
					$dispatchdetail->tare = $val2['tare'];
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
	print '<table class="liste" width="100%">';
	print '	<tr class="liste_titre">';
	print '		<td style="width: 300px;">Produit</td>';
	print '		<td align="center" style="width: 100px;">Lot</td>';
	print '		<td align="center" style="width: 150px;">Poids commandé</td>';
	print '		<td align="center" style="width: 150px;">Poids expédié</td>';
	print '		<td align="center" style="width: 150px;">Poids à expédier</td>';
	print '		<td align="center">Flacon</td>';
	print '		<td align="center">Poids</td>';
	print '		<td align="center">Poids Réel</td>';
	print '		<td align="center">Tare</td>';
	print '	</tr>';
}

//Affiche la ligne d'expédition
function _print_expedition_line(&$PDOdb,&$expedition,&$line,&$TDispatchDetail,$fk_expeditiondet,$mode){
	global $db;
	
	$nbLines = ($TDispatchDetail->nbLines > 0) ? $TDispatchDetail->nbLines: 1;
	$PDOdb->Execute("SELECT fk_product, asset_lot, tarif_poids, poids FROM ".MAIN_DB_PREFIX."commandedet WHERE rowid = ".$line->fk_origin_line);
	$PDOdb->Get_line();
	
	$product = new Product($db);
	$product->fetch($PDOdb->Get_field('fk_product'));
	if($mode == 'edit')
		_form_expedition_line($PDOdb,$product,$line,$TDispatchDetail,$nbLines,$fk_expeditiondet);
	else if ($mode == 'view')
		_view_expedition_line($PDOdb,$product,$line,$TDispatchDetail,$nbLines);
}

//Affichage en type edition
function _form_expedition_line(&$PDOdb,&$product,&$line,&$TDispatchDetail,$nbLines,$fk_expeditiondet){
	global $db;
	
	$form = new FormProduct($db);
	
	print '<tr class="line_'.$fk_expeditiondet.'_'.(($line->rang)? $line->rang : 1 ).'">';
	print '<td rowspan="'.$nbLines.'">'.$product->ref.' - '.$product->label.'</td>';
	print '<td rowspan="'.$nbLines.'" align="center">'.$PDOdb->Get_field('asset_lot').'</td>';
	print '<td rowspan="'.$nbLines.'" align="center">'.floatval($PDOdb->Get_field('tarif_poids')).' '.measuring_units_string($PDOdb->Get_field('poids'),"weight").'</td>';
	print '<td rowspan="'.$nbLines.'" align="center">'.floatval($TDispatchDetail->getPoidsExpedie($PDOdb,$line->rowid)).' '.measuring_units_string($PDOdb->Get_field('poids'),"weight").'</td>';
	print '<td rowspan="'.$nbLines.'" align="center">'.floatval($PDOdb->Get_field('tarif_poids') - $TDispatchDetail->getPoidsExpedie($PDOdb,$line->rowid)).' '.measuring_units_string($PDOdb->Get_field('poids'),"weight").'</td>';
	if($TDispatchDetail->nbLines > 0){
		$cpt = 1;
		foreach($TDispatchDetail->lines as $detailline){
			if($cpt > 1){
				print '<tr style="height:30px;">';
				print '<td align="right">';
				print 		'<a alt="Supprimer la liaison" title="Supprimer la liaison" style="cursor:pointer;" onclick="delete_line('.$fk_expeditiondet.',this,'.$detailline->rowid.');"><img src="img/supprimer.png" style="cursor:pointer;" /></a>';
			}
			else
				print '<td align="right">';
				_select_equipement($PDOdb,$product,$detailline,$fk_expeditiondet);
			
			print '<input type="hidden" name="idexpeditiondetasset_'.$fk_expeditiondet.'_'.(($detailline->rang)? $detailline->rang : 1 ).'" value="'.$detailline->rowid.'">';
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
		print '<td align="right">';
			_select_equipement($PDOdb,$product,$detailline,$fk_expeditiondet);
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
	}
	print '</tr>';
	//actions
	print '<tr>';
	print '<td colspan="8" align="left"><span style="padding-left: 25px;">Ajouter un flacon d\'expédition:</span><a id="add_'.$fk_expeditiondet.'" alt="Lié un flacon suplémentaire" title="Lié un flacon suplémentaire" style="cursor:pointer;" onclick="add_line('.$fk_expeditiondet.','.$nbLines.');"><img src="img/ajouter.png" style="cursor:pointer;" /></a></td>';
	print '</tr>';
}

//Affichage en type view
function _view_expedition_line(&$PDOdb,&$product,&$line,&$TDispatchDetail,$nbLines){
	
	print '<tr style="height:30px;">';
	print '<td rowspan="'.$nbLines.'">'.$product->ref.' - '.$product->label.' </td>';
	print '<td rowspan="'.$nbLines.'" align="center">'.$PDOdb->Get_field('asset_lot').'</td>';
	print '<td rowspan="'.$nbLines.'" align="center">'.floatval($PDOdb->Get_field('tarif_poids')).' '.measuring_units_string($PDOdb->Get_field('poids'),"weight").'</td>';
	print '<td rowspan="'.$nbLines.'" align="center">'.floatval($TDispatchDetail->getPoidsExpedie($PDOdb,$line->rowid)).' '.measuring_units_string($PDOdb->Get_field('poids'),"weight").'</td>';
	print '<td rowspan="'.$nbLines.'" align="center">'.floatval($PDOdb->Get_field('tarif_poids') - $TDispatchDetail->getPoidsExpedie($PDOdb,$line->rowid)).' '.measuring_units_string($PDOdb->Get_field('poids'),"weight").'</td>';
	
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
			
			print '<td align="center">'.$PDOdb->Get_field('serial_number').' - Lot n° '.$PDOdb->Get_field('lot_number').' - '.floatval($PDOdb->Get_field('contenancereel_value')).' '.measuring_units_string($PDOdb->Get_field('contenancereel_units'),"weight").'</td>';
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

function _select_equipement(&$PDOdb,&$product,&$line,$fk_expeditiondet){
	
	print '<select id="equipement_'.$fk_expeditiondet.'_'.(($line->rang)? $line->rang : 1 ).'" name="equipement_'.$fk_expeditiondet.'_'.(($line->rang)? $line->rang : 1 ).'" class="equipement_'.$line->rowid.'">';
	
	//Chargement des équipement lié au produit
	$sql = "SELECT rowid, serial_number, lot_number, contenancereel_value, contenancereel_units
	 		 FROM ".MAIN_DB_PREFIX."asset
	 		 WHERE fk_product = ".$product->id."
	 		 ORDER BY contenance_value DESC";
	$PDOdb->Execute($sql);
	
	$cpt = 0;
	while($PDOdb->Get_line()){
		if($PDOdb->Get_field('contenancereel_value') > 0){
			$cpt++;
			print '<option value="'.$PDOdb->Get_field('rowid').'" '.(($line->fk_asset == $PDOdb->Get_field('rowid')) ? 'selected="selected"' : "").'>';
			print 		$PDOdb->Get_field('serial_number')." - Lot n° ".$PDOdb->Get_field('lot_number')." - ".number_format($PDOdb->Get_field('contenancereel_value'),2,",","")." ".measuring_units_string($PDOdb->Get_field('contenancereel_units'),"weight");
			print '</option>';	
		}	
	}

	if($cpt == 0){
		print '<option value="null">Aucun flacon utilisable pour ce produit</option>';
	}
	print '</select>';
}

llxFooter();
<?php
require('config.php');
require('class/dispatch.class.php');
require('class/dispatchdetail.class.php');

//Mise en page du tableau récap de l'expédition
include('detail_head.php');

$commande = $objectsrc; //récupération de l'onjet commande
$expedition = $object; //récupération de l'objet expedition

//Boutons d'action
print '<div class="tabsAction">';
if($expedition->fk_statut != 1){
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
				_form_add_lines($expedition,$commande);
				break;
				
			case 'save':
				_save_expedition_lines($PDOdb,$expedition,$commande,$_REQUEST);
				_fiche($PDOdb,$expedition,$commande);
				break;
			
			default :
				_fiche($PDOdb,$expedition,$commande);
		}
		
	}
	elseif(isset($_REQUEST['id'])) {
		_fiche($PDOdb,$expedition,$commande);
	}
}

//Affiche le détail des lignes d'expédition avec leur flacons
function _fiche(&$PDOdb,&$expedition, &$commande){
	
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
	
	if(count($expedition->lines) > 0){
		
		//Parcours des lignes d'expédition
		foreach($expedition->lines as $line){
			
			//Chargement des détails associé à la ligne
			$TDispatchDetail = new TDispatchDetail;
			$TDispatchDetail->loadLines($PDOdb, $line->rowid);
			
			_print_expedition_line($PDOdb,$line,$TDispatchDetail);
			
			//Parcours des détails associé à la ligne d'expédition
			foreach($TDispatchDetail->lines as $detailLine){
				_print_expedition_detailLine($PDOdb,$detailLine);
			}
		}
	}
	else{
		print '<tr><td colspan="9">Aucunes lignes d\'expéditions à afficher<td></tr>';
	}
	
	print '</table>';
}

//Parse le formulaire puis le traite
function _save_expedition_lines(&$expedition, &$commande, $request){
	
}

//Affiche la ligne d'expédition
function _print_expedition_line(&$PDOdb,&$line,&$TDispatchDetail){
	global $db;
	
	$PDOdb->Execute("SELECT fk_product, asset_lot, tarif_poids, poids FROM ".MAIN_DB_PREFIX."commandedet WHERE rowid = ".$line->fk_origin_line);
	$PDOdb->Get_line();
	
	$product = new Product($db);
	$product->fetch($PDOdb->Get_field('fk_product'));
	
	print '<tr class="impair">';
	print '<td>'.$product->ref.' - '.$product->label.'</td>';
	print '<td align="center">'.$PDOdb->Get_field('asset_lot').'</td>';
	print '<td align="center">'.floatval($PDOdb->Get_field('tarif_poids')).' '.measuring_units_string($PDOdb->Get_field('poids'),"weight").'</td>';
	print '<td align="center">'.floatval($TDispatchDetail->getPoidsExpedie($PDOdb,$line->rowid)).' '.measuring_units_string($PDOdb->Get_field('poids'),"weight").'</td>';
	print '<td align="center">'.floatval($PDOdb->Get_field('tarif_poids') - $TDispatchDetail->getPoidsExpedie($PDOdb,$line->rowid)).' '.measuring_units_string($PDOdb->Get_field('poids'),"weight").'</td>';
	print '<td align="center"> - </td>';
	print '<td align="center"> - </td>';
	print '<td align="center"> - </td>';
	print '<td align="center"> - </td>';
	print '</tr>';
}

function _print_expedition_detailLine(&$PDOdb,&$detailLine){
	
	$sql = "SELECT rowid, serial_number, lot_number, contenancereel_value, contenancereel_units
	 		 FROM ".MAIN_DB_PREFIX."asset
	 		 WHERE rowid = ".$detailLine->fk_asset;
	$PDOdb->Execute($sql);
	$PDOdb->Get_line();
	
	print '<tr class="pair">';
	print '<td align="center"> - </td>';
	print '<td align="center"> - </td>';
	print '<td align="center"> - </td>';
	print '<td align="center"> - </td>';
	print '<td align="center"> - </td>';
	print '<td align="center">'.$PDOdb->Get_field('serial_number').' - Lot n° '.$PDOdb->Get_field('lot_number').' - '.floatval($PDOdb->Get_field('contenancereel_value')).' '.measuring_units_string($PDOdb->Get_field('contenancereel_units'),"weight").'</td>';
	print '<td align="center">'.floatval($detailLine->weight).' '.measuring_units_string($detailLine->weight_unit,"weight").'</td>';
	print '<td align="center">'.floatval($detailLine->weight_reel).' '.measuring_units_string($detailLine->weight_reel_unit,"weight").'</td>';
	print '<td align="center">'.floatval($detailLine->tare).' '.measuring_units_string($detailLine->tare_unit,"weight").'</td>';
	print '</tr>';
}

llxFooter();
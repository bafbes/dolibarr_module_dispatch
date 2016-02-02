<?php
	
	require 'config.php';
	dol_include_once('/dispatch/class/dispatchasset.class.php');
	dol_include_once('/contrat/class/contrat.class.php');
	dol_include_once('/product/class/html.formproduct.class.php' );
	dol_include_once('/asset/class/asset.class.php');
	dol_include_once('/core/lib/product.lib.php');	
	
	$PDOdb=new TPDOdb;
	
	$type_object=GETPOST('type_object');
	$id = (int)GETPOST('id');
	
	$dispatch=new TDispatch;
	$dispatch->loadByObject($PDOdb,$id,$type_object);
	
	$action = GETPOST('action');
	
	switch ($action) {
		case 'save':
			
			$TLine = GETPOST('TLine');
			if(!empty($TLine[-1]['serial_number']) && !empty($TLine[-1]['fk_object'])) {
				
				$asset = new TAsset;
				$asset->loadReference($PDOdb, $TLine[-1]['serial_number']);
				
				if($asset->getId()>0) {
					$k=$dispatch->addChild($PDOdb, 'TDispatchAsset');
					$dispatch->TDispatchAsset[$k]->fk_asset = $asset->getId();
					$dispatch->TDispatchAsset[$k]->fk_object = $TLine[-1]['fk_object'];
					$dispatch->TDispatchAsset[$k]->object_type = $type_object;
					$dispatch->TDispatchAsset[$k]->asset = $asset;
					
					$dispatch->save($PDOdb);
				}
				
			}
				
			break;
		case 'delete-line':
			$k = (int)GETPOST('k');
			$dispatch->TDispatchAsset[$k]->to_delete=true;
			
			$dispatch->save($PDOdb);
			break;
	}
	
	_fiche($PDOdb,$dispatch);
	
function _fiche(&$PDOdb,&$dispatch) {
	global $db,$conf,$langs;
	
	llxHeader();
	
	$form=new TFormCore('auto','asset','post');
	echo $form->hidden('action', 'save');
	echo $form->hidden('id', $dispatch->fk_object);
	echo $form->hidden('type_object', $dispatch->type_object);
	
	$object = _header($dispatch->fk_object,$dispatch->type_object);
	$pListe[0] = "Sélectionnez une ligne";
	foreach($object->lines as $k=>&$line){
		$pListe[$line->id] = ($k+1).'/ '.$line->label;
	}
	
	
	print count($dispatch->TDispatchAsset).' équipement(s) lié(s)<br />';
	
	?>
	<table width="100%" class="border">
		<tr class="liste_titre">
			<td>Ligne concernée</td>
			<td>Equipement</td>
			<?php
				if(!empty($conf->global->USE_LOT_IN_OF)) {
				?><td>Numéro de Lot</td><?php
				}
			?>
			<td>DLUO</td>
			
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
	
	foreach($dispatch->TDispatchAsset as $k=>&$da) {
		
		if($da->to_delete) continue;
		
		$class= ($class == 'pair') ? 'impair' : 'pair';
		
		?><tr class="<?php echo $class ?>">
			<td><?php echo $pListe[$da->fk_object]; ?></td>
			<td><?php echo $da->asset->getNomUrl(1,0,1); ?></td>
			<td><?php echo $da->asset->lot_number; ?></td>
			<?php
				if(!empty($conf->global->USE_LOT_IN_OF)) {
					?><td><?php echo $da->asset->dluo ? dol_print_date($da->asset->dluo) : 'N/A'; ?></td><?php
				}
			?>
			
			
			
			<?php
			if($conf->global->clinomadic->enabled){
				?>
				<td>IMEI</td>
				<td>Firmware</td>
				<?php
			}
			?>
			<td><?php
			
					if($object->statut == 0 || $type_object == 'contrat') echo '<a href="?action=delete-line&k='.$k.'&id='.$object->id.'&type_object='.$dispatch->type_object.'">'.img_delete().'</a>';
						
			?></td>
		</tr>
		
		<?php
		
	}
	
	
	
	$formproduct=new FormProduct($db);
	if($object->statut == 0 || $type_object == 'contrat') {
		
	?><tr style="background-color: lightblue;">
			<td><?php echo $form->combo('', 'TLine[-1][fk_object]', $pListe, ''); ?></td>
			<td><?php echo $form->texte('','TLine[-1][serial_number]', '', 30); ?></td>
			<?php
				if(!empty($conf->global->USE_LOT_IN_OF)) {
					?><td>&nbsp;</td><?php
				}
			?>
			<td>&nbsp;</td>
			<?php
			if($conf->global->clinomadic->enabled){
				?>
				<td>&nbsp;</td>
				<td>&nbsp;</td>
				<?php
			}
			?>
			<td>Nouveau
			</td>
	</tr><?php
	
	}
	
	?>
	</table>
	<script type="text/javascript">
		$(document).ready(function() {
			    $( "input[name='TLine[-1][serial_number]']" ).autocomplete({
			      source: "<?php echo dol_buildpath('/dispatch/script/interface.php',1) ?>?get=serial_number",
			      minLength: 1,
			      select: function( event, ui ) {
			        
			      }
			    });
		});
		
			
		</script>
	<?php
	
	echo $form->btsubmit($langs->trans('Save'), 'bt_new');
	
	dol_fiche_end();
	
	$form->end();
	
	llxFooter();
	
	
}
function _header($id,$object_type) {
	global $db,$langs;
	
	if($object_type == 'contrat') {
		$object=new Contrat($db);
		$object->fetch($id);
		dol_include_once('/core/lib/contract.lib.php');
		$head = contract_prepare_head($object);
        dol_fiche_head($head, 'dispatch', $langs->trans("Contract"), 0, 'contract');
		
		
		
	}
	
	return $object;
	
}
	

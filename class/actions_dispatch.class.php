<?php
class ActionsDispatch
{ 
     /** Overloading the doActions function : replacing the parent's function with the one below 
      *  @param      parameters  meta datas of the hook (context, etc...) 
      *  @param      object             the object you want to process (an invoice if you are in invoice module, a propale in propale's module, etc...) 
      *  @param      action             current action (if set). Generally create or edit or null 
      *  @return       void 
      */
      
    function beforePDFCreation($parameters, &$object, &$action, $hookmanager) {
    	
		// pour implementation dans Dolibarr 3.7
		if (in_array('pdfgeneration',explode(':',$parameters['context']))) {
			
			define('INC_FROM_DOLIBARR',true);
			dol_include_once('/dispatch/config.php');
			dol_include_once('/asset/class/asset.class.php');
			dol_include_once('/dispatch/class/dispatchdetail.class.php');
			
			if(isset($parameters['object']) && get_class($object) == 'Expedition'){
				
				$PDOdb = new TPDOdb;
				
				foreach($object->lines as &$line){
					$sql = 'SELECT DISTINCT(lot_number),rowid FROM '.MAIN_DB_PREFIX.'expeditiondet_asset WHERE fk_expeditiondet = '.$line->line_id;
					$PDOdb->Execute($sql);
					
					$TRes = $PDOdb->Get_All();
					
					if(count($TRes)>0){
						$line->desc .= "<br>Lot expédié : ";
						foreach($TRes as $res){
							$dispatchDetail = new TDispatchDetail;
							$dispatchDetail->load($PDOdb, $res->rowid);
							
							$asset = new TAsset;
							$asset->load($PDOdb, $dispatchDetail->fk_asset);
							$asset->load_asset_type($PDOdb);
							
							$unite = (($asset->assetType->measuring_units == 'unit') ? 'unité(s)' : measuring_units_string($dispatchDetail->weight_reel_unit, $asset->assetType->measuring_units));

							$line->desc .= "<br>- ".$res->lot_number." x ".$dispatchDetail->weight_reel." ".$unite.' (DLUO : '.$asset->get_date('dluo').')';
						}	
					}
				}
			}
			
			//pre($object,true);exit;
		}
		
    }
	
}

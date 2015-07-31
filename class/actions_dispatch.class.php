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
			dol_include_once('/dispatch/class/dispatchdetail.class.php');
			
			if(isset($parameters['object']) && get_class($object) == 'Expedition'){
				
				$PDOdb = new TPDOdb;
				
				foreach($object->lines as &$line){
					$sql = 'SELECT DISTINCT(lot_number) FROM '.MAIN_DB_PREFIX.'expeditiondet_asset WHERE fk_expeditiondet = '.$line->line_id;
					$PDOdb->Execute($sql);
					$line->desc .= "<br>Lot expédié : ";
					while ($PDOdb->Get_line()) {
						$line->desc .= $PDOdb->Get_field('lot_number')." ";
					}
				}
			}
			
			//pre($object,true);exit;
		}
		
    }
	
}

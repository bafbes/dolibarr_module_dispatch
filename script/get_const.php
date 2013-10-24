<?php
require("../config.php");
include_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
global $db;

if(isset($_POST['modele'])){
	$modele = $_POST['modele'];
}
else
	return 0;

echo json_encode(array(
				'margeleft' => dolibarr_get_const($db, 'ETIQUETTE_MARGE_LEFT_'.$modele), 
				'margetop' => dolibarr_get_const($db, 'ETIQUETTE_MARGE_TOP_'.$modele)
			));
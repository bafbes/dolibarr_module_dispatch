<?php
require("../config.php");
require(DOL_DOCUMENT_ROOT."/custom/asset/class/asset.class.php");

if(isset($_POST['id_dispatchdet_asset'])){
	$id_dispatchdet_asset = $_POST['id_dispatchdet_asset'];
}
else
	return 0;

$ATMdb = new Tdb;

$sql = "DELETE FROM ".MAIN_DB_PREFIX."dispatchdet_asset WHERE rowid = ".$id_dispatchdet_asset;
$ATMdb->Execute($sql);

return 1;
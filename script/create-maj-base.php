<?php
/*
 * Script créant et vérifiant que les champs requis s'ajoutent bien
 * 
 */
 	define('INC_FROM_CRON_SCRIPT', true);
	
	require('../config.php');
	require('../class/dispatch.class.php');

	$ATMdb=new TPDOdb;
	$ATMdb->debug=true;

	$o=new TDispatch;
	$o->init_db_by_vars($ATMdb);
	
	$o=new TDispatchdet;
	$o->init_db_by_vars($ATMdb);
	
	$o=new TDispatchdet_asset;
	$o->init_db_by_vars($ATMdb);
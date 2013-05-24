<?php
/*
 * Script créant et vérifiant que les champs requis s'ajoutent bien
 * 
 */
 	define('INC_FROM_CRON_SCRIPT', true);
	
	require('../config.php');
	require('../class/expedition.class.php');

	$ATMdb=new TPDOdb;
	$ATMdb->debug=true;

	$o=new TExpedition;
	$o->init_db_by_vars($ATMdb);
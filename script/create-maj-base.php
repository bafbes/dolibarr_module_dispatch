<?php
/*
 * Script créant et vérifiant que les champs requis s'ajoutent bien
 * 
 */
 	dol_include_once('/dispatch/class/dispatchdetail.class.php');
 
 	if(!defined('INC_FROM_DOLIBARR')) {
        define('INC_FROM_CRON_SCRIPT', true);
        require('../config.php');
        $ATMdb=new TPDOdb;
        $ATMdb->debug=true;
    }
    else{
        $ATMdb=new TPDOdb;
    }
	
	//$ATMdb->debug=true;

	$o=new TDispatchDetail;
	$o->init_db_by_vars($ATMdb);

	$o=new TRecepDetail;
	$o->init_db_by_vars($ATMdb);

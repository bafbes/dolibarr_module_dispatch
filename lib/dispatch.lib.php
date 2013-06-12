<?php
/**
 *	\file       htdocs/core/lib/sendings.lib.php
 *	\ingroup    expedition
 *	\brief      Library for expedition module
 */
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/expedition/class/expedition.class.php';


/**
 * Prepare array with list of tabs
 *
 * @param   Object	$object		Object related to tabs
 * @return  array				Array of tabs to shoc
 */
function dispatch_prepare_head($object)
{
	global $langs, $conf, $user;

	$langs->load("sendings");
	$langs->load("deliveries");

	$h = 0;
	$head = array();

	$head[$h][0] = DOL_URL_ROOT."/dispatch/fiche.php?fk_commande=".$object->id;
	$head[$h][1] = $langs->trans("SendingCard");
	$head[$h][2] = 'shipping';
	$h++;

	// Show more tabs from modules
	// Entries must be declared in modules descriptor with line
    // $this->tabs = array('entity:+tabname:Title:@mymodule:/mymodule/mypage.php?id=__ID__');   to add new tab
    // $this->tabs = array('entity:-tabname);   												to remove a tab
	complete_head_from_modules($conf,$langs,$object,$head,$h,'delivery');

	complete_head_from_modules($conf,$langs,$object,$head,$h,'delivery','remove');

	return $head;
}
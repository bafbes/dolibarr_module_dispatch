<?php
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT.'/expedition/class/expedition.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/html.formproduct.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/product.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/sendings.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/modules/expedition/modules_expedition.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/doleditor.class.php';
if (! empty($conf->product->enabled) || ! empty($conf->service->enabled))  require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
if (! empty($conf->propal->enabled))   require_once DOL_DOCUMENT_ROOT.'/comm/propal/class/propal.class.php';
if (! empty($conf->commande->enabled)) require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
if (! empty($conf->stock->enabled))    require_once DOL_DOCUMENT_ROOT.'/product/stock/class/entrepot.class.php';

$langs->load("sendings");
$langs->load("companies");
$langs->load("bills");
$langs->load('deliveries');
$langs->load('orders');
$langs->load('stocks');
$langs->load('other');
$langs->load('propal');

$origin		= GETPOST('origin','alpha')?GETPOST('origin','alpha'):'expedition';   // Example: commande, propal
$origin_id 	= GETPOST('id','int')?GETPOST('id','int'):'';
if (empty($origin_id)) $origin_id  = GETPOST('origin_id','int');    // Id of order or propal
if (empty($origin_id)) $origin_id  = GETPOST('object_id','int');    // Id of order or propal
$id = $origin_id;
$ref=GETPOST('ref','alpha');

// Security check
$socid='';
if ($user->societe_id) $socid=$user->societe_id;
$result=restrictedArea($user, $origin, $origin_id);

$action		= GETPOST('action','alpha');

$object = new Expedition($db);
(GETPOST('id'))? $object->fetch(GETPOST('id')): "" ;

llxHeader('',$langs->trans('Sending'),'Expedition');

$form = new Form($db);
$formfile = new FormFile($db);
$formproduct = new FormProduct($db);
$product_static = new Product($db);

if ($action == 'setdate_livraison' && $user->rights->expedition->creer)
{
    //print "x ".$_POST['liv_month'].", ".$_POST['liv_day'].", ".$_POST['liv_year'];
    $datedelivery=dol_mktime(GETPOST('liv_hour','int'), GETPOST('liv_min','int'), 0, GETPOST('liv_month','int'), GETPOST('liv_day','int'), GETPOST('liv_year','int'));

    $object->fetch($id);
    $result=$object->set_date_livraison($user,$datedelivery);
    if ($result < 0)
    {
        $mesg='<div class="error">'.$object->error.'</div>';
    }
}

// Action update description of emailing
else if ($action == 'settrackingnumber' || $action == 'settrackingurl'
|| $action == 'settrueWeight'
|| $action == 'settrueWidth'
|| $action == 'settrueHeight'
|| $action == 'settrueDepth'
|| $action == 'setshipping_method_id')
{
    $error=0;

    $shipping = new Expedition($db);
    $result=$shipping->fetch($id);
    if ($result < 0) dol_print_error($db,$shipping->error);

    if ($action == 'settrackingnumber')			$shipping->tracking_number = trim(GETPOST('trackingnumber','alpha'));
    if ($action == 'settrackingurl')			$shipping->tracking_url = trim(GETPOST('trackingurl','int'));
    if ($action == 'settrueWeight')				$shipping->trueWeight = trim(GETPOST('trueWeight','int'));
    if ($action == 'settrueWidth')				$shipping->trueWidth = trim(GETPOST('trueWidth','int'));
    if ($action == 'settrueHeight')				$shipping->trueHeight = trim(GETPOST('trueHeight','int'));
    if ($action == 'settrueDepth')				$shipping->trueDepth = trim(GETPOST('trueDepth','int'));
    if ($action == 'setshipping_method_id')	$shipping->shipping_method_id = trim(GETPOST('shipping_method_id','int'));

    if (! $error)
    {
        if ($shipping->update($user) >= 0)
        {
            ?>
			<script type="text/javascript">
				window.location = 'detail.php?id=<?php echo $shipping->id;?>';
			</script>
			<?php
            exit;
        }
        setEventMessage($shipping->error,'errors');
    }

    $action="";
}

if (! empty($id) || ! empty($ref))
{
    $result = $object->fetch($id,$ref);
    if ($result < 0)
    {
        dol_print_error($db,$object->error);
        exit -1;
    }
    $lines = $object->lines;
    $num_prod = count($lines);

    if ($object->id > 0)
    {
        dol_htmloutput_mesg($mesg);

        if (!empty($object->origin))
        {
            $typeobject = $object->origin;
            $origin = $object->origin;
            $object->fetch_origin();
        }

        $soc = new Societe($db);
        $soc->fetch($object->socid);

        $head=shipping_prepare_head($object);
        dol_fiche_head($head, 'delivery', $langs->trans("Sending"), 0, 'sending');

        dol_htmloutput_mesg($mesg);

        // Calculate true totalWeight and totalVolume for all products
        // by adding weight and volume of each product line.
        $totalWeight = '';
        $totalVolume = '';
        $weightUnit=0;
        $volumeUnit=0;
        for ($i = 0 ; $i < $num_prod ; $i++)
        {
            $weightUnit=0;
            $volumeUnit=0;
            if (! empty($lines[$i]->weight_units)) $weightUnit = $lines[$i]->weight_units;
            if (! empty($lines[$i]->volume_units)) $volumeUnit = $lines[$i]->volume_units;

            // TODO Use a function addvalueunits(val1,unit1,val2,unit2)=>(val,unit)
            if ($lines[$i]->weight_units < 50)
            {
                $trueWeightUnit=pow(10,$weightUnit);
                $totalWeight += $lines[$i]->weight*$lines[$i]->qty_shipped*$trueWeightUnit;
            }
            else
            {
                $trueWeightUnit=$weightUnit;
                $totalWeight += $lines[$i]->weight*$lines[$i]->qty_shipped;
            }
            if ($lines[$i]->volume_units < 50)
            {
                //print $lines[$i]->volume."x".$lines[$i]->volume_units."x".($lines[$i]->volume_units < 50)."x".$volumeUnit;
                $trueVolumeUnit=pow(10,$volumeUnit);
                //print $lines[$i]->volume;
                $totalVolume += $lines[$i]->volume*$lines[$i]->qty_shipped*$trueVolumeUnit;
            }
            else
            {
                $trueVolumeUnit=$volumeUnit;
                $totalVolume += $lines[$i]->volume*$lines[$i]->qty_shipped;
            }
        }

        print '<table class="border" width="100%">';

        $linkback = '<a href="'.DOL_URL_ROOT.'/expedition/liste.php">'.$langs->trans("BackToList").'</a>';

        // Ref
        print '<tr><td width="20%">'.$langs->trans("Ref").'</td>';
        print '<td colspan="3">';
        print $form->showrefnav($object, 'ref', $linkback, 1, 'ref', 'ref');
        print '</td></tr>';

        // Customer
        print '<tr><td width="20%">'.$langs->trans("Customer").'</td>';
        print '<td colspan="3">'.$soc->getNomUrl(1).'</td>';
        print "</tr>";

        // Linked documents
        if ($typeobject == 'commande' && $object->$typeobject->id && ! empty($conf->commande->enabled))
        {
            print '<tr><td>';
            $objectsrc=new Commande($db);
            $objectsrc->fetch($object->$typeobject->id);
            print $langs->trans("RefOrder").'</td>';
            print '<td colspan="3">';
            print $objectsrc->getNomUrl(1,'commande');
            print "</td>\n";
            print '</tr>';
        }
        if ($typeobject == 'propal' && $object->$typeobject->id && ! empty($conf->propal->enabled))
        {
            print '<tr><td>';
            $objectsrc=new Propal($db);
            $objectsrc->fetch($object->$typeobject->id);
            print $langs->trans("RefProposal").'</td>';
            print '<td colspan="3">';
            print $objectsrc->getNomUrl(1,'expedition');
            print "</td>\n";
            print '</tr>';
        }

        // Ref customer
        print '<tr><td>'.$langs->trans("RefCustomer").'</td>';
        print '<td colspan="3">'.$object->ref_customer."</a></td>\n";
        print '</tr>';

        // Date creation
        print '<tr><td>'.$langs->trans("DateCreation").'</td>';
        print '<td colspan="3">'.dol_print_date($object->date_creation,"day")."</td>\n";
        print '</tr>';

        // Delivery date planed
        print '<tr><td height="10">';
        print '<table class="nobordernopadding" width="100%"><tr><td>';
        print $langs->trans('DateDeliveryPlanned');
        print '</td>';
		
        print '</tr></table>';
        print '</td><td colspan="2">';
		print $object->date_delivery ? dol_print_date($object->date_delivery,'dayhourtext') : '&nbsp;';
        print '</td>';
        print '</tr>';

        // Weight
        print '<tr><td>'.$form->editfieldkey("Weight",'trueWeight',$object->trueWeight,$object,$user->rights->expedition->creer).'</td><td colspan="3">';
        print $form->editfieldval("Weight",'trueWeight',$object->trueWeight,$object,$user->rights->expedition->creer);
        print ($object->trueWeight && $object->weight_units!='')?' '.measuring_units_string($object->weight_units,"weight"):'';
        print '</td></tr>';

        // Width
        print '<tr><td>'.$form->editfieldkey("Width",'trueWidth',$object->trueWidth,$object,$user->rights->expedition->creer).'</td><td colspan="3">';
        print $form->editfieldval("Width",'trueWidth',$object->trueWidth,$object,$user->rights->expedition->creer);
        print ($object->trueWidth && $object->width_units!='')?' '.measuring_units_string($object->width_units,"size"):'';
        print '</td></tr>';

        // Height
        print '<tr><td>'.$form->editfieldkey("Height",'trueHeight',$object->trueHeight,$object,$user->rights->expedition->creer).'</td><td colspan="3">';
        print $form->editfieldval("Height",'trueHeight',$object->trueHeight,$object,$user->rights->expedition->creer);
        print ($object->trueHeight && $object->height_units!='')?' '.measuring_units_string($object->height_units,"size"):'';
        print '</td></tr>';

        // Depth
        print '<tr><td>'.$form->editfieldkey("Depth",'trueDepth',$object->trueDepth,$object,$user->rights->expedition->creer).'</td><td colspan="3">';
        print $form->editfieldval("Depth",'trueDepth',$object->trueDepth,$object,$user->rights->expedition->creer);
        print ($object->trueDepth && $object->depth_units!='')?' '.measuring_units_string($object->depth_units,"size"):'';
        print '</td></tr>';

        // Volume
        print '<tr><td>';
        print $langs->trans("Volume");
        print '</td>';
        print '<td colspan="3">';
        $calculatedVolume=0;
        if ($object->trueWidth && $object->trueHeight && $object->trueDepth) $calculatedVolume=($object->trueWidth * $object->trueHeight * $object->trueDepth);
        // If sending volume not defined we use sum of products
        if ($calculatedVolume > 0)
		{
			print $calculatedVolume.' ';
	        if ($volumeUnit < 50) print measuring_units_string(0,"volume");
    	    else print measuring_units_string($volumeUnit,"volume");
        }
        if ($totalVolume > 0)
        {
        	if ($calculatedVolume) print ' ('.$langs->trans("SumOfProductVolumes").': ';
			print $totalVolume;
        	if ($calculatedVolume) print ')';
        }
        print "</td>\n";
        print '</tr>';

        // Status
        print '<tr><td>'.$langs->trans("Status").'</td>';
        print '<td colspan="3">'.$object->getLibStatut(4)."</td>\n";
        print '</tr>';

        // Sending method
        print '<tr><td height="10">';
        print '<table class="nobordernopadding" width="100%"><tr><td>';
        print $langs->trans('SendingMethod');
        print '</td>';

        if ($action != 'editshipping_method_id') print '<td align="right"><a href="'.$_SERVER["PHP_SELF"].'?action=editshipping_method_id&amp;id='.$object->id.'">'.img_edit($langs->trans('SetSendingMethod'),1).'</a></td>';
        print '</tr></table>';
        print '</td><td colspan="2">';
        if ($action == 'editshipping_method_id')
        {
            print '<form name="setshipping_method_id" action="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'" method="post">';
            print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
            print '<input type="hidden" name="action" value="setshipping_method_id">';
            $object->fetch_delivery_methods();
            print $form->selectarray("shipping_method_id",$object->meths,$object->shipping_method_id,1,0,0,"",1);
            if ($user->admin) print info_admin($langs->trans("YouCanChangeValuesForThisListFromDictionnarySetup"),1);
            print '<input type="submit" class="button" value="'.$langs->trans('Modify').'">';
            print '</form>';
        }
        else
        {
            if ($object->shipping_method_id > 0)
            {
                // Get code using getLabelFromKey
                $code=$langs->getLabelFromKey($db,$object->shipping_method_id,'c_shipment_mode','rowid','code');
                print $langs->trans("SendingMethod".strtoupper($code));
            }
        }
        print '</td>';
        print '</tr>';

        // Tracking Number
        print '<tr><td>'.$form->editfieldkey("TrackingNumber",'trackingnumber',$object->tracking_number,$object,$user->rights->expedition->creer).'</td><td colspan="3">';
        print $form->editfieldval("TrackingNumber",'trackingnumber',$object->tracking_url,$object,$user->rights->expedition->creer,'string',$object->tracking_number);
        print '</td></tr>';

        // Other attributes
        $parameters=array('colspan' => ' colspan="3"');
        $reshook=$hookmanager->executeHooks('formObjectOptions',$parameters,$object,$action);    // Note that $action and $object may have been modified by hook

        print "</table>\n";
		

    }
}

print "</table>\n";

print "\n</div>\n";
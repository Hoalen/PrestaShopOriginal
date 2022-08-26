<?php
	// Oratio(R) Copyright
	// Copyright (c) 2010 by Omega Centauri sarl (France)
	// Autor : Boiron Jacky
	// XML Parser for synchronise Oratio(R)  and Prestashop(R) 
	// For the Prestashop version : 1.6.0

error_reporting(E_ALL);
ini_set('display_errors', 1);
	// gestion antislash si magic_quotes_gpc active
	if (get_magic_quotes_gpc()){
        $_GET = array_map('stripslashes', $_GET);
        $_POST = array_map('stripslashes', $_POST);
        $_COOKIE = array_map('stripslashes', $_COOKIE);
    }
	
	define('PS_ADMIN_DIR', getcwd());
	
	include(PS_ADMIN_DIR.'/../config/config.inc.php');
	include(PS_ADMIN_DIR.'/functions.php');
	// this is the file contain the account login to connect on the back office
	include(PS_ADMIN_DIR.'/../config/account_oratio.inc.php');

	// Define Port product for synchro with Oratio
	define('ORATIO_PORT_SERVICE', 'PORT');
	define('ORATIO_PORT_BARCODE', 100047600000);
	
	// Define Port product for synchro with Oratio
	define('ORATIO_DISCOUNT_SERVICE', 'COUPON');
	define('ORATIO_DISCOUNT_BARCODE', 100099800000);
	
	// Define Oratio cd_vat 
	define('ORATIO_CDVAT_196', 1);
	define('ORATIO_CDVAT_55', 3);
	if (isset($_GET['shopid']) && $_GET['shopid'] == 3) {
        define('ORATIO_CDVAT_0', 20);
        define('PRESTA_ORDER_RETURN_STATES', "(3)");
        define('PRESTA_SHOP_ID', 3);
    }
    else {
        define('PRESTA_ORDER_RETURN_STATES', "(3)");
        define('PRESTA_SHOP_ID', 1);
	    define('ORATIO_CDVAT_0', 22);
    }
	define('ORATIO_CDVAT_20', 5);
	
	define ('ACTION_CREATE', "creer_retour");
	define ('ACTION_READ', "retour_attente");
	define ('ACTION_READ_LIST', "liste_retour_attente");
	define ('UPDATE_ORDER_RETURN', "update_order_return");
	
	// Parameters received from Oratio
	//$Order_returnId = $_GET['Order_returnId'];
	//$newOrder_returnStatusId = $_GET['NewStatus'];
	//$packnumber = $_GET['packnumber'];
	//$id_carrier = $_GET['id_carrier'];
	$action = (isset($HTTP_GET_VARS['action']) ? $HTTP_GET_VARS['action'] : $_GET['action']);
	
	function connexion() {
		$email = trim(_ADMIN_USER_ACCOUNT_LOGIN_);
		$passwd = trim(_ADMIN_USER_ACCOUNT_PASSWORD_);
		$employee = new Employee();
		$employee = $employee->getByemail($email, $passwd, false);
		return $employee;
	}
	
	function update_order_return(){
		$connexion = connexion();
		$Order_returnId = $_GET['Order_returnId'];
		$newOrder_returnStatusId = $_GET['NewStatus'];
		$packnumber = $_GET['packnumber'];
		$id_carrier = $_GET['id_carrier'];
		if ($Order_returnId){
			$order_return = new OrderReturn($Order_returnId);
			$order_return->state = $newOrder_returnStatusId;
			$order_return->update();
		}
	}
	
	function replace_carac($chaine) {
		//$mauvais = array('"', '&', 'œ', '®', 'ß', '&#225;');
		//$bon   = array('\'', '&#38;', '&#156;', '&#174;', '&szlig;', '&szlig;');
		$mauvais = array('"', '&', 'œ', '®');
		$bon   = array('\'', '&#38;', '&#156;', '&#174;');
		
		$texte = str_replace( $mauvais, $bon, $chaine );
		
		return $texte;
	}
	
	function replace_carac_product($chaine) {
		$mauvais = array("&");
		$bon   = array("and");
		
		$texte = str_replace( $mauvais, $bon, $chaine );
		
		return $texte;
	}
	
	switch ($action) {
		case ACTION_READ:
			
			$connexion = connexion();
			$token = Tools::getAdminToken('AdminOrder'.(int)(Tab::getIdFromClassName('AdminOrder')).intval($connexion->id));
			
			$sql = "SELECT a.id_order_return AS id,
            COALESCE(a.bl_oratio, 0) AS bl,
			CONCAT(LEFT(c.firstname, 1), '. ', c.lastname) AS customer,
			(select name from "._DB_PREFIX_."country_lang where id_lang = 2 and id_country = (select id_country from "._DB_PREFIX_."address where id_address = a.id_address_delivery)) AS country,
			osl.name AS osname, 
			os.color, date(a.date_add) AS date,
			a.payment,
(select coalesce(id_group,0) from "._DB_PREFIX_."customer_group cg where cg.id_customer = a.id_customer and id_group = 3) as ambassadeur,
(select coalesce(id_group,0) from "._DB_PREFIX_."customer_group cg where cg.id_customer = a.id_customer and id_group = 6) as salarie,
			a.total_paid_tax_incl AS totalTTC,
			IF((SELECT COUNT(so.id_order_return) FROM "._DB_PREFIX_."order_return so WHERE so.id_customer = a.id_customer AND so.valid = 1) > 1, 0, 1) as new, 
			(SELECT COUNT(od.id_order_return) FROM "._DB_PREFIX_."order_return_detail od WHERE od.id_order_return = a.id_order_return GROUP BY id_order_return) AS product_number,
			(select m.message from "._DB_PREFIX_."message m where m.id_order_return = a.id_order_return and m.private = 0 and m.id_message = (select max(mm.id_message) from "._DB_PREFIX_."message mm where mm.id_order_return = a.id_order_return and mm.private = 0)) AS message
			FROM "._DB_PREFIX_."order_return a
			LEFT JOIN "._DB_PREFIX_."customer c ON c.id_customer = a.id_customer
			LEFT JOIN "._DB_PREFIX_."order_return_history oh ON oh.id_order_return = a.id_order_return
			LEFT JOIN "._DB_PREFIX_."order_return_state os ON os.id_order_return_state = oh.id_order_return_state
			LEFT JOIN "._DB_PREFIX_."order_return_state_lang osl ON os.id_order_return_state = osl.id_order_return_state
			where
			osl.id_lang = 2 AND a.id_shop = ".PRESTA_SHOP_ID."
			AND os.id_order_return_state IN ".PRESTA_ORDER_RETURN_STATES."
			AND oh.id_order_return_history = (SELECT MAX(id_order_return_history) FROM "._DB_PREFIX_."order_return_history WHERE id_order_return = a.id_order_return)";
			

            $res = Db::getInstance()->executeS($sql, false);
			// On fait une boucle pour lister tout ce que contient la table :
			$result = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>";
			$result .="<order_returns>";
            while ($donnees = Db::getInstance()->nextRow($res)) {
//			    error_log(json_encode($donnees));
			    if ($donnees['bl'] > 0) $donnees['id'] .= '_' . $donnees['bl'];
			    if ($donnees['payment'] == "Dotation") {
			        $donnees['id'] .= '_' . $donnees['payment'];
                    if ($donnees['ambassadeur'] == 3) {
                        $donnees['id'] .= 'Ambassadeur';
                    }
                    else if ($donnees['salarie'] == 6) {
                        $donnees['id'] .= 'Salarie';
                    }
                }
				$result .= 		"<order_return>";
				$result .= 			"<customer>" .utf8_encode($donnees['customer']). "</customer>";
				$result .= 			"<country>" .$donnees['country']. "</country>";
				$result .= 			"<amount>" .$donnees['totalTTC']. "</amount>";
				$result .= 			"<date>" .$donnees['date']. "</date>";
				$result .= 			"<payment>" .utf8_encode($donnees['payment']). "</payment>";
				$result .= 			"<status>" .utf8_encode($donnees['osname']). "</status>";
				$result .= 			"<message_client>" .utf8_encode(str_replace(CHR(13).CHR(10)," ; ",replace_carac($donnees['message']))). "</message_client>";
				$result .= 			"<id>" .$donnees['id']. "</id>";
				$result .= 			"<token>" .$token. "</token>";
//				$result .= 			"<bl>" .$donnees['bl']. "</bl>";
				$result .= 		"</order_return>";
			}
			
			
			
			$result .="</order_returns>";
//            error_log($result);
			echo $result;
			break;
			exit;
		
		case ACTION_READ_LIST:
			
			$connexion = connexion();
			$token = Tools::getAdminToken('AdminOrder'.(int)(Tab::getIdFromClassName('AdminOrder')).intval($connexion->id));

			$result = "";
			$i = 1;

            $sql = "select a.id_order_return AS id
			FROM "._DB_PREFIX_."order_return a
			LEFT JOIN "._DB_PREFIX_."order_return_history oh ON oh.id_order_return = a.id_order_return
			LEFT JOIN "._DB_PREFIX_."order_return_state os ON os.id_order_return_state = oh.id_order_return_state
			LEFT JOIN "._DB_PREFIX_."order_return_state_lang osl ON os.id_order_return_state = osl.id_order_return_state
			where
			osl.id_lang = 2 AND a.id_shop = ".PRESTA_SHOP_ID."
			AND os.id_order_return_state IN ".PRESTA_ORDER_RETURN_STATES."
			AND oh.id_order_return_history = (SELECT MAX(id_order_return_history) FROM "._DB_PREFIX_."order_return_history WHERE id_order_return = a.id_order_return)";


            $res = Db::getInstance()->executeS($sql, false);
            while ($donnees = Db::getInstance()->nextRow($res)) {
				if($i == 1){
					$result .= $donnees['id'];
				}
				else{
					$result .= ",".$donnees['id'];
				}
				$i++;
			}
			echo $result;
			break;
			exit;
		
		case ACTION_CREATE:
			
			$connexion = connexion();
			$token = Tools::getAdminToken('AdminOrder'.(int)(Tab::getIdFromClassName('AdminOrder')).intval($connexion->id));
			
			$Order_returnId = $_GET['Order_returnId'];
			$cart_rules = $_GET['cart_rules'];
			//$cart_rules = utf8_encode($cart_rules);
			$cart_rules_list = json_decode($cart_rules, true);
			
			// cart rules condition for duedate //
			$count_condition_duedate = 0;
			$where_duedate = "AND ( ";
			$where_not_duedate = "";
			
			foreach ($cart_rules_list as $num => $data_duedate) {
				$condition = "";
				if($data_duedate['filter'] == 'containing'){
					$condition = '%'.$data_duedate['cart_rule'].'%';
				}
				elseif($data_duedate['filter'] == 'starts_with'){
					$condition = '%'.$data_duedate['cart_rule'];
				}
				else{
					$condition = $data_duedate['cart_rule'];
				}
				
				if($num == 0){
					$where_duedate .= "lower(crl.name) like lower('".$condition."') ";
				}
				else{
					$where_duedate .= "or lower(crl.name) like lower('".$condition."') ";
				}
				
				$where_not_duedate .= " AND lower(crl.name) not like lower('".$condition."') ";
				
				$count_condition_duedate++;
			}
			
			$where_duedate .= " )";
			
			if(!$count_condition_duedate){
				$where_duedate = "";
			}
			// end cart rules condition for duedate //
			
			//TODO : get tax rate for total_discounts and calculate vatamount
			////(pso.total_shipping / 1.196) as total_shipping_ht
			
			$reponse =  "SELECT	pso.id_order_return,
						DATE(pso.date_add) AS dateretour,
						ca.id_reference as id_carrier, ca.name as carrier_name,
						pso.total_paid_real AS totalTTC,
						pso.total_paid_real - (( pso.total_products + (pso.total_shipping / ((pso.carrier_tax_rate / 100) + 1))) - (pso.total_discounts / 1.2)) AS vatamount,
						pso.id_address_delivery,
						pso.id_address_invoice,
						CONCAT(c.firstname, ' ', c.lastname) as name,
						c.id_gender as gender,
						c.email,
						c.birthday,
						pso.total_shipping,
						( pso.total_shipping / (((CASE WHEN pso.carrier_tax_rate IS NULL THEN 0 ELSE pso.carrier_tax_rate END) / 100) + 1) ) as total_shipping_ht,
						pso.carrier_tax_rate as shipping_rate
						FROM "._DB_PREFIX_."order_return pso
						LEFT OUTER JOIN "._DB_PREFIX_."customer c on c.id_customer = pso.id_customer
						LEFT OUTER JOIN ps_carrier ca on ca.id_carrier = pso.id_carrier
						WHERE pso.id_order_return = '$Order_returnId';";
			
			// On fait une boucle pour lister tout ce que contient la table :
			
			$result = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>";
			$result .="<documents>";

            $res = Db::getInstance()->executeS($reponse, false);
            while ($order_return = Db::getInstance()->nextRow($res)) {

				$result .=	"<document reference = \"".$order_return['id_order_return']."\" reqdate=\"".$order_return['dateretour']."\" transdate=\"".$order_return['dateretour']."\" presta_carrier_name=\"".$order_return['carrier_name']."\" presta_id_carrier=\"".$order_return['id_carrier']."\" >";
				$result .=	"<contact name=\"".replace_carac($order_return['name'])."\" gender=\"".$order_return['gender']."\" email_address=\"".$order_return['email']."\" birthdate=\"".$order_return['birthday']."\" />";
				$result .=	"<amount>";
				$result .=	"<reductedamountwithvat>" .$order_return['totalTTC']. "</reductedamountwithvat>";
				$result .=	"<vatamount>" .$order_return['vatamount']. "</vatamount>";
				$result .=	"</amount>";
				

				$reponse2 =  "SELECT
								psocr.id_cart_rule AS coupon_id, 
								crl.name AS coupon_name,
								-round((psocr.value / (1 + ((CASE WHEN pso.total_paid_tax_excl = pso.total_paid_tax_incl THEN 0.00 ELSE 20.00 END) / 100))),4) AS netamount,
								-psocr.value as amount
							FROM "._DB_PREFIX_."order_return_cart_rule psocr
							LEFT JOIN "._DB_PREFIX_."cart_rule_lang crl ON crl.id_cart_rule = psocr.id_cart_rule
							LEFT JOIN "._DB_PREFIX_."order_return pso ON pso.id_order_return = psocr.id_order_return
							WHERE crl.id_lang = 2
							".$where_not_duedate."
							AND psocr.id_order_return = ".$order_return['id_order_return'].";";
							
				
				$resultdiscount = '';

                $res2 = Db::getInstance()->executeS($reponse2, false);
                while ($discount = Db::getInstance()->nextRow($res2)) {
					
					$cd_vat = ORATIO_CDVAT_20;
					if($discount['amount'] == $discount['netamount']){
						$cd_vat = ORATIO_CDVAT_0;
					}
					else{
						$cd_vat = ORATIO_CDVAT_20;
					}
					
					$resultdiscount .= "<commandline id=\"\" ";
					$resultdiscount .= 'partnumber="'.ORATIO_DISCOUNT_SERVICE.'" ';
					$resultdiscount .= 'product_cd_vat="'.$cd_vat.'" ';
					$resultdiscount .= 'description="Coupon de reduction : '.$discount['coupon_name'].'" ';
					$resultdiscount .= 'sellprice="'.$discount['netamount'].'" >';
					$resultdiscount .= '<amounts  ';
					$resultdiscount .= ' amount="'.$discount['amount'].'" netamount="'.$discount['netamount'].'" />';
					$resultdiscount .= '<nodes>';
					$resultdiscount .= '<node qty="1" barcode="'.ORATIO_DISCOUNT_BARCODE.'" >';
					$resultdiscount .= "</node>";
					$resultdiscount .= "</nodes>";
					$resultdiscount .= "</commandline>";
				}
				
				
				/*  ligne de produit  */
				// **********************
				
				$reponse3 =
							"SELECT	DISTINCT pr.id_product AS id,
									pr.reference AS partnumber,
									pr.`condition`,
									(CASE WHEN tva.rate IS NULL THEN 0.00 ELSE tva.rate END) AS vat_rate,
									pr.price AS sellprice,
									od.product_name AS name,
									(od.product_price * ((CASE WHEN tva.rate IS NULL THEN 0.00 ELSE tva.rate END) /100)) - ((od.reduction_amount / (1 + (CASE WHEN tva.rate IS NULL THEN 0.00 ELSE tva.rate END) /100)) * ((CASE WHEN tva.rate IS NULL THEN 0.00 ELSE tva.rate END) /100)) AS taxamount,
									(od.product_price * (1 + (CASE WHEN tva.rate IS NULL THEN 0.00 ELSE tva.rate END) /100)) - od.reduction_amount AS amount,
									od.product_price - (od.reduction_amount / (1 + (CASE WHEN tva.rate IS NULL THEN 0.00 ELSE tva.rate END) /100)) AS netamount,
									od.reduction_percent,
									od.id_order_return_detail
									FROM "._DB_PREFIX_."order_return_detail od
									LEFT JOIN "._DB_PREFIX_."order_return pso ON od.id_order_return = pso.id_order_return
									LEFT JOIN "._DB_PREFIX_."product pr ON pr.id_product = od.product_id
									LEFT JOIN "._DB_PREFIX_."product_lang prl ON pr.id_product = prl.id_product
									LEFT JOIN "._DB_PREFIX_."order_return_detail_tax odt ON od.id_order_return_detail = odt.id_order_return_detail
									LEFT JOIN "._DB_PREFIX_."tax tva ON odt.id_tax = tva.id_tax
									WHERE prl.id_lang = 2
									AND pso.id_order_return = ".$order_return['id_order_return']."
									ORDER_RETURN BY od.id_order_return_detail;";
				$result .=	"<commandlines>";
                $res3 = Db::getInstance()->executeS($reponse3, false);
                while ($comline = Db::getInstance()->nextRow($res3)) {
					
					//$cd_vat = ORATIO_CDVAT_196;
					$cd_vat = ORATIO_CDVAT_20;
					
					if($comline['vat_rate'] == 0 || $comline['vat_rate'] == 0.000){
						$cd_vat = ORATIO_CDVAT_0;
					}
					elseif ($comline['vat_rate'] == 5.5 || $comline['vat_rate'] == 5.500){
						$cd_vat = ORATIO_CDVAT_55;
					}
					else{
						//$cd_vat = ORATIO_CDVAT_196;
						$cd_vat = ORATIO_CDVAT_20;
					}
					
					$result .=	"<commandline id=\"".$comline['id']."\" partnumber=\"".$comline['partnumber']."\" condition=\"".$comline['condition']."\" product_cd_vat=\"".$cd_vat."\" sellprice=\"".$comline['sellprice']."\">";
					$result .=	"<name>".replace_carac_product($comline['name'])."</name>";
					$result .=	"<amounts taxamount=\"".$comline['taxamount']."\" amount=\"".$comline['amount']."\" netamount=\"".$comline['netamount']."\" discount=\"".$comline['reduction_percent']."\" />";
					
					/*  declinaisons  */
					// ******************
					/* Affiche qty, code bare et calcul */ /* le nombre de declinaisons choisis */	/* par le client pour ce produit */	
					$reponse4 = "SELECT	psodet.product_quantity AS qty,
								CASE WHEN psodet.product_attribute_id = 0 or psodet.product_id = 688 THEN
									(select ean13 from "._DB_PREFIX_."product where id_product = psodet.product_id)
								ELSE
									(SELECT pa.ean13 FROM "._DB_PREFIX_."product_attribute pa WHERE psodet.product_id = pa.id_product AND pa.id_product_attribute = psodet.product_attribute_id)
								END	AS barcode,
								(select p.name from "._DB_PREFIX_."product_lang p where p.id_product = psodet.product_id and p.id_lang = 2 and p.`id_shop`= psodet.`id_shop`) as product_name
								FROM "._DB_PREFIX_."order_return_detail psodet
								WHERE psodet.id_order_return = ".$order_return['id_order_return']."
								AND psodet.id_order_return_detail = ".$comline['id_order_return_detail'].";";
								$result .=	"<nodes>";
                    $res4 = Db::getInstance()->executeS($reponse4, false);
                    while ($node = Db::getInstance()->nextRow($res4)) {
						$result .=	"<node qty=\"".$node['qty']."\" barcode=\"".substr($node['barcode'], 0, 12)."\">";
						$ssreponse = "SELECT	CONCAT(agl.public_name, ' ', al.name) AS description
												FROM "._DB_PREFIX_."attribute_lang al
												LEFT JOIN "._DB_PREFIX_."product_attribute_combination pac ON al.id_attribute = pac.id_attribute
												LEFT JOIN "._DB_PREFIX_."order_return_detail psodet ON pac.id_product_attribute  = psodet.product_attribute_id
												LEFT JOIN "._DB_PREFIX_."attribute psa ON psa.id_attribute = al.id_attribute
												LEFT JOIN "._DB_PREFIX_."attribute_group_lang agl ON agl.id_attribute_group = psa.id_attribute_group
												WHERE psodet.id_order_return = ".$order_return['id_order_return']."
												AND al.id_lang = 2
												AND agl.id_lang = 2
												AND psodet.id_order_return_detail = ".$comline['id_order_return_detail'].";";
						$ssresult = "<description>";
						
						$ssresult .= replace_carac_product($node['product_name'])." - ";

                        $ress = Db::getInstance()->executeS($ssreponse, false);
                        while ($ssnode = Db::getInstance()->nextRow($ress)) {
							$ssresult .= replace_carac_product($ssnode['description'])." ";
						}
						
						$ssresult .= "</description>";
						$result .= $ssresult;
						$result .=	"</node>";
					}
					$result .=	"</nodes>";
					$result .=	"</commandline>";
				}
				
				//Discount
				
				$result .= $resultdiscount;
				
				//Shipping
				//$cd_vat = ORATIO_CDVAT_196;
				$cd_vat = ORATIO_CDVAT_20;
				if($order_return['shipping_rate'] == 0 || $order_return['shipping_rate'] == 0.000){
					$cd_vat = ORATIO_CDVAT_0;
				}
				elseif ($order_return['shipping_rate'] == 5.5 || $order_return['shipping_rate'] == 5.500){
					$cd_vat = ORATIO_CDVAT_55;
				}
				else{
					//$cd_vat = ORATIO_CDVAT_196;
					$cd_vat = ORATIO_CDVAT_20;
				}
				
				$result .= "<commandline id=\"\" ";
				$result .= 'partnumber="'.ORATIO_PORT_SERVICE.'" ';
				$result .= 'product_cd_vat="'.$cd_vat.'" ';
				$result .= 'sellprice="'.$order_return['total_shipping'].'" >';
				$result .= '<amounts  ';
				$result .= ' amount="'.$order_return['total_shipping'].'" netamount="'.$order_return['total_shipping_ht'].'" />';
				$result .= '<nodes>';
				$result .= '<node qty="1" barcode="'.ORATIO_PORT_BARCODE.'" >';
				$result .= "</node>";
				$result .= "</nodes>";
				$result .= "</commandline>";
				
				//END order_return product list
				$result .=	"</commandlines>";
				
				/*  paiement  */
				// **************
				
				$reponse5 = "SELECT pso.payment AS paiement
									FROM "._DB_PREFIX_."order_return pso
									WHERE pso.id_order_return = ".$order_return['id_order_return'].";";
				$result .= 	"<paiements>";
                $res5 = Db::getInstance()->executeS($reponse5, false);
                while ($paiement = Db::getInstance()->nextRow($res5)) {
					$result .= 	"<paiement type=\"".utf8_encode($paiement['paiement'])."\"/>";
				}
				$result .= 	"</paiements>";
				
				
				/*  avoir et carte cadeau en echeance  */
				// **********************
				
				$reponse_echeance = "SELECT
								psocr.id_cart_rule AS coupon_id, 
								lower(crl.name) AS coupon_name,
								round((psocr.value / (1 + ((CASE WHEN pso.total_paid_tax_excl = pso.total_paid_tax_incl THEN 0.00 ELSE 20.00 END) / 100))),3) AS netamount,
								psocr.value as amount
							FROM "._DB_PREFIX_."order_return_cart_rule psocr
							LEFT JOIN "._DB_PREFIX_."cart_rule_lang crl ON crl.id_cart_rule = psocr.id_cart_rule
							LEFT JOIN "._DB_PREFIX_."order_return pso ON pso.id_order_return = psocr.id_order_return
							WHERE crl.id_lang = 1
							".$where_duedate."
							AND psocr.id_order_return = ".$order_return['id_order_return']."
							ORDER_RETURN BY crl.name;";
				
				$result .=	"<duedates>";
                $res_ech = Db::getInstance()->executeS($reponse_echeance, false);
                while ($duedate = Db::getInstance()->nextRow($res_ech)) {
					$result .=	"<duedate coupon_name=\"".$duedate['coupon_name']."\" netamount=\"".$duedate['netamount']."\" amount=\"".$duedate['amount']."\" />";
				}
				$result .=	"</duedates>";
				
				/*  adresse shipto  */
				// ********************
				
				$reponse6 = "SELECT	CONCAT(psa.firstname, ' ', psa.lastname) AS nom,
									psa.address1 AS addr1,
									psa.address2 AS addr2,
									psa.postcode AS zip,
									psa.city AS city,
									psa.phone AS phone,
									psa.company AS company,
									cl.name AS country,
									(SELECT c.iso_code FROM "._DB_PREFIX_."country c WHERE c.id_country = cl.id_country) AS iso_code
									FROM "._DB_PREFIX_."address psa
									LEFT JOIN "._DB_PREFIX_."country_lang cl ON psa.id_country = cl.id_country
									WHERE cl.id_lang = 2
									AND psa.id_address = ".$order_return['id_address_delivery'].";";
                $res6 = Db::getInstance()->executeS($reponse6, false);
                while ($shipto = Db::getInstance()->nextRow($res6)) {
					$result .= 	"<shipto_address addr1=\"".replace_carac($shipto['company'])."\"  addr2=\"".replace_carac($shipto['nom'])."\" addr3=\"\" addr4=\"".replace_carac($shipto['addr1'])."\"  addr5=\"".replace_carac($shipto['addr2'])."\"  zip=\"".$shipto['zip']."\" city=\"".replace_carac($shipto['city'])."\" country=\"".$shipto['country']."\" iso_code=\"".$shipto['iso_code']."\" phone=\"".$shipto['phone']."\"/>";
				}
				
				/*  adresse invoiceto  */
				// ***********************
				
				$reponse6bis = "SELECT	CONCAT(psa.firstname, ' ', psa.lastname) AS nom,
									psa.address1 AS addr1,
									psa.address2 AS addr2,
									psa.postcode AS zip,
									psa.city AS city,
									psa.phone AS phone,
									psa.company AS company,
									cl.name AS country,
									(SELECT c.iso_code FROM "._DB_PREFIX_."country c WHERE c.id_country = cl.id_country) AS iso_code
									FROM "._DB_PREFIX_."address psa
									LEFT JOIN "._DB_PREFIX_."country_lang cl ON psa.id_country = cl.id_country
									WHERE cl.id_lang = 2
									AND psa.id_address = ".$order_return['id_address_invoice'].";";
                $res6b = Db::getInstance()->executeS($reponse6bis, false);
                while ($invoiceto = Db::getInstance()->nextRow($res6b)) {
					$result .= 	"<invoiceto_address addr1=\"".replace_carac($invoiceto['company'])."\" addr2=\"".replace_carac($invoiceto['nom'])."\" addr3=\"\" addr4=\"".replace_carac($invoiceto['addr1'])."\"  addr5=\"".replace_carac($invoiceto['addr2'])."\"  zip=\"".$invoiceto['zip']."\" city=\"".replace_carac($invoiceto['city'])."\" country=\"".$invoiceto['country']."\" iso_code=\"".$invoiceto['iso_code']."\" phone=\"".$invoiceto['phone']."\"/>";
				}
				$result .= 	"</document>";
			}
			$result .="</documents>";
			echo $result;
			break;
			exit;
		
		case UPDATE_ORDER_RETURN:
			$update_order_return = update_order_return();
			echo 'email sent';
			exit;
	}// fin switch				

/*
 --
-- PostgreSQL database dump
--

SET statement_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SET check_function_bodies = false;
SET client_min_messages = warning;

SET search_path = public, pg_catalog;

SET default_tablespace = '';

SET default_with_oids = false;

--
-- Name: carrier; Type: TABLE; Schema: public; Owner: oratio; Tablespace:
--

CREATE TABLE carrier (
    id_carrier integer NOT NULL,
    name text,
    businessnumber character varying(100),
    minweight numeric,
    maxweight numeric,
    website text,
    averagetime text,
    presta_id_carrier integer
);


ALTER TABLE public.carrier OWNER TO oratio;

--
-- Name: TABLE carrier; Type: COMMENT; Schema: public; Owner: oratio
--

COMMENT ON TABLE carrier IS 'table des transporteurs';


--
-- Name: carrier_id_carrier_seq; Type: SEQUENCE; Schema: public; Owner: oratio
--

CREATE SEQUENCE carrier_id_carrier_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.carrier_id_carrier_seq OWNER TO oratio;

--
-- Name: carrier_id_carrier_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: oratio
--

ALTER SEQUENCE carrier_id_carrier_seq OWNED BY carrier.id_carrier;


--
-- Name: id_carrier; Type: DEFAULT; Schema: public; Owner: oratio
--

ALTER TABLE ONLY carrier ALTER COLUMN id_carrier SET DEFAULT nextval('carrier_id_carrier_seq'::regclass);


--
-- Data for Name: carrier; Type: TABLE DATA; Schema: public; Owner: oratio
--

INSERT INTO carrier VALUES (6, 'EXAPAQ', '', 0, 30, 'http://www.exapaq.com/index.php', '2', NULL);
INSERT INTO carrier VALUES (7, 'DHL Air Cargo', '', 0, 0, '', '5', NULL);
INSERT INTO carrier VALUES (10, 'Colissimo Expert Outre Mer Z2', '', 0, 0, 'http://www.colissimo.fr/portail_colissimo/suivreResultat.do?parcelnumber=@', '5 Ã  10 jours', 34);
INSERT INTO carrier VALUES (11, 'Colissimo Expert Outre Mer Z1', '', 0, 0, 'http://www.colissimo.fr/portail_colissimo/suivreResultat.do?parcelnumber=@', '5 Ã  7 jours', 35);
INSERT INTO carrier VALUES (12, 'Fedex  Inter. Z2,3,4', '', 0, 0, 'http://www.fedex.com/Tracking?action=track&tracknumbers=@', '4 Ã  5 jours', 31);
INSERT INTO carrier VALUES (15, 'Hoalen', '', 0, 0, '', 'Retrait en Magasin', 1);
INSERT INTO carrier VALUES (16, 'Chronopost Z1', '', 0, 0, 'http://www.chronopost.fr/transport-express/livraison-colis/suivi', '3 Ã  4 jours', 39);
INSERT INTO carrier VALUES (14, 'Fedex Inter. Z1b', '', 0, 0, 'http://www.fedex.com/Tracking?action=track&tracknumbers=@', '3 Ã  4 jours', 40);
INSERT INTO carrier VALUES (9, 'Chronopost FR', '', 0, 0, 'http://www.chronopost.fr/transport-express/livraison-colis/suivi', '24 heures', 73);
INSERT INTO carrier VALUES (13, 'Fedex  Inter. Z1', '', 0, 0, 'http://www.fedex.com/Tracking?action=track&tracknumbers=@', '3 Ã  4 jours', 31);
INSERT INTO carrier VALUES (17, 'Colissimo - Livraison en point relais', '', 0, 0, 'http://www.colissimo.fr/portail_colissimo/suivre.do?colispart=@', '48 heures.', 77);
INSERT INTO carrier VALUES (18, 'Colissimo - Livraison Ã  domicile', '', 0, 0, 'http://www.colissimo.fr/portail_colissimo/suivre.do?colispart=@', '48 heures.', 78);
INSERT INTO carrier VALUES (19, 'GEODIS', '', 0, 0, 'https://secure.espacedestinataire.mobi/client/json/destinataire/findExpeditionByNumAndCodePostal', '', NULL);


--
-- Name: carrier_id_carrier_seq; Type: SEQUENCE SET; Schema: public; Owner: oratio
--

SELECT pg_catalog.setval('carrier_id_carrier_seq', 19, true);


--
-- PostgreSQL database dump complete
--


INSERT INTO carrier (name,businessnumber,minweight,maxweight,website,averagetime,presta_id_carrier) VALUES (

 * */
?>

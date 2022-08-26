<?php
	
	
	// Params for tests
	header('Content-type: text/html');
	$_GET['action'] = "creer_commande";
	$_GET['OrderId'] = 90475;
	$cart_rules = '[{"filter":"starts_with","cd_type":"CHC","cart_rule":"Chèque%20cadeau"},{"filter":"starts_with","cd_type":"AVH","cart_rule":"Avoir"},{"filter":"starts_with","cd_type":"CHC","cart_rule":"carte%20cadeau"},{"filter":"containing","cd_type":"AVH","cart_rule":"Avoir"},{"filter":"exactly","cd_type":"AVH","cart_rule":"Avoir"}]';
	
	
	
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
//        define('PRESTA_ORDER_STATES', "(6,13)");
		define('PRESTA_ORDER_STATES', "(1,2,23,10,11,9)");
        define('PRESTA_SHOP_ID', 3);
    }
    else {
//        define('PRESTA_ORDER_STATES', "(6,13)");
		define('PRESTA_ORDER_STATES', "(1,2,10,11)");
        define('PRESTA_SHOP_ID', 1);
	    define('ORATIO_CDVAT_0', 22);
    }
	define('ORATIO_CDVAT_20', 5);
	
	define ('ACTION_CREATE', "creer_commande");
	define ('ACTION_READ', "commande_attente");
	define ('ACTION_READ_LIST', "liste_commande_attente");
	define ('UPDATE_ORDER', "update_order");
	
	// Parameters received from Oratio
	//$OrderId = $_GET['OrderId'];
	//$newOrderStatusId = $_GET['NewStatus'];
	//$packnumber = $_GET['packnumber'];
	//$id_carrier = $_GET['id_carrier'];
	
	$action = (isset($HTTP_GET_VARS['action']) ? $HTTP_GET_VARS['action'] : $_GET['action']);
	
	function connexion() {
		$email = trim(_ADMIN_USER_ACCOUNT_LOGIN_);
		//$passwd = trim(_ADMIN_USER_ACCOUNT_PASSWORD_);
		$employee = new Employee();
		$employee = $employee->getByemail($email, $passwd, false);
		return $employee;
	}
	
	function update_order(){
		$connexion = connexion();
		$OrderId = $_GET['OrderId'];
		$newOrderStatusId = $_GET['NewStatus'];
		$packnumber = !empty($_GET['packnumber']) && strlen($_GET['packnumber']) > 6 ?$_GET['packnumber']:0;
		$id_facture = !empty($_GET['id_facture']) && strlen($_GET['id_facture']) > 2 ?$_GET['id_facture']:0;

		if ($id_facture) {
			Db::getInstance()->execute('UPDATE '._DB_PREFIX_.'orders SET fw_oratio = '.(int)$id_facture.' WHERE id_order = '.(int)$OrderId);
		}

		if (isset($_GET['id_carrier']) && $_GET['id_carrier'] != 73) {
			$id_carrier = $_GET['id_carrier']; // add by hoalen 22 octobre 2020
		}
		

		if ($OrderId){
			$order = new Order($OrderId);
			
			//we put the Oratio shipping number in the order
			if($packnumber){
				$order->shipping_number = $packnumber;
			}
			// search prestashop carrier_id by Oratio carrier name
			/*if($carrier_name){
				@mysql_connect(_DB_SERVER_, _DB_USER_, _DB_PASSWD_); //Connexion à MySQL
				mysql_select_db(_DB_NAME_); //selection de la base
				$response = mysql_query("SELECT id_carrier FROM "._DB_PREFIX_."carrier WHERE name = '".$carrier_name."';") or die(mysql_error());
				$id_carrier = mysql_fetch_array($response);
				$id_carrier = $id_carrier['id_carrier'];
				
				if($id_carrier){
					$order->id_carrier = $id_carrier;
				}
			}*/
			
			if($id_carrier){
//				$order->id_carrier = $id_carrier; // add by hoalen le 21 mai 2021 suite à la découverte de l'id_carrier modifié pour son id reference
                if($packnumber){
                    $id_order_carrier = Db::getInstance()->getValue('
            SELECT `id_order_carrier`
            FROM `'._DB_PREFIX_.'order_carrier`
            WHERE `id_order` = '.(int)$OrderId);
                    $order_carrier = new OrderCarrier($id_order_carrier);
                    $order_carrier->tracking_number = $packnumber;
                    $order_carrier->update();
                }
			}

			$order->update();
			
			$history = new OrderHistory();
			$history->id_order = $OrderId;
			$use_existing_payment = true;
			$history->changeIdOrderState(intval($newOrderStatusId), intval($OrderId), $use_existing_payment);
			$history->id_employee = intval($connexion->id);
			$carrier = new Carrier(intval($order->id_carrier), intval($order->id_lang));
			$templateVars = array('{followup}' => ($history->id_order_state == _PS_OS_SHIPPING_ AND $order->shipping_number) ? str_replace('@', $order->shipping_number, $carrier->url) : '');
			$history->addWithemail(true, $templateVars);

			// if it's Oratio synchro order to delivery with shipping number
			if ($packnumber && $order->shipping_number){
				global $_LANGMAIL;
				$id_lang = $order->id_lang;
				$customer = new Customer(intval($order->id_customer));
				$templateVars = array(
					'{followup}' => str_replace('@', $order->shipping_number, $carrier->url),
					'{firstname}' => $customer->firstname,
					'{lastname}' => $customer->lastname,
					'{id_order}' => intval($order->id)
				);
				
				$subject = 'Package in transit';
				$key = str_replace('\'', '\\\'', $subject);
				$str = "";
				
				$file_core = _PS_ROOT_DIR_.'/mails/'.Language::getIsoById((int)$id_lang).'/lang.php';
				if (Tools::file_exists_cache($file_core) && empty($_LANGMAIL))
					include_once($file_core);
				
				$file_theme = _PS_THEME_DIR_.'mails/'.Language::getIsoById((int)$id_lang).'/lang.php';
				if (Tools::file_exists_cache($file_theme))
					include_once($file_theme);
				
				if (!is_array($_LANGMAIL))
					$str = str_replace('"', '&quot;', $subject);
				if (key_exists($key, $_LANGMAIL))
					$str = $_LANGMAIL[$key];
				else
					$str = $subject;
					
				$str = str_replace('"', '&quot;', addslashes($str));
				
				//Mail::Send(intval($order->id_lang), 'in_transit', ((is_array($_LANGMAIL) AND key_exists($subject, $_LANGMAIL)) ? $_LANGMAIL[$subject] : $subject), $templateVars, $customer->email, $customer->firstname.' '.$customer->lastname);
				Mail::Send(intval($order->id_lang), 'in_transit', $str, $templateVars, $customer->email, $customer->firstname.' '.$customer->lastname);
			}
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
		
		
		case ACTION_CREATE:
			
			$connexion = connexion();
			$token = Tools::getAdminToken('AdminOrders'.(int)(Tab::getIdFromClassName('AdminOrders')).intval($connexion->id));
			
			$OrderId = $_GET['OrderId'];
			$cart_rules = $_GET['cart_rules']; // &cart_rules=[{"filter":"starts_with","cd_type":"CHC","cart_rule":"Chèque%20cadeau"},{"filter":"starts_with","cd_type":"AVH","cart_rule":"Avoir"},{"filter":"starts_with","cd_type":"CHC","cart_rule":"carte%20cadeau"},{"filter":"containing","cd_type":"AVH","cart_rule":"Avoir"},{"filter":"exactly","cd_type":"AVH","cart_rule":"Avoir"}]
			//$cart_rules = utf8_encode($cart_rules);
			$cart_rules_list = json_decode($cart_rules, true);
			
			// cart rules condition for duedate //
			$count_condition_duedate = 0;
			$where_duedate = "AND ( ";
			$where_not_duedate = "";

			/**
			 * Si nouvelle langue en plus de FR/EN, procéder comme pour la condition si Avoir (FR) => Credit slip (EN)
			 */
			foreach ($cart_rules_list as $num => $data_duedate) {
				if ($data_duedate['cart_rule'] == "Avoir") {
					$new = $cart_rules_list[$num];
					$new['cart_rule'] = "Credit Slip";
					$cart_rules_list[] = $new;
				}
			}
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
//				$where_duedate = "AND ( lower(crl.name) like lower('%Chèque cadeau') or lower(crl.name) like lower('%Avoir') or lower(crl.name) like lower('%carte cadeau') or lower(crl.name) like lower('%Avoir%') or lower(crl.name) like lower('Avoir') )";
//				$where_not_duedate = "AND lower(crl.name) not like lower('%Chèque cadeau') AND lower(crl.name) not like lower('%Avoir') AND lower(crl.name) not like lower('%carte cadeau') AND lower(crl.name) not like lower('%Avoir%') AND lower(crl.name) not like lower('Avoir')";
			// end cart rules condition for duedate //
			
			//TODO : get tax rate for total_discounts and calculate vatamount
			////(pso.total_shipping / 1.196) as total_shipping_ht
			
			$reponse =  "SELECT	pso.id_order,pso.id_customer,
						DATE(pso.date_add) AS datecommande,
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
						pso.carrier_tax_rate as shipping_rate,
						concat(CASE WHEN pso.carrier_tax_rate > 0.0 THEN calfr.delay ELSE CASE WHEN ca.id_reference > 1 THEN calen.delay ELSE calfr.delay END END , CASE WHEN pso.carrier_tax_rate > 0.0 THEN ' par ' ELSE CASE WHEN ca.id_reference > 1 THEN ' by ' ELSE ' par ' END END, ca.`name`) as shipping_label 
						FROM "._DB_PREFIX_."orders pso
						LEFT OUTER JOIN "._DB_PREFIX_."customer c on c.id_customer = pso.id_customer
						LEFT OUTER JOIN ps_carrier ca on ca.id_carrier = pso.id_carrier
						LEFT OUTER JOIN ps_carrier_lang calfr on calfr.id_carrier = pso.id_carrier and calfr.id_lang = 1 
						LEFT OUTER JOIN ps_carrier_lang calen on calen.id_carrier = pso.id_carrier and calen.id_lang = 2 
						WHERE pso.id_order = '$OrderId' group by ca.id_carrier;";
			
			// On fait une boucle pour lister tout ce que contient la table :
			
			$result = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>";
			
			
			$result .="<documents>";

            $res = Db::getInstance()->executeS($reponse, false);
            while ($order = Db::getInstance()->nextRow($res)) {

				$result .=	"<document reference = \"".$order['id_order']."\" reqdate=\"".$order['datecommande']."\" transdate=\"".$order['datecommande']."\" presta_carrier_name=\"".$order['carrier_name']."\" presta_id_carrier=\"".$order['id_carrier']."\" >";
				$result .=	"<contact name=\"".replace_carac($order['name'])."\" gender=\"".$order['gender']."\" email_address=\"".$order['email']."\" birthdate=\"".$order['birthday']."\" />";
				$result .=	"<amount>";
				$result .=	"<reductedamountwithvat>" .$order['totalTTC']. "</reductedamountwithvat>";
				$result .=	"<vatamount>" .$order['vatamount']. "</vatamount>";
				$result .=	"</amount>";



				/*  bon de reduction  */
				// **********************
				
				/*$reponse2 = mysql_query(
							"SELECT	
								ccr.id_cart_rule AS coupon_id,
								crl.name AS coupon_name,
								-round((pso.total_discounts_tax_incl / (1 + ((CASE WHEN pso.total_paid_tax_excl = pso.total_paid_tax_incl THEN 0.00 ELSE 20.00 END) / 100))),2) AS netamount,
								-pso.total_discounts_tax_incl AS amount
							FROM "._DB_PREFIX_."orders pso
							LEFT JOIN "._DB_PREFIX_."cart_cart_rule ccr ON ccr.id_cart = pso.id_cart
							LEFT JOIN "._DB_PREFIX_."cart_rule cr ON cr.id_cart_rule = ccr.id_cart_rule
							LEFT JOIN "._DB_PREFIX_."cart_rule_lang crl ON crl.id_cart_rule = ccr.id_cart_rule
							WHERE crl.id_lang = 2
							AND pso.id_order = ".$order['id_order'].";") or die(mysql_error());*/
				
				
				
				/*$reponse2 =  "SELECT
								psocr.id_cart_rule AS coupon_id, 
								crl.name AS coupon_name,
								-round((psocr.value / (1 + ((CASE WHEN pso.total_paid_tax_excl = pso.total_paid_tax_incl THEN 0.00 ELSE 20.00 END) / 100))),2) AS netamount,
								-psocr.value as amount
							FROM "._DB_PREFIX_."order_cart_rule psocr
							LEFT JOIN "._DB_PREFIX_."cart_rule_lang crl ON crl.id_cart_rule = psocr.id_cart_rule
							LEFT JOIN "._DB_PREFIX_."orders pso ON pso.id_order = psocr.id_order
							WHERE crl.id_lang = 2
							".$where_not_duedate."
							AND psocr.id_order = ".$order['id_order'].";";*/
							
				$reponse2 =  "SELECT o.id_cart,
								psocr.id_cart_rule AS coupon_id, 
								cr.code AS code,
								cr.description AS description,
								cr.reduction_percent AS reduction_percent,
								cr.reduction_product AS code_promo,
								crl.name AS coupon_name,
								-round((psocr.value / (1 + ((CASE WHEN pso.total_paid_tax_excl = pso.total_paid_tax_incl THEN 0.00 ELSE 20.00 END) / 100))),4) AS netamount,
								-psocr.value as amount
							FROM "._DB_PREFIX_."order_cart_rule psocr
                            LEFT JOIN "._DB_PREFIX_."orders o ON o.id_order = psocr.id_order
							LEFT JOIN "._DB_PREFIX_."cart_rule_lang crl ON crl.id_cart_rule = psocr.id_cart_rule
							LEFT JOIN "._DB_PREFIX_."cart_rule cr ON cr.id_cart_rule = psocr.id_cart_rule
							LEFT JOIN "._DB_PREFIX_."orders pso ON pso.id_order = psocr.id_order
							WHERE crl.id_lang = 2
							/*".$where_not_duedate."*/
							
							AND cr.id_cart_rule_type NOT IN (3,4)
							AND psocr.id_order = ".$order['id_order'].";";
							

				// oratio affaires
				/*
					id	description
					781 Opérations promotionnelles // reduction_percent > 0 && reduction_product = -2 // pas de tiret dans le nom ? // reduction_amount -> discount en euros ?? @TODO tshirt à 20/25 € quelle catégorie ?
					792 Prix réduits
					783 Niveau 1
					784 Niveau 2
					785 Niveau 3
					786 Niveau 4
					787 Niveau 5
					788 Friends
					789 Staff
					790 Ambassadeurs
					791 Investisseurs
				 */

//				var_dump($order);




				// Script pas bon car le niveau ou le label peut changer entre le moment où est passé la commande et le moment où à lieu la synchro
				// @todo : se servir de la table ps_orders_salted qui enregistre les infos salted au moment de la commande.
				
				//$level_label = Db::getInstance()->getValue("SELECT coalesce((select label from "._DB_NAME_SALTED_.".levels where level = c.level_priority),c.level_priority,0) as level_label FROM "._DB_NAME_SALTED_.".users c /* LEFT OUTER JOIN (select level,label as label from "._DB_NAME_SALTED_.".levels) as levels ON levels.level = c.id_level*/ WHERE c.id_hoalen =".$order['id_customer']);
//				die($level_label);
				
				
				
				$level_label = Db::getInstance()->getValue("SELECT COALESCE(label_priority, label_current, 0) as level_label
															FROM "._DB_PREFIX_."order_salted
															WHERE id_order = ".$order['id_order']);
				
				//echo $level_label.'<br>';
				
				$codePromoProjectId = 781;
				$DefaultProjectId = 792;
				$SaltLevelProjectId = 783;
				
				$cartRuleIdTosaltedProjectId = array(
					3776 => ['Sand' => 784],
					3775 => ['Coral' => 785],
					3774 => ['Stone' => 786],
					3773 => ['Granit' => 787],
					4214 => ['Friend' => 788, 'Friends' => 788],
					3846 => ['Staff' => 789, 'Ambassadeur' => 790, 'Investisseur' => 791]
				);
				
				/*$cartRuleIdTosaltedProjectId = array(
					3776 => array(784),
					3775 => array(785),
					3774 => array(786),
					3773 => array(787),
					3846 => array(
						'Friends' => 788,
						'Staff' => 789,
						'Ambassadeur' => 790,
						'Investisseur' => 791,
						)
				);*/

				$projectIds = array();
				$products_elligible = array();

				$resultdiscount = '';
				$montantdiscount = 0;

				$pid = 0;
				$saltedpid = 0;
				$salted = 0;


//				print "<pre>";

                $res2 = Db::getInstance()->executeS($reponse2, false);
                
                
                
                while ($discount = Db::getInstance()->nextRow($res2)) {
	                
	               


					$cart_rule = new CartRule($discount['coupon_id']);
					$cart = new Cart($discount['id_cart']);
					$products_elligible = $cart_rule->checkProductRestrictionsFromCart($cart, TRUE, TRUE, TRUE);



					// @ faire le lien entre le discount et le niveau salted
					// @todo ajouter dans la requete le champs determinant les codes promos produits = -2
                	if (strpos($discount['code'], 'SALTED') !== FALSE && strpos($discount['code'], 'SHIPPING') === FALSE) {
	                	
						if (strpos($discount['code'], 'LEVEL') !== FALSE) {
							$salted=1;
							 
//                		var_dump($discount['code']);
							preg_match('/\s(\d*)%/',$discount['coupon_name'],$m);
							if (isset($m[1])) {
								
//                		var_dump($m[1]);
//                		var_dump($cartRuleIdTosaltedProjectId[$discount['coupon_id']]);
//                		var_dump($cartRuleIdTosaltedProjectId[$discount['coupon_id']][$level_label]);
								if (isset($cartRuleIdTosaltedProjectId[$discount['coupon_id']]) && isset($cartRuleIdTosaltedProjectId[$discount['coupon_id']][$level_label])) {
									$saltedpid = $cartRuleIdTosaltedProjectId[$discount['coupon_id']][$level_label];
								}
								
								$montantdiscount += $m[1];

							}
						}
						else {
							
							$mntntdscnt = preg_replace('/[^\d]/','',$discount['coupon_name']);
							$saltedpid = $SaltLevelProjectId;
							$montantdiscount = $mntntdscnt > 10 ? $mntntdscnt :10;
						}
					} elseif ($discount['code_promo'] == -2) { 
						// @todo checkProductRestrictionsFromCart
//                		die('coucou');

						$saltedpid = $codePromoProjectId;
						$montantdiscount = $discount['reduction_percent'];
					} else {
					
						$cd_vat = ORATIO_CDVAT_20;
						if($discount['amount'] == $discount['netamount']){
							$cd_vat = ORATIO_CDVAT_0;
						}
						else{
							$cd_vat = ORATIO_CDVAT_20;
						}
						
						
						$resultdiscount .= "<commandline id=\"\" ";
						$resultdiscount .= 'partnumber="'.ORATIO_DISCOUNT_SERVICE.'" ';
	//					$resultdiscount .= 'project_id="" ';
						$resultdiscount .= 'product_cd_vat="'.$cd_vat.'" ';
						$resultdiscount .= 'description="Coupon '.(strpos($discount['code'], 'SALTED') !== FALSE?$discount['coupon_name']:$discount['description']).'" ';
	//					$resultdiscount .= 'description="Coupon '.$discount['coupon_name'].' '.$discount['description'].'" ';
						$resultdiscount .= 'sellprice="'.$discount['netamount'].'" >';
						$resultdiscount .= '<amounts  ';
						$resultdiscount .= ' amount="'.$discount['amount'].'" netamount="'.$discount['netamount'].'" />';
						$resultdiscount .= '<nodes>';
						$resultdiscount .= '<node qty="1" barcode="'.ORATIO_DISCOUNT_BARCODE.'" >';
						$resultdiscount .= "</node>";
						$resultdiscount .= "</nodes>";
						$resultdiscount .= "</commandline>";
					}
				}
				
				

//				var_dump($pid);
//				die;
				
				/*  ligne de produit  */
				// **********************
				
				$reponse3 =
							"SELECT	DISTINCT pr.id_product AS id,od.product_attribute_id,
									pr.reference AS partnumber,
									pr.`condition`,
									(CASE WHEN tva.rate IS NULL THEN 0.00 ELSE tva.rate END) AS vat_rate,
									pr.price AS sellprice,
									od.product_name AS name,
									(od.product_price * ((CASE WHEN tva.rate IS NULL THEN 0.00 ELSE tva.rate END) /100))AS taxamount,/* - ((od.reduction_amount / (1 + (CASE WHEN tva.rate IS NULL THEN 0.00 ELSE tva.rate END) /100)) * ((CASE WHEN tva.rate IS NULL THEN 0.00 ELSE tva.rate END) /100)) AS taxamount,*/
									(od.product_price * (1 + (CASE WHEN tva.rate IS NULL THEN 0.00 ELSE tva.rate END) /100)) AS amount, /*- od.reduction_amount AS amount,*/
									od.product_price AS netamount,/* - (od.reduction_amount / (1 + (CASE WHEN tva.rate IS NULL THEN 0.00 ELSE tva.rate END) /100)) AS netamount,*/
									od.reduction_percent,
									od.reduction_amount,
									od.id_order_detail,i.`id_image` as img
									FROM "._DB_PREFIX_."order_detail od
									LEFT JOIN "._DB_PREFIX_."orders pso ON od.id_order = pso.id_order
									LEFT JOIN "._DB_PREFIX_."product pr ON pr.id_product = od.product_id
									LEFT JOIN "._DB_PREFIX_."product_lang prl ON pr.id_product = prl.id_product
									LEFT JOIN "._DB_PREFIX_."order_detail_tax odt ON od.id_order_detail = odt.id_order_detail
									LEFT JOIN "._DB_PREFIX_."tax tva ON odt.id_tax = tva.id_tax
									LEFT JOIN ps_image i ON i.id_product = pr.id_product AND i.`cover` = 1
									WHERE prl.id_lang = 2
									AND pso.id_order = ".$order['id_order']."
									ORDER BY od.id_order_detail;";
				$result .=	"<commandlines>";
                $res3 = Db::getInstance()->executeS($reponse3, false);
//                print "<pre>";
				while ($comline = Db::getInstance()->nextRow($res3)) {
//					var_dump($comline);
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
					
					$result .=	"<commandline id=\"".$comline['id']."\" ";
					$result .=	"partnumber=\"".$comline['partnumber']."\" ";
					$result .=	"condition=\"".$comline['condition']."\" ";
					$result .=	"product_cd_vat=\"".$cd_vat."\" ";
					$result .=	"img=\"http://www.hoalen.com/azadmin/grayscale.php?img=img/p/".$comline['id']."-".$comline['img']."-m.jpg\" "; // http://www.hoalen.com/azadmin/grayscale.php?img=img/p/3113-10910-m.jpg // http://www.hoalen.com/img/p/3113-10910-m.jpg
					$result .=	"sellprice=\"".$comline['sellprice']."\">";
					$result .=	"<name>".replace_carac_product($comline['name'])."</name>";
					$result .=	"<amounts taxamount=\"".$comline['taxamount']."\" ";
					$result .=	"amount=\"".$comline['amount']."\" ";
					$result .=	"netamount=\"".$comline['netamount']."\" ";
//					$result .=	"data-reduction_percent=\"".$comline['reduction_percent']."\" ";
//					$result .=	"data-montantdiscount=\"".$montantdiscount."\" ";
//					$result .=	"data-pid=\"".$pid."\" ";
//					$result .=	"data-saltedpid=\"".$saltedpid."\" ";
//					$result .=	"data-default-pid=\"".$DefaultProjectId."\" ";

					if (floatval($comline['reduction_amount']) > 0  ) { // && ((!$salted && in_array($comline['id'].'-'.$comline['product_attribute_id'],$products_elligible)))) {
						$result .=" project_id=\"".$codePromoProjectId."\" "."discount=\"".number_format(floatval($comline['reduction_amount']*100/$comline['amount']),2)."\"";
//						$result .=" project_id=\"".$codePromoProjectId."\" "."discount=\"".number_format(floatval(100-($comline['netamount']*100/$comline['sellprice'])),2)."\"";
					}
					else {
						if ($comline['id'] != 688 && ($montantdiscount || $comline['reduction_percent'] > 0)  ) { // && ((!$salted && in_array($comline['id'].'-'.$comline['product_attribute_id'],$products_elligible)))) {


							if (floatval($comline['reduction_percent'])) {
								$result .=" project_id=\"";
								$result .= $DefaultProjectId."\" ";
							}
							else if ($saltedpid != 781 || ($saltedpid == 781 && in_array($comline['id'].'-'.$comline['product_attribute_id'],$products_elligible))) {
								$result .=" project_id=\"";
								$result .= $saltedpid."\" ";
							}

						}
						else {
							$result .= "";
						}
						$result .= " ";
						if ($comline['reduction_percent'] > 0  ) { // && ((!$salted && in_array($comline['id'].'-'.$comline['product_attribute_id'],$products_elligible)))) {
							$result .= "discount=\"".$comline['reduction_percent']."\"";
						}
						else {
							if ($comline['id'] != 688 && ($saltedpid != 781 || ($saltedpid == 781 && in_array($comline['id'].'-'.$comline['product_attribute_id'],$products_elligible)))) { // && ((!$salted && in_array($comline['id'].'-'.$comline['product_attribute_id'],$products_elligible)))) {
								$result .= "discount=\"".$montantdiscount."\"";
							}
							else {
								$result .= "";
							}
						}
					}
					$result .= " />";
					
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
								FROM "._DB_PREFIX_."order_detail psodet
								WHERE psodet.id_order = ".$order['id_order']."
								AND psodet.id_order_detail = ".$comline['id_order_detail'].";";
								$result .=	"<nodes>";
                    $res4 = Db::getInstance()->executeS($reponse4, false);
                    while ($node = Db::getInstance()->nextRow($res4)) {
						$result .=	"<node qty=\"".$node['qty']."\" barcode=\"".substr($node['barcode'], 0, 12)."\">";
						$ssreponse = "SELECT	CONCAT(agl.public_name, ' ', al.name) AS description
												FROM "._DB_PREFIX_."attribute_lang al
												LEFT JOIN "._DB_PREFIX_."product_attribute_combination pac ON al.id_attribute = pac.id_attribute
												LEFT JOIN "._DB_PREFIX_."order_detail psodet ON pac.id_product_attribute  = psodet.product_attribute_id
												LEFT JOIN "._DB_PREFIX_."attribute psa ON psa.id_attribute = al.id_attribute
												LEFT JOIN "._DB_PREFIX_."attribute_group_lang agl ON agl.id_attribute_group = psa.id_attribute_group
												WHERE psodet.id_order = ".$order['id_order']."
												AND al.id_lang = 2
												AND agl.id_lang = 2
												AND psodet.id_order_detail = ".$comline['id_order_detail'].";";
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
				if($order['shipping_rate'] == 0 || $order['shipping_rate'] == 0.000){
					$cd_vat = ORATIO_CDVAT_0;
				}
				elseif ($order['shipping_rate'] == 5.5 || $order['shipping_rate'] == 5.500){
					$cd_vat = ORATIO_CDVAT_55;
				}
				else{
					//$cd_vat = ORATIO_CDVAT_196;
					$cd_vat = ORATIO_CDVAT_20;
				}
				
				$result .= "<commandline id=\"\" ";
				$result .= 'partnumber="'.ORATIO_PORT_SERVICE.'" ';
				$result .= 'product_cd_vat="'.$cd_vat.'" ';
				$result .= 'sellprice="'.$order['total_shipping'].'" >';
				$result .=	"<name>".replace_carac_product($order['shipping_label'])."</name>";
				$result .= '<amounts  ';
				$result .= ' amount="'.$order['total_shipping'].'" netamount="'.$order['total_shipping_ht'].'" />';
				$result .= '<nodes>';
				$result .= '<node qty="1" barcode="'.ORATIO_PORT_BARCODE.'" >';
				$result .= "</node>";
				$result .= "</nodes>";
				$result .= "</commandline>";
				
				//END order product list
				$result .=	"</commandlines>";
				
				/*  paiement  */
				// **************
				
				$reponse5 = "SELECT pso.payment AS paiement
									FROM "._DB_PREFIX_."orders pso
									WHERE pso.id_order = ".$order['id_order'].";";
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
							FROM "._DB_PREFIX_."order_cart_rule psocr
							LEFT JOIN "._DB_PREFIX_."cart_rule_lang crl ON crl.id_cart_rule = psocr.id_cart_rule
							LEFT JOIN "._DB_PREFIX_."cart_rule cr ON crl.id_cart_rule = cr.id_cart_rule
							LEFT JOIN "._DB_PREFIX_."orders pso ON pso.id_order = psocr.id_order
							WHERE crl.id_lang = 1
							/*".$where_duedate."*/
							AND cr.id_cart_rule_type IN (3,4)
							AND psocr.id_order = ".$order['id_order']."
							ORDER BY crl.name;";
				
				$result .=	"<duedates>";
                $res_ech = Db::getInstance()->executeS($reponse_echeance, false);
                while ($duedate = Db::getInstance()->nextRow($res_ech)) {
					$result .=	"<duedate coupon_name=\"".$duedate['coupon_name']."\" netamount=\"".$duedate['netamount']."\" amount=\"".$duedate['amount']."\" />";
				}
				$result .=	"</duedates>";
				
				/*  adresse shipto  */
				// ********************
				
				$reponse6 = "SELECT	CONCAT(psa.firstname, ' ', psa.lastname) AS nom,
									CASE WHEN char_length(address1) < 5 THEN
 CASE WHEN char_length(concat(address1, ' ', address2)) < 5 THEN
 alias 
 ELSE 
 	CASE WHEN alias LIKE 'Mon adresse%' OR alias LIKE 'My address%' THEN
    concat(address1, ' ', address2)
    ELSE
 	concat(address1, ' ', address2, ' ', alias) 
 	END
 END
ELSE 
address1 
END
as addr1,
									psa.address2 AS addr2,
									psa.postcode AS zip,
									psa.city AS city,
									CASE WHEN char_length(psa.phone) > 9 THEN psa.phone ELSE psa.phone_mobile END AS phone,
									psa.company AS company,
									cl.name AS country,
									(SELECT c.iso_code FROM "._DB_PREFIX_."country c WHERE c.id_country = cl.id_country) AS iso_code
									FROM "._DB_PREFIX_."address psa
									LEFT JOIN "._DB_PREFIX_."country_lang cl ON psa.id_country = cl.id_country
									WHERE cl.id_lang = 2
									AND psa.id_address = ".$order['id_address_delivery'].";";

				/**
				 * point relais ups
				 */
				$isUPSPR = Db::getInstance()->getValue("SELECT count(*) FROM `ps_carrier` ca LEFT JOIN ps_configuration co on value = id_reference  where deleted = 0 AND co.name IN ('UPS_CARRIER_RELAIS_EU', 'UPS_CARRIER_RELAIS') AND `id_reference` = ".$order['id_carrier']);
				if ($isUPSPR) {
					$reponse6 = "SELECT ap_name as company, ap_address1 as addr1, ap_address2 as addr2, ap_state, ap_postcode as zip, ap_city as city ,CONCAT(psa.firstname, ' ', psa.lastname) AS nom,
									cl.name AS country,
									(SELECT c.iso_code FROM ps_country c WHERE c.id_country = cl.id_country) AS iso_code,
									CASE WHEN char_length(psa.phone) > 9 THEN psa.phone ELSE psa.phone_mobile END AS phone
FROM ps_ups_openorder upso
left join ps_orders pso on pso.id_order = upso.id_order
left join ps_address psa on psa.id_address = pso.id_address_delivery
LEFT JOIN ps_country_lang cl ON psa.id_country = cl.id_country
WHERE upso.id_order=$OrderId
group by upso.id_order";
				}

                $res6 = Db::getInstance()->executeS($reponse6, false);
//				error_log(json_encode(array($OrderId => array('isUPSPR'=>$isUPSPR, 'other'=>$order))));
                while ($shipto = Db::getInstance()->nextRow($res6)) {
//				error_log(json_encode(array($OrderId => array('shipto'=>$shipto))));
					$result .= 	"<shipto_address addr1=\"".replace_carac($shipto['company'])."\"  addr2=\"".replace_carac($shipto['nom'])."\" addr3=\"\" addr4=\"".replace_carac($shipto['addr1'])."\"  addr5=\"".replace_carac($shipto['addr2'])."\"  zip=\"".$shipto['zip']."\" city=\"".replace_carac($shipto['city'])."\" country=\"".$shipto['country']."\" iso_code=\"".$shipto['iso_code']."\" phone=\"".$shipto['phone']."\"/>";
				}

				/*  adresse invoiceto  */
				// ***********************
				
				$reponse6bis = "SELECT	CONCAT(psa.firstname, ' ', psa.lastname) AS nom,
									psa.address1 AS addr1,
									psa.address2 AS addr2,
									psa.postcode AS zip,
									psa.city AS city,
									CASE WHEN char_length(psa.phone) > 9 THEN psa.phone ELSE psa.phone_mobile END AS phone,
									psa.company AS company,
									cl.name AS country,
									(SELECT c.iso_code FROM "._DB_PREFIX_."country c WHERE c.id_country = cl.id_country) AS iso_code
									FROM "._DB_PREFIX_."address psa
									LEFT JOIN "._DB_PREFIX_."country_lang cl ON psa.id_country = cl.id_country
									WHERE cl.id_lang = 2
									AND psa.id_address = ".$order['id_address_invoice'].";";
                $res6b = Db::getInstance()->executeS($reponse6bis, false);
                while ($invoiceto = Db::getInstance()->nextRow($res6b)) {
					$result .= 	"<invoiceto_address addr1=\"".replace_carac($invoiceto['company'])."\" addr2=\"".replace_carac($invoiceto['nom'])."\" addr3=\"\" addr4=\"".replace_carac($invoiceto['addr1'])."\"  addr5=\"".replace_carac($invoiceto['addr2'])."\"  zip=\"".$invoiceto['zip']."\" city=\"".replace_carac($invoiceto['city'])."\" country=\"".$invoiceto['country']."\" iso_code=\"".$invoiceto['iso_code']."\" phone=\"".$invoiceto['phone']."\"/>";
				}
				$result .= 	"</document>";
			}
			$result .="</documents>";
			//echo $result;
			
			
			$result = str_replace('&', '&amp;', $result);
			$result = str_replace('<', '&lt;', $result);
			echo "<pre>";echo $result;echo'</pre>';die();
			
			break;
			//exit;
		
	}// fin switch				

?>

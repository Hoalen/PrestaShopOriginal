<?php
	
	function parse_xml_into_array($xml_string, $options = array()) {
		/*
		DESCRIPTION:
		- parse an XML string into an array
		INPUT:
		- $xml_string
		- $options : associative array with any of these keys:
			- 'flatten_cdata' : set to true to flatten CDATA elements
			- 'use_objects' : set to true to parse into objects instead of associative arrays
			- 'convert_booleans' : set to true to cast string values 'true' and 'false' into booleans
		OUTPUT:
		- associative array
		*/
	
		// Remove namespaces by replacing ":" with "_"
		if (preg_match_all("|</([\\w\\-]+):([\\w\\-]+)>|", $xml_string, $matches, PREG_SET_ORDER)) {
			foreach ($matches as $match) {
				$xml_string = str_replace('<'. $match[1] .':'. $match[2], '<'. $match[1] .'_'. $match[2], $xml_string);
				$xml_string = str_replace('</'. $match[1] .':'. $match[2], '</'. $match[1] .'_'. $match[2], $xml_string);
			}
		}
	
		$output = json_decode(json_encode(@simplexml_load_string($xml_string, 'SimpleXMLElement', ($options['flatten_cdata'] ? LIBXML_NOCDATA : 0))), (isset($options['use_objects']) && $options['use_objects'] ? false : true));
	
		// Cast string values "true" and "false" to booleans
		if (isset($options['convert_booleans']) && $options['convert_booleans']) {
			$bool = function(&$item, $key) {
				if (in_array($item, array('true', 'TRUE', 'True'), true)) {
					$item = true;
				} elseif (in_array($item, array('false', 'FALSE', 'False'), true)) {
					$item = false;
				}
			};
			array_walk_recursive($output, $bool);
		}
	
		return $output;
	}


	/**
	* MODULE PRESTASHOP non OFFICIEL UPS by hoalen
	*/
	




	
	error_reporting(E_ALL);
	ini_set('display_errors', 1);
	
	//error_log('tracking ups start');
	$compteur = 0;
	$compteur_unsaved = 0;
	//header('Content-type: text/plain');
	//header('Content-Type: application/xml');
	include('../config/config.inc.php');
	//define('_TRACKING_URL', "https://wwwcie.ups.com/webservices/Track");
	define('_TRACKING_URL', "https://onlinetools.ups.com/webservices/Track");
	$password = "Saltwater29+";


	/* No more than one update per hour */
	//if (time() - (int)Configuration::get('CHRONO_TRACKING_LAST_UPDATE') < 1800) {
	    //die('NO UPDATE');
	//}
	
	
	/*
		
		Presta Status 
		
		2 -> Paiement accepté
		3 -> Commande acceptée
		4 -> Préparation en cours
		
		20 -> Prêt pour l'expédition
		5 -> Expédié (Facturé sur Oratio)
		
		24 -> Livraison en cours
		25 -> Problème lors de la livraison
		26 -> Disponible en points retrait
		
		21 -> Livrée
		27 -> Récupéré en point retrait
	
	*/




	/* Orders in state "shipping", for our carriers, with a tracking number */
	
	
	$query = '	SELECT oc.id_order, s.tracking_number, o.current_state 
				FROM '._DB_PREFIX_.'order_carrier oc 
				LEFT JOIN '._DB_PREFIX_.'orders o ON o.id_order=oc.id_order 
				LEFT JOIN ps_ups_shipment s ON s.id_order=oc.id_order 
				LEFT JOIN '._DB_PREFIX_.'carrier c ON o.id_carrier=c.id_carrier
				WHERE c.id_reference IN (187,188,189)
				    AND s.tracking_number != ""  
				    AND o.current_state IN (5,24,25,26) 
				    AND o.date_add > (NOW() - INTERVAL 30 DAY)';
	
	
	
	
	/*$query = '	SELECT oc.id_order, s.tracking_number, o.current_state 
				FROM '._DB_PREFIX_.'order_carrier oc 
				LEFT JOIN '._DB_PREFIX_.'orders o ON o.id_order=oc.id_order 
				LEFT JOIN ps_ups_shipment s ON s.id_order=oc.id_order 
				LEFT JOIN '._DB_PREFIX_.'carrier c ON o.id_carrier=c.id_carrier
				WHERE c.id_reference IN (187,188,189)
				    AND s.tracking_number != ""  
				    AND o.current_state IN (4,5,20,24,25,26) 
				    AND o.date_add > (NOW() - INTERVAL 30 DAY)';*/
	
	
	
	/*
	$querya = 'SELECT oc.id_order, oc.tracking_number, o.current_state FROM '._DB_PREFIX_.'order_carrier oc 
	LEFT JOIN '
	    ._DB_PREFIX_.'orders o ON o.id_order=oc.id_order LEFT JOIN '._DB_PREFIX_.'carrier c ON o.id_carrier=c.id_carrier
	WHERE c.id_reference IN (30,31,32,33,40,79)
	    AND oc.tracking_number!=""  
	    AND o.current_state IN (21)
	    AND o.date_add > (NOW() - INTERVAL 30 DAY)';    */
	
	
	/* order states
		
		5 -> Expédiée	
		21 -> Livrée
		24 -> En cours de livraison
		25 -> Problème lors de la livraison
	
	*/
	
	//$events = array();
	
	//$orders_events = array();
	
	$orders = Db::getInstance()->ExecuteS($query);

	/*print "<pre>";
	var_dump($orders);
	die;*/

	foreach ($orders as $order) {
	    $tn = $order['tracking_number'];
/*		$req = <<<EOL
		<?xml version="1.0" encoding="utf-8"?>
		<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:upss="http://www.ups.com/XMLSchema/XOLTWS/UPSS/v1.0" xmlns:trk="http://www.ups.com/XMLSchema/XOLTWS/Track/v2.0" xmlns:common="http://www.ups.com/XMLSchema/XOLTWS/Common/v1.0">
		<soapenv:Header>
		<upss:UPSSecurity>
		<upss:UsernameToken>
		<upss:Username>HOALEN</upss:Username>
		<upss:Password>Ups_29800+</upss:Password>
		</upss:UsernameToken>
		<upss:ServiceAccessToken>
		<upss:AccessLicenseNumber>3D92F36B4929A315</upss:AccessLicenseNumber>
		</upss:ServiceAccessToken></upss:UPSSecurity>
		</soapenv:Header>
		<soapenv:Body>
		<trk:TrackRequest>
		<common:Request>
		<common:RequestOption>1</common:RequestOption><
		/common:Request>
		<trk:InquiryNumber>$tn</trk:InquiryNumber>
		<trk:Locale>fr_FR</trk:Locale></trk:TrackRequest></soapenv:Body>
		</soapenv:Envelope>
		EOL;*/
		
		// Test with json Restful

		// $url = "https://onlinetools.ups.com/track/v1/details/".$tn."?locale=en_US";

		// $params = array(
		// 	"warehouse" => $warehousesStart,
		// 	"warhouse2" => $warehousesEnd,
		// 	"limit" => $productsSearchLimit
		// );
		
		// $ch = curl_init( );
		// curl_setopt( $ch, CURLOPT_URL, $urlOratioGetDeliveries );
		// curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		// curl_setopt( $ch, CURLOPT_POST, 1);
		// curl_setopt( $ch, CURLOPT_POSTFIELDS, $params);
		// $result = json_decode(curl_exec( $ch ));
		// curl_close( $ch );









		$req = 	'<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:v1="http://www.ups.com/XMLSchema/XOLTWS/UPSS/v1.0" xmlns:v3="http://www.ups.com/XMLSchema/XOLTWS/Track/v2.0" xmlns:v11="http://www.ups.com/XMLSchema/XOLTWS/Common/v1.0">
				<soapenv:Header> <v1:UPSSecurity>
				<v1:UsernameToken> <v1:Username>HOALEN</v1:Username> <v1:Password>'.$password.'</v1:Password>
				</v1:UsernameToken> <v1:ServiceAccessToken>
				<v1:AccessLicenseNumber>3D92F36B4929A315</v1:AccessLicenseNumber> </v1:ServiceAccessToken>
				</v1:UPSSecurity> </soapenv:Header> <soapenv:Body>
				<v3:TrackRequest> <v11:Request>
				<v11:RequestOption>1</v11:RequestOption> <v11:TransactionReference>
				<v11:CustomerContext>Your Test Case Summary Description</v11:CustomerContext> </v11:TransactionReference>
				</v11:Request>
				<v3:InquiryNumber>'.$tn.'</v3:InquiryNumber> </v3:TrackRequest>
				</soapenv:Body> </soapenv:Envelope>';
				

		// $ch = curl_init(_TRACKING_URL);
	
	    $header = array(
	        "Content-type: text/xml;charset=\"utf-8\"",
	        "Accept: text/xml",
	        "Cache-Control: no-cache",
	        "Pragma: no-cache",
	        "SOAPAction: \"run\"",
	        "Content-length: ".strlen($req),
	    );

	    $soap_do = curl_init();
	    curl_setopt($soap_do, CURLOPT_URL, _TRACKING_URL);
	    curl_setopt($soap_do, CURLOPT_CONNECTTIMEOUT, 10);
	    curl_setopt($soap_do, CURLOPT_TIMEOUT,        10);
	    curl_setopt($soap_do, CURLOPT_RETURNTRANSFER, true );
	    curl_setopt($soap_do, CURLOPT_SSL_VERIFYPEER, false);
	    curl_setopt($soap_do, CURLOPT_SSL_VERIFYHOST, false);
	    curl_setopt($soap_do, CURLOPT_POST,           true );
	    curl_setopt($soap_do, CURLOPT_POSTFIELDS,     $req);
	    curl_setopt($soap_do, CURLOPT_HTTPHEADER,     $header);
	    $result = curl_exec($soap_do);

		


		
	    if($result === false) {
	        $err = 'Curl error: ' . curl_error($soap_do);
	        curl_close($soap_do);
	        print $err;
	    } else {
	        $httpcode = curl_getinfo($soap_do, CURLINFO_HTTP_CODE);
	        curl_close($soap_do);	


			//$xml2 = new SimpleXMLElement($result);
			//echo $xml2->{'soapenv:envelope'}->{'soapenv:header'}->{'soapenv:body'}->{'soapenv:fault'}->faultcode;
			//echo '<br>ici';
			//echo $xml2->{'soapenv:envelope'}->{'soapenv:header'}->{'soapenv:body'}->{'trk:trackresponse'}->{'common:response'}->{'common:responsestatus'}->{'common:description'};
			//print_r($xml2->xpath('//soapenv:Envelope/soapenv:Header/soapenv:Body/trk:TrackResponse/common:Response/common:ResponseStatus/common:Description'));
			//print_r($xml2->{'soapenv:envelope'}->{'soapenv:header'});



			//echo '</br>';

//          echo $httpcode;
        	$xml = simplexml_load_string($result, NULL, NULL, "https://schemas.xmlsoap.org/soap/envelope/");

			$tracking = parse_xml_into_array($result);

			
			//echo json_encode($json);

			/* Datas Model

			
			"soapenv_Body": {
				"trk_TrackResponse": {
					"common_Response": {
						"common_ResponseStatus": {
						"common_Code": "1",
						"common_Description": "Success"
						},
						"common_TransactionReference": {
						"common_CustomerContext": "Your Test Case Summary Description"
						}
					},
					"trk_Shipment": {
						"trk_InquiryNumber": {
							"trk_Code": "01",
							"trk_Description": "ShipmentIdentificationNumber",
							"trk_Value": "1ZW63Y846896964176"
						},
						"trk_ShipperNumber": "W63Y84",
						"trk_ShipmentAddress": [
						{
							"trk_Type": {
								"trk_Code": "01",
								"trk_Description": "Shipper Address"
							},
							"trk_Address": {
								"trk_AddressLine": "430 ZA DU HELLEZ",
								"trk_City": "PLOUGUERNEAU",
								"trk_PostalCode": "29880",
								"trk_CountryCode": "FR"
							}
						},
						{
							"trk_Type": {
								"trk_Code": "02",
								"trk_Description": "ShipTo Address"
							},
								"trk_Address": {
								"trk_City": "BARCELONA",
								"trk_StateProvinceCode": "XX",
								"trk_PostalCode": "08017",
								"trk_CountryCode": "ES"
							}
						}
						],
						"trk_ShipmentWeight": {
							"trk_UnitOfMeasurement": {
								"trk_Code": "KGS"
							},
							"trk_Weight": "0.90"
						},
						"trk_Service": {
							"trk_Code": "011",
							"trk_Description": "UPS Standard"
						},
						"trk_ReferenceNumber": [
							{
								"trk_Code": "13",
								"trk_Value": "W63Y84VBQV8"
							},
							{
								"trk_Code": "16",
								"trk_Value": "93120"
							}
						],
						"trk_PickupDate": "20211129",
						"trk_Package": {
							"trk_TrackingNumber": "1ZW63Y846896964176",
							"trk_Activity": [
								{
									"trk_ActivityLocation": {
										"trk_Address": {
											"trk_City": "BARCELONA",
											"trk_PostalCode": "08017",
											"trk_CountryCode": "ES"
										},
										"trk_Code": "M1",
										"trk_Description": "Residential",
										"trk_SignedForByName": "PILAR C"
									},
									"trk_Status": {
										"trk_Type": "D",
										"trk_Description": "Delivered",
										"trk_Code": "KB"
									},
									"trk_Date": "20211201",
									"trk_Time": "110059"
									},
								{
									"trk_ActivityLocation": {
										"trk_Address": {
											"trk_City": "Barcelona",
											"trk_CountryCode": "ES"
										},
										"trk_Description": "Residential"
									},
									"trk_Status": {
										"trk_Type": "I",
										"trk_Description": "Out For Delivery",
										"trk_Code": "OF"
									},
									"trk_Date": "20211201",
									"trk_Time": "041542"
								},
								{
									"trk_ActivityLocation": {
										"trk_Address": {
										"trk_City": "Barcelona",
										"trk_CountryCode": "ES"
										},
										"trk_Description": "Residential"
									},
									"trk_Status": {
										"trk_Type": "I",
										"trk_Description": "Arrived at Facility",
										"trk_Code": "AR"
									},
									"trk_Date": "20211130",
									"trk_Time": "202000"
								},
								{
									"trk_ActivityLocation": {
										"trk_Address": {
										"trk_City": "Corbeil Essonnes",
										"trk_CountryCode": "FR"
										},
										"trk_Description": "Residential"
									},
									"trk_Status": {
										"trk_Type": "I",
										"trk_Description": "Departed from Facility",
										"trk_Code": "DP"
									},
									"trk_Date": "20211130",
									"trk_Time": "062600"
								},
								{
									"trk_ActivityLocation": {
										"trk_Address": {
										"trk_City": "Corbeil Essonnes",
										"trk_CountryCode": "FR"
										},
										"trk_Description": "Residential"
									},
									"trk_Status": {
										"trk_Type": "I",
										"trk_Description": "Arrived at Facility",
										"trk_Code": "AR"
									},
									"trk_Date": "20211130",
									"trk_Time": "021900"
								},
								{
									"trk_ActivityLocation": {
										"trk_Address": {
										"trk_City": "Saint-Jacques-de-la-Lande",
										"trk_CountryCode": "FR"
										},
										"trk_Description": "Residential"
									},
									"trk_Status": {
										"trk_Type": "I",
										"trk_Description": "Departed from Facility",
										"trk_Code": "DP"
									},
									"trk_Date": "20211129",
									"trk_Time": "215500"
								},
								{
									"trk_ActivityLocation": {
										"trk_Address": {
										"trk_City": "Saint-Jacques-de-la-Lande",
										"trk_CountryCode": "FR"
										},
										"trk_Description": "Residential"
									},
									"trk_Status": {
										"trk_Type": "I",
										"trk_Description": "Arrived at Facility",
										"trk_Code": "AR"
									},
									"trk_Date": "20211129",
									"trk_Time": "200000"
								},
								{
									"trk_ActivityLocation": {
										"trk_Address": {
										"trk_City": "Chateaulin",
										"trk_CountryCode": "FR"
										},
										"trk_Description": "Residential"
									},
									"trk_Status": {
										"trk_Type": "I",
										"trk_Description": "Departed from Facility",
										"trk_Code": "DP"
									},
									"trk_Date": "20211129",
									"trk_Time": "174500"
								},
								{
									"trk_ActivityLocation": {
										"trk_Address": {
										"trk_City": "Chateaulin",
										"trk_CountryCode": "FR"
										},
										"trk_Description": "Residential"
									},
									"trk_Status": {
										"trk_Type": "I",
										"trk_Description": "Origin Scan",
										"trk_Code": "OR"
									},
									"trk_Date": "20211129",
									"trk_Time": "172759"
								},
								{
									"trk_ActivityLocation": {
										"trk_Address": {
										"trk_City": "Chateaulin",
										"trk_CountryCode": "FR"
										},
										"trk_Description": "Residential"
									},
									"trk_Status": {
										"trk_Type": "P",
										"trk_Description": "Pickup Scan",
										"trk_Code": "PU"
									},
									"trk_Date": "20211129",
									"trk_Time": "160709"
								},
								{
									"trk_ActivityLocation": {
										"trk_Address": {
										"trk_CountryCode": "FR"
										},
										"trk_Description": "Residential"
									},
									"trk_Status": {
										"trk_Type": "M",
										"trk_Description": "Shipper created a label, UPS has not received the package yet.",
										"trk_Code": "MP"
									},
									"trk_Date": "20211129",
									"trk_Time": "092850"
								}
							],
							"trk_PackageWeight": {
								"trk_UnitOfMeasurement": {
								"trk_Code": "KGS"
								},
								"trk_Weight": "0.90"
							},
							"trk_ReferenceNumber": {
								"trk_Code": "19",
								"trk_Value": "93120"
							}
						}
					}
				}
			
			}

			*/
			$success = false;

			if (isset($tracking['soapenv_Body']['trk_TrackResponse']['common_Response']['common_ResponseStatus']['common_Code']) == "1") {
				$success = true;
			}


			if ($success) {

				$code = $tracking['soapenv_Body']['trk_TrackResponse']['trk_Shipment']['trk_Package']['trk_Activity'][0]['trk_Status']['trk_Code'];
				$description = $tracking['soapenv_Body']['trk_TrackResponse']['trk_Shipment']['trk_Package']['trk_Activity'][0]['trk_Status']['trk_Description'];
				


				//$xml->registerXPathNamespace('trk', 'https://www.ups.com/XMLSchema/XOLTWS/Track/v2.0'); // http://www.ups.com/XMLSchema/XOLTWS/Track/v2.0
			


				// if (isset($xml->xpath('//soapenv:Envelope/soapenv:Body/trk:fault/trk:faultstring')[0])) {
				// 	echo $xml->xpath('//soapenv:Envelope/soapenv:Body/trk:fault/trk:faultstring')[0]->__toString();
				// 	echo 'ici';
				// } else {
				// 	echo 'pas trouvé';
				// }


				// $code = $xml->xpath('//soapenv:Envelope/soapenv:Body/trk:TrackResponse/trk:Shipment/trk:Package/trk:Activity/trk:Status/trk:StatusCode')[0]->__toString();
				// $description = $xml->xpath('//soapenv:Envelope/soapenv:Body/trk:TrackResponse/trk:Shipment/trk:Package/trk:Activity/trk:Status/trk:Description')[0]->__toString();
		
		
		
		
				/* Code Label
		
				code = MP -> Shipper created a label, UPS has not received the package yet.
				code = 01 -> IGNORE (CUSTOMER ASKED CHANGES)
		
				code = MP -> ups en charge : 20
		
				code = OR : Origin Scan : 24
				code = PU -> ups en ATTENTE : 24
				code = OF : Out For Delivery : 24
				code = DS -> ups Processing at UPS Facility : 24
				code = DP -> Departed from Facility : 24
				code = VM : The delivery change was completed. / Delivery could not be made to the original UPS Access Point™. Pkg will be delivered to another nearby UPS Access Point™. : 24
				code = AR : Arrived at Facility : 24
				code = 55 : The receiving business was closed and delivery has been rescheduled for the next business day. : 24
				code = 5R : Customer was not available when UPS attempted delivery. Will deliver to a nearby UPS Access Point™ for customer pick up. : 24
				code = 3J : UPS Access Point™ location closed and package not delivered. Another attempt will be made to UPS Access Point™ location. : 24
				code = 48 : The receiver was not available for delivery. We'll make a second attempt the next business day.: 24
				code = MF : This package is being held for a future delivery date.: 24
				code = IP : Import Scan // Cette identification électronique vous indique que l'envoi a passé la procédure d'importation dans le pays de destination. : 24
				code = AG : The address is incomplete. This may delay delivery. We're attempting to update the address. / The address was corrected. : 24
				code =  C5 : Uncontrollable events have delayed delivery. We are adjusting delivery plans as quickly as possible. Please check back on the next business day for updates. : 24
				code =  AD : The company or receiver name is incorrect. This will delay delivery. We're attempting to update this information. / We've contacted the sender. : 24
				code =  G3 : We tried to deliver to the business, but it was closed. A second attempt will be made the next business day. : 24
				code =  ZC : The delivery date has been rescheduled as the receiver requested. : 24
				code =  A7 : The receiver has moved. We will deliver the package to the receiver's new address. : 24
				code =  UD : The receiver requested an alternate delivery address. The request is in progress. : 24

				code = 34 : We've incorrectly sorted this package which may cause a delay. : 25 -> Problème lors de la livraison
				code = UF : We're in the process of returning this package to the sender. / We're unable to deliver your package on schedule. : 25 -> Problème lors de la livraison
				code = S7 : The receiver requested this package to be held for pickup at the UPS facility. / The package will be returned to the sender. : 25 -> Problème lors de la livraison
				code = 6C : The maximum days to hold the package at the UPS Access Point™ location expired. The package will be returned. : 25 -> Problème lors de la livraison
				code = AY : The receiving business was closed at the time of the final delivery attempt. / The package will be returned to the sender. : 25 -> Problème lors de la livraison
				code = KR : The receiver does not want the product and refused the delivery. : 25 -> Problème lors de la livraison
				code = UP : We've begun an investigation to locate the package. : 25 -> Problème lors de la livraison
				code = UP : We're attempting to verify the package location. / Investigation only. : 25 -> Problème lors de la livraison
				
				code = ZP : Delivered to UPS Access Point™ location and awaiting customer pickup. : 26
				code = 2Q : Delivered to UPS Access Point™ : 26
				code = 5G : The package remains at the UPS Access Point™ location and is reaching the maximum days allowed to be held. -> Order State Number : 26
				
				
				code = KD : Delivered -> Order State Number : 21 -> Livrée
				code = KM -> Delivered -> Order State Number : 21 -> Livrée
				code = DL -> Delivered -> Order State Number : 21 -> Livrée
				code = 011 -> Delivered -> Order State Number : 21 -> Livrée
				code = KB -> Delivered -> Order State Number : 21 -> Livrée
				code = 9E -> Delivered -> Order State Number : 21 -> Livrée
				code = FS -> Delivered -> Order State Number : 21 -> Livrée
				code = KE -> Delivered -> Order State Number : 21 -> Livrée

				code = 2W -> Delivered -> Order State Number : 27 -> Récupéré en point retrait

				ignorés :
				KR : The receiver does not want the product and refused the delivery.
				AY : The receiving business was closed at the time of the final delivery attempt.
				AY : The receiving business was closed at the time of the final delivery attempt. / We've attempted to contact the receiver regarding pickup arrangements.
				T3 : The sender requested that we hold this package.
				C8 : The receiver is no longer in business. / The receiver arranged to pick up the package at a UPS facility. Pickup must be made within 5 business days.

				
				*/
				
				$newOrderStateNumber = 0;
				$eventCode = trim((string)$code);

				$array_ignored = array('5X', 'KR', 'AY', 'T3', 'C8','AM', 'HL','TB');
		
				$array_transit  = array('PU', 'DS', 'DP', 'VM', 'AR', '55', 'OF', '5R', '3J', 'OR', '5G','48', 'MF', 'IP', 'AG', 'C5', 'AD', 'G3','ZC','A7','UD');

				$array_all      = array('PU', 'DS', 'DP', 'VM', 'AR', '55', 'OF', '5R', '3J', 'OR', 'MP', 'DL', 'KM', '011', '01', 'ZP', '2Q', 'KB', '9E','5G','34','UF','S7','48', 'FS', 'MF','IP', 'AG', 'C5', 'AD', 'G3','KE','ZC','A7','UD','KD', '6C','UP');
		
				if ($eventCode == 'DL' || $eventCode == 'KM' || $eventCode == '011' || $eventCode == 'KB' || $eventCode == '9E' || $eventCode == 'FS' || $eventCode == 'KE' || $eventCode == 'KD') $newOrderStateNumber = 21; // Livrée (à domicile)
				else if (in_array($eventCode, $array_transit)) $newOrderStateNumber = 24;
				// else if ($eventCode == '01') $newOrderStateNumber = 24; // ignoré : le client demande un changement de Point Relais par exemple, ça n'affecte pas notre workflow
				// else if ($eventCode == 'MP') $newOrderStateNumber = 20; // ignoré : le prêt pour l'expédition ne doit pas être traité ici
				else if ($eventCode == '34') $newOrderStateNumber = 25;
				else if ($eventCode == 'UF') $newOrderStateNumber = 25;
				else if ($eventCode == '6C') $newOrderStateNumber = 25;
				else if ($eventCode == 'KR') $newOrderStateNumber = 25;
				else if ($eventCode == 'AY') $newOrderStateNumber = 25;
				else if ($eventCode == 'S7') $newOrderStateNumber = 25;
				else if ($eventCode == 'UP') $newOrderStateNumber = 25;
				else if ($eventCode == 'ZP') $newOrderStateNumber = 26;
				else if ($eventCode == '2Q') $newOrderStateNumber = 26;
				else if ($eventCode == '2W') $newOrderStateNumber = 27; // Récupérée (en point relais)


				if ($newOrderStateNumber != $order['current_state'] && $newOrderStateNumber != 0) {
			

			
					$history = new OrderHistory();
					$history->id_order = (int)$order['id_order'];
					
		
					if ($newOrderStateNumber == 20) {
						// à filter car l'export vers oratio en est altéré
						if ($order['current_state'] >= 4) {
							$history->changeIdOrderState($newOrderStateNumber, (int)$order['id_order']); // update current state in ps_orders
							$saved = $history->save(); // insert state in ps_order_history
							//error_log('$history->save : '.$saved);
							$compteur++;
						}
						else {
							$compteur_unsaved++;
	//		            	error_log('no $history->save as old status not oratio proof yet '.$order['current_state']. ' for order id : '.$order['id_order']);
						}
					} else if ($newOrderStateNumber == 24) {
						// à filter car l'export vers oratio en est altéré
						if ($order['current_state'] == 5) {
							$history->changeIdOrderState($newOrderStateNumber, (int)$order['id_order']); // update current state in ps_orders
							$saved = $history->save();  // insert state in ps_order_history
							$compteur++;
							//error_log('$history->save : '.$saved);
						}
						else {
							$compteur_unsaved++;
	//		            	error_log('no $history->save as old status not SHIPPED yet '.$order['current_state']. ' for order id : '.$order['id_order']);
						}
					} else {
						$history->changeIdOrderState($newOrderStateNumber, (int)$order['id_order']); // update current state in ps_orders
						$saved = $history->save();  // insert state in ps_order_history
						$compteur++;
						//error_log('$history->save : '.$saved);
						
					}
						
		
		
				} else {

					$compteur_unsaved++;
					if (!in_array($eventCode, $array_all) && !in_array($eventCode, $array_ignored)) {
						error_log("ups tracking : ".$order['id_order']." :(no update OR NOT REFERENCED YET IN THE CODE !!!) : ".$eventCode." : ".$description);
					} else {
						// error_log("ups tracking : ".$order['id_order']." :(no update) : ".$eventCode);
					}
				}

			}

			
	    }

	}

//error_log('tracking ups end with '.$compteur.' order\'s statuts updated with success AND '.$compteur_unsaved.' untouched.');
	
?>

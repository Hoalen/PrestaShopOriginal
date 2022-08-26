<?php
/**
* MODULE PRESTASHOP non OFFICIEL Fedex by hoalen
*/

//header('Content-type: text/plain');
//header('Content-Type: application/xml');
include('../config/config.inc.php');

/*
define('_TRACKING_URL', "https://gatewaybeta.fedex.com:443/xml");
define('_TRACKING_URL', "https://gateway.fedex.com:443/xml");
*/
define('_TRACKING_URL', "https://ws.fedex.com:443/web-services");

/* No more than one update per hour */
//if (time() - (int)Configuration::get('CHRONO_TRACKING_LAST_UPDATE') < 1800) {
    //die('NO UPDATE');
//}

/* Orders in state "shipping", for our carriers, with a tracking number */

$query = 'SELECT oc.id_order, oc.tracking_number, o.current_state FROM '._DB_PREFIX_.'order_carrier oc 
LEFT JOIN '
    ._DB_PREFIX_.'orders o ON o.id_order=oc.id_order LEFT JOIN '._DB_PREFIX_.'carrier c ON o.id_carrier=c.id_carrier
WHERE c.id_reference IN (30,31,32,33,40,79)
    AND oc.tracking_number!=""  
    AND o.current_state IN (5,24,25,26) 
    AND o.date_add > (NOW() - INTERVAL 30 DAY)';
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

print "<pre>";

//var_dump($query);
//$i = 0;
foreach ($orders as $order) {
    $tn = $order['tracking_number'];
//    $orders_events[$order['id_order']] = array('tracking_number'=>$tn,'events'=>array());
// if ($i > 180) {
//     $i++;
//     BREAK;
// }
//    var_dump($order['id_order']);


//    var_dump($tn);

$req = <<<EOL
<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:SOAP-ENC="http://schemas.xmlsoap.org/soap/encoding/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns="http://fedex.com/ws/track/v18">
   <SOAP-ENV:Body>
      <TrackRequest>
         <WebAuthenticationDetail>
            <UserCredential>
               <Key>j64mkIz4FhnZb0Aw</Key>
               <Password>PcOD4lwQqLw8QakM7ZAiQl5Kp</Password>
            </UserCredential>
         </WebAuthenticationDetail>
         <ClientDetail>
            <AccountNumber>355362744</AccountNumber>
            <MeterNumber>252582543</MeterNumber>
         </ClientDetail>
         <TransactionDetail>
            <CustomerTransactionId>Track By Number_v18</CustomerTransactionId>
            <Localization>
               <LanguageCode>EN</LanguageCode>
            </Localization>
         </TransactionDetail>
         <Version>
            <ServiceId>trck</ServiceId>
            <Major>18</Major>
            <Intermediate>0</Intermediate>
            <Minor>0</Minor>
         </Version>
         <SelectionDetails>
            <PackageIdentifier>
               <Type>TRACKING_NUMBER_OR_DOORTAG</Type>
               <Value>$tn</Value>
            </PackageIdentifier>
         </SelectionDetails>
         <ProcessingOptions>INCLUDE_DETAILED_SCANS</ProcessingOptions>
      </TrackRequest>
   </SOAP-ENV:Body>
</SOAP-ENV:Envelope>
EOL;

//    $ch = curl_init(_TRACKING_URL);

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
        curl_close($soap_do);
//        echo $result;
        $xml = simplexml_load_string($result, NULL, NULL, "http://schemas.xmlsoap.org/soap/envelope/");
//        $ns = $xml->getNamespaces(true);
//        $soap = $xml->children($ns['SOAP-ENV']);
//        var_dump($soap->Body->children($ns['SOAP-ENV']));

        $xml->registerXPathNamespace('TrackReply', 'http://fedex.com/ws/track/v18');
        $code = $xml->xpath('//SOAP-ENV:Envelope/SOAP-ENV:Body/TrackReply:TrackReply/TrackReply:CompletedTrackDetails/TrackReply:TrackDetails/TrackReply:StatusDetail/TrackReply:Code')[0]->__toString();
//        error_log($code);
/*        var_dump($elements);
die;
        foreach ($elements as $element) {
            $events[$element->EventType->__toString()] = $element->EventDescription->__toString();
            $orders_events[$order['id_order']]['events'][$element->EventType->__toString()] = $element->EventDescription->__toString();
        }
//        $result = json_decode($elements[0], true);
//        print_r($result);*/
/*  /* Code Label

		OC -> Shipment information sent to FedEx

		PU -> Picked up -> Order State Number : 24
		IT -> In transit -> Order State Number : 24
		DP -> Left/Departed FedEx origin facility -> Order State Number : 24
		CC -> International shipment release - Export/Import -> Order State Number : 24
		AR -> Arrived at FedEx location / At destination sort facility / At local FedEx facility -> Order State Number : 24
		AF -> Arrived at FedEx location / At destination sort facility / At local FedEx facility -> Order State Number : 24
        OD -> On FedEx vehicle for delivery -> Order State Number : 24
        RR -> Delivery option requested -> Order State Number : 24
        DE -> Delivery exception -> Order State Number : 24
        SE -> Shipment exception -> Order State Number : 24
        CP -> Clearance in progress -> Order State Number : 24

		DL -> Delivered -> Order State Number : 21
/*/
	    //$newOrderState = false;
	    $newOrderStateNumber = 0;
	    $eventCode = trim((string)$code);
	
	    if ($eventCode == 'DL') $newOrderStateNumber = 21;
	    else if ($eventCode == 'PU' || $eventCode == 'IT' || $eventCode == 'DP' || $eventCode == 'CC' || $eventCode == 'AR' || $eventCode == 'AF') $newOrderStateNumber = 24;
	    else if ($eventCode == 'OD' || $eventCode == 'RR' || $eventCode == 'DE' || $eventCode == 'SE') $newOrderStateNumber = 24;
	    else if ($eventCode == 'CP') $newOrderStateNumber = 24;
	
	    if($newOrderStateNumber != $order['current_state'] && $newOrderStateNumber != 0) {
	
	        //error_log("fedex tracking : ".$order['tracking_number'].' -> '.$newOrderStateNumber);
	
	        $history = new OrderHistory();
	        $history->id_order = (int)$order['id_order'];
	        $history->changeIdOrderState($newOrderStateNumber, (int)$order['id_order']);
	        //error_log('$history->save : '.$history->save());
	    } else {
		    //error_log("fedex tracking : ".$order['id_order']." : ".$eventCode." (no update)");
	    }
    }
//    $i++;
}
//var_dump($events);
//var_dump($orders_events);

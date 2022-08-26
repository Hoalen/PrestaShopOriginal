<?php

    error_reporting(E_ALL);
	ini_set('display_errors', 1);

    include("../config/settings.inc.php");
    $mysqli = new mysqli(_DB_SERVER_, _DB_USER_, _DB_PASSWD_, _DB_NAME_);
    $mysqli->set_charset("utf8mb4");

    $query = "SELECT id_customer, count(id_customer), GROUP_CONCAT(code) FROM `ps_cart_rule`
                WHERE `description` LIKE 'Salted 10% Salt'
                AND `quantity` > 0
                GROUP BY id_customer";

    $usersCode = [];

    if ($stmt = $mysqli->prepare($query)) {
            
        $stmt->execute();
        
        $stmt->store_result();
        $stmt->bind_result( $id_customer, $totalCodes, $codes);

        while($stmt->fetch()) {

            $userCode = new stdClass();
            $userCode->idCustomer = $id_customer;
            $userCode->totalCodes = $totalCodes;
            $userCode->codes = $codes;
            $usersCode[] = $userCode;
        }

    }
    $total = 0;

    $query = "DELETE FROM `ps_cart_rule` WHERE code = ? AND id_customer = ?";

    if ($stmt = $mysqli->prepare($query)) {

        foreach($usersCode as $user) {

            $codes = explode(",",$user->codes);

            $keep = array_shift($codes);
            
            if (count($codes)>0) {
                foreach($codes as $code) {

                    $stmt->bind_param('si',$code, $user->idCustomer);
                    $stmt->execute();
        
        

                    $total++;
                    echo $code.'<br>';
                }
                
            } 
        
            
        }
    }
    echo $total;
?>
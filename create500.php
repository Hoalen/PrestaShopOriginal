<?php
	
	header('Content-Type: text/html; charset=utf-8');
	
	// Error catchable
	 
	try{
    	someUndefinedFunction();
	} catch (Throwable $exception) { //Use Throwable to catch both errors and exceptions
	    header('HTTP/1.1 500 Internal Server Error'); //Tell the browser this is a 500 error
	    //echo $exception->getMessage();
	    echo file_get_contents('error500.html');
	   
	}


	// Error uncatchable
	
	// Custom pages for 500 only work if the cause of the 500 can be handled by Apache to display the page
	// Internal error in .htaccess not relay custom ErrorDocument (!) and simulate 500 error via PHP is not quite possible - when your script run it's too late - error document redirection is handled by apache mod_core (!) and PHP only send status. 
		
?>
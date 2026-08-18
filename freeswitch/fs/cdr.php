<?php
// ##############################################################################
// Flux SBC - Unindo pessoas e negócios
//
// Copyright (C) 2022 Flux Telecom
// Daniel Paixao <daniel@flux.net.br>
// Flux SBC Version 4.0 and above
// License https://www.gnu.org/licenses/agpl-3.0.html
//
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU Affero General Public License as
// published by the Free Software Foundation, either version 3 of the
// License, or (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU Affero General Public License for more details.
//
// You should have received a copy of the GNU Affero General Public License
// along with this program. If not, see <http://www.gnu.org/licenses/>.
// ##############################################################################
ini_set ( "date.timezone", "America/Sao_Paulo" );
define ( 'ENVIRONMENT', 'production' );
if (defined ( 'ENVIRONMENT' )) {
	switch (ENVIRONMENT) {
		case 'development' :
			// error_reporting(E_ALL);
			error_reporting ( E_ERROR | E_WARNING | E_PARSE );
			break;
		
		case 'testing' :
		case 'production' :
			error_reporting ( 0 );
			break;
		
		default :
			exit ( 'The application environment is not set correctly.' );
	}
}

include ("lib/flux.db.php");
include ("lib/flux.logger.php");
include ("lib/flux.fslogger.php");
include ("lib/flux.lib.php");
include ("lib/flux.constants.php");
//load custom file.
include ("lib/flux.custom.php");
include ("lib/flux.cdr.php");

// Define db object
$db = new db ();

// Get default configuration
$lib = new lib ();
$config = $lib->get_configurations ( $db );

$cdrDir = rtrim($config['cdr_log_dir'] ?? '/var/log/freeswitch/json_cdr', '/') . '/';

$doneDir  = $cdrDir . 'json_cdr_done/';
$errorDir = $cdrDir . 'json_cdr_error/';

// Set default decimal points
$decimal_points = ($config ['decimal_points'] <= 0) ? 2 : $config ['decimal_points'];

// Define logger object
$logger = new logger ( $lib );
$fslogger = new fslogger ( $lib );

if (isset ( $_SERVER ["CONTENT_TYPE"] ) && $_SERVER ["CONTENT_TYPE"] == "application/json") {
	
	$db->run ( "SET NAMES utf8" );
	//$data = json_decode ( file_get_contents ( "php://input" ), true );
	$data = file_get_contents("php://input");
    $data = utf8_encode($data);
	$data = json_decode($data,true);

	// error_log(print_r($data,true));
	$fslogger->log ( print_r ( $data, true ) );

	if (isset($data ['variables']['module_name'])){
		$fslogger->log("Looking for custom module file to include : " . "lib/addons/flux.".$data ['variables']['module_name'].".php");
		if (file_exists("lib/addons/flux.".$data ['variables']['module_name'].".php")){
			include_once("lib/addons/flux.".$data ['variables']['module_name'].".php");			
		}
	}

	//To run custom code
	if(function_exists('custom_start_hook'))
		custom_start_hook($data, $db, $logger, $decimal_points,$config);

	if ($data ['variables'] ['calltype'] == "CALLINGCARD") {
		if (isset ( $data ['variables'] ['originating_leg_uuid'] )) {
			$process_data=process_cdr ( $data, $db, $fslogger, $decimal_points,$config );
		}
	} else {
		$process_data=process_cdr ( $data, $db, $fslogger, $decimal_points,$config );
	}

	//To run custom code.
	if(function_exists('custom_end_hook'))
		custom_end_hook($data, $db, $fslogger, $decimal_points,$config,$process_data);
		
	if (file_exists("lib/addons/flux.fraud_detection.php")){
			include_once("lib/addons/flux.fraud_detection.php");
			if(function_exists('custom_fraud_hook')){custom_fraud_hook($data, $db, $fslogger, $decimal_points,$config,$process_data);}
	}
}
else {
$batchSize = 50;
$fslogger->log ("cdr_file start");

while (true) {
    $files = glob($cdrDir . "*.json");
    if (empty($files)) {
        $fslogger->log ("files empty");
        sleep(1);
        continue;
    }

    $batch = array_slice($files, 0, $batchSize);

    foreach ($batch as $file) {
        $json = json_decode(file_get_contents($file), true);
        if (!$json) {
            $fslogger->log ("no json");
            rename($file, $errorDir . basename($file));
            continue;
        } 
        else {        
        $fslogger->log ("cdr_file call function process_file_cdr");
        $fslogger->log("cdr_json ".$file.": " . json_encode($json));
        $process_data=process_file_cdr ( $json, $db, $logger, $decimal_points,$config );
        $fslogger->log("process_data OK: " . json_encode($process_data));
        if ($process_data) {
            rename($file, $doneDir . basename($file));
            $fslogger->log ("file moved: " . $doneDir . basename($file));
        } 
        else {
            rename($file, $errorDir . basename($file));
            $fslogger->log ("file error moved: " . $errorDir . basename($file));
        }
        
        }
    }
    
    break;

}
}
?>

<?php
/**
	$Id: savefirmware.php 143 2006-07-17 15:23:48Z prices $
	@file scripts/misc/savefirmware.php
	@brief Loads a program into a 0039-21 controller board remotely.
	
	$Log: savefirmware.php,v $
	Revision 1.4  2006/02/02 14:54:36  prices
	Periodic checkin
	
	Revision 1.3  2005/06/11 17:41:54  prices
	Fixed a minor problem.
	
	Revision 1.2  2005/06/01 20:44:52  prices
	Updated them to work with the new setup.
	
	Revision 1.1  2005/06/01 15:03:28  prices
	Moving from the 08/scripts directory.  This is the new home.
	
	Revision 1.2  2005/05/10 13:39:31  prices
	Periodic Checkin
	
	Revision 1.1  2005/04/12 02:02:21  prices
	Inception.
	
*/
/**
 * @cond SCRIPT
 */

	if (empty($argv[1]) || !file_exists($argv[2]) || !file_exists($argv[3])) {
		die("Usage: ".$argv[0]." <version> <Code File> <Data File> <file Type> <firmwarePart> <hardwarePart> <status> <cvstag></cvstag>\r\n");	
	}
	require_once(dirname(__FILE__).'/../head.inc.php');
	require_once("firmware.inc.php");

	$firmware = new firmware($prefs['servers'], HUGNET_DATABASE, array("dbWrite" => TRUE));
	$Info["FirmwareVersion"] = $argv[1];
	$Info["FirmwareCode"] = implode("", file($argv[2]));
	$Info["FirmwareData"] = implode("", file($argv[3]));
	$Info["FWPartNum"] = $argv[5];
	$Info["HWPartNum"] = $argv[6];
	$Info["Date"] = date("Y-m-d H:i:s");
	$Info["FirmwareFileType"] = $argv[4];
	$Info["FirmwareStatus"] = $argv[7];
	$Info["FirmwareCVSTag"] = $argv[8];
	$Info["Target"] = $argv[9];
	$return = $endpoint->db->AutoExecute('firmware', $Info, 'INSERT');
	if ($return) {
		print "Successfully Added\r\n";
		return(0);
	} else {

		print "Error (".$firmware->Errno."):  ".$firmware->Error."\r\n";
//		print "Query: ".$firmware->wdb->LastQuery."\r\n";
		return($firmware->Errno);
	}
/**
 * @endcond
 */
?>

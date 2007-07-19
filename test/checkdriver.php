<?php
/**
	$Id$
	@file scripts/endpoints/checkdriver.php
	@brief Checks Drivers.
	
	$Log: checkdriver.php,v $
	Revision 1.1  2005/06/01 15:03:28  prices
	Moving from the 08/scripts directory.  This is the new home.
	
	Revision 1.4  2005/05/10 13:39:31  prices
	Periodic Checkin
	
	Revision 1.3  2005/04/05 13:37:27  prices
	Added lots of documentation.
	
	
*/
/**
 * @cond	SCRIPT
*/

	$dfportal_no_session = TRUE;

	include_once("blankhead.inc.php");
	include_once("hugnetservers.inc.php");

	$mhistory = new history_raw();
	$mhistory->DefaultSortBy = "Date desc";

	$mhistory->lookup($argv[1], "DeviceKey");
	print "Found ".count($lhistory->lookup)." records\n";
	$packet=$mhistory->lookup[0];
	$endpoint->device->lookup($packet["DeviceKey"], "DeviceKey");

	$packet = array_merge($packet, $endpoint->device->lookup[0]);


	$packet = $endpoint->DecodeData($packet);

	print strip_tags(get_stuff($packet));
/**
 * @endcond	
*/

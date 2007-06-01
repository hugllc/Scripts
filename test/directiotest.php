<?php
/**
	$Id: directiotest.php 52 2006-05-14 20:51:23Z prices $
	@file scripts/test/directiotest.php
	@brief Tests the directio functions in include/directio.php
	
	$Log: directiotest.php,v $
	Revision 1.1  2005/06/01 15:03:28  prices
	Moving from the 08/scripts directory.  This is the new home.
	
	Revision 1.3  2005/05/10 13:39:31  prices
	Periodic Checkin
	
	Revision 1.2  2005/04/05 13:37:27  prices
	Added lots of documentation.
	
	
*/
/**
 * @cond	SCRIPT
*/

$fd = dio_open('/dev/cuaa0', O_RDWR | O_NOCTTY | O_NONBLOCK);

//dio_fcntl($fd, F_SETFL, O_SYNC);

dio_tcsetattr($fd, array(
  'baud' => 38400,
  'bits' => 8,
  'stop'  => 1,
  'parity' => 0
));

print "Starting...\r\n";
while (1) {

  $data = dio_read($fd, 256);

  if ($data) {
     echo $data;
  }
}


/*
	$dfportal_no_session = TRUE;
	$extra_includes[] = "process.inc.php";
	$extra_includes[] = "directio.inc.php";
	include_once("blankhead.inc.php");

	$endpoint = new endpoint_direct();
	$endpoint->verbose = TRUE;
	$endpoint->connect();

	while(1) {
		$val = $endpoint->rawread();
		if ($val === FALSE) {
			print "Socket Not Open\r\n";
			break;
		} else {
			if (strlen($val) > 0) {
				print $val."\r\n";
			} else {
				usleep(10000);
			}
		}
	}

	$endpoint->close();
*/
/**
 * @endcond
*/
?>

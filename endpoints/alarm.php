<?php
/**
	$Id$
	@file scripts/endpoints/alarm.php
	@brief Stript to check for alarms.
	
	$Log: alarm.php,v $
	Revision 1.2  2005/06/01 20:44:52  prices
	Updated them to work with the new setup.
	
	Revision 1.1  2005/06/01 15:03:28  prices
	Moving from the 08/scripts directory.  This is the new home.
	
	Revision 1.11  2005/05/10 13:39:31  prices
	Periodic Checkin
	
	Revision 1.10  2005/04/05 13:37:27  prices
	Added lots of documentation.
	
	
*/
/**
 * @cond	SCRIPT
*/
	require_once dirname(__FILE__).'/../head.inc.php';

	$uproc = new process($prefs['servers'], HUGNET_DATABASE, "NORMAL", basename(__FILE__));
	$uproc->Register();
	$uproc->CheckRegistered(TRUE);
/*
	$servers = array();
	$servers[0]["Host"] = "localhost"; // Set to your server name or$
	$servers[0]["User"] = "Portal";  // Set to the database username
	$servers[0]["Password"] = "Por*tal"; // Set to the database password
	$servers[0]["AccessType"] = "R";  // R for Read, W for Write, RW for both
	$servers[0]["db"] = "HUGnet";
*/
//	$alarms = new container("", "Alarms", "HUGNet");
//	$alarms->AutoSETS();

	$alarms = new MDB_QueryWrapper($prefs['servers'], HUGNET_DATABASE, array('table' => "Alarms", 'primaryCol' => 'AlarmKey', "dbWrite" => TRUE));

	$endpoint = new driver($prefs['servers'], HUGNET_DATABASE, array());


	$endpoint->device->reset();
	$endpoint->device->setIndex('DeviceKey');
	$devices = $endpoint->device->getAll();

	$count = 0;
	$LastChecked = array();
	$LastEmail = array();
	$AlarmArray = array();
	while(1) {

		$uproc->Checkin();
		$lastminute = date("i");

		$ret = $alarms->getAll();
		$AlarmArray = (count($ret) > 0) ? $ret : array();
		$emailtext = array();
		foreach($AlarmArray as $key => $alarm) {
			if (!isset($LastChecked[$alarm["AlarmKey"]])) $LastChecked[$alarm["AlarmKey"]] = GetNextPoll(strtotime($alarm["LastChecked"]), $alarm["AlarmCheckTime"]);
			if (!isset($LastEmail[$alarm["AlarmKey"]])) $LastEmail[$alarm["AlarmKey"]] = GetNextPoll(strtotime($alarm["LastEmail"]), $alarm["AlarmEmailTime"]);

			print "[".$uproc->me["PID"]."] Checking Alarm ".$alarm["AlarmName"]." ";
			if ($alarm["Active"] == "YES") {
				if ($LastChecked[$alarm["AlarmKey"]] <= time()) {
					$baddev = $alarms->execute($alarm["AlarmSQL"]);

					if (count($baddev) > 0) {
						print " Found ".count($baddev)." Problems ";

						$info["AlarmKey"] = $alarm["AlarmKey"];
						$info["LastFound"] = date("Y-m-d H:i:s");
						$info["LastChecked"] = $info["LastFound"];
						$LastChecked[$alarm["AlarmKey"]] = GetNextPoll(strtotime($info["LastChecked"]), $alarm["AlarmCheckTime"]);

						if ($LastEmail[$alarm["AlarmKey"]] <= time()) {
							$LastEmail[$alarm["AlarmKey"]] = GetNextPoll(strtotime($info["LastChecked"]), $alarm["AlarmEmailTime"]);
							$info["LastEmail"] = $info["LastFound"];
							$emailtext[$alarm["AlarmEmail"]] .= "Found problems on ".$alarm["AlarmName"]."\r\n";
							$devcounter = 0;
							foreach($baddev as $rec) {
								if (isset($dev["DeviceKey"])) {
									if (($devcounter > 500) || ($devcounter > $alarm["AlarmMaxHits"])) break;
									if (isset($devices[$rec["DeviceKey"]]["DeviceID"])) {
										$devcounter++;
										$emailtext[$alarm["AlarmEmail"]] .= "Device ".$devices[$rec["DeviceKey"]]["DeviceID"]."\n";
										$emailtext[$alarm["AlarmEmail"]] .= "		Info: http://www.hugllc.com/HUGnet/info.php?DeviceID=".$devices[$rec["DeviceKey"]]["DeviceID"]."\r\n";
										$emailtext[$alarm["AlarmEmail"]] .= "		History: http://www.hugllc.com/HUGnet/history.php?DeviceID=".$devices[$rec["DeviceKey"]]["DeviceID"]."\r\n";
										$emailtext[$alarm["AlarmEmail"]] .= "\r\n";
									}
								}
							}
						}

						if ($alarms->save($info)) {
							print " Alarm Updated ";
						} else {
							print get_stuff($alarms);
						}


					} else {
						if ($alarms->Errno == 0) {
							print " Fine ";
							$info["AlarmKey"] = $alarm["AlarmKey"];
							$info["LastChecked"] = date("Y-m-d H:i:s");
							$LastChecked[$alarm["AlarmKey"]] = GetNextPoll(strtotime($info["LastChecked"]), $alarm["AlarmCheckTime"]);
							if ($alarms->save($info)) {
								print " Alarm Updated ";
							} else {
								print get_stuff($alarms);
							}
						} else {
							print " Database Error (".$alarms->db->Errno."): ".$alarms->db->Error;
						}
					}
				} else {
					print " Not Time ";
				}
			} else {
				print " Off ";
			}
			print "\n";
		}

		foreach($emailtext as $email => $text) {
			if (mail($email, "HUGnet Problems Identified", $text)) {
				print "[".$uproc->me["PID"]."] Sent Email to ".$email."\n";
			} else {
				print "[".$uproc->me["PID"]."] Message to ".$email." Failed\n";
			}
		}
		/*
			This section pauses until the next minute.		
		*/
		if ($count++ == 5) {
			$uproc->Checkin();
			$count = 0;
		}
		print  "[".$uproc->me["PID"]."] Pausing...\n";
		while(date("i") == $lastminute) {
			sleep(1);
		}

	}

	$uproc->Unregister();
	$uproc->CheckUnregistered(TRUE);

	include_once("blanktail.inc.php");
/**
 * @endcond
*/

/**
	@brief Figures out the next time we should poll
	@param $time
	@param $Interval
	@return The time of the next poll
*/
function GetNextPoll($time, $Interval) {
	if (!is_numeric($time)) {
		$time = strtotime($time);
	}

	$sec = 0; //date("s", $time);
	$min = date("i", $time);
	$hour = date("H", $time);
	$mon = date("m", $time);
	$day = date("d", $time);
	$year = date("Y", $time);

	$nexttime = mktime($hour, ($min + $Interval), $sec, $mon, $day, $year);
	return($nexttime);
}


?>

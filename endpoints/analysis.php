<?php
/**
	$Id: analysis.php 680 2007-03-23 21:40:02Z prices $
	@file scripts/endpoints/analysis/analysis.php
	@brief Stript to check data
	
	
	
*/
/**
 * @cond	SCRIPT
*/

    define("ANALYSIS_VERSION", "0.2.1");
    define("ANALYSIS_PARTNUMBER", "0039260350");  //0039-26-01-P

    print '$Id: analysis.php 680 2007-03-23 21:40:02Z prices $'."\n";
    print 'analysis.php Version '.ANALYSIS_VERSION."\n";
    print "Starting...\n";


	define('ANALYSIS_HISTORY_COUNT', 1000);

	require_once dirname(__FILE__).'/../head.inc.php';
	require_once 'analysis.inc.php';

    for ($i = 0; $i < count($newArgv); $i++) {
        switch($newArgv[$i]) {
            // Gateway IP address
            case "-D":
                $i++;
                $forceStart = $newArgv[$i];
                break;
        }
    }


	$uproc = new process();
	$uproc->Register();
	$uproc->CheckRegistered(TRUE);

//	$endpoint = new driver($prefs['servers'], HUGNET_DATABASE, array('dbWrite' => TRUE));
//	$analysis = new analysis($prefs['servers'], HUGNET_DATABASE, array('dbWrite' => TRUE));
//	$rhistory = new history_raw($prefs['servers'], HUGNET_DATABASE);
//	$rwhistory = new history_raw($prefs['servers'], HUGNET_DATABASE, array('dbWrite' => TRUE));


	$plugins = new plugins(dirname(__FILE__)."/analysis/", "dfp.php", dirname(__FILE__)."/plugins");

	if (is_array($plugins->plugins["Functions"]["preAnalysis"])) {
		foreach($plugins->plugins["Functions"]["preAnalysis"] as $plug) {
			print "Found preAnalysis Plugin: ".$plug["Title"]." (".$plug["Name"].")\r\n";
		}
	}

	for($i = 0; $i < 10; $i++) {
		if (is_array($plugins->plugins["Functions"]["Analysis".$i])) {
			foreach($plugins->plugins["Functions"]["Analysis".$i] as $plug) {
				print "Found Analysis".$i." Plugin: ".$plug["Title"]." (".$plug["Name"].")\r\n";
			}
		}
	}
	if (is_array($plugins->plugins["Functions"]["Analysis"])) {
		foreach($plugins->plugins["Functions"]["Analysis"] as $plug) {
			print "Found Analysis Plugin: ".$plug["Title"]." (".$plug["Name"].")\r\n";
		}
	}
	for($i = 10; $i < 20; $i++) {
		if (is_array($plugins->plugins["Functions"]["Analysis".$i])) {
			foreach($plugins->plugins["Functions"]["Analysis".$i] as $plug) {
				print "Found Analysis".$i." Plugin: ".$plug["Title"]." (".$plug["Name"].")\r\n";
			}
		}
	}
	$_SESSION['Deep'] = FALSE;
	$where = "";
	if (!empty($DeviceID)) {
//		$endpoint->device->setWhere("DeviceID='".$argv[1]."'");
        $where .= " DeviceID = '".$DeviceID."'";
		$_SESSION['Deep'] = TRUE;
        
	}
	
//	$endpoint->device->setIndex('DeviceKey');
//	$devices = $endpoint->device->getAll();
    $query = "SELECT * FROM devices ";
    if (!empty($where)) {
        $query .= " WHERE ".$where;
    }
    $devices = $endpoint->db->getArray($query);

	foreach($devices as $key => $dev) {
		$devices[$key] = $endpoint->DriverInfo($dev);
/*
		if (!empty($argv[2])) {
			$devices[$key]["LastAnalysis"] = $argv[2];
		}
*/
	}
	$processed = 0;
	foreach($devices as $dev) {
//		$uproc->Checkin();
//		$rhistory->reset();

		$temp = $plugins->run_filter($temp, "preAnalysis", $dev);

        // If this is an unassigned device don't do any analysis on it
        if ($dev['GatewayKey'] == 0) continue;

		print "Working with device ".$dev['DeviceID']."\r\n";

		$_SESSION['devInfo'] =& $dev;
        if ($_SESSION['Deep']) $dev['LastAnalysis'] = '0000-00-00 00:00:00';




        $rawbasequery = "SELECT * FROM ".$endpoint->raw_history_table.
                     " WHERE ".
                     " DeviceKey=".$dev['DeviceKey'];
        $orderby = " ORDER BY Date ASC "; 
		$_SESSION['devCache'] =& $dev;
        $basequery = str_replace($endpoint->raw_history_table, $endpoint->getHistoryTable($dev), $rawbasequery);

        if (isset($forceStart)) {
            $res = strtotime($forceStart);            
        } else {
            $query = str_replace("*", "Date", $basequery)." AND  Date >= '".$dev['LastAnalysis']."'".$orderby." LIMIT 0,1";
            $res = $endpoint->db->getArray($query);  
            if (count($res) == 0) continue;
            $res = strtotime($res[0]['Date']);
        }
        foreach(array("Y", "m", "d") as $val) {
            $startdate[$val] = (int) date($val, $res);
        }

		$start = 0;
        $dev['date'] = $res;
        $lastpoll = strtotime($dev['LastPoll']);
		for($day = 0; ($dev['date'] < time()) && ($dev['date'] < $lastpoll); $day++) {
            $dev['date'] = mktime(0, 0, 0, $startdate['m'], $startdate['d']+$day, $startdate['Y']);
            $dev['daystart'] = date("Y-m-d 00:00:00", $dev['date']);
            $dev['dayend'] = date("Y-m-d 23:59:59", $dev['date']);
            $datewhere = "Date >= ".$endpoint->db->qstr($dev['daystart'])." AND Date <= ".$endpoint->db->qstr($dev['dayend']);
            $dev['datewhere'] = $datewhere;

   				print "Looking up ".date("Y-m-d", $dev['date'])." Records... ";
//				$rhistory->setLimit($start, ANALYSIS_HISTORY_COUNT);
			$_SESSION['rawHistoryCache'] = $endpoint->db->getArray($rawbasequery . " AND (" . $datewhere . ") " . $orderby);
			$_SESSION['historyCache'] = $endpoint->db->getArray($basequery . " AND (" . $datewhere . ") " . $orderby);

			if ($_SESSION['historyCache'] === FALSE) break;
			$_SESSION['analysisOut'] = array(
			    "DeviceKey" => $dev['DeviceKey'],
			    "Date" => date("Y-m-d", $dev['date']),
			);
			$count = count($_SESSION['historyCache']);
            $rawcount = count($_SESSION['rawHistoryCache']);
			print 'found: '.$count." Raw: ".$rawcount;
			if ($rawcount > 0) {
				$filterout = array();
                if ($verbose) print "\r\n"; 
				for($i = 0; $i < 10; $i++) {
					$filterout = $plugins->run_filter($filterout, "Analysis".$i, $dev);
				}
				$filterout = $plugins->run_filter($filterout, "Analysis", $dev);
				for($i = 10; $i < 20; $i++) {
					$filterout = $plugins->run_filter($filterout, "Analysis".$i, $dev);
				}
				$processed += $count;
                            
                $endpoint->db->Execute("DELETE FROM analysis WHERE DeviceKey=".$dev['DeviceKey']. " AND Date=".$endpoint->db->qstr($_SESSION['analysisOut']['Date']));
                $ret  = $endpoint->db->AutoExecute("analysis", $_SESSION['analysisOut'], 'INSERT');
                if ($ret) {
                    $update = array(
                        "LastAnalysis" => date("Y-m-d H:i:s", $dev['date']),
                    );
                    $ret  = $endpoint->db->AutoExecute($endpoint->device_table, $update, 'UPDATE', "DeviceKey=".$dev['DeviceKey']);
                    
                } else {
                    
                }
                
			}
//				print " Processed ".$processed." records\r\n";
            print " Done \r\n";
		}
	}
	$uproc->Unregister();
	$uproc->CheckUnregistered(TRUE);
/**
 * @endcond
*/

?>

<?php

	function analysis_averages(&$stuff, &$device) {
        $sTime = microtime(TRUE);
        global $verbose;
		global $endpoint;

   		if ($verbose > 1) print "analysis_averages start\r\n";

        $data = &$_SESSION['historyCache'];
		if ((($device["MinAverage"] != "15MIN") 
			&& ($device["MinAverage"] != "HOURLY")
			&& ($device["MinAverage"] != "DAILY"))
	 		|| (!is_object($endpoint->drivers[$device["Driver"]])))
			{
				//Nothing to do.  None of these averages will be saved
					return($stuff);
		}

		$average_table = $endpoint->getAverageTable($device);
        $deletequery = "DELETE FROM ".$average_table
                      ." WHERE DeviceKey=".$device['DeviceKey']
                      ." AND Date=? AND Type=?";
        $dquery = $endpoint->db->Prepare($deletequery);

		$fifteen = array();
		foreach($data as $row) {

			$time = strtotime($row["Date"]);
			if (date("i",$time) < 15) {
				$hour = date("Y-m-d H:00:00", $time);
			} else if (date("i",$time) < 30) {
				$hour = date("Y-m-d H:15:00", $time);
			} else if (date("i",$time) < 45) {
				$hour = date("Y-m-d H:30:00", $time);
			} else {
				$hour = date("Y-m-d H:45:00", $time);
			}
			$fifteen[$hour]["Count"]++;
			$fifteen[$hour]["DeviceKey"] = $device["DeviceKey"];
			$fifteen[$hour]["Date"] = $hour;
			$fifteen[$hour]["Type"] = "15MIN";
			$fifteen[$hour]["Input"] = $key;
			
			foreach($row as $key => $val) {
				if (strtolower(substr($key, 0, 4)) == "data") {
					$fifteen[$hour]["Data"][substr($key, 4)] += $val;
				}
			}
		}
        $fifteenTotal = $fifteen;

		$hourly = array();
		foreach($fifteen as $min => $row) {
			$time = strtotime($row["Date"]);
			$hour = date("Y-m-d H:00:00", $time);
			$hourly[$hour]["Count"] += $row["Count"];
			$hourly[$hour]["DeviceKey"] = $device["DeviceKey"];
			$hourly[$hour]["Date"] = $hour;
			$hourly[$hour]["Type"] = "HOURLY";
			foreach($fifteen[$min]["Data"] as $key => $val) {
				if (!$device['doTotal'][$key]) {
				    $fifteen[$min]["Data"][$key] = $val/$row["Count"];
				}
				$hourly[$hour]["Data"][$key] += $val;
			}
		}

        $hourlyTotal = $hourly;

		$daily = array();
		$daily["Data"] = array();
		foreach($hourly as $hour => $row) {
			$time = strtotime($row["Date"]);
			$day = date("Y-m-d 5:00:00", $time);
			$daily["Count"] += $row["Count"];
			$daily["DeviceKey"] = $device["DeviceKey"];
			$daily["Date"] = $day;
			$daily["Type"] = "DAILY";
            $colCnt = 0;
			foreach($hourly[$hour]["Data"] as $key => $val) {
				if (!$device['doTotal'][$key]) {
				    $hourly[$hour]["Data"][$key] = $val / $row["Count"];
                }
				$daily["Data"][$key] += $val;
			}
		}

        // Set up most of the SQL query...
        $basequery = "REPLACE INTO ".$average_table." (DeviceKey,Date,Type";
        for($i = 0; $i < $device['NumSensors']; $i++) {
            $basequery .= ",Data".$i."";
        }
        $basequery .= ") VALUES (".$device['DeviceKey'].",?,?";
        for($i = 0; $i < $device['NumSensors']; $i++) {
            $basequery .= ",?";
        }
        $basequery .= ")";
        $query = $endpoint->db->Prepare($basequery);

		if ($verbose) print " Saving Averages: ";

        // Now get the data for the query;
        $hist = array();
        $del = array();
		if ($device["MinAverage"] == "15MIN") {
			$lasterror = "";
			foreach($fifteen as $min => $row) {
			    $del[] = array($row['Date'], $row['Type']);
                $hist[] = analysis_averages_insert($row, $device['NumSensors']);
			}
			if ($verbose) print $lasterror." 15Min ";
		}


		if (($device["MinAverage"] == "15MIN") 
			|| ($device["MinAverage"] == "HOURLY"))
			{
			$lasterror = "";
			foreach($hourly as $hour => $row) {
			    $del[] = array($row['Date'], $row['Type']);
                $hist[] = analysis_averages_insert($row, $device['NumSensors']);
			}
			if ($verbose) print $lasterror." Hourly ";

		}




		if (($device["MinAverage"] == "15MIN") 
			|| ($device["MinAverage"] == "HOURLY")
			|| ($device["MinAverage"] == "DAILY"))
			{
			// Average
			$lasterrror = "";
			foreach($daily["Data"] as $key => $val) {
				if (!$device['doTotal'][$key]) {
				    $daily["Data"][$key] = $val / $daily["Count"];
				}
			}
			if ($verbose) print $lasterror." Daily ";

			$del[] = array($daily['Date'], $daily['Type']);
            $hist[] = analysis_averages_insert($daily, $device['NumSensors']);
//            $ret = $endpoint->db->Execute($dquery, $del);
            if ($verbose) print " Saving... ";
            $qtime = microtime(TRUE);
            $ret = $endpoint->db->Execute($query, $hist);
            if ($verbose) print " Done (".(microtime(TRUE) - $qtime)."s)";
        	if ($ret == FALSE) {
        	    if ($verbose) print "Insert Failed";
        		$lasterror = " Error (".$endpoint->db->MetaError()."): ".$endpoint->db->MetaErrorMsg($endpoint->db->MetaError())." ";
        	}
            
		}


        if ($verbose) print "\r\n";

        $dTime = microtime(TRUE) - $sTime;
		if ($verbose > 1) print "analysis_history_check end (".$dTime."s) \r\n";
    }

	$this->register_function("analysis_averages", "Analysis");

function analysis_averages_insert($row, $count) {

    $ret[] = $row["Date"];
    $ret[] = $row["Type"];
    for($i = 0; $i < $count; $i++) {
        $ret[] = $row['Data'][$i];
    }
    return $ret;
}

?>

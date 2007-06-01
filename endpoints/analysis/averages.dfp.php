<?php

	function analysis_averages($stuff, &$device) {

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

        $deletequery = "DELETE FROM ".$average_table." WHERE DeviceKey=".$device['DeviceKey'];

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



		$hourly = array();
		foreach($fifteen as $min => $row) {
			$time = strtotime($row["Date"]);
			$hour = date("Y-m-d H:00:00", $time);
			$hourly[$hour]["Count"] += $row["Count"];
			$hourly[$hour]["DeviceKey"] = $device["DeviceKey"];
			$hourly[$hour]["Date"] = $hour;
			$hourly[$hour]["Type"] = "HOURLY";
			foreach($fifteen[$min]["Data"] as $key => $val) {
				$fifteen[$min]["Data".$key] = $val/$row["Count"];
				$hourly[$hour]["Data"][$key] += $val;
			}
		}


		$daily = array();
		$daily["Data"] = array();
		foreach($hourly as $hour => $row) {
			$time = strtotime($row["Date"]);
			$day = date("Y-m-d 5:00:00", $time);
			$daily["Count"] += $row["Count"];
			$daily["DeviceKey"] = $device["DeviceKey"];
			$daily["Date"] = $day;
			$daily["Type"] = "DAILY";
			foreach($hourly[$hour]["Data"] as $key => $val) {
				$hourly[$hour]["Data".$key] = $val / $row["Count"];
				$daily["Data"][$key] += $val;
			}
		}

		if ($verbose) print " Saving Averages: ";

		if ($device["MinAverage"] == "15MIN") {
			$lasterror = "";
			foreach($fifteen as $min => $row) {
			    $where = "Date=".$endpoint->db->qstr($row['Date'])." AND Type=".$endpoint->db->qstr("15MIN");
                $endpoint->db->Execute($deletequery." AND ".$where);
                $ret  = $endpoint->db->AutoExecute($average_table, $row, 'INSERT');
				if ($ret == FALSE) {
				    if ($verbose) print "Insert Failed";
//					$lasterror = " Error (".$average->wdb->Errno."): ".$average->wdb->Error." ";
				}
			}
			if ($verbose) print $lasterror." 15Min ";
		}


		if (($device["MinAverage"] == "15MIN") 
			|| ($device["MinAverage"] == "HOURLY"))
			{
			$lasterror = "";
			foreach($hourly as $hour => $row) {
			    $where = "Date=".$endpoint->db->qstr($row['Date'])." AND Type=".$endpoint->db->qstr("HOURLY");
                $endpoint->db->Execute($deletequery." AND ".$where);
                $ret  = $endpoint->db->AutoExecute($average_table, $row, 'INSERT');
				if ($ret == FALSE) {
				    if ($verbose) print "Insert Failed";
				}
			}
			if ($verbose) print $lasterror." Hourly ";

		}




		if (($device["MinAverage"] == "15MIN") 
			|| ($device["MinAverage"] == "HOURLY")
			|| ($device["MinAverage"] == "DAILY"))
			{
			$lasterrror = "";
			foreach($daily["Data"] as $key => $val) {
				$daily["Data".$key] = $val / $daily["Count"];
			}
		    $where = "Date=".$endpoint->db->qstr($daily['Date'])." AND Type=".$endpoint->db->qstr("DAILY");
            $endpoint->db->Execute($deletequery." AND ".$where);
            $ret  = $endpoint->db->AutoExecute($average_table, $daily, 'INSERT');
			if ($ret == FALSE) {
			    if ($verbose) print "Insert Failed";
			}

//			if (!$average->Replace($daily)) {
//				$lasterror = " Error (".$average->wdb->Errno."): ".$average->wdb->Error." ";
//			}
			if ($verbose) print $lasterror." Daily ";
		}
/*
	// Do this stuff only once a day.
	if ($stuff["Date"] != date("Y-m-d")) {

		if (is_object($endpoint->drivers[$device["Driver"]]->history)) {
			$hist = $endpoint->drivers[$device["Driver"]]->history;
			$OldSelect = $hist->Select;
			$hist->SelectAverages();
			$date = strtotime($stuff["Date"]);
			$date += 5*60*60;  // Put the time in the middle of the day at 5am.


			$yearstart = date("Y-1-1 00:00:00", $date);
			$yearend = date("Y-12-31 23:59:59", $date);
			$hist->SetRange("Date", $yearstart, $yearend);
			$hist->lookup($device["DeviceKey"], "DeviceKey");

			if (is_array($hist->lookup[0])) {
				$info = $hist->lookup[0];

				$info["Type"] = "YEARLY";
				$info["Date"] = date("Y-1-1 05:00:00", $date);
				$lasterrror = "";
				if (!$average->Replace($info)) {
					$lasterror = " Error (".$average->wdb->Errno."): ".$average->wdb->Error." ";
				}
				if ($verbose) print $lasterror." Yearly ";
				$hist->lookup = array();
			}




			$monthstart = date("Y-m-1 00:00:00", $date);
			$monthend = date("Y-m-d H:i:s", mktime(23, 59, 59, (date("m", $date)+1), 0, date("Y", $date)));			
			$hist->SetRange("Date", $monthstart, $monthend);
			$hist->lookup($device["DeviceKey"], "DeviceKey");
			if (is_array($hist->lookup[0])) {
				$info = $hist->lookup[0];
				$info["Type"] = "MONTHLY";
				$info["Date"] = date("Y-m-1 05:00:00", $date);
				$lasterrror = "";
				if (!$average->Replace($info)) {
					$lasterror = " Error (".$average->wdb->Errno."): ".$average->wdb->Error." ";
				}
				if ($verbose) print $lasterror." Monthly ";
//print $hist->LastLookupQuery;
//print get_stuff($info, "monthly");
				$hist->lookup = array();
				$info = array();
			}

			$week = $date - (date("w", $date)*86400);
			$weekstart = date("Y-m-d 00:00:00", $week);
			$weekend = date("Y-m-d 23:59:59", ($week+(6*86400)));
			$hist->SetRange("Date", $weekstart, $weekend);
			$hist->lookup($device["DeviceKey"], "DeviceKey");
			if (is_array($hist->lookup[0])) {
				$info = $hist->lookup[0];
				$info["Type"] = "WEEKLY";
				$info["Date"] = date("Y-m-d 05:00:00", $week);
	
				$lasterrror = "";
				if (!$average->Replace($info)) {
					$lasterror = " Error (".$average->wdb->Errno."): ".$average->wdb->Error." ";
				}
				if ($verbose) print $lasterror." Weekly ";
//print $hist->LastLookupQuery;
//print get_stuff($info, "weekly");
				$hist->lookup = array();
				$info = array();
			}

			$hist->Select = $OldSelect;
			$hist->SetRange("Date", FALSE, FALSE);
		}
	}
	*/
        if ($verbose) print "\r\n";

		if ($verbose > 1) print "analysis_history_check end\r\n";
		
		return($stuff);
	}

	$this->register_function("analysis_averages", "Analysis");

?>

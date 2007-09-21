<?php

	function analysis_unsolicited(&$stuff, &$dev) {
        $sTime = microtime(TRUE);
        global $verbose;

		if ($verbose > 1) print "analysis_unsolicited start\r\n";
        global $endpoint;

        $data = &$_SESSION['rawHistoryCache'];
        $stuff = &$_SESSION['analysisOut'];

		$stuff["Powerups"] = 0;
		$stuff["Boredom"] = 0;
		$stuff["Reconfigs"] = 0;

//		$plog = new container("", "PacketLog", "HUGNet");
//		$plog->AutoSETS();
//		$plog->SetRange("Date", $device["RangeStart"], $device["RangeEnd"]);
//		$plog->lookup($device["DeviceKey"], "DeviceKey");


//        $res = $endpoint->db->;
        $query = "SELECT * FROM ".$endpoint->packet_log_table." WHERE ".
                 " DeviceKey= ".$dev['DeviceKey']." AND (".$dev['datewhere'].")";
        $res = $endpoint->db->getArray($query);

        if (is_array($res)) {
    		foreach($res as $log) {
    			switch($log["Command"]) {
    				case "5D":
    					$stuff["Reconfigs"]++;
    					break;
    				case "5E":
    					$stuff["Powerups"]++;
    					break;
    				case "5F";
    					$stuff["Boredom"]++;
    					break;
    				default:
    					break;
    			}
    		}
        }		
		
        $dTime = microtime(TRUE) - $sTime;
		if ($verbose > 1) print "analysis_unsolicited end (".$dTime."s)\r\n";

	}


	$this->register_function("analysis_unsolicited", "Analysis");

?>
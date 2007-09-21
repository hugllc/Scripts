<?php

    /** 
        Here we are moving old devices off of gateways so that they don't bog
        the system down.  If they have not reported in in a significant period
        of time we just remove them from the gateway by setting the GatewayKey = 0
    */
	function analysis_unassigned(&$stuff, &$dev) {
        $sTime = microtime(TRUE);
        global $verbose, $endpoint;

		if ($verbose > 1) print "analysis_unassigned start\r\n";
        global $endpoint;

        if (($dev['PollInterval'] == 0) && ($dev["GatewayKey"] > 0)) {
            $days = 30;

            $cutoff = time() - ($days * 86400);
            if ((strtotime($dev["LastHistory"]) < $cutoff) 
                && (strtotime($dev["LastPoll"]) < $cutoff)
                && (strtotime($dev["LastConfig"]) < $cutoff)
                ) {
                $query = "UPDATE ".$endpoint->device_table
                         ." SET GatewayKey=0 "
                         ." WHERE "
                         ." DeviceKey= ".$dev['DeviceKey'];
        
                $res = $endpoint->db->Execute($query);
                $_SESSION['devInfo']["GatewayKey"] = 0;
                print "Moved to unassigned devices\n";
            }
    	}	
        $dTime = microtime(TRUE) - $sTime;
   		if ($verbose > 1) print "analysis_unassigned end (".$dTime."s )\r\n";
	}


	$this->register_function("analysis_unassigned", "preAnalysis");

?>
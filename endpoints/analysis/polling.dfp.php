<?php

	function analysis_polling(&$here, &$device) {
        $sTime = microtime(TRUE);
        global $verbose;

		if ($verbose > 1) print "analysis_polling start\r\n";

        $data = &$_SESSION['rawHistoryCache'];
        $stuff = &$_SESSION['analysisOut'];
		$stuff["AveragePollTime"] = 0;
		$stuff["Polls"] = 0;
        $stuff['AverageReplyTime'] = 0;
        $stuff['Replies'] = 0;
		$lastpoll = 0;
		foreach($data as $key => $row) {
			if ($row["Status"] == "GOOD") {
				if ($lastpoll != 0) {
       				$stuff["Polls"]++;
					$stuff["AveragePollTime"] += (strtotime($row["Date"]) - $lastpoll)/60;
				}
			}
			$lastpoll = strtotime($row["Date"]);
            if ($row['ReplyTime'] > 0) {
                $stuff['AverageReplyTime'] += $row['ReplyTime'];
                $stuff['Replies']++;
            }
		}
		if ($stuff["Polls"] > 0) {
			$stuff["AveragePollTime"] /= $stuff["Polls"];
		}
		if ($stuff['Replies'] > 0) {
		    $stuff['AverageReplyTime'] /= $stuff['Replies'];
		}
        $dTime = microtime(TRUE) - $sTime;
		if ($verbose > 1) print "analysis_polling end (".$dTime."s) \r\n";

	}


	$this->register_function("analysis_polling", "Analysis");

?>
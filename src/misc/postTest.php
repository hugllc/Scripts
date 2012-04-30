#!/usr/bin/env php
<?php
/**
 * Saves firmware to the database
 *
 * PHP Version 5
 *
 * <pre>
 * Scripts related to HUGnet
 * Copyright (C) 2007-2011 Hunt Utilities Group, LLC
 * Copyright (C) 2009 Scott Price
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 3
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 * </pre>
 *
 * @category   Scripts
 * @package    Scripts
 * @subpackage Misc
 * @author     Scott Price <prices@hugllc.com>
 * @copyright  2007-2011 Hunt Utilities Group, LLC
 * @copyright  2009 Scott Price
 * @license    http://opensource.org/licenses/gpl-license.php GNU Public License
 * @version    SVN: $Id$
 * @link       https://dev.hugllc.com/index.php/Project:Scripts
 *
 */

$data = array(
    "test" => "This is a test",
    "test2" => array(
        "this",
        "is",
        "a",
        "test",
        "also",
    ),
);

var_dump(do_post_request("http://localhost/test.php?test=2", $data));



function do_post_request($url, $postdata, $optional_headers = null)
{
    $params = array(
        'http' => array(
            'method' => 'POST',
            'content' => http_build_query($postdata)."\n",
        )
    );
    if ($optional_headers !== null) {
        $params['http']['header'] = $optional_headers;
    }
    $ctx = stream_context_create($params);
    $fp = @fopen($url, 'rb', false, $ctx);
    if (!$fp) {
        /* Failed, so return false */
        return false;
    }
    $response = @stream_get_contents($fp);
    $return = json_decode($response, true);
    if (is_null($return) && ($response != "null")) {
        $return = $response;
    }
    return $return;
}



?>

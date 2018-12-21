<?php

/*
    This file is part of AXE Ninja.
    https://github.com/elbereth/axeninja-ctl

    AXE Ninja is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    AXE Ninja is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with AXE Ninja.  If not, see <http://www.gnu.org/licenses/>.

 */

DEFINE('DMN_VERSION','1.0.1');

xecho('dmnvotesrrdxport v'.DMN_VERSION."\n");

function do_xport( $start, $end, $step, $filename, $testnet = false ) {

if ($testnet) {
  $testnet = "testnet";
}
else {
  $testnet = '';
}

xecho("RRD DB xport ($start to $end step $step $testnet): ");
$xport = rrd_xport( array( "-s", $start, "-e", $end, "--step", $step,
       "DEF:a=mnvotes2$testnet.rrd:Yea:MAX",
       "DEF:b=mnvotes2$testnet.rrd:Nay:MAX",
       "DEF:c=mnvotes2$testnet.rrd:Abstain:MAX",
       'XPORT:a:Yea',
       'XPORT:b:Nay',
       'XPORT:c:Abstain'
	   )
  );
if ($xport === false) {
  echo "Failed!\n";
  return false;
}
else {
  echo "OK\n";
  foreach($xport["data"] as $key1 => $data1) {
    foreach($xport["data"][$key1]["data"] as $key2 => $data2) {
      if (is_nan($data2)) {
        $xport["data"][$key1]["data"][$key2] = false;
      } else {
        $xport["data"][$key1]["data"][$key2] = intval(round($data2));
      }
    }
  }
}

xecho("JSON Encoding output: ");
$json = json_encode($xport);
if ($json !== false) {
  echo "OK (".strlen($json)." bytes)\n";
  xecho("Writing to file: ");
  if (file_put_contents($filename,$json) !== false) {
    echo "OK\n";
    return true;
  }
  else {
    echo "Error\n";
    return false;
  }
}
else {
  echo "Error\n";
  return false;
}

}

do_xport( "now-3h", "now", "600", "mnvotes-main-3h.json" );
do_xport( "now-24h", "now", "3600", "mnvotes-main-24h.json" );
do_xport( "now-7d", "now", "43200", "mnvotes-main-7d.json" );
do_xport( "now-3h", "now", "600", "mnvotes-test-3h.json", true );
do_xport( "now-24h", "now", "3600", "mnvotes-test-24h.json", true );
do_xport( "now-7d", "now", "43200", "mnvotes-test-7d.json", true );

?>

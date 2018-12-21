<?php

/*
    This file is part of AXE Ninja.
    https://github.com/axerunners/axeninja-ctl

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

xecho('dmnvotesrrd v'.DMN_VERSION."\n");

function getMNVotes($testnet = false) {

if ($testnet) {
  $testnetval = "1";
  $testnetstr = "testnet";
} else {
  $testnetval = "0";
  $testnetstr = "";
}

xecho("Retrieving masternodes votes (testnet=$testnetval): ");
$result = dmn_api_get("/masternodes/votes?testnet=$testnetval",array(),$response);
if ($response['http_code'] == 200) {
  echo "Fetched...";
  $votes = json_decode($result,true);
  if ($votes=== false) {
    echo " Failed to JSON decode!\n";
    die(200);
  }
  elseif (!is_array($votes) || !array_key_exists('data',$votes) || !is_array($votes['data'])) {
    echo " Incorrect data!\n";
    die(202);
  }
  $votes = $votes['data'];
  echo " OK (".count($votes)." entries)\n";
}
else {
  echo "Failed [".$response['http_code']."]\n";
  if ($response['http_code'] != 500) {
    $result = json_decode($result,true);
    if ($result !== false) {
      foreach($result['messages'] as $num => $msg) {
        xecho("Error #$num: $msg\n");
      }
    }
  }
  die(201);
}

$votesyea = 0;
$votesnay = 0;
$votesabstain = 0;
$votestotal = 0;
foreach($votes as $vote) {
  if ($vote["Vote"] == "Yea") {
    $votesyea += $vote["VoteCount"];
  }
  elseif ($vote["Vote"] == "Nay") {
    $votesnay += $vote["VoteCount"];
  }
  else {
    $votesabstain += $vote["VoteCount"];
  }
}
$votestotal = $votesyea+$votesnay+$votesabstain;
xecho("Submitting to RRDtool DB (N:$votesyea:$votesnay:$votesabstain:$votestotal): ");
// rrdtool create mnvotes2.rrd --start 1431101864 DS:Yea:GAUGE:600:U:U DS:Nay:GAUGE:600:U:U DS:Abstain:GAUGE:600:U:U DS:Total:GAUGE:600:U:U RRA:MAX:0.5:1:600 RRA:MAX:0.5:6:700 RRA:MAX:0.5:24:775 RRA:MAX:0.5:288:797
if (rrd_update("mnvotes2$testnetstr.rrd", ["N:$votesyea:$votesnay:$votesabstain:$votestotal"] )) {
  echo "OK\n";
}
else {
  echo "Failed\n";
}

}

getMNVotes();
getMNVotes(true);

?>

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

if (!defined('DMN_SCRIPT') || !defined('DMN_CONFIG') || (DMN_SCRIPT !== true) || (DMN_CONFIG !== true)) {
  die('Not executable');
}

define('DMN_VERSION','1.3.0');

xecho('dmnblockdegapper v'.DMN_VERSION."\n");

if ($argc == 2) {
    if ($argv[1] == "test") {
        $display = "testnet";
        $testnet = 1;
        $uname = 'tp2pool';
    }
    else {
        $display = "mainnet";
        $testnet = 0;
        $uname = 'p2pool';
    }
}
else {
    xecho("Usage: ".$argv[0]." main|test\n");
    die(0);
}

  xecho('Retrieving last month block for '.$display.': ');
  $result = dmn_cmd_get('/blocksgaps',array("interval"=>"P1M",'testnet'=>$testnet),$response);
  if ($response['http_code'] == 200) {
    echo "Fetched...";
    $blocks = json_decode($result,true);
    if ($blocks=== false) {
      echo " Failed to JSON decode!\n";
      die(200);
    }
    elseif (!is_array($blocks) || !array_key_exists('data',$blocks) || !is_array($blocks['data'])) {
      echo " Incorrect data!\n";
      die(202);
    }
    $blocks = $blocks['data'];
    echo " OK (".count($blocks)." entries)\n";

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

  xecho("Finding gaps on $display:\n");

  $prevblock = -1;
  $gaps = array();
  foreach($blocks as $blockindex => $block) {
    if ($prevblock == -1) {
    }
    elseif (($prevblock-1) != $block) {
      if (($prevblock - $block) > 2) {
        xecho("Gap found, missing blocks ".($block+1)." to ".($prevblock-1)."\n");
        $gaps[] = ($block+1)." ".($prevblock-1);
      }
      else {
        xecho("Gap found, missing block ".($prevblock-1)."\n");
        $gaps[] = ($prevblock-1);
      }

    }
    $prevblock = $block;
  }

  if (count($gaps) == 0) {
    xecho('No gaps found! (Yeah \o/)'."\n");
  }
  else {
    xecho("De-gapping (".count($gaps)." gaps):\n");
    foreach($gaps as $id => $gap) {
      xecho(sprintf("#%'.03d",$id+1)." ($gap): ");
      $output = array();
      $result = 0;
      $lastline = exec("/usr/bin/timeout 60 ".DMN_DIR."/axeblockretrieve $uname $gap",$output,$result);
      if ($result == 0) {
        echo "OK";
      }
      else {
        echo "Error ($lastline)";
      }
      echo "\n";
    }
  }

?>

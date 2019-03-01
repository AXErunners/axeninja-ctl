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

if ((!defined('DMN_SCRIPT')) || (DMN_SCRIPT !== true)) {
  die('This is part of the dmnctl script, run it from there.');
}

DEFINE('DMN_VERSION','2.2.2');

function dmn_checkportopen($ip, $port, $testnet, $config, &$subver, &$errmsg) {

  $subver = '';
  $errmsg = '';
  $res = 0;
//  $version = $config[$testnet]['Version'];
  $sversion = $config[$testnet]['SatoshiVersion'];
  $protocol = $config[$testnet]['ProtocolVersion'];
  $magic = hex2bin($config[$testnet]['ProtocolMagic']);
  try
  {
    $c = new \Axe\Node($ip,$port,DMN_PORTCHECK_TIMEOUT,DMN_VERSION,$sversion,$protocol,$magic);
    $subver = $c->getSubVer();
    $c->closeConnection();
    $res = 1;
  }
  catch (\Axe\EFailedToReadFromPeer $eftrfp) {
    $subver = '';
    $errmsg = $eftrfp->getMessage();
    $res = 1;
  }
  catch (\Axe\EUnexpectedFragmentation $euf) {
    $subver = '';
    $errmsg = $euf->getMessage();
    $res = 1;
  }
  catch (\Axe\EUnexpectedPacketType $eupt) {
    $subver = '';
    $errmsg = $eupt->getMessage();
    $res = 3;
  }
  catch (Exception $e) {
    $errmsg = $e->getMessage();
    if (strpos($errmsg,'timed out') !== false) {
      $res = 2;
    }
  }
  return $res;

}

xecho("dmnportcheckdo v".DMN_VERSION."\n");

if (($argc != 4) && ($argc != 5)) {
  xecho("Usage: ".basename($argv[0])." ip port testnet [outputfile]\n");
  die();
}

xecho("Retrieving configuration: ");
$result = dmn_cmd_get('/portcheck/config',array(),$response);
if ($response['http_code'] == 200) {
  echo "Fetched...";
  $config = json_decode($result,true);
  if ($config === false) {
    echo " Failed to JSON decode!\n";
    die(100);
  }
  elseif (!is_array($config) || !array_key_exists('data',$config) || !is_array($config['data'])) {
    echo " Incorrect data!\n";
    die(102);
  }
  echo " OK\n";
  $config = $config['data'];
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
  die(101);
}

$ip = $argv[1];
$port = $argv[2];
$testnet = $argv[3];
if ($testnet == 1) {
  $testnet = 1;
}
else {
  $testnet = 0;
}
xecho("Testing $ip:$port (");
if ($testnet == 1) {
  echo "Testnet";
}
else {
  echo "Mainnet";
}
echo "): ";
$subver = '';
$errormsg = '';

$checkres = dmn_checkportopen($ip,$port,$testnet,$config,$subver,$errormsg);
if ($checkres == 1) {
  $mnstatus = 'open';
}
elseif ($checkres == 0) {
  $mnstatus = 'closed';
}
elseif ($checkres == 2) {
  $mnstatus = 'timeout';
}
else {
  $mnstatus = 'rogue';
}
echo "$mnstatus ($subver) [$errormsg]\n";

if ($argc == 5) {
  xecho("Saving result to ".$argv[4].": ");
  $out = array($ip,$port,$testnet,$mnstatus,$subver,$errormsg,date('Y-m-d H:i:s',time()+DMN_PORTCHECK_INTERVAL));
  $outjson = json_encode($out);
  $res = file_put_contents($argv[4],$outjson);
  if ($res !== false) {
    echo "OK (".$res." bytes)\n";
  }
  else {
    echo "Failed!\n";
    die(1);
  }
}

die(0);

?>

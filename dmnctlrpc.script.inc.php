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

  define('DMN_VERSION_CTLRPC','1.0.0');

  xecho('AXE Ninja Control RPC Client v'.DMN_VERSION_CTLRPC."\n");

  if ($argc != 4) {
    xecho("Usage: ".basename($argv[0])." uname rpccommand outputfile\n");
    die(0);
  }

  $uname = $argv[1];
  $rpccommand = $argv[2];
  $outputfile = $argv[3];
  $nodepath1 = DMN_PID_PATH.$uname.'/.darkcoin/';
$nodepath2 = DMN_PID_PATH.$uname.'/.axe/';
$nodepath3 = DMN_PID_PATH.$uname.'/.axecore/';

  if (is_dir($nodepath1)) {
    $nodepath = $nodepath1;
  }
  elseif (is_dir($nodepath2)) {
    $nodepath = $nodepath2;
  }
  elseif (is_dir($nodepath3)) {
    $nodepath = $nodepath3;
  }
  else {
    xecho("Directory $nodepath3 not found.\n");
    die(1);
  }
  if (file_exists($outputfile)) {
    xecho("Output file already exists. Aborting.\n");
    die(5);
  }

  xecho("Loading configuration for $uname: ");
  $conf = new AxeConfig($uname);
  if ($conf->isConfigLoaded()) {
    echo "OK\n";
  }
  else {
    echo "Error (Loading configuration)\n";
    die(2);
  }

  xecho("Executing RPC command '$rpccommand': ");
  $rpc = new \axerunners\EasyAxe($conf->getconfig('rpcuser'),$conf->getconfig('rpcpassword'),'localhost',$conf->getconfig('rpcport'));

  $rpclist = explode(' ',$rpccommand);
  $rpcparams = array();
  for ($x = 1;$x<count($rpclist);$x++) {
    if (ctype_digit($rpclist[$x])) {
      $rpcparams[] = intval($rpclist[$x]);
    }
    elseif (is_numeric($rpclist[$x])) {
      $rpcparams[] = floatval($rpclist[$x]);
    }
    else {
      $rpcparams[] = $rpclist[$x];
    }
  }
  $rpccommand = $rpclist[0];
  $result = call_user_func_array(array($rpc, $rpccommand),$rpcparams);
  if ($result === false) {
    echo "Error (Calling RPC $rpccommand with ".count($rpcparams)." parameters) [".$rpc->response['error']['message']."]\n";
    die(3);
  }
  echo "OK\n";
  xecho("Writing file $outputfile: ");
  if (is_array($result)) {
    $res = file_put_contents($outputfile,$rpc->raw_response);
  }
  else {
    $res = file_put_contents($outputfile,$result);
  }
  if ($res === false) {
    xecho("Error (Writing file)\n");
    die(4);
  }
  else {
    echo "OK ($res bytes written)\n";
    die(0);
  }

?>

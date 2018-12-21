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

if ((!defined('DMN_SCRIPT')) || (DMN_SCRIPT !== true)) {
  die('This is part of the dmnctl script, run it from there.');
}

DEFINE('DMN_VERSION','2.3.0');

xecho("dmnbalance v".DMN_VERSION."\n");
if (file_exists(DMN_BALANCE_SEMAPHORE) && (posix_getpgid(intval(file_get_contents(DMN_BALANCE_SEMAPHORE))) !== false) ) {
  xecho("Already running (PID ".sprintf('%d',file_get_contents(DMN_BALANCE_SEMAPHORE)).")\n");
  die(10);
}
file_put_contents(DMN_BALANCE_SEMAPHORE,sprintf('%s',getmypid()));

xecho('Retrieving MN info (mainnet): ');
$result = dmn_cmd_get('/masternodes',array("testnet"=>0),$response);
if ($response['http_code'] == 200) {
  echo "Fetched...";
  $mnlist = json_decode($result,true);
  if ($mnlist === false) {
    echo " Failed to JSON decode!\n";
    die(200);
  }
  elseif (!is_array($mnlist) || !array_key_exists('data',$mnlist) || !is_array($mnlist['data']) || !array_key_exists('masternodes',$mnlist['data']) || !is_array($mnlist['data']['masternodes'])) {
    echo " Incorrect data!\n";
    die(202);
  }
  $mnpubkeys = array();
  foreach($mnlist['data']['masternodes'] as $mnip) {
    $mnpubkeys[] = $mnip['MasternodePubkey'];
  }
  echo " OK (PubKeys: Mainnet=".count($mnpubkeys).")\n";
}
else {
  echo "Failed [".$response['http_code']."]\n";
  if ($response['http_code'] != 500) {
    $result = json_decode($result,true);
    var_dump($result);
    if ($result !== false) {
      foreach($result['messages'] as $num => $msg) {
        xecho("Error #$num: $msg\n");
      }
    }
  }
  die(201);
}
xecho('Retrieving MN info (testnet): ');
$result = dmn_cmd_get('/masternodes',array("testnet"=>1),$response);
if ($response['http_code'] == 200) {
  echo "Fetched...";
  $mnlist = json_decode($result,true);
  if ($mnlist === false) {
    echo " Failed to JSON decode!\n";
    die(200);
  }
  elseif (!is_array($mnlist) || !array_key_exists('data',$mnlist) || !is_array($mnlist['data']) || !array_key_exists('masternodes',$mnlist['data']) || !is_array($mnlist['data']['masternodes'])) {
    echo " Incorrect data!\n";
    die(202);
  }
  $tnpubkeys = array();
  foreach($mnlist['data']['masternodes'] as $mnip) {
    $tnpubkeys[] = $mnip['MasternodePubkey'];
  }
  echo " OK (PubKeys: Testnet=".count($tnpubkeys).")\n";
}
else {
  echo "Failed [".$response['http_code']."]\n";
  if ($response['http_code'] != 500) {
    $result = json_decode($result,true);
    var_dump($result);
    if ($result !== false) {
      foreach($result['messages'] as $num => $msg) {
        xecho("Error #$num: $msg\n");
      }
    }
  }
  die(201);
}

xecho('Retrieving MN balance: ');
$result = dmn_cmd_get('/balances',array(),$response);
if ($response['http_code'] == 200) {
  echo "Fetched...";
  $mnbal = json_decode($result,true);
  if ($mnbal === false) {
    echo " Failed to JSON decode!\n";
    die(200);
  }
  elseif (!is_array($mnlist) || !array_key_exists('data',$mnbal) || !is_array($mnbal['data'])
       || !array_key_exists('balances',$mnbal['data']) || !is_array($mnbal['data']['balances'])
       || !array_key_exists('mainnet',$mnbal['data']['balances']) || !is_array($mnbal['data']['balances']['mainnet'])
       || !array_key_exists('testnet',$mnbal['data']['balances']) || !is_array($mnbal['data']['balances']['testnet'])) {
    echo " Incorrect data!\n";
    die(202);
  }
  $mnlastupdate = $mnbal['data']['balances']['mainnet'];
  $tnlastupdate = $mnbal['data']['balances']['testnet'];
  echo " OK (Testnet=".count($tnlastupdate)." Mainnet=".count($mnlastupdate).")\n";
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

xecho("Computing balances to check: ");
$numok = 0;
$mncheck = array();
$mnchecknot = array();
foreach($mnpubkeys as $mnpubkey) {
  if (array_key_exists($mnpubkey,$mnlastupdate)) {
    $numok++;
    $delta = time() - $mnlastupdate[$mnpubkey];
    if ((($argc > 1) && ($argv[1] == 'force')) || ($delta > DMN_BALANCE_INTERVAL)) {
      $mncheck[] = $mnpubkey;
    }
    else {
      $mnchecknot[] = $mnpubkey;
    }
  }
  else {
    $mncheck[] = $mnpubkey;
  }
}
$numtok = 0;
$tncheck = array();
$tnchecknot = array();
foreach($tnpubkeys as $tnpubkey) {
  if (array_key_exists($tnpubkey,$tnlastupdate)) {
    $numtok++;
    $delta = time() - $tnlastupdate[$tnpubkey];
    if ($delta > DMN_BALANCE_INTERVAL) {
      $tncheck[] = $tnpubkey;
    }
    else {
      $tnchecknot[] = $tnpubkey;
    }
  }
  else {
    $tncheck[] = $tnpubkey;
  }
}

echo "OK (Testnet: $numtok known/".count($tncheck)." to-[re]check & Mainnet: $numok known/".count($mncheck)." to-[re]check)\n";

$mncheck = array_unique($mncheck);
$tncheck = array_unique($tncheck);

$payload = array();
$numdone = 0;
$numtot = count($mncheck)+count($tncheck);
$nbcar = strlen($numtot);
foreach($mncheck as $mnpubkey) {
  $numdone++;
  xecho("(".str_pad($numdone,$nbcar," ",STR_PAD_LEFT)."/$numtot) Retrieving $mnpubkey balance: ");
  $url = str_replace('%%p%%',$mnpubkey,DMN_BALANCE_URL_MAINNET);

  $res = file_get_contents($url);
  if ($res === false) {
    echo "Error\n";
  }
  else {
    $mncurbalance = floatval($res);
    $mncurbalancerdisplay = sprintf("%.9f",$mncurbalance);
    echo "$mncurbalancerdisplay AXE\n";
    $payload[] = array('TestNet' => 0,
                       'PubKey' => $mnpubkey,
                       'Balance' => $mncurbalance,
                       'LastUpdate' => date('Y-m-d H:i:s'));
  }
}
foreach($tncheck as $mnpubkey) {
  $numdone++;
  xecho("($numdone/$numtot) Retrieving $mnpubkey balance: ");
  $url = str_replace('%%p%%',$mnpubkey,DMN_BALANCE_URL_TESTNET);

  $res = file_get_contents($url);
  if ($res === false) {
    echo "Error\n";
  }
  else {
    $mncurbalance = floatval($res);
    $mncurbalancerdisplay = sprintf("%.9f",$mncurbalance);
    echo "$mncurbalancerdisplay AXE\n";
    $payload[] = array('TestNet' => 1,
                       'PubKey' => $mnpubkey,
                       'Balance' => $mncurbalance,
                       'LastUpdate' => date('Y-m-d H:i:s'));
  }
}

if (count($payload) > 0) {
  xecho("Submitting ".count($payload)." balances to webservice: ");
  $response = '';
  $content = dmn_cmd_post('/balances',$payload,$response);

  if ($response['http_code'] == 202) {
    $content = json_decode($content,true);
    echo "OK (".$content['data']['balances'].")\n";
  }
  else {
    echo "Failed [".$response['http_code']."]\n";
    if ($response['http_code'] != 500) {
      $result = json_decode($content,true);
      if ($result !== false) {
        foreach($result['messages'] as $num => $msg) {
          xecho("Error #$num: $msg\n");
        }
      }
    }
  }
}

unlink(DMN_BALANCE_SEMAPHORE);

?>

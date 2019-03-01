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

DEFINE('DMN_VERSION','2.4.0');

// Execute port check commands
function dmn_portcheck_mt(&$commands) {

  $descriptorspec = array(
     0 => array("pipe", "r"),
     1 => array("pipe", "w"),
     2 => array("pipe", "a")
  );
  $threads = array();
  $pipes = array();
  $commandsdone = 0;
  $done = 0;
  $lastdonetime = time();
  $lastdone = -1;
  $inittime = microtime(true);
  $nbpad = strlen(count($commands));
  $nbok = 0;
  $nberr = 0;

  xecho("Executing ".count($commands)." portcheck commands (using ".DMN_THREADS_MAX." threads):\n");

  while ($done != count($commands)) {

    // Check if finished threads
    // If finished set the status of the command to 1 "Almost done"
    $oldthreads = $threads;
    $threads = array();
    foreach($oldthreads as $thread) {
      $info = proc_get_status($thread['res']);
      if (!$info['running']) {
        $cid = $thread['cid'];
        $commands[$cid]['status'] = 1;
        fclose($pipes[$cid][0]);
        $output = stream_get_contents($pipes[$cid][1]);
        if ($info['exitcode'] != 0) {
          $commands[$cid]['result'] = $output;
          $commands[$cid]['status'] = -1;
          $nberr++;
        }
        else {
          $commands[$cid]['status'] = 2;
          $nbok++;
        }
        fclose($pipes[$cid][1]);
        fclose($pipes[$cid][2]);
        proc_close($thread['res']);
        $done++;
      }
      else {
        $threads[] = $thread;
      }
    }

    // Fill up free threads with all possible commands
    // Execute the command in a thread
    while ((count($threads) < DMN_THREADS_MAX) && ($commandsdone < count($commands))) {
      $pipes[$commandsdone] = array();
      $thres[$commandsdone] = proc_open('/usr/bin/timeout 30 '.DMN_DIR.'/dmnportcheckdo '.$commands[$commandsdone]['cmd'].' '.$commands[$commandsdone]['file'],$descriptorspec,$pipes[$commandsdone]);
      if (is_resource($thres[$commandsdone])) {
        $threads[] = array('cid' => $commandsdone, 'res' => $thres[$commandsdone]);
        $commandsdone++;
      }
    }
    if (($lastdone != $done) && (time() > $lastdonetime)) {
      xecho(" (".str_pad(round(($done/count($commands))*100,0),3," ",STR_PAD_LEFT)."% - ".str_pad($done,$nbpad," ",STR_PAD_LEFT)."/".count($commands).") In progress...\n");
      $lastdone = $done;
      $lastdonetime = time();
    }
    // Do a 100ms pause
    usleep(100000);
  }

  xecho(" (100% - ".count($commands)."/".count($commands).") Done in ".round(microtime(true)-$inittime,3)." seconds [$nbok sucessfully/$nberr with errors]\n");

}

xecho("dmnportcheck v".DMN_VERSION."\n");
if (($argc < 2) || (($argv[1] != 'db') && ($argv[1] != 'nodb'))) {
  xecho("Usage: ".basename($argv[0])." [no]db [ip-port-testnet]+\n");
  die();
}

if ($argv[1] == 'db') {
  if (file_exists(DMN_PORTCHECK_SEMAPHORE) && (posix_getpgid(intval(file_get_contents(DMN_PORTCHECK_SEMAPHORE))) !== false) ) {
    xecho("Already running (PID ".sprintf('%d',file_get_contents(DMN_PORTCHECK_SEMAPHORE)).")\n");
    die(10);
  }
  file_put_contents(DMN_PORTCHECK_SEMAPHORE,sprintf('%s',getmypid()));
}

xecho('Retrieving MN port check: ');
$result = dmn_cmd_get('/portcheck/list',array(),$response);
if ($response['http_code'] == 200) {
  echo "Fetched...";
  $mnportcheck = json_decode($result,true);
  if ($mnportcheck === false) {
    echo " Failed to JSON decode!\n";
    die(200);
  }
  elseif (!is_array($mnportcheck) || !array_key_exists('data',$mnportcheck) || !is_array($mnportcheck['data'])) {
    echo " Incorrect data!\n";
    die(202);
  }
  $mnportcheck = $mnportcheck['data'];
  echo " OK (".count($mnportcheck)." entries)\n";
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

if ($argv[1] == 'nodb') {
  xecho("Add IPs from commandline: ");
  $mnpc = array();
  for($x = 2; $x < $argc; $x++) {
    $ip = explode('-',$argv[$x]);
    if (count($ip) == 3) {
      $mnpc[] = $argv[$x];
    }
  }
  $mnsubver = array();
  foreach($mnportcheck as $row){
    $mnsubver[$row['NodeIP'].'-'.$row['NodePort'].'-'.$row['NodeTestNet']] = $row['NodeSubVer'];
  }
  echo "Done (".count($mnpc)." entries)\n";
}
else {

  xecho('Retrieving MN info (mainnet): ');
  $result = dmn_cmd_get('/masternodes',array(),$response);
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
    $mnips = array();
    foreach($mnlist['data']['masternodes'] as $mnip) {
      $mnips[] = $mnip['MasternodeIP'].'-'.$mnip['MasternodePort'].'-0';
    }
    echo " OK (".count($mnips)." masternodes)\n";
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

  xecho('Retrieving MN info (testnet): ');
  $result = dmn_cmd_get('/masternodes',array("testnet" => 1),$response);
  if ($response['http_code'] == 200) {
    echo "Fetched... ";
    $mnlist = json_decode($result,true);
    if ($mnlist === false) {
      echo " Failed to JSON decode!\n";
      die(200);
    }
    elseif (!is_array($mnlist) || !array_key_exists('data',$mnlist) || !is_array($mnlist['data']) || !array_key_exists('masternodes',$mnlist['data']) || !is_array($mnlist['data']['masternodes'])) {
      echo " Incorrect data!\n";
      die(202);
    }
    foreach($mnlist['data']['masternodes'] as $mnip) {
      $mnips[] = $mnip['MasternodeIP'].'-'.$mnip['MasternodePort'].'-1';
    }
    echo " OK (".count($mnlist['data']["masternodes"])." masternodes)\n";
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

  xecho("Computing nodes to check: ");
  $num = 0;
  $numok = 0;
  $mnpc = array();
  $mnpcnot = array();
  $mnsubver = array();
  foreach($mnportcheck as $row){
    $num++;
    $mnsubver[$row['NodeIP'].'-'.$row['NodePort'].'-'.$row['NodeTestNet']] = $row['NodeSubVer'];
    if (in_array($row['NodeIP'].'-'.$row['NodePort'].'-'.$row['NodeTestNet'],$mnips)) {
      $numok++;
//      echo "[".$row['NodeTestNet']."] ".$row['NodeIP'].':'.$row['NodePort']." - ".$row['NextCheck']." - ";
      $date = new DateTime($row['NextCheck']);
      $row['NextCheck'] = $date->getTimestamp();
//      echo time()." > ".$row['NextCheck']." = ";
      if ((time() > $row['NextCheck']) ) {
//        echo "True\n";
        $mnpc[] = $row['NodeIP'].'-'.$row['NodePort'].'-'.$row['NodeTestNet'];
      }
      else {
//        echo "False\n";
        $mnpcnot[] = $row['NodeIP'].'-'.$row['NodePort'].'-'.$row['NodeTestNet'];
      }
    }
  }

  echo "OK ($num rows/$numok valid/".count($mnpc)." to-recheck)\n";

  xecho('Appending new nodes to check: ');

  $mncount = 0;
  foreach($mnips as $mnip) {
    if ((!in_array($mnip,$mnpc,true)) && (!in_array($mnip,$mnpcnot,true))) {
      $mncount++;
      $mnpc[] = $mnip;
    }
  }

  echo "OK ($mncount new nodes/".count($mnpc)." total)\n";
  $sql = array();
}

$payload = array();
if (count($mnpc) > 0) {
  xecho("Preparing multi-threaded commands: ");
  if (!is_dir("/dev/shm/dmnportcheck/")) {
    mkdir("/dev/shm/dmnportcheck/",0700,true);
  }
  $tmpdate = date('YmdHis');
  $commands = array();
  foreach($mnpc as $mniprow) {
    $mnip = explode('-',$mniprow);
    if (count($mnip) == 3) {
      if (filter_var($mnip[0], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        $ip = "[".$mnip[0]."]";
      }
      else {
        $ip = $mnip[0];
      }
      $commands[] = array("status" => 0,
                          "masternodeip" => $mnip,
                          "cmd" => $ip." ".$mnip[1]." ".$mnip[2],
                          "file" => "/dev/shm/dmnportcheck/$tmpdate.".sha1($mnip[0])."_".$mnip[1]."_".$mnip[2].".json");
    }
  }
  echo "OK (".count($commands).")\n";
  $res = dmn_portcheck_mt($commands);

  xecho("Retrieving commands results: ");
  foreach($commands as $command) {
    if (($command['status'] == 2) && file_exists($command['file'])) {
      $contentraw = file_get_contents($command['file']);
      unlink($command['file']);
      $content = json_decode($contentraw,true);
      if (($content !== false) && is_array($content) && (count($content) == 7)) {
        $key = $content[0].'-'.$content[1].'-'.$content[2];
        $subver = $content[4];
        if (($subver == '') && array_key_exists($key,$mnsubver)) {
          $subver = $mnsubver[$key];
        }
        if (substr($content[0],0,1) == "[") {
          $ip = substr($content[0],1,strlen($content[0])-2);
        }
        else {
          $ip = $content[0];
        }
        $payload[] = array('NodeIP' => $ip,
                           'NodePort' => $content[1],
                           'NodeTestNet' => $content[2],
                           'NodePortCheck' => $content[3],
                           'NextCheck' => $content[6],
                           'NodeSubVer' => $subver,
                           'ErrorMessage' => $content[5]);
      }
    }
  }

  echo "OK (".count($payload)." entries)\n";
}
if (count($payload) > 0) {
  xecho("Submitting via webservice: ");
  $response = '';
  $content = dmn_cmd_post('/portcheck',$payload,$response);

  if ($response['http_code'] == 202) {
    $content = json_decode($content,true);
    echo "OK (".$content['data']['portcheck'].")\n";
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

if (($argc == 1) || (($argc == 2) && ($argv[1] == 'sql'))) {
  unlink(DMN_PORTCHECK_SEMAPHORE);
}

?>

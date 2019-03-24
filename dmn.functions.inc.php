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

define('DMN_DIR',__DIR__);

// Display log line (with date)
function xecho($line) {
  echo "\033[0;37m".date('Y-m-d H:i:s')."\033[1;30m - \e[1;37m".$line."\033[0m";
}

// Check if PID is running and is axed
function dmn_checkpid($pid) {
  if ($pid !== false) {
    $output = array();
    exec('ps -o comm -p '.$pid,$output,$retval);
    if (($retval == 0) && (is_array($output)) && (count($output)>=2)) {
      return $output[1] === "axed";
    }
    else {
      return false;
    }
  }
  else {
    return false;
  }

}

// Returns the PID for the specified username
function dmn_getpid($uname,$testnet = false) {

  if ($testnet) {
    $testinfo = '/testnet3';
  }
  else {
    $testinfo = '';
  }
  if (file_exists(DMN_PID_PATH.$uname."/.darkcoin$testinfo/darkcoind.pid") !== FALSE) {
    $res = trim(file_get_contents(DMN_PID_PATH.$uname."/.darkcoin$testinfo/darkcoind.pid"));
  }
  else if (file_exists(DMN_PID_PATH.$uname."/.axecore$testinfo/axed.pid") !== FALSE) {
    $res = trim(file_get_contents(DMN_PID_PATH.$uname."/.axecore$testinfo/axed.pid"));
  }
  else if (file_exists(DMN_PID_PATH.$uname."/.axecore$testinfo/axe.pid") !== FALSE) {
    $res = trim(file_get_contents(DMN_PID_PATH.$uname."/.axecore$testinfo/axe.pid"));
  }
  else if (file_exists(DMN_PID_PATH.$uname."/.axe$testinfo/axed.pid") !== FALSE) {
    $res = trim(file_get_contents(DMN_PID_PATH.$uname."/.axe$testinfo/axed.pid"));
  }
  else if (file_exists(DMN_PID_PATH.$uname."/.axe$testinfo/axe.pid") !== FALSE) {
    $res = trim(file_get_contents(DMN_PID_PATH.$uname."/.axe$testinfo/axe.pid"));
  }
  else {
    $res = false;
  }
  return $res;

}

// Retrieve the uid/gid of username
function dmn_getuid($uname,&$gid) {

  $passwd = file_get_contents('/etc/passwd');
  $passwdlist = explode("\n",$passwd);
  foreach($passwdlist as $line) {
    $passwdline = explode(":",$line);
    if ($passwdline[0] == $uname) {
      $gid = $passwdline[3];
      return $passwdline[2];
    }
  }

}

// Run AXE Ninja public webservice GET method command
function dmn_api_get($command,$payload = array(),&$response) {

  global $argv;

  if (substr($command,0,1) != '/') {
    $command = '/'.$command;
  }

  $ch = curl_init();
  curl_setopt( $ch, CURLOPT_USERAGENT, basename($argv[0])."/".DMN_VERSION );
  curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, 2 );
  curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, true );
  curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 10 );
  curl_setopt( $ch, CURLOPT_TIMEOUT, 30 );
  curl_setopt( $ch, CURLOPT_MAXREDIRS, 0 );
  curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true);
  if (count($payload) > 0) {
    $payloadurl = '?'.http_build_query($payload);
  }
  else {
    $payloadurl = '';
  }
  curl_setopt( $ch, CURLOPT_URL, DMN_URL_API.$command.$payloadurl );
  curl_setopt( $ch, CURLOPT_HTTPHEADER, array(
      'Content-Length: 0'
  ) );

  $content = curl_exec( $ch );
  $response = curl_getinfo( $ch );

  return $content;

}

// Run AXE Ninja webservice GET method command
function dmn_cmd_get($command,$payload = array(),&$response) {

  global $argv;

  if (substr($command,0,1) != '/') {
    $command = '/'.$command;
  }

  $ch = curl_init();
  curl_setopt( $ch, CURLOPT_USERAGENT, basename($argv[0])."/".DMN_VERSION );
  curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, 2 );
  curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, true );
  curl_setopt( $ch, CURLOPT_SSLCERT, DMN_SSL_CERT);
  curl_setopt( $ch, CURLOPT_SSLKEY, DMN_SSL_KEY);
  curl_setopt( $ch, CURLOPT_CAINFO, DMN_SSL_CAINFO );
  curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 10 );
  curl_setopt( $ch, CURLOPT_INTERFACE, DMN_INTERFACE );
  curl_setopt( $ch, CURLOPT_TIMEOUT, 30 );
  curl_setopt( $ch, CURLOPT_MAXREDIRS, 0 );
  curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true);
  if (count($payload) > 0) {
    $payloadurl = '?'.http_build_query($payload);
  }
  else {
    $payloadurl = '';
  }
  curl_setopt( $ch, CURLOPT_URL, DMN_URL_CMD.$command.$payloadurl );
  curl_setopt( $ch, CURLOPT_HTTPHEADER, array(
      'Content-Length: 0'
  ) );

  $content = curl_exec( $ch );
  $response = curl_getinfo( $ch );

  return $content;

}

// Run Axe Masternode Ninja webservice POST method command
function dmn_cmd_post($command,$payload,&$response) {

  global $argv;

  if (substr($command,0,1) != '/') {
    $command = '/'.$command;
  }
  $ch = curl_init();
  curl_setopt( $ch, CURLOPT_USERAGENT, basename($argv[0])."/".DMN_VERSION );
  curl_setopt( $ch, CURLOPT_URL, DMN_URL_CMD.$command );
  curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, 2 );
  curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, true );
  curl_setopt( $ch, CURLOPT_SSLCERT, DMN_SSL_CERT);
  curl_setopt( $ch, CURLOPT_SSLKEY, DMN_SSL_KEY);
  curl_setopt( $ch, CURLOPT_CAINFO, DMN_SSL_CAINFO );
  curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 10 );
  curl_setopt( $ch, CURLOPT_INTERFACE, DMN_INTERFACE );
  curl_setopt( $ch, CURLOPT_TIMEOUT, 30 );
  curl_setopt( $ch, CURLOPT_MAXREDIRS, 0 );
  curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, 'POST' );
  curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true);
  $payloadjson = json_encode($payload);
  if ($payloadjson === false) {
    return false;
  }
/*  curl_setopt( $ch, CURLOPT_HTTPHEADER, array(
      'Content-Type: application/json',
      'Content-Length: ' . strlen($payloadjson))
  );*/

//  curl_setopt( $ch, CURLOPT_POSTFIELDSIZE, strlen($payloadjson));
  curl_setopt( $ch, CURLOPT_POSTFIELDS, $payloadjson );
  $content = curl_exec( $ch );
  $response = curl_getinfo( $ch );

  return $content;

}

// Get axed version from binary
function dmn_axedversion($dpath) {

  if (file_exists($dpath) || is_link($dpath)) {
    exec($dpath.' -?',$output,$retval);
    if (preg_match("/DarkCoin version v(.*)/", $output[0], $output_array) == 1) {
      return $output_array[1];
    }
    else if (preg_match("/Darkcoin Core Daemon version v(.*)/", $output[0], $output_array) == 1) {
      return $output_array[1];
    }
    else if (preg_match("/Axe Core Daemon version v(.*)/", $output[0], $output_array) == 1) {
      return $output_array[1];
    }
    else {
      return false;
    }
  }
  else {
    return false;
  }

}

// Get array($ip/$port) for IPv4 or IPv6 (ip:port)
function getipport($addr) {
  $portpos = strrpos($addr,":");
  $ip = substr($addr,0,$portpos);
  $port = substr($addr,$portpos+1,strlen($addr)-$portpos-1);
  return array($ip,$port);
}

// Random password generator
function randomPassword($length = 8) {
  $alphabet = "abcdefghijklmnopqrstuwxyzABCDEFGHIJKLMNOPQRSTUWXYZ0123456789";
  $pass = array(); //remember to declare $pass as an array
  $alphaLength = strlen($alphabet) - 1; //put the length -1 in cache
  for ($i = 0; $i < $length; $i++) {
    $n = rand(0, $alphaLength);
    $pass[] = $alphabet[$n];
  }
  return implode($pass); //turn the array into a string
}

function delTree($dir) {
    $files = array_diff(scandir($dir), array('.','..'));
    foreach ($files as $file) {
        (is_dir("$dir/$file")) ? delTree("$dir/$file") : unlink("$dir/$file");
    }
    return rmdir($dir);
}

function xechoToFile($logfile,$line) {
  $data = date('Y-m-d H:i:s').' - '.$line;
  file_put_contents($logfile,$data,FILE_APPEND);
}

// Die but delete semaphore file before
function die2($retcode,$semaphorefile) {
    unlink($semaphorefile);
    die($retcode);
}

// Check and set semaphore file
function semaphore($semaphore) {

    if (file_exists($semaphore) && (posix_getpgid(intval(file_get_contents($semaphore))) !== false) ) {
        xecho("Already running (PID ".sprintf('%d',file_get_contents($semaphore)).")\n");
        die(10);
    }
    file_put_contents($semaphore,sprintf('%s',getmypid()));

}

?>

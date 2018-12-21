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

namespace Axe;

use Exception;

define('PROTOCOL_VERSION',70210);
define('PROTOCOL_MAGIC',"\xb5\xce\x6b\x04");
define('HRVERSION',"/Axe Core:%s/AXE Ninja Port Checker:%s.%d/");
define('THISVERSION',5);

function strToHex($string){
    $hex = '';
    for ($i=0; $i<strlen($string); $i++){
        $ord = ord($string[$i]);
        $hexCode = dechex($ord);
        $hex .= substr('0'.$hexCode, -2);
    }
    return strToUpper($hex);
}

class EUnexpectedPacketType extends Exception {}
class EFailedToReadFromPeer extends Exception {}
class EUnexpectedFragmentation extends Exception {}

// Connect to P2P port of axed
// Based on code found on internet for Bitcoin (don't remember the source sorry)
class Node {
	private $sock;
	private $version = 0;
	private $myself;
	private $queue = array();
        private $subver;
        private $prot_magic;

	public function __construct($ip, $port = 9937, $timeout = 5, $versionid = '1.0.0', $sversionid = '0.12.2.2', $protver = PROTOCOL_VERSION, $prot_magic = PROTOCOL_MAGIC) {
		$this->sock = @fsockopen($ip, $port, $errno, $errstr, $timeout);
		if (!$this->sock) throw new Exception($errstr, $errno);

		$this->myself = pack('NN', mt_rand(0,0xffffffff), mt_rand(0, 0xffffffff));
                $this->prot_magic = $prot_magic;

		// send "version" packet
		$pkt = $this->_makeVersionPacket($protver,$versionid,$sversionid);
		fwrite($this->sock, $pkt);

		// wait for reply
		while((!feof($this->sock)) && ($this->version == 0)) {
			$pkt = $this->readPacket();
			switch($pkt['type']) {
				case 'version':
					if ($this->version != 0) throw new Exception('Got version packet twice!');
					$this->_decodeVersionPayload($pkt['payload']);
					break;
				case 'reject':
					$rejectinfo = $this->_decodeRejectPayload($pkt['payload']);
                    throw new EUnexpectedPacketType($pkt['type'].' ['.bin2hex($pkt['type']).'] message='.$rejectinfo["message"].' ccode='.dechex($rejectinfo["ccode"]).' reason='.$rejectinfo["reason"]);
				default:
					throw new EUnexpectedPacketType($pkt['type'].' ['.bin2hex($pkt['type']).']');
			}
		}
	}

        public function closeConnection() {
		if ($this->sock !== false) {
			@fclose($this->sock);
		}
        }

	public function getAddr() {
		fwrite($this->sock, $this->_makePacket('getaddr', ''));

		while(1) {
			$pkt = $this->readPacket(true);
			if ($pkt['type'] != 'addr') {
				$this->queue[] = $pkt;
				continue;
			}
			break;
		}

		$payload = $pkt['payload'];

		$count = $this->_getVarInt($payload);
		$res = array();

		$size = 30;
		if ($this->version < 31402) $size = 26;

		if ($count*26 > strlen($payload)) return array(); // something is wrong

		// decode payload
		for($i = 0; $i < $count; $i++) {
			$addr = substr($payload, $i*$size, $size);
			if ($size == 26) $addr = "\x00\x00\x00\x00".$addr; // no timestamp, add something to not die
			$info = unpack('Vtimestamp/V2services', $addr);
			$info['ipv4'] = inet_ntop(substr($addr, 24, 4));
			list(,$info['port']) = unpack('n', substr($addr, 28, 2));

			if ($info['services1'] > 1) continue;
			if ($info['services2'] != 0) continue;

			$res[] = $info;
		}

		return $res;
	}

	public function checkOrder() {
		// send a fake transaction to see if remote host supports ip transaction
		fwrite($this->sock, $this->_makePacket('checkorder', 'blah'));
	}

	public function getVersion() {
		return $this->version;
	}

	public function getVersionStr() {
		$v = $this->version;
		if ($v > 10000) {
			// [22:06:18] <ArtForz> new is major * 10000 + minor * 100 + revision
			$rem = floor($v / 100);
			$proto = $v - ($rem*100);
			$v = $rem;
		} else {
			// [22:06:05] <ArtForz> old was major * 100 + minor
			$proto = 0;
		}
		foreach(array('revision','minor','major') as $type) {
			$rem = floor($v / 100);
			$$type = $v - ($rem * 100);
			$v = $rem;
		}
		// build string
		return $major . '.' . $minor . '.' . $revision . '[.'.$proto.']';
	}

        public function getSubVer() {
		return $this->subver;
        }

	protected function _decodeVersionPayload($data) {
                $datasubver = substr($data,81);
                $datasubver = substr($datasubver,strpos($datasubver,'/'));
                $this->subver = substr($datasubver,0,strrpos($datasubver,'/')+1);
		$data = unpack('Vversion/V2nServices/V2timestamp', $data);

		$this->version = $data['version'];
		if ($this->version == 10300) $this->version = 300;

		// send verack?
		if ($this->version >= 209)
			fwrite($this->sock, $this->_makePacket('verack', NULL));
	}

    protected function _decodeRejectPayload($data) {
        $tmp = unpack("cmsgsize",substr($data,0,1));
        $message = substr($data,1,$tmp["msgsize"]);
        $tmp = unpack("Cccode",substr($data,$tmp["msgsize"]+1,1));
        $ccode = $tmp["ccode"];
        $tmp = unpack("cmsgsize",substr($data,$tmp["msgsize"]+2,1));
        $reason = substr($data,$tmp["msgsize"]+3,$tmp["msgsize"]);

        return array("message" => $message,
			         "ccode" => $ccode,
			         "reason" => $reason);
    }

    public function readPacket($noqueue = false) {
		if ((!$noqueue) && ($this->queue)) return array_shift($this->queue);
		$data = fread($this->sock, 20);
		if ($data === false) throw new EFailedToReadFromPeer('Failed to read from peer');
		if (strlen($data) != 20) throw new EUnexpectedFragmentation('unexpected fragmentation ('.strlen($data).' bytes read/expected 20)');
		if (substr($data, 0, 4) != $this->prot_magic) throw new Exception('Corrupted stream');
		$type = substr($data, 4, 12);
		$type_pos = strpos($type, "\0");
		if ($type_pos !== false) $type = substr($type, 0, $type_pos);

		list(,$len) = unpack('V', substr($data, 16, 4));
		if (($this->version >= 209) || ($this->version == 0)) {
			$checksum = fread($this->sock, 4);
			$payload = '';
			while(!feof($this->sock) && (strlen($payload) < $len)) {
				$payload .= fread($this->sock, $len - strlen($payload));
			}
			$local = $this->_checksum($payload);
			if ($local != $checksum) throw new Exception('Received corrupted data');
		} else {
			$payload = '';
			while(!feof($this->sock) && (strlen($payload) < $len)) {
				$payload .= fread($this->sock, $len - strlen($payload));
			}
		}
//		echo "Packet[$type]: ".bin2hex($payload)."\n";
		$pkt = array(
			'type' => $type,
			'payload' => $payload
		);
		return $pkt;
	}

	protected function _makeVersionPacket($version, $str = '.0', $sstr = '.0', $nServices = 0, $timestamp = null, $nBestHeight = 0) {
		if (is_null($timestamp)) $timestamp = time();
		$data = pack('V', $version);
//                echo "_makeVersionPacket ($version)";
		$data .= pack('VV', ($nServices >> 32) & 0xffffffff, $nServices & 0xffffffff);
		$data .= pack('VV', ($timestamp >> 32) & 0xffffffff, $timestamp & 0xffffffff);
		$data .= $this->_address(stream_socket_get_name($this->sock, false), $nServices);
		$data .= $this->_address(stream_socket_get_name($this->sock, true), $nServices);
		$data .= $this->myself;
		$data .= $this->_string(sprintf(HRVERSION,$sstr,$str,THISVERSION));
		$data .= pack('V', $nBestHeight);
        $data .= pack('c', 0);

		return $this->_makePacket('version', $data);
	}

	protected function _address($addr, $nServices) {
		// addr is ipv4:port or ipv6:port
		$portpos = strrpos($addr,":");
		$ip = substr($addr,0,$portpos);
		$port = substr($addr,$portpos+1,strlen($addr)-$portpos-1);
		//list($ip, $port) = explode(':', $addr);
		$data = pack('VV', ($nServices >> 32) & 0xffffffff, $nServices & 0xffffffff);
		$data .= str_repeat("\0", 12); // reserved, probably for ipv6
		$data .= inet_pton($ip);
		$data .= pack('n', $port);
		return $data;
	}

	protected function _string($str) {
		return $this->_int(strlen($str)).$str;
	}

	protected function _int($i) {
		if ($i < 253) return chr($i);
		if ($i < 0xffff) return chr(253).pack('v', $i);
		if ($i < 0xffffffff) return chr(254).pack('V', $i);
		return chr(255).pack('VV', ($i >> 32) & 0xffffffff, $i & 0xffffffff);
	}

	protected function _makePacket($type, $data) {
		$packet = $this->prot_magic; // magic header
//                echo "_makePacket - PROT_MAGIC: [".strToHex($this->prot_magic)."]";
		$packet .= $type . str_repeat("\0", 12-strlen($type));
		$packet .= pack('V', strlen($data));
		if ((!is_null($data)) && ($this->version > 0x209 || $this->version == 0)) $packet .= $this->_checksum($data);
		$packet .= $data;
		return $packet;
	}

	protected function _checksum($data) {
		return substr(hash('sha256', hash('sha256', $data, true), true), 0, 4);
	}

	protected function _getVarInt(&$str) {
		$v = ord(substr($str, 0, 1));
		if ($v < 0xfd) {
			$str = substr($str, 1);
			return $v;
		}
		switch($v) {
			case 0xfd:
				$res = unpack('v', substr($str, 1, 2));
				$str = substr($str, 3);
				return $res[1];
			case 0xfe:
				$res = unpack('V', substr($str, 1, 4));
				$str = substr($str, 5);
				return $res[1];
			case 0xff:
				$res = unpack('VV', substr($str, 1, 8));
				$str = substr($str, 9);
				return ($res[1] << 32) | $res[2];
		}
	}
}

?>

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

if (!defined('DMN_SCRIPT') || !defined('DMN_CONFIG') || (DMN_SCRIPT !== true) || (DMN_CONFIG !== true)) {
  die('Not executable');
}

define('DMN_VERSION','1.0.0');

xecho('dmnblockcomputeexpected v'.DMN_VERSION."\n");

if (($argc < 3) || ($argc > 5)) {
  xecho("Usage: dmnblockcomputeexpected testnet blockfrom [blockto]\n");
  die(1);
}

$testnet = intval($argv[1]);
if ($testnet < 0) {
  $testnet = 0;
}
if ($testnet > 1) {
  $testnet = 1;
}
$blockfrom = intval($argv[2]);
if ($argc == 4) {
  $blockto = intval($argv[3]);
}
else {
  $blockto = $blockfrom;
}

xecho("Computing expected for block $blockfrom to $blockto (".($blockto-$blockfrom+1)." blocks/testnet=$testnet):\n");
xecho("Connecting to MySQL...\n");
$mysqli = new mysqli(DMNCTLMYSQLHOST, DMNCTLMYSQLUSER, DMNCTLMYSQLPASS, DMNCTLMYSQLDATABASE);
if ($mysqli->connect_error) {
  die('Connect Error (' . $mysqli->connect_errno . ') '
            . $mysqli->connect_error);
}
xecho("Connected: ".$mysqli->host_info." (".$mysqli->server_version.")\n");

$sql = <<<EOT
DROP TABLE IF EXISTS _cibh_nodecount;
CREATE TEMPORARY TABLE IF NOT EXISTS
    _cibh_nodecount ENGINE=MEMORY AS (
                        SELECT
                                MP.BlockHeight BlockHeight,
                                BlockMNPayee,
                                COUNT(NodeID) CountNode,
                                MAX(BlockMNRatio) BlockMNRatio
                        FROM
                                (SELECT
                                        BlockHeight,
                                        MAX(Protocol) Protocol
                                FROM
                                        cmd_info_blocks_history2
                                WHERE
                                        BlockHeight >= %d
                                        AND BlockHeight <= %d
					AND BlockTestNet = %d
                                GROUP BY
                                        BlockHeight
                                ) MP
                        LEFT JOIN
                                cmd_info_blocks_history2 cibh
                                ON
                                        (cibh.BlockHeight=MP.BlockHeight
                                        AND cibh.Protocol=MP.Protocol)
                                GROUP BY
                                        BlockHeight,
                                        BlockMNPayee
                        );
DROP TABLE IF EXISTS _cibh_maxnodecount;
CREATE TEMPORARY TABLE IF NOT EXISTS _cibh_maxnodecount ENGINE=MEMORY AS (
        SELECT
                BlockHeight,
                MAX(CountNode) MaxCountNode
        FROM
                _cibh_nodecount
        GROUP BY
                BlockHeight
        );
SELECT NC.BlockHeight BlockHeight, BlockMNPayee, BlockMNRatio FROM _cibh_maxnodecount MNC, _cibh_nodecount NC WHERE MNC.BlockHeight = NC.BlockHeight AND MNC.MaxCountNode = NC.CountNode;
EOT;
$sql = sprintf($sql,$blockfrom,$blockto,$testnet);
xecho("Executing query....\n");
//echo $sql."\n";
$blockhist = array();
if ($mysqli->multi_query($sql) &&
  $mysqli->more_results() && $mysqli->next_result() &&
  $mysqli->more_results() && $mysqli->next_result() &&
  $mysqli->more_results() && $mysqli->next_result() &&
  $mysqli->more_results() && $mysqli->next_result() &&
  ($result = $mysqli->store_result())) {
  $update = array();
  while($row = $result->fetch_assoc()){
    $update[] = sprintf("(%d,%d,'%s',%.9f)",
                                         $testnet,
                                         intval($row['BlockHeight']),
                                         $row['BlockMNPayee'],
                                         floatval($row['BlockMNRatio']));
  }
  xecho("  Done (".count($update)." computed)\n");
  $sql = "INSERT INTO cmd_info_blocks (BlockTestnet, BlockId, BlockMNPayeeExpected, BlockMNValueRatioExpected) VALUES ".implode(",",$update)
        ." ON DUPLICATE KEY UPDATE BlockMNPayeeExpected = VALUES(BlockMNPayeeExpected), BlockMNValueRatioExpected = VALUES(BlockMNValueRatioExpected)";
//  echo $sql."\n";
  xecho("Updating expected values in block database:\n");
  if ($result = $mysqli->query($sql)) {
    xecho("  Done (".$mysqli->info.")\n");
  }
  else {
    xecho("  Error (".$mysqli->errno.': '.$mysqli->error.")\n");
  }
}
else {
  xecho(" Failed (".$mysqli->errno.": ".$mysqli->error.")\n");
  die(2);
}


?>

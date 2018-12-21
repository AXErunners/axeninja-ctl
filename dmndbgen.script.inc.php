<?php

/*
    This file is part of AXE Ninja.
    https://github.com/axerunners/axeninja-fe

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

DEFINE('DMN_VERSION','0.1.1');

function generate_masternodeactive($mysqli) {

    xecho("Generating masternodes active:\n");
    semaphore(DMN_DBGEN_SEMAPHORE);

    $sql = <<<EOT
INSERT INTO cmd_info_masternode_active (MasternodeOutputHash, MasternodeOutputIndex, MasternodeTestNet, MasternodeProtocol, ActiveCount, InactiveCount, UnlistedCount, LastSeen)
    SELECT
        ciml.MasternodeOutputHash,
        ciml.MasternodeOutputIndex,
        ciml.MasternodeTestNet,
        cns.NodeProtocol,
        SUM(CASE
            WHEN MasternodeStatus = 'active' THEN 1
            WHEN MasternodeStatus = 'current' THEN 1
            ELSE NULL END) AS ActiveCount,
        SUM(CASE
            WHEN MasternodeStatus = 'inactive' THEN 1
            ELSE NULL END) AS InactiveCount,
        SUM(CASE
            WHEN MasternodeStatus = 'unlisted' THEN 1
            ELSE NULL END) AS UnlistedCount,
        current_timestamp()
    FROM
        cmd_info_masternode2_list ciml, cmd_nodes cn, cmd_nodes_status cns
    WHERE
        cn.NodeID = cns.NodeID AND
        ciml.NodeID = cns.NodeID AND
        cn.NodeType <> 'p2pool'
    GROUP BY
        ciml.MasternodeOutputHash, ciml.MasternodeOutputIndex, ciml.MasternodeTestNet, cns.NodeProtocol
ON DUPLICATE KEY UPDATE ActiveCount = VALUES(ActiveCount), InactiveCount = VALUES(InactiveCount), UnlistedCount = VALUES(UnlistedCount), LastSeen = current_timestamp() 	
EOT;

    if ($result = $mysqli->query($sql)) {
        xecho("\e[42m\e[1;37m OK \e[0m ".$mysqli->affected_rows . "\n");
        xecho("Retrieving current timestamp in table:\n");
        $sql = "SELECT UNIX_TIMESTAMP(MAX(`LastSeen`)), UNIX_TIMESTAMP(current_timestamp()) FROM `cmd_info_masternode_active` WHERE 1";
        if ($result = $mysqli->query($sql)) {
            $row = $result->fetch_array(MYSQLI_NUM);
            xecho("\e[42m\e[1;37m OK \e[0m ".$row[1]." -- ".$row[0]."\n");
            if ($row[1]-$row[0]>300) {
                xecho("\e[43m\e[1;31m WARNING \e[0m ".($row[1]-$row[0])." seconds old data in database, something is wrong !");
            }
            xecho("Purging old entries in database (older than 120 seconds from last insert/update):\n");
            $sql = "DELETE FROM cmd_info_masternode_active WHERE LastSeen < FROM_UNIXTIME(".($row[0]-120).")";
            if ($result = $mysqli->query($sql)) {
                xecho("\e[42m\e[1;37m OK \e[0m ".$mysqli->affected_rows." ".$mysqli->info."\n");
            }
            else{
                xecho("\e[41m\e[0;33m SQL ERROR \e[0m \e[1;33m".$mysqli->errno.": ".$mysqli->error."\n");
            }
        }
        else{
            xecho("\e[41m\e[0;33m SQL ERROR \e[0m \e[1;33m".$mysqli->errno.": ".$mysqli->error."\n");
        }
    }
    else{
        xecho("\e[41m\e[0;33m SQL ERROR \e[0m \e[1;33m".$mysqli->errno.": ".$mysqli->error."\n");
    }

    unlink(DMN_DBGEN_SEMAPHORE);
}



xecho("\033[1;31mAXE Ninja \033[0;31mControl \033[1;33mDatabase Generator \033[0;37mv\033[1;32m".DMN_VERSION."\n");

if ($argc != 2) {
    xecho("Usage: ".$argv[0]." <command>\n");
    xecho("Command can be: masternodeactive = Generate the masternode active table content\n");
    die(10);
}

xecho("Connecting to MySQL...\n");
$mysqli = new mysqli(DMNCTLMYSQLHOST, DMNCTLMYSQLUSER, DMNCTLMYSQLPASS, DMNCTLMYSQLDATABASE);
if ($mysqli->connect_error) {
    xecho("\033[41m\033[0;33m ERROR \033[0m \033[1;31m".$mysqli->connect_errno.' - '. $mysqli->connect_error."\n");
    die;
}
xecho("\e[42m\e[1;37m CONNECTED \e[0m ".$mysqli->host_info." (".$mysqli->server_version.")\n");

if ($argv[1] == "masternodeactive") {
    generate_masternodeactive($mysqli);
}
else {
    xecho("Unknown command ".$argv[1]."\n");
    die(11);
}

xecho("Disconnecting MySQL...\n");
if ($mysqli->close()) {
    xecho("\e[42m\e[1;37m OK \e[0m\n");
    die;
}
xecho("\033[41m\033[0;33m ERROR \033[0m \033[1;31m".$mysqli->errno.' - '. $mysqli->error."\n");

?>
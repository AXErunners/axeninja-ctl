# AXE Ninja Control Scripts (axeninja-ctl)

This is part of what makes the AXE Ninja monitoring application.
It contains:
* axe-node.php : is a php implementation of Axe protocol to retrieve subver during port checking
* axeblocknotify : is the blocknotify script (for stats)
* axeblockretrieve : is a script used to retrieve block information when blocknotify script did not work (for stats)
* axeupdate : is an auto-update axed script (uses git)
* dmnbalance : is the balance check script (for stats)
* dmnblockcomputeexpected : is a script used to compute and store the expected fields in cmd_info_blocks table
* dmnblockdegapper : is a script that detects if blocks are missing in cmd_info_blocks table and retrieve them if needed
* dmnblockparser : is the block parser script (for stats)
* dmnctl : is the control script (start, stop and status of nodes)
* dmnctlrpc : is the RPC call sub-script for the control script
* dmnctlstartstopdaemon : is the start/stop daemon sub-script for the control script
* dmncron : is the cron script
* dmnportcheck : is the port check script (for stats)
* dmnportcheckdo : is the actual port check sub-script for the port check script
* dmnreset : is the reset .dat files script
* dmnthirdpartiesfetch : is the script that fetches third party data from the web (for stats)
* dmnvotesrrd and dmnvotesrrdexport: are obsolete v11 votes storage and exported (for graphs)

## Requirement:
* AXE Ninja Back-end: https://github.com/elbereth/axeninja-be
* AXE Ninja Database: https://github.com/elbereth/axeninja-db
* AXE Ninja Front-End: https://github.com/elbereth/axeninja-fe
* PHP 5.6 with curl

Important: Almost all the scripts uses the private rest API to retrieve and submit data to the database (only dmnblockcomputeexpected uses direct MySQL access).

## Install:
* Go to /opt
* Get latest code from github:
```shell
git clone https://github.com/elbereth/axeninja-ctl.git
```
* Get sub-modules:
```shell
cd axeninja-ctl
git submodule update --init --recursive
```
* Configure the tool.

## Configuration:
* Copy dmn.config.inc.php.sample to dmn.config.inc.php and setup your installation.
* Add dmncron to your crontab (every minute is what official AXE Ninja uses)
```
*/1 * * * * /opt/axeninja-ctl/dmncron
```
If you want to enable logging, you need to create the /var/log/dmn/ folder and give the user write access.
Then add "log" as first argument when calling dmncron:
```
*/1 * * * * /opt/axeninja-ctl/dmncron log
```
* Add dmnthirdpartiesfetch to your crontab (every minute is fine, can be longer)
```
*/1 * * * * /opt/axeninja-ctl/dmnthirdpartiesfetch >> /dev/null
```

### axeblocknotify:
* You need /dev/shm available and writable.
* Edit axeblocknotify.config.inc.php to indicates each of your nodes you wish to retrieve block info from.
* You can either retrieve block templates (bt = true) and/or block/transaction (blocks = true). For the later you need to have txindex=1 in your axe config file.
* Add in each of your nodes in axe.conf a line to enable blocknotify feature:
```
blocknotify=/opt/axeninja-ctl/axeblocknotify
```
* Restart your node.
* On each block received by the node, the script will be called and data will be created in /dev/shm.

_Based on AXE Ninja by Alexandre (aka elbereth) Devilliers_

#!/bin/zsh
#
#   This file is part of AXE Ninja.
#   https://github.com/elbereth/axeninja-ctl
#
#   AXE Ninja is free software: you can redistribute it and/or modify
#   it under the terms of the GNU General Public License as published by
#   the Free Software Foundation, either version 3 of the License, or
#   (at your option) any later version.
#
#   AXE Ninja is distributed in the hope that it will be useful,
#   but WITHOUT ANY WARRANTY; without even the implied warranty of
#   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
#   GNU General Public License for more details.
#
#   You should have received a copy of the GNU General Public License
#   along with AXE Ninja.  If not, see <http://www.gnu.org/licenses/>.
#

# Disable logging by default
updatelog=/dev/null
statuslog=/dev/null
votesrrdlog=/dev/null
balancelog=/dev/null
portchecklog=/dev/null
blockparserlog=/dev/null
autoupdatelog=/dev/null

# If parameter 1 is log then enable logging
if [[ "$1" == "log" ]]; then
  rundate=$(date +%Y%m%d%H%M%S)
  updatelog=/var/log/dmn/update.$rundate.log
  statuslog=/var/log/dmn/status.$rundate.log
  votesrrdlog=/var/log/dmn/votesrrd.$rundate.log
  balancelog=/var/log/dmn/balance.$rundate.log
  portchecklog=/var/log/dmn/portcheck.$rundate.log
  blockparserlog=/var/log/dmn/blockparser.$rundate.log
  autoupdatelog=/var/log/dmn/autoupdate.$rundate.log
fi

# Sequentially run scripts
#/opt/dmnctl/axedupdate >> $updatelog
/opt/dmnctl/dmnctl status >> $statuslog
#/opt/dmnctl/dmnvotesrrd >> $votesrrdlog
/opt/dmnctl/dmnblockparser >> $blockparserlog

# Concurrently run scripts
/opt/dmnctl/dmnbalance >> $balancelog &
/opt/dmnctl/dmnportcheck db >> $portchecklog &
/opt/dmnctl/dmnautoupdate >> $autoupdatelog &

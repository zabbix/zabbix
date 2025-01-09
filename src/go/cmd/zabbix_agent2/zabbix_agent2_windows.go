/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/

package main

import (
	"os"
	"path/filepath"
	"strings"

	"golang.zabbix.com/agent2/pkg/pdh"
	"golang.zabbix.com/sdk/errs"
)

const osDependentUsageMessageFormat = //
`  %[1]s [-c config-file] [-m] [-S automatic]
  %[1]s [-c config-file] [-m] [-S delayed]
  %[1]s [-c config-file] [-m] [-S manual]
  %[1]s [-c config-file] [-m] [-S disabled]
  %[1]s [-c config-file] -i [-m] [-S automatic]
  %[1]s [-c config-file] -i [-m] [-S delayed]
  %[1]s [-c config-file] -i [-m] [-S manual]
  %[1]s [-c config-file] -i [-m] [-S disabled]
  %[1]s [-c config-file] -d [-m]
  %[1]s [-c config-file] -s [-m]
  %[1]s [-c config-file] -x [-m]
`

func init() {
	if path, err := os.Executable(); err == nil {
		dir, name := filepath.Split(path)
		confDefault = dir + strings.TrimSuffix(name, filepath.Ext(name)) + ".conf"
	}
}

func loadOSDependentItems() error {
	err := pdh.LocateObjectsAndDefaultCounters(true)
	if err != nil {
		return errs.Wrap(err, "failed to load objects and default counters")
	}

	return nil
}

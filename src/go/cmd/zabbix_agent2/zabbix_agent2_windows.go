/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

package main

import (
	"os"
	"path/filepath"
	"strings"

	"golang.zabbix.com/agent2/pkg/pdh"
	"golang.zabbix.com/sdk/errs"
)

func init() {
	if path, err := os.Executable(); err == nil {
		dir, name := filepath.Split(path)
		confDefault = dir + strings.TrimSuffix(name, filepath.Ext(name)) + ".win.conf"
	}
}

func loadOSDependentItems() error {
	err := pdh.LocateObjectsAndDefaultCounters(true)
	if err != nil {
		return errs.Wrap(err, "failed to load objects and default counters")
	}

	return nil
}

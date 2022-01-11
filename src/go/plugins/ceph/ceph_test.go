/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

package ceph

import (
	"io/ioutil"
	"log"
	"os"
	"strings"
	"testing"
)

var fixtures map[command][]byte

const cmdBroken command = "broken"

func TestMain(m *testing.M) {
	var err error

	fixtures = make(map[command][]byte)

	for _, cmd := range []command{
		cmdDf, cmdPgDump, cmdOSDCrushRuleDump, cmdOSDCrushTree, cmdOSDDump, cmdHealth, cmdStatus,
	} {
		fixtures[cmd], err = ioutil.ReadFile("testdata/" +
			strings.ReplaceAll(string(cmd), " ", "_") + ".json")
		if err != nil {
			log.Fatal(err)
		}
	}

	fixtures[cmdBroken] = []byte{1, 2, 3, 4, 5}

	os.Exit(m.Run())
}

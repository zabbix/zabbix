//go:build !windows
// +build !windows

/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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
	"errors"

	"git.zabbix.com/ap/plugin-support/zbxflag"
)

const usageMessageExampleConfPath = `/etc/zabbix/zabbix_agent2.conf`

func osDependentFlags() zbxflag.Flags { return zbxflag.Flags{} }

func setServiceRun(fourground bool) {}

func openEventLog() error { return nil }

func fatalCloseOSItems() {}

func eventLogInfo(msg string) error { return nil }

func eventLogErr(err error) error { return nil }

func confirmService() {}

func validateExclusiveFlags(args *Arguments) error {
	var (
		exclusiveFlagsSet = []bool{
			args.print,
			args.test != "",
			args.runtimeCommand != "",
			args.testConfig,
		}
		count int
	)

	if args.verbose && !(args.test != "" || args.print) {
		return errors.New("Verbose parameter can be specified only with test or print parameters")
	}

	for _, exclusiveFlagSet := range exclusiveFlagsSet {
		if exclusiveFlagSet {
			count++
		}
		if count >= 2 {
			return errors.New("mutually exclusive options used, use help '-help'('-h'), for additional information")
		}
	}

	return nil
}

func handleWindowsService(conf string) error { return nil }

func waitServiceClose() {}

func sendServiceStop() {}

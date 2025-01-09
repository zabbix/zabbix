//go:build !windows
// +build !windows

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
	"errors"

	"golang.zabbix.com/sdk/zbxflag"
)

const usageMessageExampleConfPath = `/etc/zabbix/zabbix_agent2.conf`

func osDependentFlags() zbxflag.Flags { return zbxflag.Flags{} }

func setServiceRun(fourground bool) {}

func openEventLog() error { return nil }

func fatalCloseOSItems() {}

func eventLogInfo(msg string) error { return nil }

func eventLogErr(err error) error { return err }

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
		return errors.New("option -v, --verbose can only be specified with -t or -p")
	}

	for _, exclusiveFlagSet := range exclusiveFlagsSet {
		if exclusiveFlagSet {
			count++
		}
		if count >= 2 { //nolint:gomnd
			return errors.New("mutually exclusive options used, see -h, --help for more information")
		}
	}

	return nil
}

func handleWindowsService(conf string) error { return nil }

func waitServiceClose() {}

func sendServiceStop() {}

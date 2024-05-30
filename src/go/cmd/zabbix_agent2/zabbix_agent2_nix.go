//go:build !windows
// +build !windows

/*
** Copyright (C) 2001-2024 Zabbix SIA
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
	"os/signal"
	"syscall"

	"golang.zabbix.com/sdk/log"
)

const osDependentUsageMessageFormat = ""

func loadOSDependentItems() error {
	return nil
}

func createSigsChan() chan os.Signal {
	sigs := make(chan os.Signal, 1)

	signal.Notify(sigs, syscall.SIGINT, syscall.SIGTERM, syscall.SIGCHLD)

	return sigs
}

// handleSig() checks received signal and returns true if the signal is handled
// and can be ignored, false if the program should stop.
func handleSig(sig os.Signal) bool {
	switch sig {
	case syscall.SIGINT, syscall.SIGTERM:
		sendServiceStop()
	case syscall.SIGCHLD:
		if err := checkExternalExits(); err != nil {
			log.Warningf("Error: %s", err)
			sendServiceStop()
		} else {
			return true
		}
	}
	return false
}

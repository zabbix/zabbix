//go:build !windows
// +build !windows

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

package main

import (
	"os"
	"os/signal"
	"syscall"

	"zabbix.com/pkg/log"
)

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

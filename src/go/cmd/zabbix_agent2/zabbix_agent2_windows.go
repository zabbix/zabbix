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
	"path/filepath"
	"strings"
	"syscall"

	"zabbix.com/pkg/pdh"
)

func loadOSDependentItems() error {
	return pdh.LocateObjectsAndDefaultCounters(true)
}

func init() {
	if path, err := os.Executable(); err == nil {
		dir, name := filepath.Split(path)
		confDefault = dir + strings.TrimSuffix(name, filepath.Ext(name)) + ".win.conf"
	}
}

func createSigsChan() chan os.Signal {
	sigs := make(chan os.Signal, 1)
	signal.Notify(sigs, syscall.SIGINT, syscall.SIGTERM)

	return sigs
}

// handleSig() checks received signal and returns true if the signal is handled
// and can be ignored, false if the program should stop.
// Needed for consistency with Unix.
func handleSig(sig os.Signal) bool {
	switch sig {
	case syscall.SIGINT, syscall.SIGTERM:
		sendServiceStop()
	}

	return false
}

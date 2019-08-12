/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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

package vfsdev

import (
	"bufio"
	"encoding/json"
	"fmt"
	"io/ioutil"
	"os"
	"strings"
	"syscall"

	"golang.org/x/sys/unix"
)

const (
	devLocation       = "/dev/"
	sysBlkdevLocation = "/sys/dev/block/"
	devtypePrefix     = "DEVTYPE="
)

type devRecord struct {
	Name string `json:"{#DEVNAME}"`
	Type string `json:"{#DEVTYPE}"`
}

func (p *Plugin) getDiscovery() (out string, err error) {
	var entries []os.FileInfo
	if entries, err = ioutil.ReadDir(devLocation); err != nil {
		return
	}

	var sysfs bool
	if stat, tmperr := os.Stat(sysBlkdevLocation); tmperr == nil {
		sysfs = stat.IsDir()
	}

	devs := make([]*devRecord, 0)
	for _, entry := range entries {
		if stat, tmperr := os.Stat(devLocation + entry.Name()); tmperr == nil {
			if stat.Mode()&os.ModeType == os.ModeDevice {
				dev := &devRecord{Name: entry.Name()}
				if sysfs {
					rdev := stat.Sys().(*syscall.Stat_t).Rdev
					filename := fmt.Sprintf("%s%d:%d/uevent", sysBlkdevLocation, unix.Major(rdev), unix.Minor(rdev))
					if file, tmperr := os.Open(filename); tmperr == nil {
						scanner := bufio.NewScanner(file)
						for scanner.Scan() {
							if strings.HasPrefix(scanner.Text(), devtypePrefix) {
								dev.Type = scanner.Text()[len(devtypePrefix):]
							}
						}
						file.Close()
					}
				}
				devs = append(devs, dev)
			}
		}
	}
	var b []byte
	if b, err = json.Marshal(&devs); err != nil {
		return
	}
	return string(b), nil
}

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

package vfsdev

import (
	"bufio"
	"bytes"
	"encoding/json"
	"errors"
	"fmt"
	"io/ioutil"
	"math"
	"os"
	"strconv"
	"strings"
	"syscall"
	"time"

	"golang.org/x/sys/unix"
)

const (
	devLocation       = "/dev/"
	sysBlkdevLocation = "/sys/dev/block/"
	devtypePrefix     = "DEVTYPE="
	diskstatLocation  = "/proc/diskstats"
	devTypeRom        = 5
	devTypeRomString  = "rom"
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
		bypass := 0
		devname := devLocation + entry.Name()
		if stat, tmperr := os.Stat(devname); tmperr == nil {
			if stat.Mode()&os.ModeType == os.ModeDevice {
				dev := &devRecord{Name: entry.Name()}
				if sysfs {
					rdev := stat.Sys().(*syscall.Stat_t).Rdev
					dirname := fmt.Sprintf("%s%d:%d/", sysBlkdevLocation, unix.Major(rdev), unix.Minor(rdev))

					if lstat, tmperr := os.Lstat(devname); tmperr == nil {
						filename := dirname + "/device/type"
						if file, tmperr := os.Open(filename); tmperr == nil {
							var devtype int

							if _, tmperr = fmt.Fscanf(file, "%d\n", &devtype); tmperr == nil {
								if devtype == devTypeRom {
									dev.Type = devTypeRomString
									if lstat.Mode()&os.ModeSymlink != 0 {
										bypass = 1
									}
								}
							}
							file.Close()
						}
					}

					if dev.Type == "" {
						filename := dirname + "uevent"
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
				}
				if bypass == 0 {
					devs = append(devs, dev)
				}
			}
		}
	}
	var b []byte
	if b, err = json.Marshal(&devs); err != nil {
		return
	}
	return string(b), nil
}

func (p *Plugin) getDeviceName(name string) (devName string, err error) {
	if name == "" {
		return "", nil
	}
	if !strings.HasPrefix(name, devLocation) {
		name = devLocation + name
	}
	var stat os.FileInfo
	if stat, err = os.Stat(name); err != nil {
		return
	}
	var file *os.File
	if file, err = os.Open(diskstatLocation); err != nil {
		return
	}
	defer file.Close()

	scanner := bufio.NewScanner(file)
	for scanner.Scan() {
		fields := strings.Fields(scanner.Text())
		if len(fields) < 3 {
			return "", fmt.Errorf("unexpected %s file format", diskstatLocation)
		}
		var major, minor uint64
		if major, err = strconv.ParseUint(fields[0], 10, 32); err != nil {
			return
		}
		if minor, err = strconv.ParseUint(fields[1], 10, 32); err != nil {
			return
		}
		rdev := stat.Sys().(*syscall.Stat_t).Rdev
		if uint64(unix.Major(rdev)) == major && uint64(unix.Minor(rdev)) == minor {
			return fields[2], nil
		}
	}

	return "nil", errors.New("no matching record found")
}

const (
	diskstatMatchNone = iota
	diskstatMatchMultiple
	diskstatMatchSingle
)

func (p *Plugin) matchDiskstatFields(name string, rdev uint64, fields []string) (match int, err error) {
	if name == "" {
		return diskstatMatchMultiple, nil
	}
	if name == fields[2] {
		return diskstatMatchSingle, nil
	}
	if rdev != math.MaxUint64 {
		var major, minor uint64
		if major, err = strconv.ParseUint(fields[0], 10, 32); err != nil {
			return
		}
		if minor, err = strconv.ParseUint(fields[1], 10, 32); err != nil {
			return
		}
		if uint64(unix.Major(rdev)) == major && uint64(unix.Minor(rdev)) == minor {
			return diskstatMatchMultiple, nil
		}
	}
	return diskstatMatchNone, nil
}

func (p *Plugin) scanDeviceStats(name string, buf *bytes.Buffer) (devstats *devStats, err error) {
	rdev := uint64(math.MaxUint64)
	if name != "" {
		if !strings.HasPrefix(name, devLocation) {
			name = devLocation + name
		}
		var stat os.FileInfo
		if stat, err = os.Stat(name); err == nil {
			rdev = stat.Sys().(*syscall.Stat_t).Rdev
		}
	}

	var stats devStats
	scanner := bufio.NewScanner(buf)
	for scanner.Scan() {
		fields := strings.Fields(scanner.Text())
		if len(fields) < 7 {
			return nil, fmt.Errorf("unexpected %s file format", diskstatLocation)
		}
		var match int
		if match, err = p.matchDiskstatFields(name, rdev, fields); err != nil {
			return
		}
		if match == diskstatMatchNone {
			continue
		}
		if match == diskstatMatchSingle {
			// 'reset' devstats, as it might contain some information from matching device numbers
			var tmpstats devStats
			devstats = &tmpstats
		} else {
			devstats = &stats
		}
		var rxop, rxsec, txop, txsec int
		if len(fields) >= 14 {
			rxop, rxsec, txop, txsec = 3, 5, 7, 9
		} else {
			rxop, rxsec, txop, txsec = 3, 4, 5, 6
		}
		var n uint64
		if n, err = strconv.ParseUint(fields[rxop], 10, 64); err != nil {
			return
		}
		devstats.rx.operations += n

		if n, err = strconv.ParseUint(fields[rxsec], 10, 64); err != nil {
			return
		}
		devstats.rx.sectors += n

		if n, err = strconv.ParseUint(fields[txop], 10, 64); err != nil {
			return
		}
		devstats.tx.operations += n

		if n, err = strconv.ParseUint(fields[txsec], 10, 64); err != nil {
			return
		}
		devstats.tx.sectors += n

		if match == diskstatMatchSingle {
			return
		}
	}
	return
}

func (p *Plugin) getDeviceStats(name string) (stats *devStats, err error) {
	var file *os.File
	if file, err = os.Open(diskstatLocation); err != nil {
		return
	}
	var buf bytes.Buffer
	_, err = buf.ReadFrom(file)
	file.Close()
	if err != nil {
		return
	}
	return p.scanDeviceStats(name, &buf)
}

func (p *Plugin) collectDeviceStats(devices map[string]*devUnit) (err error) {
	var file *os.File
	if file, err = os.Open(diskstatLocation); err != nil {
		return
	}

	var buf bytes.Buffer
	_, err = buf.ReadFrom(file)
	file.Close()
	if err != nil {
		return
	}
	now := time.Now()

	for _, dev := range devices {
		if stats, tmperr := p.getDeviceStats(dev.name); tmperr == nil && stats != nil {
			stats.clock = now.UnixNano()
			dev.history[dev.tail] = *stats
			if dev.tail = dev.tail.inc(); dev.tail == dev.head {
				dev.head = dev.head.inc()
			}
		}
	}
	return
}

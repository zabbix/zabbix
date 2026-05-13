/*
** Copyright (C) 2001-2026 Zabbix SIA
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

package dev

import (
	"bufio"
	"bytes"
	"encoding/json"
	"errors"
	"fmt"
	"math"
	"os"
	"strconv"
	"strings"
	"syscall"
	"time"

	"golang.org/x/sys/unix"
	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/log"
)

const (
	devLocation       = "/dev/"
	sysBlkdevLocation = "/sys/dev/block/"
	devDiskByID       = "/dev/disk/by-id/"
	devtypePrefix     = "DEVTYPE="
	diskstatLocation  = "/proc/diskstats"
	devTypeRom        = 5
	devTypeRomString  = "rom"
)

var errCannotObtainDevInfo = errs.New("cannot obtain device information")

type devRecord struct {
	Name string `json:"{#DEVNAME}"`
	Type string `json:"{#DEVTYPE}"`
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
		//nolint:unconvert
		rdev := uint64(stat.Sys().(*syscall.Stat_t).Rdev)
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
			//nolint:unconvert
			rdev = uint64(stat.Sys().(*syscall.Stat_t).Rdev)
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

func isSysfsAvailable() (bool, error) {
	var found bool

	stat, err := os.Stat(sysBlkdevLocation)
	if err == nil {
		found = stat.IsDir()
	}

	if !found {
		return false, errs.Wrapf(errCannotObtainDevInfo, "directory \"%s\" is not found", sysBlkdevLocation)
	}

	return true, nil
}

//nolint:gocyclo,gocognit,cyclop // legacy code, suppressed until refactored
func getDevRecords(sysfs bool) ([]*devRecord, map[string]uint64, error) {
	entries, err := os.ReadDir(devLocation)
	if err != nil {
		return nil, nil, errs.Wrapf(err, "cannot read directory \"%s\"", devLocation)
	}

	devs := make([]*devRecord, 0)
	rdevs := make(map[string]uint64)

	for _, entry := range entries {
		var rdev uint64

		bypass := 0
		devname := devLocation + entry.Name()

		stat, err := os.Stat(devname)
		if err != nil {
			continue
		}

		//nolint:nestif // legacy code, suppressed until refactored
		if stat.Mode()&os.ModeType == os.ModeDevice {
			dev := &devRecord{Name: entry.Name()}

			if sysfs {
				sysInfo, ok := stat.Sys().(*syscall.Stat_t)

				if !ok {
					// should never happen
					log.Errf("cannot get device major and minor for \"%s\"", devname)

					continue
				}

				rdev = sysInfo.Rdev

				dirname := fmt.Sprintf(
					"%s%d:%d/",
					sysBlkdevLocation,
					unix.Major(rdev),
					unix.Minor(rdev),
				)

				lstat, err := os.Lstat(devname)
				if err == nil {
					filename := dirname + "/device/type"
					//nolint:gosec // path is constructed from controlled, trusted components
					file, err := os.Open(filename)
					if err == nil {
						var devtype int

						_, err = fmt.Fscanf(file, "%d\n", &devtype)
						if err == nil {
							//nolint:revive // legacy code, suppressed until refactored
							if devtype == devTypeRom {
								dev.Type = devTypeRomString

								if lstat.Mode()&os.ModeSymlink != 0 {
									bypass = 1
								}
							}
						}

						err = file.Close()
						if err != nil {
							log.Errf("cannot close file \"%s\"", filename)
						}
					}
				}

				if dev.Type == "" {
					filename := dirname + "uevent"
					//nolint:gosec // path is constructed from controlled, trusted components
					file, err := os.Open(filename)
					if err == nil {
						scanner := bufio.NewScanner(file)
						for scanner.Scan() {
							//nolint:revive // legacy code, suppressed until refactored
							if strings.HasPrefix(scanner.Text(), devtypePrefix) {
								dev.Type = scanner.Text()[len(devtypePrefix):]
							}
						}

						err = file.Close()
						if err != nil {
							log.Errf("cannot close file \"%s\"", filename)
						}
					}
				}
			}

			if bypass == 0 {
				devs = append(devs, dev)
				rdevs[entry.Name()] = rdev
			}
		}
	}

	return devs, rdevs, nil
}

func getDiscovery() (string, error) {
	sysfs, err := isSysfsAvailable()
	if err != nil {
		return "", err
	}

	devs, _, err := getDevRecords(sysfs)
	if err != nil {
		return "", err
	}

	var b []byte

	b, err = json.Marshal(&devs)
	if err != nil {
		return "", errs.Wrap(err, "failed to marshal devices")
	}

	return string(b), nil
}

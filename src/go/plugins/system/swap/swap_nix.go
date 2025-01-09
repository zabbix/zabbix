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

package swap

import (
	"bufio"
	"errors"
	"fmt"
	"os"
	"strconv"
	"strings"
	"syscall"

	"golang.org/x/sys/unix"
	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/plugin"
)

const (
	devPath          = "/dev/"
	diskstatLocation = "/proc/diskstats"
	swapLocation     = "/proc/swaps"
	vmstatLocation   = "/proc/vmstat"
)

func init() {
	err := plugin.RegisterMetrics(&impl, "Swap",
		"system.swap.size", "Returns Swap space size in bytes or in percentage from total.",
		"system.swap.in", "Swap in (from device into memory) statistics.",
		"system.swap.out", "Swap out (from memory onto device) statistics.",
	)
	if err != nil {
		panic(errs.Wrap(err, "failed to register metrics"))
	}
}

func getSwapSize() (uint64, uint64, error) {
	info := &syscall.Sysinfo_t{}
	if err := syscall.Sysinfo(info); err != nil {
		return 0, 0, err
	}

	return uint64(info.Totalswap), uint64(info.Freeswap), nil
}

func getSwapPages() (uint64, uint64, bool) {
	var err error
	var file *os.File
	var st uint8

	file, err = os.Open(vmstatLocation)
	if err != nil {
		return 0, 0, false
	}
	defer file.Close()

	var r, w uint64

	scanner := bufio.NewScanner(file)
	for scanner.Scan() {
		var tmp string

		if (1&st) == 0 && strings.HasPrefix(scanner.Text(), "pswpin ") {
			fmt.Sscanf(scanner.Text(), "%s %d", &tmp, &r)
			st |= 1
		} else if (2&st) == 0 && strings.HasPrefix(scanner.Text(), "pswpout ") {
			fmt.Sscanf(scanner.Text(), "%s %d", &tmp, &w)
			st |= 2
		}

		if st == 3 {
			return r, w, true
		}
	}

	return 0, 0, false
}

func getSwapDevStats(swapdev string, rw bool) (uint64, uint64, bool) {
	var err error

	var stat os.FileInfo
	stat, err = os.Stat(swapdev)
	if err != nil {
		return 0, 0, false
	}

	var file *os.File
	file, err = os.Open(diskstatLocation)
	if err != nil {
		return 0, 0, false
	}
	defer file.Close()

	scanner := bufio.NewScanner(file)
	for scanner.Scan() {
		fields := strings.Fields(scanner.Text())

		var io_idx, sect_idx int
		var io, sect, rev uint64
		var major, minor uint32

		if len(fields) == 7 {
			if rw {
				io_idx, sect_idx = 5, 6
			} else {
				io_idx, sect_idx = 3, 4
			}
		} else if len(fields) >= 10 {
			if rw {
				io_idx, sect_idx = 7, 9
			} else {
				io_idx, sect_idx = 3, 5
			}
		} else {
			continue
		}

		rev, err = strconv.ParseUint(fields[0], 10, 32)
		if err != nil {
			continue
		}
		major = uint32(rev)

		rev, err = strconv.ParseUint(fields[1], 10, 32)
		if err != nil {
			continue
		}
		minor = uint32(rev)

		//nolint:unconvert
		rdev := uint64(stat.Sys().(*syscall.Stat_t).Rdev)
		if unix.Major(rdev) != major || unix.Minor(rdev) != minor {
			continue
		}

		io, err = strconv.ParseUint(fields[io_idx], 10, 64)
		if err != nil {
			continue
		}

		sect, err = strconv.ParseUint(fields[sect_idx], 10, 64)
		if err != nil {
			continue
		}

		return io, sect, true
	}

	return 0, 0, false
}

func getSwapStats(swapdev string, rw bool) (uint64, uint64, uint64, bool) {
	var gotData bool
	var io, sect, pag uint64

	if len(swapdev) == 0 || swapdev == "all" {
		swapdev = ""
		if rw {
			_, pag, gotData = getSwapPages()
		} else {
			pag, _, gotData = getSwapPages()
		}
	} else if !strings.HasPrefix(swapdev, devPath) {
		swapdev = devPath + swapdev
	}

	var file *os.File
	var err error
	file, err = os.Open(swapLocation)
	if err != nil {
		return 0, 0, pag, gotData
	}
	defer file.Close()

	scanner := bufio.NewScanner(file)
	for scanner.Scan() {
		if !strings.HasPrefix(scanner.Text(), devPath) {
			continue
		}

		fields := strings.Fields(scanner.Text())

		if len(swapdev) != 0 && swapdev != fields[0] {
			continue
		}

		ioRec, sectRec, gotStats := getSwapDevStats(fields[0], rw)
		if gotStats {
			io += ioRec
			sect += sectRec
			gotData = true
		}
	}

	return io, sect, pag, gotData
}

func getSwapStatsIn(swapdev string) (uint64, uint64, uint64, error) {
	io, sect, pag, gotData := getSwapStats(swapdev, false)

	if !gotData {
		return 0, 0, 0, errors.New("Cannot obtain swap information.")
	}

	return io, sect, pag, nil
}

func getSwapStatsOut(swapdev string) (uint64, uint64, uint64, error) {
	io, sect, pag, gotData := getSwapStats(swapdev, true)

	if !gotData {
		return 0, 0, 0, errors.New("Cannot obtain swap information.")
	}

	return io, sect, pag, nil
}

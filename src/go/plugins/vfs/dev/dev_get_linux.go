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

package vfsdev

import (
	"encoding/json"
	"fmt"
	"os"
	"path"
	"path/filepath"
	"regexp"
	"sort"
	"strconv"
	"strings"
	"syscall"

	"golang.org/x/sys/unix"
	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/zbxerr"
)

const (
	modeNone vfsDevGetMode = iota
	modeDisks
	modeDiskStats
	modeDevices
	modeDeviceStats
)

type vfsDevGetMode int

type vfsDevice struct {
	major uint32
	minor uint32
	devid string
	name  string
}

type vfsDevDisksCfg struct {
	Name            string `json:"name"`
	Devid           string `json:"devid"`
	Type            string `json:"type"`
	Path            string `json:"path"`
	Model           string `json:"model"`
	Serial          string `json:"serial"`
	WWN             string `json:"wwn"`
	SizeBytes       uint64 `json:"size_bytes"`
	LogicalBlkSize  uint64 `json:"logical_block_size"`
	PhysicalBlkSize uint64 `json:"physical_block_size"`
}

type vfsDevDisks struct {
	Cfg []vfsDevDisksCfg `json:"config"`
}

type vfsStats struct {
	ReadsCompleted  uint64 `json:"reads_completed"`
	WritesCompleted uint64 `json:"writes_completed"`
	BytesRead       uint64 `json:"bytes_read"`
	BytesWritten    uint64 `json:"bytes_written"`
	IOTimeMs        uint64 `json:"io_time_ms"`
}

type vfsDevDiskStatsCfg struct {
	Name      string `json:"name"`
	Devid     string `json:"devid"`
	Type      string `json:"type"`
	SizeBytes uint64 `json:"size_bytes"`
}

type vfsDevDiskStatsVal struct {
	Name  string   `json:"name"`
	Stats vfsStats `json:"stats"`
}

type vfsDevDiskStats struct {
	Cfg []vfsDevDiskStatsCfg `json:"config"`
	Val []vfsDevDiskStatsVal `json:"values"`
}

type vfsPartitions map[string]uint64

type vfsDevicesCfg struct {
	Name       string        `json:"name"`
	Devid      string        `json:"devid"`
	Type       string        `json:"type"`
	Partitions vfsPartitions `json:"partitions"`
}

type vfsDevices struct {
	Cfg []vfsDevicesCfg `json:"config"`
}

type vfsDeviceStatsCfg struct {
	Name      string `json:"name"`
	Devid     string `json:"devid"`
	Type      string `json:"type"`
	SizeBytes uint64 `json:"size_bytes"`
}

type vfsDeviceStatsVal struct {
	Name  string   `json:"name"`
	Stats vfsStats `json:"stats"`
}

type vfsDeviceStats struct {
	Cfg []vfsDeviceStatsCfg `json:"config"`
	Val []vfsDeviceStatsVal `json:"values"`
}

func vfsDevGetParseModeParam(mode string) (vfsDevGetMode, error) {
	switch mode {
	case "disks", "":
		return modeDisks, nil
	case "disk_stats":
		return modeDiskStats, nil
	case "devices":
		return modeDevices, nil
	case "device_stats":
		return modeDeviceStats, nil

	default:
		return modeNone, errs.Wrapf(zbxerr.ErrorInvalidParams, "invalid first parameter '%s'", mode)
	}
}

func vfsDevGetPrepareRegexParam(devNames string) (*regexp.Regexp, error) {
	var (
		rgx *regexp.Regexp
		err error
	)

	if devNames != "" {
		rgx, err = regexp.Compile(devNames)
		if err != nil {
			return nil, errs.Wrapf(
				err,
				"invalid regular expression in second parameter: %q",
				devNames,
			)
		}
	}

	return rgx, nil
}

func vfsDevGetParamsValidate(params []string) (vfsDevGetMode, *regexp.Regexp, error) {
	var (
		mode        vfsDevGetMode
		devNamesRgx *regexp.Regexp
		err         error
	)

	switch len(params) {
	case 2:
		devNamesRgx, err = vfsDevGetPrepareRegexParam(params[1])
		if err != nil {
			return modeNone, nil, err
		}

		fallthrough
	case 1:
		mode, err = vfsDevGetParseModeParam(params[0])
		if err != nil {
			return modeNone, nil, err
		}
	case 0:
	default:
		return modeNone, nil, zbxerr.ErrorTooManyParameters
	}

	return mode, devNamesRgx, nil
}

func vfsDevGet(params []string) (string, error) {
	var (
		mode        vfsDevGetMode
		devNamesRgx *regexp.Regexp
		out         any
		err         error
	)

	mode, devNamesRgx, err = vfsDevGetParamsValidate(params)
	if err != nil {
		return "", err
	}

	sysfs, err := isSysfsAvailable()
	if err != nil {
		return "", err
	}

	devs, rdevs, err := getDevRecords(sysfs)
	if err != nil {
		return "", err
	}

	switch mode {
	case modeDisks:
		out = vfsDevGetDisks(devs, rdevs, devNamesRgx)
	case modeDiskStats:
		out = vfsDevGetDiskStats(devs, rdevs, devNamesRgx)
	case modeDevices:
		out = vfsDevGetDevices(devs, rdevs, devNamesRgx)
	case modeDeviceStats:
		out = vfsDevGetDeviceStats(devs, rdevs, devNamesRgx)
	}

	b, err := json.Marshal(out)
	if err != nil {
		return "", errs.Wrap(err, "failed to marshal devices")
	}

	return string(b), nil
}

func sysfsStrGet(rdev uint64, relPath string) string {
	fp := path.Join(
		sysBlkdevLocation,
		fmt.Sprintf("%d:%d", unix.Major(rdev), unix.Minor(rdev)),
		relPath,
	)

	//nolint:gosec // path is constructed from controlled, trusted components
	data, err := os.ReadFile(fp)
	if err != nil {
		return ""
	}

	return strings.TrimSpace(string(data))
}

func sysfsUintGet(rdev uint64, relPath string) uint64 {
	s := sysfsStrGet(rdev, relPath)
	if s == "" {
		return 0
	}

	val, err := strconv.ParseUint(s, 10, 64)
	if err != nil {
		return 0
	}

	return val
}

func devidsInit() []vfsDevice {
	entries, err := os.ReadDir(devDiskByID)
	if err != nil {
		return nil
	}

	devices := make([]vfsDevice, 0, len(entries))

	for _, entry := range entries {
		var stat syscall.Stat_t

		devName := entry.Name()

		if devName == "." || devName == ".." {
			continue
		}

		symlinkPath := path.Join(devDiskByID, devName)

		fp, err := filepath.EvalSymlinks(symlinkPath)
		if err != nil {
			continue
		}

		if err := syscall.Stat(fp, &stat); err != nil {
			continue
		}

		if stat.Mode&syscall.S_IFMT != syscall.S_IFBLK {
			continue
		}

		devices = append(devices, vfsDevice{
			major: unix.Major(stat.Rdev),
			minor: unix.Minor(stat.Rdev),
			devid: devName,
			name:  filepath.Base(fp),
		})
	}

	sort.Slice(devices, func(i, j int) bool {
		return deviceCompare(devices[i], devices[j]) < 0
	})

	return devices
}

func devPathGet(name string) string {
	return path.Join(devLocation, name)
}

func devModelGet(rdev uint64) string {
	return sysfsStrGet(rdev, path.Join("device", "model"))
}

func devSerialGet(rdev uint64) string {
	return sysfsStrGet(rdev, path.Join("device", "serial"))
}

func devWWNGet(rdev uint64) string {
	return sysfsStrGet(rdev, path.Join("device", "wwid"))
}

func sysfsSizeGet(rdev uint64) uint64 {
	return sysfsUintGet(rdev, "size") * 512
}

func sysfsLogicalBlksizeGet(rdev uint64) uint64 {
	return sysfsUintGet(rdev, path.Join("queue", "logical_block_size"))
}

func sysfsPhysicalBlksizeGet(rdev uint64) uint64 {
	return sysfsUintGet(rdev, path.Join("queue", "physical_block_size"))
}

//nolint:gocyclo,cyclop // large switch over independent token indices, not genuinely complex
func sysfsDevStatsGet(rdev uint64) vfsStats {
	var stats vfsStats

	line := sysfsStrGet(rdev, "stat")
	if line == "" {
		return stats
	}

	tokens := strings.Fields(line)

	for idx, tok := range tokens {
		if idx > 10 {
			break
		}

		switch idx {
		case 0:
			val, err := strconv.ParseUint(tok, 10, 64)
			if err != nil {
				break
			}

			stats.ReadsCompleted = val
		case 2:
			/* units: sectors, must be multiplied by 512 to convert to bytes */
			val, err := strconv.ParseUint(tok, 10, 64)
			if err != nil {
				break
			}

			stats.BytesRead = val * 512
		case 4:
			val, err := strconv.ParseUint(tok, 10, 64)
			if err != nil {
				break
			}

			stats.WritesCompleted = val
		case 6:
			/* units: sectors, must be multiplied by 512 to convert to bytes */
			val, err := strconv.ParseUint(tok, 10, 64)
			if err != nil {
				break
			}

			stats.BytesWritten = val * 512
		case 10:
			val, err := strconv.ParseUint(tok, 10, 64)
			if err != nil {
				break
			}

			stats.IOTimeMs = val
		}
	}

	return stats
}

func sysfsDiskPartitionsGet(rdev uint64) vfsPartitions {
	partitions := make(vfsPartitions)
	major := unix.Major(rdev)
	minor := unix.Minor(rdev)

	diskPath := path.Join(sysBlkdevLocation, fmt.Sprintf("%d:%d", major, minor))

	entries, err := os.ReadDir(diskPath)
	if err != nil {
		return partitions
	}

	for _, entry := range entries {
		partitionPath := path.Join(diskPath, entry.Name())

		/* partition directories should contain a text file named "partition" */
		stat, err := os.Stat(path.Join(partitionPath, "partition"))
		if err != nil || !stat.Mode().IsRegular() {
			continue
		}

		//nolint:gosec // path is constructed from controlled, trusted components
		val, err := os.ReadFile(path.Join(partitionPath, "size"))
		if err != nil {
			continue
		}

		size, err := strconv.ParseUint(strings.TrimSpace(string(val)), 10, 64)
		if err != nil {
			continue
		}

		// The size is in standard UNIX 512 byte blocks
		// and it must be multiplied by 512 to get size in bytes.
		partitions[entry.Name()] = size * 512
	}

	return partitions
}

func vfsDevGetDisks(devs []*devRecord, rdevs map[string]uint64, reg *regexp.Regexp) vfsDevDisks {
	devIDs := devidsInit()

	out := vfsDevDisks{
		Cfg: make([]vfsDevDisksCfg, 0),
	}

	for _, dev := range devs {
		if !isDisk(dev.Type) {
			continue
		}

		if reg != nil && !reg.MatchString(dev.Name) {
			continue
		}

		rdev := rdevs[dev.Name]
		model := devModelGet(rdev)
		cfg := vfsDevDisksCfg{
			Name:            dev.Name,
			Devid:           devIDGet(devIDs, rdev, model),
			Type:            dev.Type,
			Path:            devPathGet(dev.Name),
			Model:           model,
			Serial:          devSerialGet(rdev),
			WWN:             devWWNGet(rdev),
			SizeBytes:       sysfsSizeGet(rdev),
			LogicalBlkSize:  sysfsLogicalBlksizeGet(rdev),
			PhysicalBlkSize: sysfsPhysicalBlksizeGet(rdev),
		}
		out.Cfg = append(out.Cfg, cfg)
	}

	return out
}

func vfsDevGetDiskStats(devs []*devRecord, rdevs map[string]uint64, reg *regexp.Regexp) vfsDevDiskStats {
	devIDs := devidsInit()

	out := vfsDevDiskStats{
		Cfg: make([]vfsDevDiskStatsCfg, 0),
		Val: make([]vfsDevDiskStatsVal, 0),
	}

	for _, dev := range devs {
		if !isDisk(dev.Type) {
			continue
		}

		if reg != nil && !reg.MatchString(dev.Name) {
			continue
		}

		rdev := rdevs[dev.Name]
		model := devModelGet(rdev)
		cfg := vfsDevDiskStatsCfg{
			Name:      dev.Name,
			Devid:     devIDGet(devIDs, rdev, model),
			Type:      dev.Type,
			SizeBytes: sysfsSizeGet(rdev),
		}
		out.Cfg = append(out.Cfg, cfg)

		val := vfsDevDiskStatsVal{
			Name:  dev.Name,
			Stats: sysfsDevStatsGet(rdev),
		}
		out.Val = append(out.Val, val)
	}

	return out
}

func vfsDevGetDevices(devs []*devRecord, rdevs map[string]uint64, reg *regexp.Regexp) vfsDevices {
	devIDs := devidsInit()

	out := vfsDevices{
		Cfg: make([]vfsDevicesCfg, 0),
	}

	for _, dev := range devs {
		if !isDisk(dev.Type) {
			continue
		}

		if reg != nil && !reg.MatchString(dev.Name) {
			continue
		}

		rdev := rdevs[dev.Name]
		model := devModelGet(rdev)
		cfg := vfsDevicesCfg{
			Name:       dev.Name,
			Devid:      devIDGet(devIDs, rdev, model),
			Type:       dev.Type,
			Partitions: sysfsDiskPartitionsGet(rdev),
		}
		out.Cfg = append(out.Cfg, cfg)
	}

	return out
}

func vfsDevGetDeviceStats(devs []*devRecord, rdevs map[string]uint64, reg *regexp.Regexp) vfsDeviceStats {
	devIDs := devidsInit()

	out := vfsDeviceStats{
		Cfg: make([]vfsDeviceStatsCfg, 0),
		Val: make([]vfsDeviceStatsVal, 0),
	}

	for _, dev := range devs {
		if !isDisk(dev.Type) && !isPartition(dev.Type) {
			continue
		}

		if reg != nil && !reg.MatchString(dev.Name) {
			continue
		}

		rdev := rdevs[dev.Name]
		model := devModelGet(rdev)
		cfg := vfsDeviceStatsCfg{
			Name:      dev.Name,
			Devid:     devIDGet(devIDs, rdev, model),
			Type:      dev.Type,
			SizeBytes: sysfsSizeGet(rdev),
		}
		out.Cfg = append(out.Cfg, cfg)

		val := vfsDeviceStatsVal{
			Name:  dev.Name,
			Stats: sysfsDevStatsGet(rdev),
		}
		out.Val = append(out.Val, val)
	}

	return out
}

func isDisk(devType string) bool {
	return devType == "disk" || devType == "rom"
}

func isPartition(devType string) bool {
	return devType == "partition"
}

func deviceCompare(a, b vfsDevice) int {
	if a.major < b.major {
		return -1
	}

	if a.major > b.major {
		return 1
	}

	if a.minor < b.minor {
		return -1
	}

	if a.minor > b.minor {
		return 1
	}

	return strings.Compare(a.devid, b.devid)
}

func findDeviceRange(devices []vfsDevice, rdev uint64) (int, int) {
	major, minor := unix.Major(rdev), unix.Minor(rdev)

	l, r := -1, -1

	for i, d := range devices {
		if major == d.major && minor == d.minor {
			if l == -1 {
				l = i
			}

			r = i
		} else if l != -1 {
			break
		}
	}

	return l, r
}

func isAlnum(c byte) bool {
	return (c >= 'a' && c <= 'z') || (c >= 'A' && c <= 'Z') || (c >= '0' && c <= '9')
}

func normalizeModel(model string) string {
	var normModel []byte

	for i := range model {
		c := model[i]

		if !isAlnum(c) && c != '.' && c != ' ' {
			break
		}

		if c == ' ' {
			normModel = append(normModel, '_')
		} else {
			normModel = append(normModel, c)
		}
	}

	return string(normModel)
}

func findDeviceByModel(devices []vfsDevice, l, r int, model string) int {
	normModel := normalizeModel(model)

	for i := l; i <= r; i++ {
		if strings.Contains(devices[i].devid, normModel) {
			return i
		}
	}

	return -1
}

// devIDGet gets a device ID in the format used by /dev/disk/by-id/.
//
// When more than one device ID is present for a particular device, the ID
// containing the device model is preferred as it is human-readable.
//
// Algorithm for resolving device IDs:
//
//  1. Read all symlink names in /dev/disk/by-id/. Treat them as device IDs
//     and resolve the <major-ID> and <minor-ID> for each.
//
//  2. Sort devices by <major-ID> and <minor-ID>.
//     Sort IDs of the same device in lexicographical order.
//
//  3. If there is only one device ID for the symlink, use it.
//
//  4. Otherwise, try to find the device ID that contains the device model:
//     4.1. Read the model from
//     /sys/dev/block/<major-ID>:<minor-ID>/device/model
//     4.2. Take the longest left-anchored prefix of the model consisting only
//     of alphanumeric characters, digits, dots, and spaces.
//     Replace spaces with underscores.
//     4.3. Choose the device ID that contains the resulting model string.
//
//  5. If no match is found, use the first symlink for the device.
func devIDGet(devices []vfsDevice, rdev uint64, model string) string {
	if len(devices) == 0 {
		return ""
	}

	l, r := findDeviceRange(devices, rdev)

	if l == -1 {
		return ""
	}

	if l == r || model == "" {
		return devices[l].devid
	}

	matchIdx := findDeviceByModel(devices, l, r, model)

	if matchIdx != -1 {
		return devices[matchIdx].devid
	}

	return devices[l].devid
}

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

package vfsfs

import (
	"syscall"

	"golang.org/x/sys/windows"
	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/plugin"
)

func init() {
	err := plugin.RegisterMetrics(
		&impl, "VfsFs",
		"vfs.fs.discovery", "List of mounted filesystems. Used for low-level discovery.",
		"vfs.fs.get", "List of mounted filesystems with statistics.",
		"vfs.fs.size", "Disk space in bytes or in percentage from total.",
	)
	if err != nil {
		panic(errs.Wrap(err, "failed to register metrics"))
	}
}

func getMountPaths() (paths []string, err error) {
	buffer := make([]uint16, windows.MAX_PATH+1)
	volume := make([]uint16, windows.MAX_PATH+1)
	var h windows.Handle
	if h, err = windows.FindFirstVolume(&volume[0], uint32(len(volume))); err != nil {
		return
	}
	defer windows.FindVolumeClose(h)

	var result []string
	var size uint32
	for {
		for {
			if err = windows.GetVolumePathNamesForVolumeName(&volume[0], &buffer[0], uint32(len(buffer)), &size); err != nil {
				if err.(syscall.Errno) != syscall.ERROR_MORE_DATA {
					err = errs.Wrapf(err, "Cannot obtain a list of filesystems. Volume: %s Error", windows.UTF16ToString(volume))
					return
				}
				buffer = make([]uint16, size)
			} else {
				break
			}
		}

		buf := buffer
		for buf[0] != 0 {
			result = append(result, windows.UTF16ToString(buf))
			for i, c := range buf {
				if c == 0 {
					buf = buf[i+1:]
					break
				}
			}
		}

		if err = windows.FindNextVolume(h, &volume[0], uint32(len(volume))); err != nil {
			if err.(syscall.Errno) == syscall.ERROR_NO_MORE_FILES {
				break
			}
			return
		}

	}
	return result, nil
}

func getFsInfo(path string) (fsname, fstype, drivetype, drivelabel string, err error) {
	fsname = path
	if len(fsname) > 0 && fsname[len(fsname)-1] == '\\' {
		fsname = fsname[:len(fsname)-1]
	}

	if len(path) >= windows.MAX_PATH && path[:4] != `\\?\` {
		path = `\\?\` + path
	}

	wpath := windows.StringToUTF16Ptr(path)
	bufType := make([]uint16, windows.MAX_PATH+1)
	bufLabel := make([]uint16, windows.MAX_PATH+1)
	if err = windows.GetVolumeInformation(wpath, &bufLabel[0], uint32(len(bufLabel)),
		nil, nil, nil, &bufType[0], uint32(len(bufType))); err != nil {
		fstype = "UNKNOWN"
	} else {
		fstype = windows.UTF16ToString(bufType)
		drivelabel = windows.UTF16ToString(bufLabel)
	}

	dt := windows.GetDriveType(wpath)
	switch dt {
	case windows.DRIVE_UNKNOWN:
		drivetype = "unknown"
	case windows.DRIVE_NO_ROOT_DIR:
		drivetype = "norootdir"
	case windows.DRIVE_REMOVABLE:
		drivetype = "removable"
	case windows.DRIVE_FIXED:
		drivetype = "fixed"
	case windows.DRIVE_REMOTE:
		drivetype = "remote"
	case windows.DRIVE_CDROM:
		drivetype = "cdrom"
	case windows.DRIVE_RAMDISK:
		drivetype = "ramdisk"
	default:
		drivetype = "unknown"
	}
	return
}

func getFsStats(path string) (stats *FsStats, err error) {
	var callerFree, total uint64

	err = windows.GetDiskFreeSpaceEx(windows.StringToUTF16Ptr(path), &callerFree, &total, nil)
	if err != nil {
		return
	}

	totalUsed := total - callerFree
	stats = &FsStats{
		Total: total,
		Free:  callerFree,
		Used:  totalUsed,
	}

	if total != 0 {
		stats.PFree = float64(callerFree) * 100.0 / float64(total)
		stats.PUsed = float64(totalUsed) * 100.0 / float64(total)
	}

	return
}

func (p *Plugin) getFsInfo() (data []*FsInfo, err error) {
	var paths []string
	if paths, err = getMountPaths(); err != nil {
		return
	}
	for _, path := range paths {
		if fsname, fstype, drivetype, drivelabel, fserr := getFsInfo(path); fserr == nil {
			data = append(data, &FsInfo{
				FsName:     &fsname,
				FsType:     &fstype,
				DriveType:  &drivetype,
				DriveLabel: &drivelabel,
			})
		} else {
			p.Debugf(`cannot obtain file system information for "%s": %s`, path, fserr)
		}
	}
	return
}

func (p *Plugin) getFsInfoStats() (data []*FsInfoNew, err error) {
	var paths []string
	if paths, err = getMountPaths(); err != nil {
		return
	}
	fsmap := make(map[string]*FsInfoNew)
	for _, path := range paths {
		var info FsInfoNew
		if fsname, fstype, drivetype, drivelabel, fserr := getFsInfo(path); fserr == nil {
			info.FsName = &fsname
			info.FsType = &fstype
			info.DriveType = &drivetype
			info.DriveLabel = &drivelabel
		} else {
			p.Debugf(`cannot obtain file system information for "%s": %s`, path, fserr)
			continue
		}
		if stats, fserr := getFsStats(path); err == nil {
			info.Bytes = stats
			fsmap[path] = &info
		} else {
			p.Debugf(`cannot obtain file system statistics for "%s": %s`, path, fserr)
			continue
		}
	}
	if paths, err = getMountPaths(); err != nil {
		return
	}
	for _, path := range paths {
		if info, ok := fsmap[path]; ok {
			data = append(data, info)
		}
	}
	return
}

func getFsInode(string) (*FsStats, error) {
	return nil, plugin.UnsupportedMetricError
}

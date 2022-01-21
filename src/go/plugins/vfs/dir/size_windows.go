/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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

package dir

import (
	"fmt"
	"io/fs"
	"os"
	"syscall"

	"zabbix.com/pkg/win32"
)

func (sp *sizeParams) handleHomeDir(path string, d fs.DirEntry) (int64, error) {
	if sp.diskMode {
		_, len, err := win32.GetFullPathName(path)
		if err != nil {
			return 0, err
		}

		sp.disk, err = win32.GetVolumePathName(path, len)
		if err != nil {
			return 0, err
		}
	}

	// home dir is not counter on windows
	return 0, nil
}

func skipDir(d fs.DirEntry) bool {
	if d.IsDir() {
		return true
	}

	return false
}

func (sp *sizeParams) getSize(fs fs.FileInfo, path string) (int64, error) {
	sys, ok := fs.Sys().(*syscall.Win32FileAttributeData)
	if !ok {
		return 0, fmt.Errorf("failed to read %s file size", fs.Name())
	}

	fileSize := uint64(sys.FileSizeHigh)<<32 | uint64(sys.FileSizeLow)

	if sp.diskMode {
		clusterSize, err := sp.getClusterSize(path)
		if err != nil {
			return 0, err
		}

		mod := fileSize % clusterSize
		if mod != 0 {
			fileSize += clusterSize - mod
		}
	}

	return int64(fileSize), nil
}

func (sp *sizeParams) getClusterSize(path string) (uint64, error) {
	clusters, err := win32.GetDiskFreeSpace(sp.disk)
	if err != nil {
		return 0, err
	}

	return uint64(clusters.LpSectorsPerCluster) * uint64(clusters.LpBytesPerSector), nil
}

func (cp *common) osSkip(path string, d fs.DirEntry) bool {
	if d.Type() == fs.ModeSymlink {
		return true
	}

	i, err := d.Info()
	if err != nil {
		impl.Logger.Errf("failed to get file info for path %s, %s", path, err.Error())
		return true
	}

	for _, f := range cp.files {
		if os.SameFile(f, i) {
			return true
		}
	}

	cp.files = append(cp.files, i)

	return false
}

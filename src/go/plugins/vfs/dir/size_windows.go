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

package dir

import (
	"fmt"
	"io/fs"
	"syscall"

	"golang.zabbix.com/agent2/pkg/win32"
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

func hashFromFileInfo(i *syscall.ByHandleFileInformation) uint64 {
	return uint64(i.FileIndexHigh)<<32 | uint64(i.FileIndexLow)
}

func getInodeData(path string) (inodeData, bool, error) {
	uPath, err := syscall.UTF16PtrFromString(path)
	if err != nil {
		return inodeData{}, false, err
	}

	attributes := uint32(syscall.FILE_FLAG_BACKUP_SEMANTICS | syscall.FILE_FLAG_OPEN_REPARSE_POINT)
	h, err := syscall.CreateFile(uPath, 0, 0, nil, syscall.OPEN_EXISTING, attributes, 0)
	if err != nil {
		return inodeData{}, false, err
	}

	defer syscall.CloseHandle(h)

	var i syscall.ByHandleFileInformation
	err = syscall.GetFileInformationByHandle(h, &i)
	if err != nil {
		return inodeData{}, false, err
	}

	if i.NumberOfLinks <= 1 {
		return inodeData{}, false, nil
	}

	dev := uint64(i.VolumeSerialNumber)
	ino := hashFromFileInfo(&i)

	return inodeData{dev, ino}, true, nil
}

func (cp *common) osSkip(path string, d fs.DirEntry) bool {
	if d.Type() == fs.ModeSymlink {
		return true
	}

	iData, ok, err := getInodeData(path)
	if err != nil {
		impl.Logger.Errf("failed to get file info for path %s, %s", path, err.Error())

		return true
	}

	// inodeData is returned only for files with hardlinks
	if !ok {
		return false
	}

	_, found := cp.files[iData]
	if found {
		return true
	}

	cp.files[iData] = true

	return false
}

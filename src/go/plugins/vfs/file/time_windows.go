//go:build windows
// +build windows

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

package file

import (
	"errors"
	"fmt"
	"os"
	"syscall"
	"unsafe"

	"golang.org/x/sys/windows"
	"golang.zabbix.com/sdk/zbxerr"
)

const (
	fileBasicInfo = 0 // FILE_BASIC_INFO
)

type FILE_BASIC_INFO struct {
	CreationTime   windows.Filetime
	LastAccessTime windows.Filetime
	LastWriteTime  windows.Filetime
	ChangeTime     windows.Filetime
	FileAttributes uint32
	// padding
	_ uint32
}

func getFileChange(path string) (unixTimeNano int64, err error) {
	var f *os.File
	if f, err = os.Open(path); err != nil {
		return 0, zbxerr.New(fmt.Sprintf("Cannot open file")).Wrap(err)
	}
	defer f.Close()

	var bi FILE_BASIC_INFO
	err = windows.GetFileInformationByHandleEx(windows.Handle(f.Fd()), fileBasicInfo, (*byte)(unsafe.Pointer(&bi)),
		uint32(unsafe.Sizeof(bi)))

	if err != nil {
		return 0, zbxerr.New(fmt.Sprintf("Cannot obtain file information")).Wrap(err)
	}
	return bi.ChangeTime.Nanoseconds(), nil
}

// Export -
func (p *Plugin) exportTime(params []string) (result interface{}, err error) {
	if len(params) > 2 || len(params) == 0 {
		return nil, errors.New("Invalid number of parameters.")
	}
	if "" == params[0] {
		return nil, errors.New("Invalid first parameter.")
	}

	if len(params) == 1 || params[1] == "" || params[1] == "modify" {
		if fi, ferr := os.Stat(params[0]); ferr != nil {
			return nil, zbxerr.New(fmt.Sprintf("Cannot stat file")).Wrap(err)
		} else {
			return fi.ModTime().Unix(), nil
		}
	} else if params[1] == "access" {
		if fi, ferr := os.Stat(params[0]); ferr != nil {
			return nil, zbxerr.New(fmt.Sprintf("Cannot stat file")).Wrap(err)
		} else {
			if stat, ok := fi.Sys().(*syscall.Win32FileAttributeData); !ok {
				return nil, errors.New("Invalid system data returned by stat.")
			} else {
				return stat.LastAccessTime.Nanoseconds() / 1e9, nil
			}
		}
	} else if params[1] == "change" {
		if utn, err := getFileChange(params[0]); err == nil {
			return utn / 1e9, nil
		}
		return
	} else {
		return nil, errors.New("Invalid second parameter.")
	}

}

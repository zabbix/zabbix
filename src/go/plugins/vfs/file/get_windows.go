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

package file

import (
	"fmt"
	"io/fs"
	"syscall"
	"time"

	"golang.org/x/sys/windows"
)

func getFileInfo(info *fs.FileInfo, path string) (fileinfo *fileInfo, err error) {
	var fi fileInfo

	sd, err := windows.GetNamedSecurityInfo(path, windows.SE_FILE_OBJECT, windows.OWNER_SECURITY_INFORMATION)
	if err != nil {
		return nil, fmt.Errorf("Cannot obtain %s information: %s", path, err)
	}
	if !sd.IsValid() {
		return nil, fmt.Errorf("Cannot obtain %s information: Invalid security descriptor", path)
	}
	sdOwner, _, err := sd.Owner()
	if err != nil {
		return nil, fmt.Errorf("Cannot obtain %s owner information: %s", path, err)
	}
	if !sdOwner.IsValid() {
		return nil, fmt.Errorf("Cannot obtain %s information: Invalid security descriptor owner", path)
	}

	sid := sdOwner.String()
	fi.SID = &sid
	account, domain, _, err := sdOwner.LookupAccount("")
	if err != nil {
		return nil, fmt.Errorf("Cannot obtain %s owner name information: %s", path, err)
	}
	fi.User = domain + "\\" + account

	wFileSys := (*info).Sys().(*syscall.Win32FileAttributeData)
	fi.Time.Access = jsTimeLoc(time.Unix(0, wFileSys.LastAccessTime.Nanoseconds()))
	if utn, err := getFileChange(path); err != nil {
		return nil, fmt.Errorf("Cannot obtain %s change time information: %s", path, err)
	} else {
		fi.Time.Change = jsTimeLoc(time.Unix(0, utn))
	}

	return &fi, nil
}

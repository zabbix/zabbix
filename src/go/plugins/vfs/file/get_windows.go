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
	"fmt"
	"os"
	"path/filepath"
	"syscall"
	"time"

	"golang.org/x/sys/windows"
	"golang.zabbix.com/sdk/zbxerr"
)

type userInfo struct {
	SID string `json:"SID"`
}

func getFileInfo(info *os.FileInfo, path string) (fileinfo *fileInfo, err error) {
	var fi fileInfo

	sd, err := windows.GetNamedSecurityInfo(path, windows.SE_FILE_OBJECT, windows.OWNER_SECURITY_INFORMATION)
	if err != nil {
		return nil, zbxerr.New(fmt.Sprintf("Cannot obtain %s information", path)).Wrap(err)
	}
	if !sd.IsValid() {
		return nil, fmt.Errorf("Cannot obtain %s information: Invalid security descriptor.", path)
	}

	fi.Pathname, err = filepath.Abs(path)
	if err != nil {
		return nil, fmt.Errorf("Cannot obtain %s path name.", path)
	}

	fi.Basename = filepath.Base(path)
	fi.Dirname = filepath.Dir(path)

	sdOwner, _, err := sd.Owner()
	if err != nil {
		return nil, zbxerr.New(fmt.Sprintf("Cannot obtain %s owner information", path)).Wrap(err)
	}
	if !sdOwner.IsValid() {
		return nil, fmt.Errorf("Cannot obtain %s information: Invalid security descriptor owner.", path)
	}

	fi.SID = sdOwner.String()

	if account, domain, _, er := sdOwner.LookupAccount(""); er == nil {
		u := domain
		if u != "" {
			u += "\\"
		}
		u += account

		fi.User = &u
	}

	if wFileSys := (*info).Sys().(*syscall.Win32FileAttributeData); wFileSys != nil && wFileSys.LastAccessTime.Nanoseconds() > 0 {
		a := jsTimeLoc(time.Unix(0, wFileSys.LastAccessTime.Nanoseconds()))
		fi.Time.Access = &a
	}

	if utn, er := getFileChange(path); er == nil && utn > 0 {
		c := jsTimeLoc(time.Unix(0, utn))
		fi.Time.Change = &c
	}

	if (*info).Mode().IsRegular() {
		fi.Size = (*info).Size()
	}

	return &fi, nil
}

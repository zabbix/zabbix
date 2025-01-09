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

package file

import (
	"fmt"
	"os"
	"os/user"
	"path/filepath"
	"strconv"
	"syscall"
	"time"
)

type userInfo struct {
	Group       *string `json:"group"`
	Permissions string  `json:"permissions"`
	Uid         uint32  `json:"uid"`
	Gid         uint32  `json:"gid"`
}

func getFileInfo(info *os.FileInfo, name string) (fileinfo *fileInfo, err error) {
	var fi fileInfo

	stat := (*info).Sys().(*syscall.Stat_t)
	if stat == nil {
		return nil, fmt.Errorf("Cannot obtain %s permission information.", name)
	}

	fi.Pathname, err = filepath.Abs(name)
	if err != nil {
		return nil, fmt.Errorf("Cannot obtain %s path name.", name)
	}

	fi.Basename = filepath.Base(name)
	fi.Dirname = filepath.Dir(name)

	fi.Permissions = mode2str(stat.Mode)

	u := strconv.FormatUint(uint64(stat.Uid), 10)
	if usr, er := user.LookupId(u); er == nil {
		fi.User = &usr.Username
	} else {
		username := fmt.Sprintf("%d", stat.Uid)
		fi.User = &username
	}

	fi.Uid = stat.Uid

	g := strconv.FormatUint(uint64(stat.Gid), 10)
	if group, er := user.LookupGroupId(g); er == nil {
		fi.Group = &group.Name
	} else {
		groupname := fmt.Sprintf("%d", stat.Gid)
		fi.Group = &groupname
	}

	fi.Gid = stat.Gid

	if stat.Atim.Sec > 0 {
		a := jsTimeLoc(time.Unix(stat.Atim.Unix()))
		fi.Time.Access = &a
	}

	if stat.Ctim.Sec > 0 {
		c := jsTimeLoc(time.Unix(stat.Ctim.Unix()))
		fi.Time.Change = &c
	}

	fi.Size = (*info).Size()

	return &fi, nil
}

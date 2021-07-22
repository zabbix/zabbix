// +build !windows

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
	"os"
	"os/user"
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

	fi.Permissions = fmt.Sprintf("%04o", stat.Mode&07777)

	u := strconv.FormatUint(uint64(stat.Uid), 10)
	if usr, ok := user.LookupId(u); ok == nil {
		fi.User = &usr.Username
	}

	fi.Uid = stat.Uid

	g := strconv.FormatUint(uint64(stat.Gid), 10)
	if group, ok := user.LookupGroupId(g); ok == nil {
		fi.Group = &group.Name
	}

	fi.Gid = stat.Gid

	a := jsTimeLoc(time.Unix(stat.Atim.Unix()))
	fi.Time.Access = &a
	c := jsTimeLoc(time.Unix(stat.Ctim.Unix()))
	fi.Time.Change = &c

	fi.Size = (*info).Size()

	return &fi, nil
}

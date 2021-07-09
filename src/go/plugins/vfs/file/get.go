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
	"encoding/json"
	"errors"
	"fmt"
	"io/fs"
	"time"
)

type jsTimeLoc time.Time

func (t jsTimeLoc) MarshalJSON() ([]byte, error) {
	return json.Marshal(time.Time(t).Local())
}

type jsTimeUtc time.Time

func (t jsTimeUtc) MarshalJSON() ([]byte, error) {
	return json.Marshal(time.Time(t).Unix())
}

type fiTime struct {
	Access jsTimeLoc `json:"access,omitempty"`
	Modify jsTimeLoc `json:"modify,omitempty"`
	Change jsTimeLoc `json:"change,omitempty"`
}

type fiTimeStamp struct {
	Access jsTimeUtc `json:"access,omitempty"`
	Modify jsTimeUtc `json:"modify,omitempty"`
	Change jsTimeUtc `json:"change,omitempty"`
}

type fileInfo struct {
	Type        string      `json:"type,omitempty"`
	User        string      `json:"user,omitempty"`
	Group       *string     `json:"group,omitempty"`
	Permissions *string     `json:"permissions,omitempty"`
	Uid         *uint32     `json:"uid,omitempty"`
	Gid         *uint32     `json:"gid,omitempty"`
	SID         *string     `json:"SID,omitempty"`
	Size        int64       `json:"size,omitempty"`
	Time        fiTime      `json:"time,omitempty"`
	Timestamp   fiTimeStamp `json:"timestamp,omitempty"`
}

func (p *Plugin) exportGet(params []string) (result interface{}, err error) {
	if len(params) > 1 {
		return nil, errors.New("Too many parameters.")
	}
	if len(params) == 0 || params[0] == "" {
		return nil, errors.New("Invalid first parameter.")
	}

	var fi *fileInfo

	info, err := stdOs.Stat(params[0])
	if err != nil {
		return nil, err
	}
	if fi, err = getFileInfo(&info, params[0]); err != nil {
		return
	}

	switch mode := info.Mode(); {
	case mode.IsRegular():
		fi.Type = "file"
	case mode.IsDir():
		fi.Type = "dir"
	case mode&fs.ModeSymlink != 0:
		fi.Type = "sym"
	case mode&fs.ModeSocket != 0:
		fi.Type = "sock"
	case mode&fs.ModeDevice != 0:
		fi.Type = "bdev"
	case mode&fs.ModeCharDevice != 0:
		fi.Type = "cdev"
	case mode&fs.ModeNamedPipe != 0:
		fi.Type = "fifo"
	default:
		return nil, fmt.Errorf("Cannot obtain %s type information", params[0])
	}

	fi.Size = info.Size()

	fi.Time.Modify = jsTimeLoc(info.ModTime())

	fi.Timestamp.Access = jsTimeUtc(fi.Time.Access)
	fi.Timestamp.Change = jsTimeUtc(fi.Time.Change)
	fi.Timestamp.Modify = jsTimeUtc(fi.Time.Modify)

	var b []byte
	if b, err = json.Marshal(&fi); err != nil {
		return
	}
	return string(b), nil
}

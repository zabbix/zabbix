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
	"encoding/json"
	"fmt"
	"os"
	"time"

	"golang.zabbix.com/sdk/zbxerr"
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
	Access *jsTimeLoc `json:"access"`
	Modify *jsTimeLoc `json:"modify"`
	Change *jsTimeLoc `json:"change"`
}

type fiTimeStamp struct {
	Access *jsTimeUtc `json:"access"`
	Modify *jsTimeUtc `json:"modify"`
	Change *jsTimeUtc `json:"change"`
}

type fileInfo struct {
	Basename string  `json:"basename"`
	Pathname string  `json:"pathname"`
	Dirname  string  `json:"dirname"`
	Type     string  `json:"type"`
	User     *string `json:"user"`
	userInfo
	Size      int64       `json:"size"`
	Time      fiTime      `json:"time"`
	Timestamp fiTimeStamp `json:"timestamp"`
}

func (p *Plugin) exportGet(params []string) (result interface{}, err error) {
	if len(params) > 1 {
		return nil, zbxerr.ErrorTooManyParameters
	}
	if len(params) == 0 || params[0] == "" {
		return nil, zbxerr.ErrorTooFewParameters
	}

	var fi *fileInfo

	info, err := os.Lstat(params[0])
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
	case mode&os.ModeSymlink != 0:
		fi.Type = "sym"
	case mode&os.ModeCharDevice != 0:
		fi.Type = "cdev"
	case mode&os.ModeSocket != 0:
		fi.Type = "sock"
	case mode&os.ModeNamedPipe != 0:
		fi.Type = "fifo"
	case mode&os.ModeDevice != 0:
		fi.Type = "bdev"
	default:
		return nil, fmt.Errorf("Cannot obtain %s type information.", params[0])
	}

	if !info.ModTime().IsZero() {
		ml := jsTimeLoc(info.ModTime())
		fi.Time.Modify = &ml
		mu := jsTimeUtc(info.ModTime())
		fi.Timestamp.Modify = &mu
	}

	if fi.Time.Access != nil {
		a := jsTimeUtc(*fi.Time.Access)
		fi.Timestamp.Access = &a
	}
	if fi.Time.Change != nil {
		c := jsTimeUtc(*fi.Time.Change)
		fi.Timestamp.Change = &c
	}

	var b []byte
	if b, err = json.Marshal(&fi); err != nil {
		return
	}

	return string(b), nil
}

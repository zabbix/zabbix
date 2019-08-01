/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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

package vfs_file

import (
	"encoding/binary"
	"errors"
	"fmt"
	"hash/crc32"
	"io/ioutil"
	"zabbix/internal/plugin"
)

// Plugin -
type Plugin struct {
	plugin.Base
}

var impl Plugin

// Export -
func (p *Plugin) Export(key string, params []string) (result interface{}, err error) {
	switch key {
	case "vfs.file.cksum":
		if len(params) != 1 {
			return nil, errors.New("Wrong number of parameters")
		}

		/*
			start := time.Now()
		*/
		buf, err := ioutil.ReadFile(params[0])
		if err != nil {
			return nil, fmt.Errorf("Cannot read file %s", params[0])
		}
		crc32q := crc32.MakeTable(0x04c11db7)

		l := uint32(len(buf))
		lbytes := make([]byte, 4)
		binary.LittleEndian.PutUint32(lbytes, l)
		buf = append(buf, lbytes...)

		/*elapsed := time.Since(start)
		if elapsed.Seconds() > agent.Options.Timeot {
			return nil, errors.New("Timeout while processing item")
		}*/

		return crc32.Checksum([]byte(buf), crc32q), nil
	case "vfs.file.contents":
		return nil, fmt.Errorf("TODO: %s", key)
	case "vfs.file.exists":
		return nil, fmt.Errorf("TODO: %s", key)
	}

	return nil, fmt.Errorf("Not implemented: %s", key)
}

func init() {
	plugin.RegisterMetric(&impl, "checksum", "vfs.file.cksum", "Returns File checksum, calculated by the UNIX cksum algorithm")
	plugin.RegisterMetric(&impl, "content", "vfs.file.contents", "Returns content of the file")
	plugin.RegisterMetric(&impl, "exsists", "vfs.file.exists", "Returns File exists")
}

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
	"crypto/sha256"
	"errors"
	"fmt"
	"io"
	"time"

	"golang.zabbix.com/sdk/std"
)

func sha256sum(file std.File, start time.Time, timeout int) (result interface{}, err error) {
	var bnum int64
	bnum = 16 * 1024
	buf := make([]byte, bnum)

	hash := sha256.New()

	for bnum > 0 {
		bnum, _ = io.CopyBuffer(hash, file, buf)
		if time.Since(start) > time.Duration(timeout)*time.Second {
			return nil, errors.New("Timeout while processing item.")
		}
	}

	return fmt.Sprintf("%x", hash.Sum(nil)), nil
}

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

package pidfile

import (
	"fmt"
	"os"
)

func createPidFile(pid int, path string) (file *os.File, err error) {
	if path != "" {
		return nil, fmt.Errorf("cannot use PID file [%s]: unsupported configuration option for Windows", path)
	}

	return
}

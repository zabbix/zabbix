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

*
 */
package sensor

import (
	"bufio"
	"fmt"
	"os"
	"strconv"
	"strings"

	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/zbxerr"
)

func executeSystemBoottime(_ []string) (*string, error) {
	bootTimestamp, err := host.BootTime()
	if err != nil {
		return nil, errs.Wrap(err, "failed to get system boot time")
	}

	bootTime := strconv.Itoa(int(bootTimestamp))

	return &bootTime, nil
}

func executeNetUDPListen(params []string) (*string, error) {
	if len(params) > 1 {
		return nil, zbxerr.ErrorTooManyParameters
	}

	if len(params) == 0 {
		return nil, zbxerr.ErrorTooFewParameters
	}

	portStr := params[0]

	port, err := strconv.Atoi(portStr)
	if err != nil {
		return nil, errs.Wrapf(zbxerr.ErrorInvalidParams, "invalid port '%s'", params[0])
	}

	connections, err := net.Connections("udp")
	if err != nil {
		return nil, errs.Wrap(err, "failed to get UDP connections")
	}

	result := "0"

	for _, conn := range connections {
		if int(conn.Laddr.Port) == port {
			result = "1"

			break
		}
	}

	return &result, nil
}

func executeSystemCPUSwitches(_ []string) (*string, error) {
	file, err := os.Open("/proc/stat")
	if err != nil {
		return nil, fmt.Errorf("cannot open /proc/stat: %w", err)
	}
	defer file.Close()

	scanner := bufio.NewScanner(file)
	for scanner.Scan() {
		line := scanner.Text()

		// Look for the line starting with "ctxt"
		if strings.HasPrefix(line, "ctxt ") {
			var value uint64
			// Parse the line, which has the format "ctxt 123456789"
			n, err := fmt.Sscanf(line, "ctxt %d", &value)
			if err != nil || n != 1 {
				return nil, fmt.Errorf("failed to parse 'ctxt' line from /proc/stat: %w", err)
			}

			valueStr := strconv.FormatUint(value, 10)

			return &valueStr, nil
		}
	}

	err = scanner.Err()
	if err != nil {
		return nil, fmt.Errorf("error while scanning /proc/stat: %w", err)
	}

	return nil, fmt.Errorf("cannot find a line with 'ctxt' in /proc/stat")
}

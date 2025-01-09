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

package serverlistener

import (
	"testing"
)

func TestFormatError(t *testing.T) {
	const notsupported = "ZBX_NOTSUPPORTED"
	const message = "error message"
	pc := &passiveCheck{}
	result := pc.formatError(message)

	if string(result[:len(notsupported)]) != notsupported {
		t.Errorf("Expected error message to start with '%s' while got '%s'", notsupported,
			string(result[:len(notsupported)]))
		return
	}
	if result[len(notsupported)] != 0 {
		t.Errorf("Expected terminating zero after ZBX_NOTSUPPORTED error prefix")
		return
	}

	if string(result[len(notsupported)+1:]) != message {
		t.Errorf("Expected error description '%s' while got '%s'", message, string(result[len(notsupported)+1:]))
		return
	}
}

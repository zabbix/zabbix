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

package serverconnector

import (
	"testing"
)

func TestToken(t *testing.T) {
	tokens := make(map[string]bool)
	for i := 0; i < 100000; i++ {
		token := newToken()
		if len(token) != 32 {
			t.Errorf("Expected token length 32 while got %d", len(token))

			return
		}
		if _, ok := tokens[token]; ok {
			t.Errorf("Duplicated token detected")
		}
		tokens[token] = true
	}
}

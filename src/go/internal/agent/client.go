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

package agent

import (
	"sync/atomic"
)

const MaxBuiltinClientID = 100
const TestrunClientID = 2
const PassiveChecksClientID = 1
const LocalChecksClientID = 0

var lastClientID uint64 = MaxBuiltinClientID

// Internal client id assigned to each active server and unique passive bulk request.
// Single checks (internal and old style passive checks) has built-in client id 0.
func NewClientID() uint64 {
	return atomic.AddUint64(&lastClientID, 1)
}

/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

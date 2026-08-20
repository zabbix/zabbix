/*
** Copyright (C) 2001-2026 Zabbix SIA
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

package zbxcomms

import (
	"bytes"
	"compress/zlib"
	"encoding/binary"
	"fmt"
	"io"
	"testing"
)

type Result struct {
	data   []string
	failed bool
}

var i, offset, lastOffset int

var results = []Result{
	Result{data: []string{""}},
	Result{data: []string{"ZBXD\x01\x0A\x00\x00\x00\x00\x00\x00\x00agent.ping"}},
	Result{data: []string{"ZB", "XD\x01\x0A\x00\x00\x00\x00\x00\x00\x00agent.ping"}},
	Result{failed: true, data: []string{"ZB", "XX\x01\x0A\x00\x00\x00\x00\x00\x00\x00agent.ping"}},
	Result{data: []string{"ZBXD\x01", "\x0A\x00\x00\x00\x00\x00\x00\x00agent.ping"}},
	Result{failed: true, data: []string{"ZBBD\x01", "\x0A\x00\x00\x00\x00\x00\x00\x00agent.ping"}},
	Result{data: []string{"Z", "B", "X", "D", "\x01", "\x0A\x00\x00\x00\x00\x00\x00\x00agent.ping"}},
	Result{data: []string{"ZBXD\x01\x00\x00\x00\x00\x00\x00\x00\x00"}},
	Result{failed: true, data: []string{"ZBX"}},
	Result{failed: true, data: []string{"ZBXD"}},
	Result{failed: true, data: []string{"ZBXD\x00\x0A\x00\x00\x00\x00\x00\x00\x00agent.ping"}},
	Result{failed: true, data: []string{"ZBXD\x02\x0A\x00\x00\x00\x00\x00\x00\x00agent.ping"}},
	Result{failed: true, data: []string{"ZBXD\x04\x0A\x00\x00\x00\x00\x00\x00\x00agent.ping"}},
	Result{failed: true, data: []string{"ZBXD\xFF\x0A\x00\x00\x00\x00\x00\x00\x00agent.ping"}},
	Result{failed: true, data: []string{"ZBXD\x01"}},
	Result{failed: true, data: []string{"ZBXD\x01\x00\x00\x00\x00"}},
	Result{data: []string{"Z", "B", "X", "D", "\x01", "\x0A", "\x00", "\x00", "\x00", "\x00", "\x00", "\x00", "\x00", "agent.ping"}},
	Result{failed: true, data: []string{"Z", "B", "X", "D", "\x01", "\x01\x00\x00\x08\x00\x00\x00\x00", "agent.ping"}},
	Result{failed: true, data: []string{"ZBXD\x01\x0B\x00\x00\x00\x00\x00\x00\x00agent.ping"}},
	Result{failed: true, data: []string{"ZBXD\x01\x09\x00\x00\x00\x00\x00\x00\x00agent.ping"}},
	Result{failed: true, data: []string{"ZBXD\x01\x01\x00\x00\x00\x00\x00\x00"}},
	Result{data: []string{"ZBXD\x01\x0A\x00\x00", "\x00\x00\x00\x00\x00agent.pi", "ng"}},
	Result{data: []string{"ZBXD\x01\x0A\x00\x00", "\x00\x00\x00\x00\x00", "a", "g", "e", "n", "t", ".", "p", "i", "n", "g"}},
	Result{data: []string{"Z", "B", "X", "D", "\x01", "\x0A", "\x00", "\x00", "\x00", "\x00", "\x00", "\x00", "\x00", "a", "g", "e", "n", "t", ".", "p", "i", "n", "g"}},
	Result{data: []string{"ZBXD\x01\xF2\x07\x00\x00\x00\x00\x00\x000123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmn"}},
	Result{data: []string{"ZBXD\x01\xF3\x07\x00\x00\x00\x00\x00\x000123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmno"}},
	Result{data: []string{"ZBXD\x01\xF4\x07\x00\x00\x00\x00\x00\x000123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnop"}},
	Result{data: []string{"ZBXD\x01\x00\x08\x00\x00\x00\x00\x00\x000123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz01"}},
	Result{data: []string{"ZBXD\x01\x13\x08\x00\x00\x00\x00\x00\x000123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEF", "GHI\x00", "K"}},
	Result{failed: true, data: []string{"ZBXD\x01\x02\x08\x00\x00\x00\x00\x00\x000123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789"}},
	Result{failed: true, data: []string{"ZBXD\x01\x0A\x00\x00\x00\x00\x00\x00\x00"}},
}

type mockRead struct {
}

var m mockRead

func (t mockRead) Read(p []byte) (n int, err error) {
	if offset == len(results[i].data) {
		return 0, nil
	}

	s := results[i].data[offset][lastOffset:]

	if len(s) > cap(p) {
		n = cap(p)
		lastOffset += n
	} else {
		n = len(s)
		offset++
		lastOffset = 0
	}

	copy(p, s[:n])

	if n == 0 {
		return 0, io.EOF
	}

	return n, nil
}

func TestReceive(t *testing.T) {
	var c Connection
	for _, result := range results {
		t.Run("test", func(t *testing.T) {
			data, err := c.read(m, nil)
			if err == nil {
				if result.failed {
					t.Errorf("Expected error while got success")
				} else {
					var buffer bytes.Buffer
					for j := 0; j < len(result.data); j++ {
						buffer.WriteString(result.data[j])
					}

					if len(buffer.Bytes()) < 13 {
						if 0 != len(buffer.Bytes()) || 0 != len(data) {
							t.Errorf("Expected header on success")
						}
					} else {
						if !bytes.Equal(data, buffer.Bytes()[13:]) {
							t.Errorf("Expected data '%v' while got '%v'", buffer.Bytes(), data)
						}
					}
				}
			} else {
				if !result.failed {
					t.Errorf("Unexpected error: %s", err)
				}
			}
			i++
			offset = 0
			lastOffset = 0
		})
	}
}

func makeCompressedPayload(t testing.TB, data []byte) []byte {
	t.Helper()
	var buf bytes.Buffer
	w := zlib.NewWriter(&buf)
	if _, err := w.Write(data); err != nil {
		t.Fatalf("failed to write compressed data: %s", err)
	}
	if err := w.Close(); err != nil {
		t.Fatalf("failed to close zlib writer: %s", err)
	}
	return buf.Bytes()
}

func makeHeader(flags byte, expectedSize, reservedSize uint32) []byte {
	header := make([]byte, headerSize)
	copy(header, []byte{'Z', 'B', 'X', 'D'})
	header[4] = flags
	binary.LittleEndian.PutUint32(header[5:9], expectedSize)
	binary.LittleEndian.PutUint32(header[9:13], reservedSize)
	return header
}

func TestMaxPassiveCheckDataSize(t *testing.T) {
	if MaxPassiveCheckDataSize != 8*1024*1024 {
		t.Errorf("MaxPassiveCheckDataSize = %d, want %d", MaxPassiveCheckDataSize, 8*1024*1024)
	}
}

type countingReader struct {
	data  []byte
	reads int
}

func (r *countingReader) Read(p []byte) (int, error) {
	r.reads++
	if len(r.data) == 0 {
		return 0, io.EOF
	}
	n := copy(p, r.data)
	r.data = r.data[n:]
	return n, nil
}

func TestReceiveMaxSize(t *testing.T) {
	const maxSize = MaxPassiveCheckDataSize

	t.Run("expectedSize exceeding passive limit rejected from header", func(t *testing.T) {
		r := &countingReader{data: makeHeader(tcpProtocol, maxSize+1, 0)}
		c := &Connection{maxRecvSize: maxSize}
		_, err := c.read(r, nil)
		if err == nil {
			t.Fatal("expected error, got nil")
		}
		expectedErr := fmt.Sprintf("Message size %d exceeds the maximum size %d bytes.",
			maxSize+1, maxSize)
		if err.Error() != expectedErr {
			t.Errorf("expected error %q, got: %s", expectedErr, err)
		}
		if r.reads != 1 {
			t.Errorf("expected exactly 1 Read, got %d", r.reads)
		}
	})

	t.Run("generic reservedSize exceeding 128 MiB rejected without passive limit", func(t *testing.T) {
		const maxRecvDataSize = 128 * 1048576
		r := &countingReader{data: makeHeader(tcpProtocol|zlibCompress, 1, maxRecvDataSize+1)}
		c := &Connection{maxRecvSize: 0}
		_, err := c.read(r, nil)
		if err == nil {
			t.Fatal("expected error, got nil")
		}
		expectedErr := fmt.Sprintf("Message size %d exceeds the maximum size %d bytes.",
			maxRecvDataSize+1, maxRecvDataSize)
		if err.Error() != expectedErr {
			t.Errorf("expected error %q, got: %s", expectedErr, err)
		}
		if r.reads != 1 {
			t.Errorf("expected exactly 1 Read, got %d", r.reads)
		}
	})

	t.Run("reservedSize exceeding passive limit rejected before decompression", func(t *testing.T) {
		r := &countingReader{data: makeHeader(tcpProtocol|zlibCompress, 1, maxSize+1)}
		c := &Connection{maxRecvSize: maxSize}
		_, err := c.read(r, nil)
		if err == nil {
			t.Fatal("expected error, got nil")
		}
		expectedErr := fmt.Sprintf("Uncompressed message size %d exceeds the maximum size %d bytes.",
			maxSize+1, maxSize)
		if err.Error() != expectedErr {
			t.Errorf("expected error %q, got: %s", expectedErr, err)
		}
		if r.reads != 1 {
			t.Errorf("expected exactly 1 Read, got %d", r.reads)
		}
	})

	t.Run("default 128 MiB limit enforced without passive limit", func(t *testing.T) {
		r := &countingReader{data: makeHeader(tcpProtocol, 128*1048576+1, 0)}
		c := &Connection{maxRecvSize: 0}
		_, err := c.read(r, nil)
		if err == nil {
			t.Fatal("expected error, got nil")
		}
		expectedErr := fmt.Sprintf("Message size %d exceeds the maximum size %d bytes.",
			128*1048576+1, 128*1048576)
		if err.Error() != expectedErr {
			t.Errorf("expected error %q, got: %s", expectedErr, err)
		}
		if r.reads != 1 {
			t.Errorf("expected exactly 1 Read, got %d", r.reads)
		}
	})
}

func TestUncompressMaxSize(t *testing.T) {
	const maxSize = 100

	tests := []struct {
		name    string
		data    []byte
		expLen  uint32
		maxSize uint32
		wantErr string
	}{
		{
			name:    "expLen exceeding maxSize rejected before decompression",
			data:    []byte("test"),
			expLen:  maxSize + 1,
			maxSize: maxSize,
			wantErr: "Uncompressed message size 101 exceeds the maximum size 100 bytes.",
		},
		{
			name:    "actual output exactly maxSize accepted",
			data:    makeCompressedPayload(t, make([]byte, maxSize)),
			expLen:  maxSize,
			maxSize: maxSize,
			wantErr: "",
		},
		{
			name:    "actual output maxSize+1 rejected",
			data:    makeCompressedPayload(t, make([]byte, maxSize+1)),
			expLen:  maxSize,
			maxSize: maxSize,
			wantErr: "Uncompressed message size 101 exceeds the maximum size 100 bytes.",
		},
		{
			name:    "actual output larger than maxSize+1 rejected",
			data:    makeCompressedPayload(t, make([]byte, maxSize+2)),
			expLen:  maxSize,
			maxSize: maxSize,
			wantErr: "Uncompressed message size 101 exceeds the maximum size 100 bytes.",
		},
		{
			name:    "actual output below limit but different from expLen returns size-mismatch error",
			data:    makeCompressedPayload(t, []byte("test")),
			expLen:  10,
			maxSize: maxSize,
			wantErr: "Uncompressed message size 4 instead of expected 10.",
		},
		{
			name:    "no maxSize limit preserves previous behavior",
			data:    makeCompressedPayload(t, []byte("test")),
			expLen:  4,
			maxSize: 0,
			wantErr: "",
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			c := &Connection{}
			_, err := c.uncompress(tt.data, tt.expLen, tt.maxSize)
			if tt.wantErr == "" {
				if err != nil {
					t.Fatalf("uncompress() unexpected error: %s", err)
				}
				return
			}
			if err == nil {
				t.Fatal("uncompress() expected error, got nil")
			}
			if err.Error() != tt.wantErr {
				t.Errorf("expected error %q, got: %s", tt.wantErr, err)
			}
		})
	}
}

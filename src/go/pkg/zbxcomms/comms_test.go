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
	"errors"
	"fmt"
	"io"
	"testing"
)

const (
	testMaxPassiveCheckDataSize = 8 * 1024 * 1024
	testMaxRecvDataSize         = 128 * 1024 * 1024
)

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

type Result struct {
	data   []string
	failed bool
}

type mockRead struct {
}

type countingReader struct {
	data  []byte
	reads int
}

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
			data, err := c.read(mockRead{}, nil)
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

func (r *countingReader) Read(p []byte) (int, error) {
	r.reads++

	if len(r.data) == 0 {
		return 0, io.EOF
	}

	n := copy(p, r.data)
	r.data = r.data[n:]

	return n, nil
}

func makeCompressedPayload(tb testing.TB, data []byte) []byte {
	tb.Helper()

	var buf bytes.Buffer

	w := zlib.NewWriter(&buf)

	_, err := w.Write(data)
	if err != nil {
		tb.Fatalf("failed to write compressed data: %s", err)
	}

	err = w.Close()
	if err != nil {
		tb.Fatalf("failed to close zlib writer: %s", err)
	}

	return buf.Bytes()
}

func makeHeader(flags byte, expectedSize, reservedSize uint32) []byte {
	var header [headerSize]byte

	copy(header[:], []byte{'Z', 'B', 'X', 'D'})
	header[4] = flags
	binary.LittleEndian.PutUint32(header[5:9], expectedSize)
	binary.LittleEndian.PutUint32(header[9:13], reservedSize)

	return header[:]
}

func TestMaxPassiveCheckDataSize(t *testing.T) {
	t.Parallel()

	if MaxPassiveCheckDataSize != testMaxPassiveCheckDataSize {
		t.Errorf(
			"MaxPassiveCheckDataSize = %d, want %d",
			MaxPassiveCheckDataSize,
			testMaxPassiveCheckDataSize,
		)
	}
}

func TestReceiveMaxSize(t *testing.T) {
	t.Parallel()

	tests := []struct {
		name         string
		flags        byte
		expectedSize uint32
		reservedSize uint32
		maxRecvSize  uint32
		wantErr      string
	}{
		{
			name:         "expectedSize exceeding passive limit rejected from header",
			flags:        tcpProtocol,
			expectedSize: testMaxPassiveCheckDataSize + 1,
			reservedSize: 0,
			maxRecvSize:  testMaxPassiveCheckDataSize,
			wantErr: fmt.Sprintf(
				"Message size %d exceeds the maximum size %d bytes.",
				testMaxPassiveCheckDataSize+1,
				testMaxPassiveCheckDataSize,
			),
		},
		{
			name:         "generic reservedSize exceeding 128 MiB rejected without passive limit",
			flags:        tcpProtocol | zlibCompress,
			expectedSize: 1,
			reservedSize: testMaxRecvDataSize + 1,
			maxRecvSize:  0,
			wantErr: fmt.Sprintf(
				"Message size %d exceeds the maximum size %d bytes.",
				testMaxRecvDataSize+1,
				testMaxRecvDataSize,
			),
		},
		{
			name:         "reservedSize exceeding passive limit rejected before decompression",
			flags:        tcpProtocol | zlibCompress,
			expectedSize: 1,
			reservedSize: testMaxPassiveCheckDataSize + 1,
			maxRecvSize:  testMaxPassiveCheckDataSize,
			wantErr: fmt.Sprintf(
				"Uncompressed message size %d exceeds the maximum size %d bytes.",
				testMaxPassiveCheckDataSize+1,
				testMaxPassiveCheckDataSize,
			),
		},
		{
			name:         "default 128 MiB limit enforced without passive limit",
			flags:        tcpProtocol,
			expectedSize: testMaxRecvDataSize + 1,
			reservedSize: 0,
			maxRecvSize:  0,
			wantErr: fmt.Sprintf(
				"Message size %d exceeds the maximum size %d bytes.",
				testMaxRecvDataSize+1,
				testMaxRecvDataSize,
			),
		},
	}

	for index := range tests {
		test := tests[index]

		t.Run(test.name, func(t *testing.T) {
			t.Parallel()

			r := &countingReader{data: makeHeader(test.flags, test.expectedSize, test.reservedSize)}
			c := &Connection{maxRecvSize: test.maxRecvSize}

			_, err := c.read(r, nil)
			if err == nil {
				t.Fatal("expected error, got nil")
			}

			if err.Error() != test.wantErr {
				t.Errorf("expected error %q, got: %s", test.wantErr, err)
			}

			if r.reads != 1 {
				t.Errorf("expected exactly 1 Read, got %d", r.reads)
			}
		})
	}
}

func checkSuccessfulUncompress(t *testing.T, data []byte, expLen uint32, wantData []byte, err error) {
	t.Helper()

	if err != nil {
		t.Fatalf("uncompress() unexpected error: %s", err)
	}

	if len(data) != int(expLen) {
		t.Errorf("uncompress() output length = %d, want %d", len(data), expLen)
	}

	if wantData != nil && !bytes.Equal(data, wantData) {
		t.Errorf("uncompress() output = %q, want %q", data, wantData)
	}
}

func checkUncompressError(t *testing.T, err error, wantErr string, wantInvalidZlib bool) {
	t.Helper()

	if err == nil {
		t.Fatal("uncompress() expected error, got nil")
	}

	if wantInvalidZlib {
		if !errors.Is(err, zlib.ErrHeader) {
			t.Errorf("expected error to wrap zlib.ErrHeader, got: %s", err)
		}

		return
	}

	if err.Error() != wantErr {
		t.Errorf("expected error %q, got: %s", wantErr, err)
	}
}

func checkUncompressResult(t *testing.T, data []byte, expLen uint32, wantData []byte, err error, wantErr string,
	wantInvalidZlib bool) {
	t.Helper()

	if wantErr == "" && !wantInvalidZlib {
		checkSuccessfulUncompress(t, data, expLen, wantData, err)

		return
	}

	checkUncompressError(t, err, wantErr, wantInvalidZlib)
}

func TestUncompressExpectedLength(t *testing.T) {
	t.Parallel()

	const expLenFixture = 100

	tests := []struct {
		name            string
		data            []byte
		expLen          uint32
		wantErr         string
		wantInvalidZlib bool
		wantData        []byte
	}{
		{
			name:    "actual output exactly expLen accepted",
			data:    makeCompressedPayload(t, make([]byte, expLenFixture)),
			expLen:  expLenFixture,
			wantErr: "",
		},
		{
			name:    "actual output expLen+1 rejected",
			data:    makeCompressedPayload(t, make([]byte, expLenFixture+1)),
			expLen:  expLenFixture,
			wantErr: "Unable to uncompress message: uncompressed message size 101 instead of expected 100.",
		},
		{
			name:    "actual output expLen+2 rejected",
			data:    makeCompressedPayload(t, make([]byte, expLenFixture+2)),
			expLen:  expLenFixture,
			wantErr: "Unable to uncompress message: uncompressed message size 101 instead of expected 100.",
		},
		{
			name:    "actual output below expLen rejected",
			data:    makeCompressedPayload(t, []byte("test")),
			expLen:  10,
			wantErr: "Unable to uncompress message: uncompressed message size 4 instead of expected 10.",
		},
		{
			name:            "invalid zlib payload rejected",
			data:            []byte("not-zlib-data"),
			expLen:          4,
			wantInvalidZlib: true,
		},
		{
			name:     "valid compressed payload accepted",
			data:     makeCompressedPayload(t, []byte("test")),
			expLen:   4,
			wantErr:  "",
			wantData: []byte("test"),
		},
	}

	for index := range tests {
		test := tests[index]

		t.Run(test.name, func(t *testing.T) {
			t.Parallel()

			c := &Connection{}

			data, err := c.uncompress(test.data, test.expLen)

			checkUncompressResult(t, data, test.expLen, test.wantData, err, test.wantErr,
				test.wantInvalidZlib)
		})
	}
}

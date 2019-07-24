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

package comms

import (
	"bytes"
	"testing"
)

type Result struct {
	data   []string
	failed bool
}

var i int
var offset int

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
	//Result{data: []string{"ZBXD\x01\x0A\x00\x00\x00\x00\x00\x00\x00"}, failed: true},
}

type mockRead struct {
}

var m mockRead

func (t mockRead) Read(p []byte) (n int, err error) {
	if offset == len(results[i].data) {
		return 0, nil
	}

	n = len(results[i].data[offset])
	copy(p, results[i].data[offset])
	offset++

	return n, nil
}

func TestReceive(t *testing.T) {
	for _, result := range results {
		t.Run("test", func(t *testing.T) {
			data, err := read(m)
			if err == nil {
				if result.failed {
					t.Errorf("Error expected")
				} else {
					var buffer bytes.Buffer
					for j := 0; j < len(result.data); j++ {
						buffer.WriteString(result.data[j])
					}

					if len(buffer.Bytes()) < 13 {
						if 0 != len(buffer.Bytes()) || 0 != len(data) {
							t.Errorf("Header is missing")
						}
					} else {
						if !bytes.Equal(data, buffer.Bytes()[13:]) {
							t.Errorf("Received bytes %v mismatch expected %v", data, buffer.Bytes())
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
		})
	}
}

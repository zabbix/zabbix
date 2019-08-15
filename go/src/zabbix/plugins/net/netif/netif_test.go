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

package netif

import (
	"fmt"
	"reflect"
	"testing"
	"zabbix/pkg/std"
)

type testCase struct {
	id     uint
	name   string
	key    string
	params []string
	fail   bool
	res    interface{}
	typ    reflect.Kind
}

var netStats02 = `Inter-|   Receive                                                |  Transmit
face |bytes    packets errs drop fifo frame compressed multicast|bytes    packets errs drop fifo colls carrier compressed
   lo: 2897093   11757    0    0    0     0          0         0  2897093   11757    0    0    0     0       0          0
 eno1: 709017493  620061   15    7    1   345        500     16001 22780124  241308   87 1234    5   543       2        100
 eno2: 709017493  abc   15    7    1   345        500     16001 22780124  241308   87 1234    5   543       x        100
  lo1  2897093   11757    0    0    0     0          0         0  2897093   11757    0    0    0     0       0          0
 eno3: 709017493  620061   15    7    1   345        500     16001 22780124  241308   87 1234    5   543       2`

var netStats03 = ``

var testCases01 = []testCase{
	{1, "netStats01", "net.if.in", []string{"eno1", "bytes"}, true, uint64(709017493), reflect.Uint64},
	{2, "netStats01", "net.if.discovery", []string{}, true, "[{\"{#IFNAME}\":\"eno1\"}]", reflect.String},
	{3, "netStats01", "net.if.collisions", []string{"eno1"}, true, uint64(543), reflect.Uint64},
}

var testCases02 = []testCase{
	{0, "netStats02", "net.if.in", []string{"eno2", "packets"}, true, uint64(0), reflect.Uint64},
	{1, "netStats02", "net.if.collisions", []string{}, true, uint64(0), reflect.Uint64},
	{2, "netStats02", "net.if.collisions", []string{"eno1", "bytes"}, true, uint64(0), reflect.Uint64},
	{3, "netStats02", "net.if.collisions", []string{"eno1", ""}, true, uint64(0), reflect.Uint64},
	{4, "netStats02", "net.if.collisions", []string{"invalid1"}, true, uint64(0), reflect.Uint64},
	{5, "netStats02", "net.if.collisions", []string{"eno1"}, true, uint64(542), reflect.Uint64},
	{6, "netStats02", "net.if.collisions", []string{"eno1"}, false, uint64(543), reflect.Uint64},
	{7, "netStats02", "net.if.collisions", []string{"lo"}, false, uint64(0), reflect.Uint64},
	{8, "netStats02", "net.if.in", []string{}, true, uint64(0), reflect.Uint64},
	{9, "netStats02", "net.if.in", []string{"eno1", "bytes", "something"}, true, uint64(0), reflect.Uint64},
	{10, "netStats02", "net.if.in", []string{"invalid1"}, true, uint64(0), reflect.Uint64},
	{11, "netStats02", "net.if.in", []string{"eno1", "b"}, true, uint64(0), reflect.Uint64},
	{12, "netStats02", "net.if.in", []string{"eno1", "bytes"}, true, uint64(0), reflect.Uint64},
	{13, "netStats02", "net.if.in", []string{"eno1", "bytes"}, false, uint64(709017493), reflect.Uint64},
	{14, "netStats02", "net.if.in", []string{"eno1", ""}, false, uint64(709017493), reflect.Uint64},
	{15, "netStats02", "net.if.in", []string{"eno1"}, false, uint64(709017493), reflect.Uint64},
	{16, "netStats02", "net.if.in", []string{"eno1", "errors"}, false, uint64(15), reflect.Uint64},
	{17, "netStats02", "net.if.in", []string{"lo", "packets"}, false, uint64(11757), reflect.Uint64},
	{18, "netStats02", "net.if.out", []string{"eno1"}, false, uint64(22780124), reflect.Uint64},
	{19, "netStats02", "net.if.out", []string{"eno1", "packets"}, false, uint64(241308), reflect.Uint64},
	{20, "netStats02", "net.if.out", []string{"eno1", "dropped"}, false, uint64(1234), reflect.Uint64},
	{21, "netStats02", "net.if.out", []string{"lo", "dropped"}, false, uint64(0), reflect.Uint64},
	{22, "netStats02", "net.if.out", []string{"eno1", "carrier"}, false, uint64(2), reflect.Uint64},
	{23, "netStats02", "net.if.out", []string{"eno1", "compressed"}, false, uint64(100), reflect.Uint64},
	{24, "netStats02", "net.if.total", []string{}, true, uint64(0), reflect.Uint64},
	{25, "netStats02", "net.if.total", []string{"eno1", "bytes"}, true, uint64(709017493), reflect.Uint64},
	{26, "netStats02", "net.if.total", []string{"eno1", "bytes"}, true, uint64(22780124), reflect.Uint64},
	{27, "netStats02", "net.if.total", []string{"eno1", "bytes"}, false, uint64(731797617), reflect.Uint64},
	{28, "netStats02", "net.if.total", []string{"eno1"}, false, uint64(731797617), reflect.Uint64},
	{29, "netStats02", "net.if.total", []string{"eno1", "overruns"}, false, uint64(6), reflect.Uint64},
	{30, "netStats02", "net.if.total", []string{"eno1", "compressed"}, false, uint64(600), reflect.Uint64},
	{31, "netStats02", "net.if.total", []string{"lo", "packets"}, false, uint64(23514), reflect.Uint64},
	{32, "netStats02", "net.if.in", []string{"eno1", "multicast"}, false, uint64(16001), reflect.Uint64},
	{33, "netStats02", "net.if.in", []string{"lo", "frame"}, false, uint64(0), reflect.Uint64},
	{34, "netStats02", "net.if.in", []string{""}, true, uint64(0), reflect.Uint64},
	{35, "netStats02", "net.if.in", []string{"", "bytes"}, true, uint64(0), reflect.Uint64},
	{36, "netStats02", "net.if.collisions", []string{""}, true, uint64(0), reflect.Uint64},
	{37, "netStats02", "net.if.in", []string{"lo1", "packets"}, true, uint64(11757), reflect.Uint64},
	{38, "netStats02", "net.if.in", []string{"eno2", "packets"}, true, uint64(0), reflect.Uint64},
	{39, "netStats02", "net.if.out", []string{"eno2", "carrier"}, true, uint64(0), reflect.Uint64},
	{40, "netStats02", "net.if.total", []string{"eno2", "packets"}, true, uint64(0), reflect.Uint64},
	{41, "netStats02", "net.if.out", []string{"eno2", "packets"}, false, uint64(241308), reflect.Uint64},
	{42, "netStats02", "net.if.collisions", []string{"eno3"}, true, uint64(0), reflect.Uint64},
	{43, "netStats02", "net.if.in", []string{"eno3", "bytes"}, true, uint64(0), reflect.Uint64},
	{44, "netStats02", "net.if.out", []string{"eno1", "c"}, true, uint64(0), reflect.Uint64},
	{45, "netStats02", "net.if.discovery", []string{"eno1"}, true, "[{\"{#IFNAME}\":\"lo\"},{\"{#IFNAME}\":\"eno1\"}]", reflect.String},
	{46, "netStats02", "net.if.discovery", []string{}, false, "[{\"{#IFNAME}\":\"lo\"},{\"{#IFNAME}\":\"eno1\"},{\"{#IFNAME}\":\"eno2\"},{\"{#IFNAME}\":\"eno3\"}]", reflect.String},
	{47, "netStats02", "wrong.key", []string{}, true, uint64(0), reflect.Uint64},
}

var testCases03 = []testCase{
	{1, "netStats03", "net.if.collisions", []string{"eno1"}, true, uint64(543), reflect.Uint64},
	{2, "netStats03", "net.if.in", []string{"eno1", "bytes"}, true, uint64(709017493), reflect.Uint},
	{3, "netStats03", "net.if.discovery", []string{}, false, "[]", reflect.String},
}

func TestNetif(t *testing.T) {
	stdOs = std.NewMockOs()

	for _, testCase := range testCases01 {
		if err := testCase.checkResult(); err != nil {
			t.Errorf("Test case (%s_%d) for key %s %s", testCase.name, testCase.id, testCase.key, err.Error())
		}
	}

	stdOs.(std.MockOs).MockFile("/proc/net/dev", []byte(netStats02))
	for _, testCase := range testCases02 {
		if err := testCase.checkResult(); err != nil {
			t.Errorf("Test case (%s_%d) for key %s %s", testCase.name, testCase.id, testCase.key, err.Error())
		}
	}

	stdOs.(std.MockOs).MockFile("/proc/net/dev", []byte(netStats03))
	for _, testCase := range testCases03 {
		if err := testCase.checkResult(); err != nil {
			t.Errorf("Test case (%s_%d) for key %s %s", testCase.name, testCase.id, testCase.key, err.Error())
		}
	}
}

func (tc *testCase) checkResult() error {
	var resTextOutput string

	if ret, err := impl.Export(tc.key, tc.params, nil); err != nil {
		if tc.fail != true {
			return fmt.Errorf("returned error: %s", err)
		}
	} else {
		if typ := reflect.TypeOf(ret).Kind(); typ == tc.typ {
			if tc.typ == reflect.String {
				resTextOutput = ret.(string)
			} else {
				resTextOutput = fmt.Sprint(ret)
			}

			if ret != tc.res && tc.fail == false {
				return fmt.Errorf("returned invalid result: %s", resTextOutput)
			} else if ret == tc.res && tc.fail == true {
				return fmt.Errorf("returned valid result (%s) while not expected", resTextOutput)
			}
		} else if tc.fail == false {
			return fmt.Errorf("returned unexpected value type %s", typ)
		}
	}

	return nil
}

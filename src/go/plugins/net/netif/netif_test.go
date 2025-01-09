//go:build linux && (amd64 || arm64)

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

package netif

import (
	"fmt"
	"reflect"
	"testing"

	"golang.zabbix.com/sdk/std"
)

var testSets = []testSet{
	{"testNetif01",
		[]testCase{
			{1, "test_name", "net.if.in", []string{"eno1", "bytes"}, true, uint64(709017493), reflect.Uint64},
			{2, "test_name", "net.if.discovery", []string{}, true, "[{\"{#IFNAME}\":\"eno1\"}]", reflect.String},
			{3, "test_name", "net.if.collisions", []string{"eno1"}, true, uint64(543), reflect.Uint64},
		},
		"",
		"",
	}, {
		"testNetif02",
		[]testCase{
			{0, "test_name", "net.if.in", []string{"eno2", "packets"}, true, uint64(0), reflect.Uint64},
			{1, "test_name", "net.if.collisions", []string{}, true, uint64(0), reflect.Uint64},
			{2, "test_name", "net.if.collisions", []string{"eno1", "bytes"}, true, uint64(0), reflect.Uint64},
			{3, "test_name", "net.if.collisions", []string{"eno1", ""}, true, uint64(0), reflect.Uint64},
			{4, "test_name", "net.if.collisions", []string{"invalid1"}, true, uint64(0), reflect.Uint64},
			{5, "test_name", "net.if.collisions", []string{"eno1"}, true, uint64(542), reflect.Uint64},
			{6, "test_name", "net.if.collisions", []string{"eno1"}, false, uint64(543), reflect.Uint64},
			{7, "test_name", "net.if.collisions", []string{"lo"}, false, uint64(0), reflect.Uint64},
			{8, "test_name", "net.if.in", []string{}, true, uint64(0), reflect.Uint64},
			{9, "test_name", "net.if.in", []string{"eno1", "bytes", "something"}, true, uint64(0), reflect.Uint64},
			{10, "test_name", "net.if.in", []string{"invalid1"}, true, uint64(0), reflect.Uint64},
			{11, "test_name", "net.if.in", []string{"eno1", "b"}, true, uint64(0), reflect.Uint64},
			{12, "test_name", "net.if.in", []string{"eno1", "bytes"}, true, uint64(0), reflect.Uint64},
			{13, "test_name", "net.if.in", []string{"eno1", "bytes"}, false, uint64(709017493), reflect.Uint64},
			{14, "test_name", "net.if.in", []string{"eno1", ""}, false, uint64(709017493), reflect.Uint64},
			{15, "test_name", "net.if.in", []string{"eno1"}, false, uint64(709017493), reflect.Uint64},
			{16, "test_name", "net.if.in", []string{"eno1", "errors"}, false, uint64(15), reflect.Uint64},
			{17, "test_name", "net.if.in", []string{"lo", "packets"}, false, uint64(11757), reflect.Uint64},
			{18, "test_name", "net.if.out", []string{"eno1"}, false, uint64(22780124), reflect.Uint64},
			{19, "test_name", "net.if.out", []string{"eno1", "packets"}, false, uint64(241308), reflect.Uint64},
			{20, "test_name", "net.if.out", []string{"eno1", "dropped"}, false, uint64(1234), reflect.Uint64},
			{21, "test_name", "net.if.out", []string{"lo", "dropped"}, false, uint64(0), reflect.Uint64},
			{22, "test_name", "net.if.out", []string{"eno1", "carrier"}, false, uint64(2), reflect.Uint64},
			{23, "test_name", "net.if.out", []string{"eno1", "compressed"}, false, uint64(100), reflect.Uint64},
			{24, "test_name", "net.if.total", []string{}, true, uint64(0), reflect.Uint64},
			{25, "test_name", "net.if.total", []string{"eno1", "bytes"}, true, uint64(709017493), reflect.Uint64},
			{26, "test_name", "net.if.total", []string{"eno1", "bytes"}, true, uint64(22780124), reflect.Uint64},
			{27, "test_name", "net.if.total", []string{"eno1", "bytes"}, false, uint64(731797617), reflect.Uint64},
			{28, "test_name", "net.if.total", []string{"eno1"}, false, uint64(731797617), reflect.Uint64},
			{29, "test_name", "net.if.total", []string{"eno1", "overruns"}, false, uint64(6), reflect.Uint64},
			{30, "test_name", "net.if.total", []string{"eno1", "compressed"}, false, uint64(600), reflect.Uint64},
			{31, "test_name", "net.if.total", []string{"lo", "packets"}, false, uint64(23514), reflect.Uint64},
			{32, "test_name", "net.if.in", []string{"eno1", "multicast"}, false, uint64(16001), reflect.Uint64},
			{33, "test_name", "net.if.in", []string{"lo", "frame"}, false, uint64(0), reflect.Uint64},
			{34, "test_name", "net.if.in", []string{""}, true, uint64(0), reflect.Uint64},
			{35, "test_name", "net.if.in", []string{"", "bytes"}, true, uint64(0), reflect.Uint64},
			{36, "test_name", "net.if.collisions", []string{""}, true, uint64(0), reflect.Uint64},
			{37, "test_name", "net.if.in", []string{"lo1", "packets"}, true, uint64(11757), reflect.Uint64},
			{38, "test_name", "net.if.in", []string{"eno2", "packets"}, true, uint64(0), reflect.Uint64},
			{39, "test_name", "net.if.out", []string{"eno2", "carrier"}, true, uint64(0), reflect.Uint64},
			{40, "test_name", "net.if.total", []string{"eno2", "packets"}, true, uint64(0), reflect.Uint64},
			{41, "test_name", "net.if.out", []string{"eno2", "packets"}, false, uint64(241308), reflect.Uint64},
			{42, "test_name", "net.if.collisions", []string{"eno3"}, true, uint64(0), reflect.Uint64},
			{43, "test_name", "net.if.in", []string{"eno3", "bytes"}, true, uint64(0), reflect.Uint64},
			{44, "test_name", "net.if.out", []string{"eno1", "c"}, true, uint64(0), reflect.Uint64},
			{45, "test_name", "net.if.discovery", []string{"eno1"}, true, "[{\"{#IFNAME}\":\"lo\"},{\"{#IFNAME}\":\"eno1\"}]", reflect.String},
			{46, "test_name", "net.if.discovery", []string{}, false, "[{\"{#IFNAME}\":\"lo\"},{\"{#IFNAME}\":\"eno1\"},{\"{#IFNAME}\":\"eno2\"},{\"{#IFNAME}\":\"eno3\"}]", reflect.String},
			{47, "test_name", "wrong.key", []string{}, true, uint64(0), reflect.Uint64},
		},
		"/proc/net/dev",
		`Inter-|   Receive                                                |  Transmit
face |bytes    packets errs drop fifo frame compressed multicast|bytes    packets errs drop fifo colls carrier compressed
   lo: 2897093   11757    0    0    0     0          0         0  2897093   11757    0    0    0     0       0          0
 eno1: 709017493  620061   15    7    1   345        500     16001 22780124  241308   87 1234    5   543       2        100
 eno2: 709017493  abc   15    7    1   345        500     16001 22780124  241308   87 1234    5   543       x        100
  lo1  2897093   11757    0    0    0     0          0         0  2897093   11757    0    0    0     0       0          0
 eno3: 709017493  620061   15    7    1   345        500     16001 22780124  241308   87 1234    5   543       2`,
	}, {
		"testNetif03",
		[]testCase{
			{1, "test_name", "net.if.collisions", []string{"eno1"}, true, uint64(543), reflect.Uint64},
			{2, "test_name", "net.if.in", []string{"eno1", "bytes"}, true, uint64(709017493), reflect.Uint},
			{3, "test_name", "net.if.discovery", []string{}, false, "[]", reflect.String},
		},
		"/proc/net/dev",
		"",
	},
}

type testCase struct {
	id     uint
	name   string
	key    string
	params []string
	fail   bool
	res    interface{}
	typ    reflect.Kind
}

type testSet struct {
	name        string
	testCases   []testCase
	fileName    string
	fileContent string
}

func TestNetif(t *testing.T) {
	stdOs = std.NewMockOs()

	for _, testSet := range testSets {
		if testSet.fileName != "" {
			stdOs.(std.MockOs).MockFile(testSet.fileName, []byte(testSet.fileContent))
		}

		for _, testCase := range testSet.testCases {
			if err := testCase.checkResult(); err != nil {
				t.Errorf("Test case (%s: %s_%d) for key %s %s", testSet.name, testCase.name, testCase.id, testCase.key, err.Error())
			}
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

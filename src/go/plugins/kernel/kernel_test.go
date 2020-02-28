// +build linux,amd64

/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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

package kernel

import (
	"fmt"
	"reflect"
	"testing"

	"zabbix.com/pkg/std"
)

var testSets = []testSet{
	{"testKernel01",
		[]testCase{
			{1, "test_name", "kernel.maxfiles", []string{}, true, uint64(18446744073709551615), reflect.Uint64},
			{2, "test_name", "kernel.maxproc", []string{}, true, uint64(18446744073709551615), reflect.Uint64},
		},
		"",
		"",
	}, {
		"testKernel02",
		[]testCase{
			{1, "test_name", "kernel.maxfiles", []string{}, false, uint64(18446744073709551615), reflect.Uint64},
			{2, "test_name", "kernel.maxfiles", []string{""}, true, uint64(18446744073709551615), reflect.Uint64},
			{3, "test_name", "kernel.maxfiles", []string{"param"}, true, uint64(18446744073709551615), reflect.Uint64},
			{4, "test_name", "wrong.key", []string{}, true, uint64(18446744073709551615), reflect.Uint64},
		},
		"/proc/sys/fs/file-max",
		"18446744073709551615\n",
	}, {
		"testKernel03",
		[]testCase{
			{1, "test_name", "kernel.maxproc", []string{}, false, uint64(18446744073709551615), reflect.Uint64},
			{2, "test_name", "kernel.maxproc", []string{""}, true, uint64(18446744073709551615), reflect.Uint64},
			{3, "test_name", "kernel.maxproc", []string{"param"}, true, uint64(18446744073709551615), reflect.Uint64},
		},
		"/proc/sys/kernel/pid_max",
		"18446744073709551615\n",
	}, {
		"testKernel04",
		[]testCase{
			{1, "test_name", "kernel.maxproc", []string{}, false, uint64(18446744073709551615), reflect.Uint64},
		},
		"/proc/sys/kernel/pid_max",
		"18446744073709551615",
	}, {
		"testKernel05",
		[]testCase{
			{1, "test_name", "kernel.maxfiles", []string{}, true, uint64(18446744073709551615), reflect.Uint64},
		},
		"/proc/sys/fs/file-max",
		"18446744073709551616",
	}, {
		"testKernel06",
		[]testCase{
			{1, "test_name", "kernel.maxproc", []string{}, true, uint64(18446744073709551615), reflect.Uint64},
		},
		"/proc/sys/kernel/pid_max",
		"abc123",
	}, {
		"testKernel07",
		[]testCase{
			{1, "test_name", "kernel.maxfiles", []string{}, true, uint64(18446744073709551615), reflect.Uint64},
		},
		"/proc/sys/fs/file-max",
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

func TestKernel(t *testing.T) {
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

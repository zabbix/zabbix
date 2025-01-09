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

package kernel

import (
	"fmt"
	"reflect"
	"testing"

	"golang.zabbix.com/sdk/std"
)

var testSets = []testSet{
	{
		"testKernel01_empty",
		[]testCase{
			{1, "maxfiles", "kernel.maxfiles", []string{}, true, uint64(18446744073709551615), reflect.Uint64},
			{2, "maxproc", "kernel.maxproc", []string{}, true, uint64(18446744073709551615), reflect.Uint64},
			{3, "openfiles", "kernel.openfiles", []string{}, true, uint64(18446744073709551615), reflect.Uint64},
		},
		"",
		"",
	}, {
		"testKernel02_file-max",
		[]testCase{
			{1, "maxfiles_no_params", "kernel.maxfiles", []string{}, false, uint64(18446744073709551615), reflect.Uint64},
			{2, "maxfiles_empty_pram_value", "kernel.maxfiles", []string{""}, true, uint64(18446744073709551615), reflect.Uint64},
			{3, "maxfiles_with_params", "kernel.maxfiles", []string{"param"}, true, uint64(18446744073709551615), reflect.Uint64},
			{4, "wrong_key", "wrong.key", []string{}, true, uint64(18446744073709551615), reflect.Uint64},
		},
		"/proc/sys/fs/file-max",
		"18446744073709551615\n",
	}, {
		"testKernel03_pid_max",
		[]testCase{
			{1, "maxproc_no_params", "kernel.maxproc", []string{}, false, uint64(18446744073709551615), reflect.Uint64},
			{2, "maxproc_empty_pram_value", "kernel.maxproc", []string{""}, true, uint64(18446744073709551615), reflect.Uint64},
			{3, "maxproc_with_params", "kernel.maxproc", []string{"param"}, true, uint64(18446744073709551615), reflect.Uint64},
		},
		"/proc/sys/kernel/pid_max",
		"18446744073709551615\n",
	}, {
		"testKernel04_pid_max_no_new_line",
		[]testCase{
			{1, "maxproc_no_params", "kernel.maxproc", []string{}, false, uint64(18446744073709551615), reflect.Uint64},
		},
		"/proc/sys/kernel/pid_max",
		"18446744073709551615",
	}, {
		"testKernel05_file-max_no_new_line",
		[]testCase{
			{1, "maxfiles_no_params", "kernel.maxfiles", []string{}, true, uint64(18446744073709551615), reflect.Uint64},
		},
		"/proc/sys/fs/file-max",
		"18446744073709551616",
	}, {
		"testKernel06_pid_max_short_file",
		[]testCase{
			{1, "maxproc_no_params", "kernel.maxproc", []string{}, true, uint64(18446744073709551615), reflect.Uint64},
		},
		"/proc/sys/kernel/pid_max",
		"abc123",
	}, {
		"testKernel07_file-max_empty_file",
		[]testCase{
			{1, "maxfiles_no_params", "kernel.maxfiles", []string{}, true, uint64(18446744073709551615), reflect.Uint64},
		},
		"/proc/sys/fs/file-max",
		"",
	}, {
		"testKernel08_file-nr",
		[]testCase{
			{1, "openfiles_no_params", "kernel.openfiles", []string{}, false, uint64(18446744073709551615), reflect.Uint64},
			{2, "openfiles_empty_pram_value", "kernel.openfiles", []string{""}, true, uint64(18446744073709551615), reflect.Uint64},
			{3, "openfiles_with_params", "kernel.openfiles", []string{"param"}, true, uint64(18446744073709551615), reflect.Uint64},
			{4, "wrong_key", "wrong.key", []string{}, true, uint64(18446744073709551615), reflect.Uint64},
		},
		"/proc/sys/fs/file-nr",
		"18446744073709551615\n",
	}, {
		"testKernel09_file-nr_no_new_line",
		[]testCase{
			{1, "openfiles_no_params", "kernel.openfiles", []string{}, true, uint64(18446744073709551615), reflect.Uint64},
		},
		"/proc/sys/fs/file-nr",
		"18446744073709551616",
	}, {
		"testKernel10_file-nr_empty_file",
		[]testCase{
			{1, "openfiles_no_params", "kernel.openfiles", []string{}, true, uint64(18446744073709551615), reflect.Uint64},
		},
		"/proc/sys/fs/file-nr",
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

// +build linux

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

package conf

import (
	"reflect"
	"strings"
	"testing"
	"zabbix/pkg/std"
)

func TestParserErrors(t *testing.T) {
	type Options struct {
		Test  string `conf:"Te$t,optional"`
		Range string `conf:",optional,1"`
	}

	var input = []string{
		"abc",
		"abc =",
		" = abc",
		"Test = value",
		"Te$t = value",
		"Range=1",
	}

	for _, data := range input {
		var options Options
		if err := Unmarshal([]byte(data), &options); err != nil {
			t.Logf("Returned error: %s", err.Error())
		} else {
			t.Errorf("Successfully parsed conf: %s", data)
		}
	}
}

func TestParserSuccess(t *testing.T) {
	type Options struct {
		Text string `conf:",optional"`
	}

	var input = []string{
		"Text=1",
		" Text = 2 ",
		"Text = 3\nText=4",
		"# comments\nText=5",
		" # comments\nText=6",
		"    \nText=7",
		"Text=8=9",
		"Text=",
		"Text=9\n#",
		"Text=10\n",
		"\n Text = 11 \n",
		"\n#####Text=x\nText=12",
	}

	var output = []Options{
		{Text: "1"},
		{Text: "2"},
		{Text: "4"},
		{Text: "5"},
		{Text: "6"},
		{Text: "7"},
		{Text: "8=9"},
		{Text: ""},
		{Text: "9"},
		{Text: "10"},
		{Text: "11"},
		{Text: "12"},
	}

	for i, data := range input {
		var options Options
		if err := Unmarshal([]byte(data), &options); err != nil {
			t.Logf("[%d] returned error: %s", i, err.Error())
			t.Fail()
		}
		if options.Text != output[i].Text {
			t.Errorf("[%d] expected %s while got %s\n", i, output[i].Text, options.Text)
		}
	}
}

func TestUtf8(t *testing.T) {
	type Options struct {
		Text string `conf:",optional"`
	}

	var input = []string{
		"Text=\xFE",
		"Text\xFE=2",
	}

	for i, data := range input {
		var options Options
		if err := Unmarshal([]byte(data), &options); err == nil {
			t.Errorf("[%d] expected error while got success\n", i)
		}
	}
}

func TestParserRangeErrors(t *testing.T) {
	type Options struct {
		Value int `conf:",,-10:10"`
	}

	var input = []string{
		`Value=-11`,
		`Value=-10.5`,
		`Value=10.5`,
		`Value=11`,
	}

	for i, data := range input {
		var options Options
		if err := Unmarshal([]byte(data), &options); err != nil {
			t.Logf("Returned error: %s", err.Error())
		} else {
			t.Errorf("[%d] expected error while got success", i)
		}
	}
}

func TestParserExistanceErrors(t *testing.T) {
	type Options struct {
		Text  string
		Value int
	}

	var input = []string{
		`Value=1`,
		`Value=1
		 Text=1
		 None=1`,
	}

	for i, data := range input {
		var options Options
		if err := Unmarshal([]byte(data), &options); err != nil {
			t.Logf("Returned error: %s", err.Error())
		} else {
			t.Errorf("[%d] expected error while got %+v", i, options)
		}
	}
}

func checkUnmarshal(t *testing.T, data []byte, expected interface{}, options interface{}) {
	if err := Unmarshal([]byte(data), options); err != nil {
		t.Errorf("Expected success while got error: %s", err.Error())
	}
	if !reflect.DeepEqual(options, expected) {
		t.Errorf("Expected %+v while got %+v", expected, options)
	}
}

func TestNestedPointer(t *testing.T) {
	type Options struct {
		Pointer ***int
	}
	input := `Pointer = 42`

	value := 42
	pvalue := &value
	ppvalue := &pvalue
	var options Options
	var expected Options = Options{&ppvalue}
	checkUnmarshal(t, []byte(input), &expected, &options)
}

func TestArray(t *testing.T) {
	type Options struct {
		Values []int `conf:"Value"`
	}
	input := `
			Value = 1
			Value = 2
			Value = 3`

	var options Options
	var expected Options = Options{[]int{1, 2, 3}}
	checkUnmarshal(t, []byte(input), &expected, &options)
}

func TestNestedArray(t *testing.T) {
	type Options struct {
		Values [][]int `conf:"Value"`
	}
	input := `
			Value.1 = 1
			Value.1 = 2
			Value.2 = 3
			Value.2 = 4
			Value.3 = 5
			Value.3 = 6`

	var options Options
	var expected Options = Options{[][]int{[]int{1, 2}, []int{3, 4}, []int{5, 6}}}
	checkUnmarshal(t, []byte(input), &expected, &options)
}

func TestOptional(t *testing.T) {
	type Options struct {
		Text *string `conf:",optional"`
	}
	input := ``

	var options Options
	var expected Options = Options{nil}
	checkUnmarshal(t, []byte(input), &expected, &options)
}

func TestDefault(t *testing.T) {
	type Options struct {
		Text string `conf:",,,Default, \"value\""`
	}
	input := ``

	var options Options
	var expected Options = Options{`Default, "value"`}
	checkUnmarshal(t, []byte(input), &expected, &options)
}

func TestMap(t *testing.T) {
	type Options struct {
		Index map[string]uint64
	}
	input := `
			Index.apple = 9
			Index.orange = 7
			Index.banana = 3
		`

	var options Options
	var expected Options = Options{map[string]uint64{"apple": 9, "orange": 7, "banana": 3}}
	checkUnmarshal(t, []byte(input), &expected, &options)
}

func TestStructMap(t *testing.T) {
	type Object struct {
		Id          uint64
		Description string
	}
	type Options struct {
		Index map[string]Object
	}
	input := `
			Index.apple.Id = 9
			Index.apple.Description = An apple
			Index.orange.Id = 7
			Index.orange.Description = An orange
			Index.banana.Id = 3
			Index.banana.Description = A banana
		`

	var options Options
	var expected Options = Options{map[string]Object{
		"apple":  Object{9, "An apple"},
		"orange": Object{7, "An orange"},
		"banana": Object{3, "A banana"}}}
	checkUnmarshal(t, []byte(input), &expected, &options)
}

func TestStructPtrMap(t *testing.T) {
	type Object struct {
		Id          uint64
		Description string
	}
	type Options struct {
		Index map[string]*Object
	}
	input := `
			Index.apple.Id = 9
			Index.apple.Description = An apple
			Index.orange.Id = 7
			Index.orange.Description = An orange
			Index.banana.Id = 3
			Index.banana.Description = A banana
		`

	objects := []Object{Object{9, "An apple"}, Object{7, "An orange"}, Object{3, "A banana"}}
	var options Options
	var expected Options = Options{map[string]*Object{
		"apple":  &objects[0],
		"orange": &objects[1],
		"banana": &objects[2]}}
	checkUnmarshal(t, []byte(input), &expected, &options)
}

func TestNestedStruct(t *testing.T) {
	type Object struct {
		Id   uint64
		Name string
	}
	type Options struct {
		Chair Object
		Desk  Object
	}
	input := `
			Chair.Id = 1
			Chair.Name = a chair
			Desk.Id = 2
			Desk.Name = a desk
		`

	var options Options
	var expected Options = Options{Object{1, "a chair"}, Object{2, "a desk"}}
	checkUnmarshal(t, []byte(input), &expected, &options)
}

func TestInclude(t *testing.T) {
	stdOs = std.NewMockOs()
	stdOs.(std.MockOs).MockFile("/tmp/array10.conf", []byte("Value=10\nValue=20"))
	stdOs.(std.MockOs).MockFile("/tmp/array100.conf", []byte("Value=100\nValue=200"))

	type Options struct {
		Values []int `conf:"Value"`
	}
	input := `
			Value = 1
			Include = /tmp/array10.conf
			Value = 2
			Include = /tmp/array100.conf
			Value = 3
		`

	var options Options
	var expected Options = Options{[]int{1, 10, 20, 2, 100, 200, 3}}
	checkUnmarshal(t, []byte(input), &expected, &options)
}

func TestRecursiveInclude(t *testing.T) {
	stdOs = std.NewMockOs()
	stdOs.(std.MockOs).MockFile("/tmp/array10.conf", []byte("Value=10\nValue=20\nInclude = /tmp/array10.conf"))

	type Options struct {
		Values []int `conf:"Value"`
	}
	input := `
			Value = 1
			Include = /tmp/array10.conf
			Value = 2
		`

	var options Options
	if err := Unmarshal([]byte(input), &options); err != nil {
		if !strings.Contains(err.Error(), "include depth exceeded limits") {
			t.Errorf("Expected recursion error message while got: %s", err.Error())
		}
	} else {
		t.Errorf("Expected error while got success")
	}
}

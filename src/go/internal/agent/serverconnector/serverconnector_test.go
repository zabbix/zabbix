package serverconnector

import (
	"reflect"
	"testing"

	"zabbix.com/internal/agent"
)

type ParseServerActiveParams struct {
	serverActive string
	isError      bool
	result       [][]string
}

func TestParseServerActive(t *testing.T) {

	var inputs = []ParseServerActiveParams{
		{"fe80::72d5:8d8b:b2ca:206", false, [][]string{{"[fe80::72d5:8d8b:b2ca:206]:10051"}}},
		{"", false, [][]string{}},
		{" [ ]:80 ", true, nil},
		{" :80 ", true, nil},
		{" 0 ", false, [][]string{{"0:10051"}}},
		{"127.0.0.1", false, [][]string{{"127.0.0.1:10051"}}},
		{"::1", false, [][]string{{"[::1]:10051"}}},
		{"aaa", false, [][]string{{"aaa:10051"}}},
		{"127.0.0.1:123", false, [][]string{{"127.0.0.1:123"}}},
		{"::1:123", false, [][]string{{"[::1:123]:10051"}}},
		{"aaa:123", false, [][]string{{"aaa:123"}}},
		{"[127.0.0.1]:123", false, [][]string{{"127.0.0.1:123"}}},
		{"[::1]:123", false, [][]string{{"[::1]:123"}}},
		{"[aaa]:123", false, [][]string{{"aaa:123"}}},
		{"[ ::1 ]", false, [][]string{{"[::1]:10051"}}},
		{"fe80::72d5:8d8b:b2ca:206, [fe80::72d5:8d8b:b2ca:207]:10052", false,
			[][]string{{"[fe80::72d5:8d8b:b2ca:206]:10051"}, {"[fe80::72d5:8d8b:b2ca:207]:10052"}}},
		{",", true, nil},
		{" , ", true, nil},
		{"127.0.0.1 , 127.0.0.2:10052 ", false, [][]string{{"127.0.0.1:10051"}, {"127.0.0.2:10052"}}},
		{"127.0.0.1,127.0.0.2:10052", false, [][]string{{"127.0.0.1:10051"}, {"127.0.0.2:10052"}}},
		{"::1, ::2", false, [][]string{{"[::1]:10051"}, {"[::2]:10051"}}},
		{"aaa, aab", false, [][]string{{"aaa:10051"}, {"aab:10051"}}},
		{"aaa:10052,aab", false, [][]string{{"aaa:10052"}, {"aab:10051"}}},
		{"127.0.0.1:123,127.0.0.2:123", false, [][]string{{"127.0.0.1:123"}, {"127.0.0.2:123"}}},
		{"::2:123,[::1:123]:10052", false, [][]string{{"[::2:123]:10051"}, {"[::1:123]:10052"}}},
		{"aaa:123,aab:123", false, [][]string{{"aaa:123"}, {"aab:123"}}},
		{"[127.0.0.1]:123,[127.0.0.2]:123", false, [][]string{{"127.0.0.1:123"}, {"127.0.0.2:123"}}},
		{"[::1]:123,[::2]:123", false, [][]string{{"[::1]:123"}, {"[::2]:123"}}},
		{"[aaa]:123,[aab]:123", false, [][]string{{"aaa:123"}, {"aab:123"}}},
		{"abc,aaa", false, [][]string{{"abc:10051"}, {"aaa:10051"}}},
		{"foo;bar,baz", false, [][]string{{"foo:10051", "bar:10051"}, {"baz:10051"}}},
		{"foo:10051;bar:10052,baz:10053", false, [][]string{{"foo:10051", "bar:10052"}, {"baz:10053"}}},
		{"foo,foo", true, nil},
		{"foo;foo", true, nil},
		{"foo;bar,foo2;foo", true, nil},
		{";", true, nil},
		{" ;", true, nil},
		{"; ", true, nil},
		{" ; ", true, nil},
	}

	for i, p := range inputs {
		var al [][]string
		var err error

		agent.Options.ServerActive = p.serverActive
		if al, err = ParseServerActive(); nil != err && true != p.isError {
			t.Errorf("[%d] test with value \"%s\" failed: %s\n", i, p.serverActive, err.Error())
			continue
		}

		if p.isError {
			continue
		}

		if len(al) != len(p.result) {
			t.Errorf("[%d] test with value \"%s\" failed, expect: %d got: %d address in the list\n", i, p.serverActive, len(p.result), len(al))
		} else if !reflect.DeepEqual(al, p.result) {
			t.Errorf("[%d] test with value \"%s\" failed: received value: %s does not match: %s\n", i, p.serverActive, al, p.result)
		}
	}
}

package serverconnector

import (
	"net"
	"testing"
	"zabbix/internal/agent"
)

type Params struct {
	serverActive string
	resultCount  int
	expectPort   string
}

var inputs = []Params{
	{"fe80::72d5:8d8b:b2ca:206", 1, "10051"},
	{"", 0, "0"},
	{" ", 0, "0"},
	{" [ ] ", -1, "0"},
	{" [ ]:80 ", -1, "0"},
	{" :80 ", -1, "0"},
	{" 0 ", 1, "10051"},
	{"127.0.0.1", 1, "10051"},
	{"::1", 1, "10051"},
	{"aaa", 1, "10051"},
	{"127.0.0.1:123", 1, "123"},
	{"::1:123", 1, "10051"},
	{"aaa:123", 1, "123"},
	{"[127.0.0.1]:123", 1, "123"},
	{"[::1]:123", 1, "123"},
	{"[aaa]:123", 1, "123"},

	{"fe80::72d5:8d8b:b2ca:206, [fe80::72d5:8d8b:b2ca:207]:10051", 2, "10051"},
	{",", -1, "0"},
	{" , ", -1, "0"},
	{"127.0.0.1 , 127.0.0.2:10051 ", 2, "10051"},
	{"127.0.0.1,127.0.0.2:10051", 2, "10051"},
	{"::1, ::2", 2, "10051"},
	{"aaa, aab", 2, "10051"},
	{"aaa:10051,aab", 2, "10051"},
	{"127.0.0.1:123,127.0.0.2:123", 2, "123"},
	{"::2:123,[::1:123]:10051", 2, "10051"},
	{"aaa:123,aab:123", 2, "123"},
	{"[127.0.0.1]:123,[127.0.0.2]:123", 2, "123"},
	{"[::1]:123,[::2]:123", 2, "123"},
	{"[aaa]:123,[aab]:123", 2, "123"},
	{"abc,aaa", 2, "10051"},
}

func TestParseServerActive(t *testing.T) {
	for i, p := range inputs {
		var al []string
		var err error

		agent.Options.ServerActive = p.serverActive
		if al, err = ParseServerActive(); nil != err && p.resultCount != -1 {
			t.Errorf("[%d] test with value \"%s\" failed: %s\n", i, p.serverActive, err.Error())
			continue
		}

		if p.resultCount == -1 {
			continue
		}

		if len(al) != p.resultCount {
			t.Errorf("[%d] test with value \"%s\" failed, expect: %d got: %d address in the list\n", i, p.serverActive, p.resultCount, len(al))
		}

		for _, s := range al {
			var port string
			if _, port, err = net.SplitHostPort(s); err != nil {
				t.Errorf("[%d] test with value \"%s\" failed on: %s with error: %s\n", i, p.serverActive, s, err.Error())
			}
			if port != p.expectPort {
				t.Errorf("[%d] test with value \"%s\" failed on: %s with error: expected port %s does not match %s\n", i, p.serverActive, s, p.expectPort, port)
			}
		}
	}
}

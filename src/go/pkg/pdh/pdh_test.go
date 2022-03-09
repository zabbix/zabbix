//go:build windows
// +build windows

package pdh

import (
	"fmt"
	"testing"

	"zabbix.com/pkg/win32"
)

func BenchmarkCreateQuery(b *testing.B) {
	for i := 0; i < b.N; i++ {
		_, _ = GetCounterInt64(CounterPath(ObjectSystem, CounterSystemUptime))
	}
}

func BenchmarkReuseQuery(b *testing.B) {
	query, _ := win32.PdhOpenQuery(nil, 0)
	counter, _ := win32.PdhAddCounter(query, CounterPath(ObjectSystem, CounterSystemUptime), 0)
	for i := 0; i < b.N; i++ {
		_ = win32.PdhCollectQueryData(query)
		_, _ = win32.PdhGetFormattedCounterValueInt64(counter)
	}
	_ = win32.PdhCloseQuery(query)
}

func TestConvertPath(t *testing.T) {

	path, err := ConvertPath(CounterPath(ObjectSystem, CounterSystemUptime))
	if err != nil {
		fmt.Printf("error: %s", err)
	} else {
		fmt.Printf("path: %s", path)
	}
}

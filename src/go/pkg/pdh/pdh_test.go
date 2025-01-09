//go:build windows
// +build windows

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

package pdh

import (
	"fmt"
	"testing"

	"golang.zabbix.com/agent2/pkg/win32"
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

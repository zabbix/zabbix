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

package conn

import (
	"sync"
	"testing"
	"time"

	"golang.zabbix.com/sdk/log"
	"golang.zabbix.com/sdk/uri"
)

var _ radosConnection = (*mockConn)(nil)

type mockConn struct {
}

func (m *mockConn) Connect() error {
	return nil
}

func (m *mockConn) Shutdown() {}

func (m *mockConn) SetConfigOption(_ string, _ string) error {
	return nil
}

func (m *mockConn) MonCommand(_ []byte) ([]byte, string, error) {
	return nil, "", nil
}

// This benchmark tests the concurrency handling of getConn.
// It simulates a high-contention race condition to verify that only
// one connection is established and returned, while any superfluous
// connections are properly closed.
func TestManager_getConn_concurrency(t *testing.T) {
	t.Parallel()

	m := NewManager(10*time.Second, 10, log.New("TestManager_getConn_concurrency"))

	// Before test change this to valid connection
	testURI, err := uri.NewWithCreds("127.0.0.1:3300", "", "", nil)
	if err != nil {
		t.Fatalf("failed to parse URI: %v", err)
	}

	concurrencyLevel := 100

	var wg sync.WaitGroup

	wg.Add(concurrencyLevel)

	// This map will store the pointers of the connections returned to each goroutine.
	// A mutex is needed to safely write to it from multiple goroutines.
	returnedConns := make(map[*Conn]struct{})

	var mu sync.Mutex

	oldFunc := newRadosConn
	newRadosConn = func(_ string) (radosConnection, error) {
		return &mockConn{}, nil
	}

	t.Cleanup(func() {
		newRadosConn = oldFunc
	})

	// 1. Execution
	// Launch many goroutines to call getConn at the same time.
	for range concurrencyLevel {
		go func() {
			defer wg.Done()

			conn, err := m.getConn(testURI)
			if err != nil {
				// Use t.Errorf to report the error without stopping the entire test,
				// allowing other goroutines to complete.
				t.Errorf("getConn returned an unexpected error: %v", err)

				return
			}

			// Record the connection pointer received by this goroutine.
			mu.Lock()

			returnedConns[conn] = struct{}{}

			mu.Unlock()
		}()
	}

	wg.Wait()

	// 2. Verification
	// Check if only ONE unique connection instance was created and distributed.
	// If singleflight works, len(returnedConns) should be 1.
	if len(returnedConns) != 1 {
		t.Fatalf("ConnManager.getConn() concurrency test failed: expected 1 connection instance, "+
			"but got %d", len(returnedConns))
	}

	// Additionally, check that the connection was correctly stored in the manager's map.
	finalConnCount := m.connections.Len()

	if finalConnCount != 1 {
		t.Fatalf("ConnManager.getConn() post-run check failed: expected 1 connection in the map, "+
			"but found %d", finalConnCount)
	}
}

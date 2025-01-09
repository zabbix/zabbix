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

package zbxcomms

import (
	"strings"
	"sync"
)

type addressPool struct {
	pool []address
	mu   sync.Mutex
}

// NewAddressPool creates address pool implementing AddressSet interface.
func NewAddressPool(addrs []string) AddressSet {
	a := &addressPool{
		pool: make([]address, 0, len(addrs)),
	}

	for _, s := range addrs {
		a.pool = append(a.pool, address{addr: s})
	}

	return a
}

// String returns list of all addresses.
func (a *addressPool) String() string {
	a.mu.Lock()
	defer a.mu.Unlock()

	var sb strings.Builder

	for _, addr := range a.pool {
		sb.WriteString(addr.addr)
		sb.WriteString(",")
	}

	s := sb.String()

	return s[:len(s)-1]
}

// Get returns current address as string <address>:<port>.
func (a *addressPool) Get() string {
	a.mu.Lock()
	defer a.mu.Unlock()

	if len(a.pool) == 0 {
		return ""
	}

	return a.pool[0].addr
}

func (a *addressPool) nextAddress() {
	a.pool = append(a.pool, a.pool[0])
	a.pool = append(a.pool[:0], a.pool[1:]...)
}

// Next cycles the address set by selecting next address.
func (a *addressPool) Next() {
	a.mu.Lock()
	defer a.mu.Unlock()

	a.nextAddress()
}

func (a *addressPool) reset() {
	a.mu.Lock()
	defer a.mu.Unlock()

	if 0 == a.pool[0].revision {
		return
	}

	a.nextAddress()
}

func (a *addressPool) addRedirect(addr string, revision uint64) bool {
	a.mu.Lock()
	defer a.mu.Unlock()

	for i, addr := range a.pool {
		if addr.revision != 0 {
			if revision < addr.revision {
				if i == 0 {
					a.nextAddress()
				}
				return false
			}
			a.pool = append(a.pool[:i], a.pool[i+1:]...)

			break
		}
	}

	a.pool = append(a.pool[:1], a.pool...)
	a.pool[0].addr = addr
	a.pool[0].revision = revision

	return true
}

func (a *addressPool) count() int {
	a.mu.Lock()
	defer a.mu.Unlock()

	return len(a.pool)
}

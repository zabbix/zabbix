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

type singleAddress string

// NewAddress creates single address implementing AddressSet interface.
func NewAddress(addr string) AddressSet {
	return singleAddress(addr)
}

// Get returns current address as string <address>:<port>.
func (a singleAddress) Get() string {
	return string(a)
}

// String returns list of all addresses.
func (a singleAddress) String() string {
	return string(a)
}

// Next cycles the address set by selecting next address.
func (singleAddress) Next() {
}

func (singleAddress) reset() {
}

func (singleAddress) addRedirect(_ string, _ uint64) bool {
	return false
}

func (singleAddress) count() int {
	return 1
}

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

// AddressSet interface provides address set for connections to Zabbix
// server/proxy.
type AddressSet interface {
	// Get returns current address as string <address>:<port>
	Get() string
	// String returns list of all addresses
	String() string

	// next cycles the address set by selecting next address
	Next()
	// reset cyckles addresses if the current is redirected
	reset()
	// count returns number of addresses in set
	count() int
	// addRedirect adds/updates redirected address in set
	addRedirect(addr string, revision uint64) bool
}

type address struct {
	addr     string
	revision uint64
}

/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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

package dns

import (
	"strings"
	"unsafe"

	"git.zabbix.com/ap/plugin-support/zbxerr"
	"golang.org/x/sys/windows"
)

func (o *options) setDefaultIP() error {
	l := uint32(20000)
	b := make([]byte, l)

	if err := windows.GetAdaptersAddresses(windows.AF_UNSPEC, windows.GAA_FLAG_INCLUDE_PREFIX, 0, (*windows.IpAdapterAddresses)(unsafe.Pointer(&b[0])), &l); err != nil {
		return err
	}

	var addresses []*windows.IpAdapterAddresses
	for addr := (*windows.IpAdapterAddresses)(unsafe.Pointer(&b[0])); addr != nil; addr = addr.Next {
		addresses = append(addresses, addr)
	}

	resolvers := map[string]bool{}
	for _, addr := range addresses {
		for next := addr.FirstUnicastAddress; next != nil; next = next.Next {
			if addr.OperStatus != windows.IfOperStatusUp {
				continue
			}

			if next.Address.IP() != nil {
				for dnsServer := addr.FirstDnsServerAddress; dnsServer != nil; dnsServer = dnsServer.Next {
					ip := dnsServer.Address.IP()
					if ip.IsMulticast() || ip.IsLinkLocalMulticast() || ip.IsLinkLocalUnicast() || ip.IsUnspecified() {
						continue
					}

					if ip.To16() != nil && strings.HasPrefix(ip.To16().String(), "fec0:") {
						continue
					}

					resolvers[ip.String()] = true
				}

				break
			}
		}
	}

	servers := []string{}
	for server := range resolvers {
		servers = append(servers, server)
	}

	if len(servers) < 0 {
		return zbxerr.New("no dns server found")
	}

	return o.setIP(servers[0])
}

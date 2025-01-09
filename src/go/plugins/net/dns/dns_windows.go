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

package dns

import (
	"strings"
	"unsafe"

	"golang.org/x/sys/windows"
	"golang.zabbix.com/sdk/zbxerr"
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

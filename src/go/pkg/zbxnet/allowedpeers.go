/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

package zbxnet

import (
	"net"
	"strings"
)

// AllowedPeers is preparsed content of field Server
type AllowedPeers struct {
	ips   []net.IP
	nets  []*net.IPNet
	names []string
}

// GetAllowedPeers is parses the Server field
func GetAllowedPeers(servers string) (allowedPeers *AllowedPeers, err error) {
	ap := &AllowedPeers{}

	if servers != "" {
		opts := strings.Split(servers, ",")
		for _, o := range opts {
			peer := strings.Trim(o, " \t")
			if _, peerNet, err := net.ParseCIDR(peer); nil == err {
				if ap.isPresent(peerNet) {
					continue
				}
				ap.nets = append(ap.nets, peerNet)
				maskLeadSize, maskTotalOnes := peerNet.Mask.Size()
				if 0 == maskLeadSize && 128 == maskTotalOnes {
					_, peerNet, _ = net.ParseCIDR("0.0.0.0/0")
					if !ap.isPresent(peerNet) {
						ap.nets = append(ap.nets, peerNet)
					}
				}
			} else if peerip := net.ParseIP(peer); nil != peerip {
				if ap.isPresent(peerip) {
					continue
				}
				ap.ips = append(ap.ips, peerip)
			} else if !ap.isPresent(peer) {
				ap.names = append(ap.names, peer)
			}
		}
	}

	return ap, nil
}

// CheckPeer validate incoming connection peer
func (ap *AllowedPeers) CheckPeer(ip net.IP) bool {
	if ap.checkNetIP(ip) {
		return true
	}

	for _, nameAllowed := range ap.names {
		if ips, err := net.LookupHost(nameAllowed); nil == err {
			for _, ipPeer := range ips {
				ipAllowed := net.ParseIP(ipPeer)
				if ipAllowed.Equal(ip) {
					return true
				}
			}
		}
	}

	return false
}

func (ap *AllowedPeers) isPresent(value interface{}) bool {
	switch v := value.(type) {
	case *net.IPNet:
		for _, va := range ap.nets {
			maskLeadSize, _ := va.Mask.Size()
			maskLeadSizeNew, _ := v.Mask.Size()
			if maskLeadSize <= maskLeadSizeNew && va.Contains(v.IP) {
				return true
			}
		}
	case net.IP:
		if ap.checkNetIP(v) {
			return true
		}
	case string:
		for _, v := range ap.names {
			if v == value {
				return true
			}
		}
	}

	return false
}

func (ap *AllowedPeers) checkNetIP(ip net.IP) bool {
	for _, netAllowed := range ap.nets {
		if netAllowed.Contains(ip) {
			return true
		}
	}
	for _, ipAllowed := range ap.ips {
		if ipAllowed.Equal(ip) {
			return true
		}
	}
	return false
}

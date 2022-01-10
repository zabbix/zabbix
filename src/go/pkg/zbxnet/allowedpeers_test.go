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
	"fmt"
	"net"
	"testing"

	"zabbix.com/internal/agent"
)

var testCases = []testCase{
	{1, "IPv4 match", "127.0.0.1", "127.0.0.1", false},
	{2, "Second IPv4 from list matches", "127.0.0.2", "127.0.0.1,127.0.0.2", false},
	{3, "Peer IP is different", "127.0.0.2", "127.0.0.1", true},
	{4, "Peer IP is different", "126.0.0.1", "127.0.0.1,127.0.0.2", true},
	{5, "IPv6 match", "2001:0db8:0000:0000:0000:ff00:0042:8329", "2001:0db8:0000:0000:0000:ff00:0042:8329", false},
	{6, "IPv6 from list matches", "2001:0db8:0000:0000:0000:ff00:0042:8329",
		"2001:0db8:0000:0000:0000:ff00:0042:8330,127.0.0.1,2001:0db8:0000:0000:0000:ff00:0042:8329", false},
	{7, "Peer IPv6 is different", "2001:0db8:0000:0000:0000:ff00:0042:8330", "2001:0db8:0000:0000:0000:ff00:0042:8329", true},
	{8, "Peer IPv6 is different at start", "3001:0db8:0000:0000:0000:ff00:0042:8330", "2001:0db8:0000:0000:0000:ff00:0042:8329", true},
	{9, "Peer IP is not in list", "2001:0db8:0000:0000:0000:ff00:0042:8329",
		"3001:0db8:0000:0000:0000:ff00:0042:8329,2101:0db8:0000:0000:0000:ff00:0042:8329,2001:0db9:0000:0000:0000:ff00:0042:8329," +
			"2001:0db8:0100:0000:0000:ff00:0042:8329,2001:0db8:0000:0001:0000:ff00:0042:8329,2001:0db8:0000:0000:0000:ff10:0042:8329," +
			"2001:0db8:0000:0000:0000:ff00:0043:8329", true},
	{10, "IPv6 compatible peer is connected", "::127.2.0.1", "127.2.0.1", true},
	{11, "IPv6 compatible expanded peer is connected", "0:0:0:0:0:0:7F00:0001", "127.0.0.1", true},
	{12, "IPv6 mapped peer is connected", "::ffff:127.0.0.1", "127.0.0.1", false},
	{13, "IPv6 mapped peer expanded is connected", "0:0:0:0:0:FFFF:7F00:0001", "127.0.0.1", false},
	{14, "IPv6 compatible peer mismatch IP", "::127.2.0.1", "127.1.0.1", true},
	{15, "IPv6 compatible expanded mismatch", "0:0:0:0:0:0:7F02:0001", "127.0.0.1", true},
	{16, "IPv6 mapped peer mismatch IP", "::ffff:127.0.0.1", "127.1.0.1", true},
	{17, "IPv6 mapped peer expanded mismatch IP", "0:0:0:0:0:FFFF:7F00:0001", "127.1.0.1", true},
	{18, "IPv6 peer partially compatible", "::fffe:127.0.0.1", "127.0.0.1", true},
	{19, "IPv6 peer does not match IPv4", "2001:0db8:0000:0000:0000:ff00:0042:8329", "127.0.0.1", true},
	{20, "IPv6 compatible expanded peer is connected, not in list", "F000:0:0:0:0:0:7F00:0001", "127.0.0.1", true},
	{21, "IPv6 compatible expanded peer is connected mismatch", "0000:0001:0:0:0:0:7F00:0001", "127.0.0.1", true},
	{22, "IPv6 mapped expanded is connected mismatch", "FFFF:FFFF:FFFF:FFFF:FFFF:FFFF:7F00:0001", "127.0.0.1", true},
	{23, "IPv6 local ip mismatch IPv4 local IP", "::1", "127.0.0.1", true},
	{24, "IPv4 local IP expected, but IPv6 local IP expanded connected", "0:0:0:0:0:0:0:0001", "127.0.0.1", true},
	{25, "IPv4 compatible peer is connected", "127.2.0.1", "::127.2.0.1", true},
	{26, "IPv4 compatible expanded peer is connected", "127.0.0.1", "0:0:0:0:0:0:7F00:0001", true},
	{27, "IPv4 mapped peer is connected", "127.0.0.1", "::ffff:127.0.0.1", false},
	{28, "IPv4 mapped peer expanded is connected", "127.0.0.1", "0:0:0:0:0:FFFF:7F00:0001", false},
	{29, "IPv4 compatible peer mismatch IP", "127.1.0.1", "::127.2.0.1", true},
	{30, "IPv4 compatible expanded mismatch", "127.0.0.1", "0:0:0:0:0:0:7F02:0001", true},
	{31, "IPv4 mapped peer mismatch IP", "127.1.0.1", "::ffff:127.0.0.1", true},
	{32, "IPv4 mapped peer expanded mismatch IP", "127.1.0.1", "0:0:0:0:0:FFFF:7F00:0001", true},
	{33, "IPv4 peer partially compatible", "127.0.0.1", "::fffe:127.0.0.1", true},
	{34, "IPv4 peer does not match IPv6", "127.0.0.1", "2001:0db8:0000:0000:0000:ff00:0042:8329", true},
	{35, "IPv4 compatible expanded peer is connected, not in list", "127.0.0.1", "F000:0:0:0:0:0:7F00:0001", true},
	{36, "IPv4 compatible expanded peer is connected mismatch", "127.0.0.1", "0000:0001:0:0:0:0:7F00:0001", true},
	{37, "IPv4 mapped expanded is connected mismatch", "127.0.0.1", "FFFF:FFFF:FFFF:FFFF:FFFF:FFFF:7F00:0001", true},
	{38, "IPv4 local IP mismatch IPv6 local IP", "127.0.0.1", "::1", true},
	{39, "IPv6 local expanded IP expected, but IPv4 local IP connected", "127.0.0.1", "0:0:0:0:0:0:0:0001", true},
	{40, "Compare only first 3 octets", "127.0.0.1", "127.0.0.0/24", false},
	{41, "Compare all 4 octets sanity check", "127.0.0.1", "127.0.0.0/32", true},
	{42, "IPv4 does not match address that is not compatible or mapped", "2001:0db8:0000:0000:0000:ff00:0042:8329", "127.0.0.0/0", true},
	{43, "IPv4 does not match address that is not compatible or mapped 2", "0:0:0:0:0:ff00:0042:8329", "127.0.0.0/0", true},
	{44, "IPv4 match address that is compatible or mapped", "::1", "127.0.0.0/0", true},
	{45, "Compare only first 96 bits", "0:0:0:0:0:0:0:0001", "0:0:0:0:0:0:ffff:ffff/96", false},
	{46, "Compare 128 bits", "0:0:0:0:0:0:0:0001", "0:0:0:0:0:0:ffff:ffff/128", true},
	{47, "Compare only the first 3 octets where the first one does not match", "128.0.0.1", "127.0.0.1/24", true},
	{48, "Compare only the first 96 bits where the first one does not match", "1:0:0:0:0:0:0:0001", "0:0:0:0:0:0:ffff:ffff/96", true},
	{49, "IPv4 in list", "127.0.0.1", "128.0.0.0/24,127.0.0.1", false},
	{50, "IPv6 in list", "::1", "1000:0000:0000:0000:0000:0000:FFFF:FFFF/96,::1", false},
	{51, "Any IPv4", "127.0.0.1", "128.0.0.0/0", false},
	{52, "Any IPv6", "::1", "1000:0000:0000:0000:0000:0000:FFFF:FFFF/0", false},
	{53, "Any IPv6 allows also any IPv4", "127.0.0.1", "1000:0000:0000:0000:0000:0000:FFFF:FFFF/0", false},
	{54, "IPv4 first CIDR value is not saved on next value in list", "128.0.0.1", "127.0.0.0/24,128.0.0.2", true},
	{55, "Long list of allowed peers and no match", "127.2.1.5",
		"localhost,127.0.0.2,127.0.0.0/24,0000:0000:0000:0000:0000:0000:127.0.0.1,::FFFF:127.0.0.1,0000:0000:0000:0000:0000:0000:0000:0003," +
			"::127.0.0.1,::127.0.0.1/128,::1", true},
	{56, "Long list of allowed peers and no match IPv6", "2001:0db8:0000:0000:0000:ff00:0042:8329",
		"localhost,127.0.0.2,127.0.0.0/24,0000:0000:0000:0000:0000:0000:127.0.0.1,::FFFF:127.0.0.1,0000:0000:0000:0000:0000:0000:0000:0003," +
			"::127.0.0.1,::127.0.0.1/128,::1", true},
	{57, "Long list of allowed peers but there is match", "127.2.1.5",
		"localhost,127.0.0.2,127.0.0.0/24,0000:0000:0000:0000:0000:0000:127.0.0.1,::FFFF:127.0.0.1,0000:0000:0000:0000:0000:0000:0000:0003," +
			"::127.0.0.1,::127.0.0.1/128,::1,::1/96,::,::/0,0.0.0.0/0,0000:0000:0000:0000:0000:0000:0000:0000/0", false},
	{58, "Long list of allowed peers but there is match IPv6", "2001:0db8:0000:0000:0000:ff00:0042:8329",
		"localhost,127.0.0.2,127.0.0.0/24,0000:0000:0000:0000:0000:0000:127.0.0.1,::FFFF:127.0.0.1,0000:0000:0000:0000:0000:0000:0000:0003," +
			"::127.0.0.1,::127.0.0.1/128,::1,::1/96,::,::/0,0.0.0.0/0,0000:0000:0000:0000:0000:0000:0000:0000/0", false},
	{59, "IPv6 unspecified address in list, connection from IPv6", "::1", "::", true},
	{60, "IPv6 unspecified address in list, connection from IPv4", "127.0.0.1", "::", true},
	{61, "DNS name as allowed address", "127.0.0.1", "localhost", false},
	{62, "DNS name as denied address", "127.0.0.2", "localhost", true},
	{63, "DNS name as denied IPv6 address", "::2", "localhost", true},
}

type testCase struct {
	id      uint
	name    string
	peer    string
	allowed string
	fail    bool
}

func TestAllowedPeers(t *testing.T) {
	for _, testCase := range testCases {
		if err := testCase.checkResult(); err != nil {
			t.Errorf("Test case \"%s\" (nr: %d), peer='%s', allowed_peers='%s' failed, error: %s)",
				testCase.name, testCase.id, testCase.peer, testCase.allowed, err.Error())
		}
	}
}

func (tc *testCase) checkResult() error {
	var options agent.AgentOptions

	options.Server = tc.allowed
	peerip := net.ParseIP(tc.peer)

	if ap, err := GetAllowedPeers(options.Server); err == nil {
		allow := ap.CheckPeer(peerip)

		if allow != true && tc.fail == false {
			return fmt.Errorf("peer is not allowed")
		} else if allow != false && tc.fail == true {
			return fmt.Errorf("peer is allowed while should not be")
		}
	}

	return nil
}

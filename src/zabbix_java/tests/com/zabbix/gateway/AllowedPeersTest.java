/*
** Copyright (C) 2001-2026 Zabbix SIA
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

package com.zabbix.gateway;

import java.net.InetAddress;

import org.junit.*;
import static org.junit.Assert.*;

public class AllowedPeersTest
{
	private static InetAddress ip(String s) throws Exception
	{
		return InetAddress.getByName(s);
	}

	@Test
	public void testExactAndCidrMatching() throws Exception
	{
		AllowedPeers ap = AllowedPeers.parse("127.0.0.1, 192.168.1.0/24 ,::1, 2001:db8::/32");

		assertTrue(ap.check(ip("127.0.0.1")));
		assertTrue(ap.check(ip("192.168.1.10")));
		assertTrue(ap.check(ip("192.168.1.255")));
		assertTrue(ap.check(ip("::1")));
		/* hextets must be hex (0-9a-f); keep it within 2001:db8::/32 */
		assertTrue(ap.check(ip("2001:db8:df11:b10c::1")));

		assertFalse(ap.check(ip("127.0.0.2")));
		assertFalse(ap.check(ip("192.168.2.1")));
		assertFalse(ap.check(ip("2001:db9::1")));
	}

	@Test
	public void testIPv4MappedEquivalence() throws Exception
	{
		// ::ffff:127.0.0.1 must match a plain 127.0.0.1 entry
		assertTrue(AllowedPeers.parse("127.0.0.1").check(ip("::ffff:127.0.0.1")));
	}

	@Test
	public void testIPv4MappedEquivalenceReverse() throws Exception
	{
		// a mapped IPv6 entry must also match a plain IPv4 peer
		assertTrue(AllowedPeers.parse("::ffff:127.0.0.1").check(ip("127.0.0.1")));
	}

	@Test
	public void testIPv4CompatibleEquivalence() throws Exception
	{
		// ::127.0.0.1 must be treated as equivalent to 127.0.0.1
		assertTrue(AllowedPeers.parse("127.0.0.1").check(ip("::127.0.0.1")));
		assertTrue(AllowedPeers.parse("::127.0.0.1").check(ip("127.0.0.1")));
		// and also match IPv4-mapped form
		assertTrue(AllowedPeers.parse("::127.0.0.1").check(ip("::ffff:127.0.0.1")));
		assertTrue(AllowedPeers.parse("::ffff:127.0.0.1").check(ip("::127.0.0.1")));
	}

	@Test
	public void testDnsHappyPathLocalhost() throws Exception
	{
		AllowedPeers ap = AllowedPeers.parse("localhost");
		// localhost should resolve to at least one loopback address
		assertTrue(ap.check(ip("127.0.0.1")) || ap.check(ip("::1")));
	}

	@Test
	public void testWildcards() throws Exception
	{
		assertTrue(AllowedPeers.parse("0.0.0.0/0").check(ip("8.8.8.8")));
		// '::/0' permits any IPv4 or IPv6 peer, matching the C trapper behaviour
		AllowedPeers any = AllowedPeers.parse("::/0");
		assertTrue(any.check(ip("::1")));
		assertTrue(any.check(ip("127.0.0.1")));
	}

	@Test
	public void testInvalidCidrPrefix() throws Exception
	{
		AllowedPeers ap = AllowedPeers.parse("192.168.1.0/64");
		assertFalse(ap.check(ip("192.168.1.1")));
	}

	@Test
	public void testInvalidCidrPrefixNotANumber() throws Exception
	{
		AllowedPeers ap = AllowedPeers.parse("192.168.1.0/abc");
		assertFalse(ap.check(ip("192.168.1.1")));
	}

	@Test
	public void testEmptyList() throws Exception
	{
		AllowedPeers ap = AllowedPeers.parse("");
		assertFalse(ap.check(ip("127.0.0.1")));
	}

	@Test(expected = IllegalArgumentException.class)
	public void testNullList()
	{
		AllowedPeers.parse(null);
	}

	@Test
	public void testUnresolvableHost() throws Exception
	{
		AllowedPeers ap = AllowedPeers.parse("this-host-should-not-resolve.invalid");
		assertFalse(ap.check(ip("127.0.0.1")));
	}

	@Test
	public void testDnsResolvedDoesNotMatchOtherPeer() throws Exception
	{
		// localhost resolves to loopback; an unrelated public IP must not match
		AllowedPeers ap = AllowedPeers.parse("localhost");

		assertFalse(ap.check(ip("8.8.8.8")));
	}

	@Test
	public void testMixedStaticAndDnsEntries() throws Exception
	{
		AllowedPeers ap = AllowedPeers.parse("192.168.1.0/24,localhost");
		// matches via static CIDR
		assertTrue(ap.check(ip("192.168.1.5")));
		// matches via DNS (localhost resolves to a loopback)
		assertTrue(ap.check(ip("127.0.0.1")) || ap.check(ip("::1")));
		// unrelated peer still rejected
		assertFalse(ap.check(ip("10.0.0.1")));
	}

	@Test
	public void testCidrOnHostnameIsIgnored() throws Exception
	{
		// CIDR is supported only for IP literals; hostname with /N must be ignored
		AllowedPeers ap = AllowedPeers.parse("localhost/24");

		assertFalse(ap.check(ip("127.0.0.1")));
	}
	
	@Test
	public void testPureIPv6EntryDoesNotMatchIPv4Peer() throws Exception
	{
		// A pure IPv6 entry (::1) must not match an unrelated IPv4 peer
		assertFalse(AllowedPeers.parse("::1").check(ip("1.2.3.4")));
		assertFalse(AllowedPeers.parse("::1").check(ip("127.0.0.1")));
	}
}

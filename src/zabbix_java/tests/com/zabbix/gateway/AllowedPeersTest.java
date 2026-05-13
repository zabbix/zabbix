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
import java.net.UnknownHostException;

import org.junit.Assert;
import org.junit.Test;

public class AllowedPeersTest
{
	private static InetAddress ip(String s) throws UnknownHostException
	{
		return InetAddress.getByName(s);
	}

	@Test
	public void testNullSpecThrows() throws Exception
	{
		try
		{
			AllowedPeers.parseForTest(null, h -> new InetAddress[0]);
			Assert.fail();
		}
		catch (IllegalArgumentException e)
		{
			Assert.assertTrue(e.getMessage().contains("null"));
		}
	}

	@Test
	public void testExactIpv4() throws Exception
	{
		AllowedPeers ap = AllowedPeers.parseForTest("127.0.0.1", h -> new InetAddress[0]);
		Assert.assertTrue(ap.check(ip("127.0.0.1")));
		Assert.assertFalse(ap.check(ip("10.0.0.1")));
	}

	@Test
	public void testExactIpv6() throws Exception
	{
		AllowedPeers ap = AllowedPeers.parseForTest("::1", h -> new InetAddress[0]);
		Assert.assertTrue(ap.check(ip("::1")));
	}

	@Test
	public void testCidrV4() throws Exception
	{
		AllowedPeers ap = AllowedPeers.parseForTest("10.0.0.0/24", h -> new InetAddress[0]);
		Assert.assertTrue(ap.check(ip("10.0.0.1")));
		Assert.assertFalse(ap.check(ip("10.1.0.1")));
	}

	@Test
	public void testInvalidCidrPrefixIgnored() throws Exception
	{
		AllowedPeers ap = AllowedPeers.parseForTest("127.0.0.1,192.168.1.0/64", h -> new InetAddress[0]);
		Assert.assertFalse(ap.isEmpty());
		Assert.assertTrue(ap.check(ip("127.0.0.1")));
	}

	@Test
	public void testInvalidCidrNanIgnored() throws Exception
	{
		AllowedPeers ap = AllowedPeers.parseForTest("127.0.0.1,192.168.1.0/abc", h -> new InetAddress[0]);
		Assert.assertTrue(ap.check(ip("127.0.0.1")));
	}

	@Test
	public void testUnresolvableHostnameIgnored() throws Exception
	{
		AllowedPeers ap = AllowedPeers.parseForTest("127.0.0.1,this-host-should-not-resolve.invalid",
				h -> { throw new UnknownHostException(h); });
		Assert.assertTrue(ap.check(ip("127.0.0.1")));
	}

	@Test
	public void testIpv4MappedEquivalence() throws Exception
	{
		AllowedPeers ap = AllowedPeers.parseForTest("127.0.0.1", h -> new InetAddress[0]);
		Assert.assertTrue(ap.check(ip("::ffff:127.0.0.1")));
	}

	@Test
	public void testIpv4CompatibleEquivalence() throws Exception
	{
		AllowedPeers ap = AllowedPeers.parseForTest("127.0.0.1", h -> new InetAddress[0]);
		Assert.assertTrue(ap.check(ip("::127.0.0.1")));
	}

	@Test
	public void testParseForTestMockResolver() throws Exception
	{
		AllowedPeers.HostResolver r = host -> {
			if ("example.local".equals(host))
				return new InetAddress[] { ip("10.10.10.10") };
			throw new UnknownHostException(host);
		};

		AllowedPeers ap = AllowedPeers.parseForTest("example.local", r);
		Assert.assertTrue(ap.check(ip("10.10.10.10")));
	}

	@Test
	public void testIpv4MappedReverseEquivalence() throws Exception
	{
		AllowedPeers ap = AllowedPeers.parseForTest("::ffff:127.0.0.1", h -> new InetAddress[0]);
		Assert.assertTrue(ap.check(ip("127.0.0.1")));
	}

	@Test
	public void testIpv4CompatibleReverseEquivalence() throws Exception
	{
		AllowedPeers ap = AllowedPeers.parseForTest("::127.0.0.1", h -> new InetAddress[0]);
		Assert.assertTrue(ap.check(ip("127.0.0.1")));
	}

	@Test
	public void testIpv6LoopbackDoesNotMatchIpv4Loopback() throws Exception
	{
		AllowedPeers ap = AllowedPeers.parseForTest("::1", h -> new InetAddress[0]);
		Assert.assertFalse(ap.check(ip("127.0.0.1")));
	}

	@Test
	public void testCidrV6() throws Exception
	{
		AllowedPeers ap = AllowedPeers.parseForTest("2001:db8::/32", h -> new InetAddress[0]);
		Assert.assertTrue(ap.check(ip("2001:db8::1")));
		Assert.assertFalse(ap.check(ip("2001:db9::1")));
	}

	@Test
	public void testIpv6WithLeadingHexLetter() throws Exception
	{
		AllowedPeers ap = AllowedPeers.parseForTest("fe80::1", h -> new InetAddress[0]);
		Assert.assertTrue(ap.check(ip("fe80::1")));
		Assert.assertFalse(ap.check(ip("fe80::2")));
	}

	@Test
	public void testAllIpv4Cidr() throws Exception
	{
		AllowedPeers ap = AllowedPeers.parseForTest("0.0.0.0/0", h -> new InetAddress[0]);
		Assert.assertTrue(ap.check(ip("8.8.8.8")));
	}

	@Test
	public void testAllDualCidr() throws Exception
	{
		AllowedPeers ap = AllowedPeers.parseForTest("::/0", h -> new InetAddress[0]);
		Assert.assertTrue(ap.check(ip("1.2.3.4")));
		Assert.assertTrue(ap.check(ip("2001:db8::1")));
	}
}

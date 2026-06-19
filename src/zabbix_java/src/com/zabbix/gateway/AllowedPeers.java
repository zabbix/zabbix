/*
** Zabbix
** Copyright (C) 2001-2026 Zabbix SIA
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

package com.zabbix.gateway;

import java.net.InetAddress;
import java.net.UnknownHostException;
import java.time.Duration;
import java.util.ArrayList;
import java.util.Collections;
import java.util.List;

import org.xbill.DNS.Cache;
import org.xbill.DNS.AAAARecord;
import org.xbill.DNS.ARecord;
import org.xbill.DNS.Lookup;
import org.xbill.DNS.Record;
import org.xbill.DNS.Resolver;
import org.xbill.DNS.SimpleResolver;
import org.xbill.DNS.TextParseException;
import org.xbill.DNS.Type;

import org.slf4j.Logger;
import org.slf4j.LoggerFactory;

/**
 * Allow-list of remote peers for Java Gateway.
 *
 * Accepts a comma separated list of IP addresses, CIDR ranges (e.g. 192.168.1.0/24, 2001:db8::/32) and DNS names.
 * IPv4-mapped (::ffff:x.x.x.x) and IPv4-compatible (::x.x.x.x) peers are matched against IPv4 entries and vice versa.
 */
class AllowedPeers
{
	private static final Logger logger = LoggerFactory.getLogger(AllowedPeers.class);

	interface HostResolver
	{
		InetAddress[] resolve(String host) throws UnknownHostException;
	}

	private static HostResolver createDnsJavaResolver(int timeoutSeconds)
	{
		final Resolver r;
		final Cache cacheA;
		final Cache cacheAAAA;

		try
		{
			r = new SimpleResolver();
			r.setTimeout(Duration.ofSeconds(timeoutSeconds));
			cacheA = new Cache();
			cacheAAAA = new Cache();
		}
		catch (Exception e)
		{
			logger.warn("failed to initialize DNS resolver: {}", e.getMessage());
			return new DnsJavaResolver(null, null, null);
		}

		return new DnsJavaResolver(r, cacheA, cacheAAAA);
	}

	private static final class DnsJavaResolver implements HostResolver
	{
		private final Resolver resolver;
		private final Cache cacheA;
		private final Cache cacheAAAA;

		DnsJavaResolver(Resolver resolver, Cache cacheA, Cache cacheAAAA)
		{
			this.resolver = resolver;
			this.cacheA = cacheA;
			this.cacheAAAA = cacheAAAA;
		}

		@Override
		public InetAddress[] resolve(String host) throws UnknownHostException
		{
			List<InetAddress> out = new ArrayList<InetAddress>(4);

			resolveDnsType(host, Type.A, cacheA, out);
			resolveDnsType(host, Type.AAAA, cacheAAAA, out);

			if (out.isEmpty())
				throw new UnknownHostException(host);

			return out.toArray(new InetAddress[out.size()]);
		}

		private void resolveDnsType(String host, int type, Cache cache, List<InetAddress> out) throws UnknownHostException
		{
			Lookup l;

			try
			{
				l = new Lookup(host, type);
			}
			catch (TextParseException e)
			{
				UnknownHostException uhe = new UnknownHostException(host);
				uhe.initCause(e);
				throw uhe;
			}

			if (null != resolver)
				l.setResolver(resolver);

			if (null != cache)
				l.setCache(cache);

			Record[] records = l.run();

			if (null == records)
				return;

			for (Record rec : records)
			{
				if (rec instanceof ARecord)
					out.add(((ARecord)rec).getAddress());
				else if (rec instanceof AAAARecord)
					out.add(((AAAARecord)rec).getAddress());
			}
		}
	}

	private final List<Entry> staticEntries = new ArrayList<Entry>();
	private final List<String> dnsHosts = new ArrayList<String>();
	private final HostResolver resolver;

	private AllowedPeers(HostResolver resolver)
	{
		this.resolver = resolver;
	}

	static AllowedPeers parse(String spec, int dnsResolveTimeoutSeconds)
	{
		return parseForTest(spec, createDnsJavaResolver(dnsResolveTimeoutSeconds));
	}

	static AllowedPeers parseForTest(String spec, HostResolver resolver)
	{
		if (null == spec)
			throw new IllegalArgumentException("allowed hosts list is null");

		AllowedPeers ap = new AllowedPeers(resolver);

		for (String raw : spec.split(","))
		{
			String item = raw.trim();

			if (item.isEmpty())
				continue;

			try
			{
				int slash = item.indexOf('/');

				if (-1 != slash)
				{
					String baseText = item.substring(0, slash);

					if (!looksLikeIpLiteral(baseText))
						throw new IllegalArgumentException("CIDR is supported only for IP literals");

					InetAddress base = InetAddress.getByName(baseText);
					int prefix = Integer.parseInt(item.substring(slash + 1));
					int max = base.getAddress().length * 8;

					if (prefix < 0 || prefix > max)
						throw new IllegalArgumentException("CIDR prefix out of range: " + prefix);

					ap.staticEntries.add(new Entry(base.getAddress(), prefix));
				}
				else
				{
					if (looksLikeIpLiteral(item))
					{
						InetAddress a = InetAddress.getByName(item);
						byte[] bytes = a.getAddress();
						ap.staticEntries.add(new Entry(bytes, bytes.length * 8));
					}
					else
						ap.dnsHosts.add(item);
				}
			}
			catch (UnknownHostException e)
			{
				logger.warn("ignoring SERVER entry '{}': cannot resolve", item);
			}
			catch (NumberFormatException e)
			{
				logger.warn("ignoring SERVER entry '{}': invalid CIDR prefix", item);
			}
			catch (IllegalArgumentException e)
			{
				logger.warn("ignoring SERVER entry '{}': {}", item, e.getMessage());
			}
		}

		return ap;
	}

	boolean isEmpty()
	{
		return staticEntries.isEmpty() && dnsHosts.isEmpty();
	}

	/** @return true if {@code peer} is permitted by the allow-list. */
	boolean check(InetAddress peer)
	{
		if (null == peer)
			return false;

		byte[] target = peer.getAddress();

		for (Entry e : staticEntries)
		{
			if (e.matches(target))
				return true;
		}

		for (String host : dnsHosts)
		{
			for (Entry e : getDnsEntries(host))
			{
				if (e.matches(target))
					return true;
			}
		}

		return false;
	}

	private List<Entry> getDnsEntries(String host)
	{
		try
		{
			InetAddress[] addrs = resolver.resolve(host);
			List<Entry> resolved = new ArrayList<Entry>(addrs.length);

			for (InetAddress a : addrs)
			{
				byte[] bytes = a.getAddress();
				resolved.add(new Entry(bytes, bytes.length * 8));
			}

			return Collections.unmodifiableList(resolved);
		}
		catch (Exception e)
		{
			return Collections.emptyList();
		}
	}

	private static boolean looksLikeIpLiteral(String s)
	{
		if (s.isEmpty())
			return false;

		if (-1 != s.indexOf(':'))
			return true;

		for (int i = 0; i < s.length(); i++)
		{
			char c = s.charAt(i);

			if ((c < '0' || c > '9') && c != '.')
				return false;
		}

		return -1 != s.indexOf('.');
	}

	private static final class Entry
	{
		private final byte[] network;
		private final int prefixBits;

		Entry(byte[] network, int prefixBits)
		{
			this.network = network;
			this.prefixBits = prefixBits;
		}

		boolean matches(byte[] target)
		{
			// Cross-family: align IPv4 and IPv4-in-IPv6 (mapped or compatible) against each other.
			if (target.length != network.length)
			{
				if (4 == target.length && 16 == network.length)
				{
					if (isIPv4Mapped(network))
						target = toIPv4Mapped(target);
					else if (isIPv4Compatible(network))
						target = toIPv4Compatible(target);
					else
						return false;
				}
				else if (16 == target.length && 4 == network.length && (isIPv4Mapped(target) || isIPv4Compatible(target)))
					target = new byte[]{target[12], target[13], target[14], target[15]};
				else
					return false;
			}

			int full = prefixBits / 8;
			int rem = prefixBits % 8;

			for (int i = 0; i < full; i++)
			{
				if (network[i] != target[i])
					return false;
			}

			if (0 == rem)
				return true;

			int mask = (0xff << (8 - rem)) & 0xff;

			return (network[full] & mask) == (target[full] & mask);
		}

		private static byte[] toIPv4Mapped(byte[] v4)
		{
			byte[] m = new byte[16];
			m[10] = (byte)0xff;
			m[11] = (byte)0xff;
			m[12] = v4[0];
			m[13] = v4[1];
			m[14] = v4[2];
			m[15] = v4[3];

			return m;
		}

		private static byte[] toIPv4Compatible(byte[] v4)
		{
			byte[] c = new byte[16];
			c[12] = v4[0];
			c[13] = v4[1];
			c[14] = v4[2];
			c[15] = v4[3];

			return c;
		}

		private static boolean isIPv4Mapped(byte[] v6)
		{
			for (int i = 0; i < 10; i++)
			{
				if (0 != v6[i])
					return false;
			}

			return (byte)0xff == v6[10] && (byte)0xff == v6[11];
		}

		private static boolean isIPv4Compatible(byte[] v6)
		{
			for (int i = 0; i < 12; i++)
			{
				if (0 != v6[i])
					return false;
			}

			return true;
		}
	}
}

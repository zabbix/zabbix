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
import java.util.ArrayList;
import java.util.List;

import org.slf4j.Logger;
import org.slf4j.LoggerFactory;

/**
 * Allow-list of remote peers for Java Gateway.
 *
 * Accepts a comma separated list of IP addresses, CIDR ranges (e.g. 192.168.1.0/24, 2001:db8::/32) and DNS names.
 * IPv4-mapped IPv6 peers are matched against IPv4 entries and vice versa.
 */
class AllowedPeers
{
	private static final Logger logger = LoggerFactory.getLogger(AllowedPeers.class);
	private final List<Entry> entries = new ArrayList<Entry>();

	private AllowedPeers()
	{
	}

	/**
	 * Parse allow-list behaviour:
	 * - invalid / unresolvable tokens are ignored (with a warning)
	 * - empty / fully invalid list results in an allow-list that matches nothing
	 *
	 * @throws IllegalArgumentException if {@code spec} is null.
	 */
	static AllowedPeers parse(String spec)
	{
		if (null == spec)
			throw new IllegalArgumentException("allowed hosts list is null");

		AllowedPeers ap = new AllowedPeers();

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

					ap.entries.add(new Entry(base.getAddress(), prefix));
				}
				else
				{
					for (InetAddress a : InetAddress.getAllByName(item))
					{
						byte[] bytes = a.getAddress();
						ap.entries.add(new Entry(bytes, bytes.length * 8));
					}
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
		return entries.isEmpty();
	}

	/** @return true if {@code peer} is permitted by the allow-list. */
	boolean check(InetAddress peer)
	{
		if (null == peer)
			return false;

		byte[] target = peer.getAddress();

		for (Entry e : entries)
		{
			if (e.matches(target))
				return true;
		}

		return false;
	}

	private static boolean looksLikeIpLiteral(String s)
	{
		if (s.isEmpty())
			return false;

		boolean hasColon = false;
		boolean hasDot = false;

		for (int i = 0; i < s.length(); i++)
		{
			char c = s.charAt(i);

			if (c >= '0' && c <= '9')
				continue;

			if (c == '.')
			{
				hasDot = true;
				continue;
			}

			if (c == ':')
			{
				hasColon = true;
				continue;
			}

			if (hasColon && ((c >= 'a' && c <= 'f') || (c >= 'A' && c <= 'F')))
				continue;

			return false;
		}

		return hasDot || hasColon;
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
			if (target.length != network.length)
			{
				if (4 == target.length && 16 == network.length)
				{
					if (isIPv4Mapped(network))
						target = toIPv4Mapped(target);
					else if (isIPv4Compatible(network))
						target = toIPv4Compatible(target);
					else
						target = toIPv4Mapped(target);
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

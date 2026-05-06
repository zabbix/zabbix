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

/**
 * Allow-list of remote peers for Java Gateway.
 *
 * Accepts a comma separated list of IP addresses, CIDR ranges (e.g. 192.168.1.0/24,
 * 2001:db8::/32) and DNS names (resolved at startup). IPv4-mapped IPv6 peers
 * (::ffff:a.b.c.d) are matched against IPv4 entries and vice versa, mirroring the
 * behaviour of the C trapper (e.g. '::/0' permits any IPv4 or IPv6 peer).
 */
class AllowedPeers
{
	private final List<Entry> entries = new ArrayList<Entry>();

	private AllowedPeers()
	{
	}

	/** @throws IllegalArgumentException if any list item is malformed or unresolvable. */
	static AllowedPeers parse(String spec)
	{
		if (null == spec)
			throw new IllegalArgumentException("allowed peers list is null");

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
					InetAddress base = InetAddress.getByName(item.substring(0, slash));
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
				throw new IllegalArgumentException("cannot resolve allowed peer '" + item + "'", e);
			}
			catch (NumberFormatException e)
			{
				throw new IllegalArgumentException("invalid CIDR prefix in '" + item + "'", e);
			}
		}

		if (ap.entries.isEmpty())
			throw new IllegalArgumentException("allowed peers list is empty");

		return ap;
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
			// Cross-family: align IPv4 and IPv4-mapped IPv6 against each other.
			if (target.length != network.length)
			{
				if (4 == target.length && 16 == network.length)
					target = toIPv4Mapped(target);
				else if (16 == target.length && 4 == network.length && isIPv4Mapped(target))
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

		private static boolean isIPv4Mapped(byte[] v6)
		{
			for (int i = 0; i < 10; i++)
			{
				if (0 != v6[i])
					return false;
			}

			return (byte)0xff == v6[10] && (byte)0xff == v6[11];
		}
	}
}

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

package com.zabbix.gateway;

class HelperFunctionChest
{
	static <T> boolean arrayContains(T[] array, T key)
	{
		for (T element : array)
		{
			if (key.equals(element))
				return true;
		}

		return false;
	}

	static int separatorIndex(String input)
	{
		for (int i = 0; i < input.length(); i++)
		{
			if ('\\' == input.charAt(i))
			{
				if (i + 1 < input.length() && ('\\' == input.charAt(i + 1) || '.' == input.charAt(i + 1)))
					i++;
			}
			else if ('.' == input.charAt(i))
			{
				return i;
			}
		}

		return -1;
	}

	static String unescapeUserInput(String input)
	{
		StringBuilder builder = new StringBuilder(input.length());

		for (int i = 0; i < input.length(); i++)
		{
			if ('\\' == input.charAt(i) && i + 1 < input.length() &&
					('\\' == input.charAt(i + 1) || '.' == input.charAt(i + 1)))
			{
				i++;
			}

			builder.append(input.charAt(i));
		}

		return builder.toString();
	}
}

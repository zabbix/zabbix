/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

package com.zabbix.gateway;

import java.util.ArrayList;

class HelperFunctionChest
{
	public static <T> boolean arrayContains(T[] array, T key)
	{
		for (T element : array)
			if (key.equals(element))
				return true;

		return false;
	}

	public static int separatorIndex(String input)
	{
		int substrPos, inputPos = 0;
		String substr = input;

		while (-1 != (substrPos = substr.indexOf('.')))
		{
			if ('\\' != substr.charAt(substrPos - 1))
				return inputPos + substrPos;

			substrPos++;
			substr = substr.substring(substrPos);

			inputPos += substrPos;
		}

		return -1;
	}

	public static String unescapeUserInput(String input)
	{
		byte[] inputByteArray = input.getBytes(), outputByteArray;
		ArrayList<Byte> outputByteList = new ArrayList<Byte>();
		int i;

		for (i = 0; i < inputByteArray.length; i++)
		{
			if (i + 1 < inputByteArray.length && '\\' == inputByteArray[i] &&
					('\\' == inputByteArray[i + 1] || '.' == inputByteArray[i + 1]))
			{
				i++;
			}

			outputByteList.add(inputByteArray[i]);
		}

		outputByteArray = new byte[outputByteList.size()];

		i = 0;
		for (Byte b : outputByteList)
		{
			outputByteArray[i] = b;
			i++;
		}

		return new String(outputByteArray);
	}
}

package com.zabbix.proxy;

import java.util.Vector;

class ZabbixItem
{
	private String key = null;
	private String keyId = null;
	private Vector<String> args = null;

	public ZabbixItem(String key)
	{
		if (null == key)
			throw new IllegalArgumentException("key must not be null");

		int bracket = key.indexOf('[');

		if (-1 != bracket)
		{
			if (']' != key.charAt(key.length() - 1))
				throw new IllegalArgumentException("no terminating ']' in key '" + key + "'");

			keyId = key.substring(0, bracket);
			args = parseArguments(key.substring(bracket + 1, key.length() - 1));
		}
		else
			keyId = key;

		if (0 == keyId.length())
			throw new IllegalArgumentException("key id is empty in key '" + key + "'");

		for (int i = 0; i < keyId.length(); i++)
			if (!isValidKeyIdChar(keyId.charAt(i)))
				throw new IllegalArgumentException("bad key id char '" + keyId.charAt(i) + "' in key '" + key + "'");

		this.key = key;
	}

	public String getKeyId()
	{
		return keyId;
	}

	public String getArgument(int index)
	{
		if (null == args || !(1 <= index && index <= args.size()))
			throw new IndexOutOfBoundsException("bad argument index '" + index + "' for key '" + key + "'");
		else
			return args.elementAt(index - 1);
	}

	public int getArgumentCount()
	{
		return null == args ? 0 : args.size();
	}

	private Vector<String> parseArguments(String keyArgs)
	{
		Vector<String> args = new Vector<String>();

		while (true)
		{
			if (0 == keyArgs.length())
			{
				args.add("");
				break;
			}
			else if (' ' == keyArgs.charAt(0))
			{
				keyArgs = keyArgs.substring(1);
			}
			else if ('"' == keyArgs.charAt(0))
			{
				int index = 1;

				while (index < keyArgs.length())
				{
					if ('"' == keyArgs.charAt(index) && '\\' != keyArgs.charAt(index - 1))
						break;
					else
						index++;
				}

				if (index == keyArgs.length())
					throw new IllegalArgumentException("quoted argument not terminated in '" + keyArgs + "'");

				args.add(keyArgs.substring(1, index).replace("\\\"", "\""));

				for (index++; index < keyArgs.length() && ' ' == keyArgs.charAt(index); index++)
					;

				if (index == keyArgs.length())
					break;

				if (',' != keyArgs.charAt(index))
					throw new IllegalArgumentException("quoted argument not followed by a comma in '" + keyArgs + "'");

				keyArgs = keyArgs.substring(index + 1);
			}
			else
			{
				int index = 0;

				while (index < keyArgs.length() && ',' != keyArgs.charAt(index))
					index++;

				args.add(keyArgs.substring(0, index));

				if (index == keyArgs.length())
					break;

				keyArgs = keyArgs.substring(index + 1);
			}
		}

		return args;
	}

	private boolean isValidKeyIdChar(char ch)
	{
		return (('a' <= ch && ch <= 'z') ||
				('A' <= ch && ch <= 'Z') ||
				('0' <= ch && ch <= '9') ||
				(-1 != "._-".indexOf(ch)));
	}
}

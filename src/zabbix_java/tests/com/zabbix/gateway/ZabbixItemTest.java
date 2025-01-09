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

import org.junit.*;
import static org.junit.Assert.*;

public class ZabbixItemTest
{
	@Test
	public void testCorrectParsing()
	{
		class CorrectParsingHelper
		{
			String key;
			String keyId;
			String[] args;

			CorrectParsingHelper(String key, String keyId, String[] args)
			{
				this.key = key;
				this.keyId = keyId;
				this.args = args;
			}
		}

		CorrectParsingHelper[] testCases = new CorrectParsingHelper[]
		{
			new CorrectParsingHelper(
					"name",
					"name", new String[] {}),
			new CorrectParsingHelper(
					"name[]",
					"name", new String[] {""}),
			new CorrectParsingHelper(
					"name[,]",
					"name", new String[] {"", ""}),
			new CorrectParsingHelper(
					"name[,\"\",]",
					"name", new String[] {"", "", ""}),
			new CorrectParsingHelper(
					"name[ ,\" \", ]",
					"name", new String[] {"", " ", ""}),
			new CorrectParsingHelper(
					"name[ ,  ,   ]",
					"name", new String[] {"", "", ""}),
			new CorrectParsingHelper(
					"name[arg1]",
					"name", new String[] {"arg1"}),
			new CorrectParsingHelper(
					"name[\"arg1\"]",
					"name", new String[] {"arg1"}),
			new CorrectParsingHelper(
					"name[arg1,arg2]",
					"name", new String[] {"arg1", "arg2"}),
			new CorrectParsingHelper(
					"name[arg1, arg2]",
					"name", new String[] {"arg1", "arg2"}),
			new CorrectParsingHelper(
					"name[\"arg1\",\"arg2\"]",
					"name", new String[] {"arg1", "arg2"}),
			new CorrectParsingHelper(
					"name[\"arg1\", \"arg2\"]",
					"name", new String[] {"arg1", "arg2"}),
			new CorrectParsingHelper(
					"name[ arg1 ,  arg2  ]",
					"name", new String[] {"arg1 ", "arg2  "}),
			new CorrectParsingHelper(
					"name[ \"arg1\" ,  \"arg2\"  ,   \" arg3 \"   ]",
					"name", new String[] {"arg1", "arg2", " arg3 "}),
			new CorrectParsingHelper(
					"name.with.period.and.digits.123.too",
					"name.with.period.and.digits.123.too", new String[] {}),
			new CorrectParsingHelper(
					"name.with.symbols._.and.-.[\"arg1\",arg2]",
					"name.with.symbols._.and.-.", new String[] {"arg1", "arg2"}),
			new CorrectParsingHelper(
					"12345",
					"12345", new String[] {}),
			new CorrectParsingHelper(
					"12345[name,with,digits,only]",
					"12345", new String[] {"name", "with", "digits", "only"}),
			new CorrectParsingHelper(
					"name[quotes around the \"argument\" word]",
					"name", new String[] {"quotes around the \"argument\" word"}),
			new CorrectParsingHelper(
					"name[utf-astoņi, \"утф-восемь\"]",
					"name", new String[] {"utf-astoņi", "утф-восемь"}),
			new CorrectParsingHelper(
					"name[ utf-astoņi ar pēdiņām un zvaigznīti: \"*\", \"утф-восемь с кавычками и запятой: \\\",\\\" и \\\",\\\"\" ]",
					"name", new String[] {"utf-astoņi ar pēdiņām un zvaigznīti: \"*\"", "утф-восемь с кавычками и запятой: \",\" и \",\""}),
			new CorrectParsingHelper(
					"name[ interesanti simboli: `~!@#$%^&*()-_=+|:;<>., \"интересные символы: `~!@#$%^&*()-_=+[]|:;<>.,\" ]",
					"name", new String[] {"interesanti simboli: `~!@#$%^&*()-_=+|:;<>.", "интересные символы: `~!@#$%^&*()-_=+[]|:;<>.,"}),
			new CorrectParsingHelper(
					"name[ backslashes are treated literally outside of double quotes, like so: \\\"text\\\"]",
					"name", new String[] {"backslashes are treated literally outside of double quotes", "like so: \\\"text\\\""}),
			new CorrectParsingHelper(
					"name[ only \\d\\o\\u\\b\\l\\e \\q\\u\\o\\t\\e\\s should be escaped, \"like so: \\\"\\t\\e\\x\\t\\\"\"]",
					"name", new String[] {"only \\d\\o\\u\\b\\l\\e \\q\\u\\o\\t\\e\\s should be escaped", "like so: \"\\t\\e\\x\\t\""}),
			new CorrectParsingHelper(
					"name[ 'single quotes' remain,'single quotes' ]",
					"name", new String[] {"'single quotes' remain", "'single quotes' "}),
			new CorrectParsingHelper(
					"jmx[java.lang:type=Memory,HeapMemoryUsage]",
					"jmx", new String[] {"java.lang:type=Memory", "HeapMemoryUsage"}),
			new CorrectParsingHelper(
					"jmx[\"java.lang:type=Memory\",\"HeapMemoryUsage\"]",
					"jmx", new String[] {"java.lang:type=Memory", "HeapMemoryUsage"}),
			new CorrectParsingHelper(
					"arrays.are.not.supported.yet[[arg1]",
					"arrays.are.not.supported.yet", new String[] {"[arg1"}),
			new CorrectParsingHelper(
					"arrays.are.not.supported.yet[a[rg1]",
					"arrays.are.not.supported.yet", new String[] {"a[rg1"}),
			new CorrectParsingHelper(
					"arrays.are.not.supported.yet[arg]1]",
					"arrays.are.not.supported.yet", new String[] {"arg]1"}),
			new CorrectParsingHelper(
					"arrays.are.not.supported.yet[arg1]]",
					"arrays.are.not.supported.yet", new String[] {"arg1]"}),
		};

		for (int i = 0; i < testCases.length; i++)
		{
			ZabbixItem item = new ZabbixItem(testCases[i].key);

			assertEquals("bad key back for key '" + testCases[i].key + "'", testCases[i].key, item.getKey());
			assertEquals("bad key id for key '" + testCases[i].key + "'", testCases[i].keyId, item.getKeyId());
			assertEquals("bad number of arguments for key '" + testCases[i].key + "'", testCases[i].args.length, item.getArgumentCount());

			for (int j = 1; j <= testCases[i].args.length; j++)
				assertEquals("bad argument '" + j + "' for key '" + testCases[i].key + "'", testCases[i].args[j - 1], item.getArgument(j));
		}
	}

	@Test
	public void testInvalidKeys()
	{
		String[] keys = new String[]
		{
			null,
			"",
			"!",
			"a^name",
			"a^name[arg1]",
			"[arg1]",
			" name",
			"name ",
			" name_arg1]",
			" name[arg1]",
			"name[arg1] ",
			"name[arg1",
			"name[arg1,arg2",
			"name[arg1]trash",
			"name[arg1,\"arg2\"trash]",
			"name[arg1,\"arg2\"]trash",
			"vārds",
			"vārds[utf-8]",
			"имя",
			"имя[utf-8]",
			"name[\"arg1",
			"name[\"arg1]",
			"name,22",
			"name(arg1)",
			"host:key[arg1]"
		};

		for (int i = 0; i < keys.length; i++)
		{
			Exception thrown = null;

			try
			{
				new ZabbixItem(keys[i]);
			}
			catch (Exception caught)
			{
				thrown = caught;
			}

			if (null == thrown)
				fail("exception not thrown for key '" + keys[i] + "'");
			else if (!(thrown instanceof IllegalArgumentException))
				fail("exception of an improper type thrown for key '" + keys[i] + "'");
		}
	}

	@Test
	public void testInvalidArgumentIndex()
	{
		String[] keys = new String[]
		{
			"jmx",
			"jmx[1]",
			"jmx[arg1,\"arg2\",arg3]"
		};

		for (int i = 0; i < keys.length; i++)
		{
			ZabbixItem item = new ZabbixItem(keys[i]);

			for (int j = 0; j < 2; j++)
			{
				Exception thrown = null;
				int index = (0 == j ? 0 : item.getArgumentCount() + 1);

				try
				{
					String arg = item.getArgument(index);
				}
				catch (Exception caught)
				{
					thrown = caught;
				}

				if (null == thrown)
					fail("exception not thrown for argument '" + index + "' of key '" + keys[i] + "'");
				else if (!(thrown instanceof IndexOutOfBoundsException))
					fail("exception of an improper type thrown for argument '" + index + "' of key '" + keys[i] + "'");
			}
		}
	}
}

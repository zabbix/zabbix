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

import java.io.File;
import java.io.IOException;
import java.io.FileInputStream;

import org.slf4j.Logger;
import org.slf4j.LoggerFactory;
import java.util.Properties;

class ConfigurationManager
{
	private static final Logger logger = LoggerFactory.getLogger(ConfigurationManager.class);

	static final String PID_FILE = "pidFile"; // has to be parsed first so that we remove the file if other parameters are bad
	static final String LISTEN_IP = "listenIP";
	static final String LISTEN_PORT = "listenPort";
	static final String START_POLLERS = "startPollers";
	static final String TIMEOUT = "timeout";
	static final String PROPERTIES_FILE = "propertiesFile";

	private static ConfigurationParameter[] parameters =
	{
		new ConfigurationParameter(PID_FILE, ConfigurationParameter.TYPE_FILE, null,
				null,
				new PostInputValidator()
				{
					@Override
					public void execute(Object value)
					{
						logger.debug("received {} configuration parameter, daemonizing", PID_FILE);

						File pidFile = (File)value;

						pidFile.deleteOnExit();

						try
						{
							System.in.close();
							System.out.close();
							System.err.close();
						}
						catch (IOException e)
						{
							throw new RuntimeException(e);
						}
					}
				}),
		new ConfigurationParameter(LISTEN_IP, ConfigurationParameter.TYPE_INETADDRESS, null,
				null,
				null),
		new ConfigurationParameter(LISTEN_PORT, ConfigurationParameter.TYPE_INTEGER, 10052,
				new IntegerValidator(1024, 65535),
				null),
		new ConfigurationParameter(START_POLLERS, ConfigurationParameter.TYPE_INTEGER, 5,
				new IntegerValidator(1, 1000),
				null),
		new ConfigurationParameter(TIMEOUT, ConfigurationParameter.TYPE_INTEGER, 3,
				new IntegerValidator(1, 30),
				null),
		new ConfigurationParameter(PROPERTIES_FILE, ConfigurationParameter.TYPE_FILE, null,
				null,
				new PostInputValidator()
				{
					@Override
					public void execute(Object value)
					{
						FileInputStream inStream = null;

						try
						{
							Properties props;

							inStream = new FileInputStream((File)value);
							props = new Properties(System.getProperties());

							props.load(inStream);

							System.setProperties(props);
						}
						catch (IOException e)
						{
							throw new RuntimeException(e);
						}
						catch (SecurityException e)
						{
							throw new RuntimeException(e);
						}
						finally
						{
							try { if (null != inStream) inStream.close(); } catch (Exception e) { }
						}
					}
				}),
	};

	static void parseConfiguration()
	{
		logger.debug("starting to parse configuration parameters");

		for (ConfigurationParameter parameter : parameters)
		{
			String property = System.getProperty("zabbix." + parameter.getName());

			if (null != property)
			{
				logger.debug("found {} configuration parameter with value '{}'", parameter.getName(), property);
				parameter.setValue(property);
			}
		}

		logger.debug("finished parsing configuration parameters");
	}

	static ConfigurationParameter getParameter(String name)
	{
		for (ConfigurationParameter parameter : parameters)
			if (parameter.getName().equals(name))
				return parameter;

		throw new IllegalArgumentException("unknown configuration parameter: '" + name + "'");
	}

	static int getIntegerParameterValue(String name)
	{
		return (Integer)getParameter(name).getValue();
	}

	static String getPackage()
	{
		return "com.zabbix.gateway";
	}
}

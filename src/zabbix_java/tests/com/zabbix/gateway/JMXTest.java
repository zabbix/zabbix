/*
 ** Zabbix
 ** Copyright (C) 2001-2022 Zabbix SIA
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

import org.junit.*;
import static org.junit.Assert.*;

import java.io.File;
import java.io.IOException;
import java.util.HashMap;
import javax.management.MBeanServerConnection;
import javax.management.ObjectName;
import javax.management.remote.JMXConnector;
import javax.management.remote.JMXServiceURL;
import javax.rmi.ssl.SslRMIClientSocketFactory;

public class JMXTest {

	final static String SECRET_MESSAGE = "secret badger message";
	final static String CORRECT_PASSWORD = "useruser";
	final static String INCORRECT_PASSWORD = "useruser2";
	final static String KEY_STORE_PASSWORD = "kuseruser";
	final static String TRUST_STORE_PASSWORD = "tuseruser";
	final static String PORT = "1617";
	final static String HOST = "127.0.0.1";
	final static String JMX_USER = "badger";
	final static String JMX_APP_URL = "service:jmx:rmi:///jndi/rmi://" + HOST + ":" + PORT + "/jmxrmi";
	final static String APP_LOG = "/tmp/ZABBIX_JMX_HELLO_APP.log";
	final static String JMX_TESTS_PATH = System.getProperty("user.dir") +
			"/tests/com/zabbix/gateway/jmx_test_beans";
	final static String CERT1_PATH = JMX_TESTS_PATH + "/k_tools/cert1/";
	final static String CERT2_PATH = JMX_TESTS_PATH + "/k_tools/cert2/";
	final static String VALID_TARGET_APP_KEYSTORE_FILE = CERT1_PATH + "SimpleAgent.keystore";
	final static String VALID_MONITOR_APP_TRUSTSTORE_FILE = CERT1_PATH + "junit_test.truststore";
	final static String VALID_TARGET_APP_TRUSTSTORE_FILE = CERT1_PATH + "SimpleAgent.truststore";
	final static String INVALID_TARGET_APP_KEYSTORE_FILE = CERT2_PATH + "SimpleAgent.keystore";
	final static String INVALID_TARGET_APP_TRUSTSTORE_FILE = CERT2_PATH + "SimpleAgent.truststore";
	final static String JMXREMOTE_ACCESS_FILE = "jmxremote.access";
	final static String JMXREMOTE_PASSWORD_FILE = "jmxremote.password";

	final static int TIME_FOR_APP_TO_START = 1000;

	static
	{
		System.setProperty("javax.net.ssl.keyStore", VALID_TARGET_APP_KEYSTORE_FILE);
		System.setProperty("javax.net.ssl.keyStorePassword", KEY_STORE_PASSWORD);
		System.setProperty("javax.net.ssl.trustStore", VALID_MONITOR_APP_TRUSTSTORE_FILE);
		System.setProperty("javax.net.ssl.trustStorePassword", TRUST_STORE_PASSWORD);
		System.setProperty("com.sun.net.ssl.checkRevocation", "true");
	}

	static HashMap<String, Boolean> useRMISSLforURLHintCache = new HashMap<String, Boolean>();

	static void jmxTestScenario(boolean useAuth,
			boolean useIncorrectPassForMonitor,
			boolean targetAppUsesRegistrySSL,
			boolean useTrustStoreWithoutTargetKey)
	{

		Process p = null;

		try
		{
			ProcessBuilder pb = new ProcessBuilder(
					"java",
					"-Dcom.sun.management.jmxremote=true",
					"-Dcom.sun.management.jmxremote.port=" + PORT,
					"-Djavax.net.ssl.keyStore=" + (useTrustStoreWithoutTargetKey ?
						INVALID_TARGET_APP_KEYSTORE_FILE : VALID_TARGET_APP_KEYSTORE_FILE),
					"-Djavax.net.ssl.keyStorePassword=" + KEY_STORE_PASSWORD,
					"-Djavax.net.ssl.trustStore=" + (useTrustStoreWithoutTargetKey ?
						INVALID_TARGET_APP_TRUSTSTORE_FILE : VALID_TARGET_APP_TRUSTSTORE_FILE),
					"-Djavax.net.ssl.trustStorePassword=" + TRUST_STORE_PASSWORD,
					"-Dcom.sun.management.jmxremote.authenticate=" + String.valueOf(useAuth),
					"-Dcom.sun.management.jmxremote.access.file=" + JMXREMOTE_ACCESS_FILE,
					"-Dcom.sun.management.jmxremote.password.file=" + JMXREMOTE_PASSWORD_FILE,
					"-Dcom.sun.management.jmxremote.ssl=true",
					"-Dcom.sun.management.jmxremote.registry.ssl=" +
							String.valueOf(targetAppUsesRegistrySSL),
					"SimpleAgent");

			pb.directory(new File(JMX_TESTS_PATH));
			pb.redirectErrorStream(true);
			pb.redirectOutput(ProcessBuilder.Redirect.appendTo(new File(APP_LOG)));
			p = pb.start();
			Thread.sleep(TIME_FOR_APP_TO_START);

			HashMap<String, Object> env = new HashMap<String, Object>();

			if (useAuth)
			{
				env.put(JMXConnector.CREDENTIALS, new String[]
				{
					JMX_USER, useIncorrectPassForMonitor ? INCORRECT_PASSWORD : CORRECT_PASSWORD
				});
			}

			JMXConnector jmxc = null;

			if (!useRMISSLforURLHintCache.containsKey(JMX_APP_URL) ||
					!useRMISSLforURLHintCache.get(JMX_APP_URL))
			{
				try
				{
					jmxc = ZabbixJMXConnectorFactory.connect(new JMXServiceURL(JMX_APP_URL), env);
					useRMISSLforURLHintCache.put(JMX_APP_URL, false);
				}
				catch (IOException e)
				{
					env.put("com.sun.jndi.rmi.factory.socket", new SslRMIClientSocketFactory());
					jmxc = ZabbixJMXConnectorFactory.connect(new JMXServiceURL(JMX_APP_URL), env);
					useRMISSLforURLHintCache.put(JMX_APP_URL, true);
				}
			}
			else
			{
				try
				{
					env.put("com.sun.jndi.rmi.factory.socket", new SslRMIClientSocketFactory());
					jmxc = ZabbixJMXConnectorFactory.connect(new JMXServiceURL(JMX_APP_URL), env);
					useRMISSLforURLHintCache.put(JMX_APP_URL, true);
				}
				catch (IOException e)
				{
					env.remove("com.sun.jndi.rmi.factory.socket");
					jmxc = ZabbixJMXConnectorFactory.connect(new JMXServiceURL(JMX_APP_URL), env);
					useRMISSLforURLHintCache.put(JMX_APP_URL, false);
				}
			}

			MBeanServerConnection mbsc = jmxc.getMBeanServerConnection();

			ObjectName objectName = new ObjectName("FOO:name=HelloBean");
			Object dataObject = mbsc.getAttribute(objectName, "Message");

			if (useAuth && useIncorrectPassForMonitor)
			{
				fail("No authentication error, but it is expected");
			}

			if (useTrustStoreWithoutTargetKey)
			{
				fail("Incorrect certificate used, error expected");
			}

			assertEquals(SECRET_MESSAGE, dataObject.toString());

		}
		catch (java.lang.SecurityException e)
		{
			if (!useIncorrectPassForMonitor || !e.getMessage().equals(
					"Authentication failed! Invalid username or password"))
			{
					fail("Test unexpectedly failed: " + e.getMessage());
			}
		}
		catch (Exception e)
		{
			if (!(useTrustStoreWithoutTargetKey && e.getMessage().startsWith(
					"Failed to retrieve RMIServer stub:")))
			{
				fail("Test unexpectedly failed: " + e.getMessage());
			}
		}
		finally
		{
			if (p != null)
			{
				p.destroyForcibly();
			}
		}
	}

	@Test
	public void basic()
	{
		jmxTestScenario(false, false, false, false);
	}

	@Test
	public void useAuth()
	{
		jmxTestScenario(true, false, false, false);
	}

	@Test
	public void incorrectAuthPass()
	{
		jmxTestScenario(true, true, false, false);
	}

	@Test
	public void useSSL()
	{
		jmxTestScenario(false, false, true, false);
	}

	@Test
	public void useSSLWithAuth()
	{
		jmxTestScenario(true, false, true, false);
	}

	@Test
	public void useSSLIncorrectAuthPass()
	{
		jmxTestScenario(true, true, true, false);
	}

	@Test
	public void monitorUsesTruststoreWithoutValidCertificate()
	{
		jmxTestScenario(true, false, true, true);
	}

	@Test
	public void flickeringRMISSL()
	{
		jmxTestScenario(true, false, false, false);
		jmxTestScenario(true, false, true, false);
		jmxTestScenario(true, false, false, false);
		jmxTestScenario(true, false, true, false);
		jmxTestScenario(true, false, true, false);
		jmxTestScenario(true, false, false, false);
	}

}

/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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

import java.io.FileInputStream;
import java.io.IOException;
import java.io.InputStream;
import java.io.InterruptedIOException;
import java.net.SocketTimeoutException;
import java.security.KeyStore;
import java.util.HashMap;
import java.util.concurrent.ArrayBlockingQueue;
import java.util.concurrent.BlockingQueue;
import java.util.concurrent.Executors;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.ThreadFactory;
import java.util.concurrent.TimeUnit;

import javax.management.remote.JMXConnector;
import javax.management.remote.JMXConnectorFactory;
import javax.management.remote.JMXServiceURL;

import java.util.*;
import javax.net.ssl.KeyManagerFactory;
import javax.rmi.ssl.SslRMIClientSocketFactory;

import javax.net.ssl.SSLContext;
import javax.net.ssl.TrustManager;
import javax.net.ssl.X509TrustManager;
import javax.rmi.ssl.SslRMIClientSocketFactory;
import java.security.cert.X509Certificate;

import org.slf4j.Logger;
import org.slf4j.LoggerFactory;

import static javax.net.ssl.SSLContext.getInstance;
import static javax.net.ssl.SSLContext.setDefault;

class ZabbixJMXConnectorFactory
{
	private static final Logger logger = LoggerFactory.getLogger(ZabbixJMXConnectorFactory.class);

	static
	{
		SSLContext sc = null;
		KeyStore keystore = null;
		KeyManagerFactory keyManagerFactory = null;

		System.setProperty("com.sun.net.ssl.checkRevocation", "true");

		try
		{
			keystore = KeyStore.getInstance(KeyStore.getDefaultType());

			InputStream in = new FileInputStream(System.getProperty("javax.net.ssl.keyStore"));
			String keyStorePassword = System.getProperty("javax.net.ssl.keyStorePassword");
			keystore.load(in, keyStorePassword == null ? null : keyStorePassword.toCharArray());
			keyManagerFactory = KeyManagerFactory.getInstance(KeyManagerFactory.getDefaultAlgorithm());
			keyManagerFactory.init(keystore, keyStorePassword == null ? null : keyStorePassword.toCharArray());
			sc = getInstance("SSL");
			sc.init(keyManagerFactory.getKeyManagers(), null, new java.security.SecureRandom());
			sc.getSocketFactory();
			setDefault(sc);
		}
		catch (Exception e)
		{
			e.printStackTrace();
			System.exit(1);
		}
	}

	private static final ExecutorService executor = Executors.newCachedThreadPool(new DaemonThreadFactory());

	private static class DaemonThreadFactory implements ThreadFactory
	{
		private ThreadFactory f = Executors.defaultThreadFactory();

		@Override
		public Thread newThread(Runnable r)
		{
			Thread t = f.newThread(r);

			t.setDaemon(true);

			return t;
		}
	}

	static JMXConnector connect(final JMXServiceURL url, final HashMap<String, Object> env) throws IOException
	{
		logger.debug("connecting to JMX agent at '{}'", url);

		final BlockingQueue<Object> queue = new ArrayBlockingQueue<Object>(1);

		Runnable task = new Runnable()
		{
			@Override
			public void run()
			{
				try
				{
					logger.trace("making a call to JMXConnectorFactory.connect('{}')", url);

					JMXConnector jmxc = JMXConnectorFactory.connect(url, env);

					logger.trace("call to JMXConnectorFactory.connect('{}') successful", url);

					if (!queue.offer(jmxc))
						jmxc.close();
				}
				catch (Throwable t)
				{
					logger.trace("call to JMXConnectorFactory.connect('{}') timed out", url);

					queue.offer(t);
				}
			}
		};

		executor.submit(task);

		Object result;

		try
		{
			int timeout = ConfigurationManager.getIntegerParameterValue(ConfigurationManager.TIMEOUT);

			result = queue.poll(timeout, TimeUnit.SECONDS);

			if (null == result)
			{
				if (!queue.offer(""))
					result = queue.take();
			}

			if (null == result)
				logger.trace("no connector after {} seconds", timeout);
			else
				logger.trace("connector acquired");
		}
		catch (SecurityException e)
		{
			throw e;
		}
		catch (InterruptedException e)
		{
			InterruptedIOException e2 = new InterruptedIOException(e.getMessage());

			e2.initCause(e);

			throw e2;
		}

		if (null == result)
			throw new SocketTimeoutException("Connection timed out");

		if (result instanceof JMXConnector)
			return (JMXConnector)result;

		try
		{
			throw (Throwable)result;
		}
		catch (IOException e)
		{
			throw e;
		}
		catch (RuntimeException e)
		{
			throw e;
		}
		catch (Error e)
		{
			throw e;
		}
		catch (Throwable e)
		{
			throw new IOException(e.toString(), e);
		}
	}
}

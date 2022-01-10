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

import java.net.Socket;
import java.util.Map;
import java.util.Iterator;

import org.json.*;

import org.slf4j.Logger;
import org.slf4j.LoggerFactory;

class SocketProcessor implements Runnable
{
	private static final Logger logger = LoggerFactory.getLogger(SocketProcessor.class);

	private static long cleanupTime = System.currentTimeMillis();
	private Socket socket;

	public static final long MILLISECONDS_IN_HOUR = 1000 * 60 * 60;

	SocketProcessor(Socket socket)
	{
		this.socket = socket;
	}

	@Override
	public void run()
	{
		logger.debug("starting to process incoming connection");

		BinaryProtocolSpeaker speaker = null;
		ItemChecker checker = null;

		try
		{
			speaker = new BinaryProtocolSpeaker(socket);

			JSONObject request = new JSONObject(speaker.getRequest());

			if (request.getString(ItemChecker.JSON_TAG_REQUEST).equals(ItemChecker.JSON_REQUEST_INTERNAL))
			{
				checker = new InternalItemChecker(request);
			}
			else if (request.getString(ItemChecker.JSON_TAG_REQUEST).equals(ItemChecker.JSON_REQUEST_JMX))
			{
				checker = new JMXItemChecker(request);

				long now = System.currentTimeMillis();

				if (now >= cleanupTime)
				{
					cleanDiscoveredObjects(now);
					cleanupTime = now + MILLISECONDS_IN_HOUR;
				}

				cleanRMISSLhintCache((JMXItemChecker)checker);
			}
			else
				throw new ZabbixException("bad request tag value: '%s'", request.getString(ItemChecker.JSON_TAG_REQUEST));

			logger.debug("dispatched request to class {}", checker.getClass().getName());
			JSONArray values = checker.getValues();

			JSONObject response = new JSONObject();
			response.put(ItemChecker.JSON_TAG_RESPONSE, ItemChecker.JSON_RESPONSE_SUCCESS);
			response.put(ItemChecker.JSON_TAG_DATA, values);

			speaker.sendResponse(response.toString());
		}
		catch (Exception e1)
		{
			String error = ZabbixException.getRootCauseMessage(e1);

			// Display first item key to identify items with incorrect configuration, all items in batch have same configuration.
			if (null == checker || null == checker.getFirstKey())
				logger.warn("error processing request: {}", error);
			else
				logger.warn("error processing request, item \"{}\" failed: {}", checker.getFirstKey(), error);

			logger.debug("error caused by", e1);

			try
			{
				JSONObject response = new JSONObject();
				response.put(ItemChecker.JSON_TAG_RESPONSE, ItemChecker.JSON_RESPONSE_FAILED);
				response.put(ItemChecker.JSON_TAG_ERROR, error);

				speaker.sendResponse(response.toString());
			}
			catch (Exception e2)
			{
				logger.warn("error sending failure notification: {}", ZabbixException.getRootCauseMessage(e1));
				logger.debug("error caused by", e2);
			}
		}
		finally
		{
			try { if (null != speaker) speaker.close(); } catch (Exception e) { }
			try { if (null != socket) socket.close(); } catch (Exception e) { }
		}

		logger.debug("finished processing incoming connection");
	}

	private void cleanDiscoveredObjects(long now)
	{
		for (Iterator<Map.Entry<String, Long>> it = JavaGateway.iterativeObjects.entrySet().iterator();
			it.hasNext(); )
		{
			Map.Entry<String, Long> entry = it.next();
			long expirationTime = entry.getValue();

			if (now >= expirationTime)
				it.remove();
		}
	}

	public static final long MILLISECONDS_IN_24HOURS = MILLISECONDS_IN_HOUR * 24;
	private static long RMISSLhintCacheCleanupTime = System.currentTimeMillis() + MILLISECONDS_IN_24HOURS;

	public void cleanRMISSLhintCache(JMXItemChecker checker)
	{
		long now = System.currentTimeMillis();

		logger.debug("RMI SSL hint cache cleanup is scheduled on " + RMISSLhintCacheCleanupTime +
				", now is: " + now);

		if (now >= RMISSLhintCacheCleanupTime)
		{
			checker.cleanUseRMISSLforURLHintCache();
			RMISSLhintCacheCleanupTime = now + MILLISECONDS_IN_24HOURS;
		}
	}
}

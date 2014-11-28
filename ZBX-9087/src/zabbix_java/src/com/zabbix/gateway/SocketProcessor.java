/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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
import java.util.Formatter;

import org.json.*;

import org.slf4j.Logger;
import org.slf4j.LoggerFactory;

class SocketProcessor implements Runnable
{
	private static final Logger logger = LoggerFactory.getLogger(SocketProcessor.class);

	private Socket socket;

	public SocketProcessor(Socket socket)
	{
		this.socket = socket;
	}

	public void run()
	{
		logger.debug("starting to process incoming connection");

		BinaryProtocolSpeaker speaker = null;

		try
		{
			speaker = new BinaryProtocolSpeaker(socket);

			JSONObject request = new JSONObject(speaker.getRequest());

			ItemChecker checker;

			if (request.getString(ItemChecker.JSON_TAG_REQUEST).equals(ItemChecker.JSON_REQUEST_INTERNAL))
				checker = new InternalItemChecker(request);
			else if (request.getString(ItemChecker.JSON_TAG_REQUEST).equals(ItemChecker.JSON_REQUEST_JMX))
				checker = new JMXItemChecker(request);
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
			logger.warn("error processing request", e1);

			try
			{
				String response = new Formatter().format("{\"%s\":\"%s\",\"%s\":%s}\n",
						ItemChecker.JSON_TAG_RESPONSE, ItemChecker.JSON_RESPONSE_FAILED,
						ItemChecker.JSON_TAG_ERROR, JSONObject.quote(e1.getMessage())).toString();

				speaker.sendResponse(response);
			}
			catch (Exception e2)
			{
				logger.warn("error sending failure notification", e2);
			}
		}
		finally
		{
			try { if (null != speaker) speaker.close(); } catch (Exception e) { }
			try { if (null != socket) socket.close(); } catch (Exception e) { }
		}

		logger.debug("finished processing incoming connection");
	}
}

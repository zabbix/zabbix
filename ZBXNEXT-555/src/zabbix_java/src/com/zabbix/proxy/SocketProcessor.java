package com.zabbix.proxy;

import java.io.*;
import java.net.Socket;
import java.nio.ByteBuffer;
import java.nio.ByteOrder;
import java.util.Vector;
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

		PrintWriter out = null;
		DataInputStream in = null;

		try
		{
			out = new PrintWriter(socket.getOutputStream(), true);
			in = new DataInputStream(socket.getInputStream());

			byte[] data;

			logger.debug("reading Zabbix protocol header");
			data = new byte[13];
			in.readFully(data);

			if (!('Z' == data[0] && 'B' == data[1] && 'X' == data[2] && 'D' == data[3] && '\1' == data[4]))
				throw new ZabbixException("bad TCP header: %02X %02X %02X %02X %02X", data[0], data[1], data[2], data[3], data[4]);

			ByteBuffer buffer = ByteBuffer.wrap(data, 5, 8);
			buffer.order(ByteOrder.LITTLE_ENDIAN);
			long length = buffer.getLong();

			if (!(0 <= length && length <= Integer.MAX_VALUE))
				throw new ZabbixException("bad data length: %d", length);

			logger.debug("reading {} bytes of request data", length);
			data = new byte[(int)length];
			in.readFully(data);
			
			String text = new String(data);
			logger.debug("received the following data in request: {}", text);
			JSONObject json = new JSONObject(text);

			ItemChecker checker;

			if (json.getString(ItemChecker.JSON_TAG_REQUEST).equals(ItemChecker.JSON_REQUEST_INTERNAL))
				checker = new InternalItemChecker(json);
			else if (json.getString(ItemChecker.JSON_TAG_REQUEST).equals(ItemChecker.JSON_REQUEST_JMX))
				checker = new JMXItemChecker(json);
			else
				throw new ZabbixException("bad request tag value: '%s'", json.getString(ItemChecker.JSON_TAG_REQUEST));

			logger.debug("dispatched request to class {}", checker.getClass().getName());
			JSONArray values = checker.getValues();

			JSONObject jsonResponse = new JSONObject();
			jsonResponse.put(ItemChecker.JSON_TAG_RESPONSE, ItemChecker.JSON_RESPONSE_SUCCESS);
			jsonResponse.put(ItemChecker.JSON_TAG_DATA, values);

			String strResponse = jsonResponse.toString(2);
			logger.debug("sending the following data in response: {}", strResponse);
			out.println(strResponse);
		}
		catch (Exception e1)
		{
			logger.warn("error processing request", e1);

			try
			{
				String strResponse = new Formatter().format("{ \"%s\" : \"%s\", \"%s\" : %s }\n",
						ItemChecker.JSON_TAG_RESPONSE, ItemChecker.JSON_RESPONSE_FAILED,
						ItemChecker.JSON_TAG_ERROR, JSONObject.quote(e1.getMessage())).toString();
				logger.debug("sending the following data in response: {}", strResponse);
				out.println(strResponse);
			}
			catch (Exception e2)
			{
				logger.warn("error sending failure notification", e2);
			}
		}
		finally
		{
			try { if (null != socket) socket.close(); } catch (Exception e) { }
			try { if (null != out) out.close(); } catch (Exception e) { }
			try { if (null != in) in.close(); } catch (Exception e) { }
		}

		logger.debug("finished processing incoming connection");
	}
}

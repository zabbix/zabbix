package com.zabbix.proxy;

import java.io.*;
import java.net.Socket;
import java.nio.ByteBuffer;
import java.nio.ByteOrder;
import java.util.Vector;

import org.json.*;

class SocketProcessor implements Runnable
{
	private Socket socket;

	public SocketProcessor(Socket socket)
	{
		this.socket = socket;
	}

	public void run()
	{
		PrintWriter out = null;
		DataInputStream in = null;

		try
		{
			out = new PrintWriter(socket.getOutputStream(), true);
			in = new DataInputStream(socket.getInputStream());

			byte[] data;
			
			data = new byte[13];
			in.readFully(data);

			if (!('Z' == data[0] && 'B' == data[1] && 'X' == data[2] && 'D' == data[3] && '\1' == data[4]))
				throw new ZabbixException("bad tcp header '%02X %02X %02X %02X %02X'", data[0], data[1], data[2], data[3], data[4]);

			ByteBuffer buffer = ByteBuffer.wrap(data, 5, 8);
			buffer.order(ByteOrder.LITTLE_ENDIAN);
			long length = buffer.getLong();

			if (!(0 <= length && length <= Integer.MAX_VALUE))
				throw new ZabbixException("bad data length '%d'", length);

			data = new byte[(int)length];
			in.readFully(data);
			
			String text = new String(data);
			JSONObject json = new JSONObject(text);

			System.out.println("Request: '" + json.toString(2) + "'");

			if (!json.getString("request").equals("java proxy items"))
				throw new ZabbixException("bad value of 'request': '%s'", json.getString("request"));

			JSONArray keys = json.getJSONArray("keys");
			Vector<ZabbixItem> items = new Vector<ZabbixItem>();

			for (int i = 0; i < keys.length(); i++)
				items.add(new ZabbixItem(keys.getString(i)));

			JSONArray values = new ItemProcessor(json.getString("conn"), json.getInt("port"),
													json.optString("username", null), json.optString("password", null)).processAll(items);

			JSONObject response = new JSONObject();
			response.put("response", "success");
			response.put("values", values);
			
			out.println(response.toString(2));
		}
		catch (Exception exception)
		{
			out.printf("{ \"response\" : \"failed\", \"exception\" : %s }\n", JSONObject.quote(exception.getMessage()));
			System.out.println(exception.getMessage());
			exception.printStackTrace();
		}
		finally
		{
			try { if (null != socket) socket.close(); } catch (Exception ex) { }
			try { if (null != out) out.close(); } catch (Exception ex) { }
			try { if (null != in) in.close(); } catch (Exception ex) { }
		}
	}
}

package com.zabbix.proxy;

import org.json.*;

import org.slf4j.Logger;
import org.slf4j.LoggerFactory;

class InternalItemChecker extends ItemChecker
{
	private static final Logger logger = LoggerFactory.getLogger(InternalItemChecker.class);

	public InternalItemChecker(JSONObject request) throws ZabbixException
	{
		super(request);
	}

	@Override
	protected String getStringValue(ZabbixItem item) throws Exception
	{
		return "1";
	}
}

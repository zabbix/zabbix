<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="2.0"
xmlns:xsl="http://www.w3.org/1999/XSL/Transform">

<xsl:output method="xml" indent="yes"/>


<!-- generic template metrics -->


<xsl:template match="template/metrics/system.uptime">
	 <xsl:variable name="metric" as="element()*">
		<metric>
			<name>Device uptime</name>
			<group>General</group>
			<description>The time since the network management portion of the system was last re-initialized.<xsl:value-of select="alarmObject"/></description>
			<units>uptime</units>
			<zabbixKey>system.uptime</zabbixKey>
			<update><xsl:copy-of select="$update30s"/></update>
			<history><xsl:copy-of select="$history14days"/></history>
			<trends><xsl:copy-of select="$trends0days"/></trends>
			<triggers>
				<trigger>
				    <documentation>This trigger expression work as follows:
1. Can be triggered from coldStart trap (either perl script or snmptt)
2. Can be triggered if uptime metric is too low (less then 10 minutes)
3. TRIGGER.VALUE wrappers and avg(1s), str(x,1s) are used to make sure that only metrics with actual values are used to determine proper  trigger's condition.</documentation>
				    <id>uptime.restarted</id>
					<expression>{TRIGGER.VALUE}=0 and ({TEMPLATE_NAME:METRIC.avg(1s)}&lt;10m or
{TEMPLATE_NAME:snmptrap.fallback.str(".1.3.6.1.6.3.1.1.4.1.0         type=6  value=OID: .1.3.6.1.6.3.1.1.5.1",1s)}=1 or
{TEMPLATE_NAME:snmptrap.fallback.str("coldStart",1s)}=1)</expression>
					<recovery_expression>{TRIGGER.VALUE}=1 and ({TEMPLATE_NAME:METRIC.avg(1s)}&gt;10m)</recovery_expression>
					<manual_close>1</manual_close>
	                <name lang="EN"><xsl:value-of select="alarmObject"/> The {HOST.NAME} has just been  restarted</name>
	                <name lang="RU"><xsl:value-of select="alarmObject"/>{HOST.NAME} был только что перезагружен</name>
	                <url/>
	                <priority>2</priority>
	                <description lang="EN">The device uptime is less then 10 minutes or SNMP trap(coldStart) received</description>
	                <description lang="RU">Аптайм устройства менее 10 минут или был получен SNMP trap(coldStart)</description>
	                <dependsOn>
	                	<dependency>nosnmp</dependency>
	               	</dependsOn>
              	    <tags>
	                	<tag>
			 				<tag>Alarm.type</tag>
			                <value>RESTARTED</value>
						</tag>
					</tags>
				</trigger>
			</triggers>
		</metric>
    </xsl:variable>
				
	<xsl:copy>
		<xsl:call-template name="defaultMetricBlock">
				<xsl:with-param name="metric" select="$metric" />
	    </xsl:call-template>
    </xsl:copy>
</xsl:template>


<xsl:template match="template/metrics/snmptrap.fallback">
	 <xsl:variable name="metric" as="element()*">
		<metric>
			<name>SNMP traps (fallback)</name>
			<group>General</group>
			<logFormat>hh:mm:sszyyyy/MM/dd</logFormat>
			<description>Item is used to collect all SNMP traps unmatched by other snmptrap items</description>
			<zabbixKey>snmptrap.fallback</zabbixKey>
			<history><xsl:copy-of select="$history14days"/></history>
			<trends><xsl:copy-of select="$trends0days"/></trends>
			<valueType><xsl:copy-of select="$valueTypeLog"/></valueType>
		</metric>
    </xsl:variable>
				
	<xsl:copy>
		<xsl:call-template name="defaultMetricBlock">
				<xsl:with-param name="metric" select="$metric" />
	    </xsl:call-template>
    </xsl:copy>
</xsl:template>



<xsl:template match="template/metrics/system.contact">
	 <xsl:variable name="metric" as="element()*">
		<metric>
			<name>Device contact details</name>
			<group>General</group>
			<description>The textual identification of the contact person for this managed node, together with information on how to contact this person.  If no contact information is known, the value is the zero-length string.</description>
			<zabbixKey>system.contact</zabbixKey>
			<history><xsl:copy-of select="$history14days"/></history>
			<trends><xsl:copy-of select="$trends0days"/></trends>
			<update><xsl:copy-of select="$update1hour"/></update>
			<valueType><xsl:copy-of select="$valueTypeChar"/></valueType>
			<inventory_link>23</inventory_link>
		</metric>
    </xsl:variable>
				
	<xsl:copy>
		<xsl:call-template name="defaultMetricBlock">
				<xsl:with-param name="metric" select="$metric" />
	    </xsl:call-template>
    </xsl:copy>	
</xsl:template>


<xsl:template match="template/metrics/system.location">
	 <xsl:variable name="metric" as="element()*">
		<metric>
		<name>Device location</name>
		<group>General</group>
		<zabbixKey>system.location</zabbixKey>
		<history><xsl:copy-of select="$history14days"/></history>
		<trends><xsl:copy-of select="$trends0days"/></trends>
		<update><xsl:copy-of select="$update1hour"/></update>
		<valueType><xsl:copy-of select="$valueTypeChar"/></valueType>
		<inventory_link>24</inventory_link>
		</metric>
    </xsl:variable>
				
	<xsl:copy>
		<xsl:call-template name="defaultMetricBlock">
				<xsl:with-param name="metric" select="$metric" />
	    </xsl:call-template>
    </xsl:copy>	
</xsl:template>

<xsl:template match="template/metrics/system.objectid">
	 <xsl:variable name="metric" as="element()*">
		<metric>
			<name>System object ID</name>
			<group>General</group>
			<zabbixKey>system.objectid</zabbixKey>
			<history><xsl:copy-of select="$history14days"/></history>
			<trends><xsl:copy-of select="$trends0days"/></trends>
			<update><xsl:copy-of select="$update1hour"/></update>
			<valueType><xsl:copy-of select="$valueTypeChar"/></valueType>
		</metric>
    </xsl:variable>
				
	<xsl:copy>
		<xsl:call-template name="defaultMetricBlock">
				<xsl:with-param name="metric" select="$metric" />
	    </xsl:call-template>
    </xsl:copy>		
</xsl:template>


<xsl:template match="template/metrics/system.name">
	 <xsl:variable name="metric" as="element()*">
		<metric>
			<name>Device name</name>
			<group>General</group>
			<zabbixKey>system.name</zabbixKey>
			<history><xsl:copy-of select="$history14days"/></history>
			<trends><xsl:copy-of select="$trends0days"/></trends>
			<update><xsl:copy-of select="$update1hour"/></update>
			<valueType><xsl:copy-of select="$valueTypeChar"/></valueType>
			<inventory_link>3</inventory_link>
		</metric>
    </xsl:variable>
				
	<xsl:copy>
		<xsl:call-template name="defaultMetricBlock">
				<xsl:with-param name="metric" select="$metric" />
	    </xsl:call-template>
    </xsl:copy>	
</xsl:template>



<xsl:template match="template/metrics/system.descr">
	 <xsl:variable name="metric" as="element()*">
		<metric>
			<name>Device description</name>
			<group>General</group>
			<zabbixKey>system.descr</zabbixKey>
			<history><xsl:copy-of select="$history14days"/></history>
			<trends><xsl:copy-of select="$trends0days"/></trends>
			<update><xsl:copy-of select="$update1hour"/></update>
			<valueType><xsl:copy-of select="$valueTypeChar"/></valueType>
			<inventory_link>14</inventory_link>
		</metric>
    </xsl:variable>
				
	<xsl:copy>
		<xsl:call-template name="defaultMetricBlock">
				<xsl:with-param name="metric" select="$metric" />
	    </xsl:call-template>
    </xsl:copy>	
</xsl:template>


<xsl:template match="template/metrics/zabbix.snmp.available">
	 <xsl:variable name="metric" as="element()*">
		<metric>
			<name>SNMP availability</name>
			<group>General</group>
			<zabbixKey>zabbix[host,snmp,available]</zabbixKey>
			<history><xsl:copy-of select="$history14days"/></history>
			<trends><xsl:copy-of select="$trends0days"/></trends>
			<update><xsl:copy-of select="$update1min"/></update>
			<valueType><xsl:copy-of select="$valueTypeInt"/></valueType>
			<triggers>
				<trigger>
					<id>nosnmp</id>
					<expression>{TEMPLATE_NAME:METRIC.max({$SNMP_TIMEOUT})}=0</expression>
	                <name lang="EN"><xsl:value-of select="alarmObject"/> No SNMP data collection</name>
	                <name lang="RU"><xsl:value-of select="alarmObject"/> Нет сбора данных по SNMP</name>
	                <url/>
	                <priority>2</priority>
	                <description lang="EN">SNMP is not available for polling. Please check device connectivity and SNMP settings.</description>
	                <description lang="RU">Не удается опросить по SNMP. Проверьте доступность устройства и настройки SNMP.</description>
	                <dependsOn>
	                	<dependency>noping</dependency>
	                	<global>true</global>
	                </dependsOn>
	                <tags>
	                	<tag>
			 				<tag>Alarm.type</tag>
			                <value>NO_DATA</value>
						</tag>
					</tags>
				</trigger>
			</triggers>
		</metric>
    </xsl:variable>
				
	<xsl:copy>
		<xsl:call-template name="defaultMetricBlock">
				<xsl:with-param name="metric" select="$metric" />
	    </xsl:call-template>
    </xsl:copy>	
</xsl:template>

</xsl:stylesheet>


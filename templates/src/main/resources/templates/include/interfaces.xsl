<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="2.0"
xmlns:xsl="http://www.w3.org/1999/XSL/Transform">

<xsl:output method="xml" indent="yes"/>


<xsl:template match="template/metrics/net.if.status">
	 <xsl:variable name="metric" as="element()*">
		<metric>
			<name><xsl:value-of select="if (alarmObject!='') then concat('[',concat(alarmObject,'] ')) else ()"/>ifOperStatus</name>
			<group>Interfaces</group>
			<history><xsl:copy-of select="$history7days"/></history>
			<trends><xsl:copy-of select="$trendsDefault"/></trends>
			<update><xsl:copy-of select="$update1min"/></update>
			<valueType><xsl:copy-of select="$valueTypeInt"/></valueType>
			<triggers>
				<trigger>
				    <documentation>This trigger expression work as follows:
1. Can be triggered from linkDown trap
2. Can be triggered if ifOperStatus = 2
3. TRIGGER.VALUE wrappers and avg(1s) are used to make sure that only metrics with actual values are used to determine proper  trigger's condition.</documentation>
				    <id>if.down</id>
					<!-- will not expand MACRO in SNMPINDEX inside str function in 3.2, only in 3.4-->
					<expression>{TRIGGER.VALUE}=0 and ({TEMPLATE_NAME:METRIC.avg(1s)}=2 or
({TEMPLATE_NAME:snmptrap[".1.3.6.1.6.3.1.1.4.1.0         type=6  value=OID: .1.3.6.1.6.3.1.1.5.[3-4]"].str("  .1.3.6.1.2.1.2.2.1.1           type=2  value=INTEGER: {#SNMPINDEX}",1s)}=1 and
{TEMPLATE_NAME:snmptrap[".1.3.6.1.6.3.1.1.4.1.0         type=6  value=OID: .1.3.6.1.6.3.1.1.5.[3-4]"].str(".1.3.6.1.6.3.1.1.5.3",1s)}=1))</expression>
					<recovery_expression>{TRIGGER.VALUE}=1 and ({TEMPLATE_NAME:METRIC.avg(1s)}=1 or
({TEMPLATE_NAME:snmptrap[".1.3.6.1.6.3.1.1.4.1.0         type=6  value=OID: .1.3.6.1.6.3.1.1.5.[3-4]"].str("  .1.3.6.1.2.1.2.2.1.1           type=2  value=INTEGER: {#SNMPINDEX}",1s)}=1 and
{TEMPLATE_NAME:snmptrap[".1.3.6.1.6.3.1.1.4.1.0         type=6  value=OID: .1.3.6.1.6.3.1.1.5.[3-4]"].str(".1.3.6.1.6.3.1.1.5.4",1s)}=1))</recovery_expression>
					<manual_close>1</manual_close>
	                <name lang="EN"><xsl:value-of select="alarmObject"/> is down</name>
	                <name lang="RU"><xsl:value-of select="alarmObject"/> недоступен</name>
	                <url/>
	                <priority>3</priority>
	                <description lang="EN">linkDown</description>
	                <description lang="RU">интерфейс недоступен</description>
              	    <tags>
	                	<tag>
			 				<tag>Alarm.type</tag>
			                <value>LINK_DOWN</value>
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

<xsl:template match="template/metrics/net.if.in">
	 <xsl:variable name="metric" as="element()*">
		<metric>
			<name><xsl:value-of select="if (alarmObject!='') then concat('[',concat(alarmObject,'] ')) else ()"/>ifOctetsIn</name>
			<group>Interfaces</group>
			<history><xsl:copy-of select="$history7days"/></history>
			<trends><xsl:copy-of select="$trendsDefault"/></trends>
			<update><xsl:copy-of select="$update1min"/></update>
			<valueType><xsl:copy-of select="$valueTypeInt"/></valueType>
			<units>bps</units>
			<triggers/>
		</metric>
    </xsl:variable>
				
	<xsl:copy>
		<xsl:call-template name="defaultMetricBlock">
				<xsl:with-param name="metric" select="$metric" />
	    </xsl:call-template>
    </xsl:copy>	
</xsl:template>


<xsl:template match="template/metrics/net.if.out">
	 <xsl:variable name="metric" as="element()*">
		<metric>
			<name><xsl:value-of select="if (alarmObject!='') then concat('[',concat(alarmObject,'] ')) else ()"/>ifOctetsOut</name>
			<group>Interfaces</group>
			<history><xsl:copy-of select="$history7days"/></history>
			<trends><xsl:copy-of select="$trendsDefault"/></trends>
			<update><xsl:copy-of select="$update1min"/></update>
			<valueType><xsl:copy-of select="$valueTypeInt"/></valueType>
			<units>bps</units>
			<triggers/>
		</metric>
    </xsl:variable>
				
	<xsl:copy>
		<xsl:call-template name="defaultMetricBlock">
				<xsl:with-param name="metric" select="$metric" />
	    </xsl:call-template>
    </xsl:copy>	
</xsl:template>


<xsl:template match="template/metrics/net.if.in.errors">
	 <xsl:variable name="metric" as="element()*">
		<metric>
			<name><xsl:value-of select="if (alarmObject!='') then concat('[',concat(alarmObject,'] ')) else ()"/>ifErrorsIn</name>
			<group>Interfaces</group>
			<history><xsl:copy-of select="$history7days"/></history>
			<trends><xsl:copy-of select="$trendsDefault"/></trends>
			<update><xsl:copy-of select="$update1min"/></update>
			<valueType><xsl:copy-of select="$valueTypeInt"/></valueType>
			<triggers/>
		</metric>
    </xsl:variable>
				
	<xsl:copy>
		<xsl:call-template name="defaultMetricBlock">
				<xsl:with-param name="metric" select="$metric" />
	    </xsl:call-template>
    </xsl:copy>	
</xsl:template>

<xsl:template match="template/metrics/net.if.out.errors">
	 <xsl:variable name="metric" as="element()*">
		<metric>
			<name><xsl:value-of select="if (alarmObject!='') then concat('[',concat(alarmObject,'] ')) else ()"/>ifErrorsOut</name>
			<group>Interfaces</group>
			<history><xsl:copy-of select="$history7days"/></history>
			<trends><xsl:copy-of select="$trendsDefault"/></trends>
			<update><xsl:copy-of select="$update1min"/></update>
			<valueType><xsl:copy-of select="$valueTypeInt"/></valueType>
			<triggers/>
		</metric>
    </xsl:variable>
				
	<xsl:copy>
		<xsl:call-template name="defaultMetricBlock">
				<xsl:with-param name="metric" select="$metric" />
	    </xsl:call-template>
    </xsl:copy>	
</xsl:template>


<xsl:template match="template/metrics/net.if.traps">
	 <xsl:variable name="metric" as="element()*">
		<metric>
			<name>SNMP traps (interfaces)</name>
			<group>Interfaces</group>
			<logFormat>hh:mm:sszyyyy/MM/dd</logFormat>
			<description>Item is used to collect all SNMP traps matched for interfaces</description>
			<zabbixKey>snmptrap[".1.3.6.1.6.3.1.1.4.1.0         type=6  value=OID: .1.3.6.1.6.3.1.1.5.[3-4]"]</zabbixKey><!-- link up or linkdown -->
			<vendorDescription>Catching linkDown and linkUp traps</vendorDescription>
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


</xsl:stylesheet>


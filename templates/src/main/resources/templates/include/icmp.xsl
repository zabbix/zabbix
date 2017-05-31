<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="2.0"
xmlns:xsl="http://www.w3.org/1999/XSL/Transform">

<xsl:output method="xml" indent="yes"/>





<xsl:template match="template/metrics/icmpping">
	 <xsl:variable name="metric" as="element()*">
		<metric>
			<name>ICMP ping</name>
			<group>Status</group>
			<zabbixKey>icmpping</zabbixKey>
			<history><xsl:copy-of select="$history7days"/></history>
			<trends><xsl:copy-of select="$trendsDefault"/></trends>
			<update><xsl:copy-of select="$update1min"/></update>
			<valueType><xsl:copy-of select="$valueTypeInt"/></valueType>
			<triggers>
				<trigger>
					<id>noping</id>
					<expression>{TEMPLATE_NAME:METRIC.max(#3)}=0</expression>
	                <name lang="EN">Unavailable by ICMP ping</name>
	                <name lang="RU">Нет ответа на ICMP ping</name>
	                <description>Last three attempts returned timeout.  Please check device connectivity.</description>
	                <priority>4</priority>
	                <tags>
	                	<tag>
			 				<tag>Alarm.type</tag>
			                <value>NO_PING</value>
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

<xsl:template match="template/metrics/icmppingloss">
	 <xsl:variable name="metric" as="element()*">
		<metric>
			<name>ICMP loss</name>
			<group>Status</group>
			<zabbixKey>icmppingloss</zabbixKey>
			<history><xsl:copy-of select="$history7days"/></history>
			<trends><xsl:copy-of select="$trendsDefault"/></trends>
			<update><xsl:copy-of select="$update1min"/></update>
			<valueType><xsl:copy-of select="$valueTypeFloat"/></valueType>
			<units>%</units>
			<triggers>
				<trigger>
					<id>icmppingloss</id>
					<expression>{TEMPLATE_NAME:METRIC.min(5m)}>{$ICMP_LOSS_WARN}</expression>
	                <name lang="EN">High ICMP ping loss (<xsl:value-of select="$nowEN" />)</name>
	                <name lang="RU">Потеря пакетов ICMP ping (<xsl:value-of select="$nowRU" />)</name>
	                <priority>2</priority>
	                <dependsOn>
	                	<dependency>noping</dependency>
	                </dependsOn>
	                <tags>
	                	<tag>
			 				<tag>Alarm.type</tag>
			                <value>PING_LOSS</value>
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
<xsl:template match="template/metrics/icmppingsec">
	 <xsl:variable name="metric" as="element()*">
		<metric>
			<name>ICMP response time</name>
			<group>Status</group>
			<zabbixKey>icmppingsec</zabbixKey>
			<history><xsl:copy-of select="$history7days"/></history>
			<trends><xsl:copy-of select="$trendsDefault"/></trends>
			<update><xsl:copy-of select="$update1min"/></update>
			<valueType><xsl:copy-of select="$valueTypeFloat"/></valueType>
			<units>s</units>
			<triggers>
				<trigger>
					<id>icmppingsec</id>
					<expression>{TEMPLATE_NAME:METRIC.avg(5m)}>{$ICMP_RESPONSE_TIME_WARN}</expression>
	                <name>High response time (<xsl:value-of select="$nowEN" />)</name>
	                <priority>2</priority>
	                <dependsOn>
	                	<dependency>noping</dependency>
	                	<dependency>icmppingloss</dependency>
	                </dependsOn>
	                <tags/>
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


<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="2.0"
xmlns:xsl="http://www.w3.org/1999/XSL/Transform">

<xsl:output method="xml" indent="yes"/>


<xsl:template match="template/metrics/system.cpu.util">
	 
	 <xsl:variable name="metric" as="element()*">
		<metric>
			<name lang="EN"><xsl:if test="alarmObject != ''">[<xsl:value-of select="alarmObject" />] </xsl:if>CPU utilization</name>
			<name lang="RU"><xsl:if test="alarmObject != ''">[<xsl:value-of select="alarmObject" />] </xsl:if>Загрузка процессора</name>
			<group>CPU</group>
			<description>CPU utilization in %</description>
			<units>%</units>
			<update><xsl:value-of select="$update3min"/></update>
			<triggers>
				<trigger>
					<documentation>If alarmObject is defined, it's added to trigger name.</documentation>
					<expression>{<xsl:value-of select="../../name"/>:METRIC.avg(5m)}>{$CPU_UTIL_MAX}</expression>
	                <name lang="EN"><xsl:if test="alarmObject != ''">[<xsl:value-of select="alarmObject" />] </xsl:if>High CPU utilization (<xsl:value-of select="$nowEN" />)</name>
	                <name lang="RU"><xsl:if test="alarmObject != ''">[<xsl:value-of select="alarmObject" />] </xsl:if>Загрузка ЦПУ слишком велика (<xsl:value-of select="$nowRU" />)</name>
	                <url/>
	                <priority>3</priority>
	                <description />
	                <tags>
	                	<tag>
		                	<tag>Alarm.object.type</tag>
			                <value>
			             		<xsl:call-template name="tagAlarmObjectType">
						         		<xsl:with-param name="alarmObjectType" select="alarmObjectType" />
						         		<xsl:with-param name="alarmObjectDefault">CPU</xsl:with-param>
			 					</xsl:call-template>
			 				</value>
	 					</tag>
						<tag>
		                	<tag>Alarm.type</tag>
			                <value>CPU_UTIL_HIGH</value>
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


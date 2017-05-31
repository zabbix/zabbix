<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="2.0"
xmlns:xsl="http://www.w3.org/1999/XSL/Transform">

<xsl:output method="xml" indent="yes"/>




<!-- metric of hw servers fault -->
<xsl:template match="template/metrics/system.status">
	 <xsl:variable name="metric" as="element()*">
		<metric>
			<name lang="EN">Overall system health status</name>
			<name lang="RU">Общий статус системы</name>
			<group>Status</group>
			<update><xsl:copy-of select="$update30s"/></update>
			<history><xsl:copy-of select="$history14days"/></history>
			<trends><xsl:copy-of select="$trends0days"/></trends>
			<triggers>
					<xsl:if test="../../macros/macro/macro[contains(text(),'HEALTH_DISASTER_STATUS')]">
						<trigger>
						    <id>health.disaster</id>
							<expression>{TEMPLATE_NAME:METRIC.last(0)}={$HEALTH_DISASTER_STATUS}</expression>
			                <name lang="EN">System is in unrecoverable state! (<xsl:value-of select="$nowEN"/>)</name>
			                <name lang="RU">Статус системы: сбой (<xsl:value-of select="$nowRU"/>)</name>
			                <priority>4</priority>
			                <description lang="EN">Please check the device for faults</description>
			                <description lang="RU">Проверьте устройство</description>
			                <tags><tag>
				 				<tag>Alarm.type</tag>
				                <value>HEALTH_FAIL</value>
							</tag></tags>
						</trigger>
					</xsl:if>
					<xsl:if test="../../macros/macro/macro[contains(text(),'HEALTH_CRIT_STATUS')]">
					<trigger>
						<id>health.critical</id>
						<expression>{TEMPLATE_NAME:METRIC.last(0)}={$HEALTH_CRIT_STATUS}</expression>
		                <name lang="EN">System status is in critical state (<xsl:value-of select="$nowEN"/>)</name>
		                <name lang="RU">Статус системы: авария (<xsl:value-of select="$nowRU"/>)</name>
		                <priority>4</priority>
		                <description lang="EN">Please check the device for errors</description>
		                <description lang="RU">Проверьте устройство</description>
		                <dependsOn>
		                	<xsl:if test="../../macros/macro/macro[contains(text(),'HEALTH_DISASTER_STATUS')]">
		                		<dependency>health.disaster</dependency>
		                	</xsl:if>
		               	</dependsOn>
		               	<tags><tag>
			 				<tag>Alarm.type</tag>
			                <value>HEALTH_FAIL</value>
						</tag></tags>
					</trigger>
					</xsl:if>
					<xsl:if test="../../macros/macro/macro[contains(text(),'HEALTH_WARN_STATUS')]">
					<trigger>
					    <id>health.warning</id>
						<expression>{TEMPLATE_NAME:METRIC.last(0)}={$HEALTH_WARN_STATUS}</expression>
		                <name lang="EN">System status is in warning state (<xsl:value-of select="$nowEN"/>)</name>
		                <name lang="RU">Статус системы: предупреждение (<xsl:value-of select="$nowRU"/>)</name>
		                <priority>2</priority>
		                <description lang="EN">Please check the device for warnings</description>
		                <description lang="RU">Проверьте устройство</description>
		                <dependsOn>
		                	<xsl:if test="../../macros/macro/macro[contains(text(),'HEALTH_CRIT_STATUS')]">
		                		<dependency>health.critical</dependency>
		                	</xsl:if>
		               	</dependsOn>
		               	<tags><tag>
			 				<tag>Alarm.type</tag>
			                <value>HEALTH_FAIL</value>
						</tag></tags>
					</trigger>
					</xsl:if>					
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


<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="2.0"
xmlns:xsl="http://www.w3.org/1999/XSL/Transform">

<xsl:output method="xml" indent="yes"/>


<!-- storage(same as memory) -->

<xsl:template match="template/metrics/vfs.fs.units">
	 <xsl:variable name="metric" as="element()*">
		<metric>
			<name>[<xsl:value-of select="alarmObject"/>] Storage units</name>
			<group>Internal Items</group>
			<history><xsl:value-of select="$history7days"/></history>
			<trends><xsl:value-of select="$trends0days"/></trends>
		</metric>
    </xsl:variable>
				
	<xsl:copy>
		<xsl:call-template name="defaultMetricBlock">
				<xsl:with-param name="metric" select="$metric" />
	    </xsl:call-template>
    </xsl:copy>	
</xsl:template>

<xsl:template match="template/metrics/vfs.fs.units.used">
	 <xsl:variable name="metric" as="element()*">
		<metric>
			<name>[<xsl:value-of select="alarmObject"/>] Used storage in units</name>
			<group>Internal Items</group>
			<description>Used storage in units</description>
			<units>units</units>
			<history><xsl:value-of select="$history7days"/></history>
			<trends><xsl:value-of select="$trends0days"/></trends>
		</metric>
    </xsl:variable>
				
	<xsl:copy>
		<xsl:call-template name="defaultMetricBlock">
				<xsl:with-param name="metric" select="$metric" />
	    </xsl:call-template>
    </xsl:copy>	
</xsl:template>


<xsl:template match="template/metrics/vfs.fs.units.total">
	 <xsl:variable name="metric" as="element()*">
		<metric>
			<name>[<xsl:value-of select="alarmObject"/>] Total space in units</name>
			<group>Internal Items</group>
			<description>Total space in units</description>
			<history><xsl:value-of select="$history7days"/></history>
			<trends><xsl:value-of select="$trends0days"/></trends>
			<units>units</units>
		</metric>
    </xsl:variable>
				
	<xsl:copy>
		<xsl:call-template name="defaultMetricBlock">
				<xsl:with-param name="metric" select="$metric" />
	    </xsl:call-template>
    </xsl:copy>		
</xsl:template>


<xsl:template match="template/metrics/vfs.fs.used">
	 <xsl:variable name="metric" as="element()*">
		<metric>
			<name>[<xsl:value-of select="alarmObject"/>] Used space</name>
			<group>Storage</group>
			<description>Used storage in Bytes</description>
			<xsl:choose>
				<xsl:when test="./calculated = 'true'">
						<xsl:choose>
							<xsl:when test="../vfs.fs.units.used and  ../vfs.fs.units">
								<expressionFormula>(last(vfs.fs.units.used[<xsl:value-of select="../vfs.fs.units.used/snmpObject"/>])*last(vfs.fs.units[<xsl:value-of select="../vfs.fs.units/snmpObject"/>]))</expressionFormula>
							</xsl:when>
						</xsl:choose>				
				</xsl:when>
			</xsl:choose>
			<units>B</units>
		</metric>
    </xsl:variable>
				
	<xsl:copy>
		<xsl:call-template name="defaultMetricBlock">
				<xsl:with-param name="metric" select="$metric" />
	    </xsl:call-template>
    </xsl:copy>		
</xsl:template>

<xsl:template match="template/metrics/vfs.fs.free">
	 <xsl:variable name="metric" as="element()*">
		<metric>
			<name>[<xsl:value-of select="alarmObject"/>] Free space</name>
			<group>Storage</group>
			<units>B</units>
		</metric>
    </xsl:variable>
				
	<xsl:copy>
		<xsl:call-template name="defaultMetricBlock">
				<xsl:with-param name="metric" select="$metric" />
	    </xsl:call-template>
    </xsl:copy>	
</xsl:template>



<xsl:template match="template/metrics/vfs.fs.total">
	 <xsl:variable name="metric" as="element()*">
		<metric>
			<name>[<xsl:value-of select="alarmObject"/>] Total space</name>
			<group>Storage</group>
			<description>Total space in Bytes</description>			
			<xsl:choose>
				<xsl:when test="./calculated = 'true'">
						<xsl:choose>
							<xsl:when test="../vfs.fs.units.total and  ../vfs.fs.units">
								<expressionFormula>(last(vfs.fs.units.total[<xsl:value-of select="../vfs.fs.units.total/snmpObject"/>])*last(vfs.fs.units[<xsl:value-of select="../vfs.fs.units/snmpObject"/>]))</expressionFormula>
							</xsl:when>
						</xsl:choose>				
				</xsl:when>
			</xsl:choose>
			<units>B</units>
		</metric>
    </xsl:variable>
				
	<xsl:copy>
		<xsl:call-template name="defaultMetricBlock">
				<xsl:with-param name="metric" select="$metric" />
	    </xsl:call-template>
    </xsl:copy>	
</xsl:template>


<xsl:template match="template/metrics/vfs.fs.pused">
	 <xsl:variable name="metric" as="element()*">
		<metric>
			<name>[<xsl:value-of select="alarmObject"/>] Storage utilization</name>
			<group>Storage</group>
			<description>Storage utilization in % for <xsl:value-of select="alarmObject"/></description>			
			<xsl:choose>
				<xsl:when test="./calculated = 'true'">
						<xsl:choose>
							<xsl:when test="../vfs.fs.units.total and  ../vfs.fs.units.used">
								<expressionFormula>(last(vfs.fs.units.used[<xsl:value-of select="../vfs.fs.units.used/snmpObject"/>])/last(vfs.fs.units.total[<xsl:value-of select="../vfs.fs.units.total/snmpObject"/>]))*100</expressionFormula>
							</xsl:when>
							<xsl:when test="../vfs.fs.total and  ../vfs.fs.used">
								<expressionFormula>(last(vfs.fs.used[<xsl:value-of select="../vfs.fs.used/snmpObject"/>])/last(vfs.fs.total[<xsl:value-of select="../vfs.fs.total/snmpObject"/>]))*100</expressionFormula>
							</xsl:when>
							<xsl:when test="../vfs.fs.total and  ../vfs.fs.free">
								<expressionFormula>((last(vfs.fs.total[<xsl:value-of select="../vfs.fs.total/snmpObject"/>])-last(vfs.fs.free[<xsl:value-of select="../vfs.fs.free/snmpObject"/>]))/last(vfs.fs.total[<xsl:value-of select="../vfs.fs.total/snmpObject"/>]))*100</expressionFormula>
							</xsl:when>
							<xsl:otherwise>
								<expressionFormula>(last(vfs.fs.used[<xsl:value-of select="../vfs.fs.used/snmpObject"/>])/(last(vfs.fs.free[<xsl:value-of select="../vfs.fs.free/snmpObject"/>])+last(vfs.fs.used[<xsl:value-of select="../vfs.fs.used/snmpObject"/>])))*100</expressionFormula>
							</xsl:otherwise>
						</xsl:choose>				
				</xsl:when>
			</xsl:choose>	
			<valueType><xsl:copy-of select="$valueTypeFloat"/></valueType>
			<units>%</units>
			<triggers>
					<trigger>
						<id>storageCrit</id>
						<expression>{TEMPLATE_NAME:METRIC.avg(5m)}>{$STORAGE_UTIL_CRIT}</expression>
		                <name lang="EN">[<xsl:value-of select="alarmObject"/>] Free disk space is low (utilized by {ITEM.VALUE1})</name>
		                <name lang="RU">[<xsl:value-of select="alarmObject"/>] Мало свободного места (использовано: {ITEM.VALUE1})</name>
		                <url/>
		                <priority>3</priority>
		                <description/>
		                <tags>
			                <tag>
			                	<tag>Alarm.object.type</tag>
				                <value>
				             		<xsl:call-template name="tagAlarmObjectType">
							         		<xsl:with-param name="alarmObjectType" select="alarmObjectType"/>
							         		<xsl:with-param name="alarmObjectDefault">Storage</xsl:with-param>	 					
				 					</xsl:call-template>
				 				</value>
							</tag>
							<tag>
			                	<tag>Alarm.type</tag>
				                <value>STORAGE_UTIL_HIGH</value>
							</tag>
						</tags>
					</trigger>
					
					<trigger>
						<id>storageWarn</id>
						<expression>{TEMPLATE_NAME:METRIC.avg(5m)}>{$STORAGE_UTIL_WARN}</expression>
		                <name lang="EN">[<xsl:value-of select="alarmObject"/>] Free disk space is low (utilized by {ITEM.VALUE1})</name>
		                <name lang="RU">[<xsl:value-of select="alarmObject"/>] Мало свободного места (использовано: {ITEM.VALUE1})</name>
		                <url/>
		                <priority>2</priority>
		                <description/>
						<dependsOn>
		                	<dependency>storageCrit</dependency>
		               	</dependsOn>
		               	<tags>
			               	<tag>
			                	<tag>Alarm.object.type</tag>
				                <value>
				             		<xsl:call-template name="tagAlarmObjectType">
							         		<xsl:with-param name="alarmObjectType" select="alarmObjectType"/>
							         		<xsl:with-param name="alarmObjectDefault">Storage</xsl:with-param>	 					
				 					</xsl:call-template>
				 				</value>
							</tag>
							<tag>
			                	<tag>Alarm.type</tag>
				                <value>STORAGE_UTIL_HIGH</value>
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


<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="2.0"
xmlns:xsl="http://www.w3.org/1999/XSL/Transform">

<xsl:output method="xml" indent="yes"/>



<!-- memory -->
<xsl:template match="template/metrics/vm.memory.units">
	 <xsl:variable name="metric" as="element()*">
		<metric>
			<name>Memory units</name>
			<group>Internal Items</group>
			<update><xsl:value-of select="$update3min"/></update>
			<trends><xsl:value-of select="$trends0days"/></trends>
			<history><xsl:value-of select="$history7days"/></history>
		</metric>
    </xsl:variable>
				
	<xsl:copy>
		<xsl:call-template name="defaultMetricBlock">
				<xsl:with-param name="metric" select="$metric" />
	    </xsl:call-template>
    </xsl:copy>	
</xsl:template>

<xsl:template match="template/metrics/vm.memory.units.used">
	 <xsl:variable name="metric" as="element()*">
		<metric>
			<name>Used memory in units</name>
			<group>Internal Items</group>
			<units>units</units>
			<update><xsl:value-of select="$update3min"/></update>
			<trends><xsl:value-of select="$trends0days"/></trends>
			<history><xsl:value-of select="$history7days"/></history>
			<description>Used memory in units</description>
		</metric>
    </xsl:variable>
				
	<xsl:copy>
		<xsl:call-template name="defaultMetricBlock">
				<xsl:with-param name="metric" select="$metric" />
	    </xsl:call-template>
    </xsl:copy>	
</xsl:template>

<xsl:template match="template/metrics/vm.memory.units.total">
	 <xsl:variable name="metric" as="element()*">
		<metric>
			<name>Total memory in units</name>
			<group>Internal Items</group>
			<update><xsl:value-of select="$update3min"/></update>
			<trends><xsl:value-of select="$trends0days"/></trends>
			<history><xsl:value-of select="$history7days"/></history>
			<units>units</units>
			<description>Total memory in units</description>
		</metric>
    </xsl:variable>
				
	<xsl:copy>
		<xsl:call-template name="defaultMetricBlock">
				<xsl:with-param name="metric" select="$metric" />
	    </xsl:call-template>
    </xsl:copy>	
</xsl:template>	



<xsl:template match="template/metrics/vm.memory.used">
	 <xsl:variable name="metric" as="element()*">
		<metric>
			<name><xsl:if test="alarmObject != ''">[<xsl:value-of select="alarmObject"/>] </xsl:if>Used memory</name>
			<group>Memory</group>
			<units>B</units>
			<description>Used memory in Bytes</description>
			<update><xsl:value-of select="$update3min"/></update>
			<xsl:choose>
				<xsl:when test="./calculated = 'true'">
						<xsl:choose>
							<xsl:when test="../vm.memory.units.used and  ../vm.memory.units">
								<expressionFormula>(last(vm.memory.units.used[<xsl:value-of select="../vm.memory.units.used/snmpObject"/>])*last(vm.memory.units[<xsl:value-of select="../vm.memory.units/snmpObject"/>]))</expressionFormula>
							</xsl:when>
						</xsl:choose>				
				</xsl:when>
			</xsl:choose>			
		</metric>
    </xsl:variable>
				
	<xsl:copy>
		<xsl:call-template name="defaultMetricBlock">
				<xsl:with-param name="metric" select="$metric" />
	    </xsl:call-template>
    </xsl:copy>	
</xsl:template>

<xsl:template match="template/metrics/vm.memory.free">
	 <xsl:variable name="metric" as="element()*">
		<metric>
			<name><xsl:if test="alarmObject != ''">[<xsl:value-of select="alarmObject"/>] </xsl:if>Available memory</name> <!--  Available as in zabbix agent templates -->
			<group>Memory</group>
			<update><xsl:value-of select="$update3min"/></update>
			<units>B</units>
		</metric>
    </xsl:variable>
				
	<xsl:copy>
		<xsl:call-template name="defaultMetricBlock">
				<xsl:with-param name="metric" select="$metric" />
	    </xsl:call-template>
    </xsl:copy>
</xsl:template>





<xsl:template match="template/metrics/vm.memory.total">
	 <xsl:variable name="metric" as="element()*">
		<metric>
			<name><xsl:if test="alarmObject != ''">[<xsl:value-of select="alarmObject"/>] </xsl:if>Total memory</name>
			<group>Memory</group>
			<description>Total memory in Bytes</description>
			<units>B</units>
			<update><xsl:value-of select="$update3min"/></update>
			<xsl:choose>
				<xsl:when test="./calculated = 'true'">
					<xsl:choose>
						<xsl:when test="../vm.memory.units.total and  ../vm.memory.units">
							<expressionFormula>(last(vm.memory.units.total[<xsl:value-of select="../vm.memory.units.total/snmpObject"/>])*last(vm.memory.units[<xsl:value-of select="../vm.memory.units/snmpObject"/>]))</expressionFormula>
						</xsl:when>
					</xsl:choose>				
				</xsl:when>
			</xsl:choose>
		</metric>
    </xsl:variable>
				
	<xsl:copy>
		<xsl:call-template name="defaultMetricBlock">
				<xsl:with-param name="metric" select="$metric" />
	    </xsl:call-template>
    </xsl:copy>
</xsl:template>


<xsl:template match="template/metrics/vm.memory.pused">
	
	 <xsl:variable name="metric" as="element()*">
		<metric>
			<name><xsl:if test="alarmObject != ''">[<xsl:value-of select="alarmObject"/>] </xsl:if>Memory utilization</name>
			<group>Memory</group>
			<description>Memory utilization in %</description>
			<units>%</units>
			<update><xsl:value-of select="$update3min"/></update>
			<xsl:choose>
				<xsl:when test="./calculated = 'true'">
						<xsl:choose>
							<xsl:when test="../vm.memory.units.total and  ../vm.memory.units.used">
								<expressionFormula>(last(vm.memory.units.used[<xsl:value-of select="../vm.memory.units.used/snmpObject"/>])/last(vm.memory.units.total[<xsl:value-of select="../vm.memory.units.total/snmpObject"/>]))*100</expressionFormula>
							</xsl:when>
							<xsl:when test="../vm.memory.total and  ../vm.memory.used">
								<expressionFormula>(last(vm.memory.used[<xsl:value-of select="../vm.memory.used/snmpObject"/>])/last(vm.memory.total[<xsl:value-of select="../vm.memory.total/snmpObject"/>]))*100</expressionFormula>
							</xsl:when>
							<xsl:when test="../vm.memory.total and  ../vm.memory.free">
								<expressionFormula>((last(vm.memory.total[<xsl:value-of select="../vm.memory.total/snmpObject"/>])-last(vm.memory.free[<xsl:value-of select="../vm.memory.free/snmpObject"/>]))/last(vm.memory.total[<xsl:value-of select="../vm.memory.total/snmpObject"/>]))*100</expressionFormula>
							</xsl:when>
							<xsl:otherwise>
								<expressionFormula>(last(vm.memory.used[<xsl:value-of select="../vm.memory.used/snmpObject"/>])/(last(vm.memory.free[<xsl:value-of select="../vm.memory.free/snmpObject"/>])+last(vm.memory.used[<xsl:value-of select="../vm.memory.used/snmpObject"/>])))*100</expressionFormula>
							</xsl:otherwise>
						</xsl:choose>				
				</xsl:when>
			</xsl:choose>
			<valueType><xsl:copy-of select="$valueTypeFloat"/></valueType>
			<triggers>
				<trigger>
					<expression>{TEMPLATE_NAME:METRIC.avg(5m)}>{$MEMORY_UTIL_MAX}</expression>
	                <name lang="EN"><xsl:if test="alarmObject != ''">[<xsl:value-of select="alarmObject"/>] </xsl:if>High memory utilization (<xsl:value-of select="$nowEN" />)</name>
	                <name lang="RU"><xsl:if test="alarmObject != ''">[<xsl:value-of select="alarmObject"/>] </xsl:if>Мало свободной памяти ОЗУ (<xsl:value-of select="$nowRU" />)</name>
	                <url/>
	                <priority>3</priority>
	                <description/>
	                <tags>	
	                	<tag>
		                	<tag>Alarm.object.type</tag>
			                <value>
			             		<xsl:call-template name="tagAlarmObjectType">
						         		
						         		<xsl:with-param name="alarmObjectType" select="alarmObjectType"/>
						         		<xsl:with-param name="alarmObjectDefault">Memory</xsl:with-param>	 					
			 					</xsl:call-template>
			 				</value>
						</tag>
						<tag>
		                	<tag>Alarm.type</tag>
			                <value>MEMORY_UTIL_HIGH</value>
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


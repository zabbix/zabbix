<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="2.0"
xmlns:xsl="http://www.w3.org/1999/XSL/Transform">

<xsl:output method="xml" indent="yes"/>

<!-- inventory -->

<xsl:template match="template/metrics/system.sw.os">
	 <xsl:variable name="metric" as="element()*">
		<metric>
			<name>Operating system</name>
			<group>Inventory</group>
			<xsl:if test="not(alarmObject)">
				<zabbixKey>system.sw.os</zabbixKey>
			</xsl:if>
			<history><xsl:copy-of select="$history14days"/></history>
			<trends><xsl:copy-of select="$trends0days"/></trends>
			<update><xsl:copy-of select="$update1hour"/></update>
			<valueType><xsl:copy-of select="$valueTypeChar"/></valueType>
			<inventory_link>5</inventory_link>
		</metric>
    </xsl:variable>
				
	<xsl:copy>
		<xsl:call-template name="defaultMetricBlock">
				<xsl:with-param name="metric" select="$metric" />
	    </xsl:call-template>
    </xsl:copy>	
</xsl:template>


<xsl:template match="template/metrics/system.hw.model">
	 <xsl:variable name="metric" as="element()*">
		<metric>
			<name lang="EN">Hardware model name</name>
			<name lang="RU">Модель</name>
			<group>Inventory</group>
			<xsl:if test="not(alarmObject)">
				<zabbixKey>system.hw.model</zabbixKey>
			</xsl:if>
			<history><xsl:copy-of select="$history14days"/></history>
			<trends><xsl:copy-of select="$trends0days"/></trends>
			<update><xsl:copy-of select="$update1hour"/></update>
			<valueType><xsl:copy-of select="$valueTypeChar"/></valueType>
			<inventory_link>29</inventory_link> <!-- model -->
		</metric>
    </xsl:variable>
				
	<xsl:copy>
		<xsl:call-template name="defaultMetricBlock">
				<xsl:with-param name="metric" select="$metric" />
	    </xsl:call-template>
    </xsl:copy>	
</xsl:template>


<xsl:template match="template/metrics/system.hw.serialnumber">
	 <xsl:variable name="metric" as="element()*">
		<metric>
			<name lang="EN"><xsl:value-of select="if (alarmObject!='') then concat('[',concat(alarmObject,'] ')) else ()"/>Hardware serial number</name>
			<name lang="RU"><xsl:value-of select="if (alarmObject!='') then concat('[',concat(alarmObject,'] ')) else ()"/>Серийный номер</name>
			<group>Inventory</group>
			<xsl:if test="not(alarmObject)">
				<zabbixKey>system.hw.serialnumber</zabbixKey>
			</xsl:if>
			<history><xsl:copy-of select="$history14days"/></history>
			<trends><xsl:copy-of select="$trends0days"/></trends>
			<update><xsl:copy-of select="$update1hour"/></update>
			<valueType><xsl:copy-of select="$valueTypeChar"/></valueType>
			<inventory_link>8</inventory_link> <!-- serial_noa-->
			<triggers>
				<trigger>
				    <id>sn.changed</id>
					<expression>{TEMPLATE_NAME:METRIC.diff()}=1 and {TEMPLATE_NAME:METRIC.strlen()}&gt;0</expression>
					<recovery_mode>2</recovery_mode>
					<manual_close>1</manual_close>
	                <name lang="EN"><xsl:value-of select="if (alarmObject!='') then alarmObject else $defaultAlarmObjectType" /> might have been replaced (new serial number:{ITEM.VALUE1})</name>
	                <name lang="RU">Возможно замена <xsl:value-of select="if (alarmObject!='') then alarmObject else 'устройства'" /> (новый серийный номер:{ITEM.VALUE1})</name>
	                <url/>
	                <priority>1</priority>
	                <description lang="EN"><xsl:value-of select="if (alarmObject!='') then alarmObject else $defaultAlarmObjectType" /> serial number has changed. Ack to close</description>
	                <description lang="RU">Изменился серийный номер <xsl:value-of select="if (alarmObject!='') then alarmObject else 'устройства'" />. Подтвердите и закройте.</description>
	                <tags>
	                	<tag>
			 				<tag>Alarm.type</tag>
			                <value>SN_CHANGE</value>
						</tag>
						<tag>
		                	<tag>Alarm.object.type</tag>
			                <value>
			             		<xsl:call-template name="tagAlarmObjectType">
						         		<xsl:with-param name="alarmObjectType" select="alarmObjectType" />
						         		<xsl:with-param name="alarmObjectDefault">Device</xsl:with-param>
			 					</xsl:call-template>
			 				</value>
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

<xsl:template match="template/metrics/system.hw.firmware">
	 <xsl:variable name="metric" as="element()*">
		<metric>
			<name lang="EN">Firmware version</name>
			<name lang="RU">Версия прошивки</name>
			<group>Inventory</group>
			<xsl:if test="not(alarmObject)">
				<zabbixKey>system.hw.firmware</zabbixKey>
			</xsl:if>
			<history><xsl:copy-of select="$history14days"/></history>
			<trends><xsl:copy-of select="$trends0days"/></trends>
			<update><xsl:copy-of select="$update1hour"/></update>
			<valueType><xsl:copy-of select="$valueTypeChar"/></valueType>
			<triggers>
				<trigger>
				    <id>firmware.changed</id>
					<expression>{TEMPLATE_NAME:METRIC.diff()}=1 and {TEMPLATE_NAME:METRIC.strlen()}&gt;0</expression>
					<recovery_mode>2</recovery_mode>
					<manual_close>1</manual_close>
	                <name lang="EN">Firmware has changed: (new:{ITEM.VALUE1})</name>
	                <name lang="RU">Версия прошивки изменилась: (сейчас:{ITEM.VALUE1})</name>
	                <url/>
	                <priority>1</priority>
	                <description lang="EN">Firmware version has changed. Ack to close</description>
	                <description lang="RU">Версия прошивки изменилась. Подтвердите и закройте.</description>
	                <!-- <dependsOn>
		                	<dependency>sn.changed</dependency>
		            </dependsOn> -->
                    <tags>
	                	<tag>
			 				<tag>Alarm.type</tag>
			                <value>FIRMWARE_CHANGE</value>
						</tag>
						<tag>
		                	<tag>Alarm.object.type</tag>
			                <value>
			             		<xsl:call-template name="tagAlarmObjectType">
						         		<xsl:with-param name="alarmObjectType" select="alarmObjectType" />
						         		<xsl:with-param name="alarmObjectDefault">Device</xsl:with-param>
			 					</xsl:call-template>
			 				</value>
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



<xsl:template match="template/metrics/system.hw.version">
	 <xsl:variable name="metric" as="element()*">
		<metric>
			<name lang="EN">Hardware version(revision)</name>
			<name lang="RU">Версия ревизии</name>
			<group>Inventory</group>
			<xsl:if test="not(alarmObject)">
				<zabbixKey>system.hw.version</zabbixKey>
			</xsl:if>
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

</xsl:stylesheet>


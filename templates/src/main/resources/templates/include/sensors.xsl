<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="2.0"
xmlns:xsl="http://www.w3.org/1999/XSL/Transform">

<xsl:output method="xml" indent="yes"/>


<xsl:template match="template/metrics/sensor.temp.value">
	 <xsl:variable name="metric" as="element()*">
		<metric>
			<name lang="EN"><xsl:value-of select="if (alarmObject!='') then concat('[',concat(alarmObject,'] ')) else ()"/>Temperature</name>
			<name lang="RU"><xsl:value-of select="if (alarmObject!='') then concat('[',concat(alarmObject,'] ')) else ()"/>Температура</name>
			<group>Temperature</group>
			<description>Temperature readings of testpoint: <xsl:value-of select="alarmObject"/></description>
			<units>°С</units>
			<valueType><xsl:copy-of select="$valueTypeFloat"/></valueType>
			<update>
				<!-- TODO: make this feature global -->
				<xsl:call-template name="updateIntervalTemplate">
	         		<xsl:with-param name="updateMultiplier" select="updateMultiplier"/>
	         		<xsl:with-param name="default" select="$update3min"/>
	 			</xsl:call-template>
 			</update>
			<triggers>
				<trigger>
				    <!-- <documentation>Using recovery expression... Temperature has to drop 5 points less than threshold level  ({$TEMP_WARN}-5)</documentation>  -->
				    <id>tempWarn</id>

					<!-- if sensor.temp.status is defined and is within same discovery rule with system.temp.value then add it TO trigger:-->
					<xsl:variable name="expression">{TEMPLATE_NAME:METRIC.avg(5m)}&gt;{$TEMP_WARN:"<xsl:value-of select="alarmObjectType" />"}</xsl:variable>
					<xsl:variable name="recovery_expression">{TEMPLATE_NAME:METRIC.max(5m)}&lt;{$TEMP_WARN:"<xsl:value-of select="alarmObjectType" />"}-5</xsl:variable>
					<xsl:variable name="discoveryRule" select="discoveryRule"/>
					<!-- Careful, since recovery expression will work only if simple expression is ALSO FALSE. So no point to define STATUS in recovery. -->
					<xsl:choose>
						 <xsl:when test="
						 	(../sensor.temp.status[discoveryRule = $discoveryRule] or (../sensor.temp.status[not(discoveryRule)] and
						 	 not(discoveryRule))
						 	 )
						 	 and ../../macros/macro/macro[contains(text(),'TEMP_WARN_STATUS')]
						 	"><!-- if discoveryRules match or both doesn't have discoveryRule -->
						 <xsl:variable name="statusMetricKey"><xsl:value-of select="../sensor.temp.status/name()"/>[<xsl:value-of select="../sensor.temp.status/snmpObject"/>]</xsl:variable>
							
							<expression><xsl:value-of select="$expression"/>
							<xsl:if test="../../macros/macro/macro[contains(text(),'TEMP_WARN_STATUS')]">
							or
							{<xsl:value-of select="../../name"/>:<xsl:value-of select="$statusMetricKey"/>.last(0)}={$TEMP_WARN_STATUS}
							</xsl:if></expression>
							
							<recovery_expression>
							<xsl:value-of select="$recovery_expression"/>
							<!-- AND
							{<xsl:value-of select="../../name"/>:<xsl:value-of select="$statusMetricKey"/>.last(0)}={$TEMP_CRIT_STATUS} -->
							</recovery_expression>
							<name lang="EN"><xsl:value-of select="alarmObject" /> temperature is above warning threshold: >{$TEMP_WARN:"<xsl:value-of select="alarmObjectType" />"} (<xsl:value-of select="$nowEN" />)({ITEM.VALUE2})</name>
	                		<name lang="RU">[<xsl:value-of select="alarmObject" />] Температура выше нормы: >{$TEMP_WARN:"<xsl:value-of select="alarmObjectType" />"} (<xsl:value-of select="$nowRU" />)({ITEM.VALUE2})</name>
														
						</xsl:when>
						<xsl:otherwise><expression><xsl:value-of select="$expression"/></expression>
						<recovery_expression><xsl:value-of select="$recovery_expression"/></recovery_expression>
						<name lang="EN"><xsl:value-of select="alarmObject" /> temperature is above warning threshold: >{$TEMP_WARN:"<xsl:value-of select="alarmObjectType" />"} (<xsl:value-of select="$nowEN" />)</name>
	                	<name lang="RU">[<xsl:value-of select="alarmObject" />] Температура выше нормы: >{$TEMP_WARN:"<xsl:value-of select="alarmObjectType" />"} (<xsl:value-of select="$nowRU" />)</name>
						</xsl:otherwise>
					</xsl:choose>	                
	                
	                
	                <url />
	                <priority>2</priority>
	                <description>This trigger uses temperature sensor values as well as temperature sensor status if available</description>
	                <dependsOn>
	                	<dependency>tempCrit</dependency>
	               	</dependsOn>
	               	<tags>	                
	               		<tag>
		                	<tag>Alarm.object.type</tag>
			                <value>
			             		<xsl:call-template name="tagAlarmObjectType">
						         		<xsl:with-param name="alarmObjectType" select="alarmObjectType"/>
						         		<xsl:with-param name="alarmObjectDefault" select="$defaultAlarmObjectType"/>	 					
			 					</xsl:call-template>
			 				</value>
						</tag>
		               	<tag>
		               		<tag>Alarm.type</tag>
		               		<value>OVERHEAT</value>
	               		</tag>
               		</tags>
				</trigger>
				<trigger>
					<!-- <documentation>Using recovery expression... Temperature has to drop 5 points less than threshold level  ({$TEMP_WARN}-5)</documentation>  -->
					<id>tempCrit</id>
					
					<!-- if sensor.temp.status is defined and is within same discovery rule with system.temp.value then add it TO trigger:-->
					<xsl:variable name="expression">{TEMPLATE_NAME:METRIC.avg(5m)}>{$TEMP_CRIT:"<xsl:value-of select="alarmObjectType"/>"}</xsl:variable>
					<xsl:variable name="recovery_expression">{TEMPLATE_NAME:METRIC.max(5m)}&lt;{$TEMP_CRIT:"<xsl:value-of select="alarmObjectType" />"}-5</xsl:variable>
					<xsl:variable name="discoveryRule" select="discoveryRule"/>
					<!-- Careful, since recovery expression will work only if simple expression is ALSO FALSE. So no point to define STATUS in recovery. -->
					
					<xsl:choose>
						 <xsl:when test="
						 	(../sensor.temp.status[discoveryRule = $discoveryRule] or (../sensor.temp.status[not(discoveryRule)] and
						 	 not(discoveryRule))
						 	 )
						 	 and (
						 	 ../../macros/macro/macro[contains(text(),'TEMP_CRIT_STATUS')] or
						 	 ../../macros/macro/macro[contains(text(),'TEMP_DISASTER_STATUS')])
						 "><!-- if discoveryRules match or both doesn't have discoveryRule -->
						 <xsl:variable name="statusMetricKey"><xsl:value-of select="../sensor.temp.status/name()"/>[<xsl:value-of select="../sensor.temp.status/snmpObject"/>]</xsl:variable>
							
							<expression><xsl:value-of select="$expression"/>
							<xsl:if test="../../macros/macro/macro[contains(text(),'TEMP_CRIT_STATUS')]">
							or
							{<xsl:value-of select="../../name"/>:<xsl:value-of select="$statusMetricKey"/>.last(0)}={$TEMP_CRIT_STATUS}
							</xsl:if>
							<xsl:if test="../../macros/macro/macro[contains(text(),'TEMP_DISASTER_STATUS')]">
								or
								{<xsl:value-of select="../../name"/>:<xsl:value-of select="$statusMetricKey"/>.last(0)}={$TEMP_DISASTER_STATUS}
							</xsl:if></expression>
							
							<recovery_expression>
							<xsl:value-of select="$recovery_expression"/>
							<!-- AND
							{<xsl:value-of select="../../name"/>:<xsl:value-of select="$statusMetricKey"/>.last(0)}={$TEMP_CRIT_STATUS} -->
							</recovery_expression>
							<name lang="EN"><xsl:value-of select="alarmObject"/> temperature is above critical threshold: >{$TEMP_CRIT:"<xsl:value-of select="alarmObjectType"/>"} (<xsl:value-of select="$nowEN" />)({ITEM.VALUE2})</name>
	                		<name lang="RU">[<xsl:value-of select="alarmObject"/>] Температура очень высокая: >{$TEMP_CRIT:"<xsl:value-of select="alarmObjectType"/>"} (<xsl:value-of select="$nowRU" />)({ITEM.VALUE2})</name>
						</xsl:when>
						<xsl:otherwise><expression><xsl:value-of select="$expression"/></expression>
						<recovery_expression><xsl:value-of select="$recovery_expression"/></recovery_expression>
						<name lang="EN"><xsl:value-of select="alarmObject"/> temperature is above critical threshold: >{$TEMP_CRIT:"<xsl:value-of select="alarmObjectType"/>"} (<xsl:value-of select="$nowEN" />)</name>
	                	<name lang="RU">[<xsl:value-of select="alarmObject"/>] Температура очень высокая: >{$TEMP_CRIT:"<xsl:value-of select="alarmObjectType"/>"} (<xsl:value-of select="$nowRU" />)</name>						
						</xsl:otherwise>
					</xsl:choose>
					
					
	                
	                <url/>
	                <priority>4</priority>
	                <description>This trigger uses temperature sensor values as well as temperature sensor status if available</description>
	                <tags>
		                <tag>
		                	<tag>Alarm.object.type</tag>
			                <value>
			             		<xsl:call-template name="tagAlarmObjectType">
						         		<xsl:with-param name="alarmObjectType" select="alarmObjectType"/>
						         		<xsl:with-param name="alarmObjectDefault" select="$defaultAlarmObjectType"/>	 					
			 					</xsl:call-template>
			 				</value>
						</tag>
   		               	<tag>
		               		<tag>Alarm.type</tag>
		               		<value>OVERHEAT</value>
	               		</tag>
	                </tags>
				</trigger>
				<trigger>
				    <!-- <documentation>Using recovery expression... Temperature has to be 5 points more than threshold level  ({$TEMP_CRIT_LOW}+5)</documentation>  -->
				    <id>tempLow</id>
					<expression>{TEMPLATE_NAME:METRIC.avg(5m)}&lt;{$TEMP_CRIT_LOW:"<xsl:value-of select="alarmObjectType" />"}</expression>
					<recovery_expression>{TEMPLATE_NAME:METRIC.min(5m)}&gt;{$TEMP_CRIT_LOW:"<xsl:value-of select="alarmObjectType" />"}+5</recovery_expression>
	                <name lang="EN"><xsl:value-of select="alarmObject" /> temperature is too low: &lt;{$TEMP_CRIT_LOW:"<xsl:value-of select="alarmObjectType" />"} (<xsl:value-of select="$nowEN" />)</name>
	                <name lang="RU">[<xsl:value-of select="alarmObject" />] Температура слишком низкая: &lt;{$TEMP_CRIT_LOW:"<xsl:value-of select="alarmObjectType" />"} (<xsl:value-of select="$nowRU" />)</name>
	                <url />
	                <priority>3</priority>
	                <description />
	               	<tags>	                
	               		<tag>
		                	<tag>Alarm.object.type</tag>
			                <value>
			             		<xsl:call-template name="tagAlarmObjectType">
						         		<xsl:with-param name="alarmObjectType" select="alarmObjectType"/>
						         		<xsl:with-param name="alarmObjectDefault" select="$defaultAlarmObjectType"/>	 					
			 					</xsl:call-template>
			 				</value>
						</tag>
		               	<tag>
		               		<tag>Alarm.type</tag>
		               		<value>TEMP_LOW</value>
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


<xsl:template match="template/metrics/sensor.temp.status">
	 <xsl:variable name="metric" as="element()*">
		<metric>
			<name>[<xsl:value-of select="alarmObject"/>] Temperature status</name>
			<group>Temperature</group>
			<update><xsl:value-of select="$update3min"/></update>
			<history><xsl:value-of select="$history14days"/></history>
			<trends><xsl:value-of select="$trends0days"/></trends>
			<description>Temperature status of testpoint: <xsl:value-of select="alarmObject"/></description>
		</metric>
    </xsl:variable>
				
	<xsl:copy>
		<xsl:call-template name="defaultMetricBlock">
				<xsl:with-param name="metric" select="$metric" />
	    </xsl:call-template>
    </xsl:copy>
</xsl:template>


<xsl:template match="template/metrics/sensor.temp.locale">
	 <xsl:variable name="metric" as="element()*">
		<metric>
			<name>[<xsl:value-of select="alarmObject"/>] Temperature sensor location</name>
			<group>Temperature</group>
			<description>Temperature location of testpoint: <xsl:value-of select="alarmObject"/></description>
			<history><xsl:copy-of select="$history7days"/></history>
			<trends><xsl:copy-of select="$trends0days"/></trends>
			<update><xsl:copy-of select="$update1hour"/></update>
		</metric>
    </xsl:variable>
				
	<xsl:copy>
		<xsl:call-template name="defaultMetricBlock">
				<xsl:with-param name="metric" select="$metric" />
	    </xsl:call-template>
    </xsl:copy>
</xsl:template>



<xsl:template match="template/metrics/sensor.psu.status">
	 <xsl:variable name="metric" as="element()*">
		<metric>
			<name lang="EN">[<xsl:value-of select="alarmObject"/>] Power supply status</name>
			<name lang="RU">[<xsl:value-of select="alarmObject"/>] Статус блока питания</name>
			<group>Power Supply</group>
			<update><xsl:copy-of select="$update3min"/></update>
			<history><xsl:copy-of select="$history14days"/></history>
			<trends><xsl:copy-of select="$trends0days"/></trends>
			<valueType><xsl:copy-of select="$valueTypeChar"/></valueType>
			<triggers>
				<trigger>
					<id>psu.critical</id>
					<expression>{TEMPLATE_NAME:METRIC.last(0)}={$PSU_CRIT_STATUS}</expression>
	                <name lang="EN">[<xsl:value-of select="alarmObject"/>] Power supply is in critical state (<xsl:value-of select="$nowEN" />)</name>
	                <name lang="RU">[<xsl:value-of select="alarmObject"/>] Статус блока питания: авария (<xsl:value-of select="$nowRU" />)</name>
	                <priority>3</priority>
	                <description lang="EN">Please check the power supply unit for errors</description>
	                <description lang="RU">Проверьте блок питания</description>
	               	<tags>
						<tag>
		                	<tag>Alarm.object.type</tag>
			                <value>
			             		<xsl:call-template name="tagAlarmObjectType">
						         		<xsl:with-param name="alarmObjectType" select="alarmObjectType"/>
						         		<xsl:with-param name="alarmObjectDefault">PSU</xsl:with-param>
			 					</xsl:call-template>
			 				</value>
			 				</tag>
			 				<tag>
				 				<tag>Alarm.type</tag>
				                <value>PSU_FAIL</value>
							</tag>
	               	</tags>
				</trigger>
<!-- 				<trigger>
					<id>psu.notok</id>
					<expression>{TEMPLATE_NAME:METRIC.last(0)}&lt;&gt;{$PSU_OK_STATUS}</expression>
	                <name lang="EN">[<xsl:value-of select="alarmObject"/>] Power supply status: (<xsl:value-of select="$nowEN" />)</name>
	                <name lang="RU">[<xsl:value-of select="alarmObject"/>] Статус блока питания: (<xsl:value-of select="$nowRU" />)</name>
	                <priority>1</priority>
	                <description lang="EN">Please check the power supply unit</description>
	                <description lang="RU">Проверьте блок питания</description>
	                <dependsOn>
	                	<dependency>psu.critical</dependency>
	               	</dependsOn>
	               	<tags>
						<tag>
		                	<tag>Alarm.object.type</tag>
			                <value>
			             		<xsl:call-template name="tagAlarmObjectType">
						         		<xsl:with-param name="alarmObjectType" select="alarmObjectType"/>
						         		<xsl:with-param name="alarmObjectDefault">PSU</xsl:with-param>
			 					</xsl:call-template>
			 				</value>
						</tag>
	               	</tags>
				</trigger>	 -->			
			</triggers>
		</metric>
    </xsl:variable>
				
	<xsl:copy>
		<xsl:call-template name="defaultMetricBlock">
				<xsl:with-param name="metric" select="$metric" />
	    </xsl:call-template>
    </xsl:copy>
</xsl:template>



<xsl:template match="template/metrics/sensor.fan.status">
	 <xsl:variable name="metric" as="element()*">
		<metric>
			<name lang="EN">[<xsl:value-of select="alarmObject"/>] Fan status</name>
			<name lang="RU">[<xsl:value-of select="alarmObject"/>] Статус вентилятора</name>
			<group>Fans</group>
			<update><xsl:copy-of select="$update3min"/></update>
			<history><xsl:copy-of select="$history14days"/></history>
			<trends><xsl:copy-of select="$trends0days"/></trends>
			<valueType><xsl:copy-of select="$valueTypeChar"/></valueType>
			<triggers>
				<trigger>
					<id>fan.critical</id>
					<expression>{TEMPLATE_NAME:METRIC.last(0)}={$FAN_CRIT_STATUS}</expression>
	                <name lang="EN"><xsl:value-of select="alarmObject"/> fan is in critical state (<xsl:value-of select="$nowEN" />)</name>
	                <name lang="RU">[<xsl:value-of select="alarmObject"/>] Статус вентилятора: сбой (<xsl:value-of select="$nowRU" />)</name>
	                <priority>3</priority>
	                <description lang="EN">Please check the fan unit</description>
	                <description lang="RU">Проверьте вентилятор</description>
	               	<tags>
						<tag>
		                	<tag>Alarm.object.type</tag>
			                <value>
			             		<xsl:call-template name="tagAlarmObjectType">
						         		<xsl:with-param name="alarmObjectType" select="alarmObjectType"/>
						         		<xsl:with-param name="alarmObjectDefault">Fan</xsl:with-param>
			 					</xsl:call-template>
			 				</value>
						</tag>
						<tag>
				 				<tag>Alarm.type</tag>
				                <value>FAN_FAIL</value>
						</tag>
	               	</tags>
				</trigger>
<!-- 				<trigger>
					<id>fan.notok</id>
					<expression>{TEMPLATE_NAME:METRIC.last(0)}&lt;&gt;{$FAN_OK_STATUS}</expression>
	                <name lang="EN">[<xsl:value-of select="alarmObject"/>] Fan status: (<xsl:value-of select="$nowEN" />)</name>
	                <name lang="RU">[<xsl:value-of select="alarmObject"/>] Статус вентилятора: (<xsl:value-of select="$nowRU" />)</name>
	                <priority>1</priority>
	                <description lang="EN">Please check the fan unit</description>
	                <description lang="RU">Проверьте вентилятор</description>
	                <dependsOn>
	                	<dependency>fan.critical</dependency>
	               	</dependsOn>
	               	<tags>
						<tag>
		                	<tag>Alarm.object.type</tag>
			                <value>
			             		<xsl:call-template name="tagAlarmObjectType">
						         		<xsl:with-param name="alarmObjectType" select="alarmObjectType"/>
						         		<xsl:with-param name="alarmObjectDefault">FAN</xsl:with-param>
			 					</xsl:call-template>
			 				</value>
						</tag>
	               	</tags>
				</trigger>	 -->
			</triggers>
		</metric>
    </xsl:variable>
				
	<xsl:copy>
		<xsl:call-template name="defaultMetricBlock">
				<xsl:with-param name="metric" select="$metric" />
	    </xsl:call-template>
    </xsl:copy>
</xsl:template>




<xsl:template match="template/metrics/sensor.fan.speed">
	 <xsl:variable name="metric" as="element()*">
		<metric>
			<name lang="EN">[<xsl:value-of select="alarmObject"/>] Fan speed</name>
			<name lang="RU">[<xsl:value-of select="alarmObject"/>] Скорость вращения вентилятора</name>
			<group>Fans</group>
			<units>rpm</units>
			<triggers/>
		</metric>
    </xsl:variable>
				
	<xsl:copy>
		<xsl:call-template name="defaultMetricBlock">
				<xsl:with-param name="metric" select="$metric" />
	    </xsl:call-template>
    </xsl:copy>
</xsl:template>
</xsl:stylesheet>


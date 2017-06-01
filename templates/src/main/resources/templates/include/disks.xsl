<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="2.0"
xmlns:xsl="http://www.w3.org/1999/XSL/Transform">

<xsl:output method="xml" indent="yes"/>


<xsl:template match="template/metrics/system.hw.diskarray.status">
	 <xsl:variable name="metric" as="element()*">
		<metric>
			<name lang="EN">[<xsl:value-of select="alarmObject"/>] Disk array controller status</name>
			<name lang="RU">[<xsl:value-of select="alarmObject"/>] Статус контроллера дискового массива</name>
			<group>Disk Arrays</group>
			<history><xsl:copy-of select="$history7days"/></history>
			<trends><xsl:copy-of select="$trends0days"/></trends>
			<valueType><xsl:copy-of select="$valueTypeChar"/></valueType>
			<triggers>
				<trigger>
				    <id>disk_array.disaster</id>
					<expression>{TEMPLATE_NAME:METRIC.last(0)}={$DISK_ARRAY_DISASTER_STATUS}</expression>
	                <name lang="EN">[<xsl:value-of select="alarmObject"/>] Disk array controller is in unrecoverable state!</name>
	                <name lang="RU">[<xsl:value-of select="alarmObject"/>] Статус контроллера дискового массива: сбой</name>
	                <url/>
	                <priority>5</priority>
	                <description lang="EN">Please check the device for faults</description>
	                <description lang="RU">Проверьте устройство</description>
	                <tags>
						<tag>
		                	<tag>Alarm.object.type</tag>
			                <value>
			             		<xsl:call-template name="tagAlarmObjectType">
						         		
						         		<xsl:with-param name="alarmObjectType" select="alarmObjectType"/>
						         		<xsl:with-param name="alarmObjectDefault">Disk</xsl:with-param>
			 					</xsl:call-template>
			 				</value>
						</tag>
	                </tags>
				</trigger>
				<trigger>
				    <id>disk_array.warning</id>
					<expression>{TEMPLATE_NAME:METRIC.last(0)}={$DISK_ARRAY_WARN_STATUS}</expression>
	                <name lang="EN">[<xsl:value-of select="alarmObject"/>] Disk array controller is in warning state</name>
	                <name lang="RU">[<xsl:value-of select="alarmObject"/>] Статус контроллера дискового массива: предупреждение</name>
	                <url/>
	                <priority>2</priority>
	                <description lang="EN">Please check the device for warnings</description>
	                <description lang="RU">Проверьте устройство</description>
	                <dependsOn>
	                	<dependency>disk_array.critical</dependency>
	               	</dependsOn>
	               	<tags>
             			<tag>
		                	<tag>Alarm.object.type</tag>
			                <value>
			             		<xsl:call-template name="tagAlarmObjectType">
						         		
						         		<xsl:with-param name="alarmObjectType" select="alarmObjectType"/>
						         		<xsl:with-param name="alarmObjectDefault">Disk</xsl:with-param>
			 					</xsl:call-template>
			 				</value>
						</tag>
               		</tags>
				</trigger>
				<trigger>
					<id>disk_array.critical</id>
					<expression>{TEMPLATE_NAME:METRIC.last(0)}={$DISK_ARRAY_CRIT_STATUS}</expression>
	                <name lang="EN">[<xsl:value-of select="alarmObject"/>] Disk array controller is in critical state</name>
	                <name lang="RU">[<xsl:value-of select="alarmObject"/>] Статус контроллера дискового массива: авария</name>
	                <url/>
	                <priority>4</priority>
	                <description lang="EN">Please check the device for errors</description>
	                <description lang="RU">Проверьте устройство</description>
	                <dependsOn>
	                	<dependency>disk_array.disaster</dependency>
	               	</dependsOn>
	               	<tags>
						<tag>
		                	<tag>Alarm.object.type</tag>
			                <value>
			             		<xsl:call-template name="tagAlarmObjectType">
						         		
						         		<xsl:with-param name="alarmObjectType" select="alarmObjectType"/>
						         		<xsl:with-param name="alarmObjectDefault">Disk</xsl:with-param>
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

<xsl:template match="template/metrics/system.hw.diskarray.model">
	 <xsl:variable name="metric" as="element()*">
		<metric>
			<name lang="EN">[<xsl:value-of select="alarmObject"/>] Disk array controller model</name>
			<name lang="RU">[<xsl:value-of select="alarmObject"/>] Модель контроллера дискового массива</name>
			<group>Disk Arrays</group>
			<trends><xsl:copy-of select="$trends0days"/></trends>
			<update><xsl:copy-of select="$update1day"/></update>
			<valueType><xsl:copy-of select="$valueTypeChar"/></valueType>
		</metric>
    </xsl:variable>
				
	<xsl:copy>
		<xsl:call-template name="defaultMetricBlock">
				<xsl:with-param name="metric" select="$metric" />
	    </xsl:call-template>
    </xsl:copy>
</xsl:template>

<xsl:template match="template/metrics/system.hw.physicaldisk.status">
	 <xsl:variable name="metric" as="element()*">
		<metric>
			<name lang="EN">[<xsl:value-of select="alarmObject"/>] Physical Disk Status</name>
			<name lang="RU">[<xsl:value-of select="alarmObject"/>] Статус физического диска</name>
			<group>Disks</group>
			<trends><xsl:copy-of select="$trends0days"/></trends>
			<valueType><xsl:copy-of select="$valueTypeChar"/></valueType>
			<triggers>
					<trigger>
					    <id>disk.notok</id>
						<expression>{TEMPLATE_NAME:METRIC.str({$DISK_OK_STATUS})}=0 and 
						{TEMPLATE_NAME:METRIC.str("")}=0</expression>
		                <name lang="EN">[<xsl:value-of select="alarmObject"/>] Physical disk is not in OK state</name>
		                <name lang="RU">[<xsl:value-of select="alarmObject"/>] Статус физического диска не норма</name>
		                <url/>
		                <priority>2</priority>
		                <description lang="EN">Please check physical disk for warnings or errors</description>
		                <description lang="RU">Проверьте диск</description>
		                <dependsOn>
		                	<dependency>disk.fail</dependency>
		                	<dependency>disk.warning</dependency>
		               	</dependsOn>
		               	<tags>
		                <tag>
		                	<tag>Alarm.object.type</tag>
			                <value>
			             		<xsl:call-template name="tagAlarmObjectType">
						         		<xsl:with-param name="alarmObjectType" select="alarmObjectType"/>
						         		<xsl:with-param name="alarmObjectDefault">Disk</xsl:with-param>	 					
			 					</xsl:call-template>
			 				</value>
						</tag>
		               		
		               	</tags>
					</trigger>
		
					<trigger>
					    <id>disk.warning</id>
						<expression>{TEMPLATE_NAME:METRIC.last(0)}={$DISK_WARN_STATUS}</expression>
		                <name lang="EN">[<xsl:value-of select="alarmObject"/>] Physical disk is in warning state</name>
		                <name lang="RU">[<xsl:value-of select="alarmObject"/>] Статус физического диска: предупреждение</name>
		                <url/>
		                <priority>2</priority>
		                <description lang="EN">Please check physical disk for warnings or errors</description>
		                <description lang="RU">Проверьте диск</description><dependsOn>
		                	<dependency>disk.fail</dependency>
		               	</dependsOn>
		               	<tags>			                
		               		<tag>
			                	<tag>Alarm.object.type</tag>
				                <value>
				             		<xsl:call-template name="tagAlarmObjectType">
							         		
							         		<xsl:with-param name="alarmObjectType" select="alarmObjectType"/>
							         		<xsl:with-param name="alarmObjectDefault">Disk</xsl:with-param>
				 					</xsl:call-template>
				 				</value>
							</tag>
						</tags>
					</trigger>
					<trigger>
						<id>disk.fail</id>
						<expression>{TEMPLATE_NAME:METRIC.last(0)}={$DISK_FAIL_STATUS}</expression>
		                <name lang="EN">[<xsl:value-of select="alarmObject"/>] Physical disk failed</name>
		                <name lang="RU">[<xsl:value-of select="alarmObject"/>] Статус физического диска: сбой</name>
		                <url/>
		                <priority>4</priority>
						<description lang="EN">Please check physical disk for warnings or errors</description>
		                <description lang="RU">Проверьте диск</description>
		                <tags>
			                <tag>
			                	<tag>Alarm.object.type</tag>
				                <value>
				             		<xsl:call-template name="tagAlarmObjectType">
							         		<xsl:with-param name="alarmObjectType" select="alarmObjectType"/>
							         		<xsl:with-param name="alarmObjectDefault">Disk</xsl:with-param>
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


<xsl:template match="template/metrics/system.hw.physicaldisk.serialnumber">
	 <xsl:variable name="metric" as="element()*">
		<metric>
			<name lang="EN">[<xsl:value-of select="alarmObject"/>] Physical Disk Serial Number</name>
			<name lang="RU">[<xsl:value-of select="alarmObject"/>] Серийный номер физического диска</name>
			<group>Disks</group>
			<trends><xsl:copy-of select="$trends0days"/></trends>
			<update><xsl:copy-of select="$update1day"/></update>
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


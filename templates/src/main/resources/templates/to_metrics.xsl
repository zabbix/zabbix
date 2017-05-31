<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="2.0"
xmlns:xsl="http://www.w3.org/1999/XSL/Transform">

<xsl:output method="xml" indent="yes"/>


<xsl:variable name="historyDefault">30</xsl:variable>
<xsl:variable name="history30days">30</xsl:variable>
<xsl:variable name="history14days">14</xsl:variable>  
<xsl:variable name="history7days">7</xsl:variable>
<xsl:variable name="trendsDefault">365</xsl:variable>
<xsl:variable name="trends365days">365</xsl:variable> 
<xsl:variable name="trends0days">0</xsl:variable>
<xsl:variable name="updateDefault">300</xsl:variable> 
<xsl:variable name="update30s">30</xsl:variable>
<xsl:variable name="update1min">60</xsl:variable>
<xsl:variable name="update3min">180</xsl:variable>
<xsl:variable name="update5min">300</xsl:variable>
<xsl:variable name="update1hour">3600</xsl:variable>
<xsl:variable name="update4hours">14400</xsl:variable> 
<xsl:variable name="update1day">86400</xsl:variable>




<xsl:variable name="valueType">3</xsl:variable>
<xsl:variable name="valueTypeFloat">0</xsl:variable>
<xsl:variable name="valueTypeChar">1</xsl:variable>
<xsl:variable name="valueTypeLog">2</xsl:variable>
<xsl:variable name="valueTypeInt">3</xsl:variable>
<xsl:variable name="valueTypeText">4</xsl:variable>
	<!-- Type of information of the item. 
	Possible values: 
	0 - numeric float; 
	1 - character; 
	2 - log; 
	3 - numeric unsigned; 
	4 - text. -->

<!--  define macros with default values to add into template-->
    <xsl:variable name="MACROS" as="element()*">
        <Performance>
			<CPU_UTIL_MAX>
				<value>90</value>
			</CPU_UTIL_MAX>
			<MEMORY_UTIL_MAX><value>90</value></MEMORY_UTIL_MAX>
        </Performance>
        <Fault>
        	<!-- <TEMP_CRIT>
        		<value>75</value>
        		<context>CPU</context>
       		</TEMP_CRIT>
       		<TEMP_WARN>
        		<value>70</value>
        		<context>CPU</context>
       		</TEMP_WARN>
       		<TEMP_CRIT>
        		<value>35</value>
        		<context>Ambient</context>
       		</TEMP_CRIT>
       		<TEMP_WARN>
        		<value>30</value>
        		<context>Ambient</context>
       		</TEMP_WARN> -->
       		
        	<TEMP_CRIT><value>60</value></TEMP_CRIT>
        	<TEMP_WARN><value>50</value></TEMP_WARN>
        	<TEMP_CRIT_LOW><value>5</value></TEMP_CRIT_LOW>
        	<STORAGE_UTIL_CRIT><value>90</value></STORAGE_UTIL_CRIT>
        	<STORAGE_UTIL_WARN><value>80</value></STORAGE_UTIL_WARN>
        </Fault>
        <General>
        	<SNMP_TIMEOUT><value>3m</value></SNMP_TIMEOUT>
        </General>
        <ICMP>
        	<ICMP_LOSS_WARN><value>20</value></ICMP_LOSS_WARN>
        	<ICMP_RESPONSE_TIME_WARN><value>0.15</value></ICMP_RESPONSE_TIME_WARN>
        </ICMP>
    </xsl:variable>

<xsl:variable name="nowEN">now: {ITEM.LASTVALUE1}</xsl:variable>
<xsl:variable name="nowRU">сейчас: {ITEM.LASTVALUE1}</xsl:variable>





<xsl:template match="node()|@*">
   <xsl:copy>
            <xsl:apply-templates select="node()|@*"/>
   </xsl:copy>
</xsl:template>

<xsl:template match="value_maps">
	<value_maps>
		<xsl:copy-of copy-namespaces="no" select="node()|@*"></xsl:copy-of>
	</value_maps>
</xsl:template>


<!-- This template modifies update interval if needed -->
<xsl:template name="updateIntervalTemplate">
  <xsl:param name="updateMultiplier"/>
  <xsl:param name="default"/>
  <xsl:if test="$updateMultiplier">
      <xsl:value-of select="$updateMultiplier * $default" />
    </xsl:if>
    <xsl:if test="not($updateMultiplier)">
      <xsl:value-of select="$default" />
  </xsl:if>
</xsl:template>

<xsl:variable name="defaultAlarmObjectType">Device</xsl:variable>
<xsl:template name="tagAlarmObjectType">
  <xsl:param name="alarmObjectType"/>
  <xsl:param name="alarmObjectDefault"/>
  <xsl:if test="$alarmObjectType">
      <xsl:value-of select="$alarmObjectType" />
  </xsl:if>
  <xsl:if test="not($alarmObjectType)">
      <xsl:value-of select="$alarmObjectDefault" />
  </xsl:if>
</xsl:template>


<xsl:template match="/*/template">
     
	     <xsl:copy>
			<xsl:apply-templates select="node()|@*"/>
			<macros>
				<xsl:for-each select="./classes">
		     		<xsl:variable name="template_class" select="./class"/>
			         <!-- add extra contextual no checks. should be before default $MACROS!-->
					<xsl:copy-of copy-namespaces="no" select="../macros/macro"/>
						<xsl:for-each select="$MACROS">
							<xsl:choose>
								<xsl:when test="name(.) = $template_class">
									<xsl:for-each select="./*">
										<macro>
							        		<macro>{$<xsl:value-of select ="name(.)"/><xsl:if test="./context!=''">:"<xsl:value-of select="./context"/>"</xsl:if>}</macro>
							                <value><xsl:value-of select="./value"/></value>
										</macro>
									</xsl:for-each>
								</xsl:when>
							</xsl:choose>
			         	</xsl:for-each>
	         	</xsl:for-each>
    		</macros>
    	<!-- add template name with _SNMP_PLACEHOLDER at the end to make dependency dynamic -->
    	<templates>
    		<!-- copy from templates first -->
    		<xsl:copy-of copy-namespaces="no" select="templates/template"/>
    		<xsl:for-each select="./classes/*">
		     		<xsl:variable name="template_class" select="."/>
	   			<xsl:choose>
					<xsl:when test="$template_class = 'Performance'">
							<!-- monitor.virton specific
							<template>
				        		<name>Template Interfaces vZbx3_SNMP_PLACEHOLDER</name>
							</template>  -->
					</xsl:when>
					 <xsl:when test="$template_class = 'Fault'">
							<!-- temp include -->
							
					</xsl:when>
					<xsl:when test="$template_class = 'Inventory'">
							<template>
				        		<name>Template SNMP Generic_SNMP_PLACEHOLDER</name>
							</template>
<!-- 							<template>
				        		<name>Template ICMP Ping</name>
							</template>	 -->
					</xsl:when>
				</xsl:choose>
			</xsl:for-each>
    	
    	</templates>
      </xsl:copy>
      
      
</xsl:template>

<xsl:template match="macros"/><!-- leave it empty -->
<xsl:template match="template/templates"/><!-- leave it empty -->


<!-- This block describes basic metric structure. Call it from each metric below-->
<xsl:template name="defaultMetricBlock">
		<xsl:param name="metric"/>
		<xsl:variable name="metricKey">
		<xsl:choose>
			<xsl:when test="$metric/zabbixKey"><xsl:value-of select="$metric/zabbixKey"/></xsl:when>
			<xsl:otherwise><xsl:value-of select="name()"/>[<xsl:value-of select="snmpObject"/>]</xsl:otherwise>
		</xsl:choose>
		</xsl:variable>
		<documentation><xsl:value-of select="documentation" /></documentation>
		<xsl:copy-of select="$metric/name"></xsl:copy-of>
		<xsl:copy-of select="$metric/group"></xsl:copy-of>

		
		
			<xsl:choose>
				<xsl:when test="itemType">
					<snmpObject><xsl:value-of select="$metricKey"/></snmpObject>
					<xsl:copy-of select="itemType"/>
				</xsl:when>
				<xsl:when test="$metric/expressionFormula">
					<snmpObject><xsl:value-of select="$metricKey"/></snmpObject>
					<xsl:copy-of select="$metric/expressionFormula"></xsl:copy-of>
					<itemType>calculated</itemType>
				</xsl:when>
				<xsl:otherwise>
					<xsl:copy-of select="oid"/>
					<snmpObject><xsl:value-of select="$metricKey"/></snmpObject>
					<xsl:copy-of select="mib"/>
					<itemType>snmp</itemType>
				</xsl:otherwise>
			</xsl:choose>
		
		<xsl:copy-of select="ref"></xsl:copy-of>
		<xsl:copy-of select="vendorDescription"></xsl:copy-of>
		<xsl:copy-of select="$metric/description"></xsl:copy-of>
		<xsl:copy-of select="$metric/logFormat"></xsl:copy-of>
		<xsl:choose>
			<xsl:when test="$metric/inventory_link and not(discoveryRule)">
				<inventory_link><xsl:value-of select="$metric/inventory_link"/></inventory_link>
			</xsl:when>
		</xsl:choose>
		
		
		

		<xsl:choose>
			<xsl:when test="$metric/history">
				<xsl:copy-of select="$metric/history"/>
			</xsl:when>
			<xsl:otherwise>
				<history><xsl:copy-of select="$historyDefault"/></history>
			</xsl:otherwise>
		</xsl:choose>
		
		<xsl:choose>
			<xsl:when test="$metric/trends">
				<xsl:copy-of select="$metric/trends"/>
			</xsl:when>
			<xsl:otherwise>
				<trends><xsl:copy-of select="$trendsDefault"/></trends>
			</xsl:otherwise>
		</xsl:choose>
		
		<xsl:copy-of select="$metric/units"></xsl:copy-of>
		
		<xsl:choose>
			<xsl:when test="$metric/update">
				<xsl:copy-of select="$metric/update"/>
			</xsl:when>
			<xsl:otherwise>
				<update><xsl:copy-of select="$updateDefault" /></update>
			</xsl:otherwise>
		</xsl:choose> 
		
		<xsl:choose>
			<xsl:when test="$metric/valueType">
				<xsl:copy-of select="$metric/valueType"/>
			</xsl:when>
			<xsl:otherwise>
				<valueType><xsl:copy-of select="$valueType" /></valueType>
			</xsl:otherwise>
		</xsl:choose> 
		
		
		<valueMap><xsl:value-of select="valueMap" /></valueMap>
		<multiplier><xsl:value-of select="multiplier" /></multiplier>
		
		<xsl:choose>
			<xsl:when test="preprocessing">
				<xsl:copy-of select="preprocessing"/>
			</xsl:when>
			<xsl:otherwise>
				<preprocessing/> <!-- 3.4 -->
			</xsl:otherwise>
		</xsl:choose>
		<alarmObject><xsl:value-of select="./alarmObject"/></alarmObject>
		<xsl:copy-of select="./discoveryRule"></xsl:copy-of>
		<xsl:if test="$metric/triggers/trigger">
			<triggers>
				<xsl:for-each select="$metric/triggers/*">
	    			<xsl:call-template name="defaultTriggerBlock">
						<xsl:with-param name="trigger" select="." />
						<xsl:with-param name="metricKey" select="$metricKey" />
		    		</xsl:call-template>            
				</xsl:for-each> 
				
			</triggers>
		</xsl:if>
		<xsl:if test="$metric/graphs/graph">
			<graphs>
				<xsl:for-each select="$metric/graphs/*">
						<xsl:copy-of select="."/>       
				</xsl:for-each> 
				
			</graphs>
		</xsl:if>
		
		<!-- <xsl:copy-of select="$metric/triggers"></xsl:copy-of> -->

</xsl:template>

<!-- This block describes basic trigger structure. Call it from each trigger in metrics below-->
<xsl:template name="defaultTriggerBlock">
		<xsl:param name="trigger"/>
		<xsl:param name="metricKey"/>
			<trigger>
					<xsl:copy-of select="$trigger/documentation"></xsl:copy-of>
					<xsl:copy-of select="$trigger/id"></xsl:copy-of>
					<!-- <xsl:copy-of select="$trigger/expression"></xsl:copy-of> -->
					<expression><xsl:value-of select="replace($trigger/expression, 'METRIC', $metricKey)"/></expression>
					<recovery_expression><xsl:value-of select="replace($trigger/recovery_expression, 'METRIC', $metricKey)"/></recovery_expression>
		            <xsl:copy-of select="$trigger/recovery_mode"></xsl:copy-of>
		            <xsl:copy-of select="$trigger/manual_close"></xsl:copy-of>
					<xsl:copy-of select="$trigger/name"></xsl:copy-of>
					<xsl:copy-of select="$trigger/url"></xsl:copy-of>
					<xsl:copy-of select="$trigger/priority"></xsl:copy-of>
					<xsl:copy-of select="$trigger/description"></xsl:copy-of>
					<xsl:copy-of select="$trigger/dependsOn"></xsl:copy-of>
	                <tags>
	                	<xsl:copy-of select="$trigger/tags/tag"></xsl:copy-of>
		                <tag><tag>Host</tag><value>{HOST.HOST}</value></tag>
	                </tags>
			</trigger>
</xsl:template>


<!-- This block describes basic graph structure. Call it for each graph needed-->
<xsl:template name="defaultGraphBlock">
		<xsl:param name="graph"/>
			<xsl:copy-of select="$graph/name"/>
            <width>900</width>
            <height>200</height>
         	<xsl:choose>
				<xsl:when test="$graph/yaxismin">
					<xsl:copy-of select="$graph/yaxismin"/>
				</xsl:when>
				<xsl:otherwise>
					<yaxismin>0</yaxismin>
				</xsl:otherwise>
			</xsl:choose>
			
			<xsl:choose>
				<xsl:when test="$graph/yaxismax">
					<xsl:copy-of select="$graph/yaxismax"/>
				</xsl:when>
				<xsl:otherwise>
					<yaxismin>100</yaxismin>
				</xsl:otherwise>
			</xsl:choose>  
			
            <show_work_period>1</show_work_period>
            <show_triggers>1</show_triggers>
            <type>0</type>
            <show_legend>1</show_legend>
            <show_3d>0</show_3d>
            <percent_left>0.0000</percent_left>
            <percent_right>0.0000</percent_right>
            <ymin_type_1>0</ymin_type_1> <!-- type_1: 0 fixed, 1- calculated, 2- item -->
            <ymax_type_1>0</ymax_type_1>
            <ymin_item_1>0</ymin_item_1>
            <ymax_item_1>0</ymax_item_1>
            <graph_items>
                <graph_item>
                    <sortorder>0</sortorder>
                    <drawtype>0</drawtype>
                    <color>1A7C11</color>
                    <yaxisside>0</yaxisside>
                    <calc_fnc>2</calc_fnc>
                    <type>0</type>
                    <item>
                        <host>Template Cisco IOS Software releases 12.2_3.5_ or later SNMPv2</host>
                        <key>sysUpTime</key>
                    </item>
                </graph_item>
                <graph_item>
                    <sortorder>1</sortorder>
                    <drawtype>0</drawtype>
                    <color>F63100</color>
                    <yaxisside>0</yaxisside>
                    <calc_fnc>2</calc_fnc>
                    <type>0</type>
                    <item>
                        <host>Template Cisco IOS Software releases 12.2_3.5_ or later SNMPv2</host>
                        <key>icmppingsec</key>
                    </item>
                </graph_item>
            </graph_items>

</xsl:template>

 

<xsl:include href="include/cpu.xsl"/>
<xsl:include href="include/memory.xsl"/>
<xsl:include href="include/vfs.xsl"/>
<xsl:include href="include/sensors.xsl"/>
<xsl:include href="include/status.xsl"/>
<xsl:include href="include/disks.xsl"/>
<xsl:include href="include/generic.xsl"/>
<xsl:include href="include/icmp.xsl"/>
<xsl:include href="include/inventory.xsl"/>

<xsl:include href="include/interfaces.xsl"/>

</xsl:stylesheet>


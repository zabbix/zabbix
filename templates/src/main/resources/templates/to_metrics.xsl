<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="2.0"
xmlns:xsl="http://www.w3.org/1999/XSL/Transform">

<xsl:output method="xml" indent="yes"/>

<!-- 
<xsl:variable name="historyDefault">3</xsl:variable> 
<xsl:variable name="history1week">7</xsl:variable>
<xsl:variable name="trendsDefault">7</xsl:variable> 
<xsl:variable name="trends0days">0</xsl:variable>
<xsl:variable name="updateDefault">30</xsl:variable> 
<xsl:variable name="update1min">60</xsl:variable>
<xsl:variable name="update5min">300</xsl:variable>
<xsl:variable name="update1hour">60</xsl:variable> 
<xsl:variable name="update1day">300</xsl:variable>
for output: -->
<xsl:variable name="historyDefault">30</xsl:variable>  
<xsl:variable name="history1week">7</xsl:variable>
<xsl:variable name="trendsDefault">365</xsl:variable> 
<xsl:variable name="trends0days">0</xsl:variable>
<xsl:variable name="updateDefault">300</xsl:variable> 
<xsl:variable name="update1min">60</xsl:variable>
<xsl:variable name="update5min">300</xsl:variable>
<xsl:variable name="update1hour">3600</xsl:variable> 
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
			<CPU_LOAD_MAX>90</CPU_LOAD_MAX>
        </Performance>
        <Fault>
        	<TEMP_CRIT>60</TEMP_CRIT>
        	<TEMP_WARN>50</TEMP_WARN>        
        </Fault>
        <General>
        	<SNMP_TIMEOUT>600</SNMP_TIMEOUT>
        </General>
    </xsl:variable>


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
					        		<macro>{$<xsl:value-of select ="name(.)"/>}</macro>
					                <value><xsl:value-of select="."/></value>
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
							<template>
				        		<name>Template ICMP Ping</name>
							</template>	
					</xsl:when>
				</xsl:choose>
			</xsl:for-each>
    	
    	</templates>
      </xsl:copy>
      
      
</xsl:template>

<xsl:template match="macros"/><!-- leave it empty -->
<xsl:template match="template/templates"/><!-- leave it empty -->
 
<xsl:template match="template/metrics/cpuLoad">
	<xsl:copy>
		<name lang="EN">CPU Load</name>
		<name lang="RU">Загрузка процессора</name>
		<group>CPU</group>
		<xsl:copy-of select="oid"></xsl:copy-of>
		<xsl:copy-of select="snmpObject"></xsl:copy-of>
		<xsl:copy-of select="mib"></xsl:copy-of>
		<xsl:copy-of select="./expressionFormula"></xsl:copy-of>
		<xsl:copy-of select="ref"></xsl:copy-of>
		<xsl:copy-of select="vendorDescription"></xsl:copy-of>
		<description>CPU load in %</description>
		<history><xsl:copy-of select="$historyDefault"/></history>
		<trends><xsl:copy-of select="$trendsDefault"/></trends>
		<units>%</units>
		<update><xsl:copy-of select="$updateDefault"/></update>
		<valueType><xsl:copy-of select="$valueType"/></valueType>
		<valueMap><xsl:value-of select="valueMap"/></valueMap>
		<multiplier><xsl:value-of select="multiplier"/></multiplier>
		<xsl:copy-of select="./discoveryRule"></xsl:copy-of>
		<triggers>
			<trigger>
				<expression>{<xsl:value-of select="../../name"></xsl:value-of>:<xsl:value-of select="snmpObject"></xsl:value-of>.avg(300)}>{$CPU_LOAD_MAX}</expression>
                <name lang="EN">CPU load is too high</name>
                <name lang="RU">Загрузка ЦПУ слишком велика</name>
                <url/>
                <priority>3</priority>
                <description/>
			</trigger>
		</triggers>
	</xsl:copy>
</xsl:template>


<xsl:template match="template/metrics/cpuUtil">
	<xsl:copy>
		<name>CPU Util</name>
		<group>CPU</group>
		<xsl:copy-of select="oid"></xsl:copy-of>
		<xsl:copy-of select="snmpObject"></xsl:copy-of>
		<xsl:copy-of select="mib"></xsl:copy-of>
		<xsl:copy-of select="./expressionFormula"></xsl:copy-of>
		<xsl:copy-of select="ref"></xsl:copy-of>
		<xsl:copy-of select="vendorDescription"></xsl:copy-of>
		<history><xsl:copy-of select="$historyDefault"/></history>
		<trends><xsl:copy-of select="$trendsDefault"/></trends>
		<units></units>
		<update><xsl:copy-of select="$updateDefault"/></update>
		<valueType><xsl:copy-of select="$valueType"/></valueType>
		<valueMap><xsl:value-of select="valueMap"/></valueMap>
		<multiplier><xsl:value-of select="multiplier"/></multiplier>
		<xsl:copy-of select="./discoveryRule"></xsl:copy-of>
	</xsl:copy>
</xsl:template>


<!-- memory -->
<xsl:template match="template/metrics/memoryUnits">
	<xsl:copy>
		<name>Memory units</name>
		<group>Internal Items</group>
		<xsl:copy-of select="oid"></xsl:copy-of>
		<xsl:copy-of select="snmpObject"></xsl:copy-of>
		<xsl:copy-of select="mib"></xsl:copy-of>
		<xsl:copy-of select="./expressionFormula"></xsl:copy-of>
		<xsl:copy-of select="ref"></xsl:copy-of>
		<xsl:copy-of select="vendorDescription"></xsl:copy-of>
		<description/>
		<history><xsl:copy-of select="$historyDefault"/></history>
		<trends><xsl:copy-of select="$trendsDefault"/></trends>
		<update><xsl:copy-of select="$updateDefault"/></update>
		<valueType><xsl:copy-of select="$valueType"/></valueType>
		<valueMap><xsl:value-of select="valueMap"/></valueMap>
		<multiplier><xsl:value-of select="multiplier"/></multiplier>
		<xsl:copy-of select="./discoveryRule"></xsl:copy-of>
	</xsl:copy>
</xsl:template>

<xsl:template match="template/metrics/memoryUnitsUsed">
	<xsl:copy>
		<name>Used memory in units</name>
		<group>Internal Items</group>
		<xsl:copy-of select="oid"></xsl:copy-of>
		<xsl:copy-of select="snmpObject"></xsl:copy-of>
		<xsl:copy-of select="mib"></xsl:copy-of>
		<xsl:copy-of select="./expressionFormula"></xsl:copy-of>
		<xsl:copy-of select="ref"></xsl:copy-of>
		<xsl:copy-of select="vendorDescription"></xsl:copy-of>
		<description>Used memory in units</description>
		<history><xsl:copy-of select="$historyDefault"/></history>
		<trends><xsl:copy-of select="$trendsDefault"/></trends>
		<units>units</units>
		<update><xsl:copy-of select="$updateDefault"/></update>
		<valueType><xsl:copy-of select="$valueType"/></valueType>
		<valueMap><xsl:value-of select="valueMap"/></valueMap>
		<multiplier><xsl:value-of select="multiplier"/></multiplier>
		<xsl:copy-of select="./discoveryRule"></xsl:copy-of>
	</xsl:copy>
</xsl:template>

<xsl:template match="template/metrics/memoryUnitsTotal">
	<xsl:copy>
		<name>Total memory in units</name>
		<group>Internal Items</group>
		<xsl:copy-of select="oid"></xsl:copy-of>
		<xsl:copy-of select="snmpObject"></xsl:copy-of>
		<xsl:copy-of select="mib"></xsl:copy-of>
		<xsl:copy-of select="./expressionFormula"></xsl:copy-of>
		<xsl:copy-of select="ref"></xsl:copy-of>
		<xsl:copy-of select="vendorDescription"></xsl:copy-of>
		<description>Total memory in units</description>
		<history><xsl:copy-of select="$historyDefault"/></history>
		<trends><xsl:copy-of select="$trendsDefault"/></trends>
		<units>units</units>
		<update><xsl:copy-of select="$updateDefault"/></update>
		<valueType><xsl:copy-of select="$valueType"/></valueType>
		<valueMap><xsl:value-of select="valueMap"/></valueMap>
		<multiplier><xsl:value-of select="multiplier"/></multiplier>
		<xsl:copy-of select="./discoveryRule"></xsl:copy-of>
	</xsl:copy>
</xsl:template>


<xsl:template match="template/metrics/memoryUsed">
	<xsl:copy>
		<name>Used memory</name>
		<group>Memory</group>
		<xsl:copy-of select="oid"></xsl:copy-of>
		<xsl:copy-of select="snmpObject"></xsl:copy-of>
		<xsl:copy-of select="mib"></xsl:copy-of>
		<xsl:choose>
			<xsl:when test="./calculated = 'true'">
					<xsl:choose>
						<xsl:when test="../memoryUnitsUsed and  ../memoryUnits">
							<expressionFormula>(last(<xsl:value-of select="../memoryUnitsUsed/snmpObject"/>)*last(<xsl:value-of select="../memoryUnits/snmpObject"/>))</expressionFormula>
						</xsl:when>
					</xsl:choose>				
			</xsl:when>
		</xsl:choose>
		<xsl:copy-of select="ref"></xsl:copy-of>
		<xsl:copy-of select="vendorDescription"></xsl:copy-of>
		<description>Used memory in Bytes</description>
		<history><xsl:copy-of select="$historyDefault"/></history>
		<trends><xsl:copy-of select="$trendsDefault"/></trends>
		<units>B</units>
		<update><xsl:copy-of select="$updateDefault"/></update>
		<valueType><xsl:copy-of select="$valueType"/></valueType>
		<valueMap><xsl:value-of select="valueMap"/></valueMap>
		<multiplier><xsl:value-of select="multiplier"/></multiplier>
		<xsl:copy-of select="./discoveryRule"></xsl:copy-of>
	</xsl:copy>
</xsl:template>

<xsl:template match="template/metrics/memoryFree">
	<xsl:copy>
		<name>Free memory</name>
		<group>Memory</group>
		<xsl:copy-of select="oid"></xsl:copy-of>
		<xsl:copy-of select="snmpObject"></xsl:copy-of>
		<xsl:copy-of select="mib"></xsl:copy-of>
		<xsl:copy-of select="./expressionFormula"></xsl:copy-of>
		<xsl:copy-of select="ref"></xsl:copy-of>
		<xsl:copy-of select="vendorDescription"></xsl:copy-of>
		<history><xsl:copy-of select="$historyDefault"/></history>
		<trends><xsl:copy-of select="$trendsDefault"/></trends>
		<units>B</units>
		<update><xsl:copy-of select="$updateDefault"/></update>
		<valueType><xsl:copy-of select="$valueType"/></valueType>
		<valueMap><xsl:value-of select="valueMap"/></valueMap>
		<multiplier><xsl:value-of select="multiplier"/></multiplier>
		<xsl:copy-of select="./discoveryRule"></xsl:copy-of>
	</xsl:copy>
</xsl:template>



<xsl:template match="template/metrics/memoryTotal">
	<xsl:copy>
		<name>Total memory</name>
		<group>Memory</group>
		<xsl:copy-of select="oid"></xsl:copy-of>
		<xsl:copy-of select="snmpObject"></xsl:copy-of>
		<xsl:copy-of select="mib"></xsl:copy-of>
		<xsl:choose>
			<xsl:when test="./calculated = 'true'">
					<xsl:choose>
						<xsl:when test="../memoryUnitsTotal and  ../memoryUnits">
							<expressionFormula>(last(<xsl:value-of select="../memoryUnitsTotal/snmpObject"/>)*last(<xsl:value-of select="../memoryUnits/snmpObject"/>))</expressionFormula>
						</xsl:when>
					</xsl:choose>				
			</xsl:when>
		</xsl:choose>
		<xsl:copy-of select="ref"></xsl:copy-of>
		<xsl:copy-of select="vendorDescription"></xsl:copy-of>
		<description>Total memory in Bytes</description>
		<history><xsl:copy-of select="$historyDefault"/></history>
		<trends><xsl:copy-of select="$trendsDefault"/></trends>
		<units>B</units>
		<update><xsl:copy-of select="$updateDefault"/></update>
		<valueType><xsl:copy-of select="$valueType"/></valueType>
		<valueMap><xsl:value-of select="valueMap"/></valueMap>
		<multiplier><xsl:value-of select="multiplier"/></multiplier>
		<xsl:copy-of select="./discoveryRule"></xsl:copy-of>
	</xsl:copy>
</xsl:template>


<xsl:template match="template/metrics/memoryUsedPercentage">
	<xsl:copy>
		<name>Memory utilization</name>
		<group>Memory</group>
		<xsl:copy-of select="oid"></xsl:copy-of>
		<xsl:copy-of select="snmpObject"></xsl:copy-of>
		<xsl:copy-of select="mib"></xsl:copy-of>
		<xsl:choose>
			<xsl:when test="./calculated = 'true'">
					<xsl:choose>
						<xsl:when test="../memoryUnitsTotal and  ../memoryUnitsUsed">
							<expressionFormula>(last(<xsl:value-of select="../memoryUnitsUsed/snmpObject"/>)/last(<xsl:value-of select="../memoryUnitsTotal/snmpObject"/>))*100</expressionFormula>
						</xsl:when>
						<xsl:when test="../memoryTotal and  ../memoryUsed">
							<expressionFormula>(last(<xsl:value-of select="../memoryUsed/snmpObject"/>)/last(<xsl:value-of select="../memoryTotal/snmpObject"/>))*100</expressionFormula>
						</xsl:when>
						<xsl:when test="../memoryTotal and  ../memoryFree">
							<expressionFormula>((last(<xsl:value-of select="../memoryTotal/snmpObject"/>)-last(<xsl:value-of select="../memoryFree/snmpObject"/>))/last(<xsl:value-of select="../memoryTotal/snmpObject"/>))*100</expressionFormula>
						</xsl:when>
						<xsl:otherwise>
							<expressionFormula>(last(<xsl:value-of select="../memoryUsed/snmpObject"/>)/(last(<xsl:value-of select="../memoryFree/snmpObject"/>)+last(<xsl:value-of select="../memoryUsed/snmpObject"/>)))*100</expressionFormula>
						</xsl:otherwise>
					</xsl:choose>				
			</xsl:when>
		</xsl:choose>
		<xsl:copy-of select="ref"></xsl:copy-of>
		<xsl:copy-of select="vendorDescription"></xsl:copy-of>
		<description>Memory utilization in %</description>
		<history><xsl:copy-of select="$historyDefault"/></history>
		<trends><xsl:copy-of select="$trendsDefault"/></trends>
		<units>%</units>
		<update><xsl:copy-of select="$updateDefault"/></update>
		<valueType><xsl:copy-of select="$valueTypeFloat"/></valueType>
		<valueMap><xsl:value-of select="valueMap"/></valueMap>
		<multiplier><xsl:value-of select="multiplier"/></multiplier>
		<xsl:copy-of select="./discoveryRule"></xsl:copy-of>
		<triggers>
			<trigger>
				<expression>{<xsl:value-of select="../../name"></xsl:value-of>:<xsl:value-of select="snmpObject"></xsl:value-of>.avg(300)}>90</expression>
                <name lang="EN">Memory utilization is too high</name>
                <name lang="RU">Мало свободной памяти ОЗУ</name>
                <url/>
                <priority>3</priority>
                <description/>
			</trigger>
		</triggers>
	</xsl:copy>
</xsl:template>


<!-- storage(same as memory) -->

<xsl:template match="template/metrics/storageUnits">
	<xsl:copy>
		<name>[<xsl:value-of select="metricLocation"/>] Storage units</name>
		<group>Internal Items</group>
		<xsl:copy-of select="oid"></xsl:copy-of>
		<xsl:copy-of select="snmpObject"></xsl:copy-of>
		<xsl:copy-of select="mib"></xsl:copy-of>
		<xsl:copy-of select="./expressionFormula"></xsl:copy-of>
		<xsl:copy-of select="ref"></xsl:copy-of>
		<xsl:copy-of select="vendorDescription"></xsl:copy-of>
		<description/>
		<history><xsl:copy-of select="$historyDefault"/></history>
		<trends><xsl:copy-of select="$trendsDefault"/></trends>
		<update><xsl:copy-of select="$updateDefault"/></update>
		<valueType><xsl:copy-of select="$valueType"/></valueType>
		<valueMap><xsl:value-of select="valueMap"/></valueMap>
		<multiplier><xsl:value-of select="multiplier"/></multiplier>
		<xsl:copy-of select="./discoveryRule"></xsl:copy-of>
	</xsl:copy>
</xsl:template>

<xsl:template match="template/metrics/storageUnitsUsed">
	<xsl:copy>
		<name>[<xsl:value-of select="metricLocation"/>] Used storage in units</name>
		<group>Internal Items</group>
		<xsl:copy-of select="oid"></xsl:copy-of>
		<xsl:copy-of select="snmpObject"></xsl:copy-of>
		<xsl:copy-of select="mib"></xsl:copy-of>
		<xsl:copy-of select="./expressionFormula"></xsl:copy-of>
		<xsl:copy-of select="ref"></xsl:copy-of>
		<xsl:copy-of select="vendorDescription"></xsl:copy-of>
		<description>Used storage in units</description>
		<history><xsl:copy-of select="$historyDefault"/></history>
		<trends><xsl:copy-of select="$trendsDefault"/></trends>
		<units>units</units>
		<update><xsl:copy-of select="$updateDefault"/></update>
		<valueType><xsl:copy-of select="$valueType"/></valueType>
		<valueMap><xsl:value-of select="valueMap"/></valueMap>
		<multiplier><xsl:value-of select="multiplier"/></multiplier>
		<xsl:copy-of select="./discoveryRule"></xsl:copy-of>
	</xsl:copy>
</xsl:template>

<xsl:template match="template/metrics/storageUnitsTotal">
	<xsl:copy>
		<name>[<xsl:value-of select="metricLocation"/>] Total storage in units</name>
		<group>Internal Items</group>
		<xsl:copy-of select="oid"></xsl:copy-of>
		<xsl:copy-of select="snmpObject"></xsl:copy-of>
		<xsl:copy-of select="mib"></xsl:copy-of>
		<xsl:copy-of select="./expressionFormula"></xsl:copy-of>
		<xsl:copy-of select="ref"></xsl:copy-of>
		<xsl:copy-of select="vendorDescription"></xsl:copy-of>
		<description>Total storage in units</description>
		<history><xsl:copy-of select="$historyDefault"/></history>
		<trends><xsl:copy-of select="$trendsDefault"/></trends>
		<units>units</units>
		<update><xsl:copy-of select="$updateDefault"/></update>
		<valueType><xsl:copy-of select="$valueType"/></valueType>
		<valueMap><xsl:value-of select="valueMap"/></valueMap>
		<multiplier><xsl:value-of select="multiplier"/></multiplier>
		<xsl:copy-of select="./discoveryRule"></xsl:copy-of>
	</xsl:copy>
</xsl:template>


<xsl:template match="template/metrics/storageUsed">
	<xsl:copy>
		<name>[<xsl:value-of select="metricLocation"/>] Used space</name>
		<group>Storage</group>
		<xsl:copy-of select="oid"></xsl:copy-of>
		<xsl:copy-of select="snmpObject"></xsl:copy-of>
		<xsl:copy-of select="mib"></xsl:copy-of>
		<xsl:choose>
			<xsl:when test="./calculated = 'true'">
					<xsl:choose>
						<xsl:when test="../storageUnitsUsed and  ../storageUnits">
							<expressionFormula>(last(<xsl:value-of select="../storageUnitsUsed/snmpObject"/>)*last(<xsl:value-of select="../storageUnits/snmpObject"/>))</expressionFormula>
						</xsl:when>
					</xsl:choose>				
			</xsl:when>
		</xsl:choose>
		<xsl:copy-of select="ref"></xsl:copy-of>
		<xsl:copy-of select="vendorDescription"></xsl:copy-of>
		<description>Used storage in Bytes</description>
		<history><xsl:copy-of select="$historyDefault"/></history>
		<trends><xsl:copy-of select="$trendsDefault"/></trends>
		<units>B</units>
		<update><xsl:copy-of select="$updateDefault"/></update>
		<valueType><xsl:copy-of select="$valueType"/></valueType>
		<valueMap><xsl:value-of select="valueMap"/></valueMap>
		<multiplier><xsl:value-of select="multiplier"/></multiplier>
		<xsl:copy-of select="./discoveryRule"></xsl:copy-of>
	</xsl:copy>
</xsl:template>

<xsl:template match="template/metrics/storageFree">
	<xsl:copy>
		<name>[<xsl:value-of select="metricLocation"/>] Free space</name>
		<group>Storage</group>
		<xsl:copy-of select="oid"></xsl:copy-of>
		<xsl:copy-of select="snmpObject"></xsl:copy-of>
		<xsl:copy-of select="mib"></xsl:copy-of>
		<xsl:copy-of select="./expressionFormula"></xsl:copy-of>
		<xsl:copy-of select="ref"></xsl:copy-of>
		<xsl:copy-of select="vendorDescription"></xsl:copy-of>
		<history><xsl:copy-of select="$historyDefault"/></history>
		<trends><xsl:copy-of select="$trendsDefault"/></trends>
		<units>B</units>
		<update><xsl:copy-of select="$updateDefault"/></update>
		<valueType><xsl:copy-of select="$valueType"/></valueType>
		<valueMap><xsl:value-of select="valueMap"/></valueMap>
		<multiplier><xsl:value-of select="multiplier"/></multiplier>
		<xsl:copy-of select="./discoveryRule"></xsl:copy-of>
	</xsl:copy>
</xsl:template>



<xsl:template match="template/metrics/storageTotal">
	<xsl:copy>
		<name>[<xsl:value-of select="metricLocation"/>] Total storage</name>
		<group>Storage</group>
		<xsl:copy-of select="oid"></xsl:copy-of>
		<xsl:copy-of select="snmpObject"></xsl:copy-of>
		<xsl:copy-of select="mib"></xsl:copy-of>
		<xsl:choose>
			<xsl:when test="./calculated = 'true'">
					<xsl:choose>
						<xsl:when test="../storageUnitsTotal and  ../storageUnits">
							<expressionFormula>(last(<xsl:value-of select="../storageUnitsTotal/snmpObject"/>)*last(<xsl:value-of select="../storageUnits/snmpObject"/>))</expressionFormula>
						</xsl:when>
					</xsl:choose>				
			</xsl:when>
		</xsl:choose>
		<xsl:copy-of select="ref"></xsl:copy-of>
		<xsl:copy-of select="vendorDescription"></xsl:copy-of>
		<description>Total storage in Bytes</description>
		<history><xsl:copy-of select="$historyDefault"/></history>
		<trends><xsl:copy-of select="$trendsDefault"/></trends>
		<units>B</units>
		<update><xsl:copy-of select="$updateDefault"/></update>
		<valueType><xsl:copy-of select="$valueType"/></valueType>
		<valueMap><xsl:value-of select="valueMap"/></valueMap>
		<multiplier><xsl:value-of select="multiplier"/></multiplier>
		<xsl:copy-of select="./discoveryRule"></xsl:copy-of>
	</xsl:copy>
</xsl:template>


<xsl:template match="template/metrics/storageUsedPercentage">
	<xsl:copy>
		<name>[<xsl:value-of select="metricLocation"/>] Storage utilization</name>
		<group>Storage</group>
		<xsl:copy-of select="oid"></xsl:copy-of>
		<xsl:copy-of select="snmpObject"></xsl:copy-of>
		<xsl:copy-of select="mib"></xsl:copy-of>
		<xsl:choose>
			<xsl:when test="./calculated = 'true'">
					<xsl:choose>
						<xsl:when test="../storageUnitsTotal and  ../storageUnitsUsed">
							<expressionFormula>(last(<xsl:value-of select="../storageUnitsUsed/snmpObject"/>)/last(<xsl:value-of select="../storageUnitsTotal/snmpObject"/>))*100</expressionFormula>
						</xsl:when>
						<xsl:when test="../storageTotal and  ../storageUsed">
							<expressionFormula>(last(<xsl:value-of select="../storageUsed/snmpObject"/>)/last(<xsl:value-of select="../storageTotal/snmpObject"/>))*100</expressionFormula>
						</xsl:when>
						<xsl:when test="../storageTotal and  ../storageFree">
							<expressionFormula>((last(<xsl:value-of select="../storageTotal/snmpObject"/>)-last(<xsl:value-of select="../storageFree/snmpObject"/>))/last(<xsl:value-of select="../storageTotal/snmpObject"/>))*100</expressionFormula>
						</xsl:when>
						<xsl:otherwise>
							<expressionFormula>(last(<xsl:value-of select="../storageUsed/snmpObject"/>)/(last(<xsl:value-of select="../storageFree/snmpObject"/>)+last(<xsl:value-of select="../storageUsed/snmpObject"/>)))*100</expressionFormula>
						</xsl:otherwise>
					</xsl:choose>				
			</xsl:when>
		</xsl:choose>
		<xsl:copy-of select="ref"></xsl:copy-of>
		<xsl:copy-of select="vendorDescription"></xsl:copy-of>
		<description>Storage utilization in % for <xsl:value-of select="metricLocation"/></description>
		<history><xsl:copy-of select="$historyDefault"/></history>
		<trends><xsl:copy-of select="$trendsDefault"/></trends>
		<units>%</units>
		<update><xsl:copy-of select="$updateDefault"/></update>
		<valueType><xsl:copy-of select="$valueTypeFloat"/></valueType>
		<valueMap><xsl:value-of select="valueMap"/></valueMap>
		<multiplier><xsl:value-of select="multiplier"/></multiplier>
		<xsl:copy-of select="./discoveryRule"></xsl:copy-of>
		<triggers>
			<trigger>
				<id>storageCrit</id>
				<expression>{<xsl:value-of select="../../name"></xsl:value-of>:<xsl:value-of select="snmpObject"></xsl:value-of>.avg(300)}>90</expression>
                <name lang="EN">Free disk space is less than 10% on <xsl:value-of select="metricLocation"/></name>
                <name lang="RU">Свободного места на <xsl:value-of select="metricLocation"/> меньше 10%</name>
                <url/>
                <priority>3</priority>
                <description/>
			</trigger>
			
			<trigger>
				<id>storageWarn</id>
				<expression>{<xsl:value-of select="../../name"></xsl:value-of>:<xsl:value-of select="snmpObject"></xsl:value-of>.avg(300)}>80</expression>
                <name lang="EN">Free disk space is less than 20% on <xsl:value-of select="metricLocation"/></name>
                <name lang="RU">Свободного места на <xsl:value-of select="metricLocation"/> меньше 20%</name>
                <url/>
                <priority>2</priority>
                <description/>
				<dependsOn>
                	<dependency>storageCrit</dependency>
               	</dependsOn>
			</trigger>
		</triggers>
	</xsl:copy>
</xsl:template>



<xsl:template match="template/metrics/temperatureValue">
	<xsl:copy>
		<name lang="EN">[<xsl:value-of select="metricLocation"/>] Temperature</name>
		<name lang="RU">[<xsl:value-of select="metricLocation"/>] Температура</name>
		<group>Temperature</group>
		<xsl:copy-of select="oid"></xsl:copy-of>
		<xsl:copy-of select="snmpObject"></xsl:copy-of>
		<xsl:copy-of select="mib"></xsl:copy-of>
<!-- <xsl:choose>
			<xsl:when test="./calculated = 'true'">
				<expressionFormula>last(<xsl:value-of select="../memoryUsed/snmpObject"/>)/(last(<xsl:value-of select="../memoryFree/snmpObject"/>)+last(<xsl:value-of select="../memoryUsed/snmpObject"/>))</expressionFormula>
			</xsl:when>
			<xsl:otherwise></xsl:otherwise>
		</xsl:choose>  -->
		<xsl:copy-of select="ref"></xsl:copy-of>
		<xsl:copy-of select="vendorDescription"></xsl:copy-of>
		<description>Temperature readings of testpoint: <xsl:value-of select="metricLocation"/></description>
		<history><xsl:copy-of select="$historyDefault"/></history>
		<trends><xsl:copy-of select="$trendsDefault"/></trends>
		<units>C</units>
		<update><xsl:copy-of select="$updateDefault"/></update>
		<valueType><xsl:copy-of select="$valueTypeFloat"/></valueType>
		<valueMap><xsl:value-of select="valueMap"/></valueMap>
		<multiplier><xsl:value-of select="multiplier"/></multiplier>
		<xsl:copy-of select="./discoveryRule"></xsl:copy-of>
		<triggers>
			<trigger>
			    <id>tempWarn</id>
				<expression>{<xsl:value-of select="../../name"></xsl:value-of>:<xsl:value-of select="snmpObject"></xsl:value-of>.avg(300)}>{$TEMP_WARN:"<xsl:value-of select="metricLocation"/>"}</expression>
                <name lang="EN"><xsl:value-of select="metricLocation"/> temperature is above warning threshold: >{$TEMP_WARN:"<xsl:value-of select="metricLocation"/>"}</name>
                <name lang="RU">[<xsl:value-of select="metricLocation"/>] Температура выше нормы: >{$TEMP_WARN:"<xsl:value-of select="metricLocation"/>"}</name>
                <url/>
                <priority>2</priority>
                <description/>
                <dependsOn>
                	<dependency>tempCrit</dependency>
               	</dependsOn>
			</trigger>
			<trigger>
				<id>tempCrit</id>
				<expression>{<xsl:value-of select="../../name"></xsl:value-of>:<xsl:value-of select="snmpObject"></xsl:value-of>.avg(300)}>{$TEMP_CRIT:"<xsl:value-of select="metricLocation"/>"}</expression>
                <name lang="EN"><xsl:value-of select="metricLocation"/> temperature is above critical threshold: >{$TEMP_CRIT:"<xsl:value-of select="metricLocation"/>"}</name>
                <name lang="RU">[<xsl:value-of select="metricLocation"/>]Температура очень высокая: >{$TEMP_CRIT:"<xsl:value-of select="metricLocation"/>"}</name>
                <url/>
                <priority>4</priority>
                <description/>
			</trigger>
		</triggers>
	</xsl:copy>
</xsl:template>


<xsl:template match="template/metrics/temperatureStatus">
	<xsl:copy>
		<name>[<xsl:value-of select="metricLocation"/>]Temperature status</name>
		<group>Temperature</group>
		<xsl:copy-of select="oid"></xsl:copy-of>
		<xsl:copy-of select="snmpObject"></xsl:copy-of>
		<xsl:copy-of select="mib"></xsl:copy-of>
<!-- <xsl:choose>
			<xsl:when test="./calculated = 'true'">
				<expressionFormula>last(<xsl:value-of select="../memoryUsed/snmpObject"/>)/(last(<xsl:value-of select="../memoryFree/snmpObject"/>)+last(<xsl:value-of select="../memoryUsed/snmpObject"/>))</expressionFormula>
			</xsl:when>
			<xsl:otherwise></xsl:otherwise>
		</xsl:choose>  -->
		<xsl:copy-of select="ref"></xsl:copy-of>
		<xsl:copy-of select="vendorDescription"></xsl:copy-of>
		<description>Temperature status of testpoint: <xsl:value-of select="metricLocation"/></description>
		<history><xsl:copy-of select="$historyDefault"/></history>
		<trends><xsl:copy-of select="$trendsDefault"/></trends>
		<units></units>
		<update><xsl:copy-of select="$updateDefault"/></update>
		<valueType><xsl:copy-of select="$valueType"/></valueType>
		<valueMap><xsl:value-of select="valueMap"/></valueMap>
		<multiplier><xsl:value-of select="multiplier"/></multiplier>
		<xsl:copy-of select="./discoveryRule"></xsl:copy-of>
	</xsl:copy>
</xsl:template>


<xsl:template match="template/metrics/temperatureLocale">
	<xsl:copy>
		<name>[<xsl:value-of select="metricLocation"/>]Temperature sensor location</name>
		<group>Temperature</group>
		<xsl:copy-of select="oid"></xsl:copy-of>
		<xsl:copy-of select="snmpObject"></xsl:copy-of>
		<xsl:copy-of select="mib"></xsl:copy-of>
<!-- <xsl:choose>
			<xsl:when test="./calculated = 'true'">
				<expressionFormula>last(<xsl:value-of select="../memoryUsed/snmpObject"/>)/(last(<xsl:value-of select="../memoryFree/snmpObject"/>)+last(<xsl:value-of select="../memoryUsed/snmpObject"/>))</expressionFormula>
			</xsl:when>
			<xsl:otherwise></xsl:otherwise>
		</xsl:choose>  -->
		<xsl:copy-of select="ref"></xsl:copy-of>
		<xsl:copy-of select="vendorDescription"></xsl:copy-of>
		<description>Temperature location of testpoint: <xsl:value-of select="metricLocation"/></description>
		<history><xsl:copy-of select="$history1week"/></history>
		<trends><xsl:copy-of select="$trends0days"/></trends>
		<units></units>
		<update><xsl:copy-of select="$update1hour"/></update>
		<valueType><xsl:copy-of select="$valueType"/></valueType>
		<valueMap><xsl:value-of select="valueMap"/></valueMap>
		<multiplier><xsl:value-of select="multiplier"/></multiplier>
		<xsl:copy-of select="./discoveryRule"></xsl:copy-of>
	</xsl:copy>
</xsl:template>


<!-- metric of hw servers fault -->
<xsl:template match="template/metrics/overallStatus">
	<xsl:copy>
		<name lang="EN">Overall system health status</name>
		<name lang="RU">Общий статус системы</name>
		<group>Status</group>
		<xsl:copy-of select="oid"></xsl:copy-of>
		<xsl:copy-of select="snmpObject"></xsl:copy-of>
		<xsl:copy-of select="mib"></xsl:copy-of>
		<!-- <xsl:choose>
			<xsl:when test="./calculated = 'true'">
				<expressionFormula>last(<xsl:value-of select="../memoryUsed/snmpObject"/>)/(last(<xsl:value-of select="../memoryFree/snmpObject"/>)+last(<xsl:value-of select="../memoryUsed/snmpObject"/>))</expressionFormula>
			</xsl:when>
			<xsl:otherwise></xsl:otherwise>
		</xsl:choose>  -->
		<xsl:copy-of select="ref"></xsl:copy-of>
		<xsl:copy-of select="vendorDescription"></xsl:copy-of>
		<description></description>
		<history><xsl:copy-of select="$historyDefault"/></history>
		<trends><xsl:copy-of select="$trendsDefault"/></trends>
		<units></units>
		<update><xsl:copy-of select="$updateDefault"/></update>
		<valueType><xsl:copy-of select="$valueType"/></valueType>
		<valueMap><xsl:value-of select="valueMap"/></valueMap>
		<multiplier><xsl:value-of select="multiplier"/></multiplier>
		<xsl:copy-of select="./discoveryRule"></xsl:copy-of>
		<triggers>
			<trigger>
			    <id>health.disaster</id>
				<expression>{<xsl:value-of select="../../name"></xsl:value-of>:<xsl:value-of select="snmpObject"></xsl:value-of>.last(0)}={$HEALTH_DISASTER_STATUS}</expression>
                <name lang="EN">System is in unrecoverable state!</name>
                <name lang="RU">Статус системы: сбой</name>
                <url/>
                <priority>5</priority>
                <description lang="EN">Please check the device for faults</description>
                <description lang="RU">Проверьте устройство</description>
			</trigger>
			<trigger>
			    <id>health.warning</id>
				<expression>{<xsl:value-of select="../../name"></xsl:value-of>:<xsl:value-of select="snmpObject"></xsl:value-of>.last(0)}={$HEALTH_WARN_STATUS}</expression>
                <name lang="EN">System status is in warning state</name>
                <name lang="RU">Статус системы: предупреждение</name>
                <url/>
                <priority>2</priority>
                <description lang="EN">Please check the device for warnings</description>
                <description lang="RU">Проверьте устройство</description>
                <dependsOn>
                	<dependency>health.critical</dependency>
               	</dependsOn>
			</trigger>
			<trigger>
				<id>health.critical</id>
				<expression>{<xsl:value-of select="../../name"></xsl:value-of>:<xsl:value-of select="snmpObject"></xsl:value-of>.last(0)}={$HEALTH_CRIT_STATUS}</expression>
                <name lang="EN">System status is in critical state</name>
                <name lang="RU">Статус системы: авария</name>
                <url/>
                <priority>4</priority>
                <description lang="EN">Please check the device for errors</description>
                <description lang="RU">Проверьте устройство</description>
                <dependsOn>
                	<dependency>health.disaster</dependency>
               	</dependsOn>
			</trigger>
		</triggers>
	</xsl:copy>
</xsl:template>




<xsl:template match="template/metrics/diskArrayStatus">
	<xsl:copy>
		<name lang="EN">[<xsl:value-of select="metricLocation"/>] Disk array controller status</name>
		<name lang="RU">[<xsl:value-of select="metricLocation"/>] Статус контроллера дискового массива</name>
		<group>Disk Arrays</group>
		<xsl:copy-of select="oid"></xsl:copy-of>
		<xsl:copy-of select="snmpObject"></xsl:copy-of>
		<xsl:copy-of select="mib"></xsl:copy-of>
		<!-- <xsl:choose>
			<xsl:when test="./calculated = 'true'">
				<expressionFormula>last(<xsl:value-of select="../memoryUsed/snmpObject"/>)/(last(<xsl:value-of select="../memoryFree/snmpObject"/>)+last(<xsl:value-of select="../memoryUsed/snmpObject"/>))</expressionFormula>
			</xsl:when>
			<xsl:otherwise></xsl:otherwise>
		</xsl:choose>  -->
		<xsl:copy-of select="ref"></xsl:copy-of>
		<xsl:copy-of select="vendorDescription"></xsl:copy-of>
		<description></description>
		<history><xsl:copy-of select="$historyDefault"/></history>
		<trends><xsl:copy-of select="$trendsDefault"/></trends>
		<units></units>
		<update><xsl:copy-of select="$updateDefault"/></update>
		<valueType><xsl:copy-of select="$valueType"/></valueType>
		<valueMap><xsl:value-of select="valueMap"/></valueMap>
		<multiplier><xsl:value-of select="multiplier"/></multiplier>
		<xsl:copy-of select="./discoveryRule"></xsl:copy-of>
		<triggers>
			<trigger>
			    <id>disk_array.disaster</id>
				<expression>{<xsl:value-of select="../../name"></xsl:value-of>:<xsl:value-of select="snmpObject"></xsl:value-of>.last(0)}={$DISK_ARRAY_DISASTER_STATUS}</expression>
                <name lang="EN">[<xsl:value-of select="metricLocation"/>] Disk array controller is in unrecoverable state!</name>
                <name lang="RU">[<xsl:value-of select="metricLocation"/>] Статус контроллера дискового массива: сбой</name>
                <url/>
                <priority>5</priority>
                <description lang="EN">Please check the device for faults</description>
                <description lang="RU">Проверьте устройство</description>
			</trigger>
			<trigger>
			    <id>disk_array.warning</id>
				<expression>{<xsl:value-of select="../../name"></xsl:value-of>:<xsl:value-of select="snmpObject"></xsl:value-of>.last(0)}={$DISK_ARRAY_WARN_STATUS}</expression>
                <name lang="EN">[<xsl:value-of select="metricLocation"/>] Disk array controller is in warning state</name>
                <name lang="RU">[<xsl:value-of select="metricLocation"/>] Статус контроллера дискового массива: предупреждение</name>
                <url/>
                <priority>2</priority>
                <description lang="EN">Please check the device for warnings</description>
                <description lang="RU">Проверьте устройство</description>
                <dependsOn>
                	<dependency>disk_array.critical</dependency>
               	</dependsOn>
			</trigger>
			<trigger>
				<id>disk_array.critical</id>
				<expression>{<xsl:value-of select="../../name"></xsl:value-of>:<xsl:value-of select="snmpObject"></xsl:value-of>.last(0)}={$DISK_ARRAY_CRIT_STATUS}</expression>
                <name lang="EN">[<xsl:value-of select="metricLocation"/>] Disk array controller is in critical state</name>
                <name lang="RU">[<xsl:value-of select="metricLocation"/>] Статус контроллера дискового массива: авария</name>
                <url/>
                <priority>4</priority>
                <description lang="EN">Please check the device for errors</description>
                <description lang="RU">Проверьте устройство</description>
                <dependsOn>
                	<dependency>disk_array.disaster</dependency>
               	</dependsOn>
			</trigger>
		</triggers>
	</xsl:copy>
</xsl:template>

<xsl:template match="template/metrics/diskArrayModel">
	<xsl:copy>
		<name lang="EN">[<xsl:value-of select="metricLocation"/>] Disk array controller model</name>
		<name lang="RU">[<xsl:value-of select="metricLocation"/>] Модель контроллера дискового массива</name>
		<group>Disk Arrays</group>
		<xsl:copy-of select="oid"></xsl:copy-of>
		<xsl:copy-of select="snmpObject"></xsl:copy-of>
		<xsl:copy-of select="mib"></xsl:copy-of>
		<!-- <xsl:choose>
			<xsl:when test="./calculated = 'true'">
				<expressionFormula>last(<xsl:value-of select="../memoryUsed/snmpObject"/>)/(last(<xsl:value-of select="../memoryFree/snmpObject"/>)+last(<xsl:value-of select="../memoryUsed/snmpObject"/>))</expressionFormula>
			</xsl:when>
			<xsl:otherwise></xsl:otherwise>
		</xsl:choose>  -->
		<xsl:copy-of select="ref"></xsl:copy-of>
		<xsl:copy-of select="vendorDescription"></xsl:copy-of>
		<description></description>
		<history><xsl:copy-of select="$historyDefault"/></history>
		<trends><xsl:copy-of select="$trendsDefault"/></trends>
		<units></units>
		<update><xsl:copy-of select="$updateDefault"/></update>
		<valueType><xsl:copy-of select="$valueType"/></valueType>
		<valueMap><xsl:value-of select="valueMap"/></valueMap>
		<multiplier><xsl:value-of select="multiplier"/></multiplier>
		<xsl:copy-of select="./discoveryRule"></xsl:copy-of>
	</xsl:copy>
</xsl:template>

<xsl:template match="template/metrics/physicalDiskStatus">
	<xsl:copy>
		<name lang="EN">[<xsl:value-of select="metricLocation"/>] Physical Disk Status</name>
		<name lang="RU">[<xsl:value-of select="metricLocation"/>] Статус физического диска</name>
		<group>Disks</group>
		<xsl:copy-of select="oid"></xsl:copy-of>
		<xsl:copy-of select="snmpObject"></xsl:copy-of>
		<xsl:copy-of select="mib"></xsl:copy-of>
		<!-- <xsl:choose>
			<xsl:when test="./calculated = 'true'">
				<expressionFormula>last(<xsl:value-of select="../memoryUsed/snmpObject"/>)/(last(<xsl:value-of select="../memoryFree/snmpObject"/>)+last(<xsl:value-of select="../memoryUsed/snmpObject"/>))</expressionFormula>
			</xsl:when>
			<xsl:otherwise></xsl:otherwise>
		</xsl:choose>  -->
		<xsl:copy-of select="ref"></xsl:copy-of>
		<xsl:copy-of select="vendorDescription"></xsl:copy-of>
		<description></description>
		<history><xsl:copy-of select="$historyDefault"/></history>
		<trends><xsl:copy-of select="$trendsDefault"/></trends>
		<units></units>
		<update><xsl:copy-of select="$updateDefault"/></update>
		<valueType><xsl:copy-of select="$valueTypeChar"/></valueType>
		<valueMap><xsl:value-of select="valueMap"/></valueMap>
		<multiplier><xsl:value-of select="multiplier"/></multiplier>
		<xsl:copy-of select="./discoveryRule"></xsl:copy-of>
		<triggers>
			<trigger>
			    <id>disk.notok</id>
				<expression>{<xsl:value-of select="../../name"></xsl:value-of>:<xsl:value-of select="snmpObject"></xsl:value-of>.str({$DISK_OK_STATUS})}=0 and 
				{<xsl:value-of select="../../name"></xsl:value-of>:<xsl:value-of select="snmpObject"></xsl:value-of>.str("")}=0</expression>
                <name lang="EN">[<xsl:value-of select="metricLocation"/>] Physical disk is not in OK state</name>
                <name lang="RU">[<xsl:value-of select="metricLocation"/>] Статус физического диска не норма</name>
                <url/>
                <priority>2</priority>
                <description lang="EN">Please check physical disk for warnings or errors</description>
                <description lang="RU">Проверьте диск</description>
                <dependsOn>
                	<dependency>disk.fail</dependency>
                	<dependency>disk.warning</dependency>
               	</dependsOn>
			</trigger>

			<trigger>
			    <id>disk.warning</id>
				<expression>{<xsl:value-of select="../../name"></xsl:value-of>:<xsl:value-of select="snmpObject"></xsl:value-of>.last(0)}={$DISK_WARN_STATUS}</expression>
                <name lang="EN">[<xsl:value-of select="metricLocation"/>] Physical disk is in warning state</name>
                <name lang="RU">[<xsl:value-of select="metricLocation"/>] Статус физического диска: предупреждение</name>
                <url/>
                <priority>2</priority>
                <description lang="EN">Please check physical disk for warnings or errors</description>
                <description lang="RU">Проверьте диск</description><dependsOn>
                	<dependency>disk.fail</dependency>
               	</dependsOn>
			</trigger>
			<trigger>
				<id>disk.fail</id>
				<expression>{<xsl:value-of select="../../name"></xsl:value-of>:<xsl:value-of select="snmpObject"></xsl:value-of>.last(0)}={$DISK_FAIL_STATUS}</expression>
                <name lang="EN">[<xsl:value-of select="metricLocation"/>] Physical disk failed</name>
                <name lang="RU">[<xsl:value-of select="metricLocation"/>] Статус физического диска: сбой</name>
                <url/>
                <priority>4</priority>
				<description lang="EN">Please check physical disk for warnings or errors</description>
                <description lang="RU">Проверьте диск</description>                
            </trigger>
		</triggers>
	</xsl:copy>
</xsl:template>


<xsl:template match="template/metrics/physicalDiskSerialNumber">
	<xsl:copy>
		<name lang="EN">[<xsl:value-of select="metricLocation"/>] Physical Disk Serial Number</name>
		<name lang="RU">[<xsl:value-of select="metricLocation"/>] Серийный номер физического диска</name>
		<group>Disks</group>
		<xsl:copy-of select="oid"></xsl:copy-of>
		<xsl:copy-of select="snmpObject"></xsl:copy-of>
		<xsl:copy-of select="mib"></xsl:copy-of>
		<!-- <xsl:choose>
			<xsl:when test="./calculated = 'true'">
				<expressionFormula>last(<xsl:value-of select="../memoryUsed/snmpObject"/>)/(last(<xsl:value-of select="../memoryFree/snmpObject"/>)+last(<xsl:value-of select="../memoryUsed/snmpObject"/>))</expressionFormula>
			</xsl:when>
			<xsl:otherwise></xsl:otherwise>
		</xsl:choose>  -->
		<xsl:copy-of select="ref"></xsl:copy-of>
		<xsl:copy-of select="vendorDescription"></xsl:copy-of>
		<description></description>
		<history><xsl:copy-of select="$historyDefault"/></history>
		<trends><xsl:copy-of select="$trendsDefault"/></trends>
		<units></units>
		<update><xsl:copy-of select="$update1day"/></update>
		<valueType><xsl:copy-of select="$valueTypeChar"/></valueType>
		<valueMap><xsl:value-of select="valueMap"/></valueMap>
		<multiplier><xsl:value-of select="multiplier"/></multiplier>
		<xsl:copy-of select="./discoveryRule"></xsl:copy-of>
	</xsl:copy>
</xsl:template>


<!-- generic template metrics -->


<xsl:template match="template/metrics/sysUptime">
	<xsl:copy>
		<name>Device uptime</name>
		<group>General</group>
		<xsl:copy-of select="oid"></xsl:copy-of>
		<xsl:copy-of select="snmpObject"></xsl:copy-of>
		<xsl:copy-of select="mib"></xsl:copy-of>
		<xsl:copy-of select="ref"></xsl:copy-of>
		<xsl:copy-of select="vendorDescription"></xsl:copy-of>
		<description>The time since the network management portion of the system was last re-initialized.<xsl:value-of select="metricLocation"/></description>
		<history><xsl:copy-of select="$historyDefault"/></history>
		<trends><xsl:copy-of select="$trendsDefault"/></trends>
		<units>uptime</units>
		<update><xsl:copy-of select="$update1min"/></update>
		<valueType><xsl:copy-of select="$valueType"/></valueType>
		<valueMap><xsl:value-of select="valueMap"/></valueMap>
		<multiplier><xsl:value-of select="multiplier"/></multiplier>
		<xsl:copy-of select="./discoveryRule"></xsl:copy-of>
		<triggers>
			<trigger>
			    <id>uptime.restarted</id>
				<expression>{<xsl:value-of select="../../name"></xsl:value-of>:<xsl:value-of select="snmpObject"></xsl:value-of>.last(0)}&lt;600</expression>
                <name lang="EN"><xsl:value-of select="metricLocation"/> The {HOST.NAME} has just been  restarted</name>
                <name lang="RU"><xsl:value-of select="metricLocation"/>{HOST.NAME} был только что перезагружен</name>
                <url/>
                <priority>2</priority>
                <description lang="EN">The device uptime is less then 10 minutes</description>
                <description lang="RU">Аптайм устройства менее 10 минут</description>
                <dependsOn>
                	<dependency>uptime.nodata</dependency>
               	</dependsOn>
			</trigger>
			<trigger>
				<id>uptime.nodata</id>
				<expression>{<xsl:value-of select="../../name"></xsl:value-of>:<xsl:value-of select="snmpObject"></xsl:value-of>.nodata({$SNMP_TIMEOUT})}=1</expression>
                <name lang="EN"><xsl:value-of select="metricLocation"/> No SNMP data collection</name>
                <name lang="RU"><xsl:value-of select="metricLocation"/> Нет сбора данных по SNMP</name>
                <url/>
                <priority>2</priority>
                <description lang="EN">SNMP object sysUptime.0 is not available for polling. Please check device connectivity and SNMP settings.</description>
                <description lang="RU">Не удается опросить sysUptime.0. Проверьте доступность устройства и настройки SNMP.</description>
			</trigger>
		</triggers>
	</xsl:copy>
</xsl:template>


<xsl:template match="template/metrics/sysContact">
	<xsl:copy>
		<name>Device contact details</name>
		<group>General</group>
		<xsl:copy-of select="oid"></xsl:copy-of>
		<xsl:copy-of select="snmpObject"></xsl:copy-of>
		<xsl:copy-of select="mib"></xsl:copy-of>
<!-- <xsl:choose>
			<xsl:when test="./calculated = 'true'">
				<expressionFormula>last(<xsl:value-of select="../memoryUsed/snmpObject"/>)/(last(<xsl:value-of select="../memoryFree/snmpObject"/>)+last(<xsl:value-of select="../memoryUsed/snmpObject"/>))</expressionFormula>
			</xsl:when>
			<xsl:otherwise></xsl:otherwise>
		</xsl:choose>  -->
		<xsl:copy-of select="ref"></xsl:copy-of>
		<xsl:copy-of select="vendorDescription"></xsl:copy-of>
		<description>The textual identification of the contact person for this managed node, together with information on how to contact this person.  If no contact information is known, the value is the zero-length string.</description>
		<history><xsl:copy-of select="$history1week"/></history>
		<trends><xsl:copy-of select="$trends0days"/></trends>
		<units></units>
		<update><xsl:copy-of select="$update1hour"/></update>
		<valueType><xsl:copy-of select="$valueTypeText"/></valueType>
		<valueMap><xsl:value-of select="valueMap"/></valueMap>
		<multiplier><xsl:value-of select="multiplier"/></multiplier>
		<xsl:copy-of select="./discoveryRule"></xsl:copy-of>
		<inventory_link>23</inventory_link>
	</xsl:copy>
</xsl:template>


<xsl:template match="template/metrics/sysLocation">
	<xsl:copy>
		<name>Device location</name>
		<group>General</group>
		<xsl:copy-of select="oid"></xsl:copy-of>
		<xsl:copy-of select="snmpObject"></xsl:copy-of>
		<xsl:copy-of select="mib"></xsl:copy-of>
<!-- <xsl:choose>
			<xsl:when test="./calculated = 'true'">
				<expressionFormula>last(<xsl:value-of select="../memoryUsed/snmpObject"/>)/(last(<xsl:value-of select="../memoryFree/snmpObject"/>)+last(<xsl:value-of select="../memoryUsed/snmpObject"/>))</expressionFormula>
			</xsl:when>
			<xsl:otherwise></xsl:otherwise>
		</xsl:choose>  -->
		<xsl:copy-of select="ref"></xsl:copy-of>
		<xsl:copy-of select="vendorDescription"></xsl:copy-of>
		<description>The textual identification of the contact person for this managed node, together with information on how to contact this person.  If no contact information is known, the value is the zero-length string.</description>
		<history><xsl:copy-of select="$history1week"/></history>
		<trends><xsl:copy-of select="$trends0days"/></trends>
		<units></units>
		<update><xsl:copy-of select="$update1hour"/></update>
		<valueType><xsl:copy-of select="$valueTypeChar"/></valueType>
		<valueMap><xsl:value-of select="valueMap"/></valueMap>
		<multiplier><xsl:value-of select="multiplier"/></multiplier>
		<xsl:copy-of select="./discoveryRule"></xsl:copy-of>
		<inventory_link>24</inventory_link>
	</xsl:copy>
</xsl:template>
<xsl:template match="template/metrics/sysObjectID">
	<xsl:copy>
		<name>system ObjectID</name>
		<group>General</group>
		<xsl:copy-of select="oid"></xsl:copy-of>
		<xsl:copy-of select="snmpObject"></xsl:copy-of>
		<xsl:copy-of select="mib"></xsl:copy-of>
<!-- <xsl:choose>
			<xsl:when test="./calculated = 'true'">
				<expressionFormula>last(<xsl:value-of select="../memoryUsed/snmpObject"/>)/(last(<xsl:value-of select="../memoryFree/snmpObject"/>)+last(<xsl:value-of select="../memoryUsed/snmpObject"/>))</expressionFormula>
			</xsl:when>
			<xsl:otherwise></xsl:otherwise>
		</xsl:choose>  -->
		<xsl:copy-of select="ref"></xsl:copy-of>
		<xsl:copy-of select="vendorDescription"></xsl:copy-of>
		<history><xsl:copy-of select="$historyDefault"/></history>
		<trends><xsl:copy-of select="$trendsDefault"/></trends>
		<units></units>
		<update><xsl:copy-of select="$update1hour"/></update>
		<valueType><xsl:copy-of select="$valueTypeChar"/></valueType>
		<valueMap><xsl:value-of select="valueMap"/></valueMap>
		<multiplier><xsl:value-of select="multiplier"/></multiplier>
		<xsl:copy-of select="./discoveryRule"></xsl:copy-of>
	</xsl:copy>
</xsl:template>


<xsl:template match="template/metrics/sysName">
	<xsl:copy>
		<name>Device name</name>
		<group>General</group>
		<xsl:copy-of select="oid"></xsl:copy-of>
		<xsl:copy-of select="snmpObject"></xsl:copy-of>
		<xsl:copy-of select="mib"></xsl:copy-of>
<!-- <xsl:choose>
			<xsl:when test="./calculated = 'true'">
				<expressionFormula>last(<xsl:value-of select="../memoryUsed/snmpObject"/>)/(last(<xsl:value-of select="../memoryFree/snmpObject"/>)+last(<xsl:value-of select="../memoryUsed/snmpObject"/>))</expressionFormula>
			</xsl:when>
			<xsl:otherwise></xsl:otherwise>
		</xsl:choose>  -->
		<xsl:copy-of select="ref"></xsl:copy-of>
		<xsl:copy-of select="vendorDescription"></xsl:copy-of>
		<history><xsl:copy-of select="$history1week"/></history>
		<trends><xsl:copy-of select="$trends0days"/></trends>
		<units></units>
		<update><xsl:copy-of select="$update1hour"/></update>
		<valueType><xsl:copy-of select="$valueTypeChar"/></valueType>
		<valueMap><xsl:value-of select="valueMap"/></valueMap>
		<multiplier><xsl:value-of select="multiplier"/></multiplier>
		<xsl:copy-of select="./discoveryRule"></xsl:copy-of>
		<inventory_link>3</inventory_link>
	</xsl:copy>
</xsl:template>



<xsl:template match="template/metrics/sysDescr">
	<xsl:copy>
		<name>Device description</name>
		<group>General</group>
		<xsl:copy-of select="oid"></xsl:copy-of>
		<xsl:copy-of select="snmpObject"></xsl:copy-of>
		<xsl:copy-of select="mib"></xsl:copy-of>
<!-- <xsl:choose>
			<xsl:when test="./calculated = 'true'">
				<expressionFormula>last(<xsl:value-of select="../memoryUsed/snmpObject"/>)/(last(<xsl:value-of select="../memoryFree/snmpObject"/>)+last(<xsl:value-of select="../memoryUsed/snmpObject"/>))</expressionFormula>
			</xsl:when>
			<xsl:otherwise></xsl:otherwise>
		</xsl:choose>  -->
		<xsl:copy-of select="ref"></xsl:copy-of>
		<xsl:copy-of select="vendorDescription"></xsl:copy-of>
		<history><xsl:copy-of select="$history1week"/></history>
		<trends><xsl:copy-of select="$trends0days"/></trends>
		<units></units>
		<update><xsl:copy-of select="$update1hour"/></update>
		<valueType><xsl:copy-of select="$valueTypeChar"/></valueType>
		<valueMap><xsl:value-of select="valueMap"/></valueMap>
		<multiplier><xsl:value-of select="multiplier"/></multiplier>
		<xsl:copy-of select="./discoveryRule"></xsl:copy-of>
		<inventory_link>14</inventory_link>
	</xsl:copy>
</xsl:template>

<!-- inventory -->

<xsl:template match="template/metrics/osVersion">
	<xsl:copy>
		<name>OS</name>
		<group>Inventory</group>
		<xsl:copy-of select="oid"></xsl:copy-of>
		<xsl:copy-of select="snmpObject"></xsl:copy-of>
		<xsl:copy-of select="mib"></xsl:copy-of>
<!-- <xsl:choose>
			<xsl:when test="./calculated = 'true'">
				<expressionFormula>last(<xsl:value-of select="../memoryUsed/snmpObject"/>)/(last(<xsl:value-of select="../memoryFree/snmpObject"/>)+last(<xsl:value-of select="../memoryUsed/snmpObject"/>))</expressionFormula>
			</xsl:when>
			<xsl:otherwise></xsl:otherwise>
		</xsl:choose>  -->
		<xsl:copy-of select="ref"></xsl:copy-of>
		<xsl:copy-of select="vendorDescription"></xsl:copy-of>
		<history><xsl:copy-of select="$history1week"/></history>
		<trends><xsl:copy-of select="$trends0days"/></trends>
		<units></units>
		<update><xsl:copy-of select="$update1day"/></update>
		<valueType><xsl:copy-of select="$valueTypeChar"/></valueType>
		<valueMap><xsl:value-of select="valueTypeChar"/></valueMap>
		<multiplier><xsl:value-of select="multiplier"/></multiplier>
		<xsl:copy-of select="./discoveryRule"></xsl:copy-of>
		<inventory_link>5</inventory_link>
	</xsl:copy>
</xsl:template>


<xsl:template match="template/metrics/hwModel">
	<xsl:copy>
		<name lang="EN">Hardware model name</name>
		<name lang="RU">Модель</name>
		<group>Inventory</group>
		<xsl:copy-of select="oid"></xsl:copy-of>
		<xsl:copy-of select="snmpObject"></xsl:copy-of>
		<xsl:copy-of select="mib"></xsl:copy-of>
<!-- <xsl:choose>
			<xsl:when test="./calculated = 'true'">
				<expressionFormula>last(<xsl:value-of select="../memoryUsed/snmpObject"/>)/(last(<xsl:value-of select="../memoryFree/snmpObject"/>)+last(<xsl:value-of select="../memoryUsed/snmpObject"/>))</expressionFormula>
			</xsl:when>
			<xsl:otherwise></xsl:otherwise>
		</xsl:choose>  -->
		<xsl:copy-of select="ref"></xsl:copy-of>
		<xsl:copy-of select="vendorDescription"></xsl:copy-of>
		<history><xsl:copy-of select="$history1week"/></history>
		<trends><xsl:copy-of select="$trends0days"/></trends>
		<units></units>
		<update><xsl:copy-of select="$update1day"/></update>
		<valueType><xsl:copy-of select="$valueTypeChar"/></valueType>
		<valueMap><xsl:value-of select="valueTypeChar"/></valueMap>
		<multiplier><xsl:value-of select="multiplier"/></multiplier>
		<xsl:copy-of select="./discoveryRule"></xsl:copy-of>
		<inventory_link>29</inventory_link> <!-- model -->
	</xsl:copy>
</xsl:template>


<xsl:template match="template/metrics/hwSerialNumber">
	<xsl:copy>
		<name lang="EN">Hardware Serial Number</name>
		<name lang="RU">Серийный номер</name>
		<group>Inventory</group>
		<xsl:copy-of select="oid"></xsl:copy-of>
		<xsl:copy-of select="snmpObject"></xsl:copy-of>
		<xsl:copy-of select="mib"></xsl:copy-of>
<!-- <xsl:choose>
			<xsl:when test="./calculated = 'true'">
				<expressionFormula>last(<xsl:value-of select="../memoryUsed/snmpObject"/>)/(last(<xsl:value-of select="../memoryFree/snmpObject"/>)+last(<xsl:value-of select="../memoryUsed/snmpObject"/>))</expressionFormula>
			</xsl:when>
			<xsl:otherwise></xsl:otherwise>
		</xsl:choose>  -->
		<xsl:copy-of select="ref"></xsl:copy-of>
		<xsl:copy-of select="vendorDescription"></xsl:copy-of>
		<history><xsl:copy-of select="$history1week"/></history>
		<trends><xsl:copy-of select="$trends0days"/></trends>
		<units></units>
		<update><xsl:copy-of select="$update1day"/></update>
		<valueType><xsl:copy-of select="$valueTypeChar"/></valueType>
		<valueMap><xsl:value-of select="valueTypeChar"/></valueMap>
		<multiplier><xsl:value-of select="multiplier"/></multiplier>
		<xsl:copy-of select="./discoveryRule"></xsl:copy-of>
		<inventory_link>8</inventory_link> <!-- serial_noa-->
	</xsl:copy>
</xsl:template>

<xsl:template match="template/metrics/hwFirmwareVersion">
	<xsl:copy>
		<name lang="EN">Firmware version</name>
		<name lang="RU">Версия прошивки</name>
		<group>Inventory</group>
		<xsl:copy-of select="oid"></xsl:copy-of>
		<xsl:copy-of select="snmpObject"></xsl:copy-of>
		<xsl:copy-of select="mib"></xsl:copy-of>
<!-- <xsl:choose>
			<xsl:when test="./calculated = 'true'">
				<expressionFormula>last(<xsl:value-of select="../memoryUsed/snmpObject"/>)/(last(<xsl:value-of select="../memoryFree/snmpObject"/>)+last(<xsl:value-of select="../memoryUsed/snmpObject"/>))</expressionFormula>
			</xsl:when>
			<xsl:otherwise></xsl:otherwise>
		</xsl:choose>  -->
		<xsl:copy-of select="ref"></xsl:copy-of>
		<xsl:copy-of select="vendorDescription"></xsl:copy-of>
		<history><xsl:copy-of select="$history1week"/></history>
		<trends><xsl:copy-of select="$trends0days"/></trends>
		<units></units>
		<update><xsl:copy-of select="$update1day"/></update>
		<valueType><xsl:copy-of select="$valueTypeChar"/></valueType>
		<valueMap><xsl:value-of select="valueTypeChar"/></valueMap>
		<multiplier><xsl:value-of select="multiplier"/></multiplier>
		<xsl:copy-of select="./discoveryRule"></xsl:copy-of>
	</xsl:copy>
</xsl:template>

</xsl:stylesheet>


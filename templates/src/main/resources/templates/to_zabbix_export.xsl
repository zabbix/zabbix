<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="2.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">

<xsl:output method="xml" indent="yes"/>

<xsl:variable name="community">{$SNMP_COMMUNITY}</xsl:variable>
<xsl:param name="snmp_item_type" select="4"/>
<xsl:param name="zbx_ver" select="3.2"/>
<xsl:variable name="snmp_port">161</xsl:variable>
<xsl:param name="discoveryDelay">3600</xsl:param>

 <xsl:variable name="step_map"> <!-- preprocessing step types, replace with zabbix ints -->
   <entry key="regex">5</entry>
   <entry key="multiplier">1</entry>
   <entry key="delta_per_second">10</entry> <!-- delta per second -->
 </xsl:variable>


 <xsl:variable name="item_type"> <!-- zabbix item types, replace with zabbix ints -->
   <entry key="snmp"><xsl:value-of select="$snmp_item_type"/></entry> <!-- 1 or 4 -->
   <entry key="internal">5</entry>
   <entry key="calculated">15</entry>
   <entry key="snmptrap">17</entry>
   <entry key="simple">3</entry>
   <entry key="zabbix_agent">0</entry>
   <entry key="zabbix_agent_active">7</entry>
 </xsl:variable>
 <!-- 
	0 - Zabbix agent; 
	1 - SNMPv1 agent; 
	2 - Zabbix trapper; 
	3 - simple check; 
	4 - SNMPv2 agent; 
	5 - Zabbix internal; 
	6 - SNMPv3 agent; 
	7 - Zabbix agent (active); 
	8 - Zabbix aggregate; 
	9 - web item; 
	10 - external check; 
	11 - database monitor; 
	12 - IPMI agent; 
	13 - SSH agent; 
	14 - TELNET agent; 
	15 - calculated; 
	16 - JMX agent; 
	17 - SNMP trap.  
 -->
<xsl:template match="/">
	<zabbix_export>
	    <version><xsl:value-of select="$zbx_ver"></xsl:value-of></version>
	    <date>2015-12-30T14:41:30Z</date>
	    <groups>
	        <group>
	            <name>Templates</name>
	        </group>
	    </groups>
		<templates>
				 <xsl:apply-templates select="child::*/template"></xsl:apply-templates>  
		</templates>
		<graphs>
			<xsl:copy-of copy-namespaces="no" select="child::*/*/metrics/*[not (discoveryRule)]/graphs/graph"/>
		</graphs>
		<triggers>
				<xsl:apply-templates select="child::*/*/metrics/*[not (discoveryRule)]/triggers/trigger"/>
		</triggers>
		<value_maps>
				<xsl:copy-of copy-namespaces="no" select="child::*/value_maps/*"/>
		</value_maps>
	</zabbix_export>
</xsl:template>

<xsl:template match="template">
			<template>
	    		<template><xsl:value-of select="./name"></xsl:value-of></template>
				<name><xsl:value-of select="./name"></xsl:value-of></name>
				<description><xsl:value-of select="./description"></xsl:value-of></description>
	            <groups>
	                <group>
	                    <name>Templates</name>
	                </group>
	            </groups>
	            <applications>
				<xsl:for-each select="distinct-values(metrics//group)">
    				<application>
	                    <name><xsl:value-of select="."/></name>
	                </application>
    			</xsl:for-each>
	            </applications>				
				<items>
					<xsl:apply-templates select="metrics/*[not (discoveryRule)]"></xsl:apply-templates>
				</items>
				<discovery_rules>
					<xsl:apply-templates select="discoveryRules"></xsl:apply-templates>
				</discovery_rules>
				<xsl:if test="$zbx_ver=3.4"><httptests/></xsl:if>
	            <macros>
	            	<xsl:for-each-group select="macros/macro" group-by="macro">
  						<macro>
  							<macro><xsl:value-of select="./macro"/></macro>
  							<value><xsl:value-of select="./value"/></value>
						</macro>
					</xsl:for-each-group>
	            </macros>
	            <xsl:copy-of copy-namespaces="no" select="./templates"/><!-- template dependencies block -->
	            <screens/>
			</template>
</xsl:template>

<xsl:template match="discoveryRules/*">
					<xsl:variable name="disc_name" select="./name"></xsl:variable>
					<discovery_rule>
						<name><xsl:value-of select="./name"/></name>
	                    <type><xsl:copy-of select="$snmp_item_type"/></type>
	                    <snmp_community><xsl:copy-of select="$community"/></snmp_community>
	                    <snmp_oid><xsl:value-of select="./snmp_oid"/></snmp_oid>
						<key><xsl:value-of select="./key"/></key>
	                    <delay><xsl:value-of select="$discoveryDelay"/></delay>
	                    <status>0</status>
	                    <allowed_hosts/>
	                    <snmpv3_contextname/>
	                    <snmpv3_securityname/>
	                    <snmpv3_securitylevel>0</snmpv3_securitylevel>
	                    <snmpv3_authprotocol>0</snmpv3_authprotocol>
	                    <snmpv3_authpassphrase/>
	                    <snmpv3_privprotocol>0</snmpv3_privprotocol>
	                    <snmpv3_privpassphrase/>
	                    <xsl:if test="$zbx_ver = 3.2"><delay_flex/></xsl:if>
	                    <params/>
	                    <ipmi_sensor/>
	                    <authtype>0</authtype>
	                    <username/>
	                    <password/>
	                    <publickey/>
	                    <privatekey/>
	                    <port>161</port>
	                    
                    	<xsl:choose>
						  <xsl:when test="./filter != ''">
						    <xsl:copy-of copy-namespaces="no" select="./filter[name()!='xmlns:tns']"/>
						  </xsl:when>
					    <xsl:otherwise>
					    <filter>
				            <evaltype>0</evaltype>
	                        <formula/>
	                        <conditions/>
                        </filter>
						  </xsl:otherwise>
						</xsl:choose>
	                    <xsl:choose>
						  <xsl:when test="$zbx_ver = 3.4">
						    <lifetime>30d</lifetime> <!-- 30days-->
						  </xsl:when>
					      <xsl:otherwise>
							<lifetime>30</lifetime> <!-- in days -->
						  </xsl:otherwise>
						</xsl:choose>
	                    <description><xsl:value-of select="./description"/></description>
	                    <item_prototypes>
	                        <xsl:apply-templates select="../../metrics/*[discoveryRule = $disc_name]"/>
	                    </item_prototypes>
	                    <trigger_prototypes>
	                        <xsl:apply-templates select="../../metrics/*[discoveryRule = $disc_name]/triggers/trigger"/>
	                    </trigger_prototypes>
	                    <graph_prototypes>
	                    	<xsl:copy-of copy-namespaces="no" select="../../metrics/*[discoveryRule = $disc_name]/graphs/graph"/>
	                    </graph_prototypes>
	                    <host_prototypes/>
                	</discovery_rule>
</xsl:template>

<xsl:template name="triggerTemplate">
		<xsl:variable name="template_name" select="../../../../name"/>
		<xsl:variable name="metric_name" select="../../name"/>
		<xsl:variable name="metric_alarm_object" select="../../alarmObject"/>

					<expression><xsl:value-of select="replace(./expression,'TEMPLATE_NAME',$template_name)"/></expression>
				<recovery_mode>
					<xsl:choose>
		  				<xsl:when test="./recovery_expression != ''">1</xsl:when>
		  				<xsl:when test="./recovery_mode != ''"><xsl:value-of select="./recovery_mode"/></xsl:when>
	      				<xsl:otherwise>0</xsl:otherwise>
					</xsl:choose>
				</recovery_mode>
                        	<recovery_expression><xsl:value-of select="replace(./recovery_expression,'TEMPLATE_NAME',$template_name)"/></recovery_expression>
				<name><xsl:value-of select="./name"/></name>
				<correlation_mode>0</correlation_mode>
                        	<correlation_tag/>
                         <url><xsl:value-of select="./url"/></url>
                         <status>0</status>
                         <priority><xsl:value-of select="./priority"/></priority>
                         <description><xsl:value-of select="./description"/></description>
                         <type>0</type>
                         <manual_close>
					<xsl:choose>
		  				<xsl:when test="./manual_close = 1">1</xsl:when>
	      				<xsl:otherwise>0</xsl:otherwise>
					</xsl:choose>
				</manual_close>
                         <dependencies>
            					<xsl:for-each select="./dependsOn/dependency">
						<xsl:variable name="trigger_id" select="."/>
									<dependency>
									<xsl:choose>
										<xsl:when test="../global = true()"> <!-- search in other templates (but templates must in the same file) -->
   										<name><xsl:value-of select="//template/metrics/*[alarmObject=$metric_alarm_object ]/triggers/trigger[id=$trigger_id]/name"/></name>
   										<expression><xsl:value-of select="replace(//template/metrics/*[alarmObject=$metric_alarm_object ]/triggers/trigger[id=$trigger_id]/expression,'TEMPLATE_NAME',$template_name)"/></expression>
   										<recovery_expression><xsl:value-of select="replace(//template/metrics/*[alarmObject=$metric_alarm_object ]/triggers/trigger[id=$trigger_id]/recovery_expression,'TEMPLATE_NAME',$template_name)"/></recovery_expression>
  										</xsl:when>
  										<xsl:otherwise>
  											<name><xsl:value-of select="//template[name=$template_name]/metrics/*[alarmObject=$metric_alarm_object ]/triggers/trigger[id=$trigger_id]/name"/></name>
   										<expression><xsl:value-of select="replace(//template[name=$template_name]/metrics/*[alarmObject=$metric_alarm_object ]/triggers/trigger[id=$trigger_id]/expression,'TEMPLATE_NAME',$template_name)"/></expression>
   										<recovery_expression><xsl:value-of select="replace(//template[name=$template_name]/metrics/*[alarmObject=$metric_alarm_object ]/triggers/trigger[id=$trigger_id]/recovery_expression,'TEMPLATE_NAME',$template_name)"/></recovery_expression>
  										</xsl:otherwise>
 									</xsl:choose>
						</dependency>
					</xsl:for-each>                        	                
                         </dependencies>
				<!--<tags><xsl:copy-of copy-namespaces="no" select="./tags/*"/></tags>   removed tags for now -->
				<tags/>
</xsl:template>


<xsl:template match="metrics/*/triggers/trigger">

		<xsl:choose>
        	<xsl:when test="../../.[not (discoveryRule)]">
							<trigger>
								<xsl:call-template name="triggerTemplate"/>
							</trigger>
			</xsl:when>
        <xsl:otherwise>
     						<trigger_prototype>
								<xsl:call-template name="triggerTemplate"/>
							</trigger_prototype>
        </xsl:otherwise>
        </xsl:choose>
</xsl:template>

<xsl:template name="itemTemplate">
	<xsl:variable name="itemType" select="./itemType"/>
	<name><xsl:value-of select="./name"></xsl:value-of></name>
	                    <type><xsl:value-of select="$item_type/entry[@key=$itemType]"/></type>
                        <xsl:choose>
						  <xsl:when test="./itemType eq 'snmp'">
						    <snmp_community><xsl:copy-of select="$community"/></snmp_community>
						  </xsl:when>
					      <xsl:otherwise>
							<snmp_community/>
						  </xsl:otherwise>
						</xsl:choose>
	                    <xsl:if test="$zbx_ver = 3.2">
		                    <xsl:choose>
							  <xsl:when test="./preprocessing/step[type eq 'multiplier']">
							    <multiplier>1</multiplier>
							  </xsl:when>
						      <xsl:otherwise>
								<multiplier>0</multiplier>
							  </xsl:otherwise>
							</xsl:choose>
						</xsl:if>
						<snmp_oid><xsl:value-of select="./oid"></xsl:value-of></snmp_oid>
						<key><xsl:value-of select="./snmpObject"></xsl:value-of></key>
	                    <delay><xsl:value-of select="./update"></xsl:value-of></delay>
						<xsl:choose>
						  <xsl:when test="$zbx_ver = 3.4"> <!--  in seconds -->
						    <history><xsl:value-of select="./history"></xsl:value-of>d</history>
	                    	<trends><xsl:value-of select="./trends"></xsl:value-of>d</trends>
						  </xsl:when>
					      <xsl:otherwise> <!--  before 3.4 its in days -->
							<history><xsl:value-of select="./history"></xsl:value-of></history>
	                    	<trends><xsl:value-of select="./trends"></xsl:value-of></trends>
						  </xsl:otherwise>
						</xsl:choose>

	                    <status>0</status>
	                    <value_type><xsl:value-of select="./valueType"></xsl:value-of></value_type>
	                    <allowed_hosts/>
	                    <units><xsl:value-of select="./units"></xsl:value-of></units>
						<xsl:if test="$zbx_ver=3.2">
	                		<xsl:choose>
							  <xsl:when test="./preprocessing/step[type eq 'delta_per_second']">
							    <delta>1</delta>
							  </xsl:when>
						      <xsl:otherwise>
								<delta>0</delta>
							  </xsl:otherwise>
							</xsl:choose>
						</xsl:if>
	                    <snmpv3_contextname/>
	                    <snmpv3_securityname/>
	                    <snmpv3_securitylevel>0</snmpv3_securitylevel>
	                    <snmpv3_authprotocol>0</snmpv3_authprotocol>
	                    <snmpv3_authpassphrase/>
	                    <snmpv3_privprotocol>0</snmpv3_privprotocol>
	                    <snmpv3_privpassphrase/>
						<xsl:if test="$zbx_ver=3.2">
	                		<xsl:choose>
							  <xsl:when test="./preprocessing/step[type eq 'multiplier']">
							    <formula><xsl:value-of select="./preprocessing/step[type eq 'multiplier']/params"/></formula>
							  </xsl:when>
						      <xsl:otherwise>
								<formula>0</formula>
							  </xsl:otherwise>
							</xsl:choose>
						</xsl:if>
	                    <xsl:if test="$zbx_ver = 3.2"><delay_flex/></xsl:if>
	                    <params><xsl:value-of select="./expressionFormula"></xsl:value-of></params>
	                    <ipmi_sensor/>
	                    <xsl:if test="$zbx_ver = 3.2"><data_type>0</data_type></xsl:if>
	                    <authtype>0</authtype>
	                    <username/>
	                    <password/>
	                    <publickey/>
	                    <privatekey/>
	                    <port><xsl:copy-of select="$snmp_port"/></port>
						<description>
							<xsl:value-of select="if (./mib) then (concat(./mib,' ')) else ()"/>
							<xsl:value-of select="if (./ref) then (concat(./ref,' ')) else ()"/>
							<xsl:value-of select="if (./vendorDescription) then (./vendorDescription) else (./description)"/>
						</description>
						<xsl:choose>
						  <xsl:when test="./inventory_link != ''">
						    <inventory_link><xsl:value-of select="./inventory_link"/></inventory_link>
						  </xsl:when>
					      <xsl:otherwise>
							<inventory_link>0</inventory_link>
						  </xsl:otherwise>
						</xsl:choose>
		                <applications>
                                <application>
                                    <name><xsl:value-of select="./group"></xsl:value-of></name>
                                </application>
                        </applications>
	                    <valuemap>
							<xsl:choose>
							  <xsl:when test="./valueMap != ''">
							    <name>
							    	<xsl:value-of select="./valueMap"/>
							    </name>
							  </xsl:when>
							</xsl:choose>
	                    </valuemap>
	                    <logtimefmt><xsl:value-of select="./logFormat"/></logtimefmt>
	                    <xsl:if test="$zbx_ver = 3.4">
	                    <preprocessing>
		                    <xsl:for-each select="./preprocessing/step">
											<xsl:variable name="step" select="."/>
	    									<step>
	    										<type><xsl:value-of select="$step_map/entry[@key=$step/type]"/></type>
	    										<params><xsl:value-of select="$step/params"/></params>
	    									</step>
							</xsl:for-each>
						</preprocessing>
	                    </xsl:if>

</xsl:template>


<xsl:template match="metrics/*">
      <xsl:choose>
        <xsl:when test="./not (discoveryRule)">
				<item>
  					<xsl:call-template name="itemTemplate"/>
  				</item>        
		</xsl:when>
        <xsl:otherwise>
        		<item_prototype>
        			<xsl:call-template name="itemTemplate"/>
					<application_prototypes/>
				</item_prototype>
        </xsl:otherwise>
      </xsl:choose>
</xsl:template>


</xsl:stylesheet>

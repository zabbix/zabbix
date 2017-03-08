<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="2.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">

<xsl:output method="xml" indent="yes"/>

<xsl:variable name="community">{$SNMP_COMMUNITY}</xsl:variable>
<xsl:param name="snmp_item_type" select="4"/>
<xsl:variable name="calc_item_type">15</xsl:variable>
<xsl:variable name="snmp_port">161</xsl:variable>


<xsl:template match="/">
	<zabbix_export>
	    <version>3.0</version>
	    <date>2015-12-30T14:41:30Z</date>
	    <groups>
	        <group>
	            <name>Templates</name>
	        </group>
	    </groups>
		<templates>
				 <xsl:apply-templates select="child::*/template"></xsl:apply-templates>  
		</templates>
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
						<name><xsl:value-of select="./name"></xsl:value-of></name>
	                    <type><xsl:copy-of select="$snmp_item_type"/></type>
	                    <snmp_community><xsl:copy-of select="$community"/></snmp_community>
	                    <snmp_oid><xsl:value-of select="./snmp_oid"></xsl:value-of></snmp_oid>
						<key><xsl:value-of select="./key"></xsl:value-of></key>
	                    <delay>1800</delay>
	                    <status>0</status>
	                    <allowed_hosts/>
	                    <snmpv3_contextname/>
	                    <snmpv3_securityname/>
	                    <snmpv3_securitylevel>0</snmpv3_securitylevel>
	                    <snmpv3_authprotocol>0</snmpv3_authprotocol>
	                    <snmpv3_authpassphrase/>
	                    <snmpv3_privprotocol>0</snmpv3_privprotocol>
	                    <snmpv3_privpassphrase/>
	                    <delay_flex/>
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
						    <xsl:copy-of copy-namespaces="no" select="./filter[name()!='xmlns:tns']"></xsl:copy-of>
						  </xsl:when>
					    <xsl:otherwise>
					    <filter>
				            <evaltype>0</evaltype>
	                        <formula/>
	                        <conditions/>
                        </filter>
						  </xsl:otherwise>
						</xsl:choose>
	                    
	                    <lifetime>30</lifetime>
	                    <description><xsl:value-of select="./description"></xsl:value-of></description>
	                    <item_prototypes>
	                        <xsl:apply-templates select="../../metrics/*[discoveryRule = $disc_name]"></xsl:apply-templates>
	                    </item_prototypes>
	                    <trigger_prototypes>
	                        <xsl:apply-templates select="../../metrics/*[discoveryRule = $disc_name]/triggers/trigger"></xsl:apply-templates>
	                    </trigger_prototypes>
	                    <graph_prototypes/>
	                    <host_prototypes/>
                	</discovery_rule>
</xsl:template>

<xsl:template match="metrics/*/triggers/trigger">
		<xsl:variable name="template_name" select="../../../../name"/>
		<xsl:variable name="metric_name" select="../../name"/>

		<xsl:choose>
        	<xsl:when test="../../.[not (discoveryRule)]">
							<trigger>
								<expression><xsl:value-of select="./expression"/></expression>
								<name><xsl:value-of select="./name"/></name>
	                            <url><xsl:value-of select="./url"/></url>
	                            <status>0</status>
	                            <priority><xsl:value-of select="./priority"/></priority>
	                            <description><xsl:value-of select="./description"/></description>
	                            <type>0</type>
	                            <dependencies>
	               					<xsl:for-each select="./dependsOn/dependency">
										<xsl:variable name="trigger_id" select="."/>
    									<dependency>			
      										<name><xsl:value-of select="//template[name=$template_name]/metrics/*[name=$metric_name]/triggers/trigger[id=$trigger_id]/name"/></name>
      										<expression><xsl:value-of select="//template[name=$template_name]/metrics/*[name=$metric_name]/triggers/trigger[id=$trigger_id]/expression"/></expression>
										</dependency>
									</xsl:for-each>                      	                
	                            </dependencies>
							</trigger>
			</xsl:when>
        <xsl:otherwise>
     						<trigger_prototype>
								<expression><xsl:value-of select="./expression"/></expression>
								<name><xsl:value-of select="./name"/></name>
	                            <url><xsl:value-of select="./url"/></url>
	                            <status>0</status>
	                            <priority><xsl:value-of select="./priority"/></priority>
	                            <description><xsl:value-of select="./description"/></description>
	                            <type>0</type>
	                            <dependencies>
	               					<xsl:for-each select="./dependsOn/dependency">
										<xsl:variable name="trigger_id" select="."/>
    									<dependency>			
      										<name><xsl:value-of select="//template[name=$template_name]/metrics/*[name=$metric_name]/triggers/trigger[id=$trigger_id]/name"/></name>
      										<expression><xsl:value-of select="//template[name=$template_name]/metrics/*[name=$metric_name]/triggers/trigger[id=$trigger_id]/expression"/></expression>
										</dependency>
									</xsl:for-each>                        	                
	                            </dependencies>
							</trigger_prototype>
        </xsl:otherwise>
        </xsl:choose>
</xsl:template>


<xsl:template match="metrics/*">
      <xsl:choose>
        <xsl:when test="./not (discoveryRule)">
				<item>
  					<name><xsl:value-of select="./name"></xsl:value-of></name>
	                    <type>
	                    <xsl:choose>
						  <xsl:when test="./expressionFormula != ''">
						    <xsl:copy-of select="$calc_item_type"/> <!-- calc zabbix type -->
						  </xsl:when>
					      <xsl:otherwise>
							<xsl:copy-of select="$snmp_item_type"/>
						  </xsl:otherwise>
						</xsl:choose>
	                    </type>
	                    <snmp_community><xsl:copy-of select="$community"/></snmp_community>
	                    <xsl:choose>
						  <xsl:when test="./multiplier != ''">
						    <multiplier>1</multiplier>
						  </xsl:when>
					      <xsl:otherwise>
							<multiplier>0</multiplier>
						  </xsl:otherwise>
						</xsl:choose>
						<snmp_oid><xsl:value-of select="./oid"></xsl:value-of></snmp_oid>
						<key><xsl:value-of select="./snmpObject"></xsl:value-of></key>
	                    <delay><xsl:value-of select="./update"></xsl:value-of></delay>
	                    <history><xsl:value-of select="./history"></xsl:value-of></history>
	                    <trends><xsl:value-of select="./trends"></xsl:value-of></trends>
	                    <status>0</status>
	                    <value_type><xsl:value-of select="./valueType"></xsl:value-of></value_type>
	                    <allowed_hosts/>
	                    <units><xsl:value-of select="./units"></xsl:value-of></units>
	                    <delta>0</delta>
	                    <snmpv3_contextname/>
	                    <snmpv3_securityname/>
	                    <snmpv3_securitylevel>0</snmpv3_securitylevel>
	                    <snmpv3_authprotocol>0</snmpv3_authprotocol>
	                    <snmpv3_authpassphrase/>
	                    <snmpv3_privprotocol>0</snmpv3_privprotocol>
	                    <snmpv3_privpassphrase/>
	                    <xsl:choose>
						  <xsl:when test="./multiplier != ''">
						    <formula><xsl:value-of select="./multiplier"/></formula>
						  </xsl:when>
					      <xsl:otherwise>
							<formula>0</formula>
						  </xsl:otherwise>
						</xsl:choose>
	                    <delay_flex/>
	                    <params><xsl:value-of select="./expressionFormula"></xsl:value-of></params>
	                    <ipmi_sensor/>
	                    <data_type>0</data_type>
	                    <authtype>0</authtype>
	                    <username/>
	                    <password/>
	                    <publickey/>
	                    <privatekey/>
	                    <port><xsl:copy-of select="$snmp_port"/></port>
						<description>
							<xsl:value-of select="./mib"></xsl:value-of><xsl:text> </xsl:text>
							<xsl:value-of select="./ref"></xsl:value-of><xsl:text> </xsl:text>
							<xsl:value-of select="./vendorDescription"></xsl:value-of>
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
	                    <logtimefmt/>
  				</item>        
		</xsl:when>
        <xsl:otherwise>
        		<item_prototype>
        			<name><xsl:value-of select="./name"></xsl:value-of></name>
	                    <type>
	                    <xsl:choose>
						  <xsl:when test="./expressionFormula != ''">
						    <xsl:copy-of select="$calc_item_type"/> <!-- calc zabbix type -->
						  </xsl:when>
					      <xsl:otherwise>
							<xsl:copy-of select="$snmp_item_type"/>
						  </xsl:otherwise>
						</xsl:choose>
	                    </type>
	                    <snmp_community><xsl:copy-of select="$community"/></snmp_community>
	                    <xsl:choose>
						  <xsl:when test="./multiplier != ''">
						    <multiplier>1</multiplier>
						  </xsl:when>
					      <xsl:otherwise>
							<multiplier>0</multiplier>
						  </xsl:otherwise>
						</xsl:choose>
						<snmp_oid><xsl:value-of select="./oid"></xsl:value-of></snmp_oid>
						<key><xsl:value-of select="./snmpObject"></xsl:value-of></key>
	                    <delay><xsl:value-of select="./update"></xsl:value-of></delay>
	                    <history><xsl:value-of select="./history"></xsl:value-of></history>
	                    <trends><xsl:value-of select="./trends"></xsl:value-of></trends>
	                    <status>0</status>
	                    <value_type><xsl:value-of select="./valueType"></xsl:value-of></value_type>
	                    <allowed_hosts/>
	                    <units><xsl:value-of select="./units"></xsl:value-of></units>
	                    <delta>0</delta>
	                    <snmpv3_contextname/>
	                    <snmpv3_securityname/>
	                    <snmpv3_securitylevel>0</snmpv3_securitylevel>
	                    <snmpv3_authprotocol>0</snmpv3_authprotocol>
	                    <snmpv3_authpassphrase/>
	                    <snmpv3_privprotocol>0</snmpv3_privprotocol>
	                    <snmpv3_privpassphrase/>
                		<xsl:choose>
						  <xsl:when test="./multiplier != ''">
						    <formula><xsl:value-of select="./multiplier"/></formula>
						  </xsl:when>
					      <xsl:otherwise>
							<formula>0</formula>
						  </xsl:otherwise>
						</xsl:choose>
	                    <delay_flex/>
	                    <params><xsl:value-of select="./expressionFormula"></xsl:value-of></params>
	                    <ipmi_sensor/>
	                    <data_type>0</data_type>
	                    <authtype>0</authtype>
	                    <username/>
	                    <password/>
	                    <publickey/>
	                    <privatekey/>
	                    <port><xsl:copy-of select="$snmp_port"/></port>
						<description>
							<xsl:value-of select="./mib"></xsl:value-of><xsl:text> </xsl:text>
							<xsl:value-of select="./ref"></xsl:value-of><xsl:text> </xsl:text>
							<xsl:value-of select="./vendorDescription"></xsl:value-of>
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
	                    <logtimefmt/>
					<application_prototypes/>
				</item_prototype>
        </xsl:otherwise>
      </xsl:choose>

</xsl:template>


</xsl:stylesheet>


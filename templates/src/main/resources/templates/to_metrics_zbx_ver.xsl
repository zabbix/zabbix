<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="2.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">

<xsl:output method="xml" indent="yes"/>

<xsl:param name="zbx_ver" select="undef"/>


<!-- 

 -->
<xsl:template match="node()|@*">
   <xsl:copy>
            <xsl:apply-templates select="node()|@*"/>
   </xsl:copy>
</xsl:template>

<xsl:template match="node()[@zbx_ver!=$zbx_ver]">
	
		
	
</xsl:template>



<xsl:template match="node()[@zbx_ver=$zbx_ver]">
	
		<xsl:copy-of select="."/>
	
</xsl:template>

</xsl:stylesheet>

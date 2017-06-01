<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="2.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">

<xsl:output method="xml" indent="yes"/>

<xsl:param name="lang" select="undef"/>


<!-- 

 -->
<xsl:template match="node()|@*">
   <xsl:copy>
            <xsl:apply-templates select="node()|@*"/>
   </xsl:copy>
</xsl:template>



<xsl:template match="node()[@lang!=$lang]">
	<!--<xsl:copy>
		<xsl:copy-of  select="concat(., '_TBD')"/>
	</xsl:copy> or just delete it   -->
</xsl:template>

<xsl:template match="node()[@lang=$lang]">
	<xsl:copy>
		<xsl:value-of  select="."/>
	</xsl:copy> 
</xsl:template>

</xsl:stylesheet>

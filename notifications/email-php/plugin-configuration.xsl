<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0">
	<xsl:import href="../ajax/notification-type.xsl" />
	
	<!-- EDIT -->
	<xsl:template name="parameters-edit">
		<table>
			<tr>
				<td colspan="2">
					<input type="hidden" name="id" value="{/page/get/@id}"/>
					<input type="hidden" name="type_notif" value="email"/>
					<label class="formLabel"><i>From :</i></label>
					<xsl:text>&#160;</xsl:text>
					<input type="text" name="from" style="width:300px;" value="{/page/notification-type/email}" placeholder="E-mail From" /> <br/>
				</td>
				<td class="tdActions">
					<span class="faicon fa-floppy-o" onclick="saveNotifType($(this));" title="Save"></span>
					<span class="faicon fa-ban" onclick="window.location.reload();" title="Cancel"></span>
				</td>
			</tr>
		</table>
	</xsl:template>
	
</xsl:stylesheet>
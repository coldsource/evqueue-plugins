# Notification plugin XMPP

This plugin allows evQueue to send XMPP (jabber) messages uppon workflow instance end.

## Requirements

This plugin requires the following command line utilities to work : __sendxmpp__, __xmllint__, __jq__

## Building

Open a shell in the directory of the plugin and type: __./build.sh__

## Installing in evQueue

Login to evQueue, go to __Notifications -> Manage plugins__, drag and drop the zip file.

Click on the cogs icon to configure the account used to send messages.

Go to __Notifications -> Configure__, click blank sheet on top right icon, select __XMPP__ type and fill your notification configuration.

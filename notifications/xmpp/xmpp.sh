#!/bin/bash

# Read configuration from STDIN
JSON=$(cat)

# Parse plugin configuration
jserver=$(echo $JSON | jq -r '.pluginconf.jserver')
username=$(echo $JSON | jq -r '.pluginconf.username')
component=$(echo $JSON | jq -r '.pluginconf.component')
password=$(echo $JSON | jq -r '.pluginconf.password')

# Parse notification configuration
sendon=$(echo $JSON | jq -r '.notificationconf.sendon')
to=$(echo $JSON | jq -r '.notificationconf.to')
message=$(echo $JSON | jq -r '.notificationconf.message')

# Read instance XML
instance=$(echo $JSON | jq -r '.instance')

# Extract instance variables
id=$(echo $instance | xmllint --xpath 'string(/workflow/@id)' -)
name=$(echo $instance | xmllint --xpath 'string(/workflow/@name)' -)
errors=$(echo $instance | xmllint --xpath 'string(/workflow/@errors)' -)

# Substitute variables in message
message=$(echo $message | sed -e "s/#ID#/$id/")
message=$(echo $message | sed -e "s/#NAME#/$name/")
message=$(echo $message | sed -e "s/#ERRORS#/$errors/")


# Send notification
send_msg() {
	echo $message | sendxmpp --tls --username $username --password $password --jserver $jserver --component $component --resource notification $to
}

if [ $sendon = "success" ] && [ $errors -eq 0 ]
then
	send_msg
fi

if [ $sendon = "error" ] && [ $errors -ne 0 ]
then
	send_msg
fi

if [ $sendon = "always" ]
then
	send_msg
fi

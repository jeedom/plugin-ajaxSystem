# Ajax System

## Configuration

The configuration of the plugin is very simple and it takes place in 2 steps : 

- setting up the link between your jeedom and your alarm
- addition of an email sharing for reporting events 

### Link configuration 

To set up the link between your Jeedom and your Ajax alarm, go to "Plugin" -> "Plugin management" -> "Ajax System" then click on "Connect", you enter your Ajax identifiers and click on "Validate".

>**NOTE**
>
> Jeedom absolutely does not save your Ajax credentials it is just one used for the first request to Ajax and to have the access token and the refresh token. The refresh token makes it possible to recover a new access token which has a lifespan of only a few hours

>**NOTE**
>
> Once the link is made all the requests go through our cloud but at no time does the cloud store your access token, so it is not possible with only the jeedom cloud to act on your alarm. For any action on this, you absolutely need the combination of your Jeedom's access token and a key known only to our cloud 

### Configuration of event reporting

From the Ajax application, go to the hub then in settings (small cogwheel at the top right) go to user and add the user : ajax@jeedom.com 

## Equipment 

Once the configuration is on "Plugin" -> "Plugin management" -> "Ajax System" you just have to synchronize, Jeeodm will automatically create all the ajax equipment linked to your Ajax account. 


# Faq 

>**I have the real-time feedback of the opening sensors but for the motion detectors I only have the information if the alarm is armed**
>
>This is normal it is a choice of the manufacturer there is no possibility to change this behavior.




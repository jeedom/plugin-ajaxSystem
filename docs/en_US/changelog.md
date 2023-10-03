# Changelog Ajax Systems

>**IMPORTANT**
>
>As a reminder, if there is no information on the update, it is because it concerns only the update of documentation, translation or text

# 03/10/2023

- Addition of a new alarm status in the event of forced arming (for example when equipment is in error but the alarm is forced to be activated)
  This new status is available on the Hub status command, and has the technical value "FORCED_ARM". A logo with a partially filled shield is now displayed on the widget in this mode to clearly indicate that the alarm is in service but with potential faults
- Revised the command update fetching mechanism to allow greater flexibility. In the near future, this should make it possible to add
  more information on the equipment. Depending on time and material available for testing
- Removed the ability to manually adjust Logical IDs on equipment orders
- Removed the ability to manually add or remove equipment orders
- Preparations for implementing a mechanism to upgrade equipment controls during plugin update. This will allow obsolete commands to be deleted but also new commands to be added without impacting the end user. (This part is currently under development)
- Updated documentation

# 06/06/2023

- Add fibra hub

# 08/23/2022

- Added group management
- Improved multi transmitter support

# 06/09/2022

- Removal of the automatic refresh of information every hour to limit the number of calls to Ajax and prevent quota overrun

# 02/21/2021

- Fixed bug with SIA protocol

# 01/05/2021

- Fixed an issue for Socket

# 01/04/2022

- Optimization of the installation of dependencies
- Correction of the color management of the equipment
- Addition of the Dual Curtain Outdoor
- Add Wall Switch

# 12/11/2021

- Color management of the modules to display the correct image (need to redo a synchronization)
- Correction of a problem on the external inputs of DoorProtect (a removal of the equipment and resynchronization is necessary)
- Fixed a problem with the SIA daemon
- Documentation update

# 12/02/2021

- Added on / off commands for relays
- Addition of a SIA daemon for the local recovery of certain information (read the documentation for the configuration)
- Addition of compatible equipment

# 08/19/2021

- Random shift of the refresh cron to try to correct the problem "You have exceeded the limit in 100 requests per minute"

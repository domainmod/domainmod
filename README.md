# Domain Manager
    Project Home: http://aysmedia.com/code/domainmanager/  
    Project Demo: http://demos.aysmedia.com/domainmanager/  
    Source Code: http://github.com/aysmedia/domainmanager/  


# About
Domain Manager is a web-based application written in PHP & MySQL used to manage a collection of domain names.  


# Live Demo
Not sure if the Domain Manager is what you're looking for? Don't want to waste your time installing it only to find out that it's not? As developers ourselves, we hate when that happens, so AYS Media always tries to run live demos of our products so that we don't waste your time.  

So go ahead, take our live demo for a test drive before you install! http://demos.aysmedia.com/domainmanager/  


# Downloading
You have two options for downloading the Domain Manager.  

NOTE: Whenever possible we recommend that you use option #1, the git repository download directly from your server.  

1. Use git right from your server to retrieve the source code. To do so, change to the directory where you want to install and run the following command:  

        git clone git://github.com/aysmedia/domainmanager.git .  

2. Visit the following URL to download the most recent source code archive: https://github.com/aysmedia/domainmanager/archive/master.zip  


# Installation
1. Please choose from one of the following two options:  

    If you used git to retrieve the source code in the previous step, just change to the directory where you ran the git command and your files are already waiting for you.  

    If you downloaded the source code in the previous step, you will now need to upload the archive to your server and then unpack it into the folder where you wish to install.  

2. Create a MySQL database that will be used to store the Domain Manager information.  

3. In the '_includes' folder, copy config.SAMPLE.inc.php to config.inc.php and then update config.inc.php to reflect your server's settings.  

4. In a web browser, visit the URL where you just installed the Domain Manager and then click the Install link. For example, http://aysmedia.com/dm/ or http://aysmedia.com/domainmanager/.  

5. After a few seconds a message should appear letting you know that the installation was successful.

6. If you have any problems during installation please see the Support section below.


# Usage
After installation just load the URL in a web browser and play around in the UI, it's pretty self explanatory.  


# Upgrading
You have two options for upgrading the Domain Manager.  

1. Use git right from your server to upgrade. To do so, just run the following command from within the directory where you installed the Domain Manager:  

        git pull  
    
    That's it. Upgrading with git is very easy, which is one of the reasons using git is our recommended method for downloading the Domain Manager source code.  

2. Visit the following URL to download the most recent source code archive: https://github.com/aysmedia/domainmanager/archive/master.zip  

   Them simply unpack the new archive overtop of where you installed the previous version.  


# Support
If you have any questions or comments please visit http://aysmedia.com or email us at code@aysmedia.com  

To report bugs, please visit http://github.com/aysmedia/domainmanager/issues/  


# License
Domain Manager - A web-based application written in PHP & MySQL used to manage a collection of domain names.  
Copyright (C) 2010 Greg Chetcuti  

Domain Manager is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.  

Domain Manager is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.  

You should have received a copy of the GNU General Public License along with Domain Manager. If not, please see http://www.gnu.org/licenses/  
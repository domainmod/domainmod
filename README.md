# Domain Manager
    Project Home: http://aysmedia.com/code/domainmanager/  
    Project Demo: http://demos.aysmedia.com/domainmanager/  
    Source Code: http://github.com/aysmedia/domainmanager/  


# About
Domain Manager is a web-based application written in PHP & MySQL used to manage a collection of domain names. Domain Manager also includes a fully functional Data Warehouse framework that allows you to import your web server data so that you can view, export, and report on your live data. Currently the Data Warehouse only supports servers running WHM/cPanel, but further support will be added in the future.

# Live Demo
Not sure if the Domain Manager is what you're looking for? Don't want to waste your time installing it only to find out that it's not? As a developer myself, I hate when that happens, so I always try to run live demos so you don't waste your time.  

So go ahead, take live demo for a test drive before you install! http://demos.aysmedia.com/domainmanager/  


# Downloading
You have two options for downloading the Domain Manager.  

NOTE: Whenever possible I recommend that you use option #1, the git repository download directly from your server. Not only is it a nice quick install, but it makes upgrading a breeze (more info below).  

1. Use git right from your server to retrieve the source code. To do so, change to the directory where you want to install Domain Manager and run the following command:  

        git clone git://github.com/aysmedia/domainmanager.git  

    The Domain Manager files will now be saved in a local directory called /domainmanager/.  

2. Visit the following URL to download the most recent source code archive: https://github.com/aysmedia/domainmanager/archive/master.zip  


# Installation
1. Please choose from one of the following two options:  

    If you used git to retrieve the source code in the previous step, just change to the directory where you ran the git command and your files are already waiting for you in a folder called /domainmanager/. Feel free to rename this folder to whatever you want.  

    If you downloaded the source code in the previous step, you will now need to upload the archive to your server and then unpack it into the folder where you wish to install.  

2. Create a MySQL database that will be used to store the Domain Manager information.  

3. In the '_includes' folder, copy config.SAMPLE.inc.php to config.inc.php and then update config.inc.php to reflect your server's settings.  

4. In a web browser, visit the URL where you just installed the Domain Manager and then click the Install link. For example, http://aysmedia.com/dm/ or http://aysmedia.com/domainmanager/.  

5. After a few seconds a message should appear letting you know that the installation was successful.

6. If you have any problems during installation please see the Support section below.


# Cron Job Installation (Optional)
Included with Domain Manger are multiple cron jobs to help keep things running smoothly. The cron jobs are completely optional, and they can be triggered at whatever frequency you wish.

The first cron job will update the conversion rates for all active currencies.

The file to execute is:

    /SERVER-PATH-TO-DOMAIN-MANAGER/cron/currencies.php  

The second cron job will send out an email reminder about Domains and SSL Certificates that are coming up for renewal.

The file to execute is:

    /SERVER-PATH-TO-DOMAIN-MANAGER/cron/expirations.php  

The third cron job will rebuild your data warehouse. If you're going to use the data warehouse, it's highly recommended that you set this cron job up to automate your builds. There's a lot of work being done in the background during a build, and more often than not a web browser will timeout if you try to build through the UI instead of using a cron job, leading to incomplete and missing information in your data warehouse. I would recommend setting the cron job up to run daily, preferably while you're asleep, so that way you'll always start the day with the freshest data possible.

The file to execute is:

    /SERVER-PATH-TO-DOMAIN-MANAGER/cron/dw.php  

If you want to run all of the above cron jobs, simply run this script instead and it will execute all current and future cron jobs.

    /SERVER-PATH-TO-DOMAIN-MANAGER/cron/main.php  


# Data Warehouse

Domain Manager has a data warehouse framework built right into it, which allows you to import the data stored on your web server. Currently the only web servers that are supported are ones that run WHM/cPanel, but I also intend on adding support for Plesk once I've ironed out all the kinks in the framework.

If you don't run a server that uses WHM, or you don't want to import your WHM data into Domain Manager, you can ignore this section.

Importing your server(s) into the data warehouse will not modify any of your Domain Manager data. The data warehouse is used for informational purposes only, and you will see its data referenced throughout Domain Manager where applicable. For example, if a domain you're editing has information stored in your data warehouse, the system will automatically match them up and display the additional information for you, giving you even more insight into your data. You can also view, export, and run reports on the information in your data warehouse.

### Supported Data
The following WHM sections are currently supported, but my end goal is to have every piece of WHM information that can be retrieved via the API stored in the data warehouse. In the future I plan on adding support for as many types of web servers as possible.  

### ACCOUNTS

Domain, IP Address, Owner, User, Contact Email, Plan, Theme, Shell, Partition, Disk Limit, Disk Usage, Max Addons, Max FTP Accounts, Max Email Lists, Max Parked Domains, Max POP Accounts, Max SQL Accounts, Max Subdomains, Creation Date, Suspend Status, Suspend Reason, Suspend Time, Max Email Per Hour, Failed Email % Before Defer, Min Failed Email # Before Defer

### DNS ZONES

Zone File Name, Original/Primary Source of Zone Data, Admin Email, Serial #, Refresh, Retry, Expiry, Minimum TTL, Authoritative Name Server

### DNS RECORDS

TTL, Class, Type, IP Address, CNAME, Mail Server, Mail Server Priority, TXT Data, Line # of Zone, # of Lines, RAW Data

### References
WHM & cPanel: http://cpanel.net  
API Documentation: http://docs.cpanel.net/twiki/bin/view/SoftwareDevelopmentKit/XmlApi 



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
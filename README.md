[![DomainMOD](http://domainmod.org/images/logo.png)](http://domainmod.org)

    Project Home: http://domainmod.org  
    Project Demo: http://demo.domainmod.org  
    Source Code: http://github.com/aysmedia/domainmod/  


## About
DomainMOD is an open source application written in PHP & MySQL used to track and manage your web resources. DomainMOD also includes a fully functional Data Warehouse framework that allows you to import your web server data so that you can view, export, and report on your live data. Currently the Data Warehouse only supports servers running WHM/cPanel, but further support will be added in the future.


## Live Demo
Not sure if DomainMOD is what you're looking for? Don't want to waste your time installing it only to find out that it's not? As a developer myself, I hate when that happens, so I always try to run live demos so you don't waste your time.  

So go ahead, take the live demo for a test drive before you install! http://demo.domainmod.org  


## Downloading
There are currently two options for downloading DomainMOD.

NOTE: Whenever possible I recommend that you use option #1, the git repository download directly from your server. Not only is it a nice quick install, but it makes upgrading a breeze.  

1. Use git right from your server to retrieve the source code. To do so, change to the directory where you want to install DomainMOD and run the following command:  

        git clone git://github.com/aysmedia/domainmod.git  

    The DomainMOD files will now be saved in a local directory called /domainmod/.  

2. Visit the following URL to download the most recent source code archive: https://github.com/aysmedia/domainmod/archive/master.zip  


## Installation
1. Please choose from one of the following two options:  

    If you used git to retrieve the source code in the previous step, just change to the directory where you ran the git command and your files are already waiting for you in a folder called /domainmod/. Feel free to rename this folder to whatever you want.  

    If you downloaded the source code in the previous step, you will now need to upload the archive to your server and then unpack it into the folder where you wish to install (or unpack it and then upload it, whichever you prefer).  

2. Create a MySQL database that will be used to store DomainMOD's data.  

3. In the '_includes' folder, copy config.SAMPLE.inc.php to config.inc.php and then update config.inc.php to reflect your server's settings.  

4. In a web browser, visit the URL where you just installed DomainMOD and then click the Install link. For example, http://example.com/dm/ or http://example.com/domainmod/.  

5. After a few seconds a message will appear letting you know that the installation was successful.

6. If you have any problems during installation please see the Support section.


## Cron Job Installation (Optional)
Included with DomainMOD are multiple cron jobs to help keep things running smoothly. The cron jobs are completely optional, and they can be triggered at whatever frequency you wish.

To run all current and future cron jobs, simply execute this one cron job instead of the others below:

    /cron/main.php

The first cron job will update the conversion rates for all active currencies.

The file to execute is: /cron/currencies.php

The second cron job will send out an email reminder about Domains and SSL Certificates that are coming up for renewal (to all active, subscribed users).

The file to execute is: /cron/expirations.php

The third cron job will rebuild your Data Warehouse. If you're going to use the Data Warehouse it's highly recommended that you set this cron job up to automate your builds. There's a lot of work being done in the background during a build, and more often than not a web browser will timeout if you try to build through the UI instead of using the cron job, leading to incomplete and missing information in your Data Warehouse. I would recommend setting the cron job up to run daily, preferably while you're asleep, so that way you'll always start the day with the freshest data possible.

The file to execute is: /cron/dw.php

The forth cron job will fix various domain fee related issues. I would recommend setting the cron job up to run daily, preferably while you're asleep, so that way you'll always start the day with the freshest data possible.

The file to execute is: /cron/fixfees.php


## Data Warehouse

DomainMOD has a Data Warehouse framework built right into it, which allows you to import the data stored on your web server. Currently the only web servers that are supported are ones that run WHM/cPanel, but I also intend on adding support for Plesk once I've ironed out all the kinks in the framework.

If you don't run a server that uses WHM, or you don't want to import your WHM data into DomainMOD, you can ignore this section.

NOTE: Importing your server(s) into the Data Warehouse will not modify any of your DomainMOD data. The Data Warehouse is used for informational purposes only, and you will see its data referenced throughout DomainMOD where applicable. For example, if a domain you're editing has information stored in your Data Warehouse, the system will automatically match them up and display the additional information for you, giving you even more insight into your data. You can also view, export, and run reports against the information in your Data Warehouse.

### Supported Data
The following WHM sections are currently supported, but my end goal is to have every piece of WHM information that can be retrieved via the API stored in the Data Warehouse. In the future I plan on adding support for as many types of web servers as possible.  

#### ACCOUNTS
Domain, IP Address, Owner, User, Contact Email, Plan, Theme, Shell, Partition, Disk Limit, Disk Usage, Max Addons, Max FTP Accounts, Max Email Lists, Max Parked Domains, Max POP Accounts, Max SQL Accounts, Max Subdomains, Creation Date, Suspend Status, Suspend Reason, Suspend Time, Max Email Per Hour, Failed Email % Before Defer, Min Failed Email # Before Defer

#### DNS ZONES

Zone Filename, Original/Primary Source of Zone Data, Admin Email, Serial #, Refresh, Retry, Expiry, Minimum TTL, Authoritative Name Server

#### DNS RECORDS

TTL, Class, Type, IP Address, CNAME, Mail Server, Mail Server Priority, TXT Data, Line # of Zone, # of Lines, RAW Data

### References
WHM & cPanel: http://cpanel.net  
API Documentation: http://docs.cpanel.net/twiki/bin/view/SoftwareDevelopmentKit/XmlApi 


## Usage
After installation just load the URL in a web browser and play around in the UI, it's pretty self explanatory.  


## Upgrading

1. If you chose Download Option #1 above, using git right from your server to download the source code, just run the following command to upgrade DomainMOD:  

        git pull  
    
    That's it. Upgrading with git is very easy, which is one of the reasons using git is our recommended method for downloading the DomainMOD source code.  

2. Visit the following URL to download the most recent source code archive: https://github.com/aysmedia/domainmod/archive/master.zip  

   Then simply upload and unpack the new archive overtop of where you installed the previous version (or unpack it and then upload it, whichever you prefer). You should also delete the 'install' folder.  


## Changelog
Please see the CHANGELOG file that came with DomainMOD, or view the Changelog online at http://domainmod.org/changelog/


## Support
If you have any questions, comments, or bugs to report, please visit http://domainmod.org/support/


## Contribute
Up to this point all of the coding has been done by myself, but I am 100% open to other contributors. However, this is my first open source project and I believe there's still a lot of work to be done before DomainMOD is ready for multiple developers, so if you want to contribute you'll need to bear with me while I figure things out.

Feel free to send an email to contribute@domainmod.org if you would like to contribute.


## License
DomainMOD is an open source application written in PHP & MySQL used to track and manage your web resources.  
Copyright (C) 2010 Greg Chetcuti  

DomainMOD is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.  

DomainMOD is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.  

You should have received a copy of the GNU General Public License along with DomainMOD. If not, please see http://www.gnu.org/licenses/  

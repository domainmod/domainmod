[![DomainMOD](http://domainmod.org/images/logo.png)](http://domainmod.org)

[![Build Status](https://scrutinizer-ci.com/g/domainmod/domainmod/badges/build.png?b=master)](https://scrutinizer-ci.com/g/domainmod/domainmod/?branch=master) [![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/domainmod/domainmod/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/domainmod/domainmod/?branch=master)

Project Home: http://domainmod.org  
Project Demo: http://demo.domainmod.org  
Source Code: http://github.com/domainmod/domainmod/  

## About
DomainMOD is an open source application written in PHP & MySQL used to manage your domains and other internet assets in a central location. DomainMOD also includes a Data Warehouse framework that allows you to import your web server data so that you can view, export, and report on your live data. Currently the Data Warehouse only supports web servers running WHM/cPanel, but further support will be added in the future.


## Live Demo
Not sure if DomainMOD is what you're looking for? Don't want to take the time to install it only to find out that it's not? I hate when that happens myself, which is why I've setup a live demo so you don't waste your time.  

So go ahead, take the live demo for a test drive before you install! http://demo.domainmod.org  


## Downloading
There are currently two options for downloading DomainMOD. Whenever possible I recommend that you use option #2, the git repository download directly from your web server. Not only is it a nice quick install, but it makes upgrading a breeze.

1. Visit the following URL to download the most up-to-date version of DomainMOD: http://domainmod.org/dl/

2. Use git right from your web server to retrieve the source code. To do so, change to the directory where you want to install DomainMOD and run the following command:  

        git clone git://github.com/domainmod/domainmod.git  


## Installing
If you downloaded the .ZIP file in the previous step, you will now need to upload the archive to your web server and then unpack it into the folder where you wish to install (or unpack it and then upload it, whichever you prefer).

If you used git to retrieve the source code in the previous step, just change to the directory where you ran the git command and your files are already waiting for you in a folder called /domainmod/. Feel free to rename this folder to whatever you want.

1. Create a MySQL database that will be used to store DomainMOD's data.  

2. In the '_includes' folder, copy config.SAMPLE.inc.php to config.inc.php and then update config.inc.php to reflect your web server's settings.  

3. In a web browser, visit the URL where you just installed DomainMOD and then click the Install link. For example, example.com/dm/ or example.com/domainmod/. After a few seconds a message will appear letting you know that the installation was successful.  

If you have any problems during installation please see the Support section.


## Cron Job Installation (Highly Recommended)
DomainMOD includes a Task Scheduler that allows you to run various system jobs at specified times, which helps keep your DomainMOD installation up-to-date and running smoothly, as well as notifies you of important information, such as emailing you to let you know about upcoming Domain & SSL Certificate expirations.

The Task Scheduler is very powerful, and it enables features that you otherwise wouldn't be able to use, but in order for it to function you need to schedule the below cron/scheduled job to run on your web server. Once this file is setup to run, the Task Scheduler will be live.

NOTE: This file should be executed every 10 minutes.

    Filename: /cron.php


## Upgrading
WARNING: Before upgrading, it is strongly recommended that you make a backup of your DomainMOD installation directory and database. If something goes wrong during the upgrade there may be no recovering, and having a backup of your installation directory and database will allow you to easily restore your previous installation.

1. If you installed DomainMOD by downloading the .ZIP file, visit the following URL to download the most up-to-date version: http://domainmod.org/dl/  

   Once the download completes, upload and unpack the new archive overtop of where you installed the previous version (or unpack it and then upload it, whichever you prefer).  

2. If you installed DomainMOD using git right from your web server, just run the following command from within your installation directory to upgrade:  

        git pull  
    
    That's it! Upgrading with git is very easy, which is one of the reasons it's my recommended method for obtaining the DomainMOD source code.  


## Data Warehouse

DomainMOD has a Data Warehouse framework built right into it, which allows you to import the data stored on your web server. Currently the only web servers that are supported are ones that run WHM/cPanel, but I also plan to add support for Plesk once I've ironed out all the kinks in the framework and figured out Plesk's API, and hopefully one day you'll be able to import any web server into the Data Warehouse, regardless of whether or not it uses a control panel.

If your web server doesn't run WHM/cPanel, or you don't want to import your web server data into DomainMOD, you can ignore this section.

NOTE: Importing your web server(s) into the Data Warehouse will not modify any of your other DomainMOD data, nor any of the data on your server.

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


## Changelog
Please see the CHANGELOG file that came with DomainMOD, or view the Changelog online at http://domainmod.org/changelog/


## Support
If you have any questions, comments, or bugs to report, please visit http://domainmod.org/support/


## Contribute
Up to this point all of the coding has been done by myself, but I am 100% open to other contributors. However, this is my first open source project and I believe there's still a lot of work to be done before DomainMOD is ready for multiple developers, so if you want to contribute you'll need to bear with me while I figure things out.

Feel free to send an email to contribute@domainmod.org if you would like to contribute.


## License
DomainMOD is an open source domain and internet asset manager.  
Copyright (c) 2010-2016 Greg Chetcuti <greg@chetcuti.com>  

DomainMOD is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.  

DomainMOD is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.  

You should have received a copy of the GNU General Public License along with DomainMOD. If not, see http://www.gnu.org/licenses/.

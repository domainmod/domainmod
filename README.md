[![DomainMOD](https://cdn.domainmod.org/images/logo.png)](https://domainmod.org)

Project Home: <https://domainmod.org>  
Project Demo: <https://demo.domainmod.org>  
Source Code: <https://domainmod.org/source/>

About
------
DomainMOD is an open source application written in PHP & MySQL used to manage your domains and other internet assets in a central location. DomainMOD also includes a Data Warehouse framework that allows you to import your web server data so that you can view, export, and report on your live data. Currently the Data Warehouse only supports web servers running WHM/cPanel.

Live Demo
----------
Not sure if DomainMOD is what you're looking for? Don't want to take the time to install it only to find out that it's not? We hate when that happens ourselves, which is why we've setup a live demo so you don't waste your time.

So go ahead, take the live demo for a test drive before you install: <https://demo.domainmod.org>

Downloading
-------------
There are currently three options for downloading DomainMOD.

**Option #1**  
Visit the following URL to download DomainMOD in a .ZIP file: <https://domainmod.org/download/>

**Option #2**  
Use git from your web server to retrieve the DomainMOD source code. To do so, change to the directory where you want to install DomainMOD and run the following command (this download option requires that you have git installed on your web server):

    git clone https://github.com/domainmod/domainmod.git

**Option #3**  
Use Softaculous from your web server to download **and** install DomainMOD.  Softaculous is the web hosting industry's leading software auto-installer, and it has helped millions of users install applications with a few clicks of a mouse. Softaculous easily integrates into the leading control panels like cPanel, Plesk, DirectAdmin, InterWorx, H-Sphere, and more. Check your hosting control panel or contact your web host if you're not sure if they offer Softaculous.

To install DomainMOD simply open Softaculous, use the search feature to find DomainMOD, and then click the "Install Now" button. After you answer a few questions about your installation Softaculous will do the rest.

More Information: <http://www.softaculous.com/softaculous/apps/others/DomainMOD/>

Installing
---------
If you installed DomainMOD using Softaculous in the previous step you can ignore the rest of this *Installing* section, as you should already have DomainMOD up-and-running.

If you downloaded the .ZIP file in the previous step, you will now need to upload the archive to your web server and then unpack it into the folder where you wish to install (or unpack it and then upload it, whichever you prefer).

If you used git to retrieve the source code in the previous step, just change to the directory where you ran the git command and your files are already waiting for you in a folder called /domainmod/. Feel free to rename this folder to whatever you want.

**Installation Steps**

1. Create a MySQL database that will be used to store DomainMOD's data.

2. In the '_includes' folder, copy config.SAMPLE.inc.php to config.inc.php and then update config.inc.php to reflect your web server's settings.

3. In a web browser, visit the URL where you just installed DomainMOD, enter the requested information, and then click the Install button. For example, example.com/domainmod/. After a few seconds a message will appear letting you know that the installation was successful.

4. Setup the below cron job on your web server.

Cron Job
---------
DomainMOD includes a Task Scheduler that allows you to run various system jobs at specified times, which helps keep your DomainMOD installation up-to-date and running smoothly, as well as notifies you of important information, such as emailing you to let you know about upcoming Domain & SSL Certificate expirations.

The Task Scheduler is very powerful, and it enables features that you otherwise wouldn't be able to use, but in order for it to function you need to schedule the below cron job to run on your web server. Once the cron job is setup to run the Task Scheduler will be live.

**NOTE:** This file should be executed every 10 minutes.

    Filename: /cron.php

Security
--------
Although we've done our best to secure DomainMOD, unfortunately there are many factors that could cause security holes, such as the software being run on insecure hardware, software like PHP and MySQL having out-of-date versions with known vulnerabilities, easy-to-guess passwords being used, and so on. Due to these factors we recommend the following steps to help secure your DomainMOD installation.

1. Do not use easy-to-guess passwords. **Ever**.

2. Although DomainMOD has its own authentication system, we recommend that you also use HTTP authentication on your installation folder to add an extra layer of security.

3. Do not store your account passwords or API keys in DomainMOD. Although the ability to save this information exists, **use it at your own risk**. This information is fairly secure if you run DomainMOD on your local computer, but there's a much higher risk of someone gaining access to it if you host the site on a server that is accessible to the outside world.

    **WARNING:** Saving your API keys (and other relevant API connection information) in DomainMOD is necessary if you want to use the [Domain Queue](domain-queue.md), however we recommend that you only save this information temporarily while you're using the Domain Queue, and that you remove it as soon as you're done.

4. Do not host DomainMOD on a public website or on an easy-to-guess URL.

5. Do not give the URL to anyone who does not need to access DomainMOD. You should treat the URL like a password.

6. Always use the most up-to-date version of DomainMOD. This will help protect you from any security vulnerability that are found and fixed.

Upgrading
----------
**WARNING:** Before upgrading it's strongly recommended that you make a backup of your DomainMOD installation directory and database. If something goes wrong during the upgrade there may be no way to recover your data, and having a backup of your installation directory and database will allow you to easily restore your previous installation.

**Upgrade Option #1**  
If you installed DomainMOD by downloading the .ZIP file, visit the following URL to download the most up-to-date version: <https://domainmod.org/download/>

Once the download completes, upload and unpack the new archive overtop of where you installed the previous version (or unpack it and then upload it, whichever you prefer).

**Upgrade Option #2**  
If you installed DomainMOD using git right from your web server, just run the following command from within your installation directory to upgrade:

    git pull
    
That's it! Upgrading with git is very easy, which is one of the reasons it's our recommended method for obtaining the DomainMOD source code.

Data Warehouse
-----------------
DomainMOD has a Data Warehouse framework built right into it, which allows you to import the data stored on your web server. Currently the only web servers that are supported are ones that run WHM/cPanel.

If your web server doesn't run WHM/cPanel, or you don't want to import your web server data into DomainMOD, you can ignore this section.

**NOTE:** Importing your web server(s) into the Data Warehouse will not modify any of your other DomainMOD data, nor any of the data on your web server.

**Supported Data**  
The following WHM sections are currently supported, but our end goal is to have every piece of WHM information that can be retrieved via the API stored in the Data Warehouse.

**Accounts**  
Domain, IP Address, Owner, User, Contact Email, Plan, Theme, Shell, Partition, Disk Limit, Disk Usage, Max Addons, Max FTP Accounts, Max Email Lists, Max Parked Domains, Max POP Accounts, Max SQL Accounts, Max Subdomains, Creation Date, Suspend Status, Suspend Reason, Suspend Time, Max Email Per Hour, Failed Email % Before Defer, Min Failed Email # Before Defer

**DNS Zones**  
Zone Filename, Original/Primary Source of Zone Data, Admin Email, Serial #, Refresh, Retry, Expiry, Minimum TTL, Authoritative Name Server

**DNS Records**  
TTL, Class, Type, IP Address, CNAME, Mail Server, Mail Server Priority, TXT Data, Line # of Zone, # of Lines, RAW Data

Support
--------
For the DomainMOD documentation please visit <https://domainmod.org/docs/>, or access the /docs/ folder within your DomainMOD installation.

If you have any questions, comments, or bugs to report, please visit <https://domainmod.org/support/>.

Changelog
-----------
Please see the CHANGELOG file that came with DomainMOD, or view the Changelog online at <https://domainmod.org/changelog/>.

License
-------
DomainMOD is an open source domain and internet asset manager.  
Copyright (c) 2010-2017 Greg Chetcuti <greg@chetcuti.com>

DomainMOD is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.

DomainMOD is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with DomainMOD. If not, see <http://www.gnu.org/licenses/>.

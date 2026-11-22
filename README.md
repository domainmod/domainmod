[![DomainMOD](https://domainmod.org/images/logo.png)](https://domainmod.org)

[![ACR B1b](https://img.shields.io/badge/ACR-B1b-2140B5)](ACR.md)

Project Home: <https://domainmod.org>  
Project Demo: <https://demo.domainmod.org>  
Documentation: <https://domainmod.org/docs/>  
Support: <https://domainmod.org/support/>  
Source Code: <https://domainmod.org/source/>

About
-----
DomainMOD is a free, open source, self-hosted application for managing your domains, hosting, SSL certificates, and other internet assets in one place. DomainMOD also includes a Data Warehouse framework that allows you to import your web server data so that you can view, export, and report on your live data. Currently the Data Warehouse only supports web servers running WHM/cPanel.

Live Demo
---------
Not sure if DomainMOD is what you're looking for? Don't want to take the time to install it only to find out that it's not? We hate when that happens ourselves, which is why we've set up a live demo so you don't waste your time.

So go ahead, take the live demo for a test drive before you install: <https://demo.domainmod.org>

Requirements
------------
**Software**: PHP 8.2-8.5, MariaDB 10.4-12.3 or MySQL 5.7-9.7  
**PHP Extensions**: PDO (MariaDB/MySQL), cURL, OpenSSL, gettext, MySQLi  
**PHP Settings**: allow_url_fopen  
**Folders**: The web server can write to the 'temp' folder  

Downloading
-----------
There are currently four options for downloading DomainMOD.

**Option #1 -- Using Docker**

Please see our official DomainMOD container on Docker Hub for more information: https://hub.docker.com/r/domainmod/domainmod

**Option #2 -- Downloading a .zip file**   

Visit the following URL to download DomainMOD in a .zip file: <https://domainmod.org/download/>

**Option #3 -- Using Git**  

To download using Git, change to the directory that will hold DomainMOD and run the following command, which puts it in a new `domainmod` folder there (this download option requires that you have Git installed on your server):

    git clone https://github.com/domainmod/domainmod.git

**Option #4 -- Installing via Softaculous**  

Softaculous is the web hosting industry's leading software auto-installer, and it has helped millions of users install applications with a few clicks of a mouse. Softaculous easily integrates into the leading control panels like cPanel, Plesk, DirectAdmin, InterWorx, H-Sphere, and more. Check your hosting control panel or contact your web host if you're not sure if they offer Softaculous.

To install DomainMOD simply open Softaculous, use the search feature to find DomainMOD, and then click the "Install Now" button. After you answer a few questions about your installation Softaculous will do the rest.

More Information: <http://www.softaculous.com/softaculous/apps/others/DomainMOD/>

Installing
----------
If you installed DomainMOD using Docker or Softaculous in the previous step you can ignore the rest of this *Installing* section, as you should already have DomainMOD up-and-running.

If you downloaded the .zip file in the previous step, you will now need to upload the archive to your web server and then unpack it into the folder where you wish to install (or unpack it and then upload it, whichever you prefer).

If you used Git to retrieve the source code in the previous step, just change to the directory where you ran the git command and your files are already waiting for you in a folder called /domainmod/. Feel free to rename this folder to whatever you want.

**Installation Steps**

1. Create a MySQL database that will be used to store DomainMOD's data.

2. In the '_includes' folder, copy config.SAMPLE.inc.php to config.inc.php and then update config.inc.php to reflect your web server's settings (including the recommended `$secret_key` for MFA).

3. Make sure the web server can write to the 'temp' folder and the files in it, or restores are turned off. If your cron job runs as a different user, give both users write access to the folder.

4. In a web browser, visit the URL where you installed DomainMOD (for example, example.com/domainmod/) and work through the installer. When it's done, you'll be taken to the sign-in page.

5. Set up the cron job below on your web server.

Cron Job
--------
DomainMOD includes a Task Scheduler that allows you to run various system jobs at specified times, which helps keep your DomainMOD installation up-to-date and running smoothly, as well as notifies you of important information, such as emailing you to let you know about upcoming domain, hosting, and SSL certificate expirations.

The Task Scheduler is very powerful, and it enables features that you otherwise wouldn't be able to use, but in order for it to function you need to schedule the below cron job to run on your web server. Once the cron job is set up to run, the Task Scheduler will be live.

If you installed DomainMOD using Docker, the cron job will be set up and running automatically within the container, so there's nothing you need to set up manually.

**NOTE:** This file should be executed every 10 minutes.

    Filename: /cron.php

If your cron job runs cron.php from the command line, the simplest setup is to run it as the same user PHP runs as for your web server, so both can write to DomainMOD's 'temp' folder.

To stop anyone else from running cron.php, you can give it a key by adding `$cron_key = 'YourKeyHere';` (with a long random value) to config.inc.php. After that, cron.php only runs from the command line, or from a URL that includes the key, such as `/cron.php?key=YourKeyHere`. See the User Guide for details: <https://domainmod.org/docs/userguide/getting-started/#cron-job>

Security
--------
Although we've done our best to secure DomainMOD, unfortunately there are many factors that could cause security holes, such as the software being run on insecure hardware, software like PHP and MySQL having out-of-date versions with known vulnerabilities, easy-to-guess passwords being used, and so on. Due to these factors we recommend the following steps to help secure your DomainMOD installation.

1. Secure your installation with an SSL Certificate.

2. If your database is on a different server than DomainMOD, encrypt the connection and check the server's certificate by adding `$dbsslmode = 'verify';` to config.inc.php. See the User Guide for details: <https://domainmod.org/docs/userguide/database-encryption/>

3. Do not use easy-to-guess passwords. **Ever**.

4. Turn on MFA (multi-factor authentication) in **Settings** -> **Security**, and set `$secret_key` in config.inc.php (or `DOMAINMOD_SECRET_KEY` for Docker), ideally when you install, so DomainMOD encrypts each user's MFA secret. See config.SAMPLE.inc.php for details.

5. Although DomainMOD has its own authentication system, we recommend that you also use HTTP authentication on your installation directory to add an extra layer of security.

6. Do not store your account passwords or API keys in DomainMOD. Although the ability to save this information exists, **use it at your own risk**. This information is fairly secure if you run DomainMOD on your local computer, but there's a much higher risk of someone gaining access to it if you host the site on a server that is accessible to the outside world.

    **WARNING:** Saving your API keys (and other relevant API connection information) in DomainMOD is only necessary if you want to use your registrar's API to import domains through the Domain Queue or to refresh them (the Refresh button and the Refresh Domains task). Imports and refreshes using RDAP and WHOIS don't need them, but they can't read WHOIS privacy or auto renewal. If you don't need those, we recommend that you only save your API keys while you're using the Domain Queue, and remove them as soon as you're done.

7. Do not host DomainMOD on a public website or on an easy-to-guess URL.

   If you do end up hosting DomainMOD on a public website, DomainMOD asks search engines not to index its pages. Don't block its URL in your website's robots.txt file, since search engines then can't see that request, and your DomainMOD URL may still end up in public search results.

8. Do not give the URL to anyone who does not need to access DomainMOD. You should treat the URL like a password.

9. Always use the most up-to-date version of DomainMOD. This will help protect you from any security vulnerabilities that are found and fixed.

Upgrading
---------
**WARNING:** Before upgrading it's **strongly** recommended that you backup your DomainMOD installation directory and database. If something goes wrong during the upgrade there may be no way to recover your data, and having a backup of your installation directory and database will allow you to easily restore your previous version. The Backup & Restore documentation can be found here: <https://domainmod.org/docs/userguide/backup-restore/>

As of v4.27.0, administrators can open **Administration** -> **Upgrade** to see the installed and latest versions, and the upgrade steps for the way DomainMOD was installed. The steps for each method are also below.

**Option #1 -- Docker Install**  
If you installed DomainMOD using Docker, please see our official DomainMOD container on Docker Hub for upgrade information: <https://hub.docker.com/r/domainmod/domainmod>  

**Option #2 -- .zip File Install**  
If you installed DomainMOD by downloading the .zip file, visit the following URL to download the most up-to-date version: <https://domainmod.org/download/>

Once the download completes, upload and unpack the new archive overtop of where you installed the previous version (or unpack it and then upload it, whichever you prefer).

**Option #3 -- Git Install**  
If you installed DomainMOD using Git right from your web server, just run the following command from within your installation directory to upgrade:

    git pull
    
**Option #4 -- Softaculous Install**  
If you installed DomainMOD using Softaculous you're just a few clicks away from upgrading to the newest version. Please see the upgrade instructions on the Softaculous Wiki: <https://www.softaculous.com/docs/How_to_upgrade_installations>

**After Upgrading**  
To finish the upgrade, sign in as an administrator and click **Upgrade Database** when DomainMOD asks.

Unless you upgraded using Docker, restart PHP so the updated translation files are loaded (restart PHP-FPM if your server uses it, otherwise restart your web server). Otherwise some text may display incorrectly until it restarts. If you can't restart it yourself, your web hosting provider should be able to do it for you.

As of v4.27.0, the web server needs to be able to write to the 'temp' folder and the files in it (see the Installation Steps above). If it can't, DomainMOD shows a warning and turns off restores until it can.

As of v4.27.0, domains no longer have a Web Hosting Provider field. The upgrade turns each domain's old Web Hosting Provider into a hosting service named after the provider, in a new hosting account with the username "Imported" (one account and service for each provider and owner, shared by their domains), and links the domains to it. Domains set to a placeholder such as [no hosting] aren't linked. Check the imported services under Hosting after upgrading.

As of v4.27.0, if your users turn on MFA, we recommend adding `$secret_key` to config.inc.php with a long random value, so DomainMOD encrypts each user's MFA secret (Docker installs use the `DOMAINMOD_SECRET_KEY` variable). Secrets saved before the key was set are encrypted the next time each user signs in with a code from their authenticator app. Keep the key safe, since it isn't in DomainMOD's backups, and use the same value when you move or restore your installation. If it's lost or changed, users with MFA on need a recovery code or an administrator's reset to sign in.

Data Warehouse
--------------
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

Code of Conduct
---------------
Please see CODE_OF_CONDUCT.md for a detailed explanation of our code of conduct.

Contributing
------------
Please see CONTRIBUTING.md for our contributing guidelines, and CONTRIBUTORS.md for everyone who has contributed code.

Support
-------
For the DomainMOD documentation please visit <https://domainmod.org/docs/>, or access the /docs/ folder within your DomainMOD installation.

If you have any questions, comments, or bugs to report, please visit <https://domainmod.org/support/>.

Changelog
---------
Please see the CHANGELOG file that came with DomainMOD, or view the Changelog online at <https://domainmod.org/changelog/>.

License
-------
DomainMOD is an open source domain and internet asset manager.  
Copyright (c) 2010-2026 Greg Chetcuti <greg@chetcuti.com>

DomainMOD is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.

DomainMOD is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with DomainMOD. If not, see <http://www.gnu.org/licenses/>.

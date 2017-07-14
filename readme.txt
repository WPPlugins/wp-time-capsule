=== WP Time Capsule ===

Contributors: WPTimeCapsule, dark-prince, thamaraiselvam
Tags: amazon backup, amazon s3 backup, amazon, auto backup, auto updater, auto updates, back up, backup before update, backup files, backup guard, backup mysql, backup plugin, backup posts, backup tool, backup without zip, backup, backupguard, backups, clone, cloud backup, complete backup, database backup, db backup, db migration, dropbox backup, dropbox, duplicate, full backup, google drive backup, google drive, incremental migrate db, migrate, migration, real-time backup, real-time, restore, rollback, s3, scheduled backup, site backup, storage, synchronize, time, website backup, wordpress backup, wordpress incremental backup, wp backup, wp time capsule, wptc
Requires at least: 3.9.14
Tested up to: 4.8
Stable tag: 1.10.2

WP Time Capsule is an automated incremental backup plugin that backs up your website changes as per your schedule to Dropbox, Google Drive and Amazon S3.

== Description ==

[WP Time Capsule](https://wptimecapsule.com/ "Incremental Backup for WordPress") was created to ensure peace of mind with WP updates and put the fun back into WordPress. It uses the cloud apps' native file versioning system to detect  changes and backs up just the changed files and db entries to your account.


**How is WP Time Capsule different than other backup plugins?**

WPTC is unique in 3 ways -<br>
1. It backs up and restores only the changed files & DB and not the entire site every time.<br>
2. The files & DB are stored in your cloud storage app - Dropbox, Google Drive or Amazon S3.
3. We have used the cloud apps' native file versioning system to detect changes and maintain file versions. So backups and restores are as reliable as they get.
<br><br>
**How does it work?**
<br>
1. Install the plugin and login with your wptimecapsule.com account.<br>
2. Next, connect the cloud app where you want to store the backup files. You can use Dropbox, Google Drive or Amazon S3.<br>
3. Once you connect the cloud app, we will automatically begin backing up your complete website to your cloud app account.<br><br>
After the first full backup is taken, you can schedule a time for WPTC to backup your websites. We will take care of your backups from here on.
This being done you will officially be *disaster-ready*. :)
<br><br>
**Backup**: Looks for files & DB changes since the last backup and uploads only the changes. The data is then stored securely in your cloud app account.
<br>
**Restore**: Checks revision history and displays the same. You can restore the site to any point in time or restore specific files & DB.
<br><br>
**How is it better?**<br>
BACKUP METHOD<br>
Traditionally - Backups are compressed and zipped. The Bad: Heavy server resource consumption.<br>
WPTC - No zipping. Changed files are directly dropped into your cloud account. The Good: ***Uses considerably less server resources***
<br><br>
BACKUP FILE<br>
Traditionally - Multiple zip files are created every time you backup. The Bad: Precious storage space is wasted.<br>
WPTC - Backs up incrementally. No multiple copies of files. The Good: ***Uses far less disk space***
<br><br>
RESTORE<br>
Traditionally - Unzip backup and restore the whole site. The Bad: Consumes time and server resource.<br>
WPTC - Restores only selected files. The Good: ***Faster restore***
<br><br>
Visit us at [wptimecapsule.com](https://wptimecapsule.com/ "Incremental Backup for WordPress")

Credits: Michael De Wildt for his WordPress Backup to Dropbox plugin based on which this plugin is being developed.

== Installation ==
= Minimum Requirements =
 * PHP version 5.3.1 or greater (recommended: PHP 5.4 or greater)
 * MySQL version 5.0.15 or greater (recommended: MySQL 5.5 or greater)

= Installation =
Installing WP Time Capsule is simple and easy. Install it like any other WordPress plugin.
<ol>
  <li>Login to your WordPress dashboard, under Plugins click Add New</li>
  <li>In the plugin repository search for 'WP Time Capsule' or upload the plugin zip file and install it</li>
  <li>After installation, login with your wptimecapsule.com account</li>
  <li>Then, connect with the cloud app that you want to use to backup your site</li>
  <li>Once the cloud app is connected, you can schedule your backup time and we will begin backing up the website to your cloud app according to schedule.</li>
</ol>

== Screenshots ==

1. **Backup calendar view** - You can view and restore files + database from a calendar view.
2. **Restore specific files** - View a list of files that have changed and been backed up and selectively restore them.
3. **Warp back your site in time** - You can restore the complete site back to a specific point in time.

== Changelog ==

= 1.10.2(7 Jul 2017) =
* Fix: Backup has been getting stuck and never completed for few Dropbox users.
* Fix: Backups will be stopped automatically when Cloud authentication is failed preventing the backup to run endlessly.

= 1.10.1(27 June 2017) =
* Fix: Schedule backup wouldn't run in certain scenarios.

= 1.10.0(22 June 2017) =
* Feature: Whitelabling is now available.
* Feature: Vulnerable plugins and WordPress updates are updated automatically.
* Improvement: Updated logo on sub-menu.
* Improvement: Dropbox will show authentication page on adding a Dropbox account on multiple sites.
* Fix: Initial setup requirement check would fail in certain scenarios.

= 1.9.5(14 June 2017) =
* Fix: jQuery of WPTC would conflict with other plugins.

= 1.9.4(13 June 2017) =
* Improvement: Dropbox SDK migrated to API V2 from API V1.
* Improvement: Settings page has been revamped.
* Improvement: Auto-whitelist WPTC IP in some security plugins installed on your sites.
* Improvement: Old activity log and WPTC database table clean up to run automatically
* Improvement: Checking minimum requirements during WPTC installation.
* Fix: Restore from Google drive would encounter failures in certain scenarios.
* Fix: For certain users, Updating a plugin/theme via InfiniteWP caused issues in WPTC.
* Fix: Other minor fixes.

= 1.9.3(24 May 2017) =
* Feature: WP Time Capsule will capture IWP updates now.
* Feature: WP Time Capsule will support Premium Themes and Plugins update now.
* Improvement: Restore flow is improved.
* Improvement: Loading mechanism of our plugin is optimized.
* Improvement: Plugins and Themes updating mechanism is improved and exact error message will be shown if update get failed.
* Improvement: Naming of Backups is improved.
* Improvement: Google analytics files will be included by default.
* Fix: Shell SQL dump threw warning in some servers.
* Fix: Report issue was sent from WP admin email instead of WP Time capsule email.

= 1.9.2(10 May 2017) =
* Improvement: PHP-MySQL Dump has been revamped.
* Fix: ZeroClipboard was causing issue in FireFox fixed.

= 1.9.1(28 Apr 2017) =
* Fix: Discourage search engines from indexing this site option gets enabled for live sites in certain scenarios.

= 1.9.0(28 Apr 2017) =
* Feature: Multisite compatible when staging on same server.
* Feature: Subfolder installations are compatible with staging on same server.
* Feature: Compatibility with Windows Server added.
* Improvement: When staging on same server permalinks are reset to avoid htaccess redirection.
* Improvement: Search engines will no longer index staged sites.
* Improvement: Color of WP admin bar changed to differentiate between live and staging site.
* Improvement: Credentials transfer during bridge restore has been revamped.
* Improvement: Use existing tokens for backing up new sites to Google Drive.
* Improvement: Default backup will now exclude files larger than 2GB.
* Fix: Restore would fail in subfolder WordPress installations.
* Fix: Filenames starting with @ would fail to upload to Dropbox.
* Fix: Database will be uploaded to Dropbox with hash in filename in certain scenarios.
* Fix: Include / Exclude file tree does not work when open_basedir is enabled.
* Fix: Dropbox authentication would fail in certain scenarios.
* Fix: Footer div and settings page of other plugins / themes gets removed in certain scenario.
* Fix: In some servers temp dir fails to create on plugin activation.
* Fix: FTP credentials were shown upon plugin activation for some users.
* Fix: jQuery of WPTC would conflict with other plugins.
* Fix: Site slows down when WPTC is installed.

= 1.8.6(11 Apr 2017) =
* Fix: Include/Exclude contents not working in free plan sites.
* Fix: Different server staging completed confirmation email had wrong staging url.

= 1.8.5(10 Apr 2017) =
* Improvement: Include/Exclude contents refactored for better performance.
* Improvement: WP Time Capsule plugin files loading optimized.

= 1.8.4(6 Apr 2017) =
* Improvement: Users can now control the number of files and db to be copied per loop during staging.
* Fix: Verification of auto-updates fail in certain scenarios.

= 1.8.3(5 Apr 2017) =
* Improvement: Auto update failure email will be sent to users.
* Improvement: Emails will be sent when staging on the same server.
* Fix: Files having a size of zero bytes fail to upload to Google Drive.
* Fix: Pop-ups generated via WPTC are not visible when certain themes are applied.
* Fix: Plugin activation throws function ‘admin_notice_on_dashboard’ not found warning.
* Fix: Certain servers were not supporting MySQL dump.

= 1.8.2(30 Mar 2017) =
* Improvement : Hashing method improved for faster restores.
* Fix: Serialized links would not update in the internal staging.
* Fix: Htaccess is modified to respect permalinks during staging.
* Fix: Staging folders were created with wrong permission.
* Fix: Activating plugin causes infinite redirects in certain scenarios.
* Fix: AJAX calls were conflicting with some plugins in certain scenarios.
* Fix: Database Dump has been introduced for faster DB backups.
* Fix: Login was not working with special characters in the password.
* Fix: WPTC was throwing some warnings on multisite.
* Fix: Added some cache/log files in the default excluding list.
* Fix: Other minor fixes.

= 1.8.1(23 Mar 2017) =
* Fix: Intermittent cURL errors causes the WordPress dashboard to go down.
* Fix: Cloud authentication is revoked when API throws an internal server error.
* Fix: Plugin would not proceed past the initial setup if temp file could not be created.

= 1.8.0(14 Mar 2017) =
* Feature : Auto Update Plugin/Theme manager launched.
* Feature : Stage an Update launched.
* Feature : Staging is now possible in the same server as the live site.
* Improvement : Backup before update has been fully reconfigured.
* Improvement : Progress bar is redesigned to reduce CPU overload.
* Improvement : Admin status bar with backup status has been removed.
* Fix : The plugin was conflicting with buddypress on earlier versions.
* Fix : Backup before update was affecting wordpress core functionalites.

= 1.7.2(16 Feb 2017) =
* Improvement : Revisioning system is modified to ensure plugin doesn’t backup data older than 30 days.
* Improvement : Old data revisions will be deleted from the database to free up space.
* Fix : Dropbox was conflicting with other plugins.
* Fix : Scheduled backup timing was configured incorrectly on certain servers.
* Fix : Files having a size of zero bytes would not be backed up.
* Fix : Backup before update model pop up would show during plugin installation instead of plugin update.
* Fix : Translation updates would fail during the backup.
* Fix : Dropbox authentication would fail on some servers.
* Fix : While staging sites, changing db prefix would fail or not update correctly.
* Fix : While staging sites on the same server, the staging process would fail.
* Fix : While staging sites, metadata upload would fail for larger sites.

= 1.7.1(23 Jan 2017) =
* Fix : Creating temp directory was unsuccessful in certain scenarios.

= 1.7.0(20 Jan 2017) =
* Feature : Introduced Staging, Now you can clone your wordpress site right from the WP Time Capsule plugin.
* Fix : When installing plugin from wordpress dashboard, the dialog box for “Backup before updates” was triggered.
* Fix : Updating WP Time Capsule plugin from third party services did not work as expected.

= 1.6.1(9 Jan 2017) =
* Fix : Restore did not start in certain scenario.

= 1.6.0(9 Jan 2017) =
* Feature : Introduced Backup Before Update which backs up your site automatically before each update.
* Fix : If the WPTC plugin is updated when a current backup is running, the backup gets stalled.
* Fix : Users were unable to see the Restore pop-up while restoring their sites.
* Fix : For certain users, uploading files to Amazon S3 would encounter failures.
* Fix : When open_basedir is enabled, CURL calls throw warnings.

= 1.5.3(22 Nov 2016) =
* Improvement : Improved Hashing for tracking file changes.
* Improvement : New files and folders are included in default exclude files list.
* Improvement : Error reporting has been improved.
* Fix : Excluding default cache files would stuck in certain scenarios for larger sites.
* Fix : Memory leak during backup is handled.
* Fix : Getting wrong home dir in exclude/include file tree.

= 1.5.2(9 Nov 2016) =
* Fix: Plugin doesn't work for WordPress sites running PHP version lower than 5.4

= 1.5.1(7 Nov 2016) =
* Improvement : Plugin-Server communication method had been improved and security patches have been applied.
* Improvement : Backups have been optimized to ensure files are handled better.
* Improvement: Added a few cache files and folders to the default exclude list.
* Fix : Restore would fail while creating recursive folders. (Thanks to Donna Cavalier for helping us fix this issue)
* Fix : Data for revisions of files larger than 5MB was logged incorrectly in the db.
* Fix : File monitor would generate incorrect results for certain scenarios and logging has been improved.
* Fix : Anonymous data was set to ON by default. (We respect our user's privacy, thanks to M Asif Rahman for bringing this to our notice)

= 1.5.0(27 Oct 2016) =
* Improvement : Calendar page showing backups is much responsive for smaller screens than before.
* Improvement : Calendar page now shows restore points instead of backup count for better understanding.
* Improvement : Initial setup flow and UI has been revamped.
* Fix : Backup wouldn’t run when files didn’t have sufficient read permissions. (Thanks to Pierre Sudarovich and Dennis Spengler for helping us fix this error)
* Fix : In certain scenarios while including or excluding files, the file size would be incorrect.
* Fix : Expanding the file tree when including or excluding files would display a load error. (Thanks to Pierre Sudarovich and Piet Bos for helping us fix this error)
* Fix : MySQL warning would be generated in certain scenarios. (Thanks to Doug Rider for helping us fix this error)
* Fix : Non Admin users were able to view the backup status on the Admin bar earlier.


= 1.4.6(7 Oct 2016) =
* Feature : File Change Monitor - When one or more files get backed up continuously during 3 consecutive backups, you will be notified of such activity.
* Improvement : Plugin compatibility for new and improved Dashboard
* Improvement : Files and database can be included / excluded before initiating first backup.
* Improvement :  File and database size will be shown for included items.
* Improvement : Sitemap.xml and Favicon.ico files will be backed up along with default files.
* Fix : SSL communication issues with cURL has been fixed.
* Fix : The server would ping the sites for backup even after being deactivated.
* Fix : WP-Content folder was excluded by default in certain scenarios.
* Fix : Backups would get stuck midway due to conflict with some excluded files.

= 1.4.5(16 Sep 2016) =
* Fix : Before scheduling the backup time and timezone, the backup would get started on Google Drive and Dropbox.

= 1.4.4(16 Sep 2016) =
* Improvement : Plugin will now support Network Admin mode.
* Improvement : Restore mechanism will now ignore warnings.
* Fix : Exclude / Include option did not work well for new files in certain scenarios.
* Fix : There were memory leaks while showing the backup status.
* Fix : Schedule backup was not happening in certain scenarios.

= 1.4.3(6 Sep 2016) =
* Fix : The default excluded files would get backed up in certain scenarios.

= 1.4.2(6 Sep 2016) =
* Improvement : Customized wp-content folder is now supported.
* Improvement : Backup stuck during a scheduled backup will be smartly removed.
* Improvement : Performance enhancement for Exclude/Include file and folder operations.
* Fix : Exclude/Include file tree would get stuck when the file has insufficient permissions.
* Fix : Backup would freeze when a table is removed from the database during the backup.
* Fix : When Visiting Site from WP Dashboard, checking backup status indicator remains static.
* Fix : Certain files when specifically included in the backup do not get backed up.

= 1.4.1(24 Aug 2016) =
* Improvement : Notification is shown when the minimum PHP requirement is not met for cloud repositories.
* Fix : The status bar would show incorrect backup tables count in certain scenarios.
* Fix : Few tables were missing from the database backup in earlier versions.
* Fix : If a new table gets added to the database during backup, the backup would get stuck midway.
* Fix : Google drive would conflict with other plugins using the same files / library as WPTC.

= 1.4.0(18 Aug 2016) =
* Improvement : Plugin-Server communication method had been changes to make backups reliable than before.
* Improvement : Backups stuck midway will be cleared in the subsequent scheduled backup call.
* Improvement : If the site goes unreachable during backup, it can be manually resumed from the Settings menu.
* Improvement : Plugin authentication mechanism has been improved.
* Fix : Bridge restore file would show incorrect backup date in earlier versions.

= 1.3.1(9 Aug 2016) =
* Improvement : Metadata is backed up only when the plugin needs it.
* Improvement : Schedule backup time zone will be auto-populated from time zone set on the WordPress site for new users.
* Improvement : Notice message for communication failure between Plugin and server has been updated.
* Improvement : Security patches have been applied for sites backed up to Amazon S3 storage.
* Fix : Activity log was showing incorrect schedule time for backups.
* Fix : During a backup, the plugin would check for database changes before every call which is unwanted.
* Fix : Files having size greater than 2GB could not be uploaded to Google Drive in certain scenarios.

= 1.3.0(4 Aug 2016) =
* Feature: File Tree view is now available when Including & Excluding files and folders.
* Feature: You can now include and exclude WordPress tables from backups.
* Improvement : The plugin will backup only the WordPress core files, folders & tables by default.

= 1.2.0(22 Jul 2016) =
* Improvement : Restore operation will be retried when some exceptions occur.
* Improvement : Plugin and server communication has been improved for restore operation.
* Improvement : Memory optimization is done to avoid memory leak during restore.
* Fix : Dropbox had compatibility issues with servers running PHP 7.
* Fix : Plugin authorization with Dropbox was failing intermittently.
* Fix : Scheduled backups taken on a particular day would show the previous day on the calendar.
* Fix : Scheduled and manual backups were not working after a failed restore operation.
* Fix : Backups would get stuck on 100% due to file permission issues on the plugin.
* Fix : Exclusion of files and folders was not working on some servers.
* Fix : Restore process popup would throw a 404 error out of nowhere.

= 1.1.4(15 Jul 2016) =
* Fix : Incorrect data was being sent during the restore process.

= 1.1.3(15 Jul 2016) =
* Improvement : Communication between plugin and the server has been greatly improved.
* Fix : Email gets triggered twice on certain scenarios
* Fix : If a backup is stopped manually before completion, a first backup completed email is triggered
* Fix : A backup is stopped when any changes are made on the settings menu.

= 1.1.2(5 Jul 2016) =
* Fix : Progress would get stalled in the middle of a backup.

= 1.1.1(4 Jul 2016) =
* Improvement: Cron code has been changed to make Cron more reliable than before.

= 1.1.0(29 Jun 2016) =
* Improvement: Backup time for Google Drive has been greatly reduced.
* Fix: Earlier version had a few SQL backup bugs.

= 1.0.0(23 Jun 2016) =
* Improvement: Migrated from WordPress cron service to custom WP Time Capsule cron service.
* Improvement: Confirmation pop up before performing restore.
* Improvement: Backup Stability has been improved.
* Improvement: Initial Setup has been improved.
* Improvement: Minor UI changes, bug fixes and improvements

= 1.0.0RC3(9 Jun 2016) =
* Improvement: Smarter system implemented for AJAX calls to reduce frequent calls.
* Improvement: Lazy load implemented for the activity log and overall loading time improved.
* Improvement: Status bar will now show the last successful backup time.
* Improvement: During backup, the status bar will now show the file count in process.
* Improvement: Users will now be able to exclude files and schedule backup time on initial setup itself.
* Improvement: Error messages shown in the Amazon S3 UI have been improved.
* Improvement: Minor bugs have been fixed to improve the overall UI.
* Fix: Existing backup data would not get deleted when authorizing a different account within the same cloud service.
* Fix: Backups would stop midway due to fatal errors on other third party plugins.
* Fix: During backup, the status bar was showing incorrect data during DB sync for some users.
* Fix: Error messages were not displayed during restore via bridge file.
* Fix: The bridge file restore process would get stuck when temp folder was missing.
* Fix: Backups older than 30 days were not automatically deleted.

= 1.0.0RC2(19 May 2016) =
* Fix: Error messages populated by Amazon S3 would show multiple times.
* Fix: Scheduled backups would get stuck randomly when the WP site is not kept open.

= 1.0.0RC1(13 May 2016) =
* Improvement: Scheduled backup is the default backup option replacing auto-backup.
* Improvement: File and folder exclusions are possible during backup.
* Improvement: Lazy load has been implemented in the Calendar View.
* Improvement: Restore Method has been optimized for low memory consumption.
* Improvement: Cron is faster than before with reduced interval times.
* Improvement: Users will be notified of errors encountered during backups via email.
* Improvement: Backups disconnected midway can be continued from the same point using a link sent via email.
* Improvement: Progress details for DB is shown during DB backups instead of overall progress.
* Improvement: Metadata of backups is now stored in the Cloud.
* Improvement: Google Oauth is improved, Users can use their email to connect to Google Drive.
* Fix: Minor bug fixes to improve speed and accuracy of backups.

= 1.0.0beta5.2(28 Apr 2016) = 
* Fix: A Memory leak was found.

= 1.0.0beta5.1(25 Apr 2016) = 
* Improvement: Auto backup will not scan all files, instead it will look for Media file changes, Database changes and Plugin & Theme changes. 
* Improvement: Backup Method optimized for low memory consumption. 
* Improvement: Backup process now exculdes few other backup plugins' backups.

= 1.0.0beta4.4(23 Feb 2016) =
* Fix: Auto backup halted if the plugin was updated when a backup was running.

= 1.0.0beta4.3(22 Feb 2016) =
* Improvement: CPU usage optimized.
* Fix: More than one backup process was triggered by cron at the same time.
* Fix: Backup calls were running for a long period without closing on timeout.

= 1.0.0beta4.2(18 Feb 2016) =
* Fix: "Expecting a file upload" error for PHP 5.6 and above.

= 1.0.0beta4.1(16 Feb 2016) =
* Fix: Specified key was too long bug.

= 1.0.0beta4(12 Feb 2016) =
* Improvement: Support for Amazon S3.
* Improvement: We have used WordPress default collation for creating new db tables.

= 1.0.0beta3.1(5 Feb 2016) =
* Fix: Stopping a running backup by clicking Stop Backup link in Settings page disabled the cron.
* Fix: Removing a site from account page removed the cron from the server, but didn't get re-enabled when logging in the plugin.
* Fix: When logged out and in from the plugin, cron got registered two or more times.
* Fix: Google Drive error didn't get removed.
* Improvement: Improved calendar view.
* Improvement: Displaying last sync'd time when hovering over "Backups are up to date" in admin bar.

= 1.0.0beta3(1 Feb 2016) =
* Improvement: Support for Google Drive.
* Improvement: Backups are now fully automated. All changes are continuously backed up.
* Improvement: During the initial backup, the plugin screen is not blocked now. You can close the tab. We will email you once the backup is done.
* Fix: Bug fixes

= 1.0.0beta2(13 May 2015) =
* Improvement: UI improvements
* Fix: Bug fixes

= 1.0.0beta1(27 Apr 2015) =
* Beta release

= 1.0.0alpha5 =
* Feature: Backup scheduling added
* Improvement: Report sending added
* Improvement: Activity log added
* Improvement: UI improvements
* Fix: Bug fixes

= 1.0.0alpha4 =
* Improvement: Background backup process
* Fix: Bug fixes

= 1.0.0alpha3 =
* Initial release.
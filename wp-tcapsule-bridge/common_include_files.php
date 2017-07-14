<?php

class Common_Include_Files{
	protected $type;
	protected $dir_path;
	public function __construct($type){
		$this->type = $type;
		$this->dir_path = dirname(__FILE__). '/';
	}
	public function init($meta_restore = 0){
		if ($this->type == 'wptc-ajax') {
			require_once $this->dir_path."wp-tc-config.php";
		} else if($this->type == 'tc-init'){
			@require_once $this->dir_path."wp-tc-config.php";
			@require_once $this->dir_path."wp-db-custom.php";
			@require_once $this->dir_path."wp-modified-functions.php";
			@require_once $this->dir_path.'wptc-constants.php';
			@require_once $this->dir_path."Base.php";
			@require_once $this->dir_path.'common-functions.php';
			//@require_once $this->dir_path.'Classes/Processed/Base.php';	//for tc-init.php separate Base.php file is used

			@require_once $this->dir_path.'Classes/Processed/Files.php';
			@require_once $this->dir_path.'Classes/Processed/Restoredfiles.php';
			@require_once $this->dir_path.'Classes/Processed/DBTables.php';
			@require_once $this->dir_path.'Classes/FileList.php';
			@require_once $this->dir_path.'Classes/Factory.php';
			@require_once $this->dir_path.'Classes/Config.php';
			@require_once $this->dir_path.'Classes/DebugLog.php';

			@require_once $this->dir_path.'wp-files/class-wp-error.php';
			@require_once $this->dir_path.'utils/g-wrapper-utils.php';

			if(empty($meta_restore)){
				@require_once $this->dir_path.'wp-files/file.php';
				@require_once $this->dir_path.'wp-files/class-wp-filesystem-base.php';
				@require_once $this->dir_path.'wp-files/class-wp-filesystem-direct.php';
				@require_once $this->dir_path.'wp-files/class-wp-filesystem-ftpext.php';
				@require_once $this->dir_path.'wp-files/class-wp-filesystem-ssh2.php';
				@require_once $this->dir_path.'wp-files/class-wp-filesystem-ftpsockets.php';
			}
			return ;
		}
		require_once $this->dir_path."wp-db-custom.php";
		require_once $this->dir_path."wp-modified-functions.php";
		require_once $this->dir_path.'wptc-constants.php';
		require_once $this->dir_path.'Classes/Sentry/SentryConfig.php';

		require_once $this->dir_path.'common-functions.php';
		require_once $this->dir_path.'Dropbox/Dropbox/API.php';
		require_once $this->dir_path.'Dropbox/Dropbox/Exception.php';
		require_once $this->dir_path.'Dropbox/Dropbox/OAuth/Consumer/ConsumerAbstract.php';
		require_once $this->dir_path.'Dropbox/Dropbox/OAuth/Consumer/Curl.php';
		require_once $this->dir_path.'utils/g-wrapper-utils.php';
		require_once $this->dir_path.'Classes/Extension/Base.php';
		require_once $this->dir_path.'Classes/Extension/Manager.php';
		require_once $this->dir_path.'Classes/Extension/DefaultOutput.php';
		require_once $this->dir_path.'Classes/Extension/GdriveOutput.php';
		require_once $this->dir_path.'Classes/Processed/Base.php';
		require_once $this->dir_path.'Classes/Processed/Files.php';
		require_once $this->dir_path.'Classes/Processed/Restoredfiles.php';
		require_once $this->dir_path.'Classes/Processed/DBTables.php';
		require_once $this->dir_path.'Classes/DatabaseBackup.php';
		require_once $this->dir_path.'Classes/FileList.php';
		require_once $this->dir_path.'Classes/DropboxFacade.php';
		require_once $this->dir_path.'Classes/Config.php';
		require_once $this->dir_path.'Classes/BackupController.php';
		require_once $this->dir_path.'Classes/Logger.php';
		require_once $this->dir_path.'Classes/DebugLog.php';
		require_once $this->dir_path.'Classes/Factory.php';
		require_once $this->dir_path.'Classes/UploadTracker.php';

		require_once $this->dir_path.'wp-files/class-wp-error.php';
		require_once $this->dir_path.'wp-files/file.php';
		require_once $this->dir_path.'wp-files/class-wp-filesystem-base.php';
		require_once $this->dir_path.'wp-files/class-wp-filesystem-direct.php';
		require_once $this->dir_path.'wp-files/class-wp-filesystem-ftpext.php';
		require_once $this->dir_path.'wp-files/class-wp-filesystem-ssh2.php';
		require_once $this->dir_path.'wp-files/class-wp-filesystem-ftpsockets.php';
		if (is_php_version_compatible_for_g_drive_wptc()) {
			require_once $this->dir_path.'Google/autoload.php';
			require_once $this->dir_path.'Google/GoogleWPTCWrapper.php';
			require_once $this->dir_path.'Classes/GdriveFacade.php';
		}

		if (is_php_version_compatible_for_s3_wptc()) {
			require_once $this->dir_path.'S3/autoload.php';
			require_once $this->dir_path.'S3/s3WPTCWrapper.php';
			require_once $this->dir_path.'Classes/S3Facade.php';
		}
		$this->include_primary_files_wptc();
	}

	public function start_sentry_restore_logs(){
		require_once $this->dir_path.'Classes/Sentry/SentryRestore.php';
	}

	public function include_primary_files_wptc(){
		include_once $this->dir_path.'Base/Factory.php';
		include_once $this->dir_path.'Base/init.php';
		include_once $this->dir_path.'Base/Hooks.php';
		include_once $this->dir_path.'Base/HooksHandler.php';
		include_once $this->dir_path.'Base/Config.php';

		include_once $this->dir_path.'Base/CurlWrapper.php';

		include_once $this->dir_path.'Classes/CronServer/Config.php';
		include_once $this->dir_path.'Classes/CronServer/CurlWrapper.php';

		include_once $this->dir_path.'Classes/WptcBackup/init.php';
		include_once $this->dir_path.'Classes/WptcBackup/Hooks.php';
		include_once $this->dir_path.'Classes/WptcBackup/HooksHandler.php';
		include_once $this->dir_path.'Classes/WptcBackup/Config.php';

		include_once $this->dir_path.'Classes/Common/init.php';
		include_once $this->dir_path.'Classes/Common/Hooks.php';
		include_once $this->dir_path.'Classes/Common/HooksHandler.php';
		include_once $this->dir_path.'Classes/Common/Config.php';

		include_once $this->dir_path.'Classes/Analytics/init.php';
		include_once $this->dir_path.'Classes/Analytics/Hooks.php';
		include_once $this->dir_path.'Classes/Analytics/HooksHandler.php';
		include_once $this->dir_path.'Classes/Analytics/Config.php';
		include_once $this->dir_path.'Classes/Analytics/BackupAnalytics.php';

		include_once $this->dir_path.'Classes/ExcludeOption/init.php';
		include_once $this->dir_path.'Classes/ExcludeOption/Hooks.php';
		include_once $this->dir_path.'Classes/ExcludeOption/HooksHandler.php';
		include_once $this->dir_path.'Classes/ExcludeOption/Config.php';
		include_once $this->dir_path.'Classes/ExcludeOption/ExcludeOption.php';

		include_once $this->dir_path.'Classes/Sentry/init.php';
		include_once $this->dir_path.'Classes/Sentry/Hooks.php';
		include_once $this->dir_path.'Classes/Sentry/HooksHandler.php';
		include_once $this->dir_path.'Classes/Sentry/Config.php';
		include_once $this->dir_path.'Classes/Sentry/Sentry.php';
		WPTC_Base_Factory::get('Wptc_Base')->init();
	}
}


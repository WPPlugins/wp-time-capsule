<?php

class WPTC_FileList {
	private static $ignored_patterns = array(
		'.DS_Store', 'Thumbs.db', 'desktop.ini',
		'.git', '.gitignore', '.gitmodules',
		'.svn', '.dropbox',
		'.sass-cache', 'cgi-bin',
		'error_log', 'DE_cl.php', 'BRIDGE-SQL-LOG.txt',
	);

	private $cached_user_extensions;

	public function __construct() {
		$this->db = WPTC_Factory::db();
	}

	public function get_user_excluded_extensions_arr() {
		$config = WPTC_Factory::get('config');
		$raw_extenstions = $config->get_option('user_excluded_extenstions');
		if (!$raw_extenstions) {
			return false;
		}

		$excluded_extenstions = array();
		$extensions = explode(',', $raw_extenstions);
		foreach ($extensions as $extension) {
			if (empty($extension)) {
				continue;
			}
			$excluded_extenstions[] = trim($extension);
		}

		return $excluded_extenstions;
	}

	public function in_ignore_list($file) {
		if (empty($this->cached_user_extensions)) {
			$user_excluded_extenstions = $this->get_user_excluded_extensions_arr();
			$this->cached_user_extensions = $user_excluded_extenstions;
		} else {
			$user_excluded_extenstions = $this->cached_user_extensions;
		}

		if (empty($user_excluded_extenstions) || !is_array($user_excluded_extenstions)) {
			$final_ignore_patterns = self::$ignored_patterns;
		} else {
			$final_ignore_patterns = array_merge($user_excluded_extenstions, self::$ignored_patterns);
		}

		foreach ($final_ignore_patterns as $pattern) {
			if (preg_match('/' . preg_quote($pattern) . '/', $file)) {
				return true;
			}
		}
	}
}

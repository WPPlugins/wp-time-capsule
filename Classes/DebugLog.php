<?php

class WPTC_Debug_Log {
	private $user_time;
	private $system_time;
	private $memory_in_mb;
	private $memory_peak_in_mb;
	private $backup_id;
	private $db;

	public function __construct(){
		$this->user_time = WPTC_Factory::get('config')->cnvt_UTC_to_usrTime($this->get_current_time());
		$this->system_time = $this->get_current_time();
		$this->memory_in_mb = $this->get_memory_usage();
		$this->memory_peak_in_mb = $this->get_peak_memory_usage();
		$this->backup_id = getTcCookie('backupID');
		$this->db = WPTC_Factory::db();
	}

	public function wptc_log_now($log, $type = ''){

		$type = empty($type) ? 'NOT-SPECIFIC' : $type ;
		// dark_debug($type, '---------$type------------');
		$result = $this->db->insert($this->db->base_prefix . "wptc_debug_log", array(
			'user_time' => $this->user_time,
			'system_time' => $this->system_time,
			'type' => $type,
			'log' => $log,
			'backup_id' => $this->backup_id,
			'memory' => $this->memory_in_mb,
			'peak_memory' => $this->memory_peak_in_mb,
		));
		// dark_debug($result, '---------$result------------');
	}

	public function delete_old_debug_logs() {
		$days_before = time() - (60 * 60 * 24 * 2); // 2 days old logs
		$this->db->get_results("DELETE FROM {$this->db->base_prefix}wptc_debug_log WHERE user_time < '$days_before'");
	}

	public function get_logs($offset = 0, $limit = 100) {
		dark_debug($offset, '---------$offset------------');
		dark_debug($limit, '---------$limit------------');
		$logs = $this->db->get_results("SELECT * FROM {$this->db->base_prefix}wptc_debug_log ORDER BY `id` ASC LIMIT ".$offset." , ".$limit."");
		if (!empty($logs)) {
			dark_debug(json_encode((array)$logs, 1), '---------json_encode((array)$this_name)------------');
			send_response_wptc('', '', (array) $logs, $is_log = 1);
		}
		send_response_wptc('', '', array(), $is_log = 1);
	}

	private function get_current_time(){
		return time();
	}

	private function get_memory_usage(){
		return round((memory_get_usage() / 1048576), 2);
	}

	private function get_peak_memory_usage(){
		return round((memory_get_peak_usage() / 1048576), 2);
	}
}
<?php
/**
* A class with functions the perform a backup of WordPress
*
* @copyright Copyright (C) 2011-2014 Awesoft Pty. Ltd. All rights reserved.
* @author Michael De Wildt (http://www.mikeyd.com.au/)
* @license This program is free software; you can redistribute it and/or modify
*          it under the terms of the GNU General Public License as published by
*          the Free Software Foundation; either version 2 of the License, or
*          (at your option) any later version.
*
*          This program is distributed in the hope that it will be useful,
*          but WITHOUT ANY WARRANTY; without even the implied warranty of
*          MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*          GNU General Public License for more details.
*
*          You should have received a copy of the GNU General Public License
*          along with this program; if not, write to the Free Software
*          Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110, USA.
*/

class WPTC_DatabaseBackup {
	const SELECT_QUERY_LIMIT = 300;
	const WAIT_TIMEOUT = 600; //10 minutes
	const NOT_STARTED = 0;
	const COMPLETE = 1;
	const IN_PROGRESS = 2;

	private
	$temp,
	$database,
	$config,
	$exclude_class_obj;
	private $db_meta_data_tables;

	public function __construct($processed = null) {
		$this->database = WPTC_Factory::db();
		$this->config = WPTC_Factory::get('config');
		$this->processed = $processed ? $processed : new WPTC_Processed_DBTables();
		$this->exclude_class_obj = WPTC_Base_Factory::get('Wptc_ExcludeOption');
		$this->set_wait_timeout();
		$this->db_meta_data_tables = array(
										$this->database->prefix.'wptc_options',
										$this->database->prefix.'wptc_backups',
										$this->database->prefix.'wptc_backup_names',
										$this->database->prefix.'wptc_processed_restored_files',
										$this->database->prefix.'wptc_processed_files',
										$this->database->prefix.'wptc_debug_log',
									);
	}

	public function get_status() {
		if ($this->processed->count_complete() == 0) {
			return self::NOT_STARTED;
		}
		$processed_files = WPTC_Factory::get('processed-files');
		$count = $processed_files->get_overall_tables();
		if ($this->processed->count_complete() <= $count) {
			return self::IN_PROGRESS;
		}
		return self::COMPLETE;
	}

	public function get_file($db_meta_data = null) {
		if ($db_meta_data == 1) {
			$file = rtrim($this->config->get_backup_dir(), '/') . '/' . DB_NAME . "-db_meta_data.sql";
		} else {
			$file = rtrim($this->config->get_backup_dir(), '/') . '/' . DB_NAME . "-backup.sql";
		}

		$files = glob($file . '*');
		if (isset($files[0])) {
			return $files[0];
		}

		$prepared_file_name = $file . '.' . WPTC_Factory::secret(DB_NAME);
		return $prepared_file_name;
	}

	private function set_wait_timeout() {
		$this->database->query("SET SESSION wait_timeout=" . self::WAIT_TIMEOUT);
	}

	private function write_db_dump_header() {

		if($this->config->choose_db_backup_path() == false){
			$alternative_dump_location = $this->config->get_alternative_backup_dir();
			$msg = sprintf(__("A database backup cannot be created because WordPress does not have write access to '%s', please ensure this directory has write access.", 'wptc'), $alternative_dump_location_tmp);
				WPTC_Factory::get('logger')->log($msg);
				return false;
		}

		//clearing the db file for the first time by simple logic to clear all the contents of the file if it already exists;
		$fh = fopen($this->get_file(), 'a');
		if (ftell($fh) < 2) {
			fclose($fh);
			$fh = fopen($this->get_file(), 'w');
		}
		fwrite($fh, '');
		fclose($fh);

		$blog_time = strtotime(current_time('mysql'));

		$this->write_to_temp("-- WP Time Capsule SQL Dump\n");
		$this->write_to_temp("-- Version " . WPTC_VERSION . "\n");
		$this->write_to_temp("-- https://wptimecapsule.com\n");
		$this->write_to_temp("-- Generation Time: " . date("F j, Y", $blog_time) . " at " . date("H:i", $blog_time) . "\n\n");
		$this->write_to_temp("
/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;\n\n");
		$this->write_to_temp("CREATE DATABASE IF NOT EXISTS " . DB_NAME . ";\n");
		$this->write_to_temp("USE " . DB_NAME . ";\n\n");

		$this->persist();

		$this->processed->update_table('header', -1);
	}

	public function execute() {

		$starting_db_backup_time = microtime(true);

		if (!$this->processed->is_complete('header')) {
			$this->write_db_dump_header();
		}
		$backup_id = getTcCookie('backupID');
		// $tables = $this->database->get_results('SHOW TABLES', ARRAY_N);
		$processed_files = WPTC_Factory::get('processed-files');
		$wp_tables = $processed_files->get_all_tables();
		foreach ($wp_tables as $tableName) {
			$excluded = $this->exclude_class_obj->is_excluded_table($tableName);
			if ($excluded) {
				continue;
			}
			if (!$this->processed->is_complete($tableName)) {
				if (is_wptc_table($tableName)) {
					$this->processed->update_table($tableName, -1); //Done
					continue;
				}
				$table = $this->processed->get_table($tableName);

				$count = 0;
				if ($table) {
					$count = $table->count;
				}

				if ($count > 0) {
					WPTC_Factory::get('logger')->log(sprintf(__("Resuming table '%s' at row %s.", 'wptc'), $tableName, $count), 'backup_progress', $backup_id);
				}
				$this->backup_database_table($tableName, $count, $starting_db_backup_time);
				WPTC_Factory::get('logger')->log(sprintf(__("Processed table %s.", 'wptc'), $tableName), 'backup_progress', $backup_id);
			}
		}
		$this->write_to_temp("
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;
/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;\n\n");
		$blog_time = strtotime(current_time('mysql'));
		$this->write_to_temp("-- Dump completed on ". date("F j, Y", $blog_time) . " at " . date("H:i", $blog_time) );
		$this->persist();
	}

	public function execute_backup_db_meta_data($backup_id){
		dark_debug(array(), '-----------execute_backup_db_meta_data-------------');
		$is_failed = $this->config->get_option('is_meta_data_backup_failed');
		if ($is_failed == 'yes') {
			return false;
		}
		$this->config->set_option('meta_data_backup_process', true);
		$starting_db_backup_time = microtime(true);
		$db_meta_data_status = $this->get_db_meta_data();
		foreach ($this->db_meta_data_tables as $key => $table_name) {
			if (empty($db_meta_data_status)) {
				$count = 0;
				WPTC_Factory::get('logger')->log(__("Starting meta data backup", 'wptc'), 'backup_progress', $backup_id);
				$this->backup_database_table($table_name, $count, $starting_db_backup_time, 1);
			} else if((isset($db_meta_data_status[$table_name]) && $db_meta_data_status[$table_name] != -1)){
				$count = $db_meta_data_status[$table_name];
				WPTC_Factory::get('logger')->log(__("Resuming meta data backup", 'wptc'), 'backup_progress', $backup_id);
				$this->backup_database_table($table_name, $count, $starting_db_backup_time, 1);
			}else if(!isset($db_meta_data_status[$table_name])){
				$count = 0;
				WPTC_Factory::get('logger')->log(__("Resuming meta data backup", 'wptc'), 'backup_progress', $backup_id);
				$this->backup_database_table($table_name, $count, $starting_db_backup_time, 1);
			} else if($db_meta_data_status[$table_name] == -1){
				continue;
				// return $this->get_file(1);
			}
		}
		// $this->append_db_meta_data(null, -1, $this->db_meta_data_tables);
		return $this->get_file(1);
	}

	public function backup_database_table($table, $offset, $starting_db_backup_time, $db_meta_data = null) {
		//dark_debug_func_map(func_get_args(), "--- function " . __FUNCTION__ . "--------", WPTC_WPTC_DARK_TEST_PRINT_ALL);
		$db_error = __('Error while accessing database.', 'wptc');

		if ($offset == 0) {
			$this->write_to_temp("\n--\n-- Table structure for table `$table`\n--\n\n");

			$table_creation_query = '';
			$table_creation_query .= "DROP TABLE IF EXISTS `$table`;";
			$table_creation_query .= "
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;\n";

			$table_create = $this->database->get_row("SHOW CREATE TABLE $table", ARRAY_N);
			if ($table_create === false) {
				throw new Exception($db_error . ' (ERROR_3)');
			}
			$table_creation_query .= $table_create[1].";";
			$table_creation_query .= "\n/*!40101 SET character_set_client = @saved_cs_client */;\n\n";

			$table_creation_query .= "--\n-- Dumping data for table `$table`\n--\n";
			$table_creation_query .= "\nLOCK TABLES `$table` WRITE;\n";
			$table_creation_query .= "/*!40000 ALTER TABLE `$table` DISABLE KEYS */;";
			$this->write_to_temp($table_creation_query . "\n");
		}
		$row_count = $offset;
		$table_count = $this->database->get_var("SELECT COUNT(*) FROM $table");
		$columns = $this->database->get_results("SHOW COLUMNS IN `$table`", OBJECT_K);

		if ($table_count != 0) {
			for ($i = $offset; $i < $table_count; $i = $i + self::SELECT_QUERY_LIMIT) {
				$table_data = $this->database->get_results("SELECT * FROM $table LIMIT " . self::SELECT_QUERY_LIMIT . " OFFSET $i", ARRAY_A);
				if ($table_data === false || !is_array($table_data[0])) {
					throw new Exception($db_error . ' (ERROR_4)');
				}

				$out = '';
				foreach ($table_data as $key => $row) {
					$data_out = $this->create_row_insert_statement($table, $row, $columns);
					$out .= $data_out;
					$row_count++;
				}

				$this->write_to_temp($out);
				if ($db_meta_data == 1) {
					if ($row_count >= $table_count) {
						$this->append_db_meta_data($table, -1);
					} else {
						$this->append_db_meta_data($table, $row_count);
					}
					$this->persist(1);
				} else {
					if ($row_count >= $table_count) {
						$this->processed->update_table($table, -1); //Done
					} else {
						$this->processed->update_table($table, $row_count);
					}
					$this->persist();
				}
				check_timeout_cut_and_exit_wptc($starting_db_backup_time);
			}
		}
		$this->write_to_temp("/*!40000 ALTER TABLE `$table` ENABLE KEYS */;\n");
		$this->write_to_temp("UNLOCK TABLES;\n");
		$this->persist();
		return true;
	}

	protected function create_row_insert_statement( $tableName, array $row, array $columns = array()) {
		$values = $this->create_row_insert_values($row, $columns);
		$joined = join(', ', $values);
		$sql    = "INSERT INTO `$tableName` VALUES($joined);\n";
		return $sql;
	}

	protected function create_row_insert_values($row, $columns) {
		$values = array();

		foreach ($row as $columnName => $value) {
			$type = $columns[$columnName]->Type;
			// If it should not be enclosed
			if ($value === null) {
				$values[] = 'null';
			} elseif (strpos($type, 'int') !== false
				|| strpos($type, 'float') !== false
				|| strpos($type, 'double') !== false
				|| strpos($type, 'decimal') !== false
				|| strpos($type, 'bool') !== false
			) {
				$values[] = $value;
			} elseif (strpos($type, 'blob') !== false) {
				$values[] = strlen($value) ? ('0x'.$value) : "''";
			} else {
				$values[] = "'".esc_sql($value)."'";
			}
		}
		return $values;
	}

	public function append_db_meta_data($table_name, $status , $table_names = null){
		$in_progress = $this->config->get_option('in_progress', true);
		if(empty($in_progress)){
			send_response_wptc('backup_stopped_manually', 'BACKUP');
		}
		if (!empty($table_names)) {
			foreach ($table_names as $table_name) {
				$db_meta_data_status[$table_name] = -1;
			}
		} else {
			$db_meta_data_status = $this->get_db_meta_data();
			$db_meta_data_status[$table_name] = $status;
		}
		$this->config->set_option('db_meta_data_status', serialize($db_meta_data_status));
	}


	public function shell_db_dump(){
		if(!$this->is_shell_exec_available()){
			return 'failed';
		}

		if ($this->config->get_option('shell_db_dump_status') === 'error') {
			return 'failed';
		}

		if ($this->config->get_option('shell_db_dump_status') === 'completed') {
			return 'completed';
		}

		if ($this->config->get_option('shell_db_dump_status') === 'running') {
			return $this->check_is_shell_db_dump_running();
		}

		@set_time_limit(0);
		$this->config->set_option('shell_db_dump_status', 'running');
		return $this->backup_db_dump();
	}

	private function check_is_shell_db_dump_running(){
		$file = $this->get_file();
		$filesize = filesize($file);
		dark_debug($filesize, '---------------$filesize-----------------');
		dark_debug($this->config->get_option('shell_db_dump_prev_size'), '---------------$prev-----------------');
		if ($this->config->get_option('shell_db_dump_prev_size') === false || $this->config->get_option('shell_db_dump_prev_size') === null) {
			$this->config->set_option('shell_db_dump_prev_size', $filesize );
			return 'running';
		} else if($this->config->get_option('shell_db_dump_prev_size') < $filesize){
			$this->config->set_option('shell_db_dump_prev_size', $filesize );
			return 'running';
		} else {
			return 'failed';
		}
		$this->config->set_option('shell_db_dump_status');
	}

	private function backup_db_dump() {

		$processed_files = WPTC_Factory::get('processed-files');
		$tables = $processed_files->get_all_included_tables();
		$file = $this->get_file();
		$paths   = $this->check_mysql_paths();
		$brace   = (substr(PHP_OS, 0, 3) == 'WIN') ? '"' : '';

		$wp_tables =  implode("\" \"",$tables);

		dark_debug($paths, '---------------$paths-----------------');
		dark_debug($brace, '---------------$brace-----------------');
		dark_debug($wp_tables, '---------------$wp_tables-----------------');


		$command = $brace . $paths['mysqldump'] . $brace . ' --force --host="' . DB_HOST . '" --user="' . DB_USER . '" --password="' . DB_PASSWORD . '" --add-drop-table --skip-lock-tables --extended-insert=FALSE "' . DB_NAME . '" "'.$wp_tables.'" > ' . $brace . $file . $brace;

		dark_debug($command, '---------------$command-----------------');

		$result = $this->wptc_exec($command);
		dark_debug($result, '---------------$result-----------------');

		if (!$result) {
			$this->config->set_option('shell_db_dump_status', 'failed');
			return 'failed';
		}

		if (wptc_get_file_size($file) == 0 || !is_file($file) || !$result) {
			$this->config->set_option('shell_db_dump_status', 'failed');
			if (file_exists($file)) {
				@unlink($file);
			}
			return 'failed';
		} else {
			$this->config->set_option('shell_db_dump_status', 'completed');
			$this->processed->update_table('header', -1);
			foreach ($tables as $table) {
				$this->processed->update_table($table, -1); //Done
			}
			return 'do_not_continue';
		}
	}

	### Function: Auto Detect MYSQL and MYSQL Dump Paths
	private function check_mysql_paths() {
		global $wpdb;
		$paths = array(
			'mysql' => '',
			'mysqldump' => ''
		);
		if (substr(PHP_OS, 0, 3) == 'WIN') {
			$mysql_install = $wpdb->get_row("SHOW VARIABLES LIKE 'basedir'");
			if ($mysql_install) {
				$install_path       = str_replace('\\', '/', $mysql_install->Value);
				$paths['mysql']     = $install_path . 'bin/mysql.exe';
				$paths['mysqldump'] = $install_path . 'bin/mysqldump.exe';
			} else {
				$paths['mysql']     = 'mysql.exe';
				$paths['mysqldump'] = 'mysqldump.exe';
			}
		} else {
			$paths['mysql'] = $this->wptc_exec('which mysql', true);
			if (empty($paths['mysql']))
				$paths['mysql'] = 'mysql'; // try anyway

			$paths['mysqldump'] = $this->wptc_exec('which mysqldump', true);
			if (empty($paths['mysqldump']))
				$paths['mysqldump'] = 'mysqldump'; // try anyway

		}
		return $paths;
	}

	private function wptc_exec($command, $string = false, $rawreturn = false) {
		if ($command == '')
			return false;

		if (function_exists('exec')) {
			$log = @exec($command, $output, $return);
			dark_debug($log, '---------------$log-----------------');
			dark_debug($output, '---------------$output-----------------');
			if ($string)
				return $log;
			if ($rawreturn)
				return $return;

			return $return ? false : true;
		} elseif (function_exists('system')) {
			$log = @system($command, $return);
			dark_debug($log, '---------------$log-----------------');

			if ($string)
				return $log;

			if ($rawreturn)
				return $return;

			return $return ? false : true;
		} else if (function_exists('passthru')) {
			$log = passthru($command, $return);
			dark_debug($log, '---------------$log-----------------');

			if ($rawreturn)
				return $return;

			return $return ? false : true;
		}

		if ($rawreturn)
			return -1;

		return false;
	}

	private function is_shell_exec_available() {
		if (in_array(strtolower(ini_get('safe_mode')), array('on', '1'), true) || (!function_exists('exec'))) {
			return false;
		}
		$disabled_functions = explode(',', ini_get('disable_functions'));
		$exec_enabled = !in_array('exec', $disabled_functions);
		return ($exec_enabled) ? true : false;
	}


	private function get_db_meta_data(){
		$db_meta_data_status = $this->config->get_option('db_meta_data_status');
		if (!empty($db_meta_data_status)) {
			$db_meta_data_status = unserialize($db_meta_data_status);
		} else {
			$db_meta_data_status = array();
		}
		return $db_meta_data_status;
	}


	private function modify_table_description($table_data){
		$temp_table = array();
		foreach ($table_data as $key => $value) {
			$temp = $table_data[$key];
			$temp_table[$value['Field']] = $table_data[$key];
		}
		return $temp_table;
	}

	private function write_to_temp($out) {
		if (!$this->temp) {
			$this->temp = fopen('php://memory', 'rw');
		}

		if (fwrite($this->temp, $out) === false) {
			throw new Exception(__('Error writing to php://memory.', 'wptc'));
		}
	}

	private function persist($db_meta_data = null) {
		if ($db_meta_data == 1) {
			$fh = fopen($this->get_file(1), 'a');
		} else {
			$fh = fopen($this->get_file(), 'a');
		}

		fseek($this->temp, 0);

		fwrite($fh, stream_get_contents($this->temp));

		if (!fclose($fh)) {
			throw new Exception(__('Error closing sql dump file.', 'wptc'));
		}

		if (!fclose($this->temp)) {
			throw new Exception(__('Error closing php://memory.', 'wptc'));
		}

		$this->temp = null;
	}

	public function is_wp_table($tableName) {
		//ignoring tables other than wordpress table
		$wp_prefix = $this->database->prefix;
		$wptc_strpos = strpos($tableName, $wp_prefix);

		if (false !== $wptc_strpos && $wptc_strpos === 0) {
			return true;
		}
		return false;
	}

	public function clean_up() {
		if (file_exists($this->get_file())) {
			@unlink($this->get_file());
		}
	}
}

<?php

Class file_Tree_WPTC{
	private $ftp_hostname;
	private $ftp_username;
	private $ftp_password;
	private $path;
	private $use_sftp;
	private $port;
	private $file_sys_obj;
	public function __construct($raw_data){
		$this->init($raw_data);
	}
	private function init($raw_data){
		$this->ftp_hostname = $raw_data['ftp_hostname'];
		$this->ftp_username = $raw_data['ftp_username'];
		$this->ftp_password = $raw_data['ftp_password'];
		$this->path 		= !empty($raw_data['dir'])? urldecode($_POST['dir']) : '';
		$this->use_sftp 	= $raw_data['use_sftp'];
		$this->port 		= $raw_data['port'];
		$this->do_file_sys_call();
	}

	private function do_file_sys_call(){
		$args = array(
			'hostname' 	=> $this->ftp_hostname,
			'port' 		=> $this->port,
			'username' 	=> $this->ftp_username,
			'password' 	=> $this->ftp_password,
			'base' 		=> $this->path
		);
		if($this->use_sftp!="") {
			$method = "SFTPExt";
		} else {
			$method = "FTPExt";
		}
		$this->file_sys_obj = $this->initfile_sys_obj($args, $method);
		if($this->file_sys_obj) {
			if($this->path == ''){
				$this->path = $this->file_sys_obj->cwd();
			}
			$fileList = $this->getFileList($this->file_sys_obj, $this->path);
			$this->printList($fileList);
		}

	}

	private function initfile_sys_obj($args, $method) {
		require_once(WPTC_PLUGIN_DIR.'Pro/Staging/lib/fileSystemBase.php');
		require_once(WPTC_PLUGIN_DIR.'Pro/Staging/lib/fileSystem'.ucfirst($method).'.php');
		$file_sys_class 	= "fileSystem".ucfirst($method);
		$this->file_sys_obj = new $file_sys_class($args);

		if ( ! defined('FS_CONNECT_TIMEOUT') ){
			define('FS_CONNECT_TIMEOUT', 30);
		}

		if ( ! defined('FS_TIMEOUT') ){
			define('FS_TIMEOUT', 30);
		}

		if ( !$this->file_sys_obj->connect() ) {
			return false; //There was an error connecting to the server.
		}
		return $this->file_sys_obj;
	}

	private function getFileList($file_sys_obj, $path) {
		$fileList = array();
		if($this->use_sftp != "") {
			$dir = $this->file_sys_obj->link->rawlist($this->path);

			if(!$dir){
				return false;
			}

			foreach($dir as $fname=>$entry) {
				if( '.' == $fname || '..' == $fname )
				continue; //Do not care about these folders.
				$fileList[] = array(
					'name'=>$fname,
					'type'=>($entry['type'] == 2 ) ? "d" : "f",
				);
			}

		} else {
			$fileList = $this->file_sys_obj->dirlist($path);
		}
		$fileList = $this->splitFilesFolders($fileList);
		return $fileList;
	}

	private function splitFilesFolders($list) {
		$fileList = array();
		$fileList['folders'] = array();
		$fileList['files'] = array();
		if(is_array($list)) {
			foreach($list as $fileInfo) {
				if($fileInfo['type']=='d') {
					$fileList['folders'][] = $this->path.$fileInfo['name'];
				}else{
					$fileList['files'][] = $this->path.$fileInfo['name'];
				}
			}
		}
		return $fileList;
	}

	private function printList($list) {
		$content = "<ul><li>Empty Folder</li></ul>";
		if(!empty($list['folders']) && is_array($list['folders'])){
			natcasesort($list['folders']);
		}
		if(!empty($list['files']) && is_array($list['files'])) {
			natcasesort($list['files']);
		}
		if(count($list['folders']) > 0 || count($list['files']) > 0) {
			$content =  "<ul class=\"jqueryFileTree\" style=\"display: none;\">";
			foreach( $list['folders'] as $file ) {
				$content .= "<li class=\"directory collapsed\"><div style='float:right;cursor: pointer;' fileName='".basename($file)."' class='fileTreeSelector' type='folder' rel='". htmlentities($file)."'>Select</div><a href=\"#\" type='folder' fileName=".basename($file)." rel=\"" . htmlentities($file) . "/\">" . basename($file) . "</a></li>";
			}
			foreach( $list['files'] as $file ) {
				$ext = preg_replace('/^.*\./', '', $file);
				$content .= "<li class=\"file ext_$ext\"><a href=\"#\" type='file' fileName=".basename($file)." rel=\"" . htmlentities($file) . "\">" . basename($file) . "</a></li>";
			}
			$content .= "</ul>";
		}
		header('Content-Type: application/json');
		die(json_encode(array("success"=>$content)));
	}

}
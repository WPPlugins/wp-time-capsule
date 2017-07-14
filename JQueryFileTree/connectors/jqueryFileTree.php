<?php
//
// jQuery File Tree PHP Connector
//
// Version 1.01
//
// Cory S.N. LaViska
// A Beautiful Site (http://abeautifulsite.net/)
// 24 March 2008
//
// History:
//
// 1.01 - updated to work with foreign characters in directory/file names (12 April 2008)
// 1.00 - released (24 March 2008)
//
// Output a list of files for jQuery File Tree
//
require_once('../../../includes/app.php');
if(!empty($_POST['dir']))
{
	$_POST['dir'] = urldecode($_POST['dir']);
}
	
$root = '';

(!empty($_POST['dir'])) ? $path = $_POST['dir'] : $path = "";

$ftp_hostname = $_POST['hostName'];
$ftp_username = $_POST['hostUserName'];
$ftp_password = $_POST['hostPassword'];
$use_sftp = $_POST['useSftp'];
$port = $_POST['hostPort'];
$args = array('hostname' => $ftp_hostname, 'port' => $port, 'username' => $ftp_username, 'password' => $ftp_password, 'base' => $path);

if($use_sftp!="") {
    $method = "SFTPExt";
} else {
    $method = "FTPExt";
}
$fileSysObj = initFileSysObj($args, $method);
if($fileSysObj) {
	if($path==''){$path = $fileSysObj->cwd();}
	$fileList = getFileList($fileSysObj, $path);
	printList($fileList);
}

//FileSystem initilize 
function initFileSysObj($args,$method) {
    require_once(APP_ROOT . '/includes/fileSystemBase.php');
    require_once(APP_ROOT . '/includes/fileSystem'.ucfirst($method).'.php');
    $fileSysClass = "fileSystem".ucfirst($method);
    $fileSysObj = new $fileSysClass($args);
    
    if ( ! defined('FS_CONNECT_TIMEOUT') )
        define('FS_CONNECT_TIMEOUT', 30);
    if ( ! defined('FS_TIMEOUT') )
        define('FS_TIMEOUT', 30);
    
    if ( !$fileSysObj->connect() ) {
        return false; //There was an error connecting to the server.
    }
    return $fileSysObj;
}

//Get the file list from given path
function getFileList($fileSysObj, $path) {
    global $use_sftp;
    $fileList = array();
    if($use_sftp!="") {
        $dir = $fileSysObj->link->rawlist($path);
        if(!$dir)
            return false;
        foreach($dir as $fname=>$entry) {
            if( '.' == $fname || '..' == $fname )
                continue; //Do not care about these folders.
            $fileList[] = array('name'=>$fname, 'type'=>($entry['type']==2)?"d":"f");
        }
    }else {
        $fileList = $fileSysObj->dirlist($path);
    }
    $fileList = splitFilesFolders($fileList);
    return $fileList;
}

//Split the folder and file in seperate array
function splitFilesFolders($list) {
    global $path;
    $fileList = array();
    $fileList['folders'] = array();
    $fileList['files'] = array();
    if(is_array($list)) {
        foreach($list as $fileInfo) {
            if($fileInfo['type']=='d') {
                $fileList['folders'][] = $path.$fileInfo['name'];
            }else{
                $fileList['files'][] = $path.$fileInfo['name'];
            }
        }
    }
    return $fileList;
}

//Print the list from given input array
function printList($list) {
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
            $content .= "<li class=\"file ext_$ext\"><div style='float:right;cursor: pointer;' fileName='".basename($file)."' class='fileTreeSelector' type='file' rel='". htmlentities($file)."'>Select</div><a href=\"#\" type='file' fileName=".basename($file)." rel=\"" . htmlentities($file) . "\">" . basename($file) . "</a></li>";
        }
        $content .= "</ul>";
    }
    header('Content-Type: application/json');
    echo jsonEncoder(array("success"=>$content));
}

?>
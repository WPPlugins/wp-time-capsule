<?php

class Cpanel_Works{
	private $config;
	public function __construct(){
		$this->config = WPTC_Pro_Factory::get('Wptc_staging_Config');
		$this->init();
	}

	private function init(){
		include_once WPTC_PLUGIN_DIR."/Pro/Staging/lib/xmlapi.php";
	}

	public function getURLPartsCommon($URL) {
		$URL = add_protocol_common($URL);
		return parse_url($URL);
	}

	public function create_db_cpanel($db_data) {
		$cpanel_data_raw = $this->config->get_option('cpanel_crentials');
		if (empty($cpanel_data_raw)) {
			die_with_json_encode(array('error' => 'missing cpanel data'));
		}
		global $xmlapi;
		$cpanel_data = unserialize($cpanel_data_raw);
		extract($cpanel_data);
		if(!defined('USERNAME')) { define('USERNAME', $cpUser); }
		$xmlapi = new xmlapi($cpHost);
		$xmlapi->set_port( 2083 );
		$xmlapi->password_auth($cpUser, $cpPass);
		$xmlapi->set_debug(1);
		$stats = $this->apiGenCommon("StatsBar", "stat", array('display' => 'sqldatabases'));
		$sqlInfo = (array)$stats->data;
		dark_debug($sqlInfo,'--------------$sqlInfo-------------');
		if($sqlInfo['_max'] != 'unlimited' &&  ($sqlInfo['_max'] - $sqlInfo['_count'] < 1)){
			die_with_json_encode(array("error" => 'cPanel error: MySQL database limit reached'));
		}

		$addDB = $xmlapi->api1_query($cpUser, "Mysql", "adddb", array($db_data['db_name'])); // check db name here
		$addDBArray = (array)$addDB;
		dark_debug($addDBArray['error'],'--------------$addDB-------------');
		if($addDB->error) {
			$DBNames = false;
			$dbName = '';
			$lastError = $addDBArray['error'];
			if(stripos($addDBArray['error'], 'database name already exists') !== false){
				die_with_json_encode(array("error" => 'Database already exist. Please try with different name.'));
			} else {
				die_with_json_encode(array("error" => $addDBArray['error']), 2);
			}
		}

		$addUser = $xmlapi->api1_query($cpUser, "Mysql", "adduser", array($db_data['db_username'], $db_data['db_password'])); //change both
		$addUserArray = (array)$addUser;
		dark_debug($addUserArray['error'],'--------------$addDB-------------');
		if($addUser->error) {
			$DBUserNames = false;//exists in the database
			$dbUser = '';
			$lastError = $addUserArray['error'];
			if(strpos($addUserArray['error'], 'exists in the database') !== false){
				die_with_json_encode(array("error" => 'User already exists'));
			} else {
				die_with_json_encode(array("error" => $addUserArray['error']), 2);
			}
		}

		$linkUserDB = $xmlapi->api1_query($cpUser, "Mysql", "adduserdb", array($db_data['db_name'], $db_data['db_username'], 'all'));
		if($linkUserDB->error) {
			die_with_json_encode(array("error" => 'cPanel error: Failed to link DB and User'));
		}
		global $wpdb;
		$database_details['db_host'] = 'localhost';
		$database_details['db_name'] = $db_data['db_name'];
		$database_details['db_username'] = $db_data['db_username'];
		$database_details['db_password'] = $db_data['db_password'];
		$database_details['db_prefix'] = $wpdb->base_prefix;
		$this->config->set_option('staging_db_details', serialize($database_details));
		die_with_json_encode(array('success'=>'1', 'note' => 'New database created successfully'));
	}

	public function auto_fill_cpanel($params = null){
		include_once WPTC_PLUGIN_DIR."/Pro/Staging/lib/xmlapi.php";
		$params['cpUser'] = $params['cpanel_username'];
		$params['cpPass'] = $params['cpanel_password'];
		$params['cpHost'] = $params['cpanel_url'];

		global $xmlapi, $ID, $DBNames,$DBUserNames,$rootDir,$dbName,$dbUser,$cpUser,$mainDomain,$subDomainFlg,$newCronKey,$lastError;

		$rootDir = 'public_html/';
		$prefix = "clone_";
		$cpUser = trim($params['cpUser']);
		$cpPass = $params['cpPass'];
		$siteInfo = $this->getURLPartsCommon($params['cpHost']);
		$host =  str_replace(array('http://','https://', 'www.','/cpanel/','/cpanel'), '', trim($siteInfo['host'], '/'));
		$hostName = trim($host);

		if(!defined('USERNAME')) { define('USERNAME', $cpUser); }

		$xmlapi = new xmlapi($hostName);
		$xmlapi->set_port( 2083 );
		$xmlapi->password_auth(USERNAME, $cpPass);
		$xmlapi->set_debug(1);

		$primaryHosts = $this->apiGenCommon("DomainLookup", "getmaindomain"); //DomainLookup::getmaindomain
		$primaryHost = (array)$primaryHosts->data;
		$mainDomain = $primaryHost['main_domain'];
		$host =  str_replace(array('http://','https://', 'www.'), '', trim($mainDomain, '/'));
		$mainDomain = trim($host);

		$appDomainPath = $mainDomain;
		$destURL = "http://".$mainDomain.'/';
		$path = '/'.$rootDir;

		$URLParts = parse_url($params['cpHost']);
		if (!isset($URLParts['host'])) {
			$cpHost = $URLParts['path'];
		} else {
			$cpHost = $URLParts['host'];
		}
		$exportArray = array();
		$exportArray['cpUser'] = $params['cpUser'];
		$exportArray['cpPass'] = $params['cpPass'];
		$exportArray['cpHost'] = $cpHost;
		$exportArray['destURL'] = $destURL;
		$exportArray['path'] = $path;
		$exportArray['db_prefix'] = $this->get_prefix($params['cpUser'], $params['cpPass'], $cpHost);
		// $exportArray['dbName'] = USERNAME.'_'.$dbName;
		// $exportArray['dbUser'] = USERNAME.'_'.$dbUser;
		// $exportArray['dbPass'] = $dbPass;
		dark_debug($exportArray,'--------------$exportArray-------------');
		$result = $this->config->set_option('cpanel_crentials', serialize($exportArray));
		dark_debug($result,'--------------$result cpanel_crentials-------------');
		die(json_encode($exportArray, JSON_UNESCAPED_SLASHES));
		// return $exportArray;
	}

	public function apiGenCommon($mod, $func, $var = array()){
	    global $xmlapi;

	    try{
	        $apiQuery = $xmlapi->api2_query(USERNAME, $mod, $func, $var);
	        if($apiQuery->error){
	            if($func == 'addsubdomain' || $func == 'search' || $func == 'mkdir'){
	                    return false;
	            }
	            $errorMsg = array('mod' => $mod, 'func' => $func);
	            $apiArray = (array)$apiQuery->error;
	            echo json_encode(array('error' => 'cPanel error: '.$apiQuery->error));
	            exit;
	        } else {
	            if($func == 'listfiles' || $func == 'listdbs' || $func == 'stat'|| $func == 'getmaindomain' || $func == 'search'){ //"DomainLookup", "getmaindomain"
	                return $apiQuery;
	            }
	        }
	    }
	    catch(Exception $e){
	        echo json_encode(array("error" => 'cPanel error: '.$e->getMessage()));
	        exit;
	    }
	    return true;
	}
	public function get_prefix($cpUser, $cpPass, $hostName) {
		include_once WPTC_PLUGIN_DIR."/Pro/Staging/lib/cpanel_uapi.php";
		$cpuapi = new cpanelUAPI($cpUser, $cpPass, $hostName);

		//Set the scope to the module we want to use. in this case, Mysql
		$cpuapi->scope = 'Mysql';
		//call the function we want like this. Any arguments are passed into the function as an array, in the form of param => value.
		$response = $cpuapi->get_restrictions();
		$data =json_decode(json_encode($response),true);
		$prefix = $data['data']['prefix'];
		dark_debug($response,'--------------$response-------------');
		dark_debug($prefix,'--------------$prefix-------------');
		if (empty($prefix)) {
			if (strlen(USERNAME) > 8) {
				return substr(USERNAME, 0, 7)."_";
			} else {
				return USERNAME."_";
			}
		} else if ($prefix == 'undefined' || empty($prefix)) {
			return false;
		} else {
			return $prefix;
		}
	}
}
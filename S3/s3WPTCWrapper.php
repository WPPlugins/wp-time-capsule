<?php

use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;

class S3_Wptc_Wrapper {
	private $client,
	$service,
	$handle,
	$as3_bucket;

	public function __construct(S3Client $client, $as3_bucket) {
		$this->client = $client;
		$this->utils = new S3_Utils();
		$this->as3_bucket = $as3_bucket;
	}

	public function setTracker($tracker) {
		$this->tracker = $tracker;
	}

	public function get_proper_s3_key_from_file_name($filename = false, $path = '') {
		return WPTC_CLOUD_DIR_NAME . '/' . wp_normalize_path($path) . '/' . basename($filename);
	}

	public function putFile($file, $filename = false, $path = '', $overwrite = true, $offset = 0, $uploadID = null) {
		$filename = (is_string($filename)) ? $filename : basename($file);
		$as3_file = $this->get_proper_s3_key_from_file_name($filename, $path);
		$complete_backup_result = $this->client->putObject(array(
			'Bucket' => $this->as3_bucket,
			'SourceFile' => $file,
			'Key' => $as3_file,
			// 'ACL' => 'public-read',
		));

		$to_return = array('VersionId' => $complete_backup_result['VersionId']);

		return $this->utils->formatted_upload_result($to_return, array('filesize' => filesize($file), 'title' => basename($filename)));
	}

	public function chunkedUpload($file, $filename = false, $path = '', $overwrite = true, $offset = 0, $uploadID = null, $starting_backup_path_time = false, $partNumber = 1, $parts = array()) {
		dark_debug_func_map(func_get_args(), "--------" . __FUNCTION__ . "--------", WPTC_WPTC_DARK_TEST_PRINT_ALL);
		//return $this->putFile($file, $filename, $path, $overwrite, $offset, $uploadID);
		$filename = (is_string($filename)) ? $filename : basename($file);
		$as3_file = $this->get_proper_s3_key_from_file_name($filename, $path);
		$oldPartNumber = $partNumber;
		dark_debug($oldPartNumber, "--------oldPartNumber--------");
		try {
			if (!empty($uploadID) && empty($oldPartNumber)) {
				dark_debug($type, "--------exitnign 1--------");
				backup_proper_exit_wptc();
			}
			if (empty($uploadID)) {
				$result = $this->client->createMultipartUpload(array(
					// 'ACL' => 'public-read',
					'Bucket' => $this->as3_bucket,
					'Key' => $as3_file,
				));
				$uploadID = $result['UploadId'];
				dark_debug($uploadID, "--------uploadID--------");
				if ($this->tracker) {
					$this->tracker->track_upload($file, $uploadID, 0);
				}
			}

			dark_debug(array(), "--------starting uploadPart--------");

			$parts_dets = $this->client->listParts(array(
				'Bucket' => $this->as3_bucket,
				'Key' => $as3_file,
				'UploadId' => $uploadID,
			));

			$next_part_number = $parts_dets['NextPartNumberMarker'];

			$parts = array();
			$parts = $parts_dets['Parts'];

			dark_debug($parts_dets, "--------parts_dets--------");

			$partNumber = $next_part_number + 1;
			dark_debug($partNumber, "--------partNumber--------");

			if ($partNumber < $oldPartNumber) {
				dark_debug($type, "--------exintng ya old--------");
				backup_proper_exit_wptc();
			}

			$handle = fopen($file, 'rb');
			fseek($handle, $offset);

			$to_exit = false;

			while (!feof($handle)) {
				dark_debug($starting_backup_path_time, "--------starting_backup_path_time--------");

				$result = $this->client->uploadPart(array(
					'Bucket' => $this->as3_bucket,
					'Key' => $as3_file,
					'UploadId' => $uploadID,
					'PartNumber' => $partNumber,
					'Body' => fread($handle, 5 * 1024 * 1024),
				));
				dark_debug($result, "--------result--------");
				dark_debug($starting_backup_path_time, "--------starting_backup_path_time after--------");

				$pIndex = $partNumber - 1;
				$parts[$pIndex] = array(
					'PartNumber' => $partNumber++,
					'ETag' => $result['ETag'],
				);

				if ($this->tracker) {
					$this->tracker->track_upload($file, $uploadID, ftell($handle), $partNumber, $parts);
				}
				dark_debug(array(), "--------must have called track upload--------");

				if ((microtime(true) - $starting_backup_path_time) >= WPTC_TIMEOUT) {
					$to_exit = true;
					break;
				}
			}
			fclose($handle);
			dark_debug($parts, "--------parts--------");

			if ($to_exit) {
				dark_debug(array(), "--------exitng by backup path time--------");
				backup_proper_exit_wptc();
			}

			if (!empty($parts)) {
				$complete_backup_result = $this->client->completeMultipartUpload(array(
					'Bucket' => $this->as3_bucket,
					'Key' => $as3_file,
					'UploadId' => $uploadID,
					'Parts' => $parts,
				));
				$to_return = array('VersionId' => $complete_backup_result['VersionId']);
				if(strrpos($file, 'wordpress-db_meta_data.sql') !== false){
					$config = WPTC_Factory::get('config');
					$config->set_option('meta_data_upload_offset', -1);
					$config->set_option('meta_data_upload_id', '');
					$config->set_option('meta_data_upload_s3_part_number', '');
					$config->set_option('meta_data_upload_s3_parts_array', '');
				}
				return $this->utils->formatted_upload_result($to_return, array('filesize' => filesize($file), 'title' => basename($filename)));
			}

		} catch (S3Exception $e) {
			dark_debug(array(), "--------comimng to ex--------");
			dark_debug($e->getMessage(), "--------S3Exception--------");
			if (!empty($uploadID)) {
				$result = $this->client->abortMultipartUpload(array(
					'Bucket' => $this->as3_bucket,
					'Key' => $as3_file,
					'UploadId' => $uploadID,
				));
			}
			if ($e->getStatusCode() == 503) {
				return array('too_many_requests' => $e->getMessage());
			}
			return array(
				'error' => $e->getMessage(),
			);
		}
	}

	public function getFile($as3_file_key, $outFile = false, $revision = null, $isChunkDownload = array(), $g_file_id = null) {
		$handle = null;
		if ($outFile !== false) {
			$tempFolderFile = $this->utils->prepareOpenSetOutFile($outFile, 'w', $handle);
		}
		$as3_file_key = WPTC_CLOUD_DIR_NAME . '/' . wp_normalize_path($as3_file_key);
		try {
			$result = $this->client->getObject(array(
				'Bucket' => $this->as3_bucket,
				'Key' => $as3_file_key,
				'SaveAs' => $tempFolderFile,
				'VersionId' => $revision,
			));
		} catch (Exception $e) {
			dark_debug($e->getMessage(), "--------caught exception--------");
			if ($e->getStatusCode() == 503) {
				return array('too_many_requests' => $e->getMessage());
			}
			throw $e;
		}

		return $result;
	}

	public function chunkedDownload($file, $outFile = false, $revision = null, $isChunkDownload = array(), $g_file_id = null, $meta_file_download) {
		$handle = null;
		if ($outFile !== false) {
			if ($isChunkDownload['c_offset'] == 0) {
				//while restoring ... first
				$tempFolderFile = $this->utils->prepareOpenSetOutFile($outFile, 'wb', $handle);
			} else {
				$tempFolderFile = $this->utils->prepareOpenSetOutFile($outFile, 'rb+', $handle);
			}
		}
		$as3_file_key = WPTC_CLOUD_DIR_NAME . '/' . wp_normalize_path($file);
		//dark_debug($as3_file_key, "--------as3_file_key--------");
		try {

			$result = $this->client->getObject(array(
				'Bucket' => $this->as3_bucket,
				'Key' => $as3_file_key,
				'Range' => $this->utils->get_formatted_range($isChunkDownload),
				'VersionId' => $revision,
			));

			if (!empty($result['Body'])) {
				fseek($handle, $isChunkDownload['c_offset']);
				fwrite($handle, $result['Body']);
			}
		} catch (Exception $e) {
			dark_debug($e->getMessage(), "--------Exception--------");
			if ($handle) {
				fclose($handle);
			}
			if ($e->getStatusCode() == 503) {
				return array('too_many_requests' => $e->getMessage());
			}
			throw $e;
		}

		if ($result) {
			$offset = ftell($handle);
		}
		if (empty($meta_file_download)) {
			if ($this->tracker) {
			$this->tracker->track_download($outFile, false, $offset, $isChunkDownload);
			}
		} else {
			$this->tracker->track_meta_download($offset, $isChunkDownload);
		}
		if ($handle) {
			fclose($handle);
		}

		return array(
			'name' => ($outFile) ? $outFile : basename($file),
			'chunked' => true,
		);
	}
}
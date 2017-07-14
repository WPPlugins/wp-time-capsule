<?php
require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
require_once ABSPATH . 'wp-admin/includes/template.php';

class WPTC_List_Table extends WP_List_Table {

	/**
	 * Constructor, we override the parent to pass our own arguments
	 * We usually focus on three parameters: singular and plural labels, as well as whether the class supports AJAX.
	 */
	function __construct() {
		parent::__construct(array(
			'singular' => 'wp_list_text_contact', //Singular label
			'plural' => 'wp_list_test_contacts', //plural label, also this well be one of the table class
			'ajax' => false, //We won't support Ajax for this table
		));
	}

	/**
	 * Add extra markup in the toolbars before or after the list
	 * @param string $which, helps you decide if you add the markup after (bottom) or before (top) the list
	 */
	function extra_tablenav($which) {
	//if ( $which == "top" ){
		//			//The code that goes before the table is here
		//			//echo ($headername!="")?$headername:"Table Data <small>Database</small>";
		//		}
	}

	/**
	 * Define the columns that are going to be used in the table
	 * @return array $columns, the array of columns to use with the table
	 */
	function get_columns() {
	// 	global $wpdb;
	// 	$columnsDB = $wpdb->get_results("SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME ='" . $wpdb->base_prefix . "wptc_activity_log'");
	// 	foreach ($columnsDB as $value) {
	// 		$columns[$value->COLUMN_NAME] = $value->COLUMN_NAME;
	// 	}
	// 	dark_debug($columns,'--------------$columns-------------');
	// 	return $columns;
	}

	/**
	 * Prepare the table with different parameters, pagination, columns and table elements
	 */
	function prepare_items() {
		global $wpdb, $_wp_column_headers;
		$screen = get_current_screen();
		$where = "";
		if (isset($_GET['type'])) {
			$type = $_GET['type'];
			switch ($type) {
			case 'backups':
				$query = "SELECT * FROM " . $wpdb->base_prefix . "wptc_activity_log WHERE type LIKE '%backup%' AND show_user = 1 GROUP BY action_id";
				break;
			case 'restores':
				$query = "SELECT * FROM " . $wpdb->base_prefix . "wptc_activity_log WHERE type LIKE 'restore%' GROUP BY action_id";
				break;
			case 'others':
				$query = "SELECT * FROM " . $wpdb->base_prefix . "wptc_activity_log WHERE type NOT LIKE 'restore%' AND type NOT LIKE 'backup%' AND show_user = 1";
				break;
			default:
				$query = "SELECT * FROM " . $wpdb->base_prefix . "wptc_activity_log GROUP BY action_id UNION SELECT * FROM " . $wpdb->base_prefix . "wptc_activity_log WHERE action_id='' AND show_user = 1";
				break;
			}
		} else {
			$query = "SELECT * FROM " . $wpdb->base_prefix . "wptc_activity_log WHERE show_user = 1   GROUP BY action_id ";
		}
		/* -- Preparing your query -- */

		/* -- Ordering parameters -- */
		//Parameters that are going to be used to order the result
		$orderby = !empty($_GET["orderby"]) ? mysql_real_escape_string($_GET["orderby"]) : 'id';
		$order = !empty($_GET["order"]) ? mysql_real_escape_string($_GET["order"]) : 'DESC';
		if (!empty($orderby) & !empty($order)) {$query .= ' ORDER BY ' . $orderby . ' ' . $order;}

		/* -- Pagination parameters -- */
		//Number of elements in your table?
		$totalitems = $wpdb->query($query); //return the total number of affected rows
		//How many to display per page?
		$perpage = 20;
		//Which page is this?
		$paged = !empty($_GET["paged"]) ? $_GET["paged"] : '';if (empty($paged) || !is_numeric($paged) || $paged <= 0) {$paged = 1;} //Page Number
		//How many pages do we have in total?
		$totalpages = ceil($totalitems / $perpage); //Total number of pages
		//adjust the query to take pagination into account
		if (!empty($paged) && !empty($perpage)) {
			$offset = ($paged - 1) * $perpage;
			$query .= ' LIMIT ' . (int) $offset . ',' . (int) $perpage;
		}

		/* -- Register the pagination -- */
		$this->set_pagination_args(array(
			"total_items" => $totalitems,
			"total_pages" => $totalpages,
			"per_page" => $perpage,
		));
		//The pagination links are automatically built according to those parameters

		/* -- Register the Columns -- */
		// $columns = $this->get_columns();
		// $_wp_column_headers[$screen->id] = $columns;

		/* -- Fetch the items -- */

		$this->items = $wpdb->get_results($query);
	}

	/**
	 * Display the rows of records in the table
	 * @return string, echo the markup of the rows
	 */
	function display_rows() {
		global $wpdb;
		//Get the records registered in the prepare_items method
		$records = $this->items;
		$i=0;
		$limit = WPTC_Factory::get('config')->get_option('activity_log_lazy_load_limit');
		//Get the columns registered in the get_columns and get_sortable_columns methods
		// $columns = $this->get_columns();
		$timezone = WPTC_Factory::get('config')->get_option('wptc_timezone');
		echo "<thead style='background: none repeat scroll 0% 0% rgb(238, 238, 238);'><tr><td style='width:10%'>Time</td><td style='width:60%'>Task</td><td>Send Report</td></tr></thead>";
		if (count($records) > 0) {

			foreach ($records as $key => $rec) {

				$more_logs = false;
				$load_more = false;
				if ($rec->action_id != '') {
					$sql = "SELECT * FROM " . $wpdb->base_prefix . "wptc_activity_log WHERE action_id=" . $rec->action_id . ' AND show_user = 1 ORDER BY id DESC LIMIT 0 , '.$limit;
					$sub_records = $wpdb->get_results($sql);
					$row_count = count($sub_records);
					if ($row_count == $limit) {
						$load_more = true;
					}

					if ($row_count > 0) {
						$more_logs = true;
						$detailed = '<table>';
						$detailed .= $this->get_activity_log($sub_records);
						if (isset($load_more) && $load_more) {
							$detailed .= '<tr><td></td><td><a style="cursor:pointer; position:relative" class="wptc_activity_log_load_more" action_id="'.$rec->action_id.'" limit="'.$limit.'">Load more</a></td><td></td></tr>';
						}
						$detailed .= '</table>';

					}
				}
				//Open the line
				echo '<tr class="act-tr">';
				$Ldata = unserialize($rec->log_data);
				$user_time = WPTC_Factory::get('config')->cnvt_UTC_to_usrTime($Ldata['log_time']);
				WPTC_Factory::get('processed-files')->modify_schedule_backup_time($user_time);
				// $user_tz = new DateTime('@' . $Ldata['log_time'], new DateTimeZone(date_default_timezone_get()));
				// $user_tz->setTimeZone(new DateTimeZone($timezone));
				// $user_tz_now = $user_tz->format("M d, Y @ g:i:s a");
				$user_tz_now = date("M d, Y @ g:i:s a", $user_time);
				$msg = '';
				if (!(strpos($rec->type, 'backup') === false)) {
					//Backup process
					$msg = 'Backup Process';
				} else if (!(strpos($rec->type, 'restore') === false)) {
					//Restore Process
					$msg = 'Restore Process';
				} else if (!(strpos($rec->type, 'staging') === false)) {
					//Restore Process
					$msg = 'Staging Process';
				} else {
					if ($row_count < 2) {
						$more_logs = false;
					}
					$msg = $Ldata['msg'];
				}
				echo '<td class="wptc-act-td">' . $user_tz_now . '</td><td class="wptc-act-td">' . $msg;
				if ($more_logs) {
					echo "&nbsp&nbsp&nbsp&nbsp<a class='wptc-show-more' action_id='" . round($rec->action_id) . "'>View details</a></td>";
				} else {
					echo "</td>";
				}
				echo '<td class="wptc-act-td"><a class="report_issue_wptc" id="' . $rec->id . '" href="#">Send report to plugin developer</a></td>';
				if ($more_logs) {

					echo "</tr><tr id='" . round($rec->action_id) . "' class='wptc-more-logs'><td colspan=3>" . $detailed . "</td>";
				} else {
					echo "</td>";
				}
				//Close the line
				echo '</tr>';
			}

		}
	}
	//Overwrite Pagination function
	function pagination($which) {

		if (empty($this->_pagination_args)) {
			return;
		}

		$total_items = $this->_pagination_args['total_items'];
		$total_pages = $this->_pagination_args['total_pages'];
		$infinite_scroll = false;
		if (isset($this->_pagination_args['infinite_scroll'])) {
			$infinite_scroll = $this->_pagination_args['infinite_scroll'];
		}

		$output = '<span class="displaying-num">' . sprintf(_n('1 log', '%s logs', $total_items, 'wptc'), number_format_i18n($total_items)) . '</span>';

		$current = $this->get_pagenum();

		$current_url = set_url_scheme('http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);

		$current_url = remove_query_arg(array('hotkeys_highlight_last', 'hotkeys_highlight_first'), $current_url);

		$page_links = array();

		$disable_first = $disable_last = '';
		if ($current == 1) {
			$disable_first = ' disabled';
		}
		if ($current == $total_pages) {
			$disable_last = ' disabled';
		}
		$page_links[] = sprintf("<a class='%s' title='%s' href='%s'>%s</a>",
			'first-page' . $disable_first,
			esc_attr__('Go to the first page'),
			esc_url(remove_query_arg('paged', $current_url)),
			'&laquo;'
		);

		$page_links[] = sprintf("<a class='%s' title='%s' href='%s'>%s</a>",
			'prev-page' . $disable_first,
			esc_attr__('Go to the previous page'),
			esc_url(add_query_arg('paged', max(1, $current - 1), $current_url)),
			'&lsaquo;'
		);

		if ('bottom' == $which) {
			$html_current_page = $current;
		} else {
			$html_current_page = sprintf("%s<input class='current-page' id='current-page-selector' title='%s' type='text' name='paged' value='%s' size='%d' />",
				'<label for="current-page-selector" class="screen-reader-text">' . __('Select Page') . '</label>',
				esc_attr__('Current page'),
				$current,
				strlen($total_pages)
			);
		}
		$html_total_pages = sprintf("<span class='total-pages'>%s</span>", number_format_i18n($total_pages));
		$page_links[] = '<span class="paging-input">' . sprintf(_x('%1$s of %2$s', 'paging'), $html_current_page, $html_total_pages) . '</span>';

		$page_links[] = sprintf("<a class='%s' title='%s' href='%s'>%s</a>",
			'next-page' . $disable_last,
			esc_attr__('Go to the next page'),
			esc_url(add_query_arg('paged', min($total_pages, $current + 1), $current_url)),
			'&rsaquo;'
		);

		$page_links[] = sprintf("<a class='%s' title='%s' href='%s'>%s</a>",
			'last-page' . $disable_last,
			esc_attr__('Go to the last page'),
			esc_url(add_query_arg('paged', $total_pages, $current_url)),
			'&raquo;'
		);

		$pagination_links_class = 'pagination-links';
		if (!empty($infinite_scroll)) {
			$pagination_links_class = ' hide-if-js';
		}
		$output .= "\n<span class='$pagination_links_class'>" . join("\n", $page_links) . '</span>';

		if ($total_pages) {
			$page_class = $total_pages < 2 ? ' one-page' : '';
		} else {
			$page_class = ' no-pages';
		}
		$this->_pagination = "<div class='tablenav-pages{$page_class}'>$output</div>";

		echo $this->_pagination;

	}

	function get_activity_log($sub_records){
		if (count($sub_records) < 1) {
			return false;
		}
		$detailed = '';
		$timezone = WPTC_Factory::get('config')->get_option('wptc_timezone');
		foreach ($sub_records as $srec) {
			$Moredata = unserialize($srec->log_data);
			$user_tmz = new DateTime('@' . $Moredata['log_time'], new DateTimeZone(date_default_timezone_get()));
			$user_tmz->setTimeZone(new DateTimeZone($timezone));
			$user_tmz_now = $user_tmz->format("M d @ g:i:s a");
			$detailed .= '<tr><td class="activity-time-wptc">' . $user_tmz_now . '</td><td>' . $Moredata['msg'] . '</td><td></td></tr>';
		}
		return $detailed;
	}
}
function lazy_load_activity_log_wptc(){
	if (!isset($_POST['data'])) {
		return false;
	}
	$data = $_POST['data'];
	if (!isset($data['action_id']) || !isset($data['limit'])) {
		return false;
	}
	global $wpdb;
	$action_id = $data['action_id'];
	$from_limit = $data['limit'];
	$detailed = '';
	$load_more = false;
	$current_limit = WPTC_Factory::get('config')->get_option('activity_log_lazy_load_limit');
	$to_limit = $from_limit + $current_limit;
	$sql = "SELECT * FROM " . $wpdb->base_prefix . "wptc_activity_log WHERE action_id=" . $action_id . ' AND show_user = 1 ORDER BY id DESC LIMIT '.$from_limit.' , '.$current_limit;
	$sub_records = $wpdb->get_results($sql);
	$row_count = count($sub_records);
	if ($row_count == $current_limit) {
		$load_more = true;
	}
	$wptc_list_table = new WPTC_List_Table();
	$detailed = $wptc_list_table->get_activity_log($sub_records);
	if (isset($load_more) && $load_more) {
		$detailed .= '<tr><td></td><td><a style="cursor:pointer; position:relative" class="wptc_activity_log_load_more" action_id="'.$action_id.'" limit="'.$to_limit.'">Load more</a></td><td></td></tr>';
	}
	echo $detailed;
}
<?php
// require_once ABSPATH . 'wp-content/plugins/wp-time-capsule/common-functions.php';

require_once WP_PLUGIN_DIR.'/wp-time-capsule/Classes/ActivityLog.php';

$wptc_list_table = new WPTC_List_Table();
$wptc_list_table->prepare_items();

if (isset($_GET['type'])) {
	$type = $_GET['type'];
} else {
	$type = 'all';
}
add_thickbox();
?>
<h2>
    Activity Log & Report
</h2>
<div class="tablenav">

			<ul class="subsubsub">
				<li>
					<a href="<?php echo $uri; ?>" id="all" <?php echo ($type == 'all') ? 'class="current"' : ""; ?>>All Activities <span class="count"></span></a> |
				</li>
				<li>
					<a href="<?php echo $uri . '&type=backups'; ?>" id="backups" <?php echo ($type == 'backups') ? 'class="current"' : ""; ?>>Backups <span class="count"></span></a> |
				</li>
				<li>
					<a href="<?php echo $uri . '&type=restores'; ?>" id="restore" <?php echo ($type == 'restores') ? 'class="current"' : ""; ?>>Restores<span class="count"></span></a> |
				</li>
				<li>
					<a href="<?php echo $uri . '&type=others'; ?>" id="other" <?php echo ($type == 'others') ? 'class="current"' : ""; ?>>Others <span class="count"></span></a>
				</li>
</ul>
    <ul class="subsubsub" style="float: right; margin-right: 20px; cursor: pointer;">
        <li>
            <a id="clear_log">Clear Logs</a>
	</li>
    </ul>
</div>
	<div class="wrap">

		<?php //Table of elements
$wptc_list_table->display();
?>

	</div>
<div id="wptc-content-id" style="display:none;"> <p> This is my hidden content! It will appear in ThickBox when the link is clicked. </p></div>
<a style="display:none" href="#TB_inline?width=600&height=550&inlineId=wptc-content-id" class="thickbox wptc-thickbox">View my inline content!</a>
<?php


wp_enqueue_script('wptc-activity', plugins_url() . '/' . WPTC_TC_PLUGIN_NAME . '/Views/wptc-activity.js', array(), WPTC_VERSION);


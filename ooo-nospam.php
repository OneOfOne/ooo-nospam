<?php
/**
 * Plugin Name: OneOfOne's NoSpam
 * Plugin URI: http://limitlessfx.com/
 * Description: Simple transparent no-spam plugin
 * Version: v0.8
 * Author: OneOfOne
 * Author URI: http://limitlessfx.com/
 * License: Apache-2
 */

if ( !function_exists( 'add_action' ) ) {
	die('Nope.');
}
session_start();

define('NOSPAM_VERSION', '0.8');
define('NOSPAM_MIN_TIME', 10.0);
define('NOSPAM_MAX_URLS', 3);
define('NOSPAM_AUTO_DELETE', false);
define('NOSPAM_DEBUG', false);
define('NOSPAM_JAVASCRIPT', false);

class OneOfOneNoSpam {
	static $FIELD_NAMES = array('email');
	static $OPTION_GROUP = 'ooo-nospam';
	static $OPTION_COUNT = 'ooo-nospam-count';
	private $options, $count;

	public function __construct() {
		$this->options = $this->sanitize(get_option(self::$OPTION_GROUP));
		$this->count = absint(get_option(self::$OPTION_COUNT));
		if(!$this->count) {
			update_option(self::$OPTION_COUNT, 0);
		}
		if(is_admin()) {
			add_action('admin_menu', array(&$this, 'add_plugin_page'));
			add_action('admin_init', array(&$this, 'admin_page_init'));
		}

		if(!is_user_logged_in()) {
			//todo comments_array
			add_action('comment_form', array(&$this, 'comment_form'));
			add_action('preprocess_comment' , array(&$this, 'preprocess_comment'), 0);
			add_filter('pre_comment_approved', array(&$this, 'pre_comment_approved') , 0, 2);
			add_filter('comments_array', array(&$this, 'filter_comments_array') , 0);
		}
	}

	public function add_plugin_page() {
		add_options_page('Settings Admin', 'OneOfOne\'s NoSpam', 'manage_options', 'ooo-nospam-admin',
			array(&$this, 'print_admin_page'));
	}

	public function print_admin_page() {
		?>
		<div class="wrap">
			<h2>OneOfOne's NoSpam v<?php echo NOSPAM_VERSION?>
				<h3>Spam comments blocked : <em><?php echo $this->count; ?></em></h3>
				<form method="post" action="options.php">
					<?php
					// This prints out all hidden setting fields
					settings_fields(self::$OPTION_GROUP);
					?>
					<table class="form-table">
						<tr>
							<th scope="row">Auto Delete?</th>
							<td><?php echo $this->get_input_option('auto_delete', 2, 'checkbox'); ?> Yes
							</td>
						</tr>
						<tr>
							<th scope="row">Allowed URLs</th>
							<td><?php echo $this->get_input_option('max_urls'); ?>
								Maximum number of URLs allowed in a comment.
							</td>
						</tr>
						<tr>
							<th scope="row">Timeout (in seconds)</th>
							<td><?php echo $this->get_input_option('min_time'); ?>
								The minimum time spent on the page before commenting.
							</td>
						</tr>
						<tr>
							<th scope="row">Enable Javascript?</th>
							<td><?php echo $this->get_input_option('javascript', 2, 'checkbox'); ?>
								This will include an extra check using javascript.
							</td>
						</tr>
						<tr>
							<th scope="row">Enable Debugging?</th>
							<td><?php echo $this->get_input_option('debug', 2, 'checkbox'); ?>
								<small>This will embed debugging info in non-spam comments,
									<b>only enable if spam is bypassing the plugin.</b></small>
							</td>
						</tr>
						<tr>
							<th scope="row">Reset plugin options?</th>
							<td><?php echo $this->get_input_option('reset', 2, 'checkbox'); ?> Yes</td>
						</tr>
					</table>
					<?php
					do_settings_sections('ooo-nospam-admin');
					submit_button();
					?>
				</form>
		</div>
	<?php
	}

	public function admin_page_init() {
		register_setting(
			self::$OPTION_GROUP, // Option group
			'ooo-nospam', // Option name
			array(&$this, 'sanitize') // Sanitize
		);

	}

	public function sanitize($input) {
		if(isset($input['reset']) && $input['reset'] === 'Y') {
			delete_option(self::$OPTION_GROUP);
			$input = array(
				'min_time' => NOSPAM_MIN_TIME,
				'max_urls' => NOSPAM_MAX_URLS,
				'auto_delete' => NOSPAM_AUTO_DELETE ? 'Y' : '',
				'javascript' => NOSPAM_JAVASCRIPT ? 'Y' : '',
				'debug' => NOSPAM_DEBUG
			);
		}
		$input['min_time'] = absint($input['min_time']) > 0 ? absint($input['min_time']) : NOSPAM_MIN_TIME;
		$input['max_urls'] = absint($input['max_urls']) > 0 ? absint($input['max_urls']) : NOSPAM_MAX_URLS;
		$input['javascript'] = isset($input['javascript']) ? $input['javascript'] : '';
		$input['auto_delete'] = isset($input['auto_delete']) ? $input['auto_delete'] : '';
		$input['debug'] = isset($input['debug']) ? $input['debug'] : '';
		return $input;
	}

	public function comment_form() {
		$cid = sprintf('%f', microtime(true));
		$fn = self::$FIELD_NAMES[mt_rand(0, count(self::$FIELD_NAMES) - 1)] .'-' . mt_rand(11, 99);
		$_SESSION['NS_' . $cid] = $fn;
		echo '
		<p style="position:absolute; left:-99999px">
			<input type="hidden" name="NS_CID" value="'. $cid .'" />
			<input type="text" id="' . $fn . '" name="' . $fn  . '" size="30" value="-"/>
		</p>
		';
		if($this->options['javascript'] === 'Y') {
			echo '<script>(function(e){if(e)e.value="js";})(document.getElementById("' . $fn . '"));</script>';
		}

	}

	public function preprocess_comment($data) {
		$type = $data['comment_type'];
		$cid = isset($_POST['NS_CID']) ? $_POST['NS_CID'] : 0;
		$fn = isset($_SESSION['NS_' . $cid]) ? $_SESSION['NS_' . $cid] : '';
		unset($_SESSION['NS_' . $cid]);

		$dummy = array(); //workaround for older versions of php
		$nurls = preg_match_all('@http(?:s)?://@', $data['comment_content'], $dummy);

		$time = microtime(true) - floatval($cid);

		$checks = array(
			'is-trackback' => $type === 'trackback' ? 1 : 0,
			'no-session-token' => !$fn ? 1 : 0,
			'hidden-field' => (!isset($_POST[$fn]) ||  $_POST[$fn] !== '-') ? 1.0 : 0,
			'number-of-urls' => ($nurls > $this->options['max_urls']) ? $nurls : 0,
			'referer' => isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'],
					get_site_url()) !== false ? 0 : 1,
			'too-fast' => $time < $this->options['min_time'] ? $time : 0,
			'javascript' => 0
		);

		if($this->options['javascript']) {
			$checks['javascript'] = $checks['hidden-field'] = $_POST[$fn] !== 'js' ? 1 : 0;
		}

		$score = 0;
		foreach($checks as $k => $v) {
			$score += $v;
		}

		if($score > 0) {
			$data['comment_approved'] = 'spam';
			$checks['spam'] = 1;
			$data['comment_content'] .= "\n" . json_encode($checks);
		} elseif($this->options['debug']) {
			$data['comment_content'] .= "\n<!-- nospam-debug : " . json_encode($checks) . '-->';
		}
		return $data;
	}

	public function pre_comment_approved($approved, $data) {
		$approved = $approved === 'spam' ? $approved : $data['comment_approved'];
		if ($approved === 'spam') {
			$this->update_spam_counter();
			if($this->options['auto_delete']) {
				add_action('wp_insert_comment', array(&$this, 'handle_auto_delete'), 0, 2);
			}
		}
		return $approved;
	}

	public function handle_auto_delete($id, $comment) {
		if(!$comment && !is_object($cmt = get_comment($comment))){
			return;
		}
		//$comment->comment_content .=  print_r(array(strpos($comment->comment_content, '"spam":1'), $comment), true);
		//wp_update_comment((array)$comment);
		if(strpos($comment->comment_content, '"spam":1') !== false) {
			wp_delete_comment($id, true);
		}
	}

	public function filter_comments_array($comments = array()) {
		$ret = array();
		foreach($comments as $k => $v) {
			if(strpos($v->comment_content, '"spam":1') === FALSE) {
				$ret[] = $v;
			}
		}
		return $ret;
	}
	private function update_spam_counter($i = 1) {
		global $wpdb;
		$wpdb->query(
			$wpdb->prepare(
				'UPDATE ' . $wpdb->options . ' SET option_value = option_value + %d WHERE option_name = %s;',
				$i, self::$OPTION_COUNT
			)
		);

	}

	private function get_input_option($name, $size = 2, $type = 'text') {
		static $fmt_text = '<input id="%1$s" name="%2$s[%1$s]" size="%3$d" value="%4$s">';
		static $fmt_checkbox = '<input type="checkbox" id="%1$s" name="%2$s[%1$s]" value="Y"%3$s>';
		if($type === 'text') {
			return sprintf($fmt_text, $name, self::$OPTION_GROUP, $size, esc_attr($this->options[$name]));
		} else if($type === 'checkbox') {
			return sprintf($fmt_checkbox, $name, self::$OPTION_GROUP,
				isset($this->options[$name]) && $this->options[$name] === 'Y' ? ' checked="checked"' : '');
		}
	}
}

add_action('init', 'init_ooo_nospam');
function init_ooo_nospam() {
	return new OneOfOneNoSpam();
}
<?php
/**
 * Plugin Name: OneOfOne's NoSpam
 * Plugin URI: http://limitlessfx.com/
 * Description: Simple transparent no-spam plugin
 * Version: v0.7.6
 * Author: OneOfOne
 * Author URI: http://limitlessfx.com/
 * License: Apache-2
 */

if ( !function_exists( 'add_action' ) ) {
	die('Nope.');
}
session_start();

define('NOSPAM_VERSION', 0.7);
define('NOSPAM_MIN_TIME', 10.0);
define('NOSPAM_MAX_URLS', 3);
define('NOSPAM_AUTO_DELETE', false);

class OneOfOneNoSpam {
	static $FIELD_NAMES = array('email', 'url');
	static $OPTION_GROUP = 'ooo-nospam';
	static $OPTION_COUNT = 'ooo-nospam-count';
	private $options, $count;

	public function __construct() {
		$this->options = $this->sanitize(get_option(self::$OPTION_GROUP));
		$this->count = absint(get_option(self::$OPTION_COUNT));

		if(is_admin()) {
			add_action('admin_menu', array(&$this, 'add_plugin_page'));
			add_action('admin_init', array(&$this, 'admin_page_init'));
		}

		if(!is_user_logged_in()) {
			add_action('comment_form', array(&$this, 'comment_form'));
			add_action('preprocess_comment' , array(&$this, 'preprocess_comment'), 100);
			add_filter('pre_comment_approved', array(&$this, 'pre_comment_approved') , 100, 2 );
		}
	}

	public function add_plugin_page() {
		add_options_page('Settings Admin', 'OneOfOne\'s NoSpam', 'manage_options', 'ooo-nospam-admin',
			array($this, 'create_admin_page'));
	}

	public function create_admin_page() {
		?>
		<div class="wrap">
			<h2>OneOfOne's NoSpam v<?php echo NOSPAM_VERSION?>
				<h3>Spam comments blocked : <em><?php echo $this->count; ?></em></h3>
				<form method="post" action="options.php">
					<?php
					// This prints out all hidden setting fields
					settings_fields(self::$OPTION_GROUP);
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
			array($this, 'sanitize') // Sanitize
		);

		add_settings_section('ns_default_options', // ID
			'Settings', // Title
			null,
			'ooo-nospam-admin' // Page
		);

		add_settings_field('auto_delete', 'Auto Delete?',
			array($this, 'auto_delete_callback'), 'ooo-nospam-admin', 'ns_default_options');

		add_settings_field('max_urls', 'Allowed URLs',
			array($this,  'max_urls_callback'), 'ooo-nospam-admin', 'ns_default_options');

		add_settings_field('min_time', 'Timeout (in seconds)',
			array($this, 'timeout_callback'), 'ooo-nospam-admin', 'ns_default_options');

		add_settings_field('reset', 'Reset plugin options?',
			array($this, 'reset_options_callback'), 'ooo-nospam-admin', 'ns_default_options');
	}

	public function sanitize($input) {
		if(isset($input['reset']) && $input['reset'] === 'Y') {
			$this->reset_options();
			$input = array(
				'min_time' => NOSPAM_MIN_TIME,
				'max_urls' => NOSPAM_MAX_URLS,
				'auto_delete' => NOSPAM_AUTO_DELETE ? 'Y' : 'N');
		}
		$new_input = array();
		$new_input['min_time'] = absint($input['min_time']) > 0 ? absint($input['min_time']) : NOSPAM_MIN_TIME;
		$new_input['max_urls'] = absint($input['max_urls']) > 0 ? absint($input['max_urls']) : NOSPAM_MAX_URLS;
		$new_input['auto_delete'] = $input['auto_delete'];
		return $new_input;
	}

	public function timeout_callback() {
		printf('<input id="min_time" name="ooo-nospam[min_time]" size="2" value="%s">
		The minimum time spent on the page before commenting.', esc_attr($this->options['min_time']));
	}

	public function max_urls_callback() {
		printf('<input id="max_urls" name="ooo-nospam[max_urls]" size="2" value="%s">
		Maximum number of URLs allowed in a comment.', esc_attr($this->options['max_urls']));
	}

	public function auto_delete_callback() {
		printf('<input type="checkbox" id="auto_delete" name="ooo-nospam[auto_delete]" value="Y"%s> Yes',
			$this->options['auto_delete'] ? ' checked="checked"' : '');
	}

	public function reset_options_callback() {
		printf('<input type="checkbox" id="reset" name="ooo-nospam[reset]" value="Y"%s> Yes', '');
	}

	public function comment_form() {
		$cid = sprintf('%f', microtime(true));
		$fn = self::$FIELD_NAMES[mt_rand(0, count(self::$FIELD_NAMES) - 1)] .'-' . mt_rand(11, 99);
		$_SESSION['NS_' . $cid] = $fn;
		echo '
		<p style="position:absolute; left:-99999px">
			<input type="hidden" name="NS_CID" value="'. $cid .'" />
			<input type="text" name="' . $fn  . '" size="30" value="-"/>
		</p>
		';

	}

	public function preprocess_comment($data) {
		$data['spam_score'] = 0;

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
			'too-fast' => $time < $this->options['min_time'] ? $time : 0
		);
		$score = 0;
		foreach($checks as $k => $v) {
			$score += $v;
		}

		if($score > 0) {
			$data['comment_content'] .= "\n" . json_encode($checks);
			$data['spam_score'] = $score;
		}
		return $data;
	}

	public function pre_comment_approved($approved, $data) {
		if ($data['spam_score'] > 0) {
			$this->count++;
			update_option(self::$OPTION_COUNT, $this->count);
			if($this->options['auto_delete'] === 'Y') {
				die('spam');
			} else {
				return 'spam';

			}
		}
		return $approved;
	}

	private function reset_options() {
		delete_option(self::$OPTION_GROUP);
	}
}

add_action( 'init', 'init_ooo_nospam');
function init_ooo_nospam() {
	return new OneOfOneNoSpam();
}
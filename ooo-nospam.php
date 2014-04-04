<?php
/**
 * Plugin Name: OneOfOne's NoSpam
 * Plugin URI: http://limitlessfx.com/php/nospam-wp
 * Description: Simple transparent no-spam plugin
 * Version: 0.4
 * Author: OneOfOne
 * Author URI: http://limitlessfx.com/
 * License: Apache-2
 */

if ( !function_exists( 'add_action' ) ) {
	echo 'You have been a bad bad human, your computer will explode in 5 seconds.';
	exit;
}
session_start();

define('NOSPAM_VERSION', 0.1);
define('NOSPAM_MIN_TIME', 10.0);
define('NOSPAM_MAX_URLS', 3);
define('NOSPAM_LOG', true);

function nospam_form_part() {
	if(!is_user_logged_in()) {
		$uid = sprintf('%f', microtime(true));
		$_SESSION['NS_' . $uid] = true;
		echo '
		<input type="hidden" name="ns_uid" value="'. $uid .'" />
		<p style="position:absolute; left:-99999px">
			<input type="text" name="email-' . substr(md5($uid), 0, 4) .'" size="30" value="-"/>
		</p>
		';

	}
}
add_action( 'comment_form', 'nospam_form_part');

function nospam_check_for_spam($data) {
	$data['spam_score'] = 0;
	if(!is_user_logged_in()) {
		$type = $data['comment_type'];
		$uid = isset($_POST['ns_uid']) ? $_POST['ns_uid'] : 0;
		$femail = 'email-' . substr(md5($uid), 0, 4);
		$dummy = array();
		$nurls = preg_match_all('@http(?:s)?://@', $data['comment_content'], $dummy);
		$t = floatval($uid);

		$checks = array(
			'is-trackback' => $type === 'trackback' ? 1 : 0,
			'no-session-token' => !isset($_SESSION['NS_' . $uid]) ? 1 : 0,
			'fake-email-field' => (!isset($_POST[$femail]) ||  $_POST[$femail] !== '-') ? 1.0 : 0,
			'number-of-urls' => ($nurls > NOSPAM_MAX_URLS) ? $nurls : 0,
			'referer' => isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'],
					get_site_url()) !== false ? 0 : 1,
			'too-fast' => (microtime(true) - $t) < NOSPAM_MIN_TIME ? (microtime(true) - $t) : 0
		);
		unset($_SESSION['xNS_' . $uid]);
		$score = 0;
		foreach($checks as $k => $v) {
			$score += $v;
		}
		if($score > 0) {
			$data['comment_content'] .= "\n" . json_encode($checks);
			$data['spam_score'] = $score;
		}

	}
	return $data;
}
add_action('preprocess_comment' , 'nospam_check_for_spam');

function nospam_filter_comment($approved, $data) {
	if(!is_user_logged_in()) {
		if ($data['spam_score'] > 0) {
			if(NOSPAM_LOG) {
				return 'spam';
			} else {
				wp_die('You have been a bad bad human.', 'Denied', array('response' => 403));
			}
		}
	}
	return $approved;
}
add_filter('pre_comment_approved', 'nospam_filter_comment' , 100, 2 );

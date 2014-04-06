=== Plugin Name ===
Contributors: oneofone
Tags: comments, spam, antispam, anti-spam, spambot, bot, comment
Requires at least: 3.5.1
Tested up to: 3.9
Stable tag: trunk
License: Apache v2
License URI: http://www.apache.org/licenses/LICENSE-2.0.html

Extremely simple, 0 configuration and 0 user/admin-interaction anti-spam plugin.

== Description ==

This plugin plugin blocks spam in comments automatically, without requiring any end-user input or any javascript.

In a way it's similar to webvitaly's [Anti-Spam](http://wordpress.org/plugins/anti-spam/) however this doesn't
require the user to enter anything at all.

The comment gets marked as spam if any of the following rules are true :

* If the comment is a trackback.
* If the time between loading the page and commenting is less than 10 seconds.
* If the Session variable specific to this form is not set.
* If the hidden input field have a different value than "-".
* If the comment includes more than 3 urls.
* If the referer isn't set properly.

Once the comment gets flagged as spam, and if the auto delete option isn't set, a json string will be appended to it
to show why it was marked, for example :

    {
        "is-trackback": 0,
        "no-session-token": 0,
        "hidden-field": 1,
        "number-of-urls": 5,
        "referer": 0,
        "too-fast": 1.902538061142
    }

Translates to :

1. They changed the hidden input field.
2. They Had 5 URLs in the comment.
3. It Took 1.9 seconds to submit the comment since the page was loaded.

Also note that the time calculations are per-form, so there are no false-positives if the user has multiple pages open
 on the site and commented on 2 of them in a short period of time.

Feel free to fork it and submit patches / fixes on [github](https://github.com/OneOfOne/ooo-nospam)

== Installation ==

1. Install it from here or `git clone https://github.com/OneOfOne/ooo-nospam oneofones-nospam`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Watch `/wp-admin/edit-comments.php?comment_status=spam` to see it in action.

== Frequently Asked Questions ==

= Are there any configuration options? =

As of version 0.6 you can access all configurable options in `/wp-admin/options-general.php?page=ooo-nospam-admin`

== Changelog ==

= v0.7 =
* Fixed a bug where default settings weren't loaded at all.
* Fixed the wording on the maximum number of urls allowed.
* Added a counter of how many comments have been blocked.

= v0.6 =
* Rewrote it to use OOP.
* Added configurable options in admin settings.

= v0.4 =
* First public release.

This plugin plugin blocks spam in comments automatically, without requiring any end-user input or any javascript.

In a way it's similar to webvitaly's [Anti-Spam](http://wordpress.org/plugins/anti-spam/) however this doesn't
require the user to enter anything at all.

The comment gets marked as spam if any of the following rules are true :

* If the comment is a trackback.
* If the time between loading the page and commenting is less than 10 seconds.
* If the Session variable specific to this form is not set.
* If the hidden email field have a different value than "-".
* If the comment includes more than 3 urls.
* If the referer isn't set properly.

Once the comment gets flagged a spam, a json string will be appended to it to show why it was marked, for example :

    {
        "is-trackback": 0,
        "no-session-token": 0,
        "fake-email-field": 1,
        "number-of-urls": 5,
        "referer": 0,
        "too-fast": 1.902538061142
    }

Translates to :

1. The changed the fake email field.
2. Had 5 URLs in it.
3. Took 1.9 seconds to submit from loading the page.

Also note that the time calculations are per-form, so there's no false-positives if the user have multiple pages open
 on the site and comments on 2 of them in a short period of time.

Feel free to fork it and submit patches / fixes on [github](https://github.com/OneOfOne/ooo-nospam)

### Installation ###

1. Download from Wordpress's plugin [registery](https://wordpress.org/plugins/oneofones-nospam/) or clone this repo to your `wp-content/plugins` folder.

2. Activate the plugin through the 'Plugins' menu in WordPress

3. Watch `/wp-admin/edit-comments.php?comment_status=spam` to see it in action.


## Frequently Asked Questions ##

### Are there any configuration options? ###

Well, yes and no, after a lot of testing I came to some sane defaults, if you really want to change them you can edit
 the plugin and change :

    define('NOSPAM_MIN_TIME', 10.0); // number of seconds between loading the page and submitting the comment
    define('NOSPAM_MAX_URLS', 3); //max number of URLs in the comment
    define('NOSPAM_LOG', true); // if set to false, it will dismiss the comment completely instead of saving it as spam.



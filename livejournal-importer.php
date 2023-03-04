<?php
/*
Plugin Name: Dreamwidth XML Importer
Description: Import posts and comments from a Dreamwidth XML file.
Author: @solarbird@solarbird.net / @moira@mastodon.murkworks.net
Version: 0.6
Stable tag: 0.6
License: GPL version 2 or later - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html

IMPORTANT NOTE: THERE ARE OTHER RESOURCES YOU NEED TO ALLOCATE. THIS IMPORTER WILL NOT
WORK ON LARGE IMPORTS IF YOU DO NOT MARSHAL THOSE RESOURCES. See the README.md for a list.

*/

// let us run a long, long time. on large imports, we need this.
ini_set('max_execution_time', '300');

add_action( 'wp_ajax_livejournal_importer', 'livejournal_import_ajax_handler' );

function livejournal_import_ajax_handler() {
	global $lj_api_import;
	check_ajax_referer( 'lj-api-import' );
	if ( !current_user_can( 'publish_posts' ) )
		die('-1');
	if ( empty( $_POST['step'] ) )
		die( '-1' );
	define('WP_IMPORTING', true);
	$result = $lj_api_import->{ 'step' . ( (int) $_POST['step'] ) }();
	if ( is_wp_error( $result ) )
		echo $result->get_error_message();
	die;
}

if ( !defined('WP_LOAD_IMPORTERS') && !defined( 'DOING_AJAX' ) )
	return;

// Load Importer API
require_once ABSPATH . 'wp-admin/includes/import.php';

if ( !class_exists( 'WP_Importer' ) ) {
	$class_wp_importer = ABSPATH . 'wp-admin/includes/class-wp-importer.php';
	if ( file_exists( $class_wp_importer ) )
		require_once $class_wp_importer;
}

// XML-RPC library for communicating with LiveJournal API
// We don't actually use this, but the LiveJournal importer did, so I'm leaving the include.
require_once( ABSPATH . WPINC . '/class-IXR.php' );

// WP HTTP based IXR Client class from 3.1 onwards
if ( file_exists( ABSPATH . WPINC . '/class-wp-http-ixr-client.php' ) ) {
	$GLOBALS['live_journal_importer_ixr_class'] = 'WP_HTTP_IXR_Client';
	require_once( ABSPATH . WPINC . '/class-wp-http-ixr-client.php' );
} else {
	$GLOBALS['live_journal_importer_ixr_class'] = 'IXR_Client';
}

/**
 * Dreamwidth XML importer, based on WordPress's LiveJournal Importer
 *
 * @package WordPress
 * @subpackage Importer
 */
if ( class_exists( 'WP_Importer' ) ) {
class LJ_API_Import extends WP_Importer {

	var $comments_url = 'http://www.dreamwidth.org/export_comments.bml';
	var $ixr_url      = 'http://www.dreamwidth.org/interface/xmlrpc';
	var $ixr;
	var $username;
	var $password;
	var $comment_meta;
	var $comments;
	var $usermap;
	var $postmap;
	var $commentmap;
	var $pointers = array();

	// This list taken from LJ, they don't appear to have an API for it
	var $moods = array( '1' => 'aggravated',
						'10' => 'discontent',
						'100' => 'rushed',
						'101' => 'contemplative',
						'102' => 'nerdy',
						'103' => 'geeky',
						'104' => 'cynical',
						'105' => 'quixotic',
						'106' => 'crazy',
						'107' => 'creative',
						'108' => 'artistic',
						'109' => 'pleased',
						'11' => 'energetic',
						'110' => 'bitchy',
						'111' => 'guilty',
						'112' => 'irritated',
						'113' => 'blank',
						'114' => 'apathetic',
						'115' => 'dorky',
						'116' => 'impressed',
						'117' => 'naughty',
						'118' => 'predatory',
						'119' => 'dirty',
						'12' => 'enraged',
						'120' => 'giddy',
						'121' => 'surprised',
						'122' => 'shocked',
						'123' => 'rejected',
						'124' => 'numb',
						'125' => 'cheerful',
						'126' => 'good',
						'127' => 'distressed',
						'128' => 'intimidated',
						'129' => 'crushed',
						'13' => 'enthralled',
						'130' => 'devious',
						'131' => 'thankful',
						'132' => 'grateful',
						'133' => 'jealous',
						'134' => 'nervous',
						'14' => 'exhausted',
						'15' => 'happy',
						'16' => 'high',
						'17' => 'horny',
						'18' => 'hungry',
						'19' => 'infuriated',
						'2' => 'angry',
						'20' => 'irate',
						'21' => 'jubilant',
						'22' => 'lonely',
						'23' => 'moody',
						'24' => 'pissed off',
						'25' => 'sad',
						'26' => 'satisfied',
						'27' => 'sore',
						'28' => 'stressed',
						'29' => 'thirsty',
						'3' => 'annoyed',
						'30' => 'thoughtful',
						'31' => 'tired',
						'32' => 'touched',
						'33' => 'lazy',
						'34' => 'drunk',
						'35' => 'ditzy',
						'36' => 'mischievous',
						'37' => 'morose',
						'38' => 'gloomy',
						'39' => 'melancholy',
						'4' => 'anxious',
						'40' => 'drained',
						'41' => 'excited',
						'42' => 'relieved',
						'43' => 'hopeful',
						'44' => 'amused',
						'45' => 'determined',
						'46' => 'scared',
						'47' => 'frustrated',
						'48' => 'indescribable',
						'49' => 'sleepy',
						'5' => 'bored',
						'51' => 'groggy',
						'52' => 'hyper',
						'53' => 'relaxed',
						'54' => 'restless',
						'55' => 'disappointed',
						'56' => 'curious',
						'57' => 'mellow',
						'58' => 'peaceful',
						'59' => 'bouncy',
						'6' => 'confused',
						'60' => 'nostalgic',
						'61' => 'okay',
						'62' => 'rejuvenated',
						'63' => 'complacent',
						'64' => 'content',
						'65' => 'indifferent',
						'66' => 'silly',
						'67' => 'flirty',
						'68' => 'calm',
						'69' => 'refreshed',
						'7' => 'crappy',
						'70' => 'optimistic',
						'71' => 'pessimistic',
						'72' => 'giggly',
						'73' => 'pensive',
						'74' => 'uncomfortable',
						'75' => 'lethargic',
						'76' => 'listless',
						'77' => 'recumbent',
						'78' => 'exanimate',
						'79' => 'embarrassed',
						'8' => 'cranky',
						'80' => 'envious',
						'81' => 'sympathetic',
						'82' => 'sick',
						'83' => 'hot',
						'84' => 'cold',
						'85' => 'worried',
						'86' => 'loved',
						'87' => 'awake',
						'88' => 'working',
						'89' => 'productive',
						'9' => 'depressed',
						'90' => 'accomplished',
						'91' => 'busy',
						'92' => 'blah',
						'93' => 'full',
						'95' => 'grumpy',
						'96' => 'weird',
						'97' => 'nauseated',
						'98' => 'ecstatic',
						'99' => 'chipper' );

	function header() {
		echo '<div class="wrap">';
		screen_icon();
		echo '<h2>' . __( 'Import Dreamwidth XML' , 'livejournal-importer') . '</h2>';
	}

	function footer() {
		echo '</div>';
	}

	function greet() {
		?>
		<div class="narrow">
		<form action="admin.php?import=livejournal" method="post">
		<?php wp_nonce_field( 'lj-api-import' ) ?>
		<input type="hidden" name="step" value="1" />
		<input type="hidden" name="login" value="true" />
		<p><?php _e( 'Howdy! This plugin allows you to import all your entries and comments from a Dreamwidth-generated XML export file. It assumes you have already created this file using <a href=https://github.com/dreamwidth/dreamwidth/blob/main/src/jbackup/jbackup.pl>the Dreamwidth jbackup perl utility</a>, named it <b>importme.xml</b>, and placed it in the directory <b>wp-content/plugins/livejournal-importer</b> with permissions matching those of the other files already in that directory.' , 'livejournal-importer') ?></p>
		<p><?php _e( 'This plugin CANNOT generate the XML export file for you; you MUST create it yourself.' , 'livejournal-importer') ?></p>
		<p><?php _e( 'If your WordPress blog has either an "imported post" or "imported-post" category, this plugin will apply that category to all of these posts. Otherwise, all posts will be marked Uncategorised. It is still safe to create that category now, without leaving the importer, but the category should be created before actually beginning the import.' , 'livejournal-importer') ?></p>

		<p><?php _e( 'If the file contains any Dreamwidth entries which are marked as private, they will be imported as <b>public</b> entries <b>unless</b> you provide a password in the field below. If you provide a password, such entries will be password-protected when they are imported so that only people who know the password can see them.' , 'livejournal-importer') ?></p>
		<p><?php _e( 'Enter the password you would like to use for all protected entries here:' , 'livejournal-importer') ?></p>
		<table class="form-table">

		<tr>
		<th scope="row"><label for="protected_password"><?php _e( 'Protected Post Password' , 'livejournal-importer') ?></label></th>
		<td><input type="text" name="protected_password" id="protected_password" class="regular-text" /></td>
		</tr>

		</table>

		<p><?php _e( "<strong>WARNING:</strong> This can take a really long time if you have a lot of entries in your Dreamwidth import, or a lot of comments. You should only start this process if you can leave your computer alone while it finishes the import. However, should the import process be interrupted, you may manually trim the successfully-imported posts from the beginning of the XML file and start the importer again." , 'livejournal-importer') ?></p>

		<p class="submit">
			<input type="submit" class="button" value="<?php esc_attr_e( 'Begin Dreamwidth import' , 'livejournal-importer') ?>" />
		</p>

		</form>
		</div>
		<?php
	}

	function _normalize_tag( $matches ) {
		return '<' . strtolower( $matches[1] );
	}

	// THIS IS ENTIRELY FROM livejournal-importer AND IS NOT USED HERE. But I kept it here in case anyone
	// wants to deal from it to make this importer better.
	function import_post( $post ) {
		global $wpdb;

		$user = wp_get_current_user();
		$post_author      = $user->ID;
		$post['security'] = !empty( $post['security'] ) ? $post['security'] : '';
		$post_status      = ( 'private' == trim( $post['security'] ) ) ? 'private' : 'publish'; // Only me
		$post_password    = ( 'usemask' == trim( $post['security'] ) ) ? $this->protected_password : ''; // "Friends" via password

		// For some reason, LJ sometimes sends a date as "2004-04-1408:38:00" (no space btwn date/time)
		$post_date = $post['eventtime'];
		if ( 18 == strlen( $post_date ) )
			$post_date = substr( $post_date, 0, 10 ) . ' ' . substr( $post_date, 10 );

		// Cleaning up and linking the title
		$post_title = isset( $post['subject'] ) ? trim( $post['subject'] ) : '';
		$post_title = $this->translate_lj_user( $post_title ); // Translate it, but then we'll strip the link
		$post_title = strip_tags( $post_title ); // Can't have tags in the title in WP
		$post_title = $wpdb->escape( $post_title );

		// Clean up content
		$post_content = $post['event'];
		$post_content = preg_replace_callback( '|<(/?[A-Z]+)|', array( &$this, '_normalize_tag' ), $post_content );
		// XHTMLize some tags
		$post_content = str_replace( '<br>', '<br />', $post_content );
		$post_content = str_replace( '<hr>', '<hr />', $post_content );
		// lj-cut ==>  <!--more-->
		$post_content = preg_replace( '|<lj-cut text="([^"]*)">|is', '<!--more $1-->', $post_content );
		$post_content = str_replace( array( '<lj-cut>', '</lj-cut>' ), array( '<!--more-->', '' ), $post_content );
		$first = strpos( $post_content, '<!--more' );
		$post_content = substr( $post_content, 0, $first + 1 ) . preg_replace( '|<!--more(.*)?-->|sUi', '', substr( $post_content, $first + 1 ) );
		// lj-user ==>  a href
		$post_content = $this->translate_lj_user( $post_content );
		//$post_content = force_balance_tags( $post_content );
		$post_content = $wpdb->escape( $post_content );

		// Handle any tags associated with the post
		$tags_input = !empty( $post['props']['taglist'] ) ? $post['props']['taglist'] : '';

		// Check if comments are closed on this post
		$comment_status = !empty( $post['props']['opt_nocomments'] ) ? 'closed' : 'open';

		echo '<li>';
		printf( __( 'Imported post <strong>%s</strong>...' , 'livejournal-importer'), stripslashes( $post_title ) );
		$postdata = compact( 'post_author', 'post_date', 'post_content', 'post_title', 'post_status', 'post_password', 'tags_input', 'comment_status' );
		$post_id = wp_insert_post( $postdata, true );
		if ( is_wp_error( $post_id ) ) {
			if ( 'empty_content' == $post_id->get_error_code() )
				return; // Silent skip on "empty" posts
			return $post_id;
		}
		if ( !$post_id ) {
			_e( 'Couldn&#8217;t get post ID (creating post failed!)' , 'livejournal-importer');
			echo '</li>';
			return new WP_Error( 'insert_post_failed', __( 'Failed to create post.' , 'livejournal-importer') );
		}

                // Save the permalink on LJ in case we want to link back or something
                add_post_meta( $post_id, 'lj_permalink', $post['url'] );
		echo '</li>';
	}

	// Convert lj-user tags to links to that user
	function translate_lj_user( $str ) {
		return preg_replace( '|<lj\s+user\s*=\s*["\']([\w-]+)["\']>|', '<a href="http://$1.dreamwidth.org/">$1</a>', $str );
	}

	// THIS FUNCTION IS FROM livejournal-importer AND IS NOT USED HERE. But I kept it here in case
	// anyone wants to make this importer better.
	function insert_postmeta( $post_id, $post ) {
		// Need the original LJ id for comments
		add_post_meta( $post_id, 'lj_itemid', $post['itemid'] );

		// And save the permalink on LJ in case we want to link back or something
		add_post_meta( $post_id, 'lj_permalink', $post['url'] );

		// Supports the following "props" from LJ, saved as lj_<prop_name> in wp_postmeta
		// 		Adult Content - adult_content
		// 		Location - current_coords + current_location
		// 		Mood - current_mood (translated from current_moodid)
		// 		Music - current_music
		// 		Userpic - picture_keyword
		foreach ( array( 'adult_content', 'current_coords', 'current_location', 'current_moodid', 'current_music', 'picture_keyword' ) as $prop ) {
			if ( !empty( $post['props'][$prop] ) ) {
				if ( 'current_moodid' == $prop ) {
					$prop = 'current_mood';
					$val = $this->moods[ $post['props']['current_moodid'] ];
				} else {
					$val = $post['props'][$prop];
				}
				add_post_meta( $post_id, 'lj_' . $prop, $val );
			}
		}
	}

	// Gets the post_ID that a LJ post has been saved as within WP
	function get_wp_post_ID( $post ) {
		global $wpdb;

		if ( empty( $this->postmap[$post] ) )
		 	$this->postmap[$post] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = 'lj_itemid' AND meta_value = %d", $post ) );

		return $this->postmap[$post];
	}

	// Gets the comment_ID that a LJ comment has been saved as within WP
	function get_wp_comment_ID( $comment ) {
		global $wpdb;
		if ( empty( $this->commentmap[$comment] ) )
		 	$this->commentmap[$comment] = $wpdb->get_var( $wpdb->prepare( "SELECT comment_ID FROM $wpdb->comments WHERE comment_karma = %d", $comment ) );
		return $this->commentmap[$comment];
	}

	function dispatch() {
		if ( empty( $_REQUEST['step'] ) )
			$step = 0;
		else
			$step = (int) $_REQUEST['step'];

		$this->header();

		switch ( $step ) {
			case -1 :
				$this->cleanup();
				// Intentional no break
			case 0 :
				$this->greet();
				break;
			case 1 :
			case 2 :
			case 3 :
				check_admin_referer( 'lj-api-import' );
				$result = $this->{ 'step' . $step }();
				if ( is_wp_error( $result ) ) {
					$this->throw_error( $result, $step );
				}
				break;
		}

		$this->footer();
	}

	// THIS IS WHERE THE ACTUAL IMPORTING STARTS. THIS IS THE IMPORTANT BIT
	// No really, this is literally the entire importer. I wrote this needing it to work
	// exactly once for me, and then people started asking for it. So here we are.
	function setup() {
		global $verified;
                global $wpdb;

		// YES IT'S A NESTED FUNCTION no this wasn't a great idea but it seemed okay at the time
		// comments are written here, it's recursive for levels since that shit matters.
		function WriteComment( $MessageParent, $CommentParent, $comments ) {

		foreach ( $comments as $commentblock ) {
        		foreach ( $commentblock->comment as $topcomment ) {
            			$current_user = wp_get_current_user();
            			// DO WE NEED TO FIX THE DATETIME IT IS WEIRD
            			$CommentTime = $topcomment->date;
				//echo "Comment time initially is " . $CommentTime . "<br>";
				//ob_flush(); flush();
            			// make it normal
            			$CommentTime = substr($CommentTime, 0, 10) . " " . substr($CommentTime, 11, 8);

				//echo "Comment time set " . $CommentTime . "<br>";
            			// GMT time?
            			$CommentDateGMT = gmdate('Y-m-d H:i:s',strtotime( $topcomment->date ) );

				//echo "in GMT: " . $CommentDateGMT . "<br>";
				//ob_flush(); flush();

            			// Name of comment poster
            			$CommentAuthor = substr($topcomment['poster'][0],0);
            			if ( $CommentAuthor == "" ) { $CommentAuthor = "ext_anonymous"; }

            			// only do this when valid
            			if ( ( substr( $CommentAuthor, 0, 4) !== 'ext_') && ( $CommentAuthor !== "" ) ) {
                			$CommentAuthorURL = "https://" . $CommentAuthor . ".dreamwidth.org";
                			} else {
                			$CommentAuthorURL = '';
                			}

            			// No email available in dreamwidth comments ever
            			$CommentAuthorEmail = '';

            			$CommentData = array(
                			'comment_post_ID' => $MessageParent,
                			'comment_parent' => $CommentParent,
                			'comment_author' => $CommentAuthor,
                			'comment_author_email' => $CommentAuthorEmail,
                			'comment_author_url' => $CommentAuthorURL,
                			'comment_content' => $topcomment->body,
                			'user_id' => 0,
               				'comment_date' => $CommentTime,
                			'comment_date_gmt' => $CommentDateGMT,
                			'comment_approved' => 1,
                			'comment_type' => 'comment'
            			);

				if ( 1 == 1 ) { // CHANGED TO 0 == 1 FOR TESTING, 1 == 1 FOR REAL IMPORTING
            			// first filter
            			$CommentData = wp_filter_comment( $CommentData );
            			$NewComment = wp_insert_comment( $CommentData );
            			if ( $NewComment ) {
                			$CommentParent = $NewComment;
                			} else {
                			echo "Comment creation failed!<br>";
                			ob_flush(); flush();
                			}

            			WriteComment( $MessageParent, $CommentParent, $topcomment->comments);
            			} // end 1 == 1
        			}
    			}
    		return;
		}


	// MAIN IMPORTER LOOP - LET'S GO FINDME2 FIND ME 2
	// THIS IS WHERE POST IMPORTING ACTUALLY HAPPENS

	// but first, we load our database. does that sound insane to me? yes. but then I remembered
	// that my truly massive combined LJ and Dreamwidth was like 60 megs and that's less than a
	// large hi-resolution photo. We can just load it.

        $ourXMLFile = ABSPATH . "wp-content/plugins/livejournal-importer/importme.xml";
        // echo "our filename: |" . $ourXMLFile . "|<br>";
	$xml=simplexml_load_file( $ourXMLFile ) or die("Error: Cannot load XML file and create XML object");
	echo "XML database successfully loaded<br>";

	// let's get the password from the form since we're keeping that
    	// This is the password to set on protected posts
    	if ( !empty( $_POST['protected_password'] ) ) {
        	$this->protected_password = $_POST['protected_password'];
        	update_option( 'ljapi_protected_password', $this->protected_password );
        	} else {
        	$this->protected_password = get_option( 'ljapi_protected_password' );
    		}

	// iterate through all messages in the file
	//
	foreach ( $xml->events->children() as $entries ) {
    		echo "Importing post nr. " . $entries['jitemid'] . ": ";
    		if ( isset( $entries['security'] ) ) { echo "filtered:"; }
		// honestly should probably try to make sure there's no HTML in the subject here but at the time I couldn't be buggered, it's done later
    		echo $entries->subject . " | " . $entries->date . " | ";
		ob_flush(); flush();

		// THIS IS WHAT WE NEED TO CLEAN UP TO IMPORT A POST
    		$user = wp_get_current_user();
    		$post_author      = $user->ID;
    		$entries['security'] = !empty( $entries['security'] ) ? $entries['security'] : '';
    		$post_status      = ( 'private' == trim( $entries['security'] ) ) ? 'private' : 'publish'; // Only me
    		$post_password    = ( 'usemask' == trim( $entries['security'] ) ) ? $this->protected_password : ''; // "Friends" via password

    		// For some reason, LJ sometimes sends a date as "2004-04-1408:38:00" (no space btwn date/time)
		// Dreamwidth might be doing that too, so let's keep it.
    		$post_date = $entries->date;
    		if ( 18 == strlen( $post_date ) ) {
        		$post_date = substr( $post_date, 0, 10 ) . ' ' . substr( $post_date, 10 );
        		} else {
        		$post_date = substr( $post_date, 0, 10 ) . ' ' . substr( $post_date, 11 );
        		}
    		// duplicate for GMT
    		$post_date_gmt = $post_date;

    		// Cleaning up and linking the title
    		$post_title = isset( $entries->subject ) ? trim( $entries->subject ) : '';
    		$post_title = strip_tags( $post_title ); // Can't have tags in the title in WP
    		$post_title = $wpdb->escape( $post_title );

    		// Handle any tags associated with the post
    		// We do this first also to recover the 'current music' tag. This is also where you'd
		// try to recover and decode "Current Mood" if you wanted to try but it's just a number
		// and not documented. LJ's list is up higher in this file if you want to try.
    		$tags_input = 'no-tag';
    		$CurrentMusic = "";
    		$PreviousName = "";
		// this is like this for a reason and that reason is not my fault.
    		foreach ($entries->props->prop as $dumbass) {
        		foreach ($dumbass->attributes() as $a => $b) {
				// echo "|" . (string)$a . "|" . (string)$b . "|<br>";
            			if ("taglist" == $PreviousName ) { $tags_input = (string)$b; }
            			if ("current_music" == $PreviousName ) { $CurrentMusic = (string)$b; }
            			//ob_flush(); flush();
            			$PreviousName = (string)$b;
            			}
        		}
    
    		// Clean up content
    		$post_content = $entries->event;
    		// Add current music to the bottom if it's there
    		if ($CurrentMusic !== "") {
        		$post_content = $post_content . "<br />&nbsp;<p><em><small>Current music: " . $CurrentMusic . "</small></em></p>";
        		}
    		$post_content = preg_replace_callback( '|<(/?[A-Z]+)|', array( &$this, '_normalize_tag' ), $post_content );
    		// XHTMLize some tags
    		$post_content = str_replace( '<br>', '<br />', $post_content );
    		$post_content = str_replace( '<hr>', '<hr />', $post_content );
    		// lj-cut ==>  <!--more-->
    		$post_content = preg_replace( '|<lj-cut text="([^"]*)">|is', '<!--more $1-->', $post_content );
    		$post_content = str_replace( array( '<lj-cut>', '</lj-cut>' ), array( '<!--more-->', '' ), $post_content );
    		$first = strpos( $post_content, '<!--more' );
    		$post_content = substr( $post_content, 0, $first + 1 ) . preg_replace( '|<!--more(.*)?-->|sUi', '', substr( $post_content, $first + 1 ) );
    		// lj-user ==>  a href
    		$post_content = $this->translate_lj_user( $post_content );
    		$post_content = $wpdb->escape( $post_content );

    		// Mark all these as imported posts
    		$post_category = array (0);
    		$post_category[0] = get_cat_ID( "imported post" );
    		if ( $post_category[0] == 0 ) { $post_category[0] = get_cat_ID( "imported-post" ); }

    		// Check if comments are closed on this post
    		$comment_status = !empty( $entries['props']['opt_nocomments'] ) ? 'closed' : 'open';
		// this is debugging because it was too annoying to comment so zero equals one lol.
		// don't take it out thanks
		if ( 0 == 1 ) {
    			echo "GOING IN WITH:<br>";
    			echo "AUTHOR: " . $post_author . "<br>";
    			echo "Date: " . $post_date . "<br>";
    			echo "Subject: " . $post_title . "<br>";
    			echo "Status: " . $post_status . "<br>";
    			echo "Password: " . $post_password . "<br>";
    			echo "Tags: " . $tags_input . "<br>";
    			echo "Comment status: " . $comment_status . "<br>";
    			//echo "Content:<br>" . $post_content . "<br>";
    			ob_flush(); flush();
			}

		// one equals one is also for debugging, don't take it out if you're going to send code back to me.
		if ( 1 == 1 ) {
    			$postdata = compact( 'post_author', 'post_date', 'post_date_gmt', 'post_content', 'post_title', 'post_status', 'post_password', 'post_category', 'tags_input', 'comment_status' );
    			$postdata = sanitize_post( $postdata );
    			$post_id = wp_insert_post( $postdata, true );
    			if ( is_wp_error( $post_id ) ) {
        			if ( !$post_id ) {
            			echo "...couldn't post this one luv sorry<br>";
            			ob_flush(); flush();
            			}
        		if ( 'empty_content' != $post_id->get_error_code() ) {
            			echo "...got an error that wasn't empty_content<br>";
            			ob_flush(); flush();
            			}
        		} else {
        		// WE HAVE POSTED!
        		echo "...imported. Adding linkback to post meta... ";
        		ob_flush(); flush();
        		// Save the permalink on LJ in case we want to link back or something
        		add_post_meta( $post_id, 'lj_permalink', $post['url'] );
        		echo "added.<br>";
        		ob_flush(); flush();
        		}
    		}
    		$MessageParent = $post_id;
    		$CommentParent = 0;
    		WriteComment($MessageParent, $CommentParent, $entries->comments);
    		//echo "---[message complete]<br>";
    		//ob_flush(); flush();
    	}

	echo "Import complete! Enjoy your posts.<br>";
	ob_flush(); flush();

	return;

	}

	// Check form inputs and start importing posts
	function step1() {
		global $verified;

		do_action( 'import_start' );

		set_time_limit( 0 );
		update_option( 'ljapi_step', 1 );
		// don't need this, we already have xml
		// $this->_create_ixr_client();
		
		// Call this->setup() which is literally everything now.
		$setup = $this->setup();

                $this->cleanup();
                do_action( 'import_done', 'livejournal' );
	}

	function step2() {
		echo "STEP 2: Do Nothing<br>";
	}

	// Re-thread comments already in the DB
	function step3() {
                echo "STEP 3: Do Nothing<br>";
	}

	// Output an error message with a button to try again.
	function throw_error( $error, $step ) {
		echo '<p><strong>' . $error->get_error_message() . '</strong></p>';
		echo $this->next_step( $step, __( 'Try Again' , 'livejournal-importer') );
	}

	// Returns the HTML for a link to the next page
	function next_step( $next_step, $label, $id = 'ljapi-next-form' ) {
		$str  = '<form action="admin.php?import=livejournal" method="post" id="' . $id . '">';
		$str .= wp_nonce_field( 'lj-api-import', '_wpnonce', true, false );
		$str .= wp_referer_field( false );
		$str .= '<input type="hidden" name="step" id="step" value="' . esc_attr($next_step) . '" />';
		$str .= '<p><input type="submit" class="button" value="' . esc_attr( $label ) . '" /> <span id="auto-message"></span></p>';
		$str .= '</form>';

		return $str;
	}

	// Remove all options used during import process and
	// set wp_comments entries back to "normal" values
	function cleanup() {
		global $wpdb;

		delete_option( 'ljapi_username' );
		delete_option( 'ljapi_password' );
		delete_option( 'ljapi_protected_password' );
		delete_option( 'ljapi_verified' );
		delete_option( 'ljapi_total' );
		delete_option( 'ljapi_count' );
		delete_option( 'ljapi_lastsync' );
		delete_option( 'ljapi_last_sync_count' );
		delete_option( 'ljapi_sync_item_times' );
		delete_option( 'ljapi_lastsync_posts' );
		delete_option( 'ljapi_post_batch' );
		delete_option( 'ljapi_imported_count' );
		delete_option( 'ljapi_maxid' );
		delete_option( 'ljapi_usermap' );
		delete_option( 'ljapi_highest_id' );
		delete_option( 'ljapi_highest_comment_id' );
		delete_option( 'ljapi_comment_batch' );
		delete_option( 'ljapi_step' );

		$wpdb->update( $wpdb->comments,
						array( 'comment_karma' => 0, 'comment_agent' => 'WP LJ Importer', 'comment_type' => '' ),
						array( 'comment_type' => 'livejournal-done' ) );
		$wpdb->update( $wpdb->comments,
						array( 'comment_karma' => 0, 'comment_agent' => 'WP LJ Importer', 'comment_type' => '' ),
						array( 'comment_type' => 'livejournal' ) );

		do_action( 'import_end' );
	}

	function _create_ixr_client() {
		global $live_journal_importer_ixr_class, $wp_version;

		if ( !$this->ixr ) {
			$this->ixr = new $live_journal_importer_ixr_class( $this->ixr_url, false, 80, 30 );
			$this->ixr->useragent = 'WordPress/' . $wp_version . '; Dreamwidth Importer - ' . get_bloginfo( 'url' );
		}
	}
}

} // class_exists( 'WP_Importer' )

function livejournal_importer_init() {
	global $lj_api_import;

	load_plugin_textdomain( 'livejournal-importer', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' ); 
	$lj_api_import = new LJ_API_Import();

	register_importer( 'livejournal', __( 'LiveJournal' , 'livejournal-importer'), __( 'Import posts from LiveJournal using their API.' , 'livejournal-importer'), array( $lj_api_import, 'dispatch' ) );
}
add_action( 'init', 'livejournal_importer_init' );

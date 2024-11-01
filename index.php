<?php
/* 
 * Plugin Name: wpcj Chimp
 * Plugin URI: http://www.wpcj.com/plugins/chimp
 * Description: wpCJ Chimp allows you to automatically add a new registered user to your MailChimp list once they registered with your blog depending on his/her selection.
 * Version: 1.2
 * Author: Williams Castillo
 * Author URI: http://www.williamscastillo.com/
 *  
 * Copyright 2010  Williams Castillo  (email : eduven@gmail.com)
*/

define('SISTEMA','wpcjChimp');
define('CONTEXT','wpcjchimp');
define('VERSION','1.2');

$wpcjChimp_plugin 				= SISTEMA;
$wpcjChimp_first_module 		= plugin_basename(dirname(__FILE__)).'/index.php';
$wpcjChimp_message				= '';		// Usado para mensajes informativos sobre operaciones
$wpcjChimp_error				= '';		// Usado para mensajes de error sobre operaciones
$wpcjChimp_header				= false;	// Usado para indicar si la cabezera con titulo ya fue enviada al usuario
$MCHandler 						= null;		// handle del MailChimp

function wpcjChimp_loginform() {
	$wpcjchimp = get_option('wpcjchimp');
	
	$wpcjChimp_html = wpcjChimp_generate_code();
	
	echo $wpcjChimp_html."\n<br />\n";
}

function wpcjChimp_validate_registration($login,$email,$errors){	
	$wpcjChimp_errors 	= '';
	$ok 				= false;
	
	if ( $_REQUEST['wpcjchimp-subscribe'] == 1 ) {
		$wpcjchimp 	= get_option('wpcjchimp');
		
		$dbfields	= $wpcjchimp['dbfields'];
		if ( is_array($dbfields) ) {
			if ( count($dbfields) != 0 ) {
				$vars 		= $dbfields['vars'];
				
				$ok = true;
			}
		}
		
		if ( $ok ) {
			if ( is_array($vars) ) {
				for ( $i = 0; $i < count($vars); $i++ ) {
					if ( $vars[$i]['tag'] != 'EMAIL' && $vars[$i]['public'] == 1 ) {
						if ( $vars[$i]['req'] == 1 ) {
							// Mandatory fields
							$name = 'wpcjchimp-'.$vars[$i]['tag'];
							if ( $_REQUEST[$name] == '' ) {
								$wpcjChimp_errors .= '<br/><strong>ERROR</strong>: <em>'.$vars[$i]['name'].'</em> is mandatory.';
							}
						}
					}
				}
			}
		}
	}
	
	if ($wpcjChimp_errors != '') {
		$errors->add('wpcjChimp_error',$wpcjChimp_errors);
	}
}

function wpcjChimp_process_registration($user_id) {
global $MCHandler;
	$ok = false;
	
	if ( $_REQUEST['wpcjchimp-subscribe'] == 1 ) {
		
		$wpcjchimp	= get_option('wpcjchimp');
		$listid		= $wpcjchimp['listid'];
		$apikey		= $wpcjchimp['mcapi'];
		
		$merge_vars = array();
		$user_email 				= $_REQUEST['user_email'];
		if ($_REQUEST['wpcjchimp-groups'] != '' ) {
			$merge_vars['INTERESTS'] 	= implode(',',$_REQUEST['wpcjchimp-groups']);
		}
		$merge_vars['OPTINIP']		= $_SERVER['REMOTE_ADDR'];
		
		$dbfields	= $wpcjchimp['dbfields'];
		if ( is_array($dbfields) ) {
			if ( count($dbfields) != 0 ) {
				$vars 		= $dbfields['vars'];
				
				$ok = true;
			}
		}	
		
		if ( $ok ) {
			if ( is_array($vars) ) {
				for ( $i = 0; $i < count($vars); $i++ ) {
					if ( $vars[$i]['tag'] != 'EMAIL' && $vars[$i]['public'] == 1 ) {
						$name = 'wpcjchimp-'.$vars[$i]['tag'];
						$merge_vars[$vars[$i]['tag']] = $_REQUEST[$name];

						if ( $vars[$i]['tag'] == 'FNAME' ) {
							update_usermeta($user_id,'first_name',$REQUEST['FNAME']);
						}
						if ( $vars[$i]['tag'] == 'LNAME' ) {
							update_usermeta($user_id,'last_name',$REQUEST['LNAME']);
						}
					}
				}
			}
		}
				
		if (is_null($MCHandler)) {
			if ( !class_exists('MCAPI') ) {
				require_once(wpcjChimp_get_plugin_url('path').'/MCAPI.class.php');
			}
			$MCHandler 	= new MCAPI($apikey);		
		}
		if ( !$MCHandler->errorCode ) {
			if ( $wpcjchimp['double-optin'] != 1 ) {
				$double = false;
			} else {
				$double = true;
			}
			if ( $double || $wpcjchimp['welcome'] != 1 ) {
				$welcome = false;
			} else {
				$welcome = true;
			}
			//echo nl2br(print_r($merge_vars,true));die();
			$retval = $MCHandler->listSubscribe( $listid, $user_email, $merge_vars,'html',$double,false,true,$welcome);
		}
	}
}

function wpcjChimp_get_plugin_url($type='url') {
	if ( !defined('WP_CONTENT_URL') )
		define( 'WP_CONTENT_URL', get_option('siteurl') . '/wp-content');
	if ( !defined('WP_CONTENT_DIR') )
		define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' );
	if ($type=='path') { return WP_CONTENT_DIR.'/plugins/'.plugin_basename(dirname(__FILE__)); }
	else { return WP_CONTENT_URL.'/plugins/'.plugin_basename(dirname(__FILE__)); }
}

function wpcjChimp_load_contextual_help() {
global $wpcjChimp_first_module,$wpcjChimp_plugin;
	$options = get_option('wpcjchimp');
	switch ($_SERVER['SCRIPT_NAME']) {
	default:
		$echoed = false;
		
		$page = $_REQUEST['page'];
		if ($page == plugin_basename(dirname(__FILE__)).'/index.php') {
			$page = $wpcjChimp_first_module;
		}
		$page = str_replace('wpcjChimp_','',$page);
		$page = str_replace('.php','',$page);
		
		$module = wpcjChimp_get_plugin_url('path')."/modules/$page.php";
		
		if (file_exists($module)) {
			
			require_once($module);				
			if ( function_exists('wpcjChimp_contextual_help') ) {
				echo '<strong>Contextual Help for '.SISTEMA.'</strong><hr/>';
				wpcjChimp_contextual_help(); 
				
				$echoed=true;
			}
		}
		if ( !$echoed ) {
			echo '<strong>Contextual Help for '.SISTEMA.'</strong><hr/>';
			echo <<<TEXTO
			<ul>
			<li><strong>wpCJ Chimp Settings</strong><br/>
			<blockquote>
			<strong><em>Your MailChimp API Key:</em></strong> Get your MailChimp API Key from Your Account at Mailchimp.com.<br/>
			<strong><em>MailChimp List:</em></strong> Once you have entered your API Key, you will be presented with your lists. Select the one in which you want your new blog users to be subscribed to.<br/>
			<strong><em>Double Opt-in:</em></strong> Specify if you want a confirmation email to be sent before a user is definitelly subscribed to your list.<br/>
			<strong><em>Send Welcome Email:</em></strong> If Double Opt-in is NO and this field is YES, your users will receive a Welcome email after a successfully sign-up.<br/>
			<strong><em>Update List Database:</em></strong> If you ever make changes to your List Database, or you change the list for the matter, force an update so the plugin knows that the new fields should be fetched.<br/>
			<strong><em>Mandatory Fields:</em></strong> These are the fields that will be required to fill by the user if he/she decides to get subscribed to your list.<br/>
			<strong><em>Optional Fields:</em></strong> This is the list of optional fields that the user might fill. Specify if you want to ask for them at sign-up time.<br/>
			<strong><em>Interest Groups:</em></strong> This is the list of Interest Groups of the selected list. Specify if you want to ask for them at sign-up time.<br/>
			<strong><em>Confirmation question/Default Answer:</em></strong> If you want your visitors to click a checkbox in order to confirm that they want to subscribe to your list, you must enter the question here. I.e.- "Subscribe to our Newsletter!" You can also specify a default answer for this question.<br/>
			<strong><em>Text to be shown to the visitors:</em></strong> This is a text that will be shown to your visitors right before the List Database fields. You can use HTML or leave it blank if you wish so.<br/>			
			</blockquote></li>
			<li><strong>wpCJ.com News</strong><br/>
			This is the news feed of our website, <a href="http://www.wpcj.com/" title="wpCJ - WordPress & Commission Junction working together!">http://www.wpcj.com/</a>. You can dismiss it at anytime.
			</li>
			<li><strong>Partial Preview of the Sign-up Page</strong><br/>
			In this box you will see an approximation of what your visitors will see below the usual WordPress registration fields.
			</li>
			<li><strong><em>Feeling Generous Today?</em> link</strong><br/>
			This is obviously a donate button. If you want to invite me one beer or two, they will be most welcome!
			<ul>
TEXTO;
		}	
	break;
	}
}

function wpcjChimp_declare_options() {
	register_setting( 'wpcjChimp_options', 'wpcjchimp' );	
}

function wpcjChimp_create_menu() {
global $wpcjChimp_rol,$wpcjChimp_first_module;

	add_options_page('wpCJ Chimp Settings', 'wpCJ Chimp', 8, __FILE__, 'wpcjChimp_load_module');
}

function wpcjChimp_load_module() {
global $wpcjChimp_error,$wpcjChimp_first_module;

	$page = $_REQUEST['page'];
	if ($page == plugin_basename(dirname(__FILE__)).'/index.php') {
		wpcjChimp_option_page();
		return ;
	}
	$page = str_replace('wpcjChimp_','',$page);
	$page = str_replace('.php','',$page);
	
	$module = wpcjChimp_get_plugin_url('path')."/modules/$page.php";
	if (file_exists($module)) {
		
		require_once($module);
		wpcjChimp_show_module();
		
	} else {
		$wpcjChimp_error = 'Module does not exists ('.$module.').';
	}
	
	if ($wpcjChimp_error != '') {
		wpcjChimp_show_error();
	}
}

function wpcjChimp_load_backend() {
	add_action('register_form'	,'wpcjChimp_loginform');
	add_action('register_post'	,'wpcjChimp_validate_registration',10,3);
	add_action('user_register'	,'wpcjChimp_process_registration');
	
	if ( is_admin() ){
		add_action('admin_init'	,'wpcjChimp_declare_options' );
		add_action('admin_menu'	,'wpcjChimp_create_menu');		
		add_action('admin_print_scripts'	, 'wpcjChimp_option_page_scripts');
		add_action('admin_print_styles'		, 'wpcjChimp_option_page_styles');	
		add_filter( 'plugin_action_links'	, 'wpcjChimp_add_action_link', 10, 2 );
		
		if (substr($_REQUEST['page'],0,strlen(plugin_basename(dirname(__FILE__)))) == plugin_basename(dirname(__FILE__))) {
			// Ayuda contextual
			add_filter( 'contextual_help', 'wpcjChimp_load_contextual_help' ); 
		}
	}
}

function wpcjChimp_option_page() {
global $wpcjChimp_error;

	// Reading current settings
	$wpcjchimp 	= get_option('wpcjchimp');
	$apikey 	= $wpcjchimp['mcapi'];
	$listid 	= $wpcjchimp['listid'];
	$force 		= $wpcjchimp['force'];
	
	$force 		= 0;
	$dbfields	= $wpcjchimp['dbfields'];
	if ( is_array($dbfields) ) {
		if ( count($dbfields) == 0 ) {
			$force = 1;
		}
	} else {
		$force = 1;
	}
	
	if ( $wpcjchimp['double-optin'] == 1 ) {
		$wpcjchimp['welcome'] = 0;
	}
	
	// Fetching wpCJ news feed
	include_once(ABSPATH . WPINC . '/rss.php');		
	ob_start();
	wp_rss('http://www.wpcj.com/feed', 8); 		
	$wpcj_news = ob_get_clean();
	ob_end_flush();
	$wpcj_news = str_ireplace('<a ' ,'<a target="_wpcj"',$wpcj_news);
	$wpcj_news = str_ireplace('<li','<li class="rss"'	,$wpcj_news);

	// Creating the form fields.
	if (is_null($MCHandler)) {
		if ( !class_exists('MCAPI') ) {
			require_once(wpcjChimp_get_plugin_url('path').'/MCAPI.class.php');
		}
		$MCHandler 	= new MCAPI($apikey);		
	}
	if ( !$MCHandler->errorCode && $apikey != '' ) {
		$lists_array = $MCHandler->lists($apikey);
		$list = '<select name="wpcjchimp[listid]">';
		for ($i = 0; $i < count($lists_array); $i++) {
			$selected = '';
			if ($lists_array[$i]['id'] == $listid) {
				$selected = 'selected="selected"';
			}
			$list .= '<option '.$selected.' value="'.$lists_array[$i]['id'].'">'.$lists_array[$i]['name'].'</option>';
		}
		$list .= '</select>';
		
		if ($listid != '' ) {
			if ( $force == 1 ) {		// fetch from MailChimp only when needed.
				$groups_vars= $MCHandler->listInterestGroups($listid);
				$vars 		= $MCHandler->listMergeVars($listid);
				
				$dbfields			= array();
				$dbfields['groups'] = $groups_vars;
				$dbfields['vars']	= $vars;
				
				$wpcjchimp['dbfields']	= $dbfields;
				$wpcjchimp['force']		= 0;
			} else {
				$groups_vars= $dbfields['groups'];
				$vars 		= $dbfields['vars'];	
			}
			$mandatory 		= '<ul>';
			$optional 		= '<ul>';
			
			$mandatory_vars = array();
			$optional_vars 	= array();
			
			if ( is_array($vars) ) {
				for ($i = 0; $i < count($vars); $i++) {
					if ($vars[$i]['public'] == 1) {
						if ($vars[$i]['req'] == 1) {
							$mandatory_vars[] = $vars[$i];
							$mandatory .= '<li>'.$vars[$i]['name'].' ('.$vars[$i]['tag'].')</li>';
						} else {
							$optional_vars[] = $vars[$i];
							$optional 		.= '<li>'.$vars[$i]['name'].' ('.$vars[$i]['tag'].')</li>';
						}
					}
				}
			}
			
			$mandatory 	.= '</ul>';
			$optional	.= '</ul>';
			$mandatory	= "<div style=\"background:#eeeeee;\">$mandatory</div>";
			$optional	= "<div style=\"background:#eeeeee;\">$optional</div>";
			$groups		= '';
			
			if ( is_array($groups_vars) ) {
				switch ($groups_vars['form_field']) {
					case 'checkbox':
						$groups 		= '';
					break;
					case 'radio':
						$groups 		= '';
					break;
					case 'select':
						$groups 		= '<select onclick="return false;" readonly="readonly">';
					break;
				}
				for ($i = 0; $i < count($groups_vars['groups']); $i++) {
					switch ($groups_vars['form_field']) {
						case 'checkbox':
							$groups 		.= ''.$groups_vars['groups'][$i].'&nbsp;<input onclick="return false;" readonly="readonly" type="checkbox"/><br/>';
						break;
						case 'radio':
							$groups 		.= '<input onclick="return false;" readonly="readonly" type="radio" name="group-x"/>&nbsp;'.$groups_vars['groups'][$i].'<br/>';
						break;
						case 'select':
							$groups 		.= '<option>'.$groups_vars['groups'][$i].'</option>';
						break;
					}
				}
				switch ($groups_vars['form_field']) {
					case 'checkbox':
						$groups 		.= '';
					break;
					case 'radio':
						$groups 		.= '';
					break;
					case 'select':
						$groups 		.= '</select>';
					break;
				}				
			}
			$groups	= "<div style=\"background:#eeeeee;\"><form>$groups</form></div>";
			
			if ( $force == 1 ) {
				// Update Settings. Basically, update the MergeVars & InterestGroups field.
				update_option('wpcjchimp',$wpcjchimp);
				
				$force = 0;
			}
		}
	} elseif ( $MCHandler->errorCode ) {
		$wpcjChimp_error = 'MailChimp Error '.$MCHandler->errorCode.': '.$MCHandler->errorMessage;
		wpcjChimp_show_error();
	}
	
	// Displaying the Settings Form
	echo '
		<div class="wrap">
		<h2>wpCJ Chimp v'.VERSION.'</h2>
		<h3 style="display:inline;">Automagically add your new blog users to your <a href="http://eepurl.com/gfKx" title="MailChimp">MailChimp List</a></h3>
		<form method="post" action="options.php">';

	settings_fields( 'wpcjChimp_options' );

	echo '
		<div class="postbox-container" style="width:65%;">
			<div class="metabox-holder">
				<div class="meta-box-sortables">
				
					<div class="postbox">						
						<h3>
							<span>wpCJ Chimp Settings</span>
						</h3>											
						<table class="form-table">						
							<tr valign="top">
								<th scope="row">Your MailChimp API Key:</th>
								<td><input class="regular-text" type="text" name="wpcjchimp[mcapi]" value="'.$wpcjchimp['mcapi'].'" /></td>
							</tr>';
	
		if ( $apikey != '' && $list != '' ) {
			echo '	
							<tr valign="top">
								<th scope="row">MailChimp List:</th>
								<td>'.$list.'</td>
							</tr>';
		}
		
		echo '	
							<tr valign="top">
								<th scope="row">Double Opt-in:</th>
								<td>
									<select name="wpcjchimp[double-optin]">
										<option value="1" '.($wpcjchimp['double-optin']!=1?'selected="selected"':'').'>Yes, send the user a confirmation email.</option>
										<option value="0" '.($wpcjchimp['double-optin']==0?'selected="selected"':'').'>No, subscribe the user directly to my list.</option>
									</select>
								</td>
							</tr>
							
							<tr valign="top">
								<th scope="row">Send Welcome Email:<br /><br />
									<span style="color:#666666;font-style:italic">
										No email will be sent if <em>Double Opt-in</em> is set to YES.
									</span></th>
								<td>
									<select name="wpcjchimp[welcome]">
										<option value="1" '.($wpcjchimp['welcome']!=1?'selected="selected"':'').'>Yes, send it.</option>
										<option value="0" '.($wpcjchimp['welcome']==0?'selected="selected"':'').'>No, subscribe them silently.</option>
									</select>
								</td>
							</tr>
							
							<tr valign="top">
								<th scope="row">Update List Database<br /><br />
								<span style="color:#666666;font-style:italic">
									This option helps you reduce your MailChimp API usage. Use it when you make changes to your MailChimp database.
								</span></th>
								<td><input name="wpcjchimp[force]" type="checkbox" value="1" />&nbsp;Yes, force an update.</td>
							</tr>';
		if ($listid != '') {
			echo '
							<tr valign="top">
								<th scope="row">Mandatory Fields</th>
								<td>'.$mandatory.'</td>
							</tr>
							
							<tr valign="top">
								<th scope="row">Optional Fields</th>
								<td><input name="wpcjchimp[optional]" type="checkbox" value="1" '.($wpcjchimp['optional']==1?'checked="checked"':'').' />&nbsp;Ask for optional fields?<br />'.$optional.'</td>
							</tr>';
		}
		if ( is_array($groups_vars) ) {
			echo '
							<tr valign="top">
								<th scope="row">Interest Groups</th>
								<td><input name="wpcjchimp[interests]" type="checkbox" value="1" '.($wpcjchimp['interests']==1?'checked="checked"':'').' />&nbsp;Ask for Interest Groups?<br />'.$groups.'</td>
							</tr>';
		}
		echo '
							<tr valign="top">
								<th scope="row">Confirmation question:<br /><br />
								<span style="color:#666666;font-style:italic">
									Leave it blank if you don\'t want confirmation.
								</span></th>
								<td><input class="regular-text" name="wpcjchimp[question]" type="text" value="'.$wpcjchimp['question'].'"/><br />
								Default answer: <select name="wpcjchimp[answer]"><option value="1" '.($wpcjchimp['answer']!==0?'selected="selected"':'').'>Yes</option><option value="0" '.($wpcjchimp['answer']==0?'selected="selected"':'').'>no</option></select>
								</td>
							</tr>
							
							<tr valign="top">
								<th scope="row">Text to be shown to the visitors, above the MailChimp fields at the sign-up page:<br /><br />
								<span style="color:#666666;font-style:italic">
									Here you have the chance to include a brief introductory text to the user. It will be shown right before asking the actual MailChimp fields.
								</span></th>
								<td><textarea name="wpcjchimp[intro]" rows="7" cols="35">'.$wpcjchimp['intro'].'</textarea></td>
							</tr>';
		if ( $wpcjchimp['show-news']=='NO' ) {
			echo '
							<tr valign="top">
								<th scope="row">Show wpCJ.com news feed:</th>
								<td><input type="checkbox" name="wpcjchimp[show-news]" value="YES" /></td>
							</tr>';	
		}
		echo '
						</table>						
					</div>
						
					<p class="submit" style="clear:both;">
						<input type="submit" class="button-primary" value="'. __('Save Changes').'" />&nbsp;&nbsp;&nbsp;&nbsp;<a class="button-secondary" href="http://bit.ly/bhR9i0" title="Donate"><em>Feeling generous today?</em></a>
					</p>';
		if ( $wpcjchimp['show-news']!='NO' ) {
			echo '		
						<div id="wpcjchimp-news" class="postbox">
							<div class="handlediv" title="Click to toggle"><br /></div>
							<h3 class="hndle"><span>wpCJ.com News</span></h3>
							<div class="inside">
								<blockquote>
									'.$wpcj_news.'
									<p style="text-align:right;"><em>Don\'t show it again: <input type="checkbox" name="wpcjchimp[show-news]" value="NO" /></em></p>
								</blockquote>
								<p style="text-align:right;">
									<a href="http://wordpress.org/extend/plugins/wpcj-testimonials/" title="wpCJ Testimonials on WordPress.org">Have you tried our free wpCJ Testimonials plugin?</a>
								</p>
							</div>
						</div>';
		}
		echo '
				</div>
			</div>
		</div>
		<div class="postbox-container" style="width:25%;">
			<div class="metabox-holder">	
				<div class="meta-box-sortables">
					<div class="postbox">
						<div class="handlediv" title="Click to toggle"><br /></div>
						<h3 class="hndle">
							<span>Need help?</span>
						</h3>
						<div class="inside">
							Click on the "Help" link located in the top right corner of this form. <br/><br/><br/>
							<!-- // MAILCHIMP SUBSCRIBE CODE \\ -->
							<ul>
								<li class="email">
									<a href="http://eepurl.com/iSda" target="_blank">Sign up for our newsletter!</a>
								</li>
							</ul>
							<!-- \\ MAILCHIMP SUBSCRIBE CODE // -->
						</div>
					</div>';

	if ( $wpcjchimp['dbfields'] != '' ) {
		$wpcjChimp_html = wpcjChimp_generate_code();
		echo '
						<div class="postbox">
							<div class="handlediv" title="Click to toggle"><br /></div>
							<h3 class="hndle">
								<span>Partial Preview of the Sign-up Page</span>
							</h3>
							<div class="inside">
								<form action="#">'.$wpcjChimp_html.'</form>
							</div>
						</div>';
	}

	echo '
						<br/><br/><br/>
					</div>
				</div>
			</div>
		</form>
		</div>
	';	
}

function wpcjChimp_show_error() {
global $wpcjChimp_error;

	if ($wpcjChimp_error != '') {
		echo 
'<div id="message" class="error fade">
	<p>'.$wpcjChimp_error.'</p>
</div>
';
		$wpcjChimp_error = '';
	}

}

function wpcjChimp_generate_code() {
	$wpcjchimp 	= get_option('wpcjchimp');
	$intro 		= $wpcjchimp['intro'];
	$question	= $wpcjchimp['question'];
	$answer		= $wpcjchimp['answer'];
	$code 		= '';
	$ok 		= false;
	
	$dbfields	= $wpcjchimp['dbfields'];
	if ( is_array($dbfields) ) {
		if ( count($dbfields) != 0 ) {					
			$interests 	= $dbfields['groups'];
			$vars 		= $dbfields['vars'];
			
			$ok = true;
		}
	}
	
	if ( $ok ) {				
		if ( is_array($vars) ) {
			for ( $i = 0; $i < count($vars); $i++ ) {
				if ( $vars[$i]['tag'] != 'EMAIL' && $vars[$i]['public'] == 1 ) {
					if ( $vars[$i]['req'] == 1 || $wpcjchimp['optional'] == 1 ) {
						
						$code .= wpcjChimp_generate_field($vars[$i]['field_type'],'wpcjchimp-'.$vars[$i]['tag'],$vars[$i]['name'],$vars[$i]['choices']);
						
						switch ( $vars[$i]['field_type'] ) {
							case 'text':
							case 'number':
							case 'email':
							case 'date':
							case 'phone':
							case 'url':
							case 'imageurl':
								if ( $text_styles != '' ) {
									$text_styles .= ',';
								}
								$text_styles .= '#mce-wpcjchimp-'.$vars[$i]['tag'];
								break;
							case 'address':
								if ( $text_styles != '' ) {
									$text_styles .= ',';
								}
								$text_styles .= '#mce-wpcjchimp-'.$vars[$i]['tag'].'-addr1,';
								$text_styles .= '#mce-wpcjchimp-'.$vars[$i]['tag'].'-addr2,';
								$text_styles .= '#mce-wpcjchimp-'.$vars[$i]['tag'].'-city,';
								$text_styles .= '#mce-wpcjchimp-'.$vars[$i]['tag'].'-state,';
								$text_styles .= '#mce-wpcjchimp-'.$vars[$i]['tag'].'-zip';
								
								if ( $select_styles != '' ) {
									$select_styles .= ',';
								}
								$select_styles .= '#mce-wpcjchimp-'.$vars[$i]['tag'].'-country';
							break;
							case 'radio':
							case 'checkbox':
								if ( $checkbox_styles != '' ) {
									$checkbox_styles .= ',';
								}
								$checkbox_styles .= '#mce-wpcjchimp-'.$vars[$i]['tag'];															
							break;
							case 'dropdown':
								if ( $select_styles != '' ) {
									$select_styles .= ',';
								}
								$select_styles .= '#mce-wpcjchimp-'.$vars[$i]['tag'];
							break;
						}
					}
				}
			}
		}
		if ( $wpcjchimp['interests'] && is_array($interests) ) {
			$code .= '<label class="input-group-label">'.$interests['name'].'</label><br><div style="width:60%;"><p style="text-align:right;font-size:16px;">';
			for ( $i = 0; $i < count($interests['groups']); $i++ ) {
				switch ($interests['form_field']) {
					case 'checkbox':
						$code .= $interests['groups'][$i].'&nbsp;<input type="checkbox" name="wpcjchimp-groups[]" value="'.$interests['groups'][$i].'"><br/>';
					break;
					case 'radio':
						$code .= $interests['groups'][$i].'&nbsp;<input type="radio" name="wpcjchimp-groups" value="'.$interests['groups'][$i].'"><br/>';
					break;
					case 'select':
					break;
				}
			}
			$code .= '</p></div>';
		}
	}
	
	$styles = '
	<script language="javascript" type="text/javascript">
		/* <![CDATA[ */
		function toggleLayer( whichLayer )
		{
		  var elem, vis;
		  if( document.getElementById ) // this is the way the standards work
		    elem = document.getElementById( whichLayer );
		  else if( document.all ) // this is the way old msie versions work
		      elem = document.all[whichLayer];
		  else if( document.layers ) // this is the way nn4 works
		    elem = document.layers[whichLayer];
		  vis = elem.style;
		  // if the style.display value is blank we try to figure it out here
		  if(vis.display==\'\'&&elem.offsetWidth!=undefined&&elem.offsetHeight!=undefined)
		    vis.display = (elem.offsetWidth!=0&&elem.offsetHeight!=0)?\'block\':\'none\';
		  vis.display = (vis.display==\'\'||vis.display==\'block\')?\'none\':\'block\';
		}		
	/* ]]> */
	</script>
	<style>
		#wpcjchimp-intro {
			font-size:24px;
			width:97%;
			padding:3px;
			margin-top:2px;
			margin-right:6px;
			margin-bottom:16px;
			border:0px;
			text-align: center;
		}
		#wpcjchimp-question-label {
			font-size:16px;
			width:97%;
			padding:3px;
			margin-top:2px;
			margin-right:6px;
			margin-bottom:16px;
			border:0px;
		}
		.input-group-label {
			font-size:24px;
			width:97%;
			padding:3px;
			margin-top:2px;
			margin-right:6px;
			margin-bottom:16px;
			border:0px;
			text-align: center;
		}
		';
	if ($text_styles != '') {
		$styles .= $text_styles.' {
			font-size:24px;
			width:97%;
			padding:3px;
			margin-top:2px;
			margin-right:6px;
			margin-bottom:16px;
			border:1px solid #e5e5e5;
			background:#fbfbfb;
		}
		';
	}
	if ($checkbox_styles != '') {
		$styles .= $checkbox_styles.' {
			font-size:24px;
			padding:3px;
			margin-top:2px;
			margin-left:5px;
			margin-right:6px;
			margin-bottom:16px;
			border:0px;
			vertical-align:middle;
		}
		';
	}
	if ($radio_styles != '') {
		$styles .= $radio_styles.' {
			font-size:24px;
			padding:3px;
			margin-top:2px;
			margin-left:5px;
			margin-right:6px;
			margin-bottom:16px;
			border:0px;
			vertical-align:middle;
		}
		';
	}
	if ($select_styles != '') {
		$styles .= $select_styles.' {
			font-size:24px;
			width:97%;
			padding:3px;
			margin-top:2px;
			margin-right:6px;
			margin-bottom:16px;
			border:1px solid #e5e5e5;
			background:#fbfbfb;
		}
		';
	}
	$styles .= '</style>';
	
	if ($intro != '') {
		$intro = "<p id='wpcjchimp-intro'>$intro</p>";
	}
	if ($question != '') {
		$question = '<p id="wpcjchimp-question-label">'.$question.'&nbsp;<input type="checkbox" name="wpcjchimp-subscribe" value="1" id="wpcjchimp-question" '.($answer!=0?'checked="checked"':'').' onclick="toggleLayer(\'mailchimp-div\');" /></p>';
	} else {
		$question = '<input type="hidden" name="wpcjchimp-subscribe" value="1" id="wpcjchimp-question" />';
	}
	
	return $styles.$question."<div id='mailchimp-div' style='display:".($answer!=0?'block"':'none')."'>$intro$code</div>";
}

function wpcjChimp_generate_field($type,$name,$label,$choices,$class='') {
	$field = '';
	if ( $label != '' ) {
		$field .= '<label';
		
		if ( $name != '' ) {
			$field .= ' for="mce-'.$name.'"';
		}
		if ( $class != '' ) {
			$field .= ' class="'.$class.'"';
		}
		$field .= '>'.$label;		
		$field .= '</label>';
	}
	switch ( $type ) {
		case 'text':
		case 'email':
		case 'number':
		case 'date':
		case 'phone':
		case 'url':
		case 'imageurl':
			$value = '';
			if ( $_REQUEST[$name] != '' ) {
				$value = ' value="'.$_REQUEST[$name].'" ';
			}
			$field .= '<input type="text" name="'.$name.'" id="mce-'.$name.'" '.$value.'/>';
		break;
		case 'address':
			$field .='<br/>
				<blockquote>
					<label for="mce-'.$name.'-addr1">Street Address</label><input type="text" value="" maxlength="70" name="'.$name.'[addr1]" id="mce-'.$name.'-addr1" class="">
					<label for="mce-'.$name.'-addr2">Address Line 2</label><input type="text" value="" maxlength="70" name="'.$name.'[addr2]" id="mce-'.$name.'-addr2">
					<label for="mce-'.$name.'-city">City</label><input type="text" value="" maxlength="40" name="'.$name.'[city]" id="mce-'.$name.'-city" class="">
					<label for="mce-'.$name.'-state">State/Province/Region</label><input type="text" value="" maxlength="20" name="'.$name.'[state]" id="mce-'.$name.'-state" class="">
					<label for="mce-'.$name.'-zip">Postal / Zip Code</label><input type="text" value="" maxlength="10" name="'.$name.'[zip]" id="mce-'.$name.'-zip" class="">
					<label for="mce-'.$name.'-country">Country</label><select name="'.$name.'[country]" id="mce-'.$name.'-country" class=""><option selected="selected" value="164">USA</option><option value="2">Albania</option><option value="4">Andorra</option><option value="6">Argentina</option><option value="8">Australia</option><option value="9">Austria</option><option value="11">Bahamas</option><option value="13">Bangladesh</option><option value="14">Barbados</option><option value="15">Belarus</option><option value="16">Belgium</option><option value="19">Bermuda</option><option value="22">Bosnia and Herzegovina</option><option value="23">Botswana</option><option value="24">Brazil</option><option value="271">British West Indies</option><option value="25">Bulgaria</option><option value="30">Canada</option><option value="32">Cayman Islands</option><option value="35">Chile</option><option value="36">China</option><option value="37">Colombia</option><option value="268">Costa Rica</option><option value="40">Croatia</option><option value="41">Cyprus</option><option value="42">Czech Republic</option><option value="43">Denmark</option><option value="187">Dominican Republic</option><option value="45">Ecuador</option><option value="46">Egypt</option><option value="47">El Salvador</option><option value="50">Estonia</option><option value="191">Faroe Islands</option><option value="52">Fiji</option><option value="53">Finland</option><option value="54">France</option><option value="59">Germany</option><option value="60">Ghana</option><option value="194">Gibraltar</option><option value="61">Greece</option><option value="195">Greenland</option><option value="192">Grenada</option><option value="198">Guatemala</option><option value="270">Guernsey</option><option value="66">Honduras</option><option value="67">Hong Kong</option><option value="68">Hungary</option><option value="69">Iceland</option><option value="70">India</option><option value="71">Indonesia</option><option value="74">Ireland</option><option value="75">Israel</option><option value="76">Italy</option><option value="202">Jamaica</option><option value="78">Japan</option><option value="81">Kenya</option><option value="269">Kuwait</option><option value="82">Kuwait</option><option value="85">Latvia</option><option value="86">Lebanon</option><option value="90">Liechtenstein</option><option value="91">Lithuania</option><option value="92">Luxembourg</option><option value="93">Macedonia</option><option value="96">Malaysia</option><option value="97">Maldives</option><option value="99">Malta</option><option value="212">Mauritius</option><option value="101">Mexico</option><option value="102">Moldova, Republic of</option><option value="103">Monaco</option><option value="105">Morocco</option><option value="109">Netherlands</option><option value="110">Netherlands Antilles</option><option value="111">New Zealand</option><option value="112">Nicaragua</option><option value="116">Norway</option><option value="118">Pakistan</option><option value="119">Panama</option><option value="219">Papua New Guinea</option><option value="121">Peru</option><option value="122">Philippines</option><option value="123">Poland</option><option value="124">Portugal</option><option value="128">Romania</option><option value="254">Russia</option><option value="129">Russian Federation</option><option value="205">Saint Kitts and Nevis</option><option value="206">Saint Lucia</option><option value="227">San Marino</option><option value="133">Saudi Arabia</option><option value="256">Scotland</option><option value="266">Serbia</option><option value="137">Singapore</option><option value="138">Slovakia</option><option value="139">Slovenia</option><option value="141">South Africa</option><option value="143">Spain</option><option value="148">Sweden</option><option value="149">Switzerland</option><option value="152">Taiwan</option><option value="154">Thailand</option><option value="267">Tobago</option><option value="261">Trinidad</option><option value="157">Turkey</option><option value="161">Ukraine</option><option value="162">United Arab Emirates</option><option value="262">United Kingdom</option><option value="163">Uruguay</option><option value="166">Vatican City State (Holy See)</option><option value="167">Venezuela</option><option value="168">Vietnam</option><option value="265">Wales</option><option value="174">Zimbabwe</option></select>
				</blockquote>
			';
		break;
		case 'radio':		
			$options = '';
			for ($i = 0; $i < count($choices); $i++) {
				$selected = '';
				if ( $_REQUEST[$name] == $choices[$i] ) {
					$selected = ' selected="selected" ';
				}
				$options .= '<input type="radio" name="'.$name.'" id="mce-'.$name.'-'.$i.'" value="'.$choices[$i].'" '.$selected.'/>&nbsp;'.$choices[$i].'<br/>';
			}
			
			$field .='<br/>
				<blockquote>
				'.$options.'
				</blockquote><br/>
			';
		break;
		case 'dropdown':
			$options = '';
			for ($i = 0; $i < count($choices); $i++) {
				$selected = '';
				if ( $_REQUEST[$name] == $choices[$i] ) {
					$selected = ' selected="selected" ';
				}
				$options .= '<option id="mce-'.$name.'-'.$i.'" value="'.$choices[$i].'" '.$selected.'/>'.$choices[$i].'<br/>';
			}
			
			$field .='<br/>
				<blockquote>
					<select name="'.$name.'">
					'.$options.'
					</select>
				</blockquote><br/>
			';
		break;
	}
	
	return "<p>$field</p>";
}

function wpcjChimp_option_page_styles() {
	if (isset($_GET['page']) && substr($_REQUEST['page'],0,strlen(plugin_basename(dirname(__FILE__)))) == plugin_basename(dirname(__FILE__))) {
		wp_enqueue_style('dashboard');
		wp_enqueue_style('thickbox');
		wp_enqueue_style('global');
		wp_enqueue_style('wp-admin');
		wp_enqueue_style('blogicons-admin-css', WP_CONTENT_URL . '/plugins/' . plugin_basename(dirname(__FILE__)). '/wpcj_plugin_tools.css');
	}
}

function wpcjChimp_option_page_scripts() {
	if (isset($_GET['page']) && substr($_REQUEST['page'],0,strlen(plugin_basename(dirname(__FILE__)))) == plugin_basename(dirname(__FILE__))) {
		wp_enqueue_script('postbox');
		wp_enqueue_script('dashboard');
		wp_enqueue_script('thickbox');
		wp_enqueue_script('media-upload');
	}
}

function wpcjChimp_add_action_link( $links, $file ) {
	
	if ( $file == plugin_basename(dirname(__FILE__)).'/index.php' ) {
		
		$settings_link = '<a href="options-general.php?page='.plugin_basename(dirname(__FILE__)).'/index.php">' . __('Settings') . '</a>';
		array_unshift( $links, $settings_link );
	}
	return $links;
}

wpcjChimp_load_backend();

?>
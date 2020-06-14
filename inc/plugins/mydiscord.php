<?php
/**
MyDiscord (Adds a Discord invite link along with server stats to the board index page.)
By KuJoe (KuJoe.net)

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
**/

// Don't allow direct initialization.
if(! defined('IN_MYBB')) {
	die('Nope.');
}

$plugins->add_hook("global_intermediate", "MyDiscordNotification");

function MyDiscord_info() {
	return array(
		"name"			=> "MyDiscord",
		"description"	=> 'Adds a Discord invite link along with server stats to the board index page.',
		"website"		=> "https://kujoe.net",
		"author"		=> "KuJoe",
		"authorsite"	=> "https://kujoe.net",
		"version"		=> "1.2",
		'compatibility'	=> '18*',
		'codename'		=> 'mydiscord'
	);
}

function mydiscord_install() {
	global $mybb, $db, $cache;
	require_once MYBB_ROOT . "inc/adminfunctions_templates.php";

	$mydiscord_group = array(
		'name'			=> 'MyDiscord',
		'title'			=> 'MyDiscord',
		'description'	=> 'MyDiscord Settings.',
		'disporder'		=> '99',
		'isdefault'		=> '0'
	);

	$db->insert_query('settinggroups', $mydiscord_group);
	$gid = $db->insert_id();
	
	$mydiscord_setting_1 = array(
		'name'			=> 'mydiscord_onoff',
		'title'			=> 'MyDiscord On/Off',
		'description'  	=> 'Is MyDiscord enabled or disabled?',
		'optionscode'  	=> 'onoff',
		'value'      	=> '0',
		'disporder'   	=> 1,
		'gid'			=> intval($gid)
	);
	
	$mydiscord_setting_2 = array(
		'name'			=> 'mydiscord_id',
		'title'			=> 'Discord ID',
		'description'  	=> 'Enter your Discord ID (Server Settings -> Widget -> Server ID)',
		'optionscode'  	=> 'text',
		'value'      	=> '0',
		'disporder'   	=> 2,
		'gid'			=> intval($gid)
	);

	$db->insert_query('settings', $mydiscord_setting_1);
	$db->insert_query('settings', $mydiscord_setting_2);
	rebuild_settings();
}

function mydiscord_is_installed() {
    global $db;
    $query = $db->simple_select("settings", "*", "name='mydiscord_onoff'");
	$count = $db->num_rows($query);
	if($count > 0) {
        return true;
    } else {
		return false;
	}
}

function mydiscord_uninstall() {
	global $mybb, $db;
	
	mydiscord_deactivate();
	$db->delete_query('settinggroups', "name='MyDiscord'");
	$db->delete_query('settings', "name='mydiscord_onoff'");
	$db->delete_query('settings', "name='mydiscord_id'");
	$db->query("DROP TABLE ". $db->table_prefix ."mydiscord");
	rebuild_settings();
}

function mydiscord_activate() {
	global $mybb, $db;
	require_once MYBB_ROOT.'/inc/adminfunctions_templates.php';
	require_once MYBB_ADMIN_DIR."inc/functions_themes.php";
	
	mydiscord_deactivate();
	
	$stylesheet = @file_get_contents(MYBB_ROOT.'inc/plugins/mydiscord/mydiscord.css');
	$mydiscord_stylesheet = array(
			'name' => 'mydiscord.css',
			'tid' => 1,
			'attachedto' => '',
			'stylesheet' => $db->escape_string($stylesheet),
			'cachefile' => 'mydiscord.css',
			'lastmodified' => TIME_NOW,
		);
	$sid = $db->insert_query("themestylesheets", $mydiscord_stylesheet);
	$db->update_query("themestylesheets", array("cachefile" => "css.php?stylesheet=".$sid), "sid = '".$sid."'", 1);
	$tids = $db->simple_select("themes", "tid");
	while($theme = $db->fetch_array($tids)) {
		update_theme_stylesheet_list($theme['tid']);
	}

	$title = "mydiscordnotification";
	$template = '<div class="mydiscordnotification">{$discordicon}<strong>{$discordname}{$discordonline}{$discordtotal}{$discordinvite}</strong></div>';
	$notification = array(
		"title" => $db->escape_string($title),
		"template" => $db->escape_string($template),
		"sid" => "-1",
		"version" => "1800",
		"dateline" => TIME_NOW
	);
	$db->insert_query("templates", $notification);
	find_replace_templatesets("index", '#'.preg_quote('{$header}').'#', '{$header}{$mydiscordnotification}');
	
	$db->query("CREATE TABLE IF NOT EXISTS ". $db->table_prefix ."mydiscord (
		`discordid` BIGINT DEFAULT NULL ,
		`discordname` TEXT DEFAULT NULL ,
		`discordinvite` TEXT DEFAULT NULL ,
		`online` TEXT DEFAULT NULL ,
		`lastchkd` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP ,
		UNIQUE KEY (`discordid`)
		) ENGINE=MyISAM{$collation};"
	);

}

function mydiscord_deactivate() {
	global $mybb, $db;
	require_once MYBB_ROOT.'/inc/adminfunctions_templates.php';
	require_once MYBB_ADMIN_DIR."inc/functions_themes.php";
	
	$query = $db->simple_select("themes", "tid");
	while($tid = $db->fetch_field($query, "tid")) {
		$css_file = MYBB_ROOT."cache/themes/theme{$tid}/mydiscord.css";
		if(file_exists($css_file)) {
			unlink($css_file);
		}
	}

	$db->query("UPDATE `" . $db->table_prefix . "settings` SET `value` = '0' WHERE name='mydiscord_onoff'");
	$db->delete_query('templates', "title='mydiscordnotification'");
	$db->delete_query('themestylesheets', "name='mydiscord.css'");
	find_replace_templatesets("index", '#'.preg_quote('{$mydiscordnotification}').'#', '');
	update_theme_stylesheet_list("1");
}

function getDiscord($url) {
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_URL, $url);
	$data = curl_exec($ch);
	curl_close($ch);
	return $data;
}

function MyDiscordNotification() {
	global $db, $mybb, $templates, $mydiscordnotification;
	if($mybb->settings['mydiscord_onoff'] == 1) {
		$guildid = intval($mybb->settings['mydiscord_id']);
		$query = $db->simple_select("mydiscord", "*", "discordid='$guildid'");
		$count = $db->num_rows($query);
		if($count == 0) {
			$create = array(
				"discordid" => $db->escape_string($guildid),
			);
			$db->insert_query("mydiscord", $create);
		}
		$get = $db->fetch_array($db->simple_select("mydiscord", "*", "discordid='$guildid'"));
		if(strtotime($get['lastchkd']) < strtotime("-5 minutes")) {
			$widget_decode = json_decode(getDiscord('https://discordapp.com/api/guilds/'.$guildid.'/widget.json'), true);
			if(!$widget_decode['id']) {
				$discordname = "Did you enter the correct Server ID? If so, check to make sure you enabled the widget in your Server Settings.";
				$discordonline = ' ('.$widget_decode['code'].')';
				$discordinvite = '';
			} else {
				$discordname = $widget_decode['name'];
				$online = count($widget_decode['members']);
				$discordonline = ' | Currently Online: '.$online;
				$invitelink = $widget_decode['instant_invite'];
				$discordinvite = ' | <a href="'.$invitelink.'" target="_blank" />JOIN OUR DISCORD!</a>';
				$db->query("UPDATE `" . $db->table_prefix . "mydiscord` SET `discordname` = '$discordname',`online` = '$online',`discordinvite` = '$invitelink',`lastchkd` = NOW() WHERE discordid='$guildid'");
			}
		} else {
			$discordname = $get['name'];
			$discordonline = ' | Currently Online: '.$get['online'];
			$discordinvite = ' | <a href="'.$get['invitelink'].'" target="_blank" />JOIN OUR DISCORD!</a>';
		}
		eval("\$mydiscordnotification = \"".$templates->get('mydiscordnotification')."\";");
	}
}

?>
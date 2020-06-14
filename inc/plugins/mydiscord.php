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
		"version"		=> "2.0",
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
	
	$mydiscord_setting_3 = array(
		'name'			=> 'mydiscord_invite',
		'title'			=> 'Discord Invite Code',
		'description'  	=> 'Enter the 6 character Discord Invite Code you would like to display',
		'optionscode'  	=> 'text',
		'value'      	=> '0',
		'disporder'   	=> 3,
		'gid'			=> intval($gid)
	);
	
	$mydiscord_setting_4 = array(
		'name'			=> 'mydiscord_token',
		'title'			=> 'Discord Bot Token',
		'description'  	=> 'Enter your Discord Bot Token (https://discordapp.com/developers)',
		'optionscode'  	=> 'text',
		'value'      	=> '0',
		'disporder'   	=> 4,
		'gid'			=> intval($gid)
	);

	$db->insert_query('settings', $mydiscord_setting_1);
	$db->insert_query('settings', $mydiscord_setting_2);
	$db->insert_query('settings', $mydiscord_setting_3);
	$db->insert_query('settings', $mydiscord_setting_4);
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
	$db->delete_query('settings', "name='mydiscord_invite'");
	$db->delete_query('settings', "name='mydiscord_token'");
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
	$template = '<div class="mydiscordnotification">{$discordicon} <strong>{$discordname}{$discordonline}{$discordtotal}{$discordinvite}</strong></div>';
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
		`discordicon` TEXT DEFAULT NULL ,
		`members_total` TEXT DEFAULT NULL ,
		`members_online` TEXT DEFAULT NULL ,
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

function getDiscord($url,$token) {
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array(
		'Authorization: Bot '.$token.''
	));
	$data = curl_exec($ch);
	curl_close($ch);
	return $data;
}

function MyDiscordNotification() {
	global $db, $mybb, $templates, $mydiscordnotification;
	if($mybb->settings['mydiscord_onoff'] == 1) {
		$guildid = intval($mybb->settings['mydiscord_id']);
		$token = $mybb->settings['mydiscord_token'];
		$query = $db->simple_select("mydiscord", "*", "discordid='$guildid'");
		$count = $db->num_rows($query);
		if($count == 0) {
			$settime = date('Y-m-d H:i:s', strtotime("-6 minutes"));
			$create = array(
				"discordid" => $db->escape_string($guildid),
				"lastchkd" => $db->escape_string($settime),
			);
			$db->insert_query("mydiscord", $create);
		}
		$get = $db->fetch_array($db->simple_select("mydiscord", "*", "discordid='$guildid'"));
		if(strtotime($get['lastchkd']) < strtotime("-5 minutes")) {
			$guild_decode = json_decode(getDiscord('https://discordapp.com/api/guilds/'.$guildid.'?with_counts=true',$token), true);
			if($guild_decode['id']) {
				$discordicon = '<img src="https://cdn.discordapp.com/icons/'.$guildid.'/'.$guild_decode['icon'].'.png?size=32" />';
				$discordname = $guild_decode['name'];
				$members_online = $guild_decode['approximate_presence_count'];
				$members_total = $guild_decode['approximate_member_count'];
				$discordtotal = ' | Total Users: '.$members_total;
				$discordonline = ' | Currently Online: '.$members_online;
				$discordinvite = ' | <a href="https://discord.gg/'.$mybb->settings['mydiscord_invite'].'" target="_blank" />JOIN OUR DISCORD!</a>';
				$db->query("UPDATE `" . $db->table_prefix . "mydiscord` SET `discordname` = '$discordname',`discordicon` = '$discordicon',`members_online` = '$members_online',`members_total` = '$members_total',`lastchkd` = NOW() WHERE discordid='$guildid'");
			} else {
				$discordicon = '<img src="https://discordapp.com/assets/28174a34e77bb5e5310ced9f95cb480b.png" alt="Discord" />';
				$discordname = "Did you enter the correct ID and Token?";
				$discordtotal = '';
				$discordonline = '';
				$discordinvite = ' | <a href="https://discord.gg/'.$mybb->settings['mydiscord_invite'].'" target="_blank" />JOIN OUR DISCORD!</a>';
			}
		} else {
			$discordicon = $get['discordicon'];
			$discordname = $get['discordname'];
			$discordtotal = ' | Total Users: '.$get['members_total'];
			$discordonline = ' | Currently Online: '.$get['members_online'];
			$discordinvite = ' | <a href="https://discord.gg/'.$mybb->settings['mydiscord_invite'].'" target="_blank" />JOIN OUR DISCORD!</a>';
		}
		eval("\$mydiscordnotification = \"".$templates->get('mydiscordnotification')."\";");
	}
}

?>
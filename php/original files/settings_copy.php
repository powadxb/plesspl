<?php
$settings = [];
$settings_response = $DB->query(" SELECT * FROM settings ");
if (!empty($settings_response)) {
	foreach ($settings_response as $setting) {
		$settings[$setting['setting_key']] = $setting['setting_value'];
	}
}
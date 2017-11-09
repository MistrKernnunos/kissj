<?php
$settings = [
	'settings' => [
		'debug' => false, // disable Whoops debugger
		'displayErrorDetails' => false, // false in production
		'addContentLengthHeader' => false, // allow the web server to send the content-length header
		
		// Renderer settings
		'renderer' => [
			'template_path' => __DIR__.'/../templates/',
		],
		
		// Monolog settings
		'logger' => [
			'name' => 'slim-app',
			'path' => __DIR__.'/../logs/app.log',
			'level' => \Monolog\Logger::DEBUG,
		],
		
		// PHPmailer settings
		'mailer' => [
			'smtp' => true,
			'smtp_server' => 'localhost',
			'smtp_auth' => true,    // SMTP authentication
			'smtp_port' => 587,
			'smtp_username' => '',
			'smtp_password' => '',
			
			'from_mail' => 'registration@localhost.cz', // registration mail
			'from_name' => 'Registrace Localhost',
			
			'bcc_mail' => 'registration@localhost.cz', // another mail
			'bcc_name' => 'Registrační Asistentka',
			
			'disable_tls' => false, // turn off all certificate checks
		],
		
		'db' => [
			'path' => __DIR__.'/../db.sqlite'
		],
		
		'eventName' => 'cej2018',
	],
];
if (file_exists(__DIR__.'/settings_custom.php')) {
	$settings = array_replace_recursive($settings, require('settings_custom.php'));
}

return $settings;
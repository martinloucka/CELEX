<?php

define('ROOT_DIR', __DIR__.'/../');
define('ADMIN_EMAIL', 'loucka.martin@gmail.com');

require __DIR__ . '/../vendor/autoload.php';

$configurator = new Nette\Configurator;

$configurator->setTempDirectory(__DIR__ . '/../temp');

$configurator->createRobotLoader()
	->addDirectory(__DIR__)
	->addDirectory(__DIR__ . '/../vendor/others')
	->register();

$configurator->addConfig(__DIR__ . '/config/config.neon');

if($configurator->detectDebugMode()) {
	$configurator->enableDebugger(__DIR__ . '/../log');
	$configurator->addConfig(__DIR__ . '/config/config.local.neon');
} else {
	$configurator->enableDebugger(__DIR__ . '/../log', ADMIN_EMAIL);
}

$container = $configurator->createContainer();

return $container;

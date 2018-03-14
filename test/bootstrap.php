<?php
echo "\n\tPHP Simple Protobuf Tes Suite\n";
echo "-------------------------------------------------\n";
$out_dir	= __DIR__ . '/out';
$result_dir	= __DIR__ . '/result';
$composer	= __DIR__ . '/../vendor/autoload.php';
$test_autoloader = __DIR__ . '/out/autoload.php';

if(!is_dir($out_dir) && !mkdir($out_dir))
	throw new Exception("Cannot creating directory $out_dir" );

if(!is_dir($result_dir) && !mkdir($result_dir))
	throw new Exception("Cannot creating directory $result_dir");

if( !file_exists($composer) )
	throw new Exception("composer autoload not found, please run `composer install & composer dumpautoload`");

/*if(file_exists(__DIR__ . '/out/autoload.php'))
	return;*/

// Generate autoloader for tests
$composer = realpath($composer);
$autoloader = <<<end
<?php
include __DIR__ . '/../../vendor/autoload.php';
function thisDirAutoloader(\$class)
{
	\$file = __DIR__ . DIRECTORY_SEPARATOR . strtr(\$class, '\\\\', DIRECTORY_SEPARATOR) . '.php' ;
	if(file_exists(\$file))
		include_once \$file;

}
spl_autoload_register('thisDirAutoloader');
end;

file_put_contents( $test_autoloader, $autoloader );
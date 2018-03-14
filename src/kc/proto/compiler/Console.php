<?php

namespace kc\proto\compiler;
/**
* 
*/
final class Console
{
	public static $file, $out, $package;
	
	public static function start()
	{

		$opt	= getopt('O::P::F:D::', ['out::', 'package::', 'file:', 'dump::']);
		$file	= isset($opt['F']) ? $opt['F'] : ( isset($opt['file']) ? $opt['file'] : null );
		$out	= isset($opt['O']) ? $opt['O'] : ( isset($opt['out']) ? $opt['out'] : null );
		$dump	= isset($opt['D']) ? $opt['D'] : ( isset($opt['dump']) ? $opt['dump'] : false );

		if( empty($file) )
			self::error("No input file");

		//Check if not absolute path
		if( !self::isAbsolute($file) )
			$file	= getcwd() . DIRECTORY_SEPARATOR . trim( $file, '/\\' );

		self::$file = realpath($file);

		if(!self::$file || !is_file(self::$file))
		{
			self::error("File $file not exists");
		}

		if( is_null($out) )
			$out = getcwd();
		elseif( !self::isAbsolute($out) )
			$out = getcwd() . DIRECTORY_SEPARATOR . trim( $out, '/\\' );
		
		self::$out = realpath($out);

		if(!self::$out)
		{
			self::error("Out dir $dir not exists");
		}

		$filename	= basename(self::$file);

		self::info("Compiling: " . self::$file);
		self::info("OutputDir: " . self::$out);

		
		$parser = new Parser(self::$file);

		$result	= $parser->execute();
		if( $dump ){
			self::info("Dumping json parsed proto file");
			file_put_contents(
						substr($filename, 0, strrpos($filename, '.')) . '.json',
						json_encode($result, JSON_PRETTY_PRINT )
					);
		}


		$builder = new Builder( self::$out );

		$builder->execute( $result );
		Console::info("OK $filename");
	}

	private static function isAbsolute($path) {
		return $path[0] == DIRECTORY_SEPARATOR || strpos($path, ':') !== false;
	}


	public static function error($msg):Void
	{
		echo "ERROR: $msg\n";
		exit;
	}

	public static function warning($msg):Void
	{
		echo "WARNING: $msg\n";
	}

	public static function info($msg):Void
	{
		echo "$msg\n";
	}
}
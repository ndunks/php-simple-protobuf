<?php

namespace kc\proto\serializer;
use kc\proto\serializer\NativeArray;
use \kc\proto\Message;
use \Exception;

/**
* 
*/
class Json implements Serializer
{
	public static
			$JSON_OPTION = JSON_PRETTY_PRINT,
			$JSON_DEPTH = 2048;
	
	public static function export(Message $message)
	{
		$array	= NativeArray::export( $message );
		//Unknown field may threat as non ASCII
		self::maping($array, 'utf8_encode');

		$json	= json_encode($array, self::$JSON_OPTION, self::$JSON_DEPTH);

		if(json_last_error() != JSON_ERROR_NONE)
			throw new Exception( json_last_error_msg() );
		else return $json;
	}

	private static function maping(Array &$array, String $function = 'utf8_encode'){
		
		if(count($array) == 0) return;

		foreach ($array as &$val)
		{
			if( is_string($val) )
				$val = $function($val);

			if( is_array($val) )
				self::maping($val, $function);
		}
	}

	public static function import($data, Message $message)
	{
		$array	= json_decode($data, true);
		if(json_last_error() != JSON_ERROR_NONE)
			throw new Exception( json_last_error_msg() );

		self::maping($array, 'utf8_decode');
		
		NativeArray::import( $array, $message );
	}


	

}
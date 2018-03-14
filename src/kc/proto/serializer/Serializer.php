<?php
namespace kc\proto\serializer;
use \kc\proto\Message;

/**
* Serializer must implement this interface
*/
interface Serializer {
	public static function export(Message $message);
	public static function import($data, Message $message);
}
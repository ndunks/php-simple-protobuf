<?php
namespace kc\proto\compiler;
/**
* proto to PHP class builder
*/
class Builder
{
	//var $package, $version, $imports = [], $block = [];
	var $dir = '', $result = [], $root_namespace = '';
	public static $class_type = [];

	function __construct($dir)
	{

		$this->dir = rtrim($dir, '/\\');// . DIRECTORY_SEPARATOR;
	}

	function getRelativeDir($namespace, $file = '')
	{
		$dir = $this->dir;
		if(!empty($namespace))
		{
			$dir .= DIRECTORY_SEPARATOR . trim(strtr($namespace, '\\', DIRECTORY_SEPARATOR), DIRECTORY_SEPARATOR);
		}
		if(!is_dir($dir) && !mkdir($dir, 0775, true))
			Process::error("Cannot creating directory: {$dir}");

		return $dir . DIRECTORY_SEPARATOR . $file;
	}

	function getNamespace($sub_namespace)
	{
		return trim($this->root_namespace . '\\' . $sub_namespace, '\\');
	}

	function execute($proto = [])
	{
		extract($proto);
		//convert package to namespace
		$this->root_namespace = strtr($package, '.', '\\');

		foreach ($block as &$block_item)
		{
			self::registerClass($block_item, $this->root_namespace);
		}

		foreach ($block as &$block_item) {

			$this->processBlock($block_item, $this->root_namespace);
		}
	}

	static function registerClass(&$block, $namespace = '')
	{
		$my_namespace = (empty($namespace) ? '' : $namespace . '\\') . $block['name'];

		$block['namespace']	= $namespace;
		$block['my_namespace']	= $my_namespace;

		self::$class_type[$my_namespace] = $block['type'];

		foreach ($block['child'] as &$child){
			self::registerClass($child, $my_namespace);
		}

	}

	function processBlock($block, $namespace = '')
	{

		$namespace	= $block['namespace'];
		$my_namespace	= $block['my_namespace'];
		$file	= $this->getRelativeDir($namespace, $block['name'] . '.php');
		
		//Process sub-block/child before processing field
		foreach ($block['child'] as $key => $value)
		{
			$this->processBlock($value, $my_namespace);

			//special for group, we need to add its field manually
			if( $value['type'] == 'group')
			{
				$block['field'][] = [
					'block_type'=> 'field',
                    'rule'		=> $value['rule'],
                    'type'		=> $value['name'],
                    'name'		=> strtolower( $value['name'] ),
                    'number'	=> $value['number']
				];
			}
		}

		if($block['type'] != 'enum') {
			// Resolve type_name and class type
			foreach ( $block['field'] as &$field )
			{
				if(! is_array($field) ){
					var_dump( $block['field'] );
					Console::error("Not array found");
				}
				$field['Name']		= ucwords($field['name']);
				$field['namespace'] = $my_namespace;
				$type	= $field['type'];

				if($type[0] >= 'A' && $type[0] <= 'Z')
				{ // is class name
					//First searching on relative class
					$type = strtr($type, '.', '\\');
					$type_name = $my_namespace . '\\' . $type;
					//Console::info("Checking $type_name");
					if(!isset(self::$class_type[$type_name]))
					{// Not Found relative class, try on parent namespace
						$type_name = $namespace . '\\' . $type;
						if(!isset(self::$class_type[$type_name]))
						{// Still not found, try on root namespace
							$type_name = $this->getNamespace($type);
							if(!isset(self::$class_type[$type_name])) {
								Console::error("Class not found $type");
							}
						}
					}
				}else{ // protobuf primitive type
					$type_name = $this->getTypeName($type);
				}

				$field['php_type']	= $type_name;
				
				$field['real_type']	= self::getClassType($type_name, $field['type']);

				$field['type_name'] = $field['real_type'] == 'enum' ? 'int' : $type_name;
				$field['is_class'] = strpos($field['type_name'], '\\') !== false || $field['type_name'][0] >= 'A' && $field['type_name'][0] <= 'Z';
			}
		}

		ob_start();
		$this->render($block['type'], $block);
		$body = ob_get_clean();

		Console::info("Writing: $file");
		
		file_put_contents($file, "<?php\r\n\r\n$body\r\n");

	}

	function render($_tpl_name, $value)
	{
		//group == message
		if($_tpl_name == 'group')
			$_tpl_name = 'message';
		extract($value);
		$_tpl_file = __DIR__ . DIRECTORY_SEPARATOR . 'template' . DIRECTORY_SEPARATOR . $_tpl_name . ".phtml";
		if( !is_file($_tpl_file) )
			Console::error("Not renderer found for $_tpl_name");
		include $_tpl_file;
	}

	public function getTypeName($type)
	{

		switch ($type) {
			case 'double':
			case 'float':
				return 'float';
				break;
			case 'int':
			case 'int64':
			case 'int32':
			case 'uint32':
			case 'uint64':
			case 'sint32':
			case 'sint64':
			case 'fixed32':
			case 'fixed64':
			case 'sfixed32':
			case 'sfixed64':
				return 'int';
				break;

			case 'bool':
				return 'boolean';
				break;

			case 'string':
			case 'bytes':
				return 'string';
				break;

			default:
				return false;
				break;
		}
	}

	public static function getClassType($class, $fallback = 'primitive')
	{
		return isset(self::$class_type[$class]) ? self::$class_type[$class] : $fallback;
	}

}
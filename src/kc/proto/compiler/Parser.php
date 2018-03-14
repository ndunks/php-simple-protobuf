<?php
namespace kc\proto\compiler;
/**
* Proto file parser
*/
class Parser
{
	var $file, $handle, $line, $comment, $line_pos = 0;

	function __construct($file)
	{
		$this->file = $file;
	}

	/**
	 * Parsing proto block and clean comment
	 */
	function execute()
	{
		$this->handle = fopen($this->file, 'r');
		$this->has_group = false;
		$this->has_enum	 = false;
		
		$package	= '';
		$version	= 0;
		$imports	= [];
		$block		= [];
		$options	= [];
		$unknowns	= [];
		// Main runtime
		while ( $this->read() )
		{
			if( $this->isStartBlock() )
			{
				$this->readBlock($block);

			}elseif( $this->isOption() )
			{
				$this->readOption($option);

			}elseif( $this->startWith('package ') )
			{
				// strlen('package ')
				$package = trim(substr($this->line, 8), "\t ;");
			}elseif( $this->startWith('syntax '))
			{
				if($this->contain('proto2'))
				{
					$version = 2;
				}elseif($this->contain('proto3'))
				{
					$version = 3;
				}
			}elseif( $this->startWith('import ') ){
				if(preg_match('/^import\s+(?P<access>weak|public)?\s*"(?P<proto>[\w\-\_\.]+)"\s*;$/', $this->line, $match))
				{
					$imports[] = ['access'	=> $match['access'],
								'proto'	=> $match['proto' ] ];
				}
			}else
			{
				$unknowns[] = $this->line;
			}
		}

		fclose($this->handle);
		if( !empty($unknowns) )
		{
			Console::warning("Unparsed line: \r\n\t" . implode("\r\n\t", $unknowns));
		}
		$result	= compact('package', 'version', 'option', 'imports', 'block');
		//print_r($property);
		//echo json_encode($result, JSON_PRETTY_PRINT);
		return $result;
	}


	function readBlock(&$block)
	{
		$new	= [];
		$block[]	= &$new;

		if($this->isGroup())
		{
			$new['block_type']	= 'group';
			list($new['rule'], $new['type'], $new['name'], $new['number'])	= preg_split('/[\s=]+/', $this->line);
			$this->has_group = true;
		}elseif(preg_match('/^(\w+)\s+(\w+)\s*{\s*$/', $this->line, $match))
		{
			$new['block_type']	= 'class';
			$new['type']		= $match[1];
			$new['name']		= $match[2];
		}else{
			Console::error("Invalid open block at line {$this->line_pos}: '$this->line'");
		}
		$new['option']	= [];
		$new['child']	= [];
		$new['field']	= [];
		if($new['type'] == 'enum')
		{
			$this->readEnumBlock($new['field'], $new['option']);
		}else{
			$this->readChildBlock($new['field'], $new['option'], $new['child']);
		}

	}

	function readChildBlock(&$field, &$option, &$child)
	{
		while ( $this->read() )
		{
			if( $this->isEndBlock() )
			{
				break;
			}elseif( $this->isStartBlock() )
			{
				$this->readBlock($child);
			}elseif( $this->isOption() ){
				$this->readOption($option);
			}else{
				$this->readField($field);
			}
		}
	}

	function readField(&$block)
	{
		// todo: detect field property in [];
		//Check if have property
		$new	= [];
		$new['block_type']	= 'field';

		if(strpos($this->line, '[') !== false)
		{
			$new['options'] = [];
			if(preg_match('/\[(.*?)\]/', $this->line, $match))
			{
				
				foreach (explode(',', $match[1]) as $tmp)
				{
					list($key, $val) = array_map('trim', explode('=', $tmp, 2));
					if(empty($key))
						return false;
					$new['options'][$key] = $val;
				}
			}
			//remove options and line terminator
			$this->line = trim(substr($this->line, 0, strpos($this->line, '[')));
		}else{
			//remove line terminator
			$this->line = rtrim($this->line,' ;');
		}

		list($new['rule'], $new['type'], $new['name'], $new['number'])	= preg_split('/[\s=]+/', $this->line);
		$block[] = $new;
	}

	function readEnumBlock(&$block, &$option)
	{
		$this->has_enum = true;
		while( $this->read() ){
			if( $this->isOption() ){
				$this->readOption($option);
			}elseif($this->isEndBlock())
			{
				return;
			}else{
				list($key, $val) = explode('=', $this->line);
				$val = trim( trim($val,"; \t"),  "\"");
				$block[trim($key)]	= $val;
			}
		}

	}

	function readProperty(&$property)
	{
		list($key, $val) = explode('=', $this->line);
		$val = trim($val, "\"; \t");
		$property[trim($key)] = $val;
		Console::info("readProperty: $key = $val");
	}

	function readOption(&$options)
	{

		list($name, $value) = explode('=', trim(substr($this->line, 7 /* len('options ') */ ), ";\t"), 2);

		$name	= trim($name);
		$value	= trim($value, "\" \t");
		if(empty($name)) return;
		$options[$name]	= $value;
		//Console::info("readOption: $name: $value");
	}

	function isOption()
	{
		return strpos($this->line, 'option ') === 0;
	}

	function isStartBlock()
	{
		return strpos($this->line, '{') === strlen($this->line) - 1;
	}

	function isEndBlock()
	{
		return $this->line === '}' || $this->line === false;
	}

	function isGroup()
	{
		return strpos($this->line, ' group ') !== false;
	}

	function startWith($str)
	{
		return strpos($this->line, $str) === 0;
	}

	function contain($str)
	{
		return strpos($this->line, $str) !== false;
	}

	/**
	 * Read line and clear comment
	 */
	function read()
	{
		$this->comment = "";
		for(;;)
		{
			$this->line_pos++;
			$this->line = fgets($this->handle, 1024);
			
			if($this->line === false)
				return false;

			$this->line = trim($this->line);

			if(	!empty($this->line) )
			{
				
				$pos = strpos($this->line, '//');
				if( $pos === false )
				{
					break;
				}elseif($pos !== 0)
				{
					$this->comment = trim(substr($this->line, $pos + 2));
					//remove comment
					$this->line = preg_replace('/^(.*?;)\s*\/\/.*/', '$1', $this->line);
					break;
				}else{
					// Comment line	
					$this->comment .= trim(substr($this->line, $pos + 2)) . "\r\n";
				} 
			}
		}

		return true;
	}
}
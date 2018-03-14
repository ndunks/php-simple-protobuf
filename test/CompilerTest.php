<?php

/**
* @group compiler
*/
class CompilerTest extends PHPUnit\Framework\TestCase
{
	
	function testCompile()
	{
		include __DIR__ . '/../vendor/autoload.php';
		$bin = realpath(dirname(__DIR__) . "/bin/compiler.php");
		
		$this->assertNotTrue(empty($bin), 'Compiler executable not found');
		$inputdir = __DIR__ . '/proto';
		$outdir	= __DIR__ . '/out';
		$classes = [];

		if(!is_dir($outdir))
			mkdir($outdir);

		$protos = [
					'simple.proto'	=> ['Simple.php'],
					'namespaced.proto'	=> ['namespaced/Simple.php'],
					'nested.proto'	=> [
								'nested/Man.php',
								'nested/Man/Wife.php',
								'nested/Man/Child.php',
								],
					'group.proto'	=> ['Group.php'],
					'simple_group.proto'	=> ['SimpleGroup.php', 'SimpleGroup/Child.php'],
					'packed.proto'	=> ['Packed.php'],
					'addressbook.proto'	=> [
								'tutorial/Person.php',
								'tutorial/Person/PhoneType.php',
								'tutorial/Person/PhoneNumber.php',
								'tutorial/AddressBook.php',
						]
					];
		foreach ($protos as $input => $results)
		{
			$file = $inputdir . '/' . $input;

			//delete all out put
			foreach ($results as $value) {
				$result = "$outdir/$value";
				if(file_exists($result))
					unlink($result);
			}
			$output = [];
			exec("php $bin --out=$outdir --file=$file", $output);
			//print_r($output);
			$this->assertContains("OK $input", $output);
			//check for generated file
			foreach ($results as $value) {
				$result = "$outdir/$value";
				$this->assertFileExists($result);
				if(file_exists($result))
				{
					//load the classes
					include $result;
					$class = strtr($value, '/', '\\');
					$class	= substr($class, 0, strrpos($class, '.'));
					$obj = new $class();
				}
			}

		}
	}
}
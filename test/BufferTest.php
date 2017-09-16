<?php
use kc\proto\serializer\native\Reader;
use kc\proto\serializer\native\Writer;
use kc\proto\serializer\native\Util;
use kc\proto\Message;


/**
* @group buffer
*/
class BufferTest extends PHPUnit\Framework\TestCase
{
	function testBuffer()
	{
		$string = "1234567890";
		$reader = new Reader($string);
		$this->assertEquals(10, $reader->available());
		$reader->pushLimit(5);
		$this->assertEquals(5, $reader->available());
		$this->assertNotTrue($reader->eof());
		$this->assertEquals("123", $reader->readBytes(3));
		$this->assertEquals(2, $reader->available());
		$this->assertEquals("45", $reader->readBytes(2));
		$this->assertEquals(0, $reader->available());
		$this->assertTrue($reader->eof());
		
		$reader->reset();
		$this->assertEquals(5, $reader->available());
		$this->assertNotTrue($reader->eof());
		$this->assertEquals("12345", $reader->readBytes(5));
		$this->assertTrue($reader->eof());
		$this->assertEquals(0, $reader->available());

		$reader->popLimit();
		$this->assertNotTrue($reader->eof());
		$this->assertEquals(5, $reader->available());
		$this->assertEquals(5, $reader->pos);
		$this->assertEquals("678", $reader->readBytes(3));
		$this->assertEquals("90", $reader->readBytes(2));
		$this->assertTrue($reader->eof());

		$reader->reset();
		$this->assertEquals("12345", $reader->readBytes(5));
			$reader->pushLimit(3);
			$this->assertEquals(3, $reader->available());
			$this->assertEquals("6", $reader->readBytes(1));
			$this->assertEquals(2, $reader->available());
			try {
				
				$reader->pushLimit(3);
				die("INVALID");
			} catch (Exception $e) {
				$this->assertContains("Limit max is", $e->getMessage());
			}
				$reader->pushLimit(1);
				$this->assertEquals(1, $reader->available());
				$this->assertEquals("7", $reader->readBytes(1));
				$this->assertEquals(0, $reader->available());
				$this->assertTrue($reader->eof());
				$reader->popLimit();

			$this->assertNotTrue($reader->eof());
			$this->assertEquals(1, $reader->available());
			$reader->reset();
			$this->assertEquals(3, $reader->available());
			$this->assertEquals(5, $reader->pos);
			$this->assertEquals("6", $reader->readBytes(1));
				$reader->pushLimit(2);
				$this->assertEquals("7", $reader->readBytes(1));
				try {
					$reader->readBytes(2);
				} catch (Exception $e) {
					$this->assertContains("Not enough buffer to read", $e->getMessage());
				}
				$this->assertEquals("8", $reader->readBytes(1)); // 8
				$this->assertTrue($reader->eof());
				$reader->popLimit();
			$this->assertTrue($reader->eof());
			$reader->popLimit();
		$this->assertNotTrue($reader->eof());
		$this->assertEquals("90", $reader->readBytes(2));
		$this->assertTrue($reader->eof());
		$reader->reset(3);



		$maxloop = 100;
		$counter = 0;
		while ( ($byte = $reader->read()) !== false)
		{
			$this->assertEquals($string[$counter], $byte);
			$counter++;
			if($counter >= $maxloop)
				throw new Exception("Maxloop reached");		
		}
		$reader->reset();
		$this->assertEquals("123", $reader->readBytes(3));
		$this->assertEquals("45", $reader->readBytes(2));
		$this->assertEquals("6", $reader->readBytes(1));
		$this->assertEquals("789", $reader->readBytes(3));
		$this->assertEquals("0", $reader->readBytes(1));
		$reader->reset();
		$this->assertEquals($string, $reader->readBytes(10));

		$reader->reset();
		$this->expectException(Exception::class);
		$reader->readBytes(11);
		$this->assertTrue($reader->eof());

		$writer = new Writer();
		$writer->writeByte(ord('A'));
		$writer->write('B');
		$writer->writeByte(ord('C'));
		$this->assertEquals('ABC', $writer->getData());

	}

	function testTag()
	{
		$writer = new Writer();
		$tests	= [];
		//All types
		for($i = 1; $i <= 18; $i++)
		{
			$test = ['number' => $i, 'wire' => Util::getWireType($i)];
			$tests[] = $test;
			$written = $writer->writeTag($test['number'], $test['wire']);
		}

		$maxloop = 100;
		$counter = 0;
		$reader	= new Reader($writer->getData());
		while ( ($tag = $reader->readTag()) !== false)
		{
			$number	= Util::getTagFieldNumber($tag);
			$wire	= Util::getTagWireType($tag);

			$this->assertEquals($tests[$counter]['number'] , $number);
			$this->assertEquals($tests[$counter]['wire'], $wire);

			if($counter++ >= $maxloop)
				throw new Exception("Maxloop reached");
				
		}

	}

	function testData()
	{
		$fields = [
			1 => [ 'name',	Message::TYPE_STRING,	Message::RULE_REQUIRED,	false,	'string'],
			2 => [ 'address',	Message::TYPE_STRING,	Message::RULE_OPTIONAL,	false,	'string'],
			3 => [ 'age',	Message::TYPE_INT32,	Message::RULE_OPTIONAL,	false,	'int'],
		];
		$values	= [
			1 => 'user',
			2 => 'Indonesia',
			3 => 25
		];
		$writer = new Writer();
		$writen	= 0;
		foreach ($fields as $number => $field)
		{
			$writen += $writer->writeValue($number, $field[1], $values[$number]);
		}
		$writer->writeToFile(__DIR__ . '/result/simple_1.manual.bin');

		//READ BACK
		$reader	= new Reader($writer->getData());
		
		foreach ($fields as $number => $field)
		{
			$tag	= $reader->readTag();
			$check	= Util::getTagFieldNumber($tag);
			$wire	= Util::getTagWireType($tag);

			$this->assertEquals( $number, $check );
			$this->assertEquals(Util::getWireType($field[1]), $wire);
			$value	= $reader->readValue($field);
			$this->assertEquals($values[$number], $value);
		}
		$this->assertTrue($reader->eof());
		$this->assertEquals(0, $reader->available());
		
	}


}
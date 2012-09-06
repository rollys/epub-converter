<?php

/**
 * Model_Epub class tests
 *
 * @group App
 * @group Model
 */
class Test_Model_Epub extends TestCase
{
	public function setUp()
	{
		$this->epub = new Model_Epub();
		$this->epub->set_epub_dir(DOCROOT . '/files/epub');
		$this->epub->set_kepub_dir(DOCROOT . '/files/kepub');
		$this->epub->set_filename('ケヴィン・ケリー著作選集１-1.0.1.epub');
	}
	
	public function tearDown()
	{
		unset($this->epub);
	}
	
	public function test_add_kepub_span()
	{
		$file = APPPATH . 'tests/fixture/k12121626.html';
		
		$ref = new ReflectionMethod('Model_Epub', 'add_kepub_span');
		$ref->setAccessible(true);
		$test = $ref->invoke($this->epub, $file);
		$expected = file_get_contents($file . '.expected');
		$this->assertEquals($expected, $test);
	}
	
	public function test_resize_image()
	{
		$file = APPPATH . 'tests/fixture/field.jpg';
		
		$ref = new ReflectionMethod('Model_Epub', 'resize_image');
		$ref->setAccessible(true);
		
		$expected = 200;
		$this->epub->set_image_max_size($expected);
		$ref->invoke($this->epub, $file);
		
		$test = Image::sizes($file)->width;
		$this->assertEquals($expected, $test);
		
		// restore fixture
		copy($file . '.orig', $file);
	}
	
	public function test_extract()
	{
		$test = $this->epub->extract();
		$this->assertTrue($test);
	}
	
	public function test_get_rootfile()
	{
		$test = $this->epub->get_rootfile();
		$this->assertEquals('OEBPS/book.opf', $test);
	}
	
	public function test_get_html_filelist()
	{
		$test = $this->epub->get_html_filelist();
		$expected = array (
		  0 => 'book.html',
		  1 => 'top.html',
		  2 => 'toc.html',
		  3 => 'foreword.html',
		  4 => 'preface.html',
		  5 => 'k12121626.html',
		  6 => 'k12799892.html',
		  7 => 'k13122515.html',
		  8 => 'k13494910.html',
		  9 => 'k13768906.html',
		  10 => 'k14200219.html',
		  11 => 'k14421452.html',
		  12 => 'k14657309.html',
		  13 => 'k14803880.html',
		  14 => 'k15016045.html',
		  15 => 'k15329827.html',
		  16 => 'k15722362.html',
		  17 => 'k15988444.html',
		  18 => 'k16282081.html',
		  19 => 'k16576836.html',
		  20 => 'k16786376.html',
		  21 => 'k17223180.html',
		  22 => 'k17312765.html',
		  23 => 'k17482223.html',
		  24 => 'k17544273.html',
		  25 => 'k17811192.html',
		  26 => 'k17939331.html',
		  27 => 'k18267373.html',
		  28 => 'k18545167.html',
		  29 => 'k19055263.html',
		  30 => 'app.html',
		  31 => 'colophon.html',
		);
		
		$this->assertEquals($expected, $test);
	}
	
	public function test_get_kepub_filename()
	{
		$test = $this->epub->get_kepub_filename();
		$expected = 'ケヴィン・ケリー著作選集１-1.0.1.kepub.epub';
		$this->assertEquals($expected, $test);
	}
	
	public function test_build_kepub()
	{
		$test = $this->epub->build_kepub();
		$filename = DOCROOT . '/files/kepub/' . $this->epub->get_kepub_filename();
		$this->assertEquals(true, file_exists($filename));
	}
	
	public function test_set_prefix()
	{
		$this->epub->set_prefix('20120905_');
		$test = $this->epub->get_kepub_filename();
		$expected = '20120905_ケヴィン・ケリー著作選集１-1.0.1.kepub.epub';
		$this->assertEquals($expected, $test);
	}
}

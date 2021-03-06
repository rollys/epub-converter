<?php

/**
 * Epub Model
 *
 * @author     Kenji Suzuki https://github.com/kenjis
 * @copyright  2012 Kenji Suzuki
 * @license    AGPL 3.0 http://opensource.org/licenses/AGPL-3.0
 */
class Model_Epub
{
	public $debug = false;  // debug mode
	
	protected $filename;
	protected $prefix;  // prefix to kepub filename
	protected $epub_dir;
	protected $kepub_dir;
	protected $work_dir;
	protected $file_list;
	protected $rootfile;
	protected $html_filelist;
	protected $image_filelist;
	protected $image_max_size;
	
	public function set_filename($filename)
	{
		$this->filename = $filename;
	}
	
	public function set_epub_dir($dir)
	{
		$this->epub_dir = $dir;
	}
	
	public function set_kepub_dir($dir)
	{
		$this->kepub_dir = $dir;
	}
	
	public function set_prefix($prefix)
	{
		$this->prefix = $prefix;
	}
	
	public function set_image_max_size($width, $height = null)
	{
		$this->image_max_size = array(
			'width' => $width,
			'height' => $height
		);
	}
	
	public function extract()
	{
		$tmp_dir = APPPATH . 'tmp/epub';
		if ( ! file_exists($tmp_dir))
		{
			mkdir($tmp_dir);
		}
		
		$file = $this->epub_dir . '/' . $this->filename;
		$this->work_dir = $tmp_dir . '/' . getmypid() . '_' . $this->filename;
		
		if (file_exists($this->work_dir))
		{
			File::delete_dir($this->work_dir, true, false);
		}
		else
		{
			mkdir($this->work_dir, 0777, true);
		}
		
		$unzip = new Unzip();
		$this->file_list = $unzip->extract($file, $this->work_dir);
		
		if (count($this->file_list) > 5)
		{
			return true;
		}
		else
		{
			throw new FuelException('EPUB file is damaged?');
		}
	}
	
	public function get_rootfile()
	{
		if (is_null($this->rootfile))
		{
			$this->read_opf();
		}
		
		return $this->rootfile;
	}
	
	protected function read_opf()
	{
		if (is_null($this->work_dir))
		{
			$this->extract();
		}
		
		$file = $this->work_dir . '/' . 'META-INF/container.xml';
		$xml = simplexml_load_file($file);
		$this->rootfile = (string) $xml->rootfiles->rootfile->attributes()->{'full-path'};
	}
	
	public function get_html_filelist()
	{
		if (is_null($this->html_filelist))
		{
			$this->read_rootfile();
		}
	
		return $this->html_filelist;
	}
	
	protected function read_rootfile()
	{
		if (is_null($this->rootfile))
		{
			$this->get_rootfile();
		}
		
		$file = $this->work_dir . '/' . $this->rootfile;
		$xml = simplexml_load_file($file);
		//var_dump($xml->manifest); exit;
		
		$this->html_filelist = array();
		
		foreach ($xml->manifest->item as $item) {
			//var_dump($item->attributes()->{'media-type'});
			
			if ((string) $item->attributes()->{'media-type'} === 'application/xhtml+xml')
			{
				$this->html_filelist[] = (string) $item->attributes()->href;
			}
			
			if (substr((string) $item->attributes()->{'media-type'}, 0, 6) === 'image/')
			{
				$this->image_filelist[] = (string) $item->attributes()->href;
			}
		}
		
		//var_export($this->html_filelist); exit;
	}
	
	public function build_kepub()
	{
		if (is_null($this->html_filelist))
		{
			$this->get_html_filelist();
		}
		
		$dir = dirname($this->rootfile);
		
		// add <span> tag
		foreach ($this->html_filelist as $index => $file)
		{
			$file = $this->work_dir . '/' . $dir . '/' . $file;
			
			// for debugging
			copy($file, $file . '.orig');
			
			$xhtml = file_get_contents($file);
			$content = $this->add_koboSpan($xhtml);
			file_put_contents($file, $content);
		}
		
		// resize images
		if ( ! is_null($this->image_max_size) && ! is_null($this->image_filelist))
		{
			foreach ($this->image_filelist as $index => $file)
			{
				$file = $this->work_dir . '/' . $dir . '/' . $file;
				$this->resize_image($file);
			}
		}
		
		$this->create_zip();
		
		if ( ! $this->debug)
		{
			File::delete_dir($this->work_dir);
		}
	}
	
	/**
	 * Download a KEPUB file and delete it
	 */
	public function download()
	{
		$info = File::file_info($this->kepub_dir . '/' . $this->get_kepub_filename());
		$class = 'File';
		
		Event::register('shutdown', function () use ($info, $class) {
			if ( ! $file = call_user_func(array($class, 'open_file'), @fopen($info['realpath'], 'rb'), LOCK_SH))
			{
				throw new \FileAccessException('Filename given could not be opened for download.');
			}
			
			while (ob_get_level() > 0)
			{
				ob_end_clean();
			}
			
			ini_get('zlib.output_compression') and ini_set('zlib.output_compression', 0);
			! ini_get('safe_mode') and set_time_limit(0);
			
			header('Content-Type: '.$info['mimetype']);
			header('Content-Disposition: attachment; filename="'.$info['basename'].'"');
			header('Content-Description: File Transfer');
			header('Content-Length: '.$info['size']);
			header('Content-Transfer-Encoding: binary');
			header('Expires: 0');
			header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
			
			while( ! feof($file))
			{
				echo fread($file, 2048);
			}
			
			call_user_func(array($class, 'close_file'), $file);
			
			// remove generated kepub file
			File::delete_dir($info['dirname']);
		});
		
		exit;
	}
	
	protected function resize_image($file)
	{
		$sizes = Image::sizes($file);
			
		if ($sizes->width > $this->image_max_size['width'])
		{
			// for debugging
			copy($file, $file . '.orig');
	
			Image::load($file)
				->resize(
					$this->image_max_size['width'],
					$this->image_max_size['height'],
					true
				)->save($file);
		}
	}
	
	protected function create_zip()
	{
		// create Zip file
		$zip = new ZipArchive();
		$filename = $this->kepub_dir . '/' . $this->get_kepub_filename();
		
		if ( ! file_exists($this->kepub_dir))
		{
			mkdir($this->kepub_dir, 0777, true);
		}
		
		if ($zip->open($filename, ZIPARCHIVE::CREATE) !== true)
		{
			exit("cannot open <$filename>\n");
		}
		
		foreach ($this->file_list as $file_to_add)
		{
			$len = strlen($this->work_dir);
			$localname = substr($file_to_add, $len + 1);
			//var_dump($localname); exit;
			$zip->addFile($file_to_add, $localname);
		}
		
		$zip->close();
	}
	
	protected function add_koboSpan($xhtml)
	{
		$content = '';
		$para = 1;
		
		//var_dump($xhtml);
		
		$in_body = false;
		$span_open = false;
		
		while (preg_match('|(.*?)<(.+?)>(.*)|su', $xhtml, $matches))
		{
			//echo 'before: ' . $matches[1] . PHP_EOL;
			//echo 'tag: ' . $matches[2] . PHP_EOL;
			//echo 'after: ' . mb_substr($matches[3], 0, 20) . PHP_EOL;
			
			$before = $matches[1];
			$tag    = $matches[2];
			$after  = $matches[3];
			
			//var_dump($tag);
			
			$content .= $before;
			
			if ($tag === 'body' || substr($tag, 0, 5) === 'body ')
			{
				$in_body = true;
			}
			
			$next = trim($after);
			
			if ($in_body)
			{
				if ($span_open)
				{
					$content .= '</span><' . $tag . '>';
					$span_open = false;
					
					if ($next !== '' && $next[0] !== '<')
					{
						$content .= '<span class="koboSpan" id="kobo.' . $para . '.1">';
						$para++;
						$span_open = true;
					}
				}
				else if ($next !== '' && $next[0] !== '<')
				{
					$content .= '<' . $tag . '><span class="koboSpan" id="kobo.' . $para . '.1">';
					$para++;
					$span_open = true;
				}
				else
				{
					$content .= '<' . $tag . '>';
				}
			}
			else
			{
				$content .= '<' . $tag . '>';
			}
			
			$xhtml = $after;
		}
			
		return $content . $xhtml;
	}
	
	public function get_kepub_filename()
	{
		$info = pathinfo($this->filename);
		//var_dump($info);
		return $this->prefix . $info['filename'] . '.kepub.epub';
	}
}

<?php



class ImagickHTML {
  
  public $vars = [
    'filename' => '',
    'css_file_paths' => [],
    'css' => [],
  ];
  
  private $query;
  private $image;
  
  public function __construct($html_path='', $replacements=[]) {
    $html = self::_load($html_path);
    foreach($replacements as $find=>$replace) {
      $replacements['{{'.$find.'}}'] = $replace;
      unset($replacements[$find]);
    }
    $html = strtr(￼$html, $replacements);
    $doc = new DOMDocument();
    $doc->loadHTML($html);
    $xpath = new DOMXPath($doc);
    $this->query = $xpath;
    # Title as filename
    $title = $xpath->query(".//title");
    if ($title->length > 0 && !empty($this->vars['filename'] = $title->item(0)->nodeValue))
      $this->vars['filename'] = trim($this->vars['filename']);
    # CSS
    $css = $xpath->query(".//link[@href]");
    if ($css->length > 0)
      for($i=0;$i<$css->length;$i++)
        $this->vars['css_file_paths'][] = $css->item($i)->getAttribute('href');
    $css_to_json = [
      '/\/\*[^\*]*\*\//'                                         => '',           // Remove comments
      '/\"/'                                                     => '\'',         // Replace all double quotes with single
      '/\ {2,}/'                                                 => ' ',          // Conovert all multi spaces to a single space
      '/\n|\r|\n\r|\r\n|\@(media|font\-face)\s*\{[^\}]{1,}\},*/' => '',           // Remove any @ selectors
      '/\}/'                                                     => '},',         // Add comma after each css definition...
      '/\},\s*$/'                                                => '}',          // ... except the last one
      '/([^\:\{]{1,})\s*\:\s*([^\;]{1,})\;/'                     => '"$1":"$2";', // Quote all CSS propertys
      '/\;/'                                                     => ',',          // Change semis to colons...
      '/\"\,\}/'                                                 => '"}',         // ...except for the last CSS propertys
      '/(^[^\{]{1,})\s*\{|(?<=\,)([^\{]{1,})\s*\{/'              => '"$1$2":{',   // quote selectors
      '/\"\ |\ \"/'                                              => '"',          // Cleanup unneeded spaces.
    ];
    $this->vars['css'] = (object)$this->vars['css'];
    foreach($this->vars['css_file_paths'] as $css_file_path) {
      $css = self::_load($css_file_path);
      $css = preg_replace(array_keys($css_to_json), array_values($css_to_json), $css);
      $css = '{'.$css.'}';
      $css = json_decode($css);
      $this->vars['css'] = (object) array_merge((array) $this->vars['css'], (array) $css);
    }
    # Go
    $this->render();
  }
  
  private function css_selector_to_xpath($css_selector) {
    $ors = preg_split('/\,\s*/', $css_selector);
    $xpaths = [];
    foreach($ors as $selector) {
      $xpath = ['/'];
      $parts = preg_split('/\s{1,}/', $selector);
      foreach($parts as $part) {
        $part = trim($part);
        preg_match_all('/(^[^\.\#]{1,})|(\.[^\.\#]{1,})|(\#[^\.\#]{1,})/', $part, $matches);
        list($all, $node, $classes, $ids) = $matches;
        $node = array_filter($node);
        $classes = array_filter($classes);
        $ids = array_filter($ids);
        $node = implode('', $node);
        if (empty($node)) $node = '*';
        $attrs = [];
        foreach($classes as $cl)
          $attrs[] = 'contains(@class, \''.str_replace('.', '', $cl).'\')';
        foreach($ids as $id)
          $attrs[] = '@id = \''.str_replace('#', '', $id).'\'';
        $attrs = implode(' and ', $attrs);
        if (!empty($attrs)) $attrs = "[$attrs]";
        $xpath[] = $node.$attrs;
      }
      $xpath = implode('/', $xpath);
      $xpaths[] = $xpath;
    }
    $xpaths = implode(' | ', $xpaths);
    return $xpaths;
  }
  
  public function render() {
    foreach($this->vars['css'] as $selector=>$style) {
      if (isset($image)) break;
      if (!preg_match('/^body(\.|\#|\[|)/i', $selector)) continue;
      foreach($style as $property=>$value) {
        if ($property != 'background' && $property != 'background-image') continue;
        if (!preg_match_all('/url\(\s*([^\)]{1,})\s*\)/i', $value, $image_path)) continue;
        if (!isset($image_path[1][0])) continue;
        $image_path = str_replace('\'','',$image_path[1][0]);
        if (empty($image_path)) continue;
        $body = $this->query->query(self::css_selector_to_xpath($selector));
        if ($body->length > 0) {
          $image = new Imagick($image_path);
          break;
        }
      }
    }
    if (!isset($image)) $image = new Imagick();
    $draw = new ImagickDraw();
    foreach($this->vars['css'] as $selector=>$style) {
      $styles = array_keys((array) $style);
      if (
        in_array('font-family', $styles) ||
        in_array('font-size', $styles) ||
        in_array('font-weight', $styles) ||
        in_array('color', $styles) ||
        in_array('text-transform', $styles)
      ) {
        $text_element = $this->query->query(self::css_selector_to_xpath($selector));
        if ($text_element->length > 0) {
          $text = $text_element->item(0)->nodeValue;
          foreach($style as $property=>$value) {
            switch($property) {
              case 'font-family' : $draw->setFont(str_replace('\'','',"$value.ttf")); break;
              case 'color' : $draw->setFillColor($value); break;
              case 'font-size' : $draw->setFontSize(floatval(str_replace('pt','',$value))); break;
              case 'font-weight' : $draw->setFontWeight(intval($value)); break;
              case 'text-transform' : 
                  if ($value == 'uppercase') $text = strtoupper($text);
                  if ($value == 'capitalize') $text = ucfirst($text);
                  if ($value == 'lowercase') $text = strtolower($text);
                break;
            }
          }
          $left = isset($style->left) && !empty($style->left) ? intval(str_replace('px', '', $style->left)) : 0;
          $top = isset($style->top) && !empty($style->top) ? intval(str_replace('px', '', $style->top)) : 0;
          $width = isset($style->width) && !empty($style->width) ? intval(str_replace('px', '', $style->width)) : 0;
          if ($width) {
            list($lines, $lineHeight) = self::word_wrap_annotation($image, $draw, $text, $width);
            for($i = 0; $i < count($lines); $i++)
              $image->annotateImage($draw, $left, $top + $i*$lineHeight, 0, $lines[$i]);
          } else {
            $image->annotateImage($draw, $left, $top, 0, $text);
          }
        }
      }
    }
    $this->image = $image;
  }
  
  public function save($file_path='') {
    if (!$this->image) return;
    $file_path = !empty($file_path) ? $file_path : !empty($this->vars['filename']) ? $this->vars['filename'] : '';
    if (empty($file_path)) return;
    $this->image->writeImageFile(fopen($file_path, 'wb'));
  }
  
  public function output() {
    if (!$this->image) return;
    header('Content-type: image/jpeg');
    echo $this->image;
  }
  
  private function _load($path) {
    return file_get_contents($path);
  }
  
  private function word_wrap_annotation(&$image, &$draw, $text, $maxWidth) {
    $words = explode(" ", $text);
    $lines = array();
    $i = 0;
    $lineHeight = 0;
    while($i < count($words) ) {
        $currentLine = $words[$i];
        if($i+1 >= count($words)) {
            $lines[] = $currentLine;
            break;
        }
        //Check to see if we can add another word to this line
        $metrics = $image->queryFontMetrics($draw, $currentLine . ' ' . $words[$i+1]);
        while($metrics['textWidth'] <= $maxWidth) {
          //If so, do it and keep doing it!
          $currentLine .= ' ' . $words[++$i];
          if($i+1 >= count($words))
              break;
          $metrics = $image->queryFontMetrics($draw, $currentLine . ' ' . $words[$i+1]);
        }
        $lines[] = $currentLine;
        $i++;
        //Finally, update line height
        if($metrics['textHeight'] > $lineHeight)
            $lineHeight = $metrics['textHeight'];
    }
    return array($lines, $lineHeight);
  }
  
}

?>

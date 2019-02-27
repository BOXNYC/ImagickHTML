# ImagickHTML
PHP class where you can provide an HTML file which has CSS, and it will use it for rendering an image. Sweet :)

### Requirements
- Imagick PHP

## Example usage
```
<?php
$IMHTML = new ImagickHTML('image.html', [
  'file_path' => $file_path,
  'name' => $player_info->name,
  'last' => $player_info->last,
  'body_class' => $conference,
  'points' => $player_info->score,
  'school' => $player_info->school,
]);
$IMHTML->save();
$IMHTML->output();
?>
```

## Notice
/!\ This class is a work in progress and only handles basic 2D CSS.

/!\ <title> is used as the file path if ya want to .save() it to disk.
  
/!\ line-height: style needs work

## Coolest part? The "CSS to XPath" function I wrote for it! Its a private util but it is sweet. The only thing it doesn't do is attribute selector but that would be easy to implement. Maybe I will one day, but no time.
```
<?php
function css_selector_to_xpath($css_selector) {
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
?>
```

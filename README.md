# ImagickHTML
PHP class where you can provide an HTML file which has CSS, and it will use it for rendering an image. Sweet :)
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

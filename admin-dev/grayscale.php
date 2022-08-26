<?php
header('Content-Type: image/jpeg');
$im = imagecreatefromjpeg("../".$_GET['img']);

if($im && imagefilter($im, IMG_FILTER_GRAYSCALE))
{
    imagejpeg($im);
}
else
{
    echo 'La conversion en grayscale a échoué.';
}

imagedestroy($im);
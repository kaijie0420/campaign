<?php

namespace App\Helpers;

class ImageRecognitionHelper 
{
    private $image;

    public function __construct($image)
    {
        $this->image = $image;
    }

    // Mock call image recognition API
    public static function validate()
    {
        // return (bool)random_int(0, 1);
        return false;
    }
}
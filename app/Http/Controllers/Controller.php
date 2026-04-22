<?php

namespace App\Http\Controllers;

abstract class Controller
{
    public function __construct()
    {
        header('Content-Type: application/json');
        header('Accept: application/json');
    }
}

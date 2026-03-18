<?php
use Webman\Route;
Route::options('[{path:.+}]', function () { return response(''); });
Route::disableDefaultRoute();

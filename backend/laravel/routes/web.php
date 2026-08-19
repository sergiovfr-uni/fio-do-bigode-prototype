<?php
use Illuminate\Support\Facades\Route;
Route::get('/', fn () => response()->json(['service'=>'Fio do Bigode API','status'=>'ok']));

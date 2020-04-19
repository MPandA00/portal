<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::prefix('infrastructure')->group(function () {
    Route::get('/', 'InfrastructureController@index')->name('infrastructure.index');
    Route::get('/ec2', 'InfrastructureController@getEc2Instances')->name('infrastructure.ec2');
});

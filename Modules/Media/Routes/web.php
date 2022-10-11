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
use Illuminate\Support\Facades\Route;

Route::resource('media', 'MediaController')
->parameters([
    'media'=> 'media'
])
->names([
    'index' => 'media.index',
    'show' => 'media.show',
    'create' => 'media.create',
    'store' => 'media.store',
    'update' => 'media.update',
    'edit' => 'media.edit',
    'destroy' => 'media.destroy',
]);
Route::get('/search', 'MediaController@search');

/*
|Media Tags
*/

Route::resource('Tag', 'MediaTagController')
->parameters([
    'Tag'=> 'Tag'
])
->names([
    'index' => 'media.Tag.index',
    'store' => 'media.Tag.store',
]);
Route::post('/delete/{id}', 'MediaTagController@destroy')->name('media.Tag.destroy');
Route::get('/update/{MediaTags}', 'MediaTagController@update')->name('media.Tag.update');
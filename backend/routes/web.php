<?php

use App\Http\Controllers\Web\InvitePageController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Web davetiye (spec §9). Token tahminine karşı IP başına sınır.
Route::middleware('throttle:invite-page')->where(['token' => '[A-Za-z0-9]{40}'])->group(function () {
    Route::get('z/{token}', [InvitePageController::class, 'show'])->name('invite.show');
    Route::post('z/{token}/open', [InvitePageController::class, 'open'])->name('invite.open');
    Route::post('z/{token}/rsvp', [InvitePageController::class, 'rsvp'])->name('invite.rsvp');
});

Route::view('indir', 'web.download')->name('download');
Route::view('aydinlatma-metni', 'web.legal', ['title' => 'Aydınlatma metni'])->name('legal.kvkk');
Route::view('gizlilik', 'web.legal', ['title' => 'Gizlilik politikası'])->name('legal.privacy');
Route::view('kullanim-kosullari', 'web.legal', ['title' => 'Kullanım koşulları'])->name('legal.terms');

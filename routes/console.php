<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('globiim:about', function () {
    $this->info('Globiim API backend');
})->purpose('Display Globiim backend information');

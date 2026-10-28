<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('orders:expire-unpaid')->everyFifteenMinutes();

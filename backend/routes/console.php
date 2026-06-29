<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('app:check-deadlines')->dailyAt('08:00');

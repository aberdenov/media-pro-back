<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('catalog:sync oasis')->hourly()->withoutOverlapping(120);
Schedule::command('catalog:sync arte')->everyFifteenMinutes()->withoutOverlapping(120);

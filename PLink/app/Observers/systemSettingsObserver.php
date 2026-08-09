<?php

namespace App\Observers;

use App\Models\systemSettings;

class systemSettingsObserver
{
    /**
     * Handle the systemSettings "created" event.
     */
    public function created(systemSettings $systemSettings): void
    {
        //
    }

    /**
     * Handle the systemSettings "updated" event.
     */
    public function updated(systemSettings $systemSettings): void
    {
        //
    }

    /**
     * Handle the systemSettings "deleted" event.
     */
    public function deleted(systemSettings $systemSettings): void
    {
        //
    }

    /**
     * Handle the systemSettings "restored" event.
     */
    public function restored(systemSettings $systemSettings): void
    {
        //
    }

    /**
     * Handle the systemSettings "force deleted" event.
     */
    public function forceDeleted(systemSettings $systemSettings): void
    {
        //
    }
}

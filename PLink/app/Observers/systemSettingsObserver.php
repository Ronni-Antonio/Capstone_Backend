<?php

namespace App\Observers;

use App\Models\systemSettings;
use App\Models\PlasticType;

class systemSettingsObserver
{
    /**
     * Handle the systemSettings "created" event.
     */
    public function created(systemSettings $systemSettings): void
    {
        $this->updatePlasticTypesPoints($systemSettings);
    }

    /**
     * Handle the systemSettings "updated" event.
     */
    public function updated(systemSettings $systemSettings): void
    {
        if ($systemSettings->isDirty('point_conversion')) {
            $this->updatePlasticTypesPoints($systemSettings);
        }
    }

    /**
     * Update all plastic types' points_per_item based on point_conversion and multiplier
     */
    protected function updatePlasticTypesPoints(systemSettings $systemSettings): void
    {
        PlasticType::all()->each(function ($plasticType) use ($systemSettings) {
            $plasticType->points_per_item = (int) round($systemSettings->point_conversion * $plasticType->multiplier);
            $plasticType->save();
        });
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

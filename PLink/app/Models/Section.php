<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Section extends Model
{
    protected $table = 'sections';
    protected $primaryKey = 'section_id';
    public $timestamps = true;
    protected $fillable = ['name'];

    public function students(): HasMany
    {
        return $this->hasMany(Students::class, 'section', 'name');
    }
}

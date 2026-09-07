<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GraduateDirectory extends Model
{
    protected $table = 'graduate_directory';

    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'last_name',
        'first_name',
        'phone',
        'imported_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'imported_at' => 'datetime',
        ];
    }
}

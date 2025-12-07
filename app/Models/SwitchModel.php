<?php

namespace App\Models;

use CodeIgniter\Model;

class SwitchModel extends Model
{
    protected $table      = 'switches';
    protected $primaryKey = 'id';
    protected $returnType = 'array';

    protected $useSoftDeletes = false;

    protected $allowedFields = [
        'name',
        'ip',
        'snmp_version',
        'community_rw',
        'ports_count',
        'room_id',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = null;

    protected array $casts = [
        'ports_count' => '?integer',
        'room_id'     => '?integer',
    ];
}

<?php

namespace App\Models;

use CodeIgniter\Model;

class ActionsLogModel extends Model
{
    protected $table            = 'actions_log';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';

    protected $useSoftDeletes   = false;

    protected $allowedFields = [
        'schedule_id',
        'action',
        'target_mac',
        'switch_ip',
        'port_ifindex',
        'result',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = null;

    protected array $casts = [
        'schedule_id' => '?integer',
        'port_ifindex' => '?integer',
    ];
}

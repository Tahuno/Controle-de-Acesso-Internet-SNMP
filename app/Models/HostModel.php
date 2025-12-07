<?php

namespace App\Models;

use CodeIgniter\Model;

class HostModel extends Model
{
    protected $table      = 'hosts';
    protected $primaryKey = 'id';
    protected $returnType = 'array';

    protected $useSoftDeletes = false;

    protected $allowedFields = [
        'hostname',
        'ip',
        'mac',
        'switch_id',
        'port_ifindex',
        'port_descr',
        'room_id',
        'is_authorized_machine',
        'is_protected',
        'is_blocked',
        'last_seen',
        'created_at',
        'updated_at',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = null;
}

<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Models\SwitchModel;
use App\Models\HostModel;
use App\Models\ActionsLogModel;
use App\Libraries\SnmpService;

class DiscoverHosts extends BaseCommand
{
    protected $group       = 'snmp';
    protected $name        = 'discover:hosts';

    public function run(array $params = [])
    {

        $switchModel = new SwitchModel();
        $hostModel   = new HostModel();
        $logModel    = new ActionsLogModel();

        $roomFilter = null;
        if (!empty($params[0])) {
            $roomFilter = (int) $params[0];
        }

        $builder = $switchModel;

        if ($roomFilter) {
            $builder = $builder->where('room_id', $roomFilter);
            CLI::write("Filtrando switches pela sala room_id={$roomFilter}", 'yellow');
        }

        $switches = $builder->findAll();

        if (empty($switches)) {
            CLI::write('Nenhum switch detectado.', 'yellow');
            return;
        }

        if (! extension_loaded('snmp')) {
            return;
        }

        $dot1dTpFdbPort       = '1.3.6.1.2.1.17.4.3.1.2'; // MAC -> bridge port
        $dot1dBasePortIfIndex = '1.3.6.1.2.1.17.1.4.1.2'; // bridge port -> ifIndex
        $ifDescrBase          = '1.3.6.1.2.1.2.2.1.2';    // ifDescr => descrição da porta

        foreach ($switches as $sw) {
            $swIp    = $sw['ip'] ?? 'unknown';
            $swId    = $sw['id'];
            $roomId  = $sw['room_id'] ?? null;

            $snmp = new SnmpService($sw);

            $walk = $snmp->walk($dot1dTpFdbPort);
            if (empty($walk) || !is_array($walk)) {
                continue;
            }

            try {
                $snmp = new SnmpService($sw);
            } catch (\Throwable $e) {
                continue;
            }

            foreach ($walk as $oid => $value) {
                // OID (6 octetos = MAC)
                $parts = explode('.', $oid);
                $suf   = array_slice($parts, -6);

                // Converte octetos decimais em MAC no formato XX:XX:XX:XX:XX:XX
                $hex = array_map(static function ($d) {
                    $h = dechex((int) $d);
                    return str_pad(strtoupper($h), 2, '0', STR_PAD_LEFT);
                }, $suf);

                $mac = implode(':', $hex);

                $bridgePort = null;
                if (preg_match('/(-?\d+)/', (string) $value, $m)) {
                    $bridgePort = (int) $m[1];
                }

                $ifIndex   = null;
                $portDescr = null;

                if (!empty($bridgePort)) {
                    // bridgePort -> ifIndex
                    $ifIdxRes = $snmp->get($dot1dBasePortIfIndex . '.' . $bridgePort);

                    if (!empty($ifIdxRes) && preg_match('/(-?\d+)/', (string) $ifIdxRes, $mm)) {
                        $ifIndex = (int) $mm[1];

                        if ($ifIndex > 0) {
                            // ifIndex -> ifDescr
                            $descr = $snmp->get($ifDescrBase . '.' . $ifIndex);
                            if (!empty($descr)) {
                                $portDescr = trim(preg_replace('/^.*?:\s*/', '', (string) $descr));
                            }
                        }
                    }
                }

                $now  = date('Y-m-d H:i:s');
                $exists = $hostModel->where('mac', $mac)->first();

                $data = [
                    'mac'          => $mac,
                    'switch_id'    => $sw['id'],
                    'port_ifindex' => $ifIndex,
                    'port_descr'   => $portDescr,
                    'last_seen'    => $now,
                    'room_id'       => $roomId,
                ];

                if ($exists) {
                    $oldRoom = $exists['room_id'] ?? null;

                    $hostModel->update($exists['id'], $data);

                    if ($oldRoom !== null && $roomId !== null && (int)$oldRoom !== (int)$roomId) {
                        $msg = "Host {$mac} rebind de sala {$oldRoom} para {$roomId}";

                        CLI::write($msg, 'yellow');

                        $logModel->insert([
                            'schedule_id'  => null,
                            'action'       => 'host-rebind-room',
                            'target_mac'   => $mac,
                            'switch_ip'    => $swIp,
                            'port_ifindex' => $ifIndex,
                            'result'       => json_encode([
                                'message'   => $msg,
                                'old_room'  => $oldRoom,
                                'new_room'  => $roomId,
                                'switch_id' => $swId,
                            ], JSON_UNESCAPED_UNICODE),
                            'created_at'   => $now,
                        ]);
                    } else {
                        CLI::write("Updated host {$mac}", 'blue');
                    }
                } else {
                    $hostModel->insert($data);
                    CLI::write("Inserted host {$mac}", 'green');
                }
            }
        }

        CLI::write('Discovery finished', 'green');
    }
}

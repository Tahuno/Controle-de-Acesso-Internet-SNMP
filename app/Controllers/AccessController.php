<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\HostModel;
use App\Models\SwitchModel;
use App\Models\ActionsLogModel;
use App\Libraries\SnmpService;

class AccessController extends BaseController
{
    protected HostModel $hostModel;
    protected SwitchModel $switchModel;
    protected ActionsLogModel $logModel;

    public function __construct()
    {
        $this->hostModel   = new HostModel();
        $this->switchModel = new SwitchModel();
        $this->logModel    = new ActionsLogModel();
    }

    public function dashboard()
    {
        $roomId = session()->get('room_id');

        $query = $this->hostModel->orderBy('hostname', 'ASC');

        if ($roomId) {
            $query->where('room_id', $roomId);
        }

        $hosts = $query->findAll();

        return view('access/dashboard', [
            'hosts' => $hosts,
        ]);
    }

    /**
     * Bloqueia um host específico pelo MAC.
     */
    public function apiBlock()
    {
        $mac = $this->request->getPost('mac');

        if (!$mac) {
            $json = $this->request->getJSON(true);
            if (is_array($json) && !empty($json['mac'])) {
                $mac = $json['mac'];
            }
        }

        if (!$mac) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON(['error' => 'mac required']);
        }

        $host = $this->hostModel->where('mac', $mac)->first();
        if (!$host) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['error' => 'host not found']);
        }

        $isProtected = !empty($host['is_protected']) || !empty($host['is_authorized_machine']);
        if ($isProtected) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON(['error' => 'host protegido, não pode ser bloqueado']);
        }

        $sw = $this->switchModel->find($host['switch_id']);
        if (!$sw) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['error' => 'switch not found']);
        }

        $snmp = new SnmpService($sw);
        $res  = $snmp->blockMac($mac);

        $this->logModel->insert([
            'action'       => 'manual-block',
            'target_mac'   => $mac,
            'switch_ip'    => $sw['ip'] ?? null,
            'port_ifindex' => $res['ifIndex'] ?? ($host['port_ifindex'] ?? null),
            'result'       => json_encode($res, JSON_UNESCAPED_UNICODE),
        ]);

        return $this->response->setJSON($res);
    }

    public function apiUnblock()
    {
        $mac = $this->request->getPost('mac');

        if (!$mac) {
            $json = $this->request->getJSON(true);
            if (is_array($json) && !empty($json['mac'])) {
                $mac = $json['mac'];
            }
        }

        if (!$mac) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON(['error' => 'mac required']);
        }

        $host = $this->hostModel->where('mac', $mac)->first();
        if (!$host) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['error' => 'host not found']);
        }

        $sw = $this->switchModel->find($host['switch_id']);
        if (!$sw) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['error' => 'switch not found']);
        }

        $snmp = new SnmpService($sw);
        $res  = $snmp->unblockMac($mac);

        $this->logModel->insert([
            'action'       => 'manual-unblock',
            'target_mac'   => $mac,
            'switch_ip'    => $sw['ip'] ?? null,
            'port_ifindex' => $res['ifIndex'] ?? ($host['port_ifindex'] ?? null),
            'result'       => json_encode($res, JSON_UNESCAPED_UNICODE),
        ]);

        return $this->response->setJSON($res);
    }


    /**
     * Bloqueia todas as máquinas da sala atual, exceto portas protegidas
     */
    public function apiBlockRoom()
    {
        $roomId = session()->get('room_id');
        if (!$roomId) {
            return $this->response->setStatusCode(400)
                ->setJSON(['error' => 'Sala não identificada']);
        }

        $hostsInRoom = $this->hostModel
            ->where('room_id', $roomId)
            ->where('is_protected', 0)
            ->where('is_authorized_machine', 0)
            ->findAll();

        if (empty($hostsInRoom)) {
            return $this->response->setJSON([
                'status'  => 'Nenhum host elegível para bloqueio nesta sala',
                'results' => [],
            ]);
        }

        $results = [];

        foreach ($hostsInRoom as $host) {
            $sw = $this->switchModel->find($host['switch_id']);
            if (!$sw) {
                $results[$host['mac']] = [
                    'success' => false,
                    'error'   => 'switch not found',
                ];
                continue;
            }

            try {
                $snmp = new SnmpService($sw);
                $res  = $snmp->blockMac($host['mac']);

                $this->logModel->insert([
                    'schedule_id'  => null,
                    'action'       => 'room-block',
                    'target_mac'   => $host['mac'],
                    'switch_ip'    => $sw['ip'] ?? null,
                    'port_ifindex' => $res['ifIndex'] ?? ($host['port_ifindex'] ?? null),
                    'result'       => json_encode($res, JSON_UNESCAPED_UNICODE),
                ]);

                $results[$host['mac']] = $res;
            } catch (\Throwable $e) {
                $results[$host['mac']] = [
                    'success' => false,
                    'error'   => $e->getMessage(),
                ];
            }
        }

        return $this->response->setJSON([
            'status'  => 'Concluído',
            'results' => $results,
        ]);
    }

    public function apiHostsStatus()
    {
        $roomId = session()->get('room_id');

        if (!$roomId) {
            return $this->response->setJSON([
                'error' => 'Sala não identificada.'
            ])->setStatusCode(400);
        }

        // Verifica se há algum switch mock associado à sala
        $hasMockSwitch = $this->switchModel
            ->where('room_id', $roomId)
            ->where('snmp_version', 'mock')
            ->countAllResults() > 0;

        $builder = $this->hostModel
            ->where('room_id', $roomId);

        // Se não for mock, aplica filtro de "ativos recentemente"
        if (!$hasMockSwitch) {
            $thresholdMinutes = 5; // ajuste se quiser
            $limit = date('Y-m-d H:i:s', time() - $thresholdMinutes * 60);

            $builder->where('last_seen >=', $limit);
        }

        $hosts = $builder
            ->orderBy('port_ifindex', 'ASC')
            ->findAll();

        return $this->response->setJSON($hosts);
    }

    public function apiDiscoverRoom()
    {
        $roomId = session()->get('room_id');

        if (!$roomId) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Sala não identificada na sessão.',
            ])->setStatusCode(400);
        }

        $cmd = 'php ' . ROOTPATH . 'spark discover:hosts ' . (int) $roomId . ' 2>&1';
        $output = shell_exec($cmd);

        $this->logModel->insert([
            'schedule_id'  => null,
            'action'       => 'web-discover-room',
            'target_mac'   => null,
            'switch_ip'    => null,
            'port_ifindex' => null,
            'result'       => json_encode([
                'room_id' => $roomId,
                'command' => $cmd,
                'output'  => $output,
            ], JSON_UNESCAPED_UNICODE),
        ]);

        return $this->response->setJSON([
            'status'  => 'ok',
            'room_id' => $roomId,
            'output'  => $output,
        ]);
    }

    public function apiRefreshHosts()
    {
        $roomId = session()->get('room_id');

        if (!$roomId) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Sala não identificada na sessão.',
            ])->setStatusCode(400);
        }

        $cmd = 'php ' . ROOTPATH . 'spark discover:hosts ' . (int) $roomId . ' 2>&1';
        $output = shell_exec($cmd);

        $this->logModel->insert([
            'schedule_id'  => null,
            'action'       => 'web-refresh-hosts',
            'target_mac'   => null,
            'switch_ip'    => null,
            'port_ifindex' => null,
            'result'       => json_encode([
                'room_id' => $roomId,
                'command' => $cmd,
                'output'  => $output,
            ], JSON_UNESCAPED_UNICODE),
        ]);

        return $this->response->setJSON([
            'status'  => 'ok',
            'room_id' => $roomId,
            'output'  => $output,
        ]);
    }

    public function logs()
    {
        $logs = $this->logModel
            ->orderBy('created_at', 'DESC')
            ->limit(100)
            ->find();

        return view('access/logs', ['logs' => $logs]);
    }
}

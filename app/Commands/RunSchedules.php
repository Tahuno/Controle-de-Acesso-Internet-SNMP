<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Models\ScheduleModel;
use App\Models\HostModel;
use App\Models\SwitchModel;
use App\Models\ActionsLogModel;
use App\Libraries\SnmpService;
use CodeIgniter\I18n\Time;

class RunSchedules extends BaseCommand
{
    protected $group       = 'SNMP';
    protected $name        = 'run:schedules';

    public function run(array $params)
    {
        $now    = Time::now();
        $nowStr = $now->toDateTimeString();

        $scheduleModel = new ScheduleModel();
        $hostModel     = new HostModel();
        $switchModel   = new SwitchModel();
        $logModel      = new ActionsLogModel();

        /**
         * Iniciar agendamento
         */
        $toStart = $scheduleModel
            ->where('status', 'pending')
            ->where('start_at <=', $nowStr)
            ->findAll();

        if (empty($toStart)) {
            CLI::write('Nenhum agendamento pendente para iniciar.');
        } else {
            CLI::write('Agendamentos pendentes a iniciar: ' . count($toStart));
        }

        foreach ($toStart as $schedule) {
            $logModel->insert([
                'schedule_id'  => $schedule['id'],
                'action'       => 'schedule-start',
                'target_mac'   => $schedule['target_mac'] ?? null,
                'switch_ip'    => null,
                'port_ifindex' => null,
                'result'       => json_encode([
                    'room_id'  => $schedule['room_id'],
                    'start_at' => $schedule['start_at'],
                    'end_at'   => $schedule['end_at'],
                    'status'   => $schedule['status'],
                ], JSON_UNESCAPED_UNICODE),
            ]);

            $hasEnd = !empty($schedule['end_at']) && $schedule['end_at'] > $nowStr;

            if (!empty($schedule['target_mac'])) {
                // Agendamento para um MAC específico
                $this->processBlockSingle(
                    $schedule,
                    $hostModel,
                    $switchModel,
                    $logModel
                );
            } else {
                // Agendamento aplicado à sala inteira
                $this->processBlockRoom(
                    $schedule,
                    $hostModel,
                    $switchModel,
                    $logModel
                );
            }

            $newStatus = $hasEnd ? 'running' : 'finished';

            $scheduleModel->update($schedule['id'], [
                'status' => $newStatus,
            ]);

            $logModel->insert([
                'schedule_id'  => $schedule['id'],
                'action'       => 'schedule-status-update',
                'target_mac'   => $schedule['target_mac'] ?? null,
                'switch_ip'    => null,
                'port_ifindex' => null,
                'result'       => json_encode([
                    'room_id'   => $schedule['room_id'],
                    'start_at'  => $schedule['start_at'],
                    'end_at'    => $schedule['end_at'],
                    'newStatus' => $newStatus,
                ], JSON_UNESCAPED_UNICODE),
            ]);
        }

        /**
         * Finalizar agendamentos EM EXECUÇÃO
         */
        $toEnd = $scheduleModel
            ->where('status', 'running')
            ->where('end_at <=', $nowStr)
            ->findAll();

        if (empty($toEnd)) {
            CLI::write('Nenhum agendamento em execução para finalizar.');
        } else {
            CLI::write('Agendamentos em execução a finalizar: ' . count($toEnd));
        }

        foreach ($toEnd as $schedule) {
            CLI::write("Finalizando schedule #{$schedule['id']} (room_id={$schedule['room_id']}, target_mac={$schedule['target_mac']})");

            $logModel->insert([
                'schedule_id'  => $schedule['id'],
                'action'       => 'schedule-end-start',
                'target_mac'   => $schedule['target_mac'] ?? null,
                'switch_ip'    => null,
                'port_ifindex' => null,
                'result'       => json_encode([
                    'room_id'  => $schedule['room_id'],
                    'end_at'   => $schedule['end_at'],
                    'status'   => $schedule['status'],
                ], JSON_UNESCAPED_UNICODE),
            ]);

            if (!empty($schedule['target_mac'])) {
                // Agendamento para um MAC específico
                $this->processUnblockSingle(
                    $schedule,
                    $hostModel,
                    $switchModel,
                    $logModel
                );
            } else {
                // Agendamento aplicado à sala inteira
                $this->processUnblockRoom(
                    $schedule,
                    $hostModel,
                    $switchModel,
                    $logModel
                );
            }

            $scheduleModel->update($schedule['id'], [
                'status' => 'finished',
            ]);

            $logModel->insert([
                'schedule_id'  => $schedule['id'],
                'action'       => 'schedule-end-finished',
                'target_mac'   => $schedule['target_mac'] ?? null,
                'switch_ip'    => null,
                'port_ifindex' => null,
                'result'       => json_encode([
                    'room_id'  => $schedule['room_id'],
                    'end_at'   => $schedule['end_at'],
                    'status'   => 'finished',
                ], JSON_UNESCAPED_UNICODE),
            ]);
        }

        CLI::write("=== RunSchedules finalizado em " . date('Y-m-d H:i:s') . " ===");
    }

    /**
     * Bloqueia um único host (target_mac) de um schedule.
     */
    protected function processBlockSingle(
        array $schedule,
        HostModel $hostModel,
        SwitchModel $switchModel,
        ActionsLogModel $logModel
    ): void {
        $mac = $schedule['target_mac'];

        $host = $hostModel
            ->where('mac', $mac)
            ->first();

        if (!$host) {
            CLI::write("  [ERRO] Host com MAC {$mac} não encontrado.", 'red');
            $logModel->insert([
                'schedule_id'  => $schedule['id'],
                'action'       => 'schedule-block',
                'target_mac'   => $mac,
                'switch_ip'    => null,
                'port_ifindex' => null,
                'result'       => json_encode(['success' => false, 'error' => 'Host não encontrado'], JSON_UNESCAPED_UNICODE),
            ]);
            return;
        }

        // Evita bloquear máquina protegida
        if (!empty($host['is_protected'])) {
            CLI::write("  [INFO] Host {$mac} é protegido. Nenhum bloqueio aplicado.", 'yellow');
            $logModel->insert([
                'schedule_id'  => $schedule['id'],
                'action'       => 'schedule-block-skipped-protected',
                'target_mac'   => $mac,
                'switch_ip'    => null,
                'port_ifindex' => $host['port_ifindex'] ?? null,
                'result'       => json_encode(['success' => false, 'error' => 'Host protegido'], JSON_UNESCAPED_UNICODE),
            ]);
            return;
        }

        $sw = $switchModel->find($host['switch_id'] ?? 0);
        if (!$sw) {
            CLI::write("  [ERRO] Switch para host {$mac} não encontrado.", 'red');
            $logModel->insert([
                'schedule_id'  => $schedule['id'],
                'action'       => 'schedule-block',
                'target_mac'   => $mac,
                'switch_ip'    => null,
                'port_ifindex' => $host['port_ifindex'] ?? null,
                'result'       => json_encode(['success' => false, 'error' => 'Switch não encontrado'], JSON_UNESCAPED_UNICODE),
            ]);
            return;
        }

        $snmp = new SnmpService($sw);
        $res  = $snmp->blockMac($mac);

        CLI::write("  [OK] BLOQUEIO {$mac} => " . json_encode($res));

        $logModel->insert([
            'schedule_id'  => $schedule['id'],
            'action'       => 'schedule-block',
            'target_mac'   => $mac,
            'switch_ip'    => $sw['ip'] ?? null,
            'port_ifindex' => $res['ifIndex'] ?? ($host['port_ifindex'] ?? null),
            'result'       => json_encode($res, JSON_UNESCAPED_UNICODE),
        ]);
    }

    /**
     * Bloqueia todos os hosts de uma sala, exceto protegidos.
     */
    protected function processBlockRoom(
        array $schedule,
        HostModel $hostModel,
        SwitchModel $switchModel,
        ActionsLogModel $logModel
    ): void {
        $roomId = $schedule['room_id'];

        $hosts = $hostModel
            ->where('room_id', $roomId)
            ->where('is_protected', false)
            ->findAll();

        if (empty($hosts)) {
            CLI::write("  [INFO] Nenhum host elegível para bloqueio na sala {$roomId}.", 'yellow');
            return;
        }

        foreach ($hosts as $host) {
            $mac = $host['mac'] ?? '';
            $sw  = $switchModel->find($host['switch_id'] ?? 0);

            if (!$sw) {
                CLI::write("  [ERRO] Switch para host {$mac} não encontrado.", 'red');
                $logModel->insert([
                    'schedule_id'  => $schedule['id'],
                    'action'       => 'schedule-room-block',
                    'target_mac'   => $mac,
                    'switch_ip'    => null,
                    'port_ifindex' => $host['port_ifindex'] ?? null,
                    'result'       => json_encode(['success' => false, 'error' => 'Switch não encontrado'], JSON_UNESCAPED_UNICODE),
                ]);
                continue;
            }

            $snmp = new SnmpService($sw);
            $res  = $snmp->blockMac($mac);

            CLI::write("  [OK] BLOQUEIO (sala {$roomId}) {$mac} => " . json_encode($res));

            $logModel->insert([
                'schedule_id'  => $schedule['id'],
                'action'       => 'schedule-room-block',
                'target_mac'   => $mac,
                'switch_ip'    => $sw['ip'] ?? null,
                'port_ifindex' => $res['ifIndex'] ?? ($host['port_ifindex'] ?? null),
                'result'       => json_encode($res, JSON_UNESCAPED_UNICODE),
            ]);
        }
    }

    /**
     * Desbloqueia um único host (target_mac) de um schedule.
     */
    protected function processUnblockSingle(
        array $schedule,
        HostModel $hostModel,
        SwitchModel $switchModel,
        ActionsLogModel $logModel
    ): void {
        $mac = $schedule['target_mac'];

        $host = $hostModel
            ->where('mac', $mac)
            ->first();

        if (!$host) {
            CLI::write("  [ERRO] Host com MAC {$mac} não encontrado para desbloqueio.", 'red');
            $logModel->insert([
                'schedule_id'  => $schedule['id'],
                'action'       => 'schedule-unblock',
                'target_mac'   => $mac,
                'switch_ip'    => null,
                'port_ifindex' => null,
                'result'       => json_encode(['success' => false, 'error' => 'Host não encontrado'], JSON_UNESCAPED_UNICODE),
            ]);
            return;
        }

        $sw = $switchModel->find($host['switch_id'] ?? 0);
        if (!$sw) {
            CLI::write("  [ERRO] Switch para host {$mac} não encontrado para desbloqueio.", 'red');
            $logModel->insert([
                'schedule_id'  => $schedule['id'],
                'action'       => 'schedule-unblock',
                'target_mac'   => $mac,
                'switch_ip'    => null,
                'port_ifindex' => $host['port_ifindex'] ?? null,
                'result'       => json_encode(['success' => false, 'error' => 'Switch não encontrado'], JSON_UNESCAPED_UNICODE),
            ]);
            return;
        }

        $snmp = new SnmpService($sw);
        $res  = $snmp->unblockMac($mac);

        CLI::write("  [OK] DESBLOQUEIO {$mac} => " . json_encode($res));

        $logModel->insert([
            'schedule_id'  => $schedule['id'],
            'action'       => 'schedule-unblock',
            'target_mac'   => $mac,
            'switch_ip'    => $sw['ip'] ?? null,
            'port_ifindex' => $res['ifIndex'] ?? ($host['port_ifindex'] ?? null),
            'result'       => json_encode($res, JSON_UNESCAPED_UNICODE),
        ]);
    }

    /**
     * Desbloqueia todos os hosts de uma sala, exceto protegidos.
     */
    protected function processUnblockRoom(
        array $schedule,
        HostModel $hostModel,
        SwitchModel $switchModel,
        ActionsLogModel $logModel
    ): void {
        $roomId = $schedule['room_id'];

        $hosts = $hostModel
            ->where('room_id', $roomId)
            ->where('is_protected', false)
            ->findAll();

        if (empty($hosts)) {
            CLI::write("  [INFO] Nenhum host elegível para desbloqueio na sala {$roomId}.", 'yellow');
            return;
        }

        foreach ($hosts as $host) {
            $mac = $host['mac'] ?? '';
            $sw  = $switchModel->find($host['switch_id'] ?? 0);

            if (!$sw) {
                CLI::write("  [ERRO] Switch para host {$mac} não encontrado.", 'red');
                $logModel->insert([
                    'schedule_id'  => $schedule['id'],
                    'action'       => 'schedule-room-unblock',
                    'target_mac'   => $mac,
                    'switch_ip'    => null,
                    'port_ifindex' => $host['port_ifindex'] ?? null,
                    'result'       => json_encode(['success' => false, 'error' => 'Switch não encontrado'], JSON_UNESCAPED_UNICODE),
                ]);
                continue;
            }

            $snmp = new SnmpService($sw);
            $res  = $snmp->unblockMac($mac);

            CLI::write("  [OK] DESBLOQUEIO (sala {$roomId}) {$mac} => " . json_encode($res));

            $logModel->insert([
                'schedule_id'  => $schedule['id'],
                'action'       => 'schedule-room-unblock',
                'target_mac'   => $mac,
                'switch_ip'    => $sw['ip'] ?? null,
                'port_ifindex' => $res['ifIndex'] ?? ($host['port_ifindex'] ?? null),
                'result'       => json_encode($res, JSON_UNESCAPED_UNICODE),
            ]);
        }
    }
}

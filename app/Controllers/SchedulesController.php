<?php

namespace App\Controllers;

use App\Models\ScheduleModel;
use App\Models\HostModel;

class SchedulesController extends BaseController
{
    protected ScheduleModel $scheduleModel;
    protected HostModel $hostModel;

    public function __construct()
    {
        $this->scheduleModel = new ScheduleModel();
        $this->hostModel     = new HostModel();
    }

    public function index()
    {
        $roomId = session()->get('room_id');

        $builder = $this->scheduleModel->orderBy('start_at', 'DESC');

        if ($roomId) {
            $builder->where('room_id', $roomId);
        }

        $schedules = $builder->findAll(50);

        return view('schedules/index', [
            'schedules' => $schedules,
            'roomId'    => $roomId,
            'message'   => session()->getFlashdata('message'),
        ]);
    }

    public function new()
    {
        $roomId = session()->get('room_id');
        $hosts  = [];

        if ($roomId) {
            $hosts = $this->hostModel
                ->where('room_id', $roomId)
                ->orderBy('hostname', 'ASC')
                ->findAll();
        }

        if (strtolower($this->request->getMethod()) === 'post') {

            $type  = $this->request->getPost('type') ?: 'room';
            $start = $this->request->getPost('start_at');
            $end   = $this->request->getPost('end_at') ?: null;

            if (!$start) {
                return view('schedules/new', [
                    'hosts' => $hosts,
                    'error' => 'Informe a data/hora de início.',
                    'old'   => $this->request->getPost(),
                ]);
            }

            $targetMac      = null;
            $roomForSchedule = $roomId;

            if ($type === 'host') {
                $targetMac = trim((string) $this->request->getPost('target_mac'));

                if (!$targetMac) {
                    return view('schedules/new', [
                        'hosts' => $hosts,
                        'error' => 'Selecione um host para agendar.',
                        'old'   => $this->request->getPost(),
                    ]);
                }

                $host = $this->hostModel
                    ->where('mac', $targetMac)
                    ->first();

                if (!$host) {
                    return view('schedules/new', [
                        'hosts' => $hosts,
                        'error' => 'O host selecionado não foi encontrado no cadastro.',
                        'old'   => $this->request->getPost(),
                    ]);
                }

                $roomForSchedule = (int) ($host['room_id'] ?? $roomId);

                if (!$roomForSchedule) {
                    return view('schedules/new', [
                        'hosts' => $hosts,
                        'error' => 'Host selecionado não está associado a nenhuma sala.',
                        'old'   => $this->request->getPost(),
                    ]);
                }
            }

            $this->scheduleModel->insert([
                'requested_by' => session()->get('username') ?? 'admin',
                'room_id'      => $roomForSchedule,
                'target_mac'   => $targetMac,
                'start_at'     => $start,
                'end_at'       => $end,
                'status'       => 'pending',
            ]);

            return redirect()
                ->to('/schedules')
                ->with('message', 'Agendamento criado com sucesso.');
        }

        return view('schedules/new', [
            'hosts' => $hosts,
            'old'   => [],
        ]);
    }
}

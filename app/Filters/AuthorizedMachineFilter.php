<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use App\Models\HostModel;

class AuthorizedMachineFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (ENVIRONMENT === 'development') {
            $session = session();
            if (!$session->get('isLoggedIn')) {
                return redirect()->to('/login');
            }

            if (!$session->get('room_id')) {
                $session->set('room_id', 1);
            }

            return;
        }

        if (is_cli()) {
            return;
        }

        $requesterIp = $request->getIPAddress();
        // var_dump($requesterIp);
        // die();

        $hostModel = new HostModel();

        // Verifica se o IP que faz a requisição pertence a uma máquina autorizada no DB
        $authorizedHost = $hostModel
            ->where('ip', $requesterIp)
            ->where('is_authorized_machine', 1)
            ->first();

        if (!$authorizedHost) {
            return service('response')
                ->setStatusCode(403)
                ->setBody('Acesso negado. Esta estação não está autorizada.');
        }

        $session = session();
        if (empty($session->get('room_id')) && !empty($authorizedHost['room_id'])) {
            $session->set('room_id', $authorizedHost['room_id']);
        }

        if (empty($session->get('host_id'))) {
            $session->set('host_id', $authorizedHost['id'] ?? null);
        }

        if (!$session->get('isLoggedIn')) {
            return redirect()->to('/login');
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return;
    }
}

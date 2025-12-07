<?php

namespace App\Libraries;

use App\Models\HostModel;

class SnmpService
{
    protected array $switch;
    protected int $timeout = 1000000;
    protected int $retries = 1;

    public function __construct(array $switch)
    {
        $this->switch = $switch;
    }

    /**
     * Verifica se este switch está em modo "mock" para testes.
     */
    protected function isMock(): bool
    {
        return isset($this->switch['snmp_version'])
            && $this->switch['snmp_version'] === 'mock';
    }

    /**
     * Cria uma sessão SNMP.
     */
    protected function createSession(): ?\SNMP
    {
        if ($this->isMock()) {
            return null;
        }

        if (!class_exists('\SNMP')) {
            throw new \RuntimeException('PHP SNMP extension is not loaded.');
        }

        $ip = $this->switch['ip'] ?? '127.0.0.1';

        $versionStr = strtolower(trim((string)($this->switch['snmp_version'] ?? 'v1')));

        switch ($versionStr) {
            case 'v1':
            case '1':
                $versionConst = \SNMP::VERSION_1;
                break;

            case 'v2c':
            case '2c':
            case '2':
            default:
                $versionConst = \SNMP::VERSION_2c;
                break;
        }

        $community = $this->switch['community_rw']
            ?? $this->switch['snmp_community']
            ?? 'private';

        $session = @new \SNMP(
            $versionConst,
            $ip,
            $community,
            $this->timeout,
            $this->retries
        );

        if (!$session) {
            return null;
        }

        // Evita o problema do SNMP::VALUE_PLAIN
        if (defined('SNMP_VALUE_PLAIN')) {
            $session->valueretrieval = SNMP_VALUE_PLAIN;
        }

        return $session;
    }

    /**
     * SNMP WALK
     */
    public function walk(string $oid): array
    {
        if ($this->isMock()) {
            return [];
        }

        $session = $this->createSession();
        if ($session === null) {
            return [];
        }

        $result = @$session->walk($oid);
        $session->close();

        if ($result === false) {
            return [];
        }

        return $result;
    }

    /**
     * SNMP GET
     */
    public function get(string $oid)
    {
        if ($this->isMock()) {
            return null;
        }

        $session = $this->createSession();
        if ($session === null) {
            return null;
        }

        $result = @$session->get($oid);
        $session->close();

        return $result;
    }

    /**
     * Habilita ou desabilita uma porta (ifAdminStatus).
     */
    public function setPortState(int $ifIndex, bool $enable): array
    {
        $oid   = "1.3.6.1.2.1.2.2.1.7.$ifIndex"; // ifAdminStatus
        $value = $enable ? 1 : 2;

        if ($this->isMock()) {
            return [
                'success' => true,
                'ifIndex' => $ifIndex,
                'value'   => $value,
                'raw'     => 'mock',
            ];
        }

        $session = $this->createSession();
        if ($session === null) {
            return [
                'success' => false,
                'ifIndex' => $ifIndex,
                'value'   => $value,
                'raw'     => 'SNMP não pode ser criada',
            ];
        }

        $result = @$session->set($oid, 'i', $value);
        $errno  = $session->getErrno();
        $error  = $session->getError();
        $session->close();

        return [
            'success' => $result !== false,
            'ifIndex' => $ifIndex,
            'value'   => $value,
            'raw'     => $result,
            'errno'   => $errno,
            'error'   => $error,
        ];
    }

    /**
     * Consulta ifAdminStatus/ifOperStatus de uma porta.
     */
    public function getPortStatus(int $ifIndex): array
    {
        $oidAdmin = "1.3.6.1.2.1.2.2.1.7.$ifIndex"; // ifAdminStatus
        $oidOper  = "1.3.6.1.2.1.2.2.1.8.$ifIndex"; // ifOperStatus

        if ($this->isMock()) {
            return [
                'ifIndex'       => $ifIndex,
                'ifAdminStatus' => 1,
                'ifOperStatus'  => 1,
            ];
        }

        $session = $this->createSession();
        if ($session === null) {
            return [
                'ifIndex'       => $ifIndex,
                'ifAdminStatus' => 0,
                'ifOperStatus'  => 0,
            ];
        }

        $admin = @$session->get($oidAdmin);
        $oper  = @$session->get($oidOper);
        $session->close();

        return [
            'ifIndex'       => $ifIndex,
            'ifAdminStatus' => (int) $admin,
            'ifOperStatus'  => (int) $oper,
        ];
    }

    /**
     * Bloqueia um host a partir do MAC
     */
    public function blockMac(string $mac): array
    {
        $hostModel = new HostModel();
        $host      = $hostModel->where('mac', $mac)->first();

        if (!$host) {
            return [
                'success' => false,
                'error'   => 'Host não encontrado no BD',
            ];
        }

        $ifIndex = $host['port_ifindex'] ?? null;
        if (!$ifIndex) {
            return [
                'success' => false,
                'error'   => 'Host não possui port_ifindex',
            ];
        }

        $res = $this->setPortState((int) $ifIndex, false);

        if (!empty($res['success'])) {
            $hostModel->update($host['id'], [
                'is_blocked' => 1,
            ]);
        }

        return $res;
    }

    public function unblockMac(string $mac): array
    {
        $hostModel = new HostModel();
        $host      = $hostModel->where('mac', $mac)->first();

        if (!$host) {
            return [
                'success' => false,
                'error'   => 'Host não encontrado no BD',
            ];
        }

        $ifIndex = $host['port_ifindex'] ?? null;
        if (!$ifIndex) {
            return [
                'success' => false,
                'error'   => 'Host não possui port_ifindex',
            ];
        }

        $res = $this->setPortState((int) $ifIndex, true);

        if (!empty($res['success'])) {
            $hostModel->update($host['id'], [
                'is_blocked' => 0,
            ]);
        }

        return $res;
    }
}

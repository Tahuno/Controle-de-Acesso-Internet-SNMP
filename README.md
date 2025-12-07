# SNMP Access Control – Sistema de Controle de Acesso à Internet por Switch

Aplicação web desenvolvida como trabalho de disciplina de Gerência e Mobilidade em Redes em um ambiente de laboratório, utilizando SNMP para bloquear e liberar portas de switch de forma centralizada.

O sistema permite que o professor/gerente de sala:

- Descubra automaticamente os hosts conectados aos switches da sala.
- Bloqueie/libere máquinas específicas.
- Bloqueie toda a sala (exceto portas protegidas).
- Agende bloqueios e liberações por horário.
- Registre todas as ações em um log de auditoria.

## Stack

- **Linguagem:** PHP
- **Framework:** CodeIgniter 4
- **Banco de dados:** MariaDB / MySQL
- **Infra:** Docker + Docker Compose
- **Protocolo de gerenciamento:** SNMP (v1/v2c)
- **Outros:**
  - phpMyAdmin para administração do banco
  - Cron (Linux) para execução periódica de agendamentos

---

## Funcionalidades

### 1. Descoberta de hosts via SNMP

- Comando CLI `discover:hosts` integrado à aplicação (CodeIgniter Command).
- A aplicação faz **walk** na tabela de ponte (FDB) do switch:
  - Obtém MACs,
  - Mapeia MAC → porta (bridgePort → ifIndex),
  - Descobre descrição da porta (`ifDescr`),
  - Popula/atualiza a tabela `hosts`.

### 2. Dashboard de Hosts (interface web)

Tela principal exibe, para a sala atual:

- **MAC / IP** do host
- **Nome do host** (quando cadastrado)
- **Switch** ao qual está conectado
- **Porta** (descrição + `ifIndex`)
- **Estado**: 
  - Liberado
  - Bloqueado
  - Desconhecido (quando não é possível determinar)
- **Last seen**: última vez visto via SNMP
- Ações:
  - **Bloquear** host
  - **Liberar** host
  - **Bloquear sala** (em massa, exceto portas protegidas)
  - **Descobrir sala**
  - **Atualizar hosts**

### 3. Bloqueio e liberação de portas

- Utiliza SNMP para alterar `ifAdminStatus` da porta:
  - `up` → porta ativa (host liberado)
  - `down` → porta desativada (host bloqueado)
- A aplicação:
  - Localiza switch + `port_ifindex` a partir do MAC,
  - Envia o comando SNMP,
  - Atualiza o estado no banco,
  - Registra a ação em `actions_log`.

### 4. Agendamentos (schedules)

- Tela para criação de agendamentos:
  - Bloquear/liberar **host específico** (`target_mac`) ou **sala inteira**.
  - Definição de `start_at` e, opcionalmente, `end_at`.
- Estados do agendamento:
  - `pending` → aguardando início.
  - `running` → em execução (janela ativa).
  - `finished` → já executado (bloqueio e liberação, se houver).
- Comando CLI periódicamente executado:
  - `php spark run:schedules`
- Responsável por:
  - Iniciar bloqueios quando `now >= start_at`.
  - Liberar (se configurado) quando `now >= end_at`.
  - Atualizar status e registrar logs detalhados.

### 5. Logs de ações (`actions_log`)

- Toda ação significativa gera um registro:
  - `schedule_id` (se originado de agendamento).
  - `action` (`manual-block`, `manual-unblock`, `schedule-block-host`, `schedule-unblock-room`, etc.).
  - `target_mac`, `switch_ip`, `port_ifindex`.
    
---

## Como executar com Docker

### Pré-requisitos

- Docker
- Docker Compose

### Passos

```bash
1. Clonar o repositório
git clone https://github.com/seu-usuario/Controle-de-Acesso-Internet-SNMP.git
cd Controle-de-Acesso-Internet-SNMP

2. Subir a stack

    docker compose up -d --build

Isso iniciará:

- `app` em `http://localhost:8080`
- `phpMyAdmin` em `http://localhost:8081`
- `db` (MariaDB) ligado à aplicação.

3. Criar o banco de dados

A stack já cria o banco `snmpdb` via Docker.  
Em seguida, importe o schema:

Via `mysql` dentro do container `db`:

    docker compose exec db mysql -u root -p snmpdb < sql/01_schema.sql

4. Acessar a aplicação

No navegador, acesse:

    http://localhost:8080

Usuário/senha de teste:

- Usuário: `admin`
- Senha: `admin123`

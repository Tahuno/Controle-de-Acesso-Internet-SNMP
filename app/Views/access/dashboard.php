<!doctype html>
<html lang="pt-br">

<head>
    <meta charset="utf-8">
    <title>SNMP Access Dashboard</title>
    <style>
        body {
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            margin: 20px;
            background: #f5f5f5;
        }

        h1 {
            margin-bottom: 0.2rem;
        }

        .subtitle {
            color: #666;
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }

        .toolbar {
            margin-bottom: 1rem;
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }

        button {
            padding: 6px 12px;
            border-radius: 4px;
            border: 1px solid #ccc;
            cursor: pointer;
            background: #fff;
        }

        button.primary {
            background: #1976d2;
            color: #fff;
            border-color: #135ba1;
        }

        button.danger {
            background: #d32f2f;
            color: #fff;
            border-color: #9a2424;
        }

        button:disabled {
            opacity: 0.6;
            cursor: default;
        }

        table {
            border-collapse: collapse;
            width: 100%;
            background: #fff;
        }

        td,
        th {
            border: 1px solid #ddd;
            padding: 8px;
            font-size: 0.9rem;
        }

        th {
            background: #f0f0f0;
            text-align: left;
        }

        tr:nth-child(even) {
            background: #fafafa;
        }

        tr.blocked {
            background: #ffe5e5;
        }

        .status {
            font-size: 0.8rem;
            padding: 2px 6px;
            border-radius: 4px;
            display: inline-block;
        }

        .status-ok {
            background: #e0f2f1;
            color: #00695c;
        }

        .status-unknown {
            background: #eeeeee;
            color: #616161;
        }

        #message {
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        #message.success {
            color: #2e7d32;
        }

        #message.error {
            color: #c62828;
        }

        small {
            color: #777;
        }

        .status-pill {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status-allowed {
            background: #e0f7e9;
            color: #2e7d32;
        }

        .status-blocked {
            background: #ffebee;
            color: #c62828;
        }

        .status-unknown {
            background: #eeeeee;
            color: #555;
        }

        tr.blocked-row {
            background-color: #ffebee33;
        }
    </style>
</head>

<body>

    <h1>Painel de Controle SNMP</h1>
    <div class="subtitle">
        Controle de acesso à Internet por máquina (MAC) na sala atual.
    </div>

    <div id="message"></div>

    <div class="toolbar">
        <a href="/logs">
            <button class="primary">Ver logs</button>
        </a>
        <a href="/schedules">
            <button class="primary">Agendamentos</button>
        </a>
        <button id="btn-discover-room" class="primary">Descobrir sala</button>
        <button id="btn-refresh" class="primary">Atualizar hosts</button>
        <button id="btn-block-room" class="danger">Bloquear sala inteira</button>
        <small>(exceto portas protegidas / máquina do professor)</small>
    </div>

    <table id="hosts-table">
        <thead>
            <tr>
                <th>MAC / IP</th>
                <th>Host</th>
                <th>Switch</th>
                <th>Porta</th>
                <th>Última vez visto</th>
                <th>Ações</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($hosts)): ?>
                <?php foreach ($hosts as $h): ?>
                    <?php
                    $isProtected = !empty($h['is_protected']) || !empty($h['is_authorized_machine']);
                    ?>
                    <tr data-mac="<?= esc($h['mac']) ?>" class="<?= $isProtected ? 'protected' : '' ?>">
                        <td><?= esc($h['mac']) ?></td>
                        <td>
                            <?= esc($h['hostname'] ?? '') ?>
                            <?php if (!empty($h['is_authorized_machine'])): ?>
                                <small>(máquina do professor)</small>
                            <?php endif; ?>
                            <?php if (!empty($h['is_protected'])): ?>
                                <small>(protegido)</small>
                            <?php endif; ?>
                        </td>
                        <td><?= esc($h['switch_id'] ?? '') ?></td>
                        <td><?= esc($h['port_descr'] ?? '') ?> (ifIndex: <?= esc($h['port_ifindex'] ?? '') ?>)</td>
                        <td><?= esc($h['last_seen'] ?? '') ?></td>
                        <td>
                            <?php if ($isProtected): ?>
                                <em>Porta protegida</em>
                            <?php else: ?>
                                <button class="btn-block danger">Bloquear</button>
                                <button class="btn-unblock">Liberar</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6">Nenhum host encontrado para esta sala.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <script>
        const API_BLOCK = '/api/block';
        const API_UNBLOCK = '/api/unblock';
        const API_HOSTS = '/api/hosts';
        const API_BLOCK_ROOM = '/api/block-room';
        const API_DISCOVER_ROOM = '/api/discover-room';
        const API_REFRESH_HOSTS = '/api/refresh-hosts';

        const msgBox = document.getElementById('message');
        const tableBody = document.querySelector('#hosts-table tbody');
        const btnRefresh = document.getElementById('btn-refresh');
        const btnBlockRoom = document.getElementById('btn-block-room');
        const btnDiscover = document.getElementById('btn-discover-room');

        function showMessage(text, type = 'success') {
            if (!msgBox) return;
            msgBox.textContent = text || '';
            msgBox.className = '';
            if (text) {
                msgBox.classList.add(type);
            }
        }

        async function post(url, body) {
            try {
                const resp = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(body || {})
                });

                let data;
                try {
                    data = await resp.json();
                } catch (e) {
                    data = {
                        error: 'Resposta inválida do servidor'
                    };
                }

                if (!resp.ok) {
                    const errMsg = (data && data.error) ? data.error : ('Erro HTTP ' + resp.status);
                    throw new Error(errMsg);
                }

                return data;
            } catch (err) {
                console.error(err);
                showMessage(err.message, 'error');
                throw err;
            }
        }

        function renderHosts(data) {
            tableBody.innerHTML = '';

            if (!Array.isArray(data) || data.length === 0) {
                const tr = document.createElement('tr');
                tr.innerHTML = '<td colspan="7">Nenhum host encontrado para esta sala.</td>';
                tableBody.appendChild(tr);
                return;
            }

            data.forEach(h => {
                const tr = document.createElement('tr');
                tr.dataset.mac = h.mac;

                const isBlocked = (h.is_blocked === true || h.is_blocked === 1 || h.is_blocked === '1');

                let statusLabel = 'Desconhecido';
                let statusClass = 'status-unknown';

                if (h.is_blocked === null || h.is_blocked === undefined) {
                    statusLabel = 'Desconhecido';
                    statusClass = 'status-unknown';
                } else if (isBlocked) {
                    statusLabel = 'Bloqueado';
                    statusClass = 'status-blocked';
                } else {
                    statusLabel = 'Liberado';
                    statusClass = 'status-allowed';
                }

                if (isBlocked) {
                    tr.classList.add('blocked-row');
                }

                tr.innerHTML = `
            <td>
                ${h.mac || ''}<br>
                <small>${h.ip || ''}</small>
            </td>
            <td>${h.hostname || ''}</td>
            <td>${h.switch_id || ''}</td>
            <td>${(h.port_descr || '')} (ifIndex: ${h.port_ifindex || ''})</td>
            <td>${h.last_seen || ''}</td>
            <td>
            <button class="btn-block danger">Bloquear</button>
            <button class="btn-unblock">Liberar</button>
            </td>
            <td>
                <span class="status-pill ${statusClass}">${statusLabel}</span>
            </td>
        `;

                tableBody.appendChild(tr);
            });

            attachRowEvents();
        }

        function attachRowEvents() {
            document.querySelectorAll('.btn-block').forEach(btn => {
                btn.onclick = async (ev) => {
                    const mac = ev.target.closest('tr').dataset.mac;
                    if (!mac) return;

                    if (!confirm('Bloquear o host ' + mac + '?')) {
                        return;
                    }

                    try {
                        const res = await post(API_BLOCK, {
                            mac
                        });
                        showMessage('Host ' + mac + ' bloqueado. Sucesso: ' + (res.success !== false), 'success');
                        await fetchHosts();
                    } catch (err) {}
                };
            });

            document.querySelectorAll('.btn-unblock').forEach(btn => {
                btn.onclick = async (ev) => {
                    const mac = ev.target.closest('tr').dataset.mac;
                    if (!mac) return;

                    if (!confirm('Liberar o host ' + mac + '?')) {
                        return;
                    }

                    try {
                        const res = await post(API_UNBLOCK, {
                            mac
                        });
                        showMessage('Host ' + mac + ' liberado. Sucesso: ' + (res.success !== false), 'success');
                        await fetchHosts();
                    } catch (err) {}
                };
            });
        }

        async function fetchHosts() {
            try {
                const resp = await fetch(API_HOSTS);
                const data = await resp.json();
                renderHosts(data);
            } catch (err) {
                console.error(err);
                showMessage('Erro ao carregar hosts.', 'error');
            }
        }

        if (btnDiscover) {
            btnDiscover.addEventListener('click', async () => {
                showMessage('Descobrindo hosts da sala...', 'info');
                try {
                    const r = await post(API_DISCOVER_ROOM, {});
                    if (r.status === 'ok') {
                        showMessage('Descoberta concluída para sala ' + r.room_id, 'success');
                        console.log('Output discover-room:', r.output);
                        await fetchHosts();
                    } else {
                        showMessage(r.message || 'Falha na descoberta da sala.', 'error');
                    }
                } catch (e) {
                    console.error(e);
                    showMessage(e.message || 'Erro ao comunicar com o servidor.', 'error');
                }
            });
        }

        if (btnRefresh) {
            btnRefresh.addEventListener('click', async () => {
                showMessage('Atualizando hosts via SNMP...', 'info');
                try {
                    const r = await post(API_REFRESH_HOSTS, {});
                    if (r.status === 'ok') {
                        showMessage('Hosts atualizados para sala ' + r.room_id, 'success');
                        console.log('Output refresh-hosts:', r.output);
                        await fetchHosts();
                    } else {
                        showMessage(r.message || 'Falha ao atualizar hosts.', 'error');
                    }
                } catch (e) {
                    console.error(e);
                    showMessage(e.message || 'Erro ao comunicar com o servidor.', 'error');
                }
            });
        }

        if (btnBlockRoom) {
            btnBlockRoom.addEventListener('click', async () => {
                if (!confirm('Deseja bloquear todas as máquinas da sala (exceto portas protegidas)?')) {
                    return;
                }

                btnBlockRoom.disabled = true;
                showMessage('Aplicando bloqueio na sala...', 'info');

                try {
                    const res = await post(API_BLOCK_ROOM, {});
                    if (res && res.status) {
                        showMessage('Bloqueio da sala concluído: ' + res.status, 'success');
                    } else {
                        showMessage('Bloqueio da sala finalizado.', 'success');
                    }
                    await fetchHosts();
                } catch (err) {} finally {
                    btnBlockRoom.disabled = false;
                }
            });
        }

        fetchHosts();

        // Polling: atualiza a cada 30s
        setInterval(fetchHosts, 30000);
    </script>

</body>

</html>
<!doctype html>
<html lang="pt-br">

<head>
    <meta charset="utf-8">
    <title>Agendamentos de Acesso</title>
    <style>
        body {
            font-family: system-ui, sans-serif;
            margin: 20px;
        }

        table {
            border-collapse: collapse;
            width: 100%;
            background: #fff;
        }

        th,
        td {
            border: 1px solid #ddd;
            padding: 6px 8px;
            font-size: 0.9rem;
        }

        th {
            background: #f0f0f0;
            text-align: left;
        }

        tr:nth-child(even) {
            background: #fafafa;
        }

        .toolbar {
            margin-bottom: 1rem;
            display: flex;
            gap: .5rem;
            align-items: center;
        }

        a.button {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 4px;
            border: 1px solid #1976d2;
            color: #fff;
            background: #1976d2;
            text-decoration: none;
            font-size: 0.9rem;
        }

        .tag {
            font-size: .75rem;
            padding: 2px 6px;
            border-radius: 4px;
            background: #eee;
        }

        .status-pending {
            color: #c47f00;
        }

        .status-running {
            color: #1976d2;
        }

        .status-finished {
            color: #2e7d32;
        }

        .status-canceled {
            color: #c62828;
        }

        .msg {
            margin-bottom: .5rem;
            font-size: .9rem;
            color: #2e7d32;
        }
    </style>
</head>

<body>

    <h1>Agendamentos de Acesso</h1>
    <div class="toolbar">
        <a href="/" class="button" style="background:#555;border-color:#555;">← Voltar ao dashboard</a>
        <a href="/schedules/new" class="button">+ Novo agendamento</a>
    </div>

    <?php if (!empty($message)): ?>
        <div class="msg"><?= esc($message) ?></div>
    <?php endif; ?>

    <?php if (empty($schedules)): ?>
        <p>Nenhum agendamento cadastrado para esta sala.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Tipo</th>
                    <th>Alvo</th>
                    <th>Início</th>
                    <th>Fim</th>
                    <th>Status</th>
                    <th>Solicitado por</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($schedules as $s): ?>
                    <?php
                    $tipo = $s['target_mac'] ? 'Host' : 'Sala';
                    $statusClass = 'status-' . ($s['status'] ?? 'pending');
                    ?>
                    <tr>
                        <td><?= esc($s['id']) ?></td>
                        <td><span class="tag"><?= esc($tipo) ?></span></td>
                        <td>
                            <?php if ($s['target_mac']): ?>
                                MAC: <?= esc($s['target_mac']) ?>
                            <?php else: ?>
                                Sala ID: <?= esc($s['room_id'] ?? '-') ?>
                            <?php endif; ?>
                        </td>
                        <td><?= esc($s['start_at']) ?></td>
                        <td><?= esc($s['end_at'] ?? '') ?></td>
                        <td class="<?= $statusClass ?>"><?= esc($s['status']) ?></td>
                        <td><?= esc($s['requested_by'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

</body>

</html>
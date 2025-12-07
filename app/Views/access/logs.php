<!doctype html>
<html lang="pt-br">

<head>
    <meta charset="utf-8">
    <title>Logs de Ações SNMP</title>
    <style>
        body {
            font-family: system-ui, sans-serif;
            margin: 20px;
        }

        table {
            border-collapse: collapse;
            width: 100%;
        }

        th,
        td {
            border: 1px solid #ddd;
            padding: 6px 8px;
            font-size: 0.85rem;
        }

        th {
            background: #f0f0f0;
            text-align: left;
        }

        tr:nth-child(even) {
            background: #fafafa;
        }

        pre {
            margin: 0;
            white-space: pre-wrap;
            word-wrap: break-word;
        }

        a {
            text-decoration: none;
            color: #1976d2;
        }
    </style>
</head>

<body>
    <h1>Logs de Ações SNMP</h1>
    <p><a href="/">← Voltar ao dashboard</a></p>

    <?php if (empty($logs)): ?>
        <p>Nenhum log registrado.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Data/Hora</th>
                    <th>Ação</th>
                    <th>MAC</th>
                    <th>Switch IP</th>
                    <th>IfIndex</th>
                    <th>Resultado</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?= esc($log['created_at'] ?? '') ?></td>
                        <td><?= esc($log['action'] ?? '') ?></td>
                        <td><?= esc($log['target_mac'] ?? '') ?></td>
                        <td><?= esc($log['switch_ip'] ?? '') ?></td>
                        <td><?= esc($log['port_ifindex'] ?? '') ?></td>
                        <td>
                            <pre><?= esc($log['result'] ?? '') ?></pre>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</body>

</html>
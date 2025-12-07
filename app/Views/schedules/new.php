<!doctype html>
<html lang="pt-br">

<head>
    <meta charset="utf-8">
    <title>Novo Agendamento</title>
    <style>
        body {
            font-family: system-ui, sans-serif;
            margin: 20px;
        }

        form {
            max-width: 480px;
            background: #fff;
            padding: 16px;
            border-radius: 6px;
            border: 1px solid #ddd;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-size: 0.9rem;
        }

        input,
        select {
            width: 100%;
            padding: 6px 8px;
            margin-top: 4px;
            box-sizing: border-box;
        }

        .row {
            margin-bottom: 10px;
        }

        button {
            padding: 6px 12px;
            border-radius: 4px;
            border: 1px solid #1976d2;
            background: #1976d2;
            color: #fff;
            cursor: pointer;
        }

        a {
            text-decoration: none;
            color: #1976d2;
        }

        .error {
            color: #c62828;
            margin-bottom: .5rem;
            font-size: .9rem;
        }

        small {
            color: #777;
        }
    </style>
</head>

<body>

    <h1>Novo Agendamento</h1>
    <p><a href="/schedules">← Voltar à lista</a></p>

    <?php if (!empty($error)): ?>
        <div class="error"><?= esc($error) ?></div>
    <?php endif; ?>

    <form method="post" action="/schedules/new">
        <?= csrf_field() ?>

        <div class="row">
            <label>
                Tipo de agendamento
                <select name="type">
                    <option value="room" <?= (isset($old['type']) && $old['type'] === 'host') ? '' : 'selected' ?>>
                        Bloquear sala atual
                    </option>
                    <option value="host" <?= (isset($old['type']) && $old['type'] === 'host') ? 'selected' : '' ?>>
                        Bloquear apenas um host
                    </option>
                </select>
            </label>
            <small>“Sala atual” usa o room_id associado à sessão; “host” usa um MAC específico.</small>
        </div>

        <div class="row">
            <label>
                Host (opcional, usado se o tipo for "host")
                <select name="target_mac">
                    <option value="">-- selecione um host --</option>
                    <?php foreach ($hosts as $h): ?>
                        <?php
                        $label = trim(($h['hostname'] ?? '') . ' - ' . $h['mac'] . ' [' . ($h['port_descr'] ?? '') . ']');
                        $selected = (isset($old['target_mac']) && $old['target_mac'] === $h['mac']) ? 'selected' : '';
                        ?>
                        <option value="<?= esc($h['mac']) ?>" <?= $selected ?>>
                            <?= esc($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>

        <div class="row">
            <label>
                Início (YYYY-MM-DD HH:MM:SS)
                <input type="text" name="start_at"
                    value="<?= esc($old['start_at'] ?? '') ?>"
                    placeholder="2025-01-01 19:00:00">
            </label>
        </div>

        <div class="row">
            <label>
                Fim (opcional, YYYY-MM-DD HH:MM:SS)
                <input type="text" name="end_at"
                    value="<?= esc($old['end_at'] ?? '') ?>"
                    placeholder="2025-01-01 21:00:00">
            </label>
            <small>Se deixar vazio, o bloqueio ficará sem horário definido para liberar (você libera manualmente).</small>
        </div>

        <button type="submit">Salvar agendamento</button>
    </form>

</body>

</html>
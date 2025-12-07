<!doctype html>
<html lang="pt-br">

<head>
    <meta charset="utf-8">
    <title>Login - Controle SNMP</title>
    <style>
        body {
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: #f5f5f5;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
        }

        .card {
            background: #fff;
            padding: 24px 28px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.12);
            width: 320px;
        }

        h1 {
            font-size: 1.3rem;
            margin-top: 0;
            margin-bottom: 0.5rem;
            text-align: center;
        }

        p.subtitle {
            margin-top: 0;
            margin-bottom: 1.5rem;
            font-size: 0.85rem;
            color: #666;
            text-align: center;
        }

        label {
            display: block;
            margin-bottom: 0.25rem;
            font-size: 0.85rem;
        }

        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 6px 8px;
            margin-bottom: 0.75rem;
            border-radius: 4px;
            border: 1px solid #ccc;
            font-size: 0.9rem;
        }

        button {
            width: 100%;
            padding: 8px;
            border-radius: 4px;
            border: none;
            background: #1976d2;
            color: #fff;
            font-size: 0.95rem;
            cursor: pointer;
        }

        button:hover {
            background: #135ba1;
        }

        .error {
            color: #c62828;
            font-size: 0.85rem;
            margin-bottom: 0.75rem;
        }
    </style>
</head>

<body>

    <div class="card">
        <h1>Login</h1>
        <p class="subtitle">Acesso ao painel de controle SNMP.</p>

        <?php if (session()->getFlashdata('error')): ?>
            <div class="error">
                <?= esc(session()->getFlashdata('error')) ?>
            </div>
        <?php endif; ?>

        <form action="/login" method="post">
            <?= csrf_field() ?>

            <label for="username">Usuário</label>
            <input type="text" id="username" name="username" required>

            <label for="password">Senha</label>
            <input type="password" id="password" name="password" required>

            <button type="submit">Entrar</button>
        </form>
    </div>

</body>

</html>
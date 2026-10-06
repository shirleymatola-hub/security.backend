# SMP — API (backend)

API REST do **Sistema de Monitoramento Policial (SMP)** em Laravel 12.
A interface está no repositório separado **SMP_frontend** (React + Vite).

- Autenticação por token com **Laravel Sanctum** (`Authorization: Bearer <token>`)
- Base de dados **PostgreSQL + PostGIS** (local ou [Neon](https://neon.tech))
- Perfis com `spatie/laravel-permission`: admin, district_commander, squad_commander,
  post_commander, manager, police, sernic_officer, citizen
- PDFs com `barryvdh/laravel-dompdf` (views em `resources/views/*/reports/pdf.blade.php`)

## Estrutura da API

Todas as rotas têm o prefixo `/api` (ver `routes/api.php`):

| Ficheiro | URL | Acesso |
|---|---|---|
| `routes/api/auth.php` | `/api/auth/login`, `/register`, `/me`, `/logout`, `/forgot-password`, `/confirm-code`, `/reset-password`, `/google/*` | público / token |
| `routes/api/public.php` | `/api/public/...` (ocorrências públicas, denúncia anónima) | público |
| `routes/api/map.php` | `/api/map/...`, `/api/jurisdictions`, `/api/police-units`, `/api/route`, ... | público (algumas com token) |
| `routes/api/common.php` | `/api/profile`, `/api/notifications`, `/api/reminders`, `/api/theme`, `/api/locale` | token |
| `routes/api/<perfil>.php` | `/api/admin/...`, `/api/manager/...`, `/api/police/...`, ... | token + perfil |
| `routes/internal_api.php` | `/api/internal/...` (integrações externas) | token interno |

Erros são sempre devolvidos em JSON (422 com `errors` para validação, 401 sem sessão, 403 sem permissão).

## Desenvolvimento local

Requisitos: PHP 8.2+ com as extensões `pdo_pgsql`, `pgsql`, `gd`, `zip`, `intl`; Composer; PostgreSQL com PostGIS.

> **WAMP:** o `php.ini` da linha de comandos tem `;extension=pdo_pgsql` e `;extension=pgsql` comentados.
> Retire o `;` dessas duas linhas em `C:\wamp64\bin\php\php8.3.14\php.ini`.

```bash
composer install
cp .env.example .env          # configure DB_* e FRONTEND_URL
php artisan key:generate
php artisan migrate           # cria a tabela personal_access_tokens (Sanctum)
php artisan storage:link
php artisan serve --port=8025
```

O frontend (`npm run dev` no SMP_frontend) corre em `http://localhost:5173` e encaminha `/api` para `http://127.0.0.1:8025`.

## Base de dados na Neon

As tabelas `"Area de Estudo"` e `unidades policiais` (geometrias dos bairros e unidades) **não são criadas pelas
migrations** — foram importadas à parte. Por isso a forma correta de passar para a Neon é copiar a base local completa:

1. Crie um projeto na Neon (região próxima do Render, ex.: `eu-central-1` Frankfurt) e copie a *connection string*.
2. Ative as extensões na base Neon:
   ```bash
   psql "postgresql://USER:PASS@ep-xxxx.eu-central-1.aws.neon.tech/neondb?sslmode=require" \
        -c "CREATE EXTENSION IF NOT EXISTS postgis; CREATE EXTENSION IF NOT EXISTS \"uuid-ossp\";"
   ```
3. Exporte a base local e importe na Neon:
   ```bash
   pg_dump -h 127.0.0.1 -U postgres -d smp -Fc --no-owner --no-acl -f smp.dump
   pg_restore --no-owner --no-acl -d "postgresql://USER:PASS@ep-xxxx.eu-central-1.aws.neon.tech/neondb?sslmode=require" smp.dump
   ```
   (Avisos sobre a extensão `postgis` já existente podem ser ignorados.)
4. No Render, defina `DB_URL` com essa connection string. No arranque o contentor corre
   `php artisan migrate --force`, que só aplica as migrations em falta (ex.: tokens do Sanctum).

## Deploy no Render

1. Crie um repositório Git só com esta pasta e envie para o GitHub/GitLab.
2. No Render: **New → Blueprint** e escolha o repositório (usa o `render.yaml` e o `Dockerfile`).
3. Preencha as variáveis pedidas:
   - `APP_KEY` — gere com `php artisan key:generate --show`
   - `APP_URL` — URL deste serviço, ex.: `https://smp-backend.onrender.com`
   - `FRONTEND_URL` — URL do frontend, ex.: `https://smp-frontend.onrender.com` (usado no CORS)
   - `DB_URL` — connection string da Neon
   - `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI` = `https://smp-backend.onrender.com/api/auth/google/callback`
   - `OPENROUTESERVICE_API_KEY`
4. Na **Google Cloud Console**, adicione o novo URI de redirecionamento
   (`.../api/auth/google/callback`) às credenciais OAuth.

Health check: `GET /up`.

## Notas importantes

- **Ficheiros enviados** (fotos de perfil, anexos, áudios) são guardados em `storage/app/public`. No plano gratuito do
  Render o disco é apagado em cada deploy/reinício. Para produção use um *Persistent Disk* do Render (plano pago)
  montado em `/var/www/html/storage/app/public`, ou um armazenamento S3/Cloudflare R2.
- **Fuso horário:** a aplicação usa `UTC` (`config/app.php`), como no projeto original. O frontend mostra as datas em UTC
  para manter as mesmas horas. Para mudar para hora de Moçambique altere `timezone` para `Africa/Maputo` e
  `VITE_TIME_ZONE` no frontend.
- **Recuperação de palavra-passe:** tal como no projeto original, o código de 6 dígitos não é enviado por email;
  em ambiente `local` a API devolve-o (`dev_code`) para testes. Em produção é preciso configurar o envio por email (`MAIL_*`).
- O plano gratuito do Render adormece o serviço após inatividade; o primeiro pedido pode demorar ~1 minuto.

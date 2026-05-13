# Release 0-Deploy — Produção em VPS Hetzner

> Objetivo: colocar a release 0 no ar, com domínio, HTTPS, backup
> automático e segurança operacional mínima.

Pré-requisito: Release 0 (MVP local) verde.

---

## 1. Provisionamento

### VPS

- Hetzner CPX11 (2 vCPU, 2 GB RAM, 40 GB SSD) — sobra pra 10 clientes.
- Ubuntu 24.04 LTS.
- IPv4 + IPv6.
- Snapshot inicial após hardening (rollback fácil).

### DNS

- `app.drnewsletter.com.br` (ou subdomínio definitivo) → A/AAAA da VPS.
- TTL baixo (300s) no setup; subir depois.

### Hardening básico

- usuário não-root com sudo, chave SSH (sem senha).
- `PermitRootLogin no`, `PasswordAuthentication no` em `sshd_config`.
- ufw: 22, 80, 443.
- fail2ban com jail de SSH.
- unattended-upgrades para security.

---

## 2. Stack do servidor

```bash
apt install -y nginx php8.3-fpm php8.3-cli php8.3-sqlite3 \
               php8.3-curl php8.3-mbstring php8.3-xml \
               sqlite3 git certbot python3-certbot-nginx
```

PHP 8.3 em produção mesmo com PHP 8.1+ no `composer.json` —
é só compatibilidade mínima, em produção rodamos o mais novo.

---

## 3. Estrutura de diretórios

```bash
useradd -r -s /usr/sbin/nologin drnl
mkdir -p /opt/drnewsletter
mkdir -p /data/drnewsletter/{uploads,logs,backups}
chown -R drnl:drnl /data/drnewsletter
chmod 750 /data/drnewsletter
```

---

## 4. Deploy

```bash
cd /opt
git clone https://github.com/felipepavao/drnewsletter-saas.git drnewsletter
cd drnewsletter
# .env precisa existir aqui — copiar de cofre local
cp /caminho/seguro/.env .
chown root:drnl .env
chmod 640 .env
php bin/migrate.php
```

Link simbólico para dados externos:

```bash
ln -s /data/drnewsletter/uploads public/uploads
# data/database.sqlite será criada por bin/migrate.php — mover:
mv data/database.sqlite /data/drnewsletter/database.sqlite
ln -s /data/drnewsletter/database.sqlite data/database.sqlite
ln -s /data/drnewsletter/logs data/logs
ln -s /data/drnewsletter/backups data/backups
```

---

## 5. PHP-FPM pool dedicado

`/etc/php/8.3/fpm/pool.d/drnewsletter.conf`:

```ini
[drnewsletter]
user = drnl
group = drnl
listen = /run/php/php-drnewsletter.sock
listen.owner = www-data
listen.group = www-data
pm = dynamic
pm.max_children = 6
pm.start_servers = 2
pm.min_spare_servers = 1
pm.max_spare_servers = 3
request_terminate_timeout = 150s
php_admin_value[disable_functions] = exec,passthru,shell_exec,system,proc_open,popen
php_admin_value[expose_php] = Off
php_admin_value[upload_max_filesize] = 12M
php_admin_value[post_max_size] = 12M
```

---

## 6. nginx

`/etc/nginx/sites-available/drnewsletter`:

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name app.drnewsletter.com.br;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name app.drnewsletter.com.br;

    root /opt/drnewsletter/public;
    index index.php;

    client_max_body_size 12M;

    # Estáticos
    location ~* \.(css|js|png|jpg|jpeg|gif|svg|woff2?|ico)$ {
        access_log off;
        expires 30d;
        add_header Cache-Control "public, immutable";
    }

    # Tudo via index.php (router)
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php-drnewsletter.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_read_timeout 150s;
    }

    # Nega acesso direto a arquivos sensíveis
    location ~ /\.(env|git) { deny all; return 404; }
    location ~ /(bootstrap|config)\.php$ { deny all; return 404; }
}
```

HTTPS:

```bash
certbot --nginx -d app.drnewsletter.com.br --redirect --no-eff-email -m felipe@drnewsletter.com.br --agree-tos
```

---

## 7. Backup automático

`/etc/cron.d/drnewsletter-backup`:

```
0 3 * * * drnl cd /opt/drnewsletter && /usr/bin/php bin/backup.php >> /data/drnewsletter/logs/backup.log 2>&1
```

**Offsite (semanal):** rsync `/data/drnewsletter/backups/` para storage
externo (Backblaze B2, S3, ou outra VPS). Excluir explicitamente `.env`
e `/opt`.

---

## 8. Spending cap na Anthropic

Antes de virar a chave em produção:

1. console.anthropic.com → Settings → Limits.
2. Hard cap: US$100/mês inicial (margem 4x da estimativa de US$26/mês).
3. Alert em 50% e 80%.
4. Email do alert chega no admin.

Esta é a **segunda linha de defesa** — independente do app, se algo der
errado o prejuízo é cap, não open-ended.

---

## 9. Pós-deploy — checklist de verificação

- [ ] HTTPS funcionando, redirect de HTTP.
- [ ] `https://app.drnewsletter.com.br/.env` → 404.
- [ ] `https://app.drnewsletter.com.br/bootstrap.php` → 404.
- [ ] Login com magic link recebe email real.
- [ ] Fluxo end-to-end (cliente → voz → planner → writer) ok.
- [ ] `php bin/backup.php` rodando manualmente cria `.gz`.
- [ ] Cron de backup ativo (verificar com `systemctl status cron`).
- [ ] `chmod` do `.env` é 640.
- [ ] `claude_calls` registrando consumo.
- [ ] Spending cap no console Anthropic ativado.

---

## 10. Procedimento de rollback

Se o deploy quebrar:

```bash
cd /opt/drnewsletter
git reset --hard <commit_anterior>
systemctl reload php8.3-fpm
```

Se o banco corromper:

```bash
systemctl stop php8.3-fpm
cd /data/drnewsletter
zcat backups/database_YYYY-MM-DD_HHMMSS.sqlite.gz > database.sqlite
systemctl start php8.3-fpm
```

---

## 11. Rotação da chave Anthropic

Procedimento padrão (qualquer suspeita = rotacionar imediatamente):

```bash
# 1. console.anthropic.com → revoke key atual
# 2. console.anthropic.com → create new key (mesmo workspace)
# 3. ssh prod:
cd /opt/drnewsletter
sudo $EDITOR .env  # atualizar ANTHROPIC_API_KEY
sudo systemctl reload php8.3-fpm
# 4. verificar log: tail -f /data/drnewsletter/logs/app.log
# 5. fazer 1 chamada de teste pela UI
```

Zero downtime. Dados intactos.

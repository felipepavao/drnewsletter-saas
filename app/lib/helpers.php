<?php

// --- Escape ---
function e($v): string {
    if ($v === null) return '';
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

// --- URLs ---
function url(string $path = ''): string {
    return APP_URL . '/' . ltrim($path, '/');
}

function asset(string $path): string {
    return APP_URL . '/assets/' . ltrim($path, '/');
}

function upload_url(string $path): string {
    if ($path === '') return '';
    return APP_URL . $path; // $path já vem como /uploads/...
}

function is_current(string $path): bool {
    return rtrim(Request::uri(), '/') === rtrim('/' . ltrim($path, '/'), '/');
}

// --- Redirect ---
function redirect(string $path, int $code = 302): void {
    Response::redirect($path, $code);
}

// --- Validações ---
function normalize_email(string $email): string {
    return strtolower(trim($email));
}

function is_valid_email(string $email): bool {
    return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
}

function normalize_instagram(string $raw): string {
    $raw = trim($raw);
    if ($raw === '') return '';
    $raw = preg_replace('#^https?://(www\.)?instagram\.com/#i', '', $raw) ?? $raw;
    $raw = ltrim($raw, '@/');
    $raw = rtrim($raw, '/');
    $raw = preg_replace('/[^a-zA-Z0-9._]/', '', $raw) ?? '';
    return $raw === '' ? '' : '@' . $raw;
}

function normalize_website(string $raw): string {
    $raw = trim($raw);
    if ($raw === '') return '';
    if (!preg_match('#^https?://#i', $raw)) {
        $raw = 'https://' . $raw;
    }
    return filter_var($raw, FILTER_VALIDATE_URL) ? $raw : '';
}

function normalize_phone(string $raw): string {
    $digits = preg_replace('/\D/', '', $raw) ?? '';
    return $digits;
}

function whatsapp_link(string $phone): string {
    $digits = normalize_phone($phone);
    if ($digits === '') return '';
    if (strlen($digits) <= 11) $digits = '55' . $digits;
    return 'https://wa.me/' . $digits;
}

// --- Auth helpers ---
function current_user(): ?array {
    $uid = Session::userId();
    if (!$uid) return null;
    static $cache = null;
    if ($cache && $cache['id'] === $uid) return $cache;
    $cache = Database::fetch('SELECT * FROM users WHERE id = ? AND status != ?', [$uid, 'deleted']);
    return $cache;
}

function is_logged_in(): bool {
    return current_user() !== null;
}

function is_admin(): bool {
    $u = current_user();
    return $u && (int) $u['is_admin'] === 1;
}

function is_viewer(?array $u = null): bool {
    $u = $u ?? current_user();
    return $u && (int) ($u['directory_listed'] ?? 1) === 0;
}

function require_auth(): void {
    if (!is_logged_in()) redirect('/');
}

function require_approved(): void {
    require_auth();
    $u = current_user();
    if ($u['status'] === 'approved' && $u['consent_lgpd_at']) return;
    // Qualquer outro estado: delega a rota para redirect_after_auth
    redirect_after_auth();
}

function require_admin(): void {
    require_auth();
    if (!is_admin()) redirect('/');
}

// --- Misc ---
function uuid4(): string {
    $b = random_bytes(16);
    $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
    $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
}

function random_code(int $digits = 6): string {
    $min = (int) str_pad('1', $digits, '0');
    $max = (int) str_pad('9', $digits, '9');
    return (string) random_int($min, $max);
}

// ---- Brasil ----

function brazil_states(): array {
    return [
        'AC' => 'Acre', 'AL' => 'Alagoas', 'AP' => 'Amapá', 'AM' => 'Amazonas',
        'BA' => 'Bahia', 'CE' => 'Ceará', 'DF' => 'Distrito Federal', 'ES' => 'Espírito Santo',
        'GO' => 'Goiás', 'MA' => 'Maranhão', 'MT' => 'Mato Grosso', 'MS' => 'Mato Grosso do Sul',
        'MG' => 'Minas Gerais', 'PA' => 'Pará', 'PB' => 'Paraíba', 'PR' => 'Paraná',
        'PE' => 'Pernambuco', 'PI' => 'Piauí', 'RJ' => 'Rio de Janeiro', 'RN' => 'Rio Grande do Norte',
        'RS' => 'Rio Grande do Sul', 'RO' => 'Rondônia', 'RR' => 'Roraima', 'SC' => 'Santa Catarina',
        'SP' => 'São Paulo', 'SE' => 'Sergipe', 'TO' => 'Tocantins',
    ];
}

// ---- Taxonomias (cache por request) ----

function load_taxonomies(): array {
    static $cache = null;
    if ($cache) return $cache;

    $professions = Database::fetchAll(
        'SELECT id, name FROM professions WHERE active = 1 ORDER BY display_order, name'
    );
    $specialties = Database::fetchAll(
        'SELECT id, name, category FROM specialties WHERE active = 1 ORDER BY display_order, name'
    );
    $workshops = Database::fetchAll(
        'SELECT id, label FROM workshops WHERE active = 1 ORDER BY display_order'
    );
    $tags = Database::fetchAll(
        'SELECT id, name, category FROM interest_tags WHERE active = 1 ORDER BY display_order, name'
    );

    // Especialidades e tags agrupadas por categoria
    $specByCat = []; foreach ($specialties as $s) { $specByCat[$s['category'] ?? 'other'][] = $s; }
    $tagsByCat = []; foreach ($tags as $t) { $tagsByCat[$t['category']][] = $t; }

    return $cache = [
        'professions' => $professions,
        'specialties' => $specialties,
        'specialties_by_category' => $specByCat,
        'workshops' => $workshops,
        'interest_tags' => $tags,
        'interest_tags_by_category' => $tagsByCat,
    ];
}

function specialty_category_label(string $cat): string {
    return [
        'medical'       => 'Médicas',
        'psychological' => 'Psicológicas / análise',
        'therapeutic'   => 'Terapêuticas / integrativas',
        'other'         => 'Outras áreas',
    ][$cat] ?? ucfirst($cat);
}

function tag_category_label(string $cat): string {
    return [
        'clinico'        => 'Atendimento clínico',
        'formacao'       => 'Formação e troca',
        'encaminhamento' => 'Encaminhamento',
        'vivencias'      => 'Vivências',
        'pesquisa'       => 'Pesquisa e produção',
        'parcerias'      => 'Parcerias',
        'pessoal'        => 'Processo pessoal',
    ][$cat] ?? ucfirst($cat);
}

// ---- Profile loading ----

function load_full_profile(int $userId): ?array {
    $user = Database::fetch(
        'SELECT id, email, status, is_admin, approved_at, last_login_at, consent_lgpd_at, created_at,
                directory_listed, suspended_at, suspended_by_user
         FROM users WHERE id = ?', [$userId]
    );
    if (!$user) return null;

    $profile = Database::fetch(
        'SELECT p.*,
                pr.name  AS profession_name,
                pr2.name AS profession_secondary_name
         FROM profiles p
         LEFT JOIN professions pr  ON pr.id  = p.profession_id
         LEFT JOIN professions pr2 ON pr2.id = p.profession_secondary_id
         WHERE p.user_id = ?', [$userId]
    );
    if (!$profile) return null;

    $visibility = Database::fetch(
        'SELECT * FROM profile_visibility WHERE user_id = ?', [$userId]
    ) ?: [
        'show_email' => 0, 'show_phone' => 0, 'show_whatsapp' => 1,
        'show_city' => 1, 'show_instagram' => 1, 'show_website' => 1,
    ];

    $workshops = Database::fetchAll(
        'SELECT w.id, w.label FROM workshops w
         JOIN user_workshops uw ON uw.workshop_id = w.id
         WHERE uw.user_id = ? ORDER BY w.display_order', [$userId]
    );
    $specialties = Database::fetchAll(
        'SELECT s.id, s.name, s.category FROM specialties s
         JOIN user_specialties us ON us.specialty_id = s.id
         WHERE us.user_id = ? ORDER BY s.display_order', [$userId]
    );
    $tags = Database::fetchAll(
        'SELECT t.id, t.name, t.category, uit.type FROM interest_tags t
         JOIN user_interest_tags uit ON uit.tag_id = t.id
         WHERE uit.user_id = ? ORDER BY t.display_order', [$userId]
    );

    return [
        'user'        => $user,
        'profile'     => $profile,
        'visibility'  => $visibility,
        'workshops'   => $workshops,
        'specialties' => $specialties,
        'tags'        => $tags,
    ];
}

function has_profile(int $userId): bool {
    return (bool) Database::fetchColumn('SELECT 1 FROM profiles WHERE user_id = ?', [$userId]);
}

// ---- Form helpers ----

function old(string $key, $default = '', array $data = []): string {
    $v = $data[$key] ?? $_POST[$key] ?? $default;
    return is_scalar($v) ? (string) $v : $default;
}

function checked(bool $cond): string {
    return $cond ? 'checked' : '';
}

function selected(bool $cond): string {
    return $cond ? 'selected' : '';
}

// Anti-spam: honeypot. Bots preenchem campos "escondidos".
//
// Nomes: campos genéricos que o autofill do Chrome/Edge NÃO reconhece.
// Histórico: já tivemos `hp_email` com type="email" — Chromium ignora
// autocomplete="off" em inputs de email e auto-preenchia, causando perda
// silenciosa de cadastros legítimos (caso da Cristina, abril/2026).
//
// Quando dispara, logamos pra detectar falsos positivos antes que o usuário
// volte cobrando — diferente do redirect silencioso que era invisível.
function honeypot_tripped(): bool {
    $confirmUrl     = trim((string) ($_POST['confirm_url'] ?? ''));
    $subscribeToken = trim((string) ($_POST['subscribe_token'] ?? ''));

    if ($confirmUrl === '' && $subscribeToken === '') {
        return false;
    }

    Log::warn('honeypot tripped on submission form', [
        'ip'              => Request::ip(),
        'user_agent'      => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 200),
        'email_attempted' => substr((string) ($_POST['email'] ?? ''), 0, 120),
        'confirm_url'     => substr($confirmUrl, 0, 120),
        'subscribe_token' => substr($subscribeToken, 0, 120),
    ]);

    return true;
}

// ---- Profile form extraction/validation ----

/**
 * Lê $_POST e retorna [$data, $errors].
 * $context: 'public' (submissão via /cadastrar, exige email + consent) | 'edit' (membro logado).
 * $forceAccountType: se não-null, ignora o POST e força 'member'|'viewer' — usado pelo cadastro
 *                    público pra garantir que só membros sejam criados lá.
 * No modo viewer, só nome + turmas + (no public) email/consent são obrigatórios.
 */
function extract_profile_data(string $context, ?string $forceAccountType = null): array {
    $tax = load_taxonomies();
    $errors = [];
    $data   = [];

    // Modo: 'member' (default, perfil completo e listado) ou 'viewer' (acesso de consulta)
    $accountType = $forceAccountType ?? Request::post('account_type', 'member');
    if (!in_array($accountType, ['member', 'viewer'], true)) $accountType = 'member';
    $data['account_type']     = $accountType;
    $data['directory_listed'] = $accountType === 'viewer' ? 0 : 1;
    $isViewer = $accountType === 'viewer';

    // --- Identidade ---
    $data['full_name']     = trim((string) Request::post('full_name', ''));
    $data['display_name']  = trim((string) Request::post('display_name', ''));
    $data['city']          = trim((string) Request::post('city', ''));
    $data['state_uf']      = strtoupper(trim((string) Request::post('state_uf', '')));
    $profId                = (int) Request::post('profession_id', 0);
    $secondary             = (int) Request::post('profession_secondary_id', 0);
    $data['profession_id']           = $profId ?: null;
    $data['profession_secondary_id'] = $secondary ?: null;

    if ($data['full_name'] === '' || mb_strlen($data['full_name']) < 3) {
        $errors['full_name'] = 'Informe seu nome completo.';
    }

    $profIds = array_column($tax['professions'], 'id');

    // Campos "membro" — só validamos se NÃO é observador
    if (!$isViewer) {
        if ($data['city'] === '') $errors['city'] = 'Informe sua cidade.';
        if (!array_key_exists($data['state_uf'], brazil_states())) {
            $errors['state_uf'] = 'Selecione um estado.';
        }
        if (!in_array($data['profession_id'], $profIds, true)) {
            $errors['profession_id'] = 'Selecione sua profissão principal.';
        }
        if ($secondary && !in_array($secondary, $profIds, true)) {
            $errors['profession_secondary_id'] = 'Profissão secundária inválida.';
        }
    } else {
        // Observador: zera os campos não aplicáveis, evitando lixo no banco.
        $data['city']                    = null;
        $data['state_uf']                = null;
        $data['profession_id']           = null;
        $data['profession_secondary_id'] = null;
    }

    // --- Turmas (pelo menos 1 — obrigatório em ambos os modos) ---
    $workshopIds = array_map('intval', (array) Request::array('workshops'));
    $validWorkshops = array_column($tax['workshops'], 'id');
    $workshopIds = array_values(array_unique(array_filter(
        $workshopIds, fn($id) => in_array($id, $validWorkshops, true)
    )));
    if (!$workshopIds) $errors['workshops'] = 'Selecione pelo menos uma turma.';
    $data['workshops'] = $workshopIds;

    // --- Apresentação (só pra membros) ---
    if (!$isViewer) {
        $data['bio']            = trim((string) Request::post('bio', ''));
        $data['offers_text']    = trim((string) Request::post('offers_text', ''));
        $data['interests_text'] = trim((string) Request::post('interests_text', ''));
        $data['open_to_work']   = Request::post('open_to_work', 'seletivo');
        if (!in_array($data['open_to_work'], ['aberto', 'seletivo', 'fechado'], true)) {
            $data['open_to_work'] = 'seletivo';
        }

        $specIds = array_map('intval', (array) Request::array('specialties'));
        $validSpec = array_column($tax['specialties'], 'id');
        $data['specialties'] = array_values(array_unique(array_filter(
            $specIds, fn($id) => in_array($id, $validSpec, true)
        )));

        $tagsRaw = Request::array('interest_tags');
        $validTags = array_column($tax['interest_tags'], 'id');
        $data['interest_tags'] = [];
        foreach ($tagsRaw as $id => $type) {
            $id = (int) $id;
            $type = (string) $type;
            if (!in_array($id, $validTags, true)) continue;
            if (in_array($type, ['oferece', 'busca', 'ambos'], true)) {
                $data['interest_tags'][$id] = $type;
            }
        }

        // --- Contatos ---
        $data['phone']     = normalize_phone(Request::post('phone', ''));
        $data['whatsapp']  = normalize_phone(Request::post('whatsapp', ''));
        $data['instagram'] = normalize_instagram((string) Request::post('instagram', ''));
        $data['website']   = normalize_website((string) Request::post('website', ''));

        if (!empty($_POST['website']) && $data['website'] === '') {
            $errors['website'] = 'Endereço do site inválido.';
        }

        // --- Visibilidade ---
        $data['visibility'] = [
            'show_email'     => (int) (Request::post('show_email') ? 1 : 0),
            'show_phone'     => (int) (Request::post('show_phone') ? 1 : 0),
            'show_whatsapp'  => (int) (Request::post('show_whatsapp') ? 1 : 0),
            'show_city'      => (int) (Request::post('show_city') ? 1 : 0),
            'show_instagram' => (int) (Request::post('show_instagram') ? 1 : 0),
            'show_website'   => (int) (Request::post('show_website') ? 1 : 0),
        ];
    } else {
        // Observador: zera tudo relacionado a apresentação pública.
        $data['bio']            = null;
        $data['offers_text']    = null;
        $data['interests_text'] = null;
        $data['open_to_work']   = 'seletivo';
        $data['specialties']    = [];
        $data['interest_tags']  = [];
        $data['phone']          = null;
        $data['whatsapp']       = null;
        $data['instagram']      = null;
        $data['website']        = null;
        $data['visibility']     = [
            'show_email'     => 0, 'show_phone'    => 0, 'show_whatsapp' => 0,
            'show_city'      => 0, 'show_instagram'=> 0, 'show_website'  => 0,
        ];
    }

    // --- Email e consent (só no contexto 'public') ---
    if ($context === 'public') {
        $email = normalize_email((string) Request::post('email', ''));
        if (!is_valid_email($email)) {
            $errors['email'] = 'Email inválido.';
        }
        $data['email'] = $email;

        if (!Request::post('consent_lgpd')) {
            $errors['consent_lgpd'] = 'Você precisa aceitar o tratamento de dados.';
        }
    }

    return [$data, $errors];
}

/**
 * Aplica um $data (já validado) em profiles, profile_visibility e tabelas many-to-many.
 * Retorna o ID do usuário (útil quando criado agora).
 */
function save_profile_data(int $userId, array $data, ?array $photoFile = null, ?array $bannerFile = null, ?array $existingPhotoPath = null, ?array $existingBannerPath = null): int {
    Database::transaction(function () use ($userId, $data) {
        // Atualiza flag de listagem no users (observador vs membro)
        if (array_key_exists('directory_listed', $data)) {
            Database::execute(
                'UPDATE users SET directory_listed = ? WHERE id = ?',
                [$data['directory_listed'] ? 1 : 0, $userId]
            );
        }

        $exists = (bool) Database::fetchColumn('SELECT 1 FROM profiles WHERE user_id = ?', [$userId]);
        if ($exists) {
            Database::execute(
                "UPDATE profiles SET
                    full_name = ?, display_name = ?, bio = ?,
                    profession_id = ?, profession_secondary_id = ?,
                    city = ?, state_uf = ?,
                    phone = ?, whatsapp = ?, instagram = ?, website = ?,
                    offers_text = ?, interests_text = ?, open_to_work = ?,
                    updated_at = datetime('now')
                 WHERE user_id = ?",
                [
                    $data['full_name'], $data['display_name'] ?: null, $data['bio'] ?: null,
                    $data['profession_id'], $data['profession_secondary_id'],
                    $data['city'], $data['state_uf'],
                    $data['phone'] ?: null, $data['whatsapp'] ?: null,
                    $data['instagram'] ?: null, $data['website'] ?: null,
                    $data['offers_text'] ?: null, $data['interests_text'] ?: null,
                    $data['open_to_work'],
                    $userId,
                ]
            );
        } else {
            Database::execute(
                "INSERT INTO profiles (
                    user_id, full_name, display_name, bio,
                    profession_id, profession_secondary_id, city, state_uf,
                    phone, whatsapp, instagram, website,
                    offers_text, interests_text, open_to_work, updated_at
                 ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, datetime('now'))",
                [
                    $userId, $data['full_name'], $data['display_name'] ?: null, $data['bio'] ?: null,
                    $data['profession_id'], $data['profession_secondary_id'],
                    $data['city'], $data['state_uf'],
                    $data['phone'] ?: null, $data['whatsapp'] ?: null,
                    $data['instagram'] ?: null, $data['website'] ?: null,
                    $data['offers_text'] ?: null, $data['interests_text'] ?: null,
                    $data['open_to_work'],
                ]
            );
        }

        // Visibilidade (upsert manual)
        $v = $data['visibility'];
        Database::execute(
            "INSERT INTO profile_visibility (user_id, show_email, show_phone, show_whatsapp, show_city, show_instagram, show_website)
             VALUES (?, ?, ?, ?, ?, ?, ?)
             ON CONFLICT(user_id) DO UPDATE SET
                show_email     = excluded.show_email,
                show_phone     = excluded.show_phone,
                show_whatsapp  = excluded.show_whatsapp,
                show_city      = excluded.show_city,
                show_instagram = excluded.show_instagram,
                show_website   = excluded.show_website",
            [$userId, $v['show_email'], $v['show_phone'], $v['show_whatsapp'], $v['show_city'], $v['show_instagram'], $v['show_website']]
        );

        // Turmas (replace)
        Database::execute('DELETE FROM user_workshops WHERE user_id = ?', [$userId]);
        foreach ($data['workshops'] as $wid) {
            Database::execute(
                'INSERT INTO user_workshops (user_id, workshop_id) VALUES (?, ?)',
                [$userId, $wid]
            );
        }

        // Especialidades (replace)
        Database::execute('DELETE FROM user_specialties WHERE user_id = ?', [$userId]);
        foreach ($data['specialties'] as $sid) {
            Database::execute(
                'INSERT INTO user_specialties (user_id, specialty_id) VALUES (?, ?)',
                [$userId, $sid]
            );
        }

        // Tags (replace)
        Database::execute('DELETE FROM user_interest_tags WHERE user_id = ?', [$userId]);
        foreach ($data['interest_tags'] as $tid => $type) {
            Database::execute(
                'INSERT INTO user_interest_tags (user_id, tag_id, type) VALUES (?, ?, ?)',
                [$userId, $tid, $type]
            );
        }
    });

    // Upload de imagens (fora da transação, pra não reter locks durante I/O)
    if ($photoFile) {
        $old = Database::fetchColumn('SELECT photo_path FROM profiles WHERE user_id = ?', [$userId]) ?: null;
        $newPath = Image::uploadAndResize($photoFile, 'photos', PHOTO_WIDTH, PHOTO_HEIGHT, $userId, $old);
        if ($newPath) {
            Database::execute('UPDATE profiles SET photo_path = ? WHERE user_id = ?', [$newPath, $userId]);
        }
    }
    if ($bannerFile) {
        $old = Database::fetchColumn('SELECT banner_path FROM profiles WHERE user_id = ?', [$userId]) ?: null;
        $newPath = Image::uploadAndResize($bannerFile, 'banners', BANNER_WIDTH, BANNER_HEIGHT, $userId, $old);
        if ($newPath) {
            Database::execute('UPDATE profiles SET banner_path = ? WHERE user_id = ?', [$newPath, $userId]);
        }
    }

    return $userId;
}

/**
 * Monta o array de dados do diretório (membros aprovados com perfil).
 * Respeita visibilidade: cidade só aparece se show_city=1; contatos nunca vão no diretório
 * (ficam só na página do perfil público).
 */
function build_directory_data(): array {
    $rows = Database::fetchAll(
        "SELECT u.id, u.email,
                p.full_name, p.display_name, p.photo_path, p.bio,
                p.profession_id, p.profession_secondary_id,
                p.city, p.state_uf, p.open_to_work,
                pr.name  AS profession_name,
                pr2.name AS profession_secondary_name,
                v.show_city
         FROM users u
         JOIN profiles p ON p.user_id = u.id
         JOIN professions pr ON pr.id = p.profession_id
         LEFT JOIN professions pr2 ON pr2.id = p.profession_secondary_id
         LEFT JOIN profile_visibility v ON v.user_id = u.id
         WHERE u.status = 'approved'
           AND u.directory_listed = 1
         ORDER BY p.full_name"
    );

    if (!$rows) return [];

    $ids = array_column($rows, 'id');
    $in  = implode(',', array_fill(0, count($ids), '?'));

    $specs = Database::fetchAll(
        "SELECT us.user_id, us.specialty_id, s.name AS specialty_name
         FROM user_specialties us
         JOIN specialties s ON s.id = us.specialty_id
         WHERE us.user_id IN ($in)", $ids
    );
    $ws = Database::fetchAll(
        "SELECT uw.user_id, uw.workshop_id
         FROM user_workshops uw WHERE uw.user_id IN ($in)", $ids
    );
    $tags = Database::fetchAll(
        "SELECT uit.user_id, uit.tag_id, uit.type, t.name AS tag_name
         FROM user_interest_tags uit
         JOIN interest_tags t ON t.id = uit.tag_id
         WHERE uit.user_id IN ($in)", $ids
    );

    $specByUser = [];
    foreach ($specs as $s) $specByUser[(int) $s['user_id']][] = (int) $s['specialty_id'];

    $wsByUser = [];
    foreach ($ws as $w) $wsByUser[(int) $w['user_id']][] = (int) $w['workshop_id'];

    $offersByUser = $seeksByUser = $topTagsByUser = [];
    foreach ($tags as $t) {
        $uid = (int) $t['user_id'];
        $tid = (int) $t['tag_id'];
        if (in_array($t['type'], ['oferece', 'ambos'], true)) $offersByUser[$uid][] = $tid;
        if (in_array($t['type'], ['busca',   'ambos'], true)) $seeksByUser[$uid][]  = $tid;
        if (count($topTagsByUser[$uid] ?? []) < 3) {
            $topTagsByUser[$uid][] = $t['tag_name'];
        }
    }

    $out = [];
    foreach ($rows as $r) {
        $uid = (int) $r['id'];
        $showCity = (int) ($r['show_city'] ?? 1) === 1;
        $bio = (string) ($r['bio'] ?? '');
        $bioShort = mb_strimwidth(preg_replace('/\s+/', ' ', trim($bio)), 0, 140, '…');
        $name = trim((string) ($r['display_name'] ?: $r['full_name']));

        $searchParts = [
            $name,
            $r['full_name'],
            $r['profession_name'],
            $r['profession_secondary_name'],
            $showCity ? $r['city'] : '',
            $r['state_uf'],
            $bio,
        ];
        foreach ($specs as $s) if ((int) $s['user_id'] === $uid) $searchParts[] = $s['specialty_name'];
        foreach ($tags as $t) if ((int) $t['user_id'] === $uid)  $searchParts[] = $t['tag_name'];
        $searchBlob = mb_strtolower(preg_replace('/\s+/', ' ', trim(implode(' ', array_filter($searchParts)))));

        $out[] = [
            'id'                    => $uid,
            'name'                  => $name,
            'full_name'             => $r['full_name'],
            'photo'                 => $r['photo_path'] ? upload_url($r['photo_path']) : null,
            'initials'              => user_initials($r['full_name']),
            'profession'            => $r['profession_name'],
            'profession_id'         => (int) $r['profession_id'],
            'profession_secondary'  => $r['profession_secondary_name'],
            'profession_secondary_id' => $r['profession_secondary_id'] ? (int) $r['profession_secondary_id'] : null,
            'city'                  => $showCity ? $r['city'] : null,
            'state_uf'              => $r['state_uf'],
            'bio_short'             => $bioShort,
            'open_to_work'          => $r['open_to_work'],
            'specialty_ids'         => $specByUser[$uid]    ?? [],
            'workshop_ids'          => $wsByUser[$uid]      ?? [],
            'offer_tag_ids'         => $offersByUser[$uid]  ?? [],
            'seek_tag_ids'          => $seeksByUser[$uid]   ?? [],
            'top_tags'              => $topTagsByUser[$uid] ?? [],
            'search_blob'           => $searchBlob,
            'url'                   => '/perfil/' . $uid,
        ];
    }

    return $out;
}

function user_initials(string $fullName): string {
    $parts = preg_split('/\s+/', trim($fullName));
    if (!$parts || $parts[0] === '') return '?';
    $first = mb_strtoupper(mb_substr($parts[0], 0, 1));
    $last  = count($parts) > 1 ? mb_strtoupper(mb_substr(end($parts), 0, 1)) : '';
    return $first . $last;
}

// ---- Admin stats / KPIs ----

function admin_user_counts(): array {
    $rows = Database::fetchAll(
        "SELECT status, COUNT(*) AS n FROM users GROUP BY status"
    );
    $base = ['pending' => 0, 'approved' => 0, 'suspended' => 0, 'deleted' => 0, 'total' => 0];
    foreach ($rows as $r) {
        $base[$r['status']] = (int) $r['n'];
        $base['total'] += (int) $r['n'];
    }
    return $base;
}

function admin_user_new_30d_count(): int {
    return (int) Database::fetchColumn(
        "SELECT COUNT(*) FROM users WHERE created_at >= datetime('now', '-30 days')"
    );
}

function admin_pending_stale_count(int $daysOld = 7): int {
    return (int) Database::fetchColumn(
        "SELECT COUNT(*) FROM users
         WHERE status = 'pending'
           AND created_at <= datetime('now', '-' || ? || ' days')",
        [$daysOld]
    );
}

function admin_pending_list(int $limit = 50): array {
    return Database::fetchAll(
        "SELECT u.id, u.email, u.created_at,
                p.full_name, p.profession_id, p.city, p.state_uf, p.photo_path,
                pr.name AS profession_name
         FROM users u
         LEFT JOIN profiles p   ON p.user_id = u.id
         LEFT JOIN professions pr ON pr.id = p.profession_id
         WHERE u.status = 'pending'
         ORDER BY u.created_at ASC
         LIMIT ?",
        [$limit]
    );
}

function admin_user_search(?string $status = null, string $q = '', int $limit = 100): array {
    $where = ['1=1'];
    $params = [];
    if ($status && $status !== 'all') {
        $where[] = 'u.status = ?';
        $params[] = $status;
    }
    if ($q !== '') {
        $where[] = '(u.email LIKE ? OR p.full_name LIKE ? OR p.city LIKE ?)';
        $like = '%' . $q . '%';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }
    $params[] = $limit;
    return Database::fetchAll(
        "SELECT u.id, u.email, u.status, u.is_admin, u.created_at, u.suspended_by_user, u.directory_listed,
                p.full_name, p.city, p.state_uf, p.photo_path,
                pr.name AS profession_name
         FROM users u
         LEFT JOIN profiles p   ON p.user_id = u.id
         LEFT JOIN professions pr ON pr.id = p.profession_id
         WHERE " . implode(' AND ', $where) . "
         ORDER BY u.created_at DESC
         LIMIT ?",
        $params
    );
}

// ---- Taxonomia: checar se está em uso ----

function taxonomy_usage_count(string $type, int $id): int {
    return match ($type) {
        'professions' => (int) Database::fetchColumn(
            'SELECT COUNT(*) FROM profiles WHERE profession_id = ? OR profession_secondary_id = ?',
            [$id, $id]
        ),
        'specialties' => (int) Database::fetchColumn(
            'SELECT COUNT(*) FROM user_specialties WHERE specialty_id = ?', [$id]
        ),
        'workshops' => (int) Database::fetchColumn(
            'SELECT COUNT(*) FROM user_workshops WHERE workshop_id = ?', [$id]
        ),
        'interest_tags' => (int) Database::fetchColumn(
            'SELECT COUNT(*) FROM user_interest_tags WHERE tag_id = ?', [$id]
        ),
        default => 0,
    };
}

function taxonomy_labels(): array {
    return [
        'professions'   => ['Profissões',    'profession'],
        'specialties'   => ['Especialidades','specialty'],
        'workshops'     => ['Turmas',        'workshop'],
        'interest_tags' => ['Tags de interesse', 'tag'],
    ];
}

// ---- Admin log ----

function admin_log_action(string $action, ?int $targetUserId = null, array $details = []): void {
    Database::execute(
        'INSERT INTO admin_log (admin_user_id, action, target_user_id, details, ip_address)
         VALUES (?, ?, ?, ?, ?)',
        [
            Session::userId(),
            $action,
            $targetUserId,
            $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : null,
            Request::ip(),
        ]
    );
}

/** Despacha o usuário recém-logado conforme seu estado (consent, status). */
function redirect_after_auth(): void {
    $u = current_user();
    if (!$u) { Session::destroy(); redirect('/'); }

    if ($u['status'] === 'deleted') { Session::destroy(); redirect('/'); }

    if ($u['status'] === 'suspended') {
        // Suspensão voluntária: oferece restaurar, mantém sessão.
        if ((int) ($u['suspended_by_user'] ?? 0) === 1) {
            redirect('/restaurar');
        }
        // Suspensão administrativa: derruba e avisa.
        Session::destroy();
        Flash::error('Sua conta está suspensa. Entre em contato com a equipe.');
        redirect('/');
    }
    if (!$u['consent_lgpd_at']) redirect('/consentimento');
    if ($u['status'] === 'pending') redirect('/aguardando');
    redirect('/');
}

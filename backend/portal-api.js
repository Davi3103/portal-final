// ============================================================
//  portal-api.js  —  Cliente da API PHP
//  Inclua este script em todas as páginas HTML do portal:
//    <script src="portal-api.js"></script>
// ============================================================

// ── CONFIGURAÇÃO ─────────────────────────────────────────────
// Aponte para a pasta "backend" no seu servidor PHP.
// Exemplos:
//   Local:      'http://localhost/portal-compras/backend'
//   Hospedagem: 'https://seusite.com.br/backend'
const API_BASE = 'http://localhost/portal-final/backend';

// ── GERENCIAMENTO DE SESSÃO ───────────────────────────────────
const Session = {
    get()    { return JSON.parse(localStorage.getItem('portal_user') || 'null'); },
    getToken(){ return localStorage.getItem('portal_token') || ''; },
    set(user, token) {
        localStorage.setItem('portal_user',    JSON.stringify(user));
        localStorage.setItem('portal_token',   token);
        localStorage.setItem('portal_auth_ts', Date.now().toString());
    },
    clear() {
        localStorage.removeItem('portal_user');
        localStorage.removeItem('portal_token');
        localStorage.removeItem('portal_auth_ts');
    },
    // Redireciona para login se não autenticado
    require() {
        if (!this.get()) {
            window.location.href = 'login.html';
            return null;
        }
        return this.get();
    }
};

// ── FUNÇÃO CENTRAL DE REQUISIÇÃO ─────────────────────────────
async function apiRequest(endpoint, method = 'GET', body = null, params = {}) {
    let url = `${API_BASE}/api/${endpoint}.php`;

    // Adiciona query string para GET
    if (method === 'GET' && Object.keys(params).length > 0) {
        const qs = new URLSearchParams(params).toString();
        url += '?' + qs;
    }

    const options = {
        method,
        headers: {
            'Content-Type': 'application/json',
            'Authorization': 'Bearer ' + Session.getToken(),
        },
    };
    if (body && method !== 'GET') {
        options.body = JSON.stringify(body);
    }

    try {
        const res  = await fetch(url, options);
        const data = await res.json();

        // Token expirado → volta para login
        if (res.status === 401) {
            Session.clear();
            window.location.href = 'login.html';
            return null;
        }

        return data;   // { ok: true/false, data: ..., erro: ..., msg: ... }
    } catch (err) {
        console.error('[API Error]', err);
        return { ok: false, erro: 'Erro de conexão com o servidor.' };
    }
}

// ══════════════════════════════════════════════════════════════
//  MÓDULOS DA API
// ══════════════════════════════════════════════════════════════

// ── AUTH ─────────────────────────────────────────────────────
const Auth = {
    async login(email, senha) {
        const res = await apiRequest('login', 'POST', { email, senha });
        if (res?.ok) Session.set(res.data.usuario, res.data.token);
        return res;
    },
    logout() {
        Session.clear();
        window.location.href = 'login.html';
    }
};

// ── SOLICITAÇÕES ─────────────────────────────────────────────
const Solicitacoes = {
    listar(filtros = {}) {
        return apiRequest('solicitacoes', 'GET', null, filtros);
    },
    detalhe(id) {
        return apiRequest('solicitacao', 'GET', null, { id });
    },
    criar(dados) {
        return apiRequest('solicitacoes', 'POST', dados);
    },
    atualizarStatus(id, acao, observacao = '') {
        return apiRequest('solicitacao', 'PUT', { id, acao, observacao });
    }
};

// ── PRODUTOS ─────────────────────────────────────────────────
const Produtos = {
    listar(filtros = {}) {
        return apiRequest('produtos', 'GET', null, filtros);
    },
    criar(dados) {
        return apiRequest('produtos', 'POST', dados);
    },
    editar(dados) {
        return apiRequest('produtos', 'PUT', dados);
    }
};

// ── CATEGORIAS ───────────────────────────────────────────────
const Categorias = {
    listar() {
        return apiRequest('categorias', 'GET');
    },
    criar(nome) {
        return apiRequest('categorias', 'POST', { nome });
    }
};

// ── UNIDADES ─────────────────────────────────────────────────
const Unidades = {
    listar(filtros = {}) {
        return apiRequest('unidades', 'GET', null, filtros);
    },
    criar(dados) {
        return apiRequest('unidades', 'POST', dados);
    },
    editar(dados) {
        return apiRequest('unidades', 'PUT', dados);
    }
};

// ── DESTINOS ─────────────────────────────────────────────────
const Destinos = {
    listar(filtros = {}) {
        return apiRequest('destinos', 'GET', null, filtros);
    },
    criar(dados) {
        return apiRequest('destinos', 'POST', dados);
    },
    editar(dados) {
        return apiRequest('destinos', 'PUT', dados);
    }
};

// ── USUÁRIOS ─────────────────────────────────────────────────
const Usuarios = {
    listar(filtros = {}) {
        return apiRequest('usuarios', 'GET', null, filtros);
    },
    criar(dados) {
        return apiRequest('usuarios', 'POST', dados);
    },
    editar(dados) {
        return apiRequest('usuarios', 'PUT', dados);
    },
    inativar(id) {
        return apiRequest('usuarios', 'DELETE', null, { id });
    }
};

// ── RELATÓRIOS ───────────────────────────────────────────────
const Relatorios = {
    geral(periodo = '')    { return apiRequest('relatorios', 'GET', null, { tipo: 'geral',    periodo }); },
    usuarios(periodo = '') { return apiRequest('relatorios', 'GET', null, { tipo: 'usuarios', periodo }); },
    produtos(periodo = '') { return apiRequest('relatorios', 'GET', null, { tipo: 'produtos', periodo }); },
    destinos(periodo = '') { return apiRequest('relatorios', 'GET', null, { tipo: 'destinos', periodo }); },
};

// ══════════════════════════════════════════════════════════════
//  UTILITÁRIOS DE UI
// ══════════════════════════════════════════════════════════════

// Exibe um toast de notificação na tela
function showToast(msg, tipo = 'success') {
    let toast = document.getElementById('_api_toast');
    if (!toast) {
        toast = document.createElement('div');
        toast.id = '_api_toast';
        toast.style.cssText = `
            position:fixed; bottom:24px; right:24px; z-index:9999;
            padding:12px 20px; border-radius:8px; font-size:14px;
            font-family:'Nunito Sans',sans-serif; font-weight:600;
            box-shadow:0 4px 16px rgba(0,0,0,.18); transition:opacity .3s;
            max-width:320px; line-height:1.4;
        `;
        document.body.appendChild(toast);
    }
    toast.style.background = tipo === 'success' ? '#009B77' : '#e05252';
    toast.style.color       = '#fff';
    toast.style.opacity     = '1';
    toast.textContent       = msg;
    clearTimeout(toast._t);
    toast._t = setTimeout(() => { toast.style.opacity = '0'; }, 3500);
}

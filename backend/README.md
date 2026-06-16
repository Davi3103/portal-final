# Backend PHP — Portal de Pedido de Compras

## Estrutura de arquivos

```
backend/
├── config.php          ← ⚠️  EDITE AQUI: dados do seu MySQL
├── helpers.php         ← funções centrais (CORS, JWT, respostas)
├── portal-api.js       ← inclua nas páginas HTML
└── api/
    ├── login.php           POST  – autenticação
    ├── solicitacoes.php    GET | POST  – listar / criar pedidos
    ├── solicitacao.php     GET | PUT   – detalhe / aprovar / recusar
    ├── produtos.php        GET | POST | PUT
    ├── categorias.php      GET | POST
    ├── unidades.php        GET | POST | PUT
    ├── destinos.php        GET | POST | PUT
    ├── usuarios.php        GET | POST | PUT | DELETE
    └── relatorios.php      GET  – dados para a tela de relatórios
```

---

## 1. Pré-requisitos

- PHP 8.1 ou superior com extensão `pdo_mysql`
- MySQL 5.7 / 8.x
- Servidor web com PHP: XAMPP, Laragon, WAMP (local) ou hospedagem compartilhada

---

## 2. Instalação local (XAMPP/Laragon)

1. Copie a pasta `backend/` para dentro de `htdocs/portal-compras/`
2. Execute o script SQL no MySQL:
   ```
   mysql -u root -p < portal_compras_schema.sql
   ```
3. Edite `backend/config.php` com seus dados:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_USER', 'root');
   define('DB_PASS', 'sua_senha');
   define('DB_NAME', 'portal_compras');
   ```
4. Coloque os arquivos HTML na mesma pasta que `backend/`

---

## 3. Conectar os HTMLs ao backend

### 3a. Incluir o script em cada página HTML

Adicione antes do `</body>` em **todos** os HTMLs:

```html
<script src="portal-api.js"></script>
```

### 3b. Substituir o login fictício (login_v3.html)

Localize a função `handleLogin()` e substitua o bloco `setTimeout` por:

```javascript
async function handleLogin() {
    const email = document.getElementById('email').value.trim();
    const senha  = document.getElementById('senha').value;
    // ... validações existentes ...

    btn.classList.add('loading');
    btn.disabled = true;

    const res = await Auth.login(email, senha);

    btn.classList.remove('loading');
    btn.disabled = false;

    if (res?.ok) {
        const user = Session.get();
        document.getElementById('user-name-ok').textContent = user.nome;
        document.getElementById('form-state').style.display = 'none';
        document.getElementById('success-state').classList.add('show');
        setTimeout(() => { window.location.href = 'menu-inicial.html'; }, 2000);
    } else {
        showError(res?.erro || 'Erro ao fazer login.', 'senha');
    }
}
```

### 3c. Carregar produtos dinamicamente (Portal de Pedidos)

Substitua o array estático `AC_DATA` pela chamada à API, logo no início do `<script>`:

```javascript
// Substitui os dados hardcoded por dados do banco
async function carregarDadosPortal() {
    const [unids, dests, prods, cats] = await Promise.all([
        Unidades.listar(),
        Destinos.listar(),
        Produtos.listar(),
        Categorias.listar(),
    ]);

    if (unids?.ok) {
        const lista = unids.data.map(u => ({ codigo: u.codigo, nome: u.nome }));
        AC_DATA['unid-lancamento']  = lista;
        AC_DATA['unid-solicitante'] = lista;
    }
    if (dests?.ok) {
        AC_DATA['destino'] = dests.data.map(d => ({ codigo: d.codigo, nome: d.nome }));
    }
    // Produtos: montar estrutura de categorias para o catálogo
    if (prods?.ok && cats?.ok) {
        renderizarCatalogoProdutos(prods.data, cats.data);
    }
}
carregarDadosPortal();
```

### 3d. Enviar pedido ao banco (Portal de Pedidos)

Na função `confirmOrder()` (ou onde você confirma o pedido), adicione:

```javascript
async function enviarPedido() {
    const user  = Session.require();
    const itens = Object.values(cart).map(item => ({
        produto_id:  item.id,
        quantidade:  item.qty,
    }));

    const payload = {
        data_solicitacao:    document.getElementById('f-data').value,
        data_lancamento:     document.getElementById('f-data-lancamento').value,
        unid_lancamento_id:  AC_SELECTED['unid-lancamento']?.codigo,
        unid_solicitante_id: AC_SELECTED['unid-solicitante']?.codigo,
        destino_id:          AC_SELECTED['destino']?.codigo,
        comprador_id:        AC_SELECTED['comprador']?.codigo,
        prioridade:          document.getElementById('f-prioridade').value,
        tipo_justificativa:  document.getElementById('f-tipo-justificativa').value,
        justificativa:       document.getElementById('f-justificativa').value,
        nome_referencia:     document.getElementById('f-nome').value,
        itens,
    };

    const res = await Solicitacoes.criar(payload);
    if (res?.ok) {
        showToast(`Pedido ${res.data.numero} criado com sucesso!`);
        resetCart();
    } else {
        showToast(res?.erro || 'Erro ao enviar pedido.', 'error');
    }
}
```

### 3e. Carregar Minhas Solicitações

```javascript
async function carregarMinhasSolicitacoes() {
    const filtros = {
        status:  document.getElementById('f-status').value,
        prio:    document.getElementById('f-prio').value,
        periodo: document.getElementById('f-period').value,
        search:  document.getElementById('search').value,
    };
    const res = await Solicitacoes.listar(filtros);
    if (res?.ok) renderTabelaSolicitacoes(res.data);
}
```

### 3f. Carregar Relatórios

```javascript
async function carregarRelatorio(aba) {
    const periodo = document.getElementById('filtro-periodo')?.value || '';
    let res;
    if (aba === 'usuarios') res = await Relatorios.usuarios(periodo);
    if (aba === 'produtos')  res = await Relatorios.produtos(periodo);
    if (aba === 'destinos')  res = await Relatorios.destinos(periodo);
    if (res?.ok) renderTabelaRelatorio(aba, res.data);
}
```

---

## 4. Criar o primeiro usuário admin

Execute no MySQL (troque a senha):

```sql
USE portal_compras;
INSERT INTO usuarios (nome, email, senha_hash, perfil)
VALUES (
    'Administrador',
    'admin@suaempresa.com.br',
    -- Gere o hash com: php -r "echo password_hash('SuaSenha123', PASSWORD_BCRYPT);"
    '$2y$12$COLE_O_HASH_AQUI',
    'admin'
);
```

Ou rode no terminal PHP para gerar o hash:
```bash
php -r "echo password_hash('SuaSenha123', PASSWORD_BCRYPT);"
```

---

## 5. Resumo dos endpoints

| Método | Endpoint               | O que faz                          | Quem acessa        |
|--------|------------------------|------------------------------------|--------------------|
| POST   | /api/login.php         | Autenticação, retorna token JWT    | Todos              |
| GET    | /api/solicitacoes.php  | Lista pedidos (filtros opcionais)  | Logados            |
| POST   | /api/solicitacoes.php  | Cria novo pedido                   | Logados            |
| GET    | /api/solicitacao.php   | Detalhe + itens + histórico        | Logados            |
| PUT    | /api/solicitacao.php   | Aprovar / Recusar / Cancelar       | Logados            |
| GET    | /api/produtos.php      | Lista produtos / busca             | Logados            |
| POST   | /api/produtos.php      | Cria produto                       | Admin              |
| PUT    | /api/produtos.php      | Edita produto                      | Admin              |
| GET    | /api/categorias.php    | Lista categorias                   | Logados            |
| POST   | /api/categorias.php    | Cria categoria                     | Admin              |
| GET    | /api/unidades.php      | Lista unidades (autocomplete)      | Logados            |
| GET    | /api/destinos.php      | Lista destinos (autocomplete)      | Logados            |
| GET    | /api/usuarios.php      | Lista usuários                     | Admin/Comprador    |
| POST   | /api/usuarios.php      | Cria usuário                       | Admin              |
| PUT    | /api/usuarios.php      | Edita usuário                      | Admin              |
| DELETE | /api/usuarios.php      | Inativa usuário                    | Admin              |
| GET    | /api/relatorios.php    | Dados para telas de relatório      | Admin/Comprador    |

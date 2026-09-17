# Planejamento v2, MultiPlay: redesign de biblioteca + gestão da coleção

Objetivo: elevar o visual do app (hoje funcional mas cru) para algo com cara de produto de
verdade, e adicionar as funcionalidades que fazem sentido pra três pessoas (você, seu sogro e o
professor da Jamilly) gerirem a própria coleção de jogos ao longo do tempo, cada um na sua conta.

---

## Decisão sobre o visual (registro da conversa)

Você sugeriu mirar em algo "tipo streaming de jogos" pra ficar mais premium. Concordo com o
diagnóstico (o app hoje é lista de texto plana, capa de 48x64px, um roxo só, sem hierarquia) mas
discordo do alvo literal. Netflix/Prime Video são interfaces de **descoberta**: o problema deles é
"o que eu escolho entre milhares de opções", por isso existem carrossel horizontal, banner hero com
autoplay e curadoria editorial. O MultiPlay resolve um problema diferente: **inventário pessoal**
("eu tenho esse jogo, já joguei ele?"). Carrossel horizontal é ótimo quando tem conteúdo demais pra
caber na tela; numa coleção pessoal, que cresce aos poucos, ele só torna mais difícil de rolar no
celular (arrastar de lado com o polegar é pior que rolar pra baixo, e esconde itens sem indicar
quantos ficaram fora).

O alvo que este plano usa é o de **biblioteca de jogos**: Xbox Game Pass "Minha biblioteca", Steam
ou GOG Galaxy. Grade de capas em vez de lista de texto, selo de status sobre a própria capa,
tipografia mais forte, um acento de cor por fabricante em vez de um roxo genérico. Dá a sensação
premium que você quer, usando os assets que a IGDB já fornece, sem herdar a parte da Netflix que
não serve aqui (carrossel de descoberta, hero autoplay, recomendação algorítmica).

**Decisão registrada:** visibilidade fica privada por usuário (cada um só vê a própria coleção e
status). Sem painel de família compartilhado nesta rodada; fica anotado em "fora do escopo" caso
vocês três decidam mudar de ideia depois.

---

## Frontend: redesign

### Paleta e tokens novos (`src/style.css`)

- Acento de cor por fabricante, usado como detalhe (borda superior do card do console, cor do
  selo de status, ícone da plataforma): `--accent-nintendo` (vermelho), `--accent-playstation`
  (azul), `--accent-xbox` (verde), `--accent-pc` (cinza claro) e `--accent` (roxo atual) como
  fallback pra consoles sem fabricante reconhecido.
- Tipografia: manter `system-ui`, mas aumentar peso e tamanho do título do console e dos nomes de
  jogo na grade (hoje `h1` é 1.5rem só na tela de login).
- Manter o tema escuro e o mínimo de toque de 44px: isso já está certo e não muda.

### Tela de consoles (`ConsolesView.vue`)

Continua em coluna única (rolagem vertical, não carrossel), mas cada linha vira um card maior:

- Tira de até 4 miniaturas de capa dos jogos já importados naquele console (colagem), substituindo
  o texto "N jogos".
- Acento de cor do fabricante como borda superior do card.
- Estatística curta por console: "3 zerados de 12" em vez de só a contagem total.

### Tela do console (`ConsoleView.vue`)

- A lista vertical de jogos vira uma **grade de capas** (`grid-template-columns:
  repeat(auto-fill, minmax(110px, 1fr))`), cada célula é a capa com um selo de status sobreposto
  no canto inferior (cor e ícone por status: troféu dourado pra "zerado", estrela pra "interesse",
  check pra "jogado", relógio pra "quero jogar").
- Como a capa fica pequena na grade, não cabem mais os 4 chips de status inline. Tocar na capa abre
  uma **folha de detalhe** (bottom sheet) com: nome, ano, os 4 chips de status, campo de nota
  pessoal, avaliação em estrelas (só relevante se já estiver "zerado") e botão de favorito. Isso
  evita botão minúsculo demais pra tocar, mantendo a densidade visual da grade.
- Filtro por status e ordenação (nome / ano / adicionado recentemente) no topo da lista de jogos já
  importados, resolvido no cliente com os dados que a API já devolve (sem endpoint novo).

### Tela nova: Início (dashboard)

- Vira a rota `/` (a lista de consoles muda para `/consoles`). Mostra: 4 blocos de estatística
  (contagem por status), tira de favoritos, lista "atualizado recentemente" (últimas mudanças de
  status, com nome do jogo e console) e atalho pra busca global.
- Navegação passa a ter uma barra inferior fixa (Início / Consoles / Buscar), mais alcançável com
  uma mão só em celular grande, o que ajuda especificamente seu sogro e o professor.

### Busca global ("cadê aquele jogo que eu tenho")

- Campo de busca na barra inferior ou na tela de Início, que busca dentro da **própria coleção já
  importada** (não a IGDB) e mostra em qual console cada resultado está. Resolve "eu sei que tenho
  esse jogo mas não lembro em qual console importei".

---

## Backend: funcionalidades novas

### Notas, avaliação e favorito por jogo (por usuário)

Hoje `game_user` só tem `status`. Adicionar:

- `notes` (texto, opcional): anotação livre ("joguei até a fase 3", "emprestei pro Fulano").
- `rating` (inteiro 1 a 5, opcional): nota pessoal, sentido principal quando o status é "zerado".
- `favorite` (booleano, padrão falso).

Migration altera `game_user` acrescentando essas 3 colunas. `POST /games/{id}/status` continua como
está; endpoint novo `PATCH /games/{id}/detail` recebe `{notes?, rating?, favorite?}` e faz
`syncWithoutDetaching` só dos campos enviados (não pode marcar detalhe de um jogo sem status: se não
existir linha no pivot, cria com `status: null` antes).

### CRUD de consoles (hoje só existe seed manual)

Adicionar coluna `user_id` (nullable, FK) em `consoles`. Console com `user_id` nulo é o catálogo
global (os 8 do seed, visíveis pra todo mundo); console com `user_id` preenchido foi criado por
aquele usuário e só aparece pra ele (consistente com a decisão de visibilidade privada). Isso deixa
seu sogro adicionar "PC" ou "Dreamcast" sem sujar a lista do professor.

- `GET /consoles` passa a devolver `where user_id is null OR user_id = auth()->id()`, ordenado por
  `sort_order` e depois por nome pros consoles custom (que não têm sort_order fixo).
- `POST /consoles` (novo): `{name, manufacturer, release_year, igdb_platform_id?}`. Se o usuário não
  souber o `igdb_platform_id`, endpoint `GET /igdb/platforms/search?q=` (expõe o
  `IgdbClient::platforms` que já existe, hoje só acessível via comando artisan) ajuda a achar o ID
  certo antes de criar.

### Estatísticas agregadas (dashboard)

`GET /me/stats` (novo): conta os jogos do usuário logado por status, total de favoritos, e as
últimas N linhas de `game_user` atualizadas (nome do jogo, console, status, `updated_at`) pra
alimentar "atualizado recentemente".

### Busca na própria coleção

`GET /games/search?q=` (novo): busca por nome nos jogos que o usuário logado já importou (via
`game_user`), em qualquer console, devolvendo nome do console junto. Diferente do
`/consoles/{id}/igdb/search`, que busca jogos novos na IGDB pra importar.

### Import em lote (P2, menor prioridade)

`POST /consoles/{id}/games/bulk`: aceita lista de resultados da IGDB de uma vez (útil se o sogro
tiver uma coleção física grande pra cadastrar de uma vez, em vez de um por um).

---

## Fora do escopo desta rodada

- Painel de família compartilhado (decisão já tomada: privada por usuário).
- Empréstimo de jogo físico entre vocês três (quem está com o cartucho de quem). Fica pra decidir
  depois, se fizer falta na prática.
- Notificações/lembretes ("você tem 5 jogos em 'quero jogar' há 6 meses").
- Edição/exclusão de console custom (esta rodada só cria).
- Build Android via Capacitor (segue dependendo do Android Studio, sem mudança aqui).

---

## Roteiro de execução (fases, cada uma entregável sozinha)

1. Tokens de cor por fabricante + acento no card do console e no selo de status (só CSS, sem
   mudança de banco).
2. Grade de capas na tela do console + bottom sheet de detalhe (move os chips de status pra dentro
   da folha).
3. Migration de `game_user` (`notes`, `rating`, `favorite`) + endpoint `PATCH /games/{id}/detail` +
   campos correspondentes na folha de detalhe.
4. Migration de `consoles` (`user_id`) + endpoints `POST /consoles` e `GET /igdb/platforms/search`
   + tela/formulário "Adicionar console".
5. Endpoint `GET /me/stats` + tela de Início (dashboard) + navegação por barra inferior.
6. Endpoint `GET /games/search` + campo de busca global.
7. (Opcional, P2) `POST /consoles/{id}/games/bulk` + UX de seleção múltipla na busca IGDB.

## Checklist "v2 pronto" (fases 1 a 7 entregues e verificadas no browser em 14/09/2026; bulk só no backend, sem UX de seleção múltipla)

- [x] Paleta por fabricante aplicada em card de console e selo de status
- [x] Grade de capas substitui a lista de texto na tela do console
- [x] Bottom sheet de detalhe do jogo (chips de status + nota + avaliação + favorito)
- [x] `game_user` com `notes`, `rating`, `favorite` migrado e testado
- [x] CRUD de consoles custom (`user_id` nullable) funcionando e isolado por usuário
- [x] Dashboard de Início com estatísticas e navegação por barra inferior
- [x] Busca global na própria coleção
- [x] Testes de feature novos cobrindo cada endpoint novo (mesmo padrão TDD da v1)

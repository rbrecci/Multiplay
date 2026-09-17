# MultiPlay: contrato da API (v2)

Base URL em dev: `http://localhost:8000/api`. Auth: Sanctum em modo token.
Toda rota exceto `register` e `login` exige header `Authorization: Bearer {token}`.
Respostas sempre JSON. Erros de validacao: 422 no formato padrao do Laravel (`{message, errors}`).
Nao autenticado: 401 `{message: "Unauthenticated."}`.

## Auth

POST /register        body {name, email, password}            -> 201 {token, user:{id,name,email}}
POST /login           body {email, password}                  -> 200 {token, user:{id,name,email}}
                                                                 401 {message} se credencial invalida
POST /logout          (auth)                                  -> 204
GET  /me              (auth)                                  -> 200 {id,name,email}

GET  /me/stats        (auth) -> 200 {
                        counts: {jogado, interesse, zerado, quero_jogar},   // int cada, so linhas com status
                        favorites_count: int,
                        favorites: [ {game_id, name, cover_url, console:{id, name}} ],
                        recent:    [ {game_id, name, cover_url, status, console:{id, name}, updated_at} ]
                      }
                      favorites = ate 12, favoritos mais recentemente atualizados primeiro
                      recent = ultimas 10 linhas de game_user do usuario por updated_at desc (status pode ser null)
                      updated_at em ISO 8601 (ex.: 2026-09-14T21:00:00+00:00)
                      so linhas de game_user do usuario logado

## Consoles

Console com `user_id` null e global (seed), visivel pra todo mundo. Console com `user_id`
preenchido e custom: so existe pro dono. Toda rota `/consoles/{id}/...` responde 404 se o
console for custom de outro usuario (mesmo 404 de console inexistente).

GET /consoles         (auth) -> 200 [ {id, name, manufacturer, release_year, sort_order, igdb_platform_id,
                                        user_id, games_count, completed_count, covers} ]
                      globais primeiro (sort_order asc), depois os custom do usuario logado
                      (release_year asc, name asc); sort_order e null nos custom
                      completed_count = jogos desse console que o usuario logado marcou como `zerado`
                      covers = ate 4 cover_url nao nulos dos jogos desse console, importados mais
                      recentemente primeiro

POST /consoles        (auth) body {name (max 100), manufacturer (max 60), release_year (1970..2100),
                                   igdb_platform_id? (int >= 1 ou null)}
                      -> 201 {id, name, manufacturer, release_year, sort_order:null, igdb_platform_id,
                              user_id, games_count:0, completed_count:0, covers:[]}
                      cria console custom do usuario logado

GET /consoles/{id}/games   (auth) -> 200 {
                                      console: {id, name, manufacturer, release_year, igdb_platform_id, user_id},
                                      games: [ {id, igdb_id, name, cover_url, first_release_year,
                                                status, notes, rating, favorite, created_at} ]
                                    }
                      status = "jogado" | "interesse" | "zerado" | "quero_jogar" | null (do usuario logado)
                      notes, rating, favorite = detalhe do usuario logado (sem linha em game_user:
                      null, null, false); favorite sempre bool
                      created_at = data de importacao do jogo, ISO 8601
                      games ordenados por name asc

## IGDB (proxy, o Client Secret nunca sai do backend)

GET /consoles/{id}/igdb/search?q=texto  (auth) -> 200 [ {igdb_id, name, cover_url, first_release_year, game_id} ]
                      game_id = id em `games` se esse jogo ja foi importado nesse console, senao null
                      cover_url ja vem em tamanho `t_cover_big` com `https:` na frente
                      q obrigatorio, min 2 chars (422 se faltar)
                      503 {message} se a IGDB nao estiver configurada (sem IGDB_CLIENT_ID/SECRET no .env)
                      502 {message} se a IGDB responder erro

GET /igdb/platforms/search?q=texto  (auth) -> 200 [ {id, name, abbreviation} ]
                      busca plataformas na IGDB pra preencher igdb_platform_id de um console custom
                      abbreviation pode ser null
                      q obrigatorio, min 2 chars (422 se faltar); 503 / 502 iguais a busca de jogos

## Jogos e status

POST /consoles/{id}/games   (auth) body {igdb_id, name, cover_url?, first_release_year?}
                      -> 201 {id, igdb_id, name, cover_url, first_release_year, status:null}
                      -> 200 com o jogo existente se (console_id, igdb_id) ja existir (idempotente)

POST /consoles/{id}/games/bulk   (auth) body {games: [ {igdb_id, name, cover_url?, first_release_year?} ]}
                      games obrigatorio, 1 a 50 itens, cada item com as mesmas regras do POST unitario
                      -> 200 {games: [ {id, igdb_id, name, cover_url, first_release_year, status} ]}
                      idempotente por (console_id, igdb_id), na mesma ordem do body; status e do usuario logado

POST /games/{id}/status     (auth) body {status: "jogado"|"interesse"|"zerado"|"quero_jogar"|null}
                      -> 200 {game_id, status}
                      null zera o status; se a linha ficar sem notes, sem rating e favorite false,
                      a linha em game_user e apagada (senao fica, com status null)

PATCH /games/{id}/detail    (auth) body {notes?: string|null (max 2000), rating?: int|null (1..5), favorite?: bool}
                      pelo menos um dos tres tem que vir (422 se body vazio)
                      -> 200 {game_id, status, notes, rating, favorite}
                      altera so os campos enviados; cria a linha em game_user com status null se nao existir
                      favorite sempre bool

GET /games/search?q=texto   (auth) -> 200 [ {id, name, cover_url, first_release_year, status, favorite,
                                              console:{id, name, manufacturer}} ]
                      busca na colecao do usuario: LIKE %q% no name, so jogos com linha em game_user
                      do usuario logado, em qualquer console; ordenado por name, limite 50
                      q obrigatorio, min 2 chars (422 se faltar)
                      nao confundir com /consoles/{id}/igdb/search, que busca jogos novos na IGDB

## Modelo

users      (id, name, email, password, timestamps)
consoles   (id, user_id FK nullable (cascade), name, manufacturer, release_year, sort_order nullable,
            igdb_platform_id nullable, timestamps)
            user_id null = global (seed); preenchido = custom do usuario, sort_order null
games      (id, console_id FK, igdb_id, name, cover_url nullable, first_release_year nullable, timestamps)
            unique(console_id, igdb_id)
game_user  (id, user_id FK, game_id FK, status enum nullable, notes text nullable,
            rating tinyint nullable (1..5), favorite bool default false, timestamps)
            unique(user_id, game_id)

Seed de consoles globais (sort_order = ordem cronologica, user_id null). IDs de plataforma da IGDB abaixo sao os conhecidos
publicamente e devem ser confirmados com `php artisan igdb:platforms "nome"` assim que houver credencial:

| sort | name                  | manufacturer | release_year | igdb_platform_id |
|------|-----------------------|--------------|--------------|------------------|
| 1    | NES                   | Nintendo     | 1983         | 18               |
| 2    | Super Nintendo        | Nintendo     | 1990         | 19               |
| 3    | PlayStation           | Sony         | 1994         | 7                |
| 4    | PlayStation 2         | Sony         | 2000         | 8                |
| 5    | Xbox 360              | Microsoft    | 2005         | 12               |
| 6    | PlayStation 4         | Sony         | 2013         | 48               |
| 7    | Nintendo Switch       | Nintendo     | 2017         | 130              |
| 8    | PlayStation 5         | Sony         | 2020         | 167              |

Usuario de demonstracao (seed): demo@multiplay.local / senha `multiplay`.

## CORS

Origens liberadas: http://localhost:5173, http://127.0.0.1:5173, capacitor://localhost, http://localhost

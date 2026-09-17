# Planejamento MVP: Catálogo de Consoles (IGDB + build mobile)

Objetivo: terminar o dia com um app funcional instalado no celular (via Capacitor, com ícone próprio), catálogo de consoles do mais antigo ao mais novo, jogos importados da IGDB, e marcação de status por usuário: **já joguei / tenho interesse / já zerei / quero jogar**.

---

## Stack (decidida)

- **Backend**: Laravel API pura (sem Inertia) + **Sanctum em modo token** (não sessão/cookie: necessário pro app empacotado, ver nota abaixo)
- **Banco**: SQLite pra hoje (zero setup); migra pra MySQL depois se for pra produção
- **Frontend**: Vue 3 + Vite, SPA independente (consome a API)
- **Mobile**: **Capacitor** empacota o build do Vue → APK Android instalável, com ícone e splash screen próprios
- **Catálogo de jogos**: **IGDB API** (via Twitch OAuth), proxied pelo backend Laravel

### Por que essas escolhas (resumo das discussões de hoje)
- **Laravel > Flask** pro backend: Sanctum, Eloquent (`withPivot`), migrations e seeders resolvem de cara exatamente o que esse projeto precisa. Flask exigiria montar na mão auth, migrations e ORM combinando várias libs, o que é mais superfície de erro numa sessão só.
- **Vue+Capacitor > Flet** pro frontend/mobile: Capacitor empacota uma WebView (leve, rápido de buildar); `flet build apk` compila um Flutter de verdade por baixo, pipeline mais pesado e com mais issues conhecidos de build quebrando. Vue+Capacitor também tem muito mais padrão documentado pra essa combinação específica (API Laravel + SPA empacotado).
- **Sanctum em modo token, não SPA/cookie**: o Capacitor roda em `capacitor://localhost`, domínio diferente da API, e cookie same-domain não funciona bem aí. Usa `createToken()` (Bearer token), como um cliente mobile de verdade.

---

## ⚠️ Checkpoint antes de começar

Você já tem **Android Studio + SDK** instalados?
- **Sim** → segue o plano como está, build vira um APK real hoje.
- **Não** → plano B: monta tudo igual (API + Vue + IGDB), mas o "build pro celular" de hoje vira um **PWA** (instala na tela inicial, zero setup extra), e o Capacitor/APK fica pra assim que o Android Studio estiver pronto.

---

## Escopo do MVP (entra hoje)

- CRUD de consoles (seed manual, mais rápido que puxar da IGDB)
- Busca e importação de **jogos via IGDB** dentro de cada console
- Marcação de status por jogo, por usuário logado
- Lista de consoles ordenada do mais antigo pro mais novo
- Tela do console: jogos já importados + campo de busca IGDB pra adicionar mais
- Login simples (Sanctum, token)
- Build Android via Capacitor **com ícone e splash screen próprios** (ou PWA, conforme o checkpoint)

## Fora do escopo (fica pra depois)

- Steam / PSN / Xbox
- iOS (Capacitor suporta, mas precisa de Mac + Xcode)
- Build assinada de produção / publicação na Play Store
- Design refinado das telas (hoje é funcional, não bonito; só o ícone e o splash saem cuidados)

---

## Modelagem do banco

```
users            (id, name, email, password)

consoles         (id, name, manufacturer, release_year, sort_order)

games            (id, console_id [FK], igdb_id, name, cover_url)

game_user        (id, user_id [FK], game_id [FK], status [enum], updated_at)
                  status: jogado | interesse | zerado | quero_jogar
                  unique(user_id, game_id)
```

- `Console hasMany Games`
- `Game belongsToMany Users` via `game_user`, com `withPivot('status')`
- `igdb_id` no `games` evita importar o mesmo jogo duas vezes

---

## Integração IGDB: passo a passo

1. Criar um app em https://dev.twitch.tv/console/apps → pegar **Client ID** e **Client Secret**
2. Backend obtém um app access token via `POST https://id.twitch.tv/oauth2/token` (client_credentials) e **cacheia** esse token (Laravel Cache, dura semanas)
3. Endpoint próprio: `GET /api/igdb/search?platform_id=&q=` → chama `POST https://api.igdb.com/v4/games` com headers `Client-ID` e `Authorization: Bearer {token}`, corpo em Apicalypse:
   ```
   fields name,cover.url,platforms,first_release_date;
   search "{q}";
   where platforms = ({platform_id});
   ```
4. Nunca expor o Client Secret pro frontend: toda chamada IGDB passa pelo Laravel
5. Ao importar um jogo da busca, salvar em `games` com o `igdb_id`, vinculado ao console atual
6. Pra descobrir o `platform_id` certo de cada console, consultar o endpoint `/platforms` da IGDB durante a implementação; não fixar os IDs de cabeça, confirmar na hora

---

## Ícone e splash screen do app

Usa o pacote oficial `@capacitor/assets`:

1. `npm install --save-dev @capacitor/assets`
2. Colocar uma imagem fonte quadrada (mín. 1024x1024) em `assets/icon.png` na raiz do projeto Vue
3. (Opcional) splash screen própria em `assets/splash.png` (mín. 2732x2732); se não tiver, o comando gera um splash simples a partir do ícone
4. Rodar:
   ```
   npx capacitor-assets generate --iconBackgroundColor '#ffffff' --splashBackgroundColor '#ffffff'
   ```
5. Nome do app e ID do pacote (ex: `com.brecci.catalogoconsoles`) vão em `capacitor.config.json`, campos `appName` e `appId`, definidos no `npx cap init`

Isso já deixa o app com identidade própria na tela do celular, em vez do ícone padrão do Capacitor.

---

## Rotas da API

```
POST /api/login                          → Sanctum token
GET  /api/consoles                       → lista ordenada
GET  /api/consoles/{console}/games       → jogos já importados + status do user
GET  /api/igdb/search                    → busca na IGDB (proxy)
POST /api/consoles/{console}/games       → importa jogo da IGDB pro console
POST /api/games/{game}/status            → marca/atualiza status do user logado
```

---

## Roteiro de execução da sessão

0. **Checkpoint Android Studio** (ver acima) + criar app no Twitch Dev Console
1. `laravel new catalogo-consoles-api` (API only, Sanctum)
2. `npm create vite@latest catalogo-consoles-app -- --template vue`
3. Migrations: `consoles`, `games`, `game_user`
4. `ConsoleSeeder` com 5-8 consoles clássicos (NES, SNES, PS1, PS2, Xbox 360, PS4, Switch, PS5)
5. Implementar o proxy IGDB (token cacheado + endpoint de busca)
6. Endpoints de auth, import de jogo, e status
7. Configurar CORS no Laravel liberando a origem do app Capacitor (`capacitor://localhost`) e do Vite dev (`http://localhost:5173`)
8. Telas Vue: login, lista de consoles, tela do console (busca IGDB + lista + botões de status)
9. `npm run build` do Vue
10. `npx cap init` → `npx cap add android` → `npx cap sync`
11. Gerar ícone e splash screen (`@capacitor/assets`, ver seção acima)
12. `npx cap sync` de novo (pra aplicar os assets) → `npx cap run android` (device via USB ou emulador), ou abrir no Android Studio e gerar o APK debug direto
13. Testar o fluxo completo no celular: login → console → buscar/importar jogo na IGDB → marcar status → fechar e abrir de novo pra confirmar que persistiu, e conferir se o ícone/splash aparecem certos

---

## Checklist "MVP pronto hoje"

- [ ] Client ID/Secret da IGDB configurados e token sendo obtido com sucesso
- [ ] API Laravel rodando com auth por token
- [ ] Seed de consoles aplicado
- [ ] Busca IGDB funcionando e importando jogo pro console certo
- [ ] Status sendo salvo e persistindo por usuário
- [ ] CORS liberado pro app empacotado
- [ ] Ícone e splash screen próprios gerados e aplicados
- [ ] Build gerado (APK via Capacitor **ou** PWA, conforme o checkpoint) e testado num celular real

## Próximos passos (pós-MVP)

- Integração Steam Web API
- Build de produção assinada + ficha na Play Store
- iOS via Capacitor (precisa de Mac)
- Design refinado das telas

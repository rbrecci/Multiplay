# Multiplay

Catálogo pessoal de consoles e jogos, do mais antigo ao mais novo, com jogos importados da IGDB e marcação de status por usuário (já joguei, tenho interesse, já zerei, quero jogar).

## Stack

- **API**: Laravel (Sanctum em modo token, sem sessão/cookie) — pasta [api](api)
- **App**: Vue 3 + Vite, SPA que consome a API — pasta [app](app)
- **Catálogo de jogos**: IGDB API, via proxy no backend Laravel
- **Mobile**: build empacotado com Capacitor

## Estrutura

```
api/     API Laravel (backend, autenticação, proxy da IGDB)
app/     SPA em Vue 3 (frontend web e base do build mobile)
docs/    contrato da API e outras notas de projeto
deploy/  pacote de deploy para o InfinityFree (fora do repositório, contém credenciais)
```

Cada uma das pastas `api` e `app` tem seu próprio README com instruções específicas de instalação e execução.

## Documentação

- [docs/api-contract.md](docs/api-contract.md) — contrato da API entre backend e frontend
- [mvp-catalogo-consoles-planejamento.md](mvp-catalogo-consoles-planejamento.md) — planejamento do MVP
- [melhorias-v2-planejamento.md](melhorias-v2-planejamento.md) — planejamento das melhorias da v2

## Status

Publicado em `multiplay.infinityfreeapp.com` via InfinityFree.
